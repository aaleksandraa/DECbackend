<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Chatbot\ConversationService;
use App\Models\Salon;
use App\Models\SocialIntegration;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ChatbotController extends Controller
{
    public function __construct(
        private ConversationService $conversationService
    ) {}

    /**
     * Process message from n8n
     * POST /api/v1/chatbot/message
     */
    public function processMessage(Request $request): JsonResponse
    {
        // Verify API key
        $apiKey = $request->header('X-API-Key');
        if (!$apiKey || $apiKey !== config('services.n8n.api_key')) {
            Log::warning('Unauthorized chatbot API access', [
                'ip' => $request->ip(),
                'provided_key' => $apiKey ? 'present' : 'missing',
            ]);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // ✅ OPTIONAL: Verify Meta webhook signature if provided
        // n8n should forward X-Hub-Signature-256 header from Meta
        $signature = $request->header('X-Hub-Signature-256');
        if ($signature && config('chatbot.meta.verify_webhook_signature', true)) {
            $body = $request->getContent();
            if (!$this->verifyWebhookSignature($body, $signature)) {
                Log::warning('Invalid webhook signature', [
                    'ip' => $request->ip(),
                ]);
                return response()->json(['error' => 'Invalid signature'], 401);
            }
        }

        $validated = $request->validate([
            'recipient_id' => 'required|string',  // ✅ Meta Page ID or IG Business Account ID
            'thread_id' => 'required|string',
            'sender_psid' => 'required|string',
            'message_text' => 'required|string|max:1000',
            'platform' => 'required|in:facebook,instagram',
            'meta_payload' => 'sometimes|array',
        ]);

        try {
            // ✅ SECURITY: Map salon via recipient_id (page/IG account), NOT from request
            // This prevents spoofing: attacker can't send salon_id=X to impersonate salon
            $integration = SocialIntegration::active()
                ->autoReplyEnabled()  // ✅ Separate scope for auto-reply
                ->where(function($query) use ($validated) {
                    $query->where('fb_page_id', $validated['recipient_id'])
                          ->orWhere('ig_business_account_id', $validated['recipient_id']);
                })
                ->with('salon')
                ->first();

            if (!$integration) {
                Log::warning('No integration found for recipient', [
                    'recipient_id' => $validated['recipient_id'],
                    'platform' => $validated['platform'],
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'No active integration found for this recipient',
                ], 404);
            }

            $salon = $integration->salon;

            // Check if chatbot is enabled
            if (!config('chatbot.enabled')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Chatbot is disabled',
                ], 503);
            }

            // Process message
            $result = $this->conversationService->processMessage(
                salon: $salon,
                threadId: $validated['thread_id'],
                senderPsid: $validated['sender_psid'],
                messageText: $validated['message_text'],
                metaPayload: array_merge(
                    $validated['meta_payload'] ?? [],
                    [
                        'platform' => $validated['platform'],
                        'source' => 'n8n',
                        'recipient_id' => $validated['recipient_id'],
                    ]
                )
            );

            // ✅ Return access token for this specific integration (multi-tenant safe)
            return response()->json([
                'success' => true,
                'data' => array_merge($result, [
                    'access_token' => $integration->access_token,  // ✅ From DB, not ENV
                    'salon_id' => $salon->id,  // For logging/debugging
                ]),
            ]);

        } catch (\Exception $e) {
            Log::error('Chatbot message processing failed', [
                'recipient_id' => $validated['recipient_id'] ?? null,
                'thread_id' => $validated['thread_id'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to process message',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Verify Meta webhook signature
     */
    private function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $appSecret = config('chatbot.meta.app_secret');

        if (!$appSecret) {
            Log::warning('Meta app secret not configured');
            return false;
        }

        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $appSecret);

        return hash_equals($expectedSignature, $signature);
    }
}
