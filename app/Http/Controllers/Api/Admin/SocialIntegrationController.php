<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Chatbot\MetaService;
use App\Models\SocialIntegration;
use App\Models\Salon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SocialIntegrationController extends Controller
{
    public function __construct(private MetaService $metaService) {}

    /**
     * Get current integration
     * GET /api/v1/admin/social-integrations
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Get salon based on user role
        if ($user->role === 'vlasnik') {
            $salon = $user->salon;
        } elseif ($user->role === 'frizer') {
            $salon = $user->staff?->salon;
        } else {
            return response()->json(['error' => 'No salon associated'], 403);
        }

        if (!$salon) {
            return response()->json(['error' => 'No salon found'], 404);
        }

        $integration = SocialIntegration::where('salon_id', $salon->id)
            ->where('provider', 'meta')
            ->where('status', 'active')
            ->first();

        if (!$integration) {
            return response()->json(['error' => 'No integration found'], 404);
        }

        // Get stats (last 30 days)
        $stats = $integration->getStats(30);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $integration->id,
                'platform' => $integration->platform,
                'fb_page_name' => $integration->fb_page_name,
                'ig_username' => $integration->ig_username,
                'status' => $integration->status,
                'auto_reply_enabled' => $integration->auto_reply_enabled,
                'connected_at' => $integration->created_at->toISOString(),
                'stats' => $stats,
            ],
        ]);
    }

    /**
     * Initiate OAuth flow
     * GET /api/v1/admin/social-integrations/connect
     */
    public function connect(Request $request)
    {
        $user = Auth::user();

        // Get salon
        if ($user->role === 'vlasnik') {
            $salon = $user->salon;
        } elseif ($user->role === 'frizer') {
            $salon = $user->staff?->salon;
        } else {
            return redirect('/admin/settings')->with('error', 'Nemate pristup ovoj funkciji');
        }

        if (!$salon) {
            return redirect('/admin/settings')->with('error', 'Salon nije pronađen');
        }

        // Generate state token for CSRF protection
        $state = base64_encode(json_encode([
            'salon_id' => $salon->id,
            'user_id' => $user->id,
            'timestamp' => now()->timestamp,
        ]));

        session(['oauth_state' => $state]);

        // Build Meta OAuth URL
        $appId = config('chatbot.meta.app_id');
        $redirectUri = url('/api/v1/admin/social-integrations/callback');

        $scopes = [
            'pages_show_list',
            'pages_messaging',
            'pages_manage_metadata',
            'instagram_basic',
            'instagram_manage_messages',
        ];

        $url = "https://www.facebook.com/v18.0/dialog/oauth?" . http_build_query([
            'client_id' => $appId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => implode(',', $scopes),
        ]);

        return redirect($url);
    }

    /**
     * OAuth callback
     * GET /api/v1/admin/social-integrations/callback
     */
    public function callback(Request $request)
    {
        $code = $request->query('code');
        $state = $request->query('state');
        $error = $request->query('error');

        // Check for OAuth error
        if ($error) {
            Log::warning('OAuth error', ['error' => $error]);
            return redirect('/admin/settings')->with('error', 'Autorizacija otkazana');
        }

        // Verify state
        if (!$state || $state !== session('oauth_state')) {
            Log::warning('Invalid OAuth state', ['provided' => $state]);
            return redirect('/admin/settings')->with('error', 'Nevažeći state token');
        }

        $stateData = json_decode(base64_decode($state), true);

        // ✅ Check state expiry (5 minutes)
        if (now()->timestamp - $stateData['timestamp'] > 300) {
            Log::warning('OAuth state expired', [
                'age_seconds' => now()->timestamp - $stateData['timestamp'],
            ]);
            return redirect('/admin/settings')->with('error', 'State token je istekao. Pokušajte ponovo.');
        }

        $salon = Salon::find($stateData['salon_id']);

        if (!$salon) {
            return redirect('/admin/settings')->with('error', 'Salon nije pronađen');
        }

        try {
            // Exchange code for token
            $tokenData = $this->exchangeCodeForToken($code);

            // Get user's pages
            $pages = $this->getUserPages($tokenData['access_token']);

            if (empty($pages)) {
                throw new \Exception('Nema dostupnih stranica. Molimo vas povežite Facebook Page.');
            }

            // ✅ PRODUCTION: Let user choose page (for now, take first)
            // TODO: Add page selection UI in frontend
            $page = $pages[0];

            if (count($pages) > 1) {
                Log::info('Multiple pages available, using first', [
                    'salon_id' => $salon->id,
                    'page_count' => count($pages),
                    'selected_page' => $page['name'],
                ]);
            }

            // Get page access token
            $pageToken = $page['access_token'];

            // Exchange for long-lived token
            $longLivedToken = $this->metaService->exchangeToken($pageToken);

            // Get page info (including IG account)
            $pageInfo = $this->metaService->getPageInfo($page['id'], $longLivedToken['access_token']);

            // Subscribe app to page
            $this->metaService->subscribeApp($page['id'], $longLivedToken['access_token']);

            // Determine platform
            $platform = 'facebook';
            if (isset($pageInfo['instagram_business_account'])) {
                $platform = 'both';
            }

            // Save integration
            $integration = SocialIntegration::updateOrCreate(
                [
                    'salon_id' => $salon->id,
                    'provider' => 'meta',
                ],
                [
                    'platform' => $platform,
                    'fb_page_id' => $pageInfo['id'],
                    'fb_page_name' => $pageInfo['name'],
                    'ig_business_account_id' => $pageInfo['instagram_business_account']['id'] ?? null,
                    'ig_username' => $pageInfo['instagram_business_account']['username'] ?? null,
                    'access_token' => $longLivedToken['access_token'],
                    'token_expires_at' => now()->addSeconds($longLivedToken['expires_in']),
                    'granted_scopes' => $page['granted_scopes'] ?? [],
                    'status' => 'active',
                    'connected_by_user_id' => $stateData['user_id'],
                    'webhook_verified' => true,
                    'last_verified_at' => now(),
                ]
            );

            Log::info('Social integration created', [
                'salon_id' => $salon->id,
                'integration_id' => $integration->id,
                'platform' => $platform,
            ]);

            return redirect('/admin/settings')->with('success', 'Instagram/Facebook uspješno povezan!');

        } catch (\Exception $e) {
            Log::error('OAuth callback failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect('/admin/settings')->with('error', 'Greška: ' . $e->getMessage());
        }
    }

    /**
     * Update integration settings
     * PATCH /api/v1/admin/social-integrations/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'auto_reply_enabled' => 'sometimes|boolean',
            'business_hours_only' => 'sometimes|boolean',
        ]);

        $user = Auth::user();

        // Get salon
        if ($user->role === 'vlasnik') {
            $salon = $user->salon;
        } elseif ($user->role === 'frizer') {
            $salon = $user->staff?->salon;
        } else {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $integration = SocialIntegration::where('id', $id)
            ->where('salon_id', $salon->id)
            ->firstOrFail();

        $integration->update($validated);

        return response()->json([
            'success' => true,
            'data' => $integration,
        ]);
    }

    /**
     * Disconnect integration
     * POST /api/v1/admin/social-integrations/disconnect
     */
    public function disconnect(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Get salon
        if ($user->role === 'vlasnik') {
            $salon = $user->salon;
        } elseif ($user->role === 'frizer') {
            $salon = $user->staff?->salon;
        } else {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $integration = SocialIntegration::where('salon_id', $salon->id)
            ->where('provider', 'meta')
            ->first();

        if ($integration) {
            $integration->update(['status' => 'revoked']);
            $integration->delete(); // Soft delete

            Log::info('Social integration disconnected', [
                'salon_id' => $salon->id,
                'integration_id' => $integration->id,
            ]);
        }

        return response()->json(['success' => true]);
    }

    private function exchangeCodeForToken(string $code): array
    {
        $response = Http::get('https://graph.facebook.com/v18.0/oauth/access_token', [
            'client_id' => config('chatbot.meta.app_id'),
            'client_secret' => config('chatbot.meta.app_secret'),
            'redirect_uri' => url('/api/v1/admin/social-integrations/callback'),
            'code' => $code,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Token exchange failed: ' . $response->body());
        }

        return $response->json();
    }

    private function getUserPages(string $userToken): array
    {
        $response = Http::withToken($userToken)->get('https://graph.facebook.com/v18.0/me/accounts', [
            'fields' => 'id,name,access_token,granted_scopes',
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to get pages: ' . $response->body());
        }

        return $response->json('data', []);
    }
}
