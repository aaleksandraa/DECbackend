<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CacheService
{
    /**
     * Cache durations in seconds
     */
    const SALON_LIST_TTL = 300;      // 5 minuta
    const SALON_PROFILE_TTL = 600;   // 10 minuta
    const SERVICES_TTL = 1800;       // 30 minuta
    const REVIEWS_TTL = 300;         // 5 minuta
    const STAFF_TTL = 600;           // 10 minuta

    /**
     * Generate cache key for salon list
     */
    public static function salonListKey(array $filters = []): string
    {
        return 'salons.list.' . md5(json_encode($filters));
    }

    /**
     * Generate cache key for salon profile
     */
    public static function salonProfileKey(string $slug): string
    {
        return "salon.profile.{$slug}";
    }

    /**
     * Generate cache key for salon services
     */
    public static function salonServicesKey(int $salonId): string
    {
        return "salon.{$salonId}.services";
    }

    /**
     * Generate cache key for salon reviews
     */
    public static function salonReviewsKey(int $salonId): string
    {
        return "salon.{$salonId}.reviews";
    }

    /**
     * Generate cache key for salon staff
     */
    public static function salonStaffKey(int $salonId): string
    {
        return "salon.{$salonId}.staff";
    }

    /**
     * Invalidate all salon-related cache
     */
    public static function invalidateSalon(int $salonId, ?string $slug = null): void
    {
        // Invalidate specific salon caches
        Cache::forget(self::salonServicesKey($salonId));
        Cache::forget(self::salonReviewsKey($salonId));
        Cache::forget(self::salonStaffKey($salonId));

        if ($slug) {
            Cache::forget(self::salonProfileKey($slug));
        }

        // Invalidate salon list cache (all variations)
        // Note: In production, consider using cache tags for better invalidation
        Cache::flush(); // Temporary solution - use tags in production
    }

    /**
     * Invalidate salon list cache
     */
    public static function invalidateSalonList(): void
    {
        // In production, use cache tags to invalidate only salon list caches
        // For now, we'll use a simple approach
        Cache::tags(['salons'])->flush();
    }

    /**
     * Remember with automatic cache key generation
     */
    public static function remember(string $key, int $ttl, callable $callback)
    {
        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Forget cache by key
     */
    public static function forget(string $key): void
    {
        Cache::forget($key);
    }

    /**
     * Flush all cache
     */
    public static function flush(): void
    {
        Cache::flush();
    }

    /**
     * Alias for invalidateSalon (for backward compatibility)
     */
    public function invalidateSalonCache(int $salonId, ?string $slug = null): void
    {
        self::invalidateSalon($salonId, $slug);
    }
}
