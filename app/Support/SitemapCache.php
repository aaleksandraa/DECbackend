<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class SitemapCache
{
    /**
     * Centralized sitemap cache keys.
     *
     * @var array<int, string>
     */
    private const KEYS = [
        'sitemap_index',
        'sitemap_static',
        'sitemap_cities',
        'sitemap_salons',
        'sitemap_staff',
        'sitemap_services',
    ];

    /**
     * Clear all sitemap cache fragments.
     */
    public static function clearAll(): void
    {
        foreach (self::KEYS as $key) {
            Cache::forget($key);
        }
    }
}

