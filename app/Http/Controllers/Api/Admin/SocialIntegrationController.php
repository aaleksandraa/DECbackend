<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Salon;
use App\Models\SocialIntegration;
use App\Services\Chatbot\MetaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Carbon;

class SocialIntegrationController extends Controller
{
    private const PENDING_PAGE_SELECTION_KEY = 'pending_social_page_selection';
    private const PENDING_PAGE_SELECTION_TTL_SECONDS = 600;

    public function __construct(private MetaService $metaService) {}

    /**
     * Get current integration
     * GET /api/v1/admin/social-integrations
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $salon = $this->resolveSalonForUser($user);

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
                'business_hours_only' => $integration->business_hours_only,
                'token_expires_at' => optional($integration->token_expires_at)?->toIso8601String(),
                'chatbot_enabled' => (bool) $salon->chatbot_enabled,
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
        $salon = $this->resolveSalonForUser($user);

        if (!$salon) {
            return redirect($this->integrationRedirectUrl('error_no_salon'));
        }

        $appId = config('chatbot.meta.app_id');
        $appSecret = config('chatbot.meta.app_secret');
        if (!$appId || !$appSecret) {
            Log::error('Meta integration config missing', [
                'has_app_id' => !empty($appId),
                'has_app_secret' => !empty($appSecret),
                'salon_id' => $salon->id,
            ]);

            return redirect($this->integrationRedirectUrl('error_config'));
        }

        $state = base64_encode(json_encode([
            'salon_id' => $salon->id,
            'user_id' => $user->id,
            'timestamp' => now()->timestamp,
        ]));

        Session::put('oauth_state', $state);

        $redirectUri = config('chatbot.meta.oauth_redirect_uri')
            ?: url('/api/v1/admin/social-integrations/callback');

        $scopes = config('chatbot.meta.required_scopes', []);
        if (!is_array($scopes) || empty($scopes)) {
            $scopes = [
                'pages_show_list',
                'pages_messaging',
                'pages_manage_metadata',
                'instagram_basic',
                'instagram_manage_messages',
            ];
        }

        $url = $this->facebookOauthDialogUrl() . '?' . http_build_query([
            'client_id' => $appId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => implode(',', array_unique($scopes)),
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

        if ($error) {
            Log::warning('OAuth error', ['error' => $error]);
            return redirect($this->integrationRedirectUrl('oauth_cancelled'));
        }

        if (!$code) {
            Log::warning('OAuth callback missing code');
            return redirect($this->integrationRedirectUrl('oauth_missing_code'));
        }

        $expectedState = Session::get('oauth_state');
        if (!$state || !$expectedState || !hash_equals($expectedState, $state)) {
            Log::warning('Invalid OAuth state', ['provided' => $state]);
            return redirect($this->integrationRedirectUrl('oauth_invalid_state'));
        }

        $stateData = json_decode(base64_decode($state), true);
        if (!is_array($stateData) || empty($stateData['salon_id']) || empty($stateData['user_id']) || empty($stateData['timestamp'])) {
            Log::warning('OAuth state payload malformed', ['state' => $state]);
            return redirect($this->integrationRedirectUrl('oauth_invalid_payload'));
        }

        $currentUser = Auth::user();
        if (!$currentUser || (int) $currentUser->id !== (int) $stateData['user_id']) {
            Log::warning('OAuth callback user mismatch', [
                'state_user_id' => $stateData['user_id'] ?? null,
                'auth_user_id' => $currentUser?->id,
            ]);
            Session::forget('oauth_state');
            return redirect($this->integrationRedirectUrl('oauth_invalid_user'));
        }

        Session::forget('oauth_state');

        if (now()->timestamp - (int) $stateData['timestamp'] > 300) {
            Log::warning('OAuth state expired', [
                'age_seconds' => now()->timestamp - (int) $stateData['timestamp'],
            ]);
            return redirect($this->integrationRedirectUrl('oauth_state_expired'));
        }

        $salon = Salon::find($stateData['salon_id']);

        if (!$salon) {
            return redirect($this->integrationRedirectUrl('error_no_salon'));
        }

        try {
            $tokenData = $this->exchangeCodeForToken($code);
            if (empty($tokenData['access_token'])) {
                throw new \Exception('Meta did not return access token.');
            }

            // Get long-lived USER token first, then use it to fetch page tokens.
            $longLivedUserToken = $this->metaService->exchangeToken($tokenData['access_token']);
            $pages = $this->getUserPages($longLivedUserToken['access_token']);

            if (empty($pages)) {
                throw new \Exception('No available pages. Please connect a Facebook Page first.');
            }

            $normalizedPages = collect($pages)
                ->filter(fn(array $page) => !empty($page['id']) && !empty($page['name']) && !empty($page['access_token']))
                ->map(fn(array $page) => [
                    'id' => (string) $page['id'],
                    'name' => (string) $page['name'],
                    'access_token' => (string) $page['access_token'],
                    'granted_scopes' => is_array($page['granted_scopes'] ?? null) ? $page['granted_scopes'] : [],
                ])
                ->values()
                ->all();

            if (empty($normalizedPages)) {
                throw new \Exception('No valid Facebook pages were returned by Meta.');
            }

            if (count($normalizedPages) > 1) {
                Session::put(self::PENDING_PAGE_SELECTION_KEY, [
                    'salon_id' => (int) $salon->id,
                    'user_id' => (int) $stateData['user_id'],
                    'meta_user_id' => $tokenData['user_id'] ?? null,
                    'token_expires_in' => isset($longLivedUserToken['expires_in']) ? (int) $longLivedUserToken['expires_in'] : null,
                    'created_at' => now()->toIso8601String(),
                    'pages' => $normalizedPages,
                ]);

                Log::info('Multiple pages available, waiting for explicit selection', [
                    'salon_id' => $salon->id,
                    'page_count' => count($normalizedPages),
                ]);

                return redirect($this->integrationRedirectUrl('select_page'));
            }

            $integration = $this->finalizeIntegrationForPage(
                $salon,
                (int) $stateData['user_id'],
                $tokenData['user_id'] ?? null,
                isset($longLivedUserToken['expires_in']) ? (int) $longLivedUserToken['expires_in'] : null,
                $normalizedPages[0]
            );

            $this->clearPendingPageSelection();

            Log::info('Social integration created', [
                'salon_id' => $salon->id,
                'integration_id' => $integration->id,
                'platform' => $integration->platform,
            ]);

            return redirect($this->integrationRedirectUrl('connected'));
        } catch (\Exception $e) {
            Log::error('OAuth callback failed', [
                'salon_id' => $salon->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect($this->integrationRedirectUrl('error_callback'));
        }
    }

    /**
     * Return pending Facebook page selection after OAuth callback (if multiple pages found).
     * GET /api/v1/admin/social-integrations/pending-pages
     */
    public function pendingPages(Request $request): JsonResponse
    {
        $user = Auth::user();
        $salon = $this->resolveSalonForUser($user);

        if (!$salon) {
            return response()->json(['error' => 'No salon found'], 404);
        }

        $pending = $this->getPendingPageSelection((int) $salon->id, (int) $user->id);
        if (!$pending) {
            return response()->json([
                'success' => true,
                'data' => null,
            ]);
        }

        $createdAt = Carbon::parse($pending['created_at']);
        $expiresAt = $createdAt->copy()->addSeconds(self::PENDING_PAGE_SELECTION_TTL_SECONDS);

        return response()->json([
            'success' => true,
            'data' => [
                'pages' => collect($pending['pages'])
                    ->map(fn(array $page) => [
                        'id' => $page['id'],
                        'name' => $page['name'],
                    ])
                    ->values()
                    ->all(),
                'created_at' => $createdAt->toIso8601String(),
                'expires_at' => $expiresAt->toIso8601String(),
            ],
        ]);
    }

    /**
     * Finalize integration with explicitly selected Facebook page.
     * POST /api/v1/admin/social-integrations/select-page
     */
    public function selectPage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page_id' => 'required|string',
        ]);

        $user = Auth::user();
        $salon = $this->resolveSalonForUser($user);
        if (!$salon) {
            return response()->json(['error' => 'No salon found'], 404);
        }

        $pending = $this->getPendingPageSelection((int) $salon->id, (int) $user->id);
        if (!$pending) {
            return response()->json([
                'error' => 'No pending Facebook page selection found. Please reconnect integration.',
            ], 404);
        }

        $selectedPage = collect($pending['pages'])
            ->first(fn(array $page) => (string) ($page['id'] ?? '') === (string) $validated['page_id']);

        if (!$selectedPage) {
            return response()->json([
                'error' => 'Selected Facebook page is not available.',
            ], 422);
        }

        try {
            $integration = $this->finalizeIntegrationForPage(
                $salon,
                (int) ($pending['user_id'] ?? $user->id),
                $pending['meta_user_id'] ?? null,
                isset($pending['token_expires_in']) ? (int) $pending['token_expires_in'] : null,
                $selectedPage
            );

            $this->clearPendingPageSelection();

            Log::info('Social integration finalized after page selection', [
                'salon_id' => $salon->id,
                'integration_id' => $integration->id,
                'page_id' => $integration->fb_page_id,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $integration->id,
                    'platform' => $integration->platform,
                    'fb_page_name' => $integration->fb_page_name,
                    'ig_username' => $integration->ig_username,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to finalize page selection', [
                'salon_id' => $salon->id,
                'page_id' => $validated['page_id'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to connect selected Facebook page.',
            ], 500);
        }
    }

    /**
     * Admin health-check overview for Meta integrations.
     * GET /api/v1/admin/social-integrations/health
     */
    public function healthCheck(Request $request): JsonResponse
    {
        $integrations = SocialIntegration::query()
            ->where('provider', 'meta')
            ->with(['salon:id,name,city'])
            ->orderByDesc('id')
            ->get()
            ->groupBy('salon_id')
            ->map(fn($items) => $items->first());

        $salonIds = $integrations
            ->keys()
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->values();

        $lastFailedBySalon = collect();

        if ($salonIds->isNotEmpty()) {
            $latestFailedPerSalon = DB::table('chatbot_messages as cm')
                ->join('chatbot_conversations as cc', 'cc.id', '=', 'cm.conversation_id')
                ->whereIn('cc.salon_id', $salonIds->all())
                ->where('cm.direction', 'outbound')
                ->whereNotNull('cm.failed_at')
                ->groupBy('cc.salon_id')
                ->selectRaw('cc.salon_id, MAX(cm.failed_at) as failed_at');

            $lastFailedBySalon = DB::table('chatbot_messages as cm')
                ->join('chatbot_conversations as cc', 'cc.id', '=', 'cm.conversation_id')
                ->joinSub($latestFailedPerSalon, 'latest_failed', function ($join) {
                    $join->on('latest_failed.salon_id', '=', 'cc.salon_id')
                        ->on('latest_failed.failed_at', '=', 'cm.failed_at');
                })
                ->where('cm.direction', 'outbound')
                ->orderByDesc('cm.id')
                ->select([
                    'cc.salon_id',
                    'cm.failed_at',
                    'cm.error_message',
                ])
                ->get()
                ->unique('salon_id')
                ->keyBy('salon_id');
        }

        $rows = $integrations->map(function (SocialIntegration $integration) use ($lastFailedBySalon) {
            $tokenStatus = 'unknown';
            $daysUntilExpiry = null;

            if ($integration->token_expires_at) {
                $daysUntilExpiry = now()->diffInDays($integration->token_expires_at, false);
                if ($daysUntilExpiry < 0) {
                    $tokenStatus = 'expired';
                } elseif ($daysUntilExpiry <= 7) {
                    $tokenStatus = 'expiring_soon';
                } else {
                    $tokenStatus = 'valid';
                }
            }

            $failed = $lastFailedBySalon->get($integration->salon_id);

            return [
                'salon_id' => $integration->salon_id,
                'salon_name' => $integration->salon?->name,
                'salon_city' => $integration->salon?->city,
                'integration_id' => $integration->id,
                'integration_status' => $integration->status,
                'platform' => $integration->platform,
                'fb_page_name' => $integration->fb_page_name,
                'ig_username' => $integration->ig_username,
                'token_expires_at' => optional($integration->token_expires_at)?->toIso8601String(),
                'token_status' => $tokenStatus,
                'days_until_expiry' => $daysUntilExpiry,
                'webhook_verified' => (bool) $integration->webhook_verified,
                'last_verified_at' => optional($integration->last_verified_at)?->toIso8601String(),
                'last_failed_send' => $failed ? [
                    'failed_at' => Carbon::parse($failed->failed_at)->toIso8601String(),
                    'error_message' => $failed->error_message,
                ] : null,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_integrations' => $rows->count(),
                    'active_integrations' => $rows->where('integration_status', 'active')->count(),
                    'expired_tokens' => $rows->where('token_status', 'expired')->count(),
                    'expiring_tokens' => $rows->where('token_status', 'expiring_soon')->count(),
                    'webhook_unverified' => $rows->where('webhook_verified', false)->count(),
                    'salons_with_failed_send' => $rows->filter(fn($row) => !empty($row['last_failed_send']))->count(),
                ],
                'salons' => $rows,
            ],
        ]);
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
            'chatbot_enabled' => 'sometimes|boolean',
        ]);

        $user = Auth::user();
        $salon = $this->resolveSalonForUser($user);
        if (!$salon) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $integration = SocialIntegration::where('id', $id)
            ->where('salon_id', $salon->id)
            ->firstOrFail();

        $chatbotEnabled = $validated['chatbot_enabled'] ?? null;
        unset($validated['chatbot_enabled']);

        if (!empty($validated)) {
            $integration->update($validated);
        }

        if ($chatbotEnabled !== null) {
            $salon->forceFill(['chatbot_enabled' => (bool) $chatbotEnabled])->save();
        }

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
        $salon = $this->resolveSalonForUser($user);
        if (!$salon) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $integration = SocialIntegration::where('salon_id', $salon->id)
            ->where('provider', 'meta')
            ->first();

        if ($integration) {
            $integration->update([
                'status' => 'revoked',
                'auto_reply_enabled' => false,
                'webhook_verified' => false,
                'last_verified_at' => null,
            ]);

            Log::info('Social integration disconnected', [
                'salon_id' => $salon->id,
                'integration_id' => $integration->id,
            ]);
        }

        $salon->forceFill(['chatbot_enabled' => false])->save();

        return response()->json(['success' => true]);
    }

    private function exchangeCodeForToken(string $code): array
    {
        $redirectUri = config('chatbot.meta.oauth_redirect_uri')
            ?: url('/api/v1/admin/social-integrations/callback');

        $response = Http::timeout(15)->get($this->graphApiBaseUrl() . '/oauth/access_token', [
            'client_id' => config('chatbot.meta.app_id'),
            'client_secret' => config('chatbot.meta.app_secret'),
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Token exchange failed: ' . $response->body());
        }

        return $response->json();
    }

    private function getUserPages(string $userToken): array
    {
        $response = Http::timeout(15)->withToken($userToken)->get($this->graphApiBaseUrl() . '/me/accounts', [
            'fields' => 'id,name,access_token,granted_scopes',
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to get pages: ' . $response->body());
        }

        return $response->json('data', []);
    }

    /**
     * Persist selected page as active integration and enable chatbot.
     */
    private function finalizeIntegrationForPage(
        Salon $salon,
        int $connectedByUserId,
        ?string $metaUserId,
        ?int $tokenExpiresInSeconds,
        array $page
    ): SocialIntegration {
        $pageToken = $page['access_token'] ?? null;
        if (!$pageToken) {
            throw new \Exception('Selected page has no valid page token.');
        }

        $pageInfo = $this->metaService->getPageInfo((string) $page['id'], $pageToken);

        $subscribed = $this->metaService->subscribeApp((string) $page['id'], $pageToken);
        if (!$subscribed) {
            throw new \Exception('Failed to subscribe app to Facebook page webhook.');
        }

        $platform = isset($pageInfo['instagram_business_account']) ? 'both' : 'facebook';

        $integration = SocialIntegration::withTrashed()->firstOrNew([
            'salon_id' => $salon->id,
            'provider' => 'meta',
        ]);

        if ($integration->trashed()) {
            $integration->restore();
        }

        $integration->fill([
            'platform' => $platform,
            'fb_page_id' => $pageInfo['id'] ?? $page['id'],
            'fb_page_name' => $pageInfo['name'] ?? $page['name'],
            'ig_business_account_id' => $pageInfo['instagram_business_account']['id'] ?? null,
            'ig_username' => $pageInfo['instagram_business_account']['username'] ?? null,
            'access_token' => $pageToken,
            'token_type' => 'page_access_token',
            'token_expires_at' => $tokenExpiresInSeconds ? now()->addSeconds($tokenExpiresInSeconds) : null,
            'granted_scopes' => $page['granted_scopes'] ?? [],
            'status' => 'active',
            'connected_by_user_id' => $connectedByUserId,
            'meta_user_id' => $metaUserId,
            // Real verification happens on first successfully signed inbound webhook event.
            'webhook_verified' => false,
            'last_verified_at' => null,
        ]);
        $integration->save();

        $salon->forceFill(['chatbot_enabled' => true])->save();

        return $integration;
    }

    private function getPendingPageSelection(int $salonId, int $userId): ?array
    {
        $pending = Session::get(self::PENDING_PAGE_SELECTION_KEY);
        if (!is_array($pending)) {
            return null;
        }

        if ((int) ($pending['salon_id'] ?? 0) !== $salonId || (int) ($pending['user_id'] ?? 0) !== $userId) {
            $this->clearPendingPageSelection();
            return null;
        }

        $pages = $pending['pages'] ?? null;
        if (!is_array($pages) || empty($pages)) {
            $this->clearPendingPageSelection();
            return null;
        }

        $createdAtRaw = $pending['created_at'] ?? null;
        if (!is_string($createdAtRaw) || trim($createdAtRaw) === '') {
            $this->clearPendingPageSelection();
            return null;
        }

        try {
            $createdAt = Carbon::parse($createdAtRaw);
        } catch (\Throwable $e) {
            $this->clearPendingPageSelection();
            return null;
        }

        if ($createdAt->diffInSeconds(now()) > self::PENDING_PAGE_SELECTION_TTL_SECONDS) {
            $this->clearPendingPageSelection();
            return null;
        }

        return $pending;
    }

    private function clearPendingPageSelection(): void
    {
        Session::forget(self::PENDING_PAGE_SELECTION_KEY);
    }

    /**
     * Resolve salon by authenticated user role.
     */
    private function resolveSalonForUser($user): ?Salon
    {
        if (!$user) {
            return null;
        }

        if (method_exists($user, 'isSalonOwner') && $user->isSalonOwner()) {
            $user->loadMissing('ownedSalon');
            return $user->ownedSalon;
        }

        if (method_exists($user, 'isStaff') && $user->isStaff()) {
            $user->loadMissing('staffProfile.salon');
            return $user->staffProfile?->salon;
        }

        return null;
    }

    /**
     * Redirect target for social integration screen in SPA.
     */
    private function integrationRedirectUrl(?string $status = null): string
    {
        $url = '/dashboard?section=social-integrations';
        if ($status) {
            $url .= '&social_status=' . urlencode($status);
        }

        return $url;
    }

    private function graphApiBaseUrl(): string
    {
        $baseUrl = rtrim((string) config('chatbot.meta.graph_api_url', 'https://graph.facebook.com'), '/');
        $version = ltrim((string) config('chatbot.meta.graph_api_version', 'v18.0'), '/');

        return $baseUrl . '/' . $version;
    }

    private function facebookOauthDialogUrl(): string
    {
        $version = ltrim((string) config('chatbot.meta.graph_api_version', 'v18.0'), '/');

        return 'https://www.facebook.com/' . $version . '/dialog/oauth';
    }
}

