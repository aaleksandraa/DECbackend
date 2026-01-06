<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HomepageCategory;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class HomepageCategoryController extends Controller
{
    /**
     * Get enabled homepage categories for public view.
     */
    public function index(): JsonResponse
    {
        $cacheKey = 'homepage.categories.public';

        $data = Cache::remember($cacheKey, 600, function () {
            // Get settings
            $enabled = SystemSetting::get('categories_enabled', false);
            $mobile = SystemSetting::get('categories_mobile', true);
            $desktop = SystemSetting::get('categories_desktop', true);
            $layout = SystemSetting::get('categories_layout', 'grid');

            // Get enabled categories
            $categories = HomepageCategory::enabled()
                ->ordered()
                ->get()
                ->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                        'slug' => $category->slug,
                        'title' => $category->title,
                        'description' => $category->description,
                        'image_url' => $category->image_url,
                        'search_url' => $category->getSearchUrl(),
                    ];
                });

            return [
                'enabled' => $enabled,
                'mobile' => $mobile,
                'desktop' => $desktop,
                'layout' => $layout,
                'categories' => $categories,
            ];
        });

        return response()->json($data);
    }
}
