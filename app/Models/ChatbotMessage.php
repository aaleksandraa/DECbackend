<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * Chatbot Message Model
 *
 * Represents a single message in a chatbot conversation.
 * Tracks message content, AI processing, and delivery status.
 *
 * @property int $id
 * @property int $conversation_id
 * @property string $direction
 * @property string $message_type
 * @property string|null $message_text
 * @property array|null $message_payload
 * @property string|null $meta_message_id
 * @property bool $ai_processed
 * @property string|null $ai_intent
 * @property array|null $ai_entities
 * @property float|null $ai_confidence
 * @property int|null $ai_processing_time_ms
 * @property string|null $template_used
 * @property bool $ai_generated
 * @property \Carbon\Carbon|null $sent_at
 * @property \Carbon\Carbon|null $delivered_at
 * @property \Carbon\Carbon|null $read_at
 * @property \Carbon\Carbon|null $failed_at
 * @property string|null $error_message
 * @property \Carbon\Carbon $created_at
 */
class ChatbotMessage extends Model
{
    public $timestamps = false; // Only created_at

    /**
     * Message directions
     */
    const DIRECTION_INBOUND = 'inbound';
    const DIRECTION_OUTBOUND = 'outbound';

    /**
     * Message types
     */
    const TYPE_TEXT = 'text';
    const TYPE_IMAGE = 'image';
    const TYPE_QUICK_REPLY = 'quick_reply';
    const TYPE_BUTTON = 'button';
    const TYPE_TEMPLATE = 'template';

    protected $fillable = [
        'conversation_id',
        'direction',
        'message_type',
        'message_text',
        'message_payload',
        'meta_message_id',
        'ai_processed',
        'ai_intent',
        'ai_entities',
        'ai_confidence',
        'ai_processing_time_ms',
        'template_used',
        'ai_generated',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'error_message',
        'created_at',
    ];

    protected $casts = [
        'message_payload' => 'array',
        'ai_entities' => 'array',
        'ai_processed' => 'boolean',
        'ai_generated' => 'boolean',
        'ai_confidence' => 'decimal:2',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    // ==========================================
    // Relationships
    // ==========================================

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatbotConversation::class, 'conversation_id');
    }

    // ==========================================
    // Scopes
    // ==========================================

    /**
     * Scope to inbound messages
     */
    public function scopeInbound(Builder $query): Builder
    {
        return $query->where('direction', self::DIRECTION_INBOUND);
    }

    /**
     * Scope to outbound messages
     */
    public function scopeOutbound(Builder $query): Builder
    {
        return $query->where('direction', self::DIRECTION_OUTBOUND);
    }

    /**
     * Scope to failed messages
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->whereNotNull('failed_at');
    }

    /**
     * Scope to recent messages
     */
    public function scopeRecent(Builder $query, int $limit = 10): Builder
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    /**
     * Scope to AI processed messages
     */
    public function scopeAiProcessed(Builder $query): Builder
    {
        return $query->where('ai_processed', true);
    }

    /**
     * Scope to messages with low AI confidence
     */
    public function scopeLowConfidence(Builder $query, float $threshold = 0.7): Builder
    {
        return $query->where('ai_confidence', '<', $threshold)
                    ->whereNotNull('ai_confidence');
    }

    // ==========================================
    // Direction Check Methods
    // ==========================================

    /**
     * Check if message is inbound
     */
    public function isInbound(): bool
    {
        return $this->direction === self::DIRECTION_INBOUND;
    }

    /**
     * Check if message is outbound
     */
    public function isOutbound(): bool
    {
        return $this->direction === self::DIRECTION_OUTBOUND;
    }

    // ==========================================
    // Delivery Status Methods
    // ==========================================

    /**
     * Mark message as sent
     */
    public function markAsSent(string $metaMessageId = null): void
    {
        $updates = ['sent_at' => now()];

        if ($metaMessageId) {
            $updates['meta_message_id'] = $metaMessageId;
        }

        $this->update($updates);
    }

    /**
     * Mark message as delivered
     */
    public function markAsDelivered(): void
    {
        $this->update(['delivered_at' => now()]);
    }

    /**
     * Mark message as read
     */
    public function markAsRead(): void
    {
        $this->update(['read_at' => now()]);
    }

    /**
     * Mark message as failed
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'failed_at' => now(),
            'error_message' => $error,
        ]);
    }

    /**
     * Check if message was sent successfully
     */
    public function wasSent(): bool
    {
        return $this->sent_at !== null;
    }

    /**
     * Check if message was delivered
     */
    public function wasDelivered(): bool
    {
        return $this->delivered_at !== null;
    }

    /**
     * Check if message was read
     */
    public function wasRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Check if message failed
     */
    public function hasFailed(): bool
    {
        return $this->failed_at !== null;
    }

    // ==========================================
    // AI Processing Methods
    // ==========================================

    /**
     * Mark message as AI processed with results
     */
    public function markAsAiProcessed(array $aiResults): void
    {
        $this->update([
            'ai_processed' => true,
            'ai_intent' => $aiResults['intent'] ?? null,
            'ai_entities' => $aiResults['entities'] ?? null,
            'ai_confidence' => $aiResults['confidence'] ?? null,
            'ai_processing_time_ms' => $aiResults['processing_time_ms'] ?? null,
        ]);
    }

    /**
     * Check if message was processed by AI
     */
    public function wasAiProcessed(): bool
    {
        return $this->ai_processed === true;
    }

    /**
     * Check if AI confidence is low
     */
    public function hasLowConfidence(float $threshold = 0.7): bool
    {
        return $this->ai_confidence !== null && $this->ai_confidence < $threshold;
    }

    /**
     * Get extracted entity value
     */
    public function getEntity(string $key, $default = null)
    {
        return data_get($this->ai_entities, $key, $default);
    }

    // ==========================================
    // Utility Methods
    // ==========================================

    /**
     * Get message summary
     */
    public function getSummary(): array
    {
        return [
            'id' => $this->id,
            'direction' => $this->direction,
            'text' => $this->message_text,
            'type' => $this->message_type,
            'ai_intent' => $this->ai_intent,
            'ai_confidence' => $this->ai_confidence,
            'sent' => $this->wasSent(),
            'delivered' => $this->wasDelivered(),
            'read' => $this->wasRead(),
            'failed' => $this->hasFailed(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    /**
     * Get delivery status
     */
    public function getDeliveryStatus(): string
    {
        if ($this->hasFailed()) {
            return 'failed';
        }

        if ($this->wasRead()) {
            return 'read';
        }

        if ($this->wasDelivered()) {
            return 'delivered';
        }

        if ($this->wasSent()) {
            return 'sent';
        }

        return 'pending';
    }

    /**
     * Create inbound message
     */
    public static function createInbound(
        int $conversationId,
        string $text,
        array $payload = []
    ): self {
        return self::create([
            'conversation_id' => $conversationId,
            'direction' => self::DIRECTION_INBOUND,
            'message_type' => self::TYPE_TEXT,
            'message_text' => $text,
            'message_payload' => $payload,
            'created_at' => now(),
        ]);
    }

    /**
     * Create outbound message
     */
    public static function createOutbound(
        int $conversationId,
        string $text,
        string $templateUsed = null,
        bool $aiGenerated = false
    ): self {
        return self::create([
            'conversation_id' => $conversationId,
            'direction' => self::DIRECTION_OUTBOUND,
            'message_type' => self::TYPE_TEXT,
            'message_text' => $text,
            'template_used' => $templateUsed,
            'ai_generated' => $aiGenerated,
            'created_at' => now(),
        ]);
    }
}
