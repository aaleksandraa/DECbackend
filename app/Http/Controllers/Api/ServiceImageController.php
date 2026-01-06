<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ServiceImageController extends Controller
{
    /**
     * Upload a new service image.
     */
    public function store(Request $request, $serviceId)
    {
        $service = Service::findOrFail($serviceId);

        // Check authorization
        $this->authorize('update', $service);

        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,jpg,png,webp|max:5120', // 5MB max
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_featured' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Get the next order number
        $maxOrder = $service->images()->max('order') ?? 0;

        // Store the image
        $path = $request->file('image')->store('services', 'public');

        // PostgreSQL requires explicit boolean - use raw SQL
        $isFeatured = $request->input('is_featured', false);

        $imageId = \DB::table('service_images')->insertGetId([
            'service_id' => $service->id,
            'image_path' => $path,
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'order' => $maxOrder + 1,
            'is_featured' => \DB::raw($isFeatured ? 'true' : 'false'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $serviceImage = ServiceImage::find($imageId);

        return response()->json([
            'message' => 'Image uploaded successfully',
            'image' => $serviceImage
        ], 201);
    }

    /**
     * Update service image details.
     */
    public function update(Request $request, $serviceId, $imageId)
    {
        $service = Service::findOrFail($serviceId);
        $this->authorize('update', $service);

        $image = ServiceImage::where('service_id', $serviceId)
            ->where('id', $imageId)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_featured' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // PostgreSQL requires explicit boolean - use raw SQL for update
        $updateData = [
            'title' => $request->input('title', $image->title),
            'description' => $request->input('description', $image->description),
            'updated_at' => now(),
        ];

        if ($request->has('is_featured')) {
            $isFeatured = $request->input('is_featured');
            $updateData['is_featured'] = \DB::raw($isFeatured ? 'true' : 'false');
        }

        \DB::table('service_images')
            ->where('id', $image->id)
            ->update($updateData);

        // Reload the image
        $image = ServiceImage::find($image->id);

        return response()->json([
            'message' => 'Image updated successfully',
            'image' => $image
        ]);
    }

    /**
     * Reorder service images.
     */
    public function reorder(Request $request, $serviceId)
    {
        $service = Service::findOrFail($serviceId);
        $this->authorize('update', $service);

        $validator = Validator::make($request->all(), [
            'images' => 'required|array',
            'images.*.id' => 'required|integer|exists:service_images,id',
            'images.*.order' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        foreach ($request->input('images') as $imageData) {
            ServiceImage::where('id', $imageData['id'])
                ->where('service_id', $serviceId)
                ->update(['order' => $imageData['order']]);
        }

        return response()->json([
            'message' => 'Images reordered successfully'
        ]);
    }

    /**
     * Delete a service image.
     */
    public function destroy($serviceId, $imageId)
    {
        $service = Service::findOrFail($serviceId);
        $this->authorize('update', $service);

        $image = ServiceImage::where('service_id', $serviceId)
            ->where('id', $imageId)
            ->firstOrFail();

        // Delete the file from storage
        if (Storage::disk('public')->exists($image->image_path)) {
            Storage::disk('public')->delete($image->image_path);
        }

        // Delete the database record
        $image->delete();

        return response()->json([
            'message' => 'Image deleted successfully'
        ]);
    }
}
