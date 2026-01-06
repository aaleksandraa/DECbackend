<?php

namespace App\Services\Chatbot;

use App\Models\ChatbotConversation;
use App\Models\ChatbotMessage;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Appointment;
use App\Services\BookingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ConversationService
{
    public function __construct(
        private OpenAIService $openAI,
        private BookingService $booking,
    ) {}

    /**
     * Main entry point: Process incoming message
     */
    public function processMessage(
        Salon $salon,
        string $threadId,
        string $senderPsid,
        string $messageText,
        array $metaPayload
    ): array {
        // 0. Check if chatbot is enabled for this salon (soft-launch guard)
        if (!$salon->chatbot_enabled) {
            Log::info('Chatbot disabled for salon', [
                'salon_id' => $salon->id,
                'salon_name' => $salon->name,
            ]);

            return [
                'conversation_id' => null,
                'response_text' => 'Hvala na poruci! Naš tim će vam se javiti uskoro. 😊',
                'requires_human' => true,
                'action' => 'disabled',
                'meta' => [
                    'reason' => 'chatbot_disabled_for_salon',
                ],
            ];
        }

        // 1. Get or create conversation (outside transaction)
        $conversation = $this->getOrCreateConversation($salon, $threadId, $senderPsid, $metaPayload);

        // 2. Store inbound message (quick write)
        $inboundMessage = $this->storeInboundMessage($conversation, $messageText, $metaPayload);

        // 3. Build context for AI
        $context = $this->buildContext($conversation, $salon);

        // 4. Analyze message with AI (OUTSIDE transaction - can take 1-3s)
        $analysis = $this->openAI->analyzeMessage($messageText, $context);

        // 5. Determine action and get data
        $action = $this->determineAction($conversation, $analysis);
        $actionData = $this->getActionData($conversation, $action, $salon);

        // 6. Check if human takeover is needed
        if ($this->shouldRequireHuman($analysis, $conversation, $messageText)) {
            $conversation->update(['requires_human' => true]);

            Log::info('Human takeover triggered', [
                'conversation_id' => $conversation->id,
                'reason' => 'low_confidence_or_explicit_request',
            ]);

            return [
                'conversation_id' => $conversation->id,
                'response_text' => 'Hvala na poruci! Naš tim će vam se javiti uskoro. 😊',
                'requires_human' => true,
                'action' => 'human_takeover',
                'meta' => [
                    'intent' => $analysis['intent'],
                    'confidence' => $analysis['confidence'],
                    'state' => $conversation->state,
                ],
            ];
        }

        // 7. Generate response with AI (OUTSIDE transaction - can take 1-3s)
        $responseText = $this->openAI->generateResponse($context, $action, $actionData);

        // 8. Update database (FAST transaction for writes only)
        DB::transaction(function() use ($conversation, $inboundMessage, $analysis, $responseText, $action) {
            // Update inbound message with AI analysis
            $inboundMessage->update([
                'ai_processed' => true,
                'ai_intent' => $analysis['intent'],
                'ai_entities' => $analysis['entities'],
                'ai_confidence' => $analysis['confidence'],
                'ai_processing_time_ms' => $analysis['processing_time_ms'],
            ]);

            // Update conversation state and context
            $this->updateConversationFromAnalysis($conversation, $analysis);

            // Store outbound message
            $this->storeOutboundMessage($conversation, $responseText, $action);

            // Update conversation metrics (count ACTUAL messages created)
            $conversation->update(['last_message_at' => now()]);
        });

        // Refresh conversation to get updated state
        $conversation->refresh();

        // 9. Check if we should create booking (AFTER confirmation)
        $bookingResult = null;
        if ($conversation->state === 'confirming' && $this->isConfirmationMessage($messageText)) {
            try {
                $bookingResult = $this->createBooking($conversation);
                $responseText = "✅ Vaš termin je uspješno zakazan! Vidimo se " .
                    $conversation->getContextValue('date') . " u " .
                    $conversation->getContextValue('time') . ". Hvala!";
            } catch (\Exception $e) {
                Log::error('Booking creation failed', [
                    'conversation_id' => $conversation->id,
                    'error' => $e->getMessage(),
                ]);
                $responseText = "Žao mi je, došlo je do greške pri kreiranju termina. Molimo vas kontaktirajte nas direktno.";
                $conversation->update(['requires_human' => true]);
            }
        }

        return [
            'conversation_id' => $conversation->id,
            'response_text' => $responseText,
            'action' => $action,
            'requires_human' => $conversation->requires_human,
            'booking_created' => $bookingResult !== null,
            'meta' => [
                'intent' => $analysis['intent'],
                'confidence' => $analysis['confidence'],
                'state' => $conversation->state,
            ],
        ];
    }

    private function getOrCreateConversation(Salon $salon, string $threadId, string $senderPsid, array $payload): ChatbotConversation
    {
        $integration = $salon->socialIntegrations()->active()->first();

        if (!$integration) {
            throw new \Exception("No active social integration for salon {$salon->id}");
        }

        // ✅ FIX: salon_id MUST be in WHERE clause to prevent multi-tenant collision
        // Thread IDs can collide between:
        // - Different salons
        // - Facebook vs Instagram
        // - Meta's thread_id reuse
        return ChatbotConversation::firstOrCreate(
            [
                'salon_id' => $salon->id,  // ✅ CRITICAL: Scope by salon
                'thread_id' => $threadId,
                'platform' => $payload['platform'] ?? 'instagram',
            ],
            [
                'social_integration_id' => $integration->id,
                'sender_psid' => $senderPsid,
                'sender_name' => $payload['sender_name'] ?? null,
                'state' => 'new',
                'started_at' => now(),
            ]
        );
    }

    private function storeInboundMessage(ChatbotConversation $conversation, string $text, array $payload): ChatbotMessage
    {
        return ChatbotMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'message_type' => 'text',
            'message_text' => $text,
            'message_payload' => $payload,
            'meta_message_id' => $payload['message_id'] ?? null,
            'created_at' => now(),
        ]);
    }

    private function storeOutboundMessage(ChatbotConversation $conversation, string $text, string $action): ChatbotMessage
    {
        return ChatbotMessage::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'message_type' => 'text',
            'message_text' => $text,
            'template_used' => $action,
            'ai_generated' => true,
            'created_at' => now(),
        ]);
    }

    private function buildContext(ChatbotConversation $conversation, Salon $salon): array
    {
        // Get recent messages for context
        $recentMessages = $conversation->messages()
            ->orderBy('created_at', 'desc')
            ->limit(config('chatbot.conversation.max_context_messages', 10))
            ->get()
            ->reverse()
            ->map(fn($m) => [
                'role' => $m->direction === 'inbound' ? 'user' : 'assistant',
                'content' => $m->message_text,
            ])
            ->toArray();

        return [
            'salon' => [
                'name' => $salon->name,
                'address' => $salon->address,
                'phone' => $salon->phone,
                'city' => $salon->city,
            ],
            'conversation_state' => $conversation->state,
            'previous_context' => $conversation->context ?? [],
            'recent_messages' => $recentMessages,
            'intent' => $conversation->intent,
        ];
    }

    private function updateConversationFromAnalysis(ChatbotConversation $conversation, array $analysis): void
    {
        $updates = [
            'intent' => $analysis['intent'],
            'confidence' => $analysis['confidence'],
        ];

        // Update context with extracted entities
        if (!empty($analysis['entities'])) {
            $conversation->updateContext($analysis['entities']);
        }

        // State transitions based on intent and current state
        $newState = $this->determineNewState($conversation->state, $analysis);
        if ($newState !== $conversation->state) {
            $updates['state'] = $newState;
        }

        // Flag for human if confidence is low
        if ($analysis['confidence'] < config('chatbot.conversation.low_confidence_threshold', 0.5)) {
            $updates['requires_human'] = true;
        }

        $conversation->update($updates);
    }

    private function determineNewState(string $currentState, array $analysis): string
    {
        $intent = $analysis['intent'];
        $entities = $analysis['entities'];

        // State machine logic
        return match([$currentState, $intent]) {
            ['new', 'booking'] => 'collecting_service',
            ['new', _] => 'greeting',

            ['greeting', 'booking'] => 'collecting_service',
            ['collecting_service', 'booking'] => isset($entities['service']) ? 'collecting_datetime' : 'collecting_service',
            ['collecting_datetime', 'booking'] => isset($entities['date']) ? 'collecting_contact' : 'collecting_datetime',
            ['collecting_contact', 'booking'] => 'confirming',
            ['confirming', 'booking'] => 'booked',

            [_, 'pricing'] => 'greeting', // Answer and return to greeting
            [_, 'hours'] => 'greeting',
            [_, 'location'] => 'greeting',

            default => $currentState,
        };
    }

    private function determineAction(ChatbotConversation $conversation, array $analysis): string
    {
        $state = $conversation->state;
        $intent = $analysis['intent'];

        return match($state) {
            'new' => 'greet',
            'greeting' => match($intent) {
                'booking' => 'ask_service',
                'pricing' => 'provide_pricing',
                'hours' => 'provide_hours',
                'location' => 'provide_location',
                default => 'greet',
            },
            'collecting_service' => 'ask_service',
            'collecting_datetime' => $conversation->getContextValue('date') ? 'ask_time' : 'ask_date',
            'collecting_contact' => 'ask_contact',
            'confirming' => 'confirm_booking',
            'booked' => 'booking_success',
            default => 'general_response',
        };
    }

    private function getActionData(ChatbotConversation $conversation, string $action, Salon $salon): array
    {
        return match($action) {
            'ask_service' => [
                'services' => $salon->services()->where('is_active', true)->pluck('name')->toArray(),
            ],

            'ask_time' => [
                'date' => $conversation->getContextValue('date'),
                'service' => $conversation->getContextValue('service'),
                'available_slots' => $this->getAvailableSlots($conversation, $salon),
            ],

            'provide_pricing' => [
                'pricing' => $salon->services()->where('is_active', true)->get(['name', 'price', 'duration'])->toArray(),
            ],

            'provide_hours' => [
                'hours' => $this->getSalonHours($salon),
            ],

            'provide_location' => [
                'address' => $salon->address,
                'city' => $salon->city,
                'google_maps_url' => $salon->google_maps_url ?? null,
            ],

            'confirm_booking' => [
                'service' => $conversation->getContextValue('service'),
                'date' => $conversation->getContextValue('date'),
                'time' => $conversation->getContextValue('time'),
            ],

            default => [],
        };
    }

    private function getAvailableSlots(ChatbotConversation $conversation, Salon $salon): array
    {
        $serviceId = $this->resolveServiceId($conversation->getContextValue('service'), $salon);
        $date = $this->normalizeDate($conversation->getContextValue('date'));

        if (!$serviceId || !$date) {
            return [];
        }

        try {
            // Use existing BookingService availability logic
            $availability = $this->booking->getAvailability($salon->id, $serviceId, $date);

            return $availability['slots'] ?? [];
        } catch (\Exception $e) {
            Log::error('Failed to get availability', [
                'salon_id' => $salon->id,
                'service_id' => $serviceId,
                'date' => $date,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function getSalonHours(Salon $salon): array
    {
        // Get working hours from database or return default
        $hours = [];

        $daysMap = [
            1 => 'Ponedjeljak',
            2 => 'Utorak',
            3 => 'Srijeda',
            4 => 'Četvrtak',
            5 => 'Petak',
            6 => 'Subota',
            0 => 'Nedjelja',
        ];

        foreach ($daysMap as $dayNum => $dayName) {
            $hours[] = [
                'day' => $dayName,
                'open' => '09:00',
                'close' => '17:00',
            ];
        }

        return $hours;
    }

    private function resolveServiceId(?string $serviceName, Salon $salon): ?int
    {
        if (!$serviceName) return null;

        // Fuzzy match service name
        $service = $salon->services()
            ->where('is_active', true)
            ->where(function($query) use ($serviceName) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($serviceName) . '%']);
            })
            ->first();

        return $service?->id;
    }

    private function normalizeDate(?string $dateStr): ?string
    {
        if (!$dateStr) return null;

        try {
            // Handle relative dates
            $dateStr = mb_strtolower($dateStr);

            if (str_contains($dateStr, 'danas')) {
                return Carbon::today()->format('Y-m-d');
            }

            if (str_contains($dateStr, 'sutra')) {
                return Carbon::tomorrow()->format('Y-m-d');
            }

            if (str_contains($dateStr, 'prekosutra')) {
                return Carbon::today()->addDays(2)->format('Y-m-d');
            }

            // Try to parse as date
            $date = Carbon::parse($dateStr);
            return $date->format('Y-m-d');

        } catch (\Exception $e) {
            Log::warning('Failed to normalize date', [
                'date_str' => $dateStr,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Check if human takeover is needed
     */
    private function shouldRequireHuman(array $analysis, ChatbotConversation $conversation, string $messageText): bool
    {
        // Low confidence
        if ($analysis['confidence'] < config('chatbot.conversation.low_confidence_threshold', 0.5)) {
            return true;
        }

        // Too many messages without progress (stuck in loop)
        if ($conversation->message_count > 10 && !$conversation->appointment_id) {
            Log::info('Too many messages without booking', [
                'conversation_id' => $conversation->id,
                'message_count' => $conversation->message_count,
            ]);
            return true;
        }

        // User explicitly asks for human
        $humanKeywords = ['čovjek', 'osoba', 'zaposleni', 'agent', 'pomoć', 'razgovarati', 'kontakt'];
        $lowerMessage = mb_strtolower($messageText);

        foreach ($humanKeywords as $keyword) {
            if (str_contains($lowerMessage, $keyword)) {
                Log::info('User requested human', [
                    'conversation_id' => $conversation->id,
                    'keyword' => $keyword,
                ]);
                return true;
            }
        }

        return false;
    }

    /**
     * Check if message is a confirmation (da, yes, potvrđujem, etc.)
     */
    private function isConfirmationMessage(string $text): bool
    {
        $text = mb_strtolower(trim($text));

        $confirmations = ['da', 'yes', 'potvrđujem', 'potvrdi', 'ok', 'u redu', 'važi', 'ajde'];

        foreach ($confirmations as $word) {
            if (str_contains($text, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create actual booking (called after user confirmation)
     */
    public function createBooking(ChatbotConversation $conversation): array
    {
        $salon = $conversation->salon;

        // Extract all required data from context
        $serviceId = $this->resolveServiceId($conversation->getContextValue('service'), $salon);
        $date = $this->normalizeDate($conversation->getContextValue('date'));
        $time = $conversation->getContextValue('time');
        $clientName = $conversation->getContextValue('client_name');
        $clientPhone = $conversation->getContextValue('client_phone');

        // Validate
        if (!$serviceId || !$date || !$time || !$clientName || !$clientPhone) {
            throw new \Exception('Missing required booking data');
        }

        // Use EXISTING public booking endpoint logic
        $bookingData = [
            'salon_id' => $salon->id,
            'service_ids' => [$serviceId],
            'date' => $date,
            'time' => $time,
            'client_name' => $clientName,
            'client_phone' => $clientPhone,
            'client_email' => $conversation->getContextValue('client_email'),
            'booking_source' => 'chatbot', // Track source
            'notes' => 'Rezervacija preko Instagram/Facebook chatbota',
        ];

        // Call existing booking service
        $appointment = $this->booking->createPublicBooking($bookingData);

        // Link appointment to conversation
        $conversation->update([
            'appointment_id' => $appointment->id,
            'state' => 'booked',
            'completed_at' => now(),
        ]);

        return [
            'success' => true,
            'appointment' => $appointment,
        ];
    }
}
