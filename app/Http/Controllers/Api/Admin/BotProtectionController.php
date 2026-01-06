<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BotProtectionController extends Controller
{
    /**
     * Get blocked IPs list
     */
    public function getBlockedIps(Request $request): JsonResponse
    {
        $query = DB::table('blocked_ips')
            ->orderBy('blocked_at', 'desc');

        // Filter by active blocks only
        if ($request->boolean('active_only')) {
            $query->where('expires_at', '>', now())
                ->orWhereNull('expires_at');
        }

        $blockedIps = $query->paginate(50);

        return response()->json($blockedIps);
    }

    /**
     * Get bot requests statistics
     */
    public function getBotStats(): JsonResponse
    {
        $stats = [
            'total_blocked' => DB::table('blocked_ips')->count(),
            'active_blocks' => DB::table('blocked_ips')
                ->where('expires_at', '>', now())
                ->orWhereNull('expires_at')
                ->count(),
            'bot_requests_today' => DB::table('bot_requests')
                ->where('created_at', '>', now()->subDay())
                ->where('is_bot', true)
                ->count(),
            'blocked_requests_today' => DB::table('bot_requests')
                ->where('created_at', '>', now()->subDay())
                ->where('is_blocked', true)
                ->count(),
            'top_blocked_ips' => DB::table('blocked_ips')
                ->select('ip', 'reason', 'block_count', 'country_code')
                ->orderBy('block_count', 'desc')
                ->limit(10)
                ->get(),
            'requests_by_country' => DB::table('bot_requests')
                ->select('country_code', DB::raw('count(*) as count'))
                ->where('created_at', '>', now()->subWeek())
                ->whereNotNull('country_code')
                ->groupBy('country_code')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get(),
        ];

        return response()->json($stats);
    }

    /**
     * Block an IP manually
     */
    public function blockIp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ip' => 'required|ip',
            'reason' => 'required|string|max:255',
            'duration_hours' => 'nullable|integer|min:1|max:8760', // Max 1 year
        ]);

        $expiresAt = $validated['duration_hours']
            ? now()->addHours($validated['duration_hours'])
            : null; // Permanent if null

        DB::table('blocked_ips')->updateOrInsert(
            ['ip' => $validated['ip']],
            [
                'reason' => $validated['reason'],
                'blocked_at' => now(),
                'expires_at' => $expiresAt,
                'block_count' => DB::raw('block_count + 1'),
                'updated_at' => now(),
            ]
        );

        // Also add to cache for immediate effect
        Cache::put("blocked_ip:{$validated['ip']}", [
            'reason' => $validated['reason'],
            'blocked_at' => now()->toDateTimeString(),
        ], $expiresAt ?? now()->addYears(10));

        return response()->json([
            'message' => 'IP blocked successfully',
            'ip' => $validated['ip'],
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Unblock an IP
     */
    public function unblockIp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ip' => 'required|ip',
        ]);

        DB::table('blocked_ips')
            ->where('ip', $validated['ip'])
            ->delete();

        Cache::forget("blocked_ip:{$validated['ip']}");

        return response()->json([
            'message' => 'IP unblocked successfully',
            'ip' => $validated['ip'],
        ]);
    }

    /**
     * Get recent bot requests
     */
    public function getRecentRequests(Request $request): JsonResponse
    {
        $query = DB::table('bot_requests')
            ->orderBy('created_at', 'desc');

        if ($request->boolean('bots_only')) {
            $query->where('is_bot', true);
        }

        if ($request->boolean('blocked_only')) {
            $query->where('is_blocked', true);
        }

        $requests = $query->limit(100)->get();

        return response()->json($requests);
    }

    /**
     * Clear old bot request logs
     */
    public function clearOldLogs(): JsonResponse
    {
        $deleted = DB::table('bot_requests')
            ->where('created_at', '<', now()->subDays(30))
            ->delete();

        return response()->json([
            'message' => 'Old logs cleared',
            'deleted_count' => $deleted,
        ]);
    }

    /**
     * Update bot protection settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'rate_limit' => 'required|integer|min:10|max:1000',
            'block_duration_hours' => 'required|integer|min:1|max:168',
            'suspicious_threshold' => 'required|integer|min:5|max:50',
        ]);

        foreach ($validated as $key => $value) {
            \App\Models\SystemSetting::set("bot_protection_{$key}", $value, 'integer', 'security');
        }

        return response()->json([
            'message' => 'Settings updated successfully',
            'settings' => $validated,
        ]);
    }

    /**
     * Get bot protection settings
     */
    public function getSettings(): JsonResponse
    {
        $settings = [
            'enabled' => \App\Models\SystemSetting::get('bot_protection_enabled', true),
            'rate_limit' => \App\Models\SystemSetting::get('bot_protection_rate_limit', 60),
            'block_duration_hours' => \App\Models\SystemSetting::get('bot_protection_block_duration_hours', 24),
            'suspicious_threshold' => \App\Models\SystemSetting::get('bot_protection_suspicious_threshold', 10),
        ];

        return response()->json($settings);
    }
}
