<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatbotMessage;
use App\Models\SocialIntegration;
use App\Services\Chatbot\ConversationService;
use App\Services\Chatbot\MetaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChatbotController extends Controller
{
    public function __construct(
        private ConversationService $conversationService,
        private MetaService $metaService
    ) {}

    /**
     * Meta webhook verification endpoint.
     * GET /api/v1/chatbot/webhook
     */
    public function verifyWebhook(Request $request)
    {
        $mode = (string) ($request->query('hub_mode') ?? $request->query('hub.mode'));
        $verifyToken = (string) ($request->query('hub_verify_token') ?? $request->query('hub.verify_token'));
        $challenge = (string) ($request->query('hub_challenge') ?? $request->query('hub.challenge'));
        $expectedVerifyToken = (string) config('chatbot.meta.webhook_verify_token', '');

        if (
            $mode === 'subscribe'
            && $verifyToken !== ''
            && $expectedVerifyToken !== ''
            && hash_equals($expectedVerifyToken, $verifyToken)
        ) {
            return response($challenge, 200);
        }

        Log::warning('Meta webhook verification failed', [
            'mode' => $mode,
            'has_challenge' => $challenge !== '',
            'token_present' => $verifyToken !== '',
            'expected_token_configured' => $expectedVerifyToken !== '',
        ]);

        return response('Forbidden', 403);
    }

    /**
     * Direct Meta webhook handler (backend-first, no n8n required).
     * POST /api/v1/chatbot/webhook
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        $verifyWebhookSignature = (bool) config('chatbot.meta.verify_webhook_signature', true);

        if ($verifyWebhookSignature) {
            $signature = (string) $request->header('X-Hub-Signature-256', '');
            if ($signature === '') {
                Log::warning('Missing webhook signature', [
                    'ip' => $request->ip(),
                ]);

                return response()->json(['error' => 'Missing signature'], 401);
            }

            $body = $request->getContent();
            if (!$this->verifyWebhookSignature($body, $signature)) {
                Log::warning('Invalid webhook signature', [
                    'ip' => $request->ip(),
                ]);

                return response()->json(['error' => 'Invalid signature'], 401);
            }
        }

        if (!config('chatbot.enabled')) {
            return response()->json([
                'success' => true,
                'processed' => 0,
                'skipped' => 0,
                'failed' => 0,
                'reason' => 'chatbot_disabled',
            ]);
        }

        $payload = $request->json()->all();
        if (!is_array($payload) || empty($payload)) {
            $payload = $request->all();
        }

        $events = $this->extractInboundEvents($payload);
        if (empty($events)) {
            return response()->json([
                'success' => true,
                'processed' => 0,
                'skipped' => 0,
                'failed' => 0,
            ]);
        }

        $processed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($events as $event) {
            try {
                $status = $this->processInboundEvent($event, $verifyWebhookSignature, true);
                if ($status === 'processed') {
                    $processed++;
                } elseif ($status === 'failed') {
                    $failed++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::error('Webhook event processing failed', [
                    'event' => $event,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'processed' => $processed,
            'skipped' => $skipped,
            'failed' => $failed,
        ]);
    }

    /**
     * Legacy endpoint for n8n compatibility.
     * POST /api/v1/chatbot/message
     */
    public function processMessage(Request $request): JsonResponse
    {
        $apiKey = $request->header('X-API-Key');
        if (!$apiKey || $apiKey !== config('services.n8n.api_key')) {
            Log::warning('Unauthorized chatbot API access', [
                'ip' => $request->ip(),
                'provided_key' => $apiKey ? 'present' : 'missing',
            ]);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'recipient_id' => 'required|string',
            'thread_id' => 'required|string',
            'sender_psid' => 'required|string',
            'message_text' => 'required|string|max:1000',
            'platform' => 'required|in:facebook,instagram',
            'meta_payload' => 'sometimes|array',
        ]);

        try {
            $status = $this->processInboundEvent([
                'recipient_id' => $validated['recipient_id'],
                'thread_id' => $validated['thread_id'],
                'sender_psid' => $validated['sender_psid'],
                'message_text' => $validated['message_text'],
                'platform' => $validated['platform'],
                'meta_payload' => $validated['meta_payload'] ?? [],
            ], false, false);

            if (!is_array($status)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Message skipped',
                ], 422);
            }

            return response()->json([
                'success' => true,
                'data' => $status,
            ]);
        } catch (\Throwable $e) {
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
     * Process one normalized inbound event.
     *
     * @return string|array Returns:
     * - 'processed' | 'skipped' | 'failed' for webhook mode ($sendReply = true)
     * - result payload array for legacy /message mode ($sendReply = false)
     */
    private function processInboundEvent(
        array $event,
        bool $markWebhookVerified,
        bool $sendReply
    ): string|array {
        $messageId = (string) data_get($event, 'meta_payload.message_id', '');
        if (
            $messageId !== ''
            && ChatbotMessage::query()
                ->where('direction', ChatbotMessage::DIRECTION_INBOUND)
                ->where('meta_message_id', $messageId)
                ->exists()
        ) {
            return 'skipped';
        }

        $integration = $this->resolveIntegration(
            (string) ($event['recipient_id'] ?? ''),
            (string) ($event['platform'] ?? 'facebook')
        );

        if (!$integration) {
            Log::warning('No integration found for inbound event', [
                'recipient_id' => $event['recipient_id'] ?? null,
                'platform' => $event['platform'] ?? null,
            ]);

            return 'skipped';
        }

        if ($markWebhookVerified && (!$integration->webhook_verified || !$integration->last_verified_at)) {
            $integration->update([
                'webhook_verified' => true,
                'last_verified_at' => now(),
            ]);
        }

        $salon = $integration->salon;
        if (!$salon || !$integration->shouldAutoReply()) {
            return 'skipped';
        }

        $integration->markMessageReceived();

        $result = $this->conversationService->processMessage(
            salon: $salon,
            threadId: (string) $event['thread_id'],
            senderPsid: (string) $event['sender_psid'],
            messageText: (string) $event['message_text'],
            metaPayload: array_merge(
                (array) ($event['meta_payload'] ?? []),
                [
                    'platform' => $event['platform'] ?? 'facebook',
                    'source' => $sendReply ? 'meta_webhook' : 'n8n',
                    'recipient_id' => $event['recipient_id'] ?? null,
                    'social_integration_id' => $integration->id,
                ]
            )
        );

        if (!$sendReply) {
            return array_merge($result, [
                'access_token' => $integration->access_token,
                'salon_id' => $salon->id,
            ]);
        }

        $responseText = trim((string) ($result['response_text'] ?? ''));
        if ($responseText === '') {
            return 'processed';
        }

        $sendResult = $this->metaService->sendMessage(
            (string) $event['sender_psid'],
            $responseText,
            (string) $integration->access_token
        );

        $conversationId = isset($result['conversation_id']) ? (int) $result['conversation_id'] : null;
        if ($conversationId) {
            $this->trackOutboundDelivery(
                conversationId: $conversationId,
                responseText: $responseText,
                action: isset($result['action']) ? (string) $result['action'] : null,
                sendResult: $sendResult
            );
        }

        return $sendResult['success'] ?? false ? 'processed' : 'failed';
    }

    /**
     * Parse Meta webhook payload into normalized events.
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractInboundEvents(array $payload): array
    {
        $events = [];
        $object = (string) ($payload['object'] ?? '');
        $entries = $payload['entry'] ?? [];

        if (!is_array($entries)) {
            return $events;
        }

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $messaging = $entry['messaging'] ?? [];
            if (is_array($messaging)) {
                foreach ($messaging as $messageEvent) {
                    $normalized = $this->normalizeMessagingEvent($messageEvent, $object);
                    if ($normalized) {
                        $events[] = $normalized;
                    }
                }
            }

            $changes = $entry['changes'] ?? [];
            if (is_array($changes)) {
                foreach ($changes as $change) {
                    $normalized = $this->normalizeChangeEvent($change, $entry, $object);
                    if ($normalized) {
                        $events[] = $normalized;
                    }
                }
            }
        }

        return $events;
    }

    /**
     * Normalize entry.messaging events (Messenger/Instagram messaging).
     */
    private function normalizeMessagingEvent(mixed $event, string $object): ?array
    {
        if (!is_array($event)) {
            return null;
        }

        if (!isset($event['message']) || !is_array($event['message'])) {
            return null;
        }

        if ((bool) data_get($event, 'message.is_echo', false)) {
            return null;
        }

        $text = trim((string) data_get($event, 'message.text', ''));
        if ($text === '') {
            return null;
        }

        $senderId = (string) data_get($event, 'sender.id', '');
        $recipientId = (string) data_get($event, 'recipient.id', '');
        if ($senderId === '' || $recipientId === '') {
            return null;
        }

        $messageId = (string) data_get($event, 'message.mid', '');
        $threadId = (string) data_get($event, 'conversation.id', '');
        if ($threadId === '') {
            $threadId = $senderId;
        }

        $platform = $object === 'instagram' ? 'instagram' : 'facebook';

        return [
            'recipient_id' => $recipientId,
            'thread_id' => $threadId,
            'sender_psid' => $senderId,
            'message_text' => $text,
            'platform' => $platform,
            'meta_payload' => [
                'message_id' => $messageId !== '' ? $messageId : null,
                'raw_event' => $event,
            ],
        ];
    }

    /**
     * Normalize entry.changes events (Instagram fallback format).
     */
    private function normalizeChangeEvent(mixed $change, array $entry, string $object): ?array
    {
        if (!is_array($change)) {
            return null;
        }

        if ((string) ($change['field'] ?? '') !== 'messages') {
            return null;
        }

        $value = $change['value'] ?? null;
        if (!is_array($value)) {
            return null;
        }

        $text = trim((string) data_get($value, 'message.text', data_get($value, 'text', '')));
        if ($text === '') {
            return null;
        }

        $senderId = (string) data_get($value, 'from.id', data_get($value, 'sender.id', ''));
        $recipientId = (string) data_get($value, 'recipient.id', data_get($entry, 'id', ''));
        if ($senderId === '' || $recipientId === '') {
            return null;
        }

        $messageId = (string) data_get($value, 'message.mid', data_get($value, 'mid', ''));
        $threadId = (string) data_get($value, 'conversation.id', '');
        if ($threadId === '') {
            $threadId = $senderId;
        }

        $platform = $object === 'instagram' ? 'instagram' : 'facebook';

        return [
            'recipient_id' => $recipientId,
            'thread_id' => $threadId,
            'sender_psid' => $senderId,
            'message_text' => $text,
            'platform' => $platform,
            'meta_payload' => [
                'message_id' => $messageId !== '' ? $messageId : null,
                'raw_change' => $change,
            ],
        ];
    }

    private function resolveIntegration(string $recipientId, string $platform): ?SocialIntegration
    {
        if ($recipientId === '') {
            return null;
        }

        return SocialIntegration::active()
            ->autoReplyEnabled()
            ->forPlatform($platform)
            ->where(function ($query) use ($recipientId) {
                $query->where('fb_page_id', $recipientId)
                    ->orWhere('ig_business_account_id', $recipientId);
            })
            ->with('salon')
            ->first();
    }

    private function trackOutboundDelivery(
        int $conversationId,
        string $responseText,
        ?string $action,
        array $sendResult
    ): void {
        $message = ChatbotMessage::query()
            ->where('conversation_id', $conversationId)
            ->where('direction', ChatbotMessage::DIRECTION_OUTBOUND)
            ->orderByDesc('id')
            ->first();

        if (!$message || trim((string) $message->message_text) !== trim($responseText)) {
            $message = ChatbotMessage::createOutbound(
                $conversationId,
                $responseText,
                $action,
                true
            );
        }

        if ($sendResult['success'] ?? false) {
            $message->markAsSent((string) ($sendResult['message_id'] ?? null));
            return;
        }

        $message->markAsFailed((string) ($sendResult['error'] ?? 'Meta send failed'));
        Log::error('Meta API send message failed', [
            'conversation_id' => $conversationId,
            'error' => $sendResult['error'] ?? 'unknown',
        ]);
    }

    /**
     * Verify Meta webhook signature.
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

