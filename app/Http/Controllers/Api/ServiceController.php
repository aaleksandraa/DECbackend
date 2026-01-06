<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Service\StoreServiceRequest;
use App\Http\Requests\Service\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Salon;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServiceController extends Controller
{
    /**
     * Display a listing of the services for a salon.
     */
    public function index(Salon $salon): AnonymousResourceCollection
    {
        $services = $salon->services()->with(['staff', 'images'])->get();

        return ServiceResource::collection($services);
    }

    /**
     * Store a newly created service in storage.
     */
    public function store(StoreServiceRequest $request, Salon $salon): JsonResponse
    {
        $this->authorize('update', $salon);

        $service = $salon->services()->create($request->validated());

        if ($request->has('staff_ids')) {
            $service->staff()->sync($request->staff_ids);
        }

        // Invalidate cache
        \App\Services\CacheService::invalidateSalon($salon->id, $salon->slug);
        \Illuminate\Support\Facades\Cache::forget('services.popular');

        return response()->json([
            'message' => 'Service created successfully',
            'service' => new ServiceResource($service->load('staff')),
        ], 201);
    }

    /**
     * Display the specified service.
     */
    public function show(Salon $salon, Service $service): ServiceResource
    {
        if ($service->salon_id !== $salon->id) {
            abort(404);
        }

        $service->load('staff');

        return new ServiceResource($service);
    }

    /**
     * Update the specified service in storage.
     */
    public function update(UpdateServiceRequest $request, Salon $salon, Service $service): JsonResponse
    {
        $this->authorize('update', $salon);

        if ($service->salon_id !== $salon->id) {
            abort(404);
        }

        $service->update($request->validated());

        if ($request->has('staff_ids')) {
            $service->staff()->sync($request->staff_ids);
        }

        // Invalidate cache
        \App\Services\CacheService::invalidateSalon($salon->id, $salon->slug);
        \Illuminate\Support\Facades\Cache::forget('services.popular');

        return response()->json([
            'message' => 'Service updated successfully',
            'service' => new ServiceResource($service->load('staff')),
        ]);
    }

    /**
     * Remove the specified service from storage.
     */
    public function destroy(Salon $salon, Service $service): JsonResponse
    {
        $this->authorize('update', $salon);

        if ($service->salon_id !== $salon->id) {
            abort(404);
        }

        $service->delete();

        // Invalidate cache
        \App\Services\CacheService::invalidateSalon($salon->id, $salon->slug);
        \Illuminate\Support\Facades\Cache::forget('services.popular');

        return response()->json([
            'message' => 'Service deleted successfully',
        ]);
    }

    /**
     * Get services by category.
     */
    public function byCategory(Salon $salon): JsonResponse
    {
        $categories = $salon->services()
            ->select('category')
            ->distinct()
            ->pluck('category');

        $servicesByCategory = [];

        foreach ($categories as $category) {
            $services = $salon->services()
                ->where('category', $category)
                ->with('staff')
                ->get();

            $servicesByCategory[$category] = ServiceResource::collection($services);
        }

        return response()->json([
            'categories' => $categories,
            'services_by_category' => $servicesByCategory,
        ]);
    }

    /**
     * Reorder services and categories.
     */
    public function reorder(\Illuminate\Http\Request $request, Salon $salon): JsonResponse
    {
        $this->authorize('update', $salon);

        $request->validate([
            'services' => 'required|array',
            'services.*.id' => 'required|integer|exists:services,id',
            'services.*.display_order' => 'required|integer|min:0',
            'category_order' => 'nullable|array',
        ]);

        // Update service display orders
        foreach ($request->services as $item) {
            Service::where('id', $item['id'])
                ->where('salon_id', $salon->id)
                ->update(['display_order' => $item['display_order']]);
        }

        // Update category order if provided
        if ($request->has('category_order')) {
            $salon->update(['category_order' => $request->category_order]);
        }

        // Invalidate cache
        \App\Services\CacheService::invalidateSalon($salon->id, $salon->slug);

        return response()->json([
            'message' => 'Services reordered successfully',
        ]);
    }
}
