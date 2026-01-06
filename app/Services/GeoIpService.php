<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeoIpService
{
    /**
     * Get country code from IP address
     * Uses multiple free services as fallback
     */
    public function getCountryCode(string $ip): ?string
    {
        // Skip local IPs
        if ($this->isLocalIp($ip)) {
            return 'LOCAL';
        }

        // Check cache first
        $cacheKey = "geoip:{$ip}";
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $countryCode = null;

        // Try Cloudflare header first (if behind Cloudflare)
        if (request()->hasHeader('CF-IPCountry')) {
            $countryCode = request()->header('CF-IPCountry');
        }

        // Try ip-api.com (free, 45 requests/minute)
        if (!$countryCode) {
            $countryCode = $this->getFromIpApi($ip);
        }

        // Try ipapi.co (free, 1000 requests/day)
        if (!$countryCode) {
            $countryCode = $this->getFromIpApiCo($ip);
        }

        // Cache for 24 hours
        if ($countryCode) {
            Cache::put($cacheKey, $countryCode, now()->addHours(24));
        }

        return $countryCode;
    }

    /**
     * Get country info from ip-api.com
     */
    private function getFromIpApi(string $ip): ?string
    {
        try {
            $response = Http::timeout(2)->get("http://ip-api.com/json/{$ip}", [
                'fields' => 'status,countryCode',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if ($data['status'] === 'success') {
                    return $data['countryCode'];
                }
            }
        } catch (\Exception $e) {
            Log::debug('GeoIP ip-api.com failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Get country info from ipapi.co
     */
    private function getFromIpApiCo(string $ip): ?string
    {
        try {
            $response = Http::timeout(2)->get("https://ipapi.co/{$ip}/country/");

            if ($response->successful()) {
                $countryCode = trim($response->body());
                if (strlen($countryCode) === 2) {
                    return $countryCode;
                }
            }
        } catch (\Exception $e) {
            Log::debug('GeoIP ipapi.co failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Check if IP is local/private
     */
    private function isLocalIp(string $ip): bool
    {
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return true;
        }

        // Check if private IP range
        $longIp = ip2long($ip);
        if ($longIp === false) {
            return false;
        }

        // Private IP ranges
        $privateRanges = [
            ['10.0.0.0', '10.255.255.255'],
            ['172.16.0.0', '172.31.255.255'],
            ['192.168.0.0', '192.168.255.255'],
        ];

        foreach ($privateRanges as $range) {
            $min = ip2long($range[0]);
            $max = ip2long($range[1]);
            if ($longIp >= $min && $longIp <= $max) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get full location info (city, country, etc.)
     */
    public function getLocationInfo(string $ip): array
    {
        // Skip local IPs
        if ($this->isLocalIp($ip)) {
            return [
                'country_code' => 'LOCAL',
                'country_name' => 'Local',
                'city' => 'Local',
            ];
        }

        // Check cache
        $cacheKey = "geoip_full:{$ip}";
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $response = Http::timeout(2)->get("http://ip-api.com/json/{$ip}");

            if ($response->successful()) {
                $data = $response->json();
                if ($data['status'] === 'success') {
                    $info = [
                        'country_code' => $data['countryCode'] ?? null,
                        'country_name' => $data['country'] ?? null,
                        'city' => $data['city'] ?? null,
                        'region' => $data['regionName'] ?? null,
                        'timezone' => $data['timezone'] ?? null,
                        'isp' => $data['isp'] ?? null,
                    ];

                    // Cache for 24 hours
                    Cache::put($cacheKey, $info, now()->addHours(24));

                    return $info;
                }
            }
        } catch (\Exception $e) {
            Log::debug('GeoIP full info failed', ['error' => $e->getMessage()]);
        }

        return [
            'country_code' => null,
            'country_name' => null,
            'city' => null,
        ];
    }

    /**
     * Check if IP is from Bosnia and Herzegovina
     */
    public function isFromBosniaHerzegovina(string $ip): bool
    {
        $countryCode = $this->getCountryCode($ip);
        return $countryCode === 'BA';
    }

    /**
     * Check if IP is from suspicious country
     */
    public function isFromSuspiciousCountry(string $ip): bool
    {
        $suspiciousCountries = [
            'CN', // China - many scrapers
            'RU', // Russia - many bots
            'IN', // India - many scrapers
            'VN', // Vietnam
            'ID', // Indonesia
        ];

        $countryCode = $this->getCountryCode($ip);
        return in_array($countryCode, $suspiciousCountries);
    }
}
