<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Salon;
use App\Models\WidgetSetting;
use App\Models\WidgetAnalytics;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AdminWidgetController extends Controller
{
    /**
     * Get all widget settings
     */
    public function index(): JsonResponse
    {
        $widgets = WidgetSetting::with('salon:id,name,slug')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($widget) {
                return [
                    'id' => $widget->id,
                    'salon' => $widget->salon,
                    'is_active' => $widget->is_active,
                    'allowed_domains' => $widget->allowed_domains,
                    'total_bookings' => $widget->total_bookings,
                    'last_used_at' => $widget->last_used_at,
                    'created_at' => $widget->created_at,
                ];
            });

        return response()->json(['widgets' => $widgets]);
    }

    /**
     * Get widget settings for specific salon
     */
    public function show(int $salonId): JsonResponse
    {
        $salon = Salon::findOrFail($salonId);

        $widget = WidgetSetting::where('salon_id', $salonId)->first();

        if (!$widget) {
            return response()->json([
                'widget' => null,
                'salon' => $salon,
            ]);
        }

        return response()->json([
            'widget' => [
                'id' => $widget->id,
                'salon_id' => $widget->salon_id,
                'api_key' => $widget->api_key, // Show full key to admin
                'is_active' => $widget->is_active,
                'allowed_domains' => $widget->allowed_domains,
                'theme' => $widget->theme,
                'settings' => $widget->settings,
                'total_bookings' => $widget->total_bookings,
                'last_used_at' => $widget->last_used_at,
                'created_at' => $widget->created_at,
            ],
            'salon' => $salon,
            'embed_code' => $this->generateEmbedCode($widget),
        ]);
    }

    /**
     * Generate or regenerate API key for salon
     */
    public function generateApiKey(Request $request, int $salonId): JsonResponse
    {
        $salon = Salon::findOrFail($salonId);

        $widget = WidgetSetting::where('salon_id', $salonId)->first();

        if ($widget) {
            // Regenerate key
            $widget->update([
                'api_key' => $this->generateUniqueApiKey(),
            ]);
        } else {
            // Create new widget
            $widget = WidgetSetting::create([
                'salon_id' => $salonId,
                'api_key' => $this->generateUniqueApiKey(),
                'is_active' => true,
                'theme' => [
                    'primaryColor' => '#FF6B35',
                    'secondaryColor' => '#F7931E',
                    'fontFamily' => 'Inter, sans-serif',
                    'borderRadius' => '12px',
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'API key uspješno generisan',
            'widget' => [
                'id' => $widget->id,
                'api_key' => $widget->api_key,
                'is_active' => $widget->is_active,
            ],
            'embed_code' => $this->generateEmbedCode($widget),
        ]);
    }

    /**
     * Update widget settings
     */
    public function updateSettings(Request $request, int $salonId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'is_active' => 'boolean',
            'allowed_domains' => 'array',
            'allowed_domains.*' => 'string|regex:/^[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]?\.[a-zA-Z]{2,}$/',
            'theme' => 'array',
            'theme.primaryColor' => 'string|regex:/^#[0-9A-Fa-f]{6}$/',
            'theme.secondaryColor' => 'string|regex:/^#[0-9A-Fa-f]{6}$/',
            'theme.fontFamily' => 'string|max:100',
            'theme.borderRadius' => 'string|max:20',
            'settings' => 'array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $widget = WidgetSetting::where('salon_id', $salonId)->firstOrFail();

        $updateData = [];

        if ($request->has('is_active')) {
            $updateData['is_active'] = (bool) $request->input('is_active');
        }

        if ($request->has('allowed_domains')) {
            $updateData['allowed_domains'] = $request->input('allowed_domains');
        }

        if ($request->has('theme')) {
            $updateData['theme'] = array_merge($widget->theme ?? [], $request->input('theme'));
        }

        if ($request->has('settings')) {
            $updateData['settings'] = array_merge($widget->settings ?? [], $request->input('settings'));
        }

        $widget->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Widget postavke uspješno ažurirane',
            'widget' => $widget,
        ]);
    }

    /**
     * Delete widget (deactivate)
     */
    public function destroy(int $salonId): JsonResponse
    {
        $widget = WidgetSetting::where('salon_id', $salonId)->firstOrFail();

        // Soft delete - just deactivate
        $widget->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Widget deaktiviran',
        ]);
    }

    /**
     * Get widget analytics
     */
    public function analytics(Request $request, int $salonId): JsonResponse
    {
        $days = $request->input('days', 30);
        $startDate = Carbon::now()->subDays($days);

        $analytics = WidgetAnalytics::where('salon_id', $salonId)
            ->where('created_at', '>=', $startDate)
            ->get();

        $totalViews = $analytics->where('event_type', WidgetAnalytics::EVENT_VIEW)->count();
        $totalBookings = $analytics->where('event_type', WidgetAnalytics::EVENT_BOOKING)->count();
        $conversionRate = $totalViews > 0 ? round(($totalBookings / $totalViews) * 100, 2) : 0;

        // Top domains
        $topDomains = $analytics
            ->whereNotNull('referrer_domain')
            ->groupBy('referrer_domain')
            ->map(function($items) {
                return [
                    'domain' => $items->first()->referrer_domain,
                    'views' => $items->where('event_type', WidgetAnalytics::EVENT_VIEW)->count(),
                    'bookings' => $items->where('event_type', WidgetAnalytics::EVENT_BOOKING)->count(),
                ];
            })
            ->sortByDesc('views')
            ->take(10)
            ->values();

        // Daily stats
        $dailyStats = $analytics
            ->groupBy(function($item) {
                return $item->created_at->format('Y-m-d');
            })
            ->map(function($items, $date) {
                return [
                    'date' => $date,
                    'views' => $items->where('event_type', WidgetAnalytics::EVENT_VIEW)->count(),
                    'bookings' => $items->where('event_type', WidgetAnalytics::EVENT_BOOKING)->count(),
                ];
            })
            ->sortBy('date')
            ->values();

        return response()->json([
            'period' => [
                'days' => $days,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => Carbon::now()->format('Y-m-d'),
            ],
            'summary' => [
                'total_views' => $totalViews,
                'total_bookings' => $totalBookings,
                'conversion_rate' => $conversionRate,
            ],
            'top_domains' => $topDomains,
            'daily_stats' => $dailyStats,
        ]);
    }

    /**
     * Generate unique API key
     */
    private function generateUniqueApiKey(): string
    {
        do {
            $key = 'frzn_live_' . Str::random(32);
        } while (WidgetSetting::where('api_key', $key)->exists());

        return $key;
    }

    /**
     * Generate embed code
     */
    private function generateEmbedCode(WidgetSetting $widget): array
    {
        $salon = $widget->salon;
        $salonSlug = $salon->slug ?? $salon->id;
        $frontendUrl = config('app.frontend_url', 'https://frizerino.com');
        $theme = $widget->theme ?? [];
        $primaryColor = $theme['primaryColor'] ?? '#FF6B35';

        // Professional JavaScript Widget (Recommended)
        $jsWidgetCode = <<<HTML
<!-- Frizerino Booking Widget - Professional -->
<div id="frizerino-widget"></div>
<script
  src="{$frontendUrl}/widget.js"
  data-salon="{$salonSlug}"
  data-key="{$widget->api_key}"
  data-primary-color="{$primaryColor}"
  data-theme="light"
  data-button-text="Rezerviši termin">
</script>
HTML;

        // Dark theme variant
        $jsWidgetDarkCode = <<<HTML
<!-- Frizerino Booking Widget - Dark Theme -->
<div id="frizerino-widget"></div>
<script
  src="{$frontendUrl}/widget.js"
  data-salon="{$salonSlug}"
  data-key="{$widget->api_key}"
  data-primary-color="{$primaryColor}"
  data-theme="dark"
  data-button-text="Rezerviši termin">
</script>
HTML;

        // Custom styling example
        $jsWidgetCustomCode = <<<HTML
<!-- Frizerino Booking Widget - Custom Styling -->
<div id="frizerino-widget"></div>
<script
  src="{$frontendUrl}/widget.js"
  data-salon="{$salonSlug}"
  data-key="{$widget->api_key}"
  data-primary-color="#YOUR_COLOR"
  data-theme="light"
  data-button-text="Book Now"
  data-button-radius="24px"
  data-font="Arial, sans-serif">
</script>

<!-- Customization Options:
  data-primary-color: Main color (hex, e.g. #FF6B35)
  data-theme: "light" or "dark"
  data-button-text: Button text
  data-button-radius: Button border radius
  data-font: Font family
  data-container: Custom container ID (default: frizerino-widget)
-->
HTML;

        return [
            'javascript' => $jsWidgetCode,
            'javascript_dark' => $jsWidgetDarkCode,
            'javascript_custom' => $jsWidgetCustomCode,
            'api_key' => $widget->api_key,
            'widget_url' => "{$frontendUrl}/widget.js",
            'salon_slug' => $salonSlug,
            'customization' => [
                'primary_color' => $primaryColor,
                'available_options' => [
                    'data-primary-color' => 'Main color (hex)',
                    'data-theme' => 'light or dark',
                    'data-button-text' => 'Button text',
                    'data-button-radius' => 'Border radius (e.g. 8px, 24px)',
                    'data-font' => 'Font family',
                    'data-container' => 'Custom container ID',
                ],
            ],
        ];
    }
}
