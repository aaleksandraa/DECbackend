<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class SettingsController extends Controller
{
    /**
     * Get all settings (admin only)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $settings = SystemSetting::all()->groupBy('group');

        $formatted = [];
        foreach ($settings as $group => $groupSettings) {
            $formatted[$group] = [];
            foreach ($groupSettings as $setting) {
                $formatted[$group][$setting->key] = [
                    'value' => $this->castValue($setting->value, $setting->type),
                    'type' => $setting->type,
                    'description' => $setting->description,
                ];
            }
        }

        return response()->json($formatted);
    }

    /**
     * Get settings by group (admin only)
     */
    public function getByGroup(Request $request, string $group): JsonResponse
    {
        $user = $request->user();

        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $settings = SystemSetting::getByGroup($group);

        return response()->json($settings);
    }

    /**
     * Update settings (admin only)
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'nullable',
        ]);

        foreach ($validated['settings'] as $settingData) {
            $setting = SystemSetting::where('key', $settingData['key'])->first();
            $value = $settingData['value'];

            if ($setting) {
                // Convert boolean values to string for storage
                if ($setting->type === 'boolean') {
                    $value = $value ? 'true' : 'false';
                }

                // Convert arrays to JSON
                if (is_array($value)) {
                    $value = json_encode($value);
                }

                $setting->update(['value' => $value]);

                // Clear cache for this setting
                Cache::forget("setting_{$setting->key}");
            } else {
                // Create the setting if it doesn't exist
                $type = 'string';
                $group = 'analytics';

                // Determine type based on key name or value
                if (str_contains($settingData['key'], 'enabled') || is_bool($value)) {
                    $type = 'boolean';
                    $value = $value ? 'true' : 'false';
                }

                SystemSetting::create([
                    'key' => $settingData['key'],
                    'value' => is_array($value) ? json_encode($value) : $value,
                    'type' => $type,
                    'group' => $group,
                ]);

                // Clear cache for this setting
                Cache::forget("setting_{$settingData['key']}");
            }
        }

        // Clear grouped caches
        Cache::forget('settings_analytics');

        return response()->json([
            'message' => 'Podešavanja su uspješno sačuvana',
            'settings' => SystemSetting::all()->keyBy('key'),
        ]);
    }

    /**
     * Get public analytics settings (for frontend to inject GA script)
     * This is a public endpoint - no auth required
     */
    public function getAnalytics(): JsonResponse
    {
        // Don't use cache for this endpoint to ensure fresh data
        $gaId = SystemSetting::where('key', 'google_analytics_id')->first();
        $gaEnabled = SystemSetting::where('key', 'google_analytics_enabled')->first();

        return response()->json([
            'google_analytics_id' => $gaId ? $gaId->value : null,
            'google_analytics_enabled' => $gaEnabled ? ($gaEnabled->value === 'true' || $gaEnabled->value === '1' || $gaEnabled->value === true) : false,
        ]);
    }

    /**
     * Update analytics settings (admin only)
     */
    public function updateAnalytics(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'google_analytics_id' => 'nullable|string|max:50',
            'google_analytics_enabled' => 'required|boolean',
        ]);

        // Update or create google_analytics_id
        SystemSetting::set(
            'google_analytics_id',
            $validated['google_analytics_id'] ?? '',
            'string',
            'analytics'
        );

        // Update or create google_analytics_enabled
        SystemSetting::set(
            'google_analytics_enabled',
            $validated['google_analytics_enabled'],
            'boolean',
            'analytics'
        );

        // Clear all related caches
        Cache::forget('setting_google_analytics_id');
        Cache::forget('setting_google_analytics_enabled');
        Cache::forget('settings_analytics');

        return response()->json([
            'message' => 'Google Analytics podešavanja su uspješno sačuvana',
            'google_analytics_id' => $validated['google_analytics_id'],
            'google_analytics_enabled' => $validated['google_analytics_enabled'],
        ]);
    }

    /**
     * Get registration settings (public endpoint)
     */
    public function getRegistrationSettings(): JsonResponse
    {
        $allowFrizerRegistration = SystemSetting::get('allow_frizer_registration', false);

        return response()->json([
            'allow_frizer_registration' => $allowFrizerRegistration,
        ]);
    }

    /**
     * Update registration settings (admin only)
     */
    public function updateRegistrationSettings(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'allow_frizer_registration' => 'required|boolean',
        ]);

        SystemSetting::set('allow_frizer_registration', $validated['allow_frizer_registration'], 'boolean', 'registration');

        // Clear cache
        Cache::forget('setting_allow_frizer_registration');

        return response()->json([
            'message' => 'Postavke registracije su uspješno sačuvane',
            'allow_frizer_registration' => $validated['allow_frizer_registration'],
        ]);
    }

    /**
     * Get public appearance settings (gradient, etc.)
     * This is a public endpoint - no auth required
     */
    public function getAppearance(): JsonResponse
    {
        $gradient = SystemSetting::get('homepage_gradient', [
            'preset' => 'beauty-rose',
            'from' => '#f43f5e',
            'via' => '#ec4899',
            'to' => '#a855f7',
            'direction' => 'br',
            'custom' => false
        ]);

        $heroBackgroundImage = SystemSetting::get('hero_background_image', null);

        $navbarGradient = SystemSetting::get('navbar_gradient', [
            'preset' => 'sunset-orange',
            'from' => '#f97316',
            'via' => '#ea580c',
            'to' => '#dc2626',
            'direction' => 'r',
            'custom' => false
        ]);

        // Salon profile layout options:
        // 'classic' - Current layout with large hero
        // 'compact' - Smaller hero, description first then gallery
        // 'modern' - Card-based with floating gallery
        // 'minimal' - Clean, no hero, focus on content
        $salonProfileLayout = SystemSetting::get('salon_profile_layout', 'classic');

        // Sticky navbar option - whether navbar should stick to top on scroll
        $stickyNavbar = SystemSetting::get('sticky_navbar', true);

        // Search version - v1 or v2
        $searchVersion = SystemSetting::get('search_version', 'v1');

        return response()->json([
            'gradient' => $gradient,
            'hero_background_image' => $heroBackgroundImage,
            'navbar_gradient' => $navbarGradient,
            'salon_profile_layout' => $salonProfileLayout,
            'sticky_navbar' => $stickyNavbar,
            'search_version' => $searchVersion,
        ]);
    }

    /**
     * Get gradient presets (admin only)
     */
    public function getGradientPresets(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $presets = SystemSetting::get('gradient_presets', []);
        $currentGradient = SystemSetting::get('homepage_gradient', []);
        $heroBackgroundImage = SystemSetting::get('hero_background_image', null);

        return response()->json([
            'presets' => $presets,
            'current' => $currentGradient,
            'hero_background_image' => $heroBackgroundImage,
        ]);
    }

    /**
     * Update gradient settings (admin only)
     */
    public function updateGradient(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'preset' => 'nullable|string',
            'from' => 'required|string',
            'via' => 'nullable|string',
            'to' => 'required|string',
            'direction' => 'required|string|in:r,l,t,b,tr,tl,br,bl',
            'custom' => 'boolean',
            'background_image' => 'nullable|string|max:500',
        ]);

        // Extract background_image separately
        $backgroundImage = $validated['background_image'] ?? null;
        unset($validated['background_image']);

        SystemSetting::set('homepage_gradient', $validated, 'json', 'appearance');
        SystemSetting::set('hero_background_image', $backgroundImage, 'string', 'appearance');

        return response()->json([
            'message' => 'Gradient postavke su uspješno sačuvane',
            'gradient' => $validated,
            'hero_background_image' => $backgroundImage,
        ]);
    }

    /**
     * Update navbar gradient settings (admin only)
     */
    public function updateNavbarGradient(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'preset' => 'nullable|string',
            'from' => 'required|string',
            'via' => 'nullable|string',
            'to' => 'required|string',
            'direction' => 'required|string|in:r,l,t,b,tr,tl,br,bl',
            'custom' => 'boolean',
        ]);

        SystemSetting::set('navbar_gradient', $validated, 'json', 'appearance');

        return response()->json([
            'message' => 'Navbar gradient postavke su uspješno sačuvane',
            'navbar_gradient' => $validated,
        ]);
    }

    /**
     * Update sticky navbar setting (admin only)
     */
    public function updateStickyNavbar(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'sticky' => 'required|boolean',
        ]);

        SystemSetting::set('sticky_navbar', $validated['sticky'], 'boolean', 'appearance');

        // Clear cache
        Cache::forget('setting_sticky_navbar');

        return response()->json([
            'message' => 'Sticky navbar postavka je uspješno sačuvana',
            'sticky_navbar' => $validated['sticky'],
        ]);
    }

    /**
     * Update search version setting (admin only)
     */
    public function updateSearchVersion(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'version' => 'required|string|in:v1,v2',
        ]);

        SystemSetting::set('search_version', $validated['version'], 'string', 'appearance');

        // Clear cache
        Cache::forget('setting_search_version');

        return response()->json([
            'message' => 'Verzija pretrage je uspješno sačuvana',
            'search_version' => $validated['version'],
        ]);
    }

    /**
     * Update salon profile layout (admin only)
     */
    public function updateSalonProfileLayout(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'layout' => 'required|string|in:classic,classic-desc-first,compact-hero,modern-card,description-first',
        ]);

        SystemSetting::set('salon_profile_layout', $validated['layout'], 'string', 'appearance');

        return response()->json([
            'message' => 'Layout profila salona je uspješno ažuriran',
            'salon_profile_layout' => $validated['layout'],
        ]);
    }

    /**
     * Get current salon profile layout (admin only)
     */
    public function getSalonProfileLayout(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $currentLayout = SystemSetting::get('salon_profile_layout', 'classic');

        return response()->json([
            'layout' => $currentLayout,
        ]);
    }

    /**
     * Get salon profile layout options (admin only)
     */
    public function getSalonProfileLayoutOptions(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $currentLayout = SystemSetting::get('salon_profile_layout', 'classic');

        $layouts = [
            [
                'id' => 'classic',
                'name' => 'Klasičan',
                'description' => 'Veliki hero sa overlay-em, galerija ispod. Trenutni izgled.',
                'preview' => '/images/layouts/classic.png',
            ],
            [
                'id' => 'classic-desc-first',
                'name' => 'Klasičan (opis prvi)',
                'description' => 'Isti kao klasičan, ali na mobilnim uređajima opis ide prije galerije.',
                'preview' => '/images/layouts/classic-desc-first.png',
            ],
            [
                'id' => 'compact-hero',
                'name' => 'Kompaktan',
                'description' => 'Manji hero (50% visine), opis salona odmah ispod naziva, pa galerija.',
                'preview' => '/images/layouts/compact.png',
            ],
            [
                'id' => 'modern-card',
                'name' => 'Moderan',
                'description' => 'Bez hero sekcije, kartica sa slikom i info sa strane. Čist, profesionalan.',
                'preview' => '/images/layouts/modern.png',
            ],
            [
                'id' => 'description-first',
                'name' => 'Minimalan',
                'description' => 'Fokus na sadržaj. Mala slika, veliki naglasak na usluge i recenzije.',
                'preview' => '/images/layouts/minimal.png',
            ],
        ];

        return response()->json([
            'current' => $currentLayout,
            'layouts' => $layouts,
        ]);
    }

    /**
     * Get featured salon (public endpoint)
     */
    public function getFeaturedSalon(): JsonResponse
    {
        $featuredSalonId = SystemSetting::get('featured_salon_id', null);
        $featuredSalonText = SystemSetting::get('featured_salon_text', 'Otvoren je novi salon u vašem gradu');
        $featuredSalonVisibility = SystemSetting::get('featured_salon_visibility', 'all'); // all, location_only
        $showTopRated = SystemSetting::get('show_top_rated_salons', true);
        $showNewest = SystemSetting::get('show_newest_salons', true);

        if (!$featuredSalonId) {
            return response()->json([
                'salon' => null,
                'text' => $featuredSalonText,
                'visibility' => $featuredSalonVisibility,
                'show_top_rated' => $showTopRated,
                'show_newest' => $showNewest,
                'message' => 'Nema istaknutog salona',
            ]);
        }

        $salon = \App\Models\Salon::with(['images', 'services', 'reviews'])
            ->where('id', $featuredSalonId)
            ->where('status', 'approved')
            ->first();

        if (!$salon) {
            return response()->json([
                'salon' => null,
                'text' => $featuredSalonText,
                'visibility' => $featuredSalonVisibility,
                'show_top_rated' => $showTopRated,
                'show_newest' => $showNewest,
                'message' => 'Istaknuti salon nije pronađen ili nije aktivan',
            ]);
        }

        // Calculate average rating
        $avgRating = $salon->reviews->avg('rating') ?? 0;
        $reviewCount = $salon->reviews->count();

        // Get primary image or first image - use url accessor which returns full URL
        $primaryImage = $salon->images->firstWhere('is_primary', true);
        $coverImageUrl = $salon->cover_image
            ?? ($primaryImage ? $primaryImage->url : null)
            ?? ($salon->images->first() ? $salon->images->first()->url : null);

        return response()->json([
            'salon' => [
                'id' => $salon->id,
                'name' => $salon->name,
                'slug' => $salon->slug,
                'description' => $salon->description,
                'address' => $salon->address,
                'city' => $salon->city,
                'cover_image' => $coverImageUrl,
                'logo' => $salon->logo,
                'images' => $salon->images->map(fn($img) => [
                    'id' => $img->id,
                    'url' => $img->url,
                    'is_primary' => $img->is_primary,
                ]),
                'average_rating' => round($avgRating, 1),
                'review_count' => $reviewCount,
                'services_count' => $salon->services->count(),
            ],
            'text' => $featuredSalonText,
            'visibility' => $featuredSalonVisibility,
            'show_top_rated' => $showTopRated,
            'show_newest' => $showNewest,
        ]);
    }

    /**
     * Get featured salon settings (admin only)
     */
    public function getFeaturedSalonAdmin(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $featuredSalonId = SystemSetting::get('featured_salon_id', null);
        $featuredSalonText = SystemSetting::get('featured_salon_text', 'Otvoren je novi salon u vašem gradu');
        $featuredSalonVisibility = SystemSetting::get('featured_salon_visibility', 'all');
        $showTopRated = SystemSetting::get('show_top_rated_salons', true);
        $showNewest = SystemSetting::get('show_newest_salons', true);

        $salon = null;
        if ($featuredSalonId) {
            $salon = \App\Models\Salon::select('id', 'name', 'slug', 'city')
                ->where('id', $featuredSalonId)
                ->first();
        }

        return response()->json([
            'featured_salon_id' => $featuredSalonId,
            'featured_salon_text' => $featuredSalonText,
            'featured_salon_visibility' => $featuredSalonVisibility,
            'show_top_rated' => $showTopRated,
            'show_newest' => $showNewest,
            'salon' => $salon,
        ]);
    }

    /**
     * Update featured salon (admin only)
     */
    public function updateFeaturedSalon(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'salon_id' => 'nullable|integer|exists:salons,id',
            'text' => 'nullable|string|max:255',
            'visibility' => 'nullable|string|in:all,location_only',
            'show_top_rated' => 'nullable|boolean',
            'show_newest' => 'nullable|boolean',
        ]);

        if (isset($validated['salon_id'])) {
            if ($validated['salon_id']) {
                SystemSetting::set('featured_salon_id', $validated['salon_id'], 'integer', 'appearance');
            } else {
                // Remove featured salon
                $setting = SystemSetting::where('key', 'featured_salon_id')->first();
                if ($setting) {
                    $setting->delete();
                }
            }
        }

        if (isset($validated['text'])) {
            SystemSetting::set('featured_salon_text', $validated['text'], 'string', 'appearance');
        }

        if (isset($validated['visibility'])) {
            SystemSetting::set('featured_salon_visibility', $validated['visibility'], 'string', 'appearance');
        }

        if (isset($validated['show_top_rated'])) {
            SystemSetting::set('show_top_rated_salons', $validated['show_top_rated'], 'boolean', 'appearance');
        }

        if (isset($validated['show_newest'])) {
            SystemSetting::set('show_newest_salons', $validated['show_newest'], 'boolean', 'appearance');
        }

        return response()->json([
            'message' => 'Istaknuti salon je uspješno ažuriran',
            'featured_salon_id' => SystemSetting::get('featured_salon_id', null),
            'featured_salon_text' => SystemSetting::get('featured_salon_text', 'Otvoren je novi salon u vašem gradu'),
            'featured_salon_visibility' => SystemSetting::get('featured_salon_visibility', 'all'),
            'show_top_rated' => SystemSetting::get('show_top_rated_salons', true),
            'show_newest' => SystemSetting::get('show_newest_salons', true),
        ]);
    }

    /**
     * Cast value to appropriate type
     */
    private function castValue($value, string $type)
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'json' => json_decode($value, true),
            default => $value,
        };
    }
}
