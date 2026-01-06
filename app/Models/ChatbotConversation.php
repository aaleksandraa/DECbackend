<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

/**
 * Chatbot Conversation Model
 *
 * Represents a conversation between a user and the chatbot.
 * Manages conversation state, context, and lifecycle.
 *
 * @property int $id
 * @property int $salon_id
 * @property int $social_integration_id
 * @property int|null $appointment_id
 * @property string $platform
 * @property string $thread_id
 * @property string $sender_psid
 * @property string|null $sender_name
 * @property string|null $sender_profile_pic
 * @property string $state
 * @property string|null $intent
 * @property float|null $confidence
 * @property array $context
 * @property int $message_count
 * @property \Carbon\Carbon|null $last_message_at
 * @property \Carbon\Carbon|null $last_bot_response_at
 * @property bool $requires_human
 * @property \Carbon\Carbon|null $human_takeover_at
 * @property int|null $human_takeover_by_user_id
 * @property \Carbon\Carbon $started_at
 * @property \Carbon\Carbon|null $completed_at
 * @property \Carbon\Carbon|null $abandoned_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ChatbotConversation extends Model
{
    /**
     * Conversation states
     */
    const STATE_NEW = 'new';
    const STATE_GREETING = 'greeting';
    const STATE_COLLECTING_SERVICE = 'collecting_service';
    const STATE_COLLECTING_DATETIME = 'collecting_datetime';
    const STATE_COLLECTING_CONTACT = 'collecting_contact';
    const STATE_CONFIRMING = 'confirming';
    const STATE_BOOKED = 'booked';
    const STATE_COMPLETED = 'completed';
    const STATE_ABANDONED = 'abandoned';
    const STATE_ERROR = 'error';

    /**
     * Conversation intents
     */
    const INTENT_BOOKING = 'booking';
    const INTENT_PRICING = 'pricing';
    const INTENT_HOURS = 'hours';
    const INTENT_LOCATION = 'location';
    const INTENT_CANCELLATION = 'cancellation';
    const INTENT_GENERAL = 'general';

    protected $fillable = [
        'salon_id',
        'social_integration_id',
        'appointment_id',
        'platform',
        'thread_id',
        'sender_psid',
        'sender_name',
        'sender_profile_pic',
        'state',
        'intent',
        'confidence',
        'context',
        'message_count',
        'last_message_at',
        'last_bot_response_at',
        'requires_human',
        'human_takeover_at',
        'human_takeover_by_user_id',
        'started_at',
        'completed_at',
        'abandoned_at',
    ];

    protected $casts = [
        'context' => 'array',
        'confidence' => 'decimal:2',
        'requires_human' => 'boolean',
        'last_message_at' => 'datetime',
        'last_bot_response_at' => 'datetime',
        'human_takeover_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'abandoned_at' => 'datetime',
    ];

    // ==========================================
    // Relationships
    // ==========================================

    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(SocialIntegration::class, 'social_integration_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatbotMessage::class, 'conversation_id');
    }

    public function humanTakeoverBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'human_takeover_by_user_id');
    }

    // ==========================================
    // Scopes
    // ==========================================

    /**
     * Scope to only active conversations
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('state', [
            self::STATE_COMPLETED,
            self::STATE_ABANDONED,
        ]);
    }

    /**
     * Scope to conversations requiring human attention
     */
    public function scopeRequiringAttention(Builder $query): Builder
    {
        return $query->where('requires_human', true)
                    ->whereNull('human_takeover_at');
    }

    /**
     * Scope to stale conversations (no activity for X hours)
     */
    public function scopeStale(Builder $query, int $hours = 24): Builder
    {
        return $query->active()
                    ->where('last_message_at', '<', now()->subHours($hours));
    }

    /**
     * Scope to conversations by intent
     */
    public function scopeByIntent(Builder $query, string $intent): Builder
    {
        return $query->where('intent', $intent);
    }

    /**
     * Scope to conversations with low confidence
     */
    public function scopeLowConfidence(Builder $query, float $threshold = 0.7): Builder
    {
        return $query->where('confidence', '<', $threshold)
                    ->whereNotNull('confidence');
    }

    // ==========================================
    // State Machine Methods
    // ==========================================

    /**
     * Transition to a new state
     */
    public function transitionTo(string $newState): void
    {
        $this->update(['state' => $newState]);

        // Set completion/abandonment timestamp
        if ($newState === self::STATE_COMPLETED) {
            $this->update(['completed_at' => now()]);
        } elseif ($newState === self::STATE_ABANDONED) {
            $this->update(['abandoned_at' => now()]);
        }
    }

    /**
     * Check if conversation is in a specific state
     */
    public function isInState(string $state): bool
    {
        return $this->state === $state;
    }

    /**
     * Check if conversation is active
     */
    public function isActive(): bool
    {
        return !in_array($this->state, [
            self::STATE_COMPLETED,
            self::STATE_ABANDONED,
        ]);
    }

    /**
     * Check if conversation is completed
     */
    public function isCompleted(): bool
    {
        return $this->state === self::STATE_COMPLETED;
    }

    /**
     * Check if conversation resulted in a booking
     */
    public function hasBooking(): bool
    {
        return $this->appointment_id !== null;
    }

    // ==========================================
    // Context Management Methods
    // ==========================================

    /**
     * Update conversation context (merge with existing)
     */
    public function updateContext(array $data): void
    {
        $currentContext = $this->context ?? [];
        $this->update([
            'context' => array_merge($currentContext, $data)
        ]);
    }

    /**
     * Get a value from context
     */
    public function getContextValue(string $key, $default = null)
    {
        return data_get($this->context, $key, $default);
    }

    /**
     * Set a single context value
     */
    public function setContextValue(string $key, $value): void
    {
        $context = $this->context ?? [];
        data_set($context, $key, $value);
        $this->update(['context' => $context]);
    }

    /**
     * Clear all context
     */
    public function clearContext(): void
    {
        $this->update(['context' => []]);
    }

    /**
     * Check if context has a specific key
     */
    public function hasContextKey(string $key): bool
    {
        return data_get($this->context, $key) !== null;
    }

    // ==========================================
    // Message Management Methods
    // ==========================================

    /**
     * Increment message count and update last message timestamp
     */
    public function incrementMessageCount(int $count = 1): void
    {
        $this->increment('message_count', $count);
        $this->update(['last_message_at' => now()]);
    }

    /**
     * Get recent messages
     */
    public function getRecentMessages(int $limit = 10)
    {
        return $this->messages()
                    ->orderBy('created_at', 'desc')
                    ->limit($limit)
                    ->get()
                    ->reverse();
    }

    /**
     * Get conversation history formatted for AI
     */
    public function getConversationHistory(int $limit = 10): array
    {
        return $this->getRecentMessages($limit)
                    ->map(fn($msg) => [
                        'role' => $msg->direction === 'inbound' ? 'user' : 'assistant',
                        'content' => $msg->message_text,
                        'timestamp' => $msg->created_at->toIso8601String(),
                    ])
                    ->toArray();
    }

    // ==========================================
    // Human Intervention Methods
    // ==========================================

    /**
     * Flag conversation for human attention
     */
    public function flagForHuman(string $reason = null): void
    {
        $this->update([
            'requires_human' => true,
            'context' => array_merge($this->context ?? [], [
                'human_flag_reason' => $reason,
                'human_flag_at' => now()->toIso8601String(),
            ])
        ]);
    }

    /**
     * Assign conversation to a human agent
     */
    public function assignToHuman(User $user): void
    {
        $this->update([
            'human_takeover_at' => now(),
            'human_takeover_by_user_id' => $user->id,
        ]);
    }

    /**
     * Check if conversation has been taken over by human
     */
    public function hasTakenOverByHuman(): bool
    {
        return $this->human_takeover_at !== null;
    }

    // ==========================================
    // Utility Methods
    // ==========================================

    /**
     * Get conversation duration in minutes
     */
    public function getDurationMinutes(): ?float
    {
        if (!$this->completed_at && !$this->abandoned_at) {
            return null;
        }

        $endTime = $this->completed_at ?? $this->abandoned_at;
        return $this->started_at->diffInMinutes($endTime);
    }

    /**
     * Mark conversation as abandoned
     */
    public function markAsAbandoned(): void
    {
        $this->transitionTo(self::STATE_ABANDONED);
    }

    /**
     * Mark conversation as completed
     */
    public function markAsCompleted(): void
    {
        $this->transitionTo(self::STATE_COMPLETED);
    }

    /**
     * Get conversation summary
     */
    public function getSummary(): array
    {
        return [
            'id' => $this->id,
            'salon' => $this->salon->name,
            'platform' => $this->platform,
            'sender' => $this->sender_name ?? 'Unknown',
            'state' => $this->state,
            'intent' => $this->intent,
            'message_count' => $this->message_count,
            'has_booking' => $this->hasBooking(),
            'requires_human' => $this->requires_human,
            'duration_minutes' => $this->getDurationMinutes(),
            'started_at' => $this->started_at->toIso8601String(),
        ];
    }
}
