<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomepageCategory;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class HomepageCategoryController extends Controller
{
    /**
     * Get all homepage categories (admin view).
     */
    public function index(): JsonResponse
    {
        try {
            $categories = HomepageCategory::orderBy('display_order')
                ->orderBy('id')
                ->get();

            // Get settings
            $settings = [
                'enabled' => SystemSetting::get('categories_enabled', false),
                'mobile' => SystemSetting::get('categories_mobile', true),
                'desktop' => SystemSetting::get('categories_desktop', true),
                'layout' => SystemSetting::get('categories_layout', 'grid'),
            ];

            return response()->json([
                'categories' => $categories,
                'settings' => $settings,
            ]);
        } catch (\Exception $e) {
            \Log::error('Homepage categories index error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error loading categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new category.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'link_type' => 'required|in:search,url,category',
            'link_value' => 'required|string',
            'is_enabled' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Generate slug
        $slug = Str::slug($request->name);
        $originalSlug = $slug;
        $counter = 1;

        while (HomepageCategory::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        // Get max display order
        $maxOrder = HomepageCategory::max('display_order') ?? 0;

        $category = HomepageCategory::create([
            'name' => $request->name,
            'slug' => $slug,
            'title' => $request->title,
            'description' => $request->description,
            'link_type' => $request->link_type,
            'link_value' => $request->link_value,
            'is_enabled' => $request->boolean('is_enabled', true),
            'display_order' => $maxOrder + 1,
        ]);

        $this->clearCache();

        return response()->json([
            'message' => 'Kategorija uspješno kreirana',
            'category' => $category,
        ], 201);
    }

    /**
     * Update a category.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $category = HomepageCategory::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'link_type' => 'sometimes|in:search,url,category',
            'link_value' => 'sometimes|string',
            'is_enabled' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $category->update($request->only([
            'name',
            'title',
            'description',
            'link_type',
            'link_value',
            'is_enabled',
        ]));

        $this->clearCache();

        return response()->json([
            'message' => 'Kategorija uspješno ažurirana',
            'category' => $category->fresh(),
        ]);
    }

    /**
     * Delete a category.
     */
    public function destroy($id): JsonResponse
    {
        $category = HomepageCategory::findOrFail($id);

        // Delete image if exists
        if ($category->image_url && Storage::disk('public')->exists($category->image_url)) {
            Storage::disk('public')->delete($category->image_url);
        }

        $category->delete();

        $this->clearCache();

        return response()->json([
            'message' => 'Kategorija uspješno obrisana',
        ]);
    }

    /**
     * Upload category image.
     */
    public function uploadImage(Request $request, $id): JsonResponse
    {
        $category = HomepageCategory::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Delete old image if exists
        if ($category->image_url && Storage::disk('public')->exists($category->image_url)) {
            Storage::disk('public')->delete($category->image_url);
        }

        // Store new image
        $image = $request->file('image');
        $filename = $category->slug . '-' . time() . '.' . $image->getClientOriginalExtension();
        $path = $image->storeAs('categories', $filename, 'public');

        $category->update(['image_url' => $path]);

        $this->clearCache();

        return response()->json([
            'message' => 'Slika uspješno uploadovana',
            'category' => $category->fresh(),
        ]);
    }

    /**
     * Reorder categories.
     */
    public function reorder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'categories' => 'required|array',
            'categories.*.id' => 'required|exists:homepage_categories,id',
            'categories.*.display_order' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::transaction(function () use ($request) {
            foreach ($request->categories as $item) {
                HomepageCategory::where('id', $item['id'])
                    ->update(['display_order' => $item['display_order']]);
            }
        });

        $this->clearCache();

        return response()->json([
            'message' => 'Redoslijed uspješno ažuriran',
        ]);
    }

    /**
     * Update global settings.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'enabled' => 'required|boolean',
            'mobile' => 'required|boolean',
            'desktop' => 'required|boolean',
            'layout' => 'required|in:grid,carousel',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        SystemSetting::set('categories_enabled', $request->boolean('enabled') ? 'true' : 'false', 'boolean', 'homepage');
        SystemSetting::set('categories_mobile', $request->boolean('mobile') ? 'true' : 'false', 'boolean', 'homepage');
        SystemSetting::set('categories_desktop', $request->boolean('desktop') ? 'true' : 'false', 'boolean', 'homepage');
        SystemSetting::set('categories_layout', $request->layout, 'string', 'homepage');

        $this->clearCache();

        return response()->json([
            'message' => 'Podešavanja uspješno ažurirana',
            'settings' => [
                'enabled' => $request->boolean('enabled'),
                'mobile' => $request->boolean('mobile'),
                'desktop' => $request->boolean('desktop'),
                'layout' => $request->layout,
            ],
        ]);
    }

    /**
     * Clear cache.
     */
    private function clearCache(): void
    {
        Cache::forget('homepage.categories.public');
    }
}
