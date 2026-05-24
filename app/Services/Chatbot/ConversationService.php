<?php

namespace App\Services\Chatbot;

use App\Models\ChatbotConversation;
use App\Models\ChatbotMessage;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Appointment;
use App\Models\Staff;
use App\Services\AppointmentService;
use App\Services\BookingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ConversationService
{
    public function __construct(
        private OpenAIService $openAI,
        private BookingService $booking,
        private AppointmentService $appointmentService,
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
                'response_text' => 'Hvala na poruci! Nas tim ce vam se javiti uskoro.',
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
        $conversation->incrementMessageCount();

        // 2.5 Fast path: explicit confirmation while already in confirming state.
        // Booking creation should happen here and not via AI state transitions.
        if (
            $conversation->state === ChatbotConversation::STATE_CONFIRMING
            && !$conversation->appointment_id
            && $this->isConfirmationMessage($messageText)
        ) {
            try {
                $bookingResult = $this->createBooking($conversation);
                $responseText = 'Termin je uspjesno zakazan! Vidimo se '
                    . $conversation->getContextValue('date')
                    . ' u '
                    . $conversation->getContextValue('time')
                    . '. Hvala!';

                $this->storeOutboundMessage($conversation, $responseText, 'booking_success');
                $conversation->incrementMessageCount();
                $conversation->update(['last_bot_response_at' => now()]);
                $conversation->refresh();

                return [
                    'conversation_id' => $conversation->id,
                    'response_text' => $responseText,
                    'action' => 'booking_success',
                    'requires_human' => $conversation->requires_human,
                    'booking_created' => $bookingResult !== null,
                    'meta' => [
                        'intent' => ChatbotConversation::INTENT_BOOKING,
                        'confidence' => 1.0,
                        'state' => $conversation->state,
                    ],
                ];
            } catch (\Exception $e) {
                Log::error('Booking creation failed', [
                    'conversation_id' => $conversation->id,
                    'error' => $e->getMessage(),
                ]);

                $responseText = 'Doslo je do greske pri kreiranju termina. Molimo vas kontaktirajte nas direktno.';
                $conversation->update(['requires_human' => true]);
                $this->storeOutboundMessage($conversation, $responseText, 'booking_failed');
                $conversation->incrementMessageCount();
                $conversation->update(['last_bot_response_at' => now()]);
                $conversation->refresh();

                return [
                    'conversation_id' => $conversation->id,
                    'response_text' => $responseText,
                    'action' => 'booking_failed',
                    'requires_human' => true,
                    'booking_created' => false,
                    'meta' => [
                        'intent' => ChatbotConversation::INTENT_BOOKING,
                        'confidence' => 0.0,
                        'state' => $conversation->state,
                    ],
                ];
            }
        }

        // 3. Build context for AI
        $context = $this->buildContext($conversation, $salon);

        // 4. Analyze message with AI (OUTSIDE transaction - can take 1-3s)
        $analysis = $this->openAI->analyzeMessage($messageText, $context);

        // 5. Check if human takeover is needed
        if ($this->shouldRequireHuman($analysis, $conversation, $messageText)) {
            $conversation->update(['requires_human' => true]);
            Log::info('Human takeover triggered', [
                'conversation_id' => $conversation->id,
                'reason' => 'low_confidence_or_explicit_request',
            ]);
            return [
                'conversation_id' => $conversation->id,
                'response_text' => 'Hvala na poruci! Nas tim ce vam se javiti uskoro.',
                'requires_human' => true,
                'action' => 'human_takeover',
                'meta' => [
                    'intent' => $analysis['intent'],
                    'confidence' => $analysis['confidence'],
                    'state' => $conversation->state,
                ],
            ];
        }

        // 6. Persist the inbound AI analysis and refresh context before choosing the next step.
        DB::transaction(function () use ($conversation, $inboundMessage, $analysis) {
            $inboundMessage->update([
                'ai_processed' => true,
                'ai_intent' => $analysis['intent'],
                'ai_entities' => $analysis['entities'],
                'ai_confidence' => $analysis['confidence'],
                'ai_processing_time_ms' => $analysis['processing_time_ms'],
            ]);

            $this->updateConversationFromAnalysis($conversation, $analysis);
        });

        $conversation->refresh();
        $context = $this->buildContext($conversation, $salon);

        // 7. Determine action using the updated conversation state/context.
        $action = $this->determineAction($conversation, $analysis);
        $actionData = $this->getActionData($conversation, $action, $salon);

        // 8. Generate response with AI (OUTSIDE transaction - can take 1-3s)
        $responseText = $this->openAI->generateResponse($context, $action, $actionData);

        // 9. Store outbound message and metrics.
        DB::transaction(function () use ($conversation, $responseText, $action) {
            // Store outbound message
            $this->storeOutboundMessage($conversation, $responseText, $action);

            // Update conversation metrics
            $conversation->incrementMessageCount();
            $conversation->update(['last_bot_response_at' => now()]);
        });

        // Refresh conversation to get updated state
        $conversation->refresh();
        return [
            'conversation_id' => $conversation->id,
            'response_text' => $responseText,
            'action' => $action,
            'requires_human' => $conversation->requires_human,
            'booking_created' => false,
            'meta' => [
                'intent' => $analysis['intent'],
                'confidence' => $analysis['confidence'],
                'state' => $conversation->state,
            ],
        ];
    }

    private function getOrCreateConversation(Salon $salon, string $threadId, string $senderPsid, array $payload): ChatbotConversation
    {
        $integrationId = isset($payload['social_integration_id']) ? (int) $payload['social_integration_id'] : null;

        if ($integrationId) {
            $integration = $salon->socialIntegrations()
                ->active()
                ->where('id', $integrationId)
                ->first();
        } else {
            $integration = $salon->socialIntegrations()->active()->first();
        }

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

        $services = $salon->services()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get(['id', 'name', 'price', 'discount_price', 'duration', 'category'])
            ->map(fn($service) => [
                'id' => $service->id,
                'name' => $service->name,
                'price' => $service->discount_price ?? $service->price,
                'duration' => $service->duration,
                'category' => $service->category,
            ])
            ->values()
            ->toArray();

        $staff = $salon->staff()
            ->whereRaw('is_active = true')
            ->orderBy('display_order')
            ->orderBy('name')
            ->with('services:id,name')
            ->get(['id', 'salon_id', 'name', 'role'])
            ->map(fn($staffMember) => [
                'id' => $staffMember->id,
                'name' => $staffMember->name,
                'role' => $staffMember->role,
                'services' => $staffMember->services->pluck('name')->values()->toArray(),
            ])
            ->values()
            ->toArray();

        return [
            'salon' => [
                'name' => $salon->name,
                'address' => $salon->address,
                'phone' => $salon->phone,
                'city' => $salon->city,
            ],
            'services' => $services,
            'staff' => $staff,
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

        $entities = $this->sanitizeEntities($analysis['entities'] ?? []);

        // Update context with extracted entities
        if (!empty($entities)) {
            $conversation->updateContext($entities);
            $conversation->refresh();
        }

        // State transitions based on intent and current state
        $newState = $this->determineNewState($conversation, $analysis);
        if ($newState !== $conversation->state) {
            $updates['state'] = $newState;
        }

        // Flag for human if confidence is low
        if ($analysis['confidence'] < config('chatbot.conversation.low_confidence_threshold', 0.5)) {
            $updates['requires_human'] = true;
        }

        $conversation->update($updates);
    }

    private function sanitizeEntities(array $entities): array
    {
        return collect($entities)
            ->filter(fn($value) => $value !== null && $value !== '')
            ->only([
                'service',
                'date',
                'time',
                'staff',
                'client_name',
                'client_phone',
                'client_email',
            ])
            ->toArray();
    }

    private function determineNewState(ChatbotConversation $conversation, array $analysis): string
    {
        $intent = $analysis['intent'];
        $currentState = $conversation->state;

        if (in_array($intent, ['pricing', 'hours', 'location'], true)) {
            return ChatbotConversation::STATE_GREETING;
        }

        if (!in_array($intent, ['booking', 'general'], true)) {
            return $currentState === ChatbotConversation::STATE_NEW
                ? ChatbotConversation::STATE_GREETING
                : $currentState;
        }

        if (!$conversation->getContextValue('service')) {
            return ChatbotConversation::STATE_COLLECTING_SERVICE;
        }

        if (!$conversation->getContextValue('date') || !$conversation->getContextValue('time')) {
            return ChatbotConversation::STATE_COLLECTING_DATETIME;
        }

        if (!$conversation->getContextValue('client_name') || !$conversation->getContextValue('client_phone')) {
            return ChatbotConversation::STATE_COLLECTING_CONTACT;
        }

        return ChatbotConversation::STATE_CONFIRMING;
    }

    private function determineAction(ChatbotConversation $conversation, array $analysis): string
    {
        $intent = $analysis['intent'];

        if ($intent === 'pricing') {
            return 'provide_pricing';
        }

        if ($intent === 'hours') {
            return 'provide_hours';
        }

        if ($intent === 'location') {
            return 'provide_location';
        }

        if (!$conversation->getContextValue('service')) {
            return 'ask_service';
        }

        if (!$conversation->getContextValue('date')) {
            return 'ask_date';
        }

        if (!$conversation->getContextValue('time')) {
            return 'ask_time';
        }

        if (!$conversation->getContextValue('client_name') || !$conversation->getContextValue('client_phone')) {
            return 'ask_contact';
        }

        if ($conversation->state === ChatbotConversation::STATE_BOOKED) {
            return 'booking_success';
        }

        if ($intent === 'booking' || $conversation->intent === ChatbotConversation::INTENT_BOOKING) {
            return 'confirm_booking';
        }

        return 'booking_scope_only';
    }

    private function getActionData(ChatbotConversation $conversation, string $action, Salon $salon): array
    {
        return match($action) {
            'ask_service' => [
                'services' => $this->getFormattedServiceSummaries($salon),
                'staff' => $this->getStaffSummaries($salon),
            ],

            'ask_date' => [
                'service' => $conversation->getContextValue('service'),
                'staff' => $conversation->getContextValue('staff'),
            ],

            'ask_time' => [
                'date' => $conversation->getContextValue('date'),
                'service' => $conversation->getContextValue('service'),
                'staff' => $conversation->getContextValue('staff'),
                'available_slots' => $this->getAvailableSlots($conversation, $salon),
            ],

            'ask_contact' => [
                'service' => $conversation->getContextValue('service'),
                'date' => $conversation->getContextValue('date'),
                'time' => $conversation->getContextValue('time'),
                'missing' => $this->getMissingContactFields($conversation),
            ],

            'provide_pricing' => [
                'pricing' => $this->getServiceSummaries($salon),
            ],

            'provide_hours' => [
                'hours' => $this->getConfiguredSalonHours($salon),
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
                'staff' => $conversation->getContextValue('staff'),
                'client_name' => $conversation->getContextValue('client_name'),
                'client_phone' => $conversation->getContextValue('client_phone'),
            ],

            default => [],
        };
    }

    private function getServiceSummaries(Salon $salon): array
    {
        return $salon->services()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get(['name', 'price', 'discount_price', 'duration'])
            ->map(fn($service) => [
                'name' => $service->name,
                'price' => $service->discount_price ?? $service->price,
                'duration' => $service->duration,
            ])
            ->values()
            ->toArray();
    }

    private function getFormattedServiceSummaries(Salon $salon): array
    {
        return collect($this->getServiceSummaries($salon))
            ->map(function (array $service) {
                $price = isset($service['price']) ? number_format((float) $service['price'], 2, '.', '') . ' KM' : 'cijena nije unesena';
                $duration = isset($service['duration']) ? (int) $service['duration'] . ' min' : 'trajanje nije uneseno';

                return "{$service['name']} ({$price}, {$duration})";
            })
            ->values()
            ->toArray();
    }

    private function getStaffSummaries(Salon $salon): array
    {
        return $salon->staff()
            ->whereRaw('is_active = true')
            ->orderBy('display_order')
            ->orderBy('name')
            ->get(['name', 'role'])
            ->map(fn($staffMember) => trim($staffMember->name . ($staffMember->role ? ' - ' . $staffMember->role : '')))
            ->values()
            ->toArray();
    }

    private function getMissingContactFields(ChatbotConversation $conversation): array
    {
        $missing = [];

        if (!$conversation->getContextValue('client_name')) {
            $missing[] = 'ime i prezime';
        }

        if (!$conversation->getContextValue('client_phone')) {
            $missing[] = 'telefon';
        }

        return $missing;
    }

    private function getAvailableSlots(ChatbotConversation $conversation, Salon $salon): array
    {
        $serviceId = $this->resolveServiceId($conversation->getContextValue('service'), $salon);
        $date = $this->normalizeDate($conversation->getContextValue('date'));

        if (!$serviceId || !$date) {
            return [];
        }

        try {
            $staffId = $this->resolveStaffId($conversation->getContextValue('staff'), $salon);
            if ($staffId) {
                $staff = Staff::where('id', $staffId)
                    ->where('salon_id', $salon->id)
                    ->whereRaw('is_active = true')
                    ->whereHas('services', fn($query) => $query->where('services.id', $serviceId))
                    ->with(['breaks', 'vacations', 'salon.salonBreaks', 'salon.salonVacations'])
                    ->first();

                $service = $salon->services()->where('is_active', true)->find($serviceId);

                if (!$staff || !$service) {
                    return [];
                }

                return $this->appointmentService->getAvailableSlots($staff, $date, (int) $service->duration);
            }

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

    private function getConfiguredSalonHours(Salon $salon): array
    {
        $hours = [];

        $daysMap = [
            'monday' => 'Ponedjeljak',
            'tuesday' => 'Utorak',
            'wednesday' => 'Srijeda',
            'thursday' => 'Cetvrtak',
            'friday' => 'Petak',
            'saturday' => 'Subota',
            'sunday' => 'Nedjelja',
        ];

        foreach ($daysMap as $dayKey => $dayName) {
            $dayHours = is_array($salon->working_hours) ? ($salon->working_hours[$dayKey] ?? null) : null;
            $isOpen = is_array($dayHours) && (bool) ($dayHours['is_open'] ?? $dayHours['is_working'] ?? false);

            $hours[] = [
                'day' => $dayName,
                'open' => $isOpen ? ($dayHours['open'] ?? $dayHours['start'] ?? null) : null,
                'close' => $isOpen ? ($dayHours['close'] ?? $dayHours['end'] ?? null) : null,
                'is_open' => $isOpen,
            ];
        }

        return $hours;
    }

    private function resolveStaffId(?string $staffName, Salon $salon): ?int
    {
        if (!$staffName) {
            return null;
        }

        $normalizedStaffName = mb_strtolower($staffName);

        $staff = $salon->staff()
            ->whereRaw('is_active = true')
            ->where(function ($query) use ($normalizedStaffName) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%' . $normalizedStaffName . '%'])
                    ->orWhereRaw('LOWER(role) LIKE ?', ['%' . $normalizedStaffName . '%']);
            })
            ->first();

        return $staff?->id;
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
        $humanKeywords = ['covjek', 'čovjek', 'osoba', 'zaposleni', 'agent', 'pomoc', 'pomoć', 'razgovarati', 'kontakt'];
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

        $confirmations = ['da', 'yes', 'potvrdjujem', 'potvrdujem', 'potvrđujem', 'potvrdi', 'ok', 'u redu', 'vazi', 'važi', 'ajde'];

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
        $staffId = $this->resolveStaffId($conversation->getContextValue('staff'), $salon);
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
            'idempotency_key' => 'chatbot:conversation:'.$conversation->id,
            'notes' => 'Rezervacija preko Instagram/Facebook chatbota',
        ];

        if ($staffId) {
            $bookingData['staff_id'] = $staffId;
        }

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
