<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Salon\StoreSalonRequest;
use App\Http\Requests\Salon\UpdateSalonRequest;
use App\Http\Resources\SalonResource;
use App\Models\Salon;
use App\Models\SalonImage;
use App\Services\CacheService;
use App\Services\SalonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class SalonController extends Controller
{
    protected $salonService;
    protected $cacheService;

    public function __construct(SalonService $salonService, CacheService $cacheService)
    {
        $this->salonService = $salonService;
        $this->cacheService = $cacheService;
    }

    /**
     * Display a listing of the salons.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Salon::query()->approved();

        // Filter by city
        if ($request->has('city')) {
            $query->where('city', 'like', '%' . $request->city . '%');
        }

        // Filter by service
        if ($request->has('service')) {
            $query->whereHas('services', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->service . '%')
                  ->orWhere('category', 'like', '%' . $request->service . '%');
            });
        }

        // Filter by target audience
        if ($request->has('target_audience')) {
            $audience = json_decode($request->target_audience, true);
            $query->forAudience($audience);
        }

        // Filter by rating
        if ($request->has('min_rating')) {
            $query->where('rating', '>=', $request->min_rating);
        }

        // Search by name or description
        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%')
                  ->orWhere('address', 'like', '%' . $request->search . '%');
            });
        }

        // Order by
        if ($request->has('order_by')) {
            $orderBy = $request->order_by;
            $direction = $request->order_direction ?? 'asc';

            if ($orderBy === 'rating') {
                $query->orderBy('rating', $direction);
            } elseif ($orderBy === 'review_count') {
                $query->orderBy('review_count', $direction);
            } elseif ($orderBy === 'name') {
                $query->orderBy('name', $direction);
            }
        } else {
            $query->orderBy('rating', 'desc');
        }

        $salons = $query->with(['images', 'services', 'staff'])->paginate($request->per_page ?? 15);

        return SalonResource::collection($salons);
    }

    /**
     * Store a newly created salon in storage.
     */
    public function store(StoreSalonRequest $request): JsonResponse
    {
        $salon = $this->salonService->createSalon($request->validated(), $request->user());

        return response()->json([
            'message' => 'Salon created successfully',
            'salon' => new SalonResource($salon),
        ], 201);
    }

    /**
     * Display the specified salon.
     */
    public function show(Salon $salon): SalonResource
    {
        // Load relations without caching to ensure fresh data with proper URLs
        $salon->load(['images', 'services.staff', 'staff', 'reviews', 'salonBreaks', 'salonVacations']);

        return new SalonResource($salon);
    }

    /**
     * Update the specified salon in storage.
     */
    public function update(UpdateSalonRequest $request, Salon $salon): JsonResponse
    {
        Gate::authorize('update', $salon);

        $salon = $this->salonService->updateSalon($salon, $request->validated());

        // Invalidate cache after update
        $this->cacheService->invalidateSalonCache($salon->id);

        return response()->json([
            'message' => 'Salon updated successfully',
            'salon' => new SalonResource($salon),
        ]);
    }

    /**
     * Remove the specified salon from storage.
     */
    public function destroy(Salon $salon): JsonResponse
    {
        Gate::authorize('delete', $salon);

        $salonId = $salon->id;
        $salon->delete();

        // Invalidate cache after deletion
        $this->cacheService->invalidateSalonCache($salonId);

        return response()->json([
            'message' => 'Salon deleted successfully',
        ]);
    }

    /**
     * Upload images for the salon.
     */
    public function uploadImages(Request $request, Salon $salon): JsonResponse
    {
        try {
            Gate::authorize('update', $salon);

            // Check if images were uploaded
            if (!$request->hasFile('images')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Morate odabrati najmanje jednu sliku.',
                ], 422);
            }

            $images = $request->file('images');

            // Ensure images is an array
            if (!is_array($images)) {
                $images = [$images];
            }

            // Validate each image
            foreach ($images as $index => $image) {
                if (!$image->isValid()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Greška pri uploadu slike ' . ($index + 1),
                    ], 422);
                }

                // Check file size (5MB max)
                if ($image->getSize() > 5 * 1024 * 1024) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Slika ' . $image->getClientOriginalName() . ' je prevelika. Maksimalna veličina je 5MB.',
                    ], 422);
                }

                // Check mime type
                $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
                if (!in_array($image->getMimeType(), $allowedMimes)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Slika ' . $image->getClientOriginalName() . ' mora biti u formatu: jpeg, png, jpg ili webp.',
                    ], 422);
                }
            }

            // Check max 10 images
            if (count($images) > 10) {
                return response()->json([
                    'success' => false,
                    'message' => 'Možete uploadovati maksimalno 10 slika odjednom.',
                ], 422);
            }

            $uploadedImages = [];

            foreach ($images as $image) {
                $path = $image->store('salons/' . $salon->id, 'public');

                // PostgreSQL requires explicit boolean casting - use raw SQL
                $isPrimary = $salon->images()->count() === 0;
                $order = $salon->images()->count() + 1;

                // Insert with raw SQL to handle PostgreSQL boolean type
                $imageId = \DB::table('salon_images')->insertGetId([
                    'salon_id' => $salon->id,
                    'path' => $path,
                    'order' => $order,
                    'is_primary' => \DB::raw($isPrimary ? 'true' : 'false'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Load the created image
                $salonImage = SalonImage::find($imageId);

                // Explicitly include URL in response
                $uploadedImages[] = [
                    'id' => $salonImage->id,
                    'path' => $salonImage->path,
                    'url' => $salonImage->url,
                    'is_primary' => $salonImage->is_primary,
                    'order' => $salonImage->order,
                ];
            }

            // Invalidate salon cache after image upload
            $this->cacheService->invalidateSalonCache($salon->id);

            return response()->json([
                'message' => 'Images uploaded successfully',
                'images' => $uploadedImages,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validacija nije uspjela.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Error uploading salon images: ' . $e->getMessage(), [
                'salon_id' => $salon->id,
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Greška prilikom uploada slika. Molimo pokušajte ponovo.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Delete an image from the salon.
     */
    public function deleteImage(Salon $salon, SalonImage $image): JsonResponse
    {
        Gate::authorize('update', $salon);

        if ($image->salon_id !== $salon->id) {
            return response()->json([
                'message' => 'Image does not belong to this salon',
            ], 403);
        }

        Storage::disk('public')->delete($image->path);
        $image->delete();

        // If the deleted image was primary, set the first remaining image as primary
        if ($image->is_primary) {
            $firstImage = $salon->images()->first();
            if ($firstImage) {
                $firstImage->update(['is_primary' => true]);
            }
        }

        // Invalidate salon cache after image deletion
        $this->cacheService->invalidateSalonCache($salon->id);

        return response()->json([
            'message' => 'Image deleted successfully',
        ]);
    }

    /**
     * Set an image as primary for the salon.
     */
    public function setPrimaryImage(Salon $salon, SalonImage $image): JsonResponse
    {
        Gate::authorize('update', $salon);

        if ($image->salon_id !== $salon->id) {
            return response()->json([
                'message' => 'Image does not belong to this salon',
            ], 403);
        }

        // Reset all images to non-primary
        $salon->images()->update(['is_primary' => false]);

        // Set the selected image as primary
        $image->update(['is_primary' => true]);

        return response()->json([
            'message' => 'Primary image set successfully',
        ]);
    }

    /**
     * Get the nearest salons based on location.
     */
    public function nearest(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'radius' => 'sometimes|numeric|min:1|max:50', // in kilometers
        ]);

        $latitude = $request->latitude;
        $longitude = $request->longitude;
        $radius = $request->radius ?? 10; // Default 10km

        $salons = $this->salonService->getNearestSalons($latitude, $longitude, $radius);

        return SalonResource::collection($salons);
    }

    /**
     * Get available time slots for a salon.
     */
    public function availableSlots(Request $request, Salon $salon): JsonResponse
    {
        $request->validate([
            'date' => 'required|date_format:d.m.Y',
            'staff_id' => 'required|exists:staff,id',
            'service_id' => 'required|exists:services,id',
            'duration' => 'nullable|integer|min:1', // Optional: total duration if multiple services
        ]);

        $slots = $this->salonService->getAvailableTimeSlots(
            $salon,
            $request->staff_id,
            $request->date,
            $request->service_id,
            $request->duration // Pass duration parameter
        );

        return response()->json([
            'slots' => $slots,
        ]);
    }

    /**
     * Get available time slots for multiple services with different staff members.
     * This is the PROFESSIONAL solution for booking multiple services.
     */
    public function availableSlotsMulti(Request $request, Salon $salon): JsonResponse
    {
        $request->validate([
            'date' => 'required|date_format:d.m.Y',
            'services' => 'required|array|min:1',
            'services.*.serviceId' => 'required|exists:services,id',
            'services.*.staffId' => 'required|exists:staff,id',
            'services.*.duration' => 'required|integer|min:1',
        ]);

        $slots = $this->salonService->getAvailableTimeSlotsForMultipleServices(
            $salon,
            $request->date,
            $request->services
        );

        return response()->json([
            'slots' => $slots,
        ]);
    }
}
