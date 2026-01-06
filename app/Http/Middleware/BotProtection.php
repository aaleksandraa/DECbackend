<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class BotProtection
{
    /**
     * Known bad bot user agents
     */
    private array $badBots = [
        'AhrefsBot',
        'SemrushBot',
        'MJ12bot',
        'DotBot',
        'BLEXBot',
        'DataForSeoBot',
        'PetalBot',
        'Bytespider',
        'YandexBot',
        'SeznamBot',
        'serpstatbot',
        'BUbiNG',
        'MegaIndex',
        'linkdexbot',
        'DomainCrawler',
        'spbot',
        'TurnitinBot',
        'Cliqzbot',
        'Baiduspider',
        'Sogou',
        '360Spider',
    ];

    /**
     * Suspicious patterns in user agents
     */
    private array $suspiciousPatterns = [
        'bot',
        'crawler',
        'spider',
        'scraper',
        'curl',
        'wget',
        'python',
        'java',
        'go-http',
        'scrapy',
        'selenium',
        'phantomjs',
        'headless',
    ];

    /**
     * Allowed good bots (search engines)
     */
    private array $goodBots = [
        'Googlebot',
        'Bingbot',
        'Slurp', // Yahoo
        'DuckDuckBot',
        'facebookexternalhit', // Facebook
        'Twitterbot',
        'LinkedInBot',
        'WhatsApp',
        'TelegramBot',
    ];

    /**
     * Countries to block (optional - adjust based on your needs)
     * Empty array = don't block by country
     */
    private array $blockedCountries = [
        // 'CN', // China
        // 'RU', // Russia
        // 'IN', // India (many scrapers)
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();
        $userAgent = $request->userAgent() ?? '';
        $path = $request->path();

        // 1. Check if IP is already blocked
        if ($this->isIpBlocked($ip)) {
            Log::warning('Blocked IP attempted access', [
                'ip' => $ip,
                'user_agent' => $userAgent,
                'path' => $path,
            ]);
            return response('Access Denied', 403);
        }

        // 2. Allow good bots (search engines)
        if ($this->isGoodBot($userAgent)) {
            return $next($request);
        }

        // 3. Check for bad bots
        if ($this->isBadBot($userAgent)) {
            $this->blockIp($ip, 'Bad bot detected: ' . $userAgent);
            Log::warning('Bad bot blocked', [
                'ip' => $ip,
                'user_agent' => $userAgent,
                'path' => $path,
            ]);
            return response('Access Denied', 403);
        }

        // 4. Check for suspicious patterns
        if ($this->hasSuspiciousPattern($userAgent)) {
            // Don't block immediately, but rate limit heavily
            $this->trackSuspiciousRequest($ip, $userAgent);
        }

        // 5. Rate limiting by IP
        if ($this->isRateLimitExceeded($ip)) {
            $this->blockIp($ip, 'Rate limit exceeded');
            Log::warning('Rate limit exceeded', [
                'ip' => $ip,
                'user_agent' => $userAgent,
                'path' => $path,
            ]);
            return response('Too Many Requests', 429);
        }

        // 6. Check for empty or suspicious user agent
        if (empty($userAgent) || strlen($userAgent) < 10) {
            $this->trackSuspiciousRequest($ip, $userAgent);
        }

        // 7. Track request
        $this->trackRequest($ip);

        return $next($request);
    }

    /**
     * Check if IP is blocked
     */
    private function isIpBlocked(string $ip): bool
    {
        return Cache::has("blocked_ip:{$ip}");
    }

    /**
     * Block an IP address
     */
    private function blockIp(string $ip, string $reason): void
    {
        // Block for 24 hours
        Cache::put("blocked_ip:{$ip}", [
            'reason' => $reason,
            'blocked_at' => now()->toDateTimeString(),
        ], now()->addHours(24));

        // Log to database for permanent record
        \DB::table('blocked_ips')->updateOrInsert(
            ['ip' => $ip],
            [
                'reason' => $reason,
                'blocked_at' => now(),
                'expires_at' => now()->addHours(24),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Check if user agent is a good bot
     */
    private function isGoodBot(string $userAgent): bool
    {
        foreach ($this->goodBots as $bot) {
            if (stripos($userAgent, $bot) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if user agent is a bad bot
     */
    private function isBadBot(string $userAgent): bool
    {
        foreach ($this->badBots as $bot) {
            if (stripos($userAgent, $bot) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if user agent has suspicious patterns
     */
    private function hasSuspiciousPattern(string $userAgent): bool
    {
        $userAgentLower = strtolower($userAgent);

        foreach ($this->suspiciousPatterns as $pattern) {
            if (stripos($userAgentLower, $pattern) !== false) {
                // Exclude good bots that might match patterns
                if (!$this->isGoodBot($userAgent)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Track suspicious request
     */
    private function trackSuspiciousRequest(string $ip, string $userAgent): void
    {
        $key = "suspicious:{$ip}";
        $count = Cache::get($key, 0);
        $count++;

        Cache::put($key, $count, now()->addHour());

        // Block after 10 suspicious requests in 1 hour
        if ($count >= 10) {
            $this->blockIp($ip, 'Too many suspicious requests');
        }
    }

    /**
     * Track request for rate limiting
     */
    private function trackRequest(string $ip): void
    {
        $key = "requests:{$ip}";
        $requests = Cache::get($key, []);
        $requests[] = now()->timestamp;

        // Keep only last 60 seconds
        $requests = array_filter($requests, function ($timestamp) {
            return $timestamp > (now()->timestamp - 60);
        });

        Cache::put($key, $requests, now()->addMinutes(2));
    }

    /**
     * Check if rate limit is exceeded
     */
    private function isRateLimitExceeded(string $ip): bool
    {
        $key = "requests:{$ip}";
        $requests = Cache::get($key, []);

        // More than 60 requests per minute = likely bot
        return count($requests) > 60;
    }
}
