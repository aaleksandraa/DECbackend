<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

/**
 * Chatbot Analytics Model
 *
 * Stores daily aggregated metrics for chatbot performance.
 * Used for reporting and monitoring.
 *
 * @property int $id
 * @property int $salon_id
 * @property \Carbon\Carbon $date
 * @property int $total_conversations
 * @property int $new_conversations
 * @property int $completed_conversations
 * @property int $abandoned_conversations
 * @property int $total_messages_received
 * @property int $total_messages_sent
 * @property float|null $avg_messages_per_conversation
 * @property float|null $avg_response_time_seconds
 * @property float|null $avg_conversation_duration_minutes
 * @property int $booking_attempts
 * @property int $successful_bookings
 * @property float|null $booking_conversion_rate
 * @property float|null $avg_ai_confidence
 * @property int $low_confidence_count
 * @property int $human_takeover_count
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ChatbotAnalytics extends Model
{
    protected $fillable = [
        'salon_id',
        'date',
        'total_conversations',
        'new_conversations',
        'completed_conversations',
        'abandoned_conversations',
        'total_messages_received',
        'total_messages_sent',
        'avg_messages_per_conversation',
        'avg_response_time_seconds',
        'avg_conversation_duration_minutes',
        'booking_attempts',
        'successful_bookings',
        'booking_conversion_rate',
        'avg_ai_confidence',
        'low_confidence_count',
        'human_takeover_count',
    ];

    protected $casts = [
        'date' => 'date',
        'avg_messages_per_conversation' => 'decimal:2',
        'avg_response_time_seconds' => 'decimal:2',
        'avg_conversation_duration_minutes' => 'decimal:2',
        'booking_conversion_rate' => 'decimal:2',
        'avg_ai_confidence' => 'decimal:2',
    ];

    // ==========================================
    // Relationships
    // ==========================================

    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    // ==========================================
    // Scopes
    // ==========================================

    /**
     * Scope to filter by date range
     */
    public function scopeForDateRange(Builder $query, $startDate, $endDate): Builder
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Scope to filter by salon
     */
    public function scopeForSalon(Builder $query, int $salonId): Builder
    {
        return $query->where('salon_id', $salonId);
    }

    /**
     * Scope to recent analytics
     */
    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('date', '>=', now()->subDays($days));
    }

    // ==========================================
    // Static Aggregation Methods
    // ==========================================

    /**
     * Aggregate analytics for a specific date
     */
    public static function aggregateForDate(int $salonId, Carbon $date): self
    {
        $analytics = self::firstOrNew([
            'salon_id' => $salonId,
            'date' => $date->toDateString(),
        ]);

        // Get conversations for this date
        $conversations = ChatbotConversation::where('salon_id', $salonId)
            ->whereDate('started_at', $date)
            ->get();

        // Volume metrics
        $analytics->total_conversations = $conversations->count();
        $analytics->new_conversations = $conversations->where('state', ChatbotConversation::STATE_NEW)->count();
        $analytics->completed_conversations = $conversations->whereNotNull('completed_at')->count();
        $analytics->abandoned_conversations = $conversations->whereNotNull('abandoned_at')->count();

        // Message metrics
        $conversationIds = $conversations->pluck('id');
        $messages = ChatbotMessage::whereIn('conversation_id', $conversationIds)->get();

        $analytics->total_messages_received = $messages->where('direction', ChatbotMessage::DIRECTION_INBOUND)->count();
        $analytics->total_messages_sent = $messages->where('direction', ChatbotMessage::DIRECTION_OUTBOUND)->count();

        if ($analytics->total_conversations > 0) {
            $analytics->avg_messages_per_conversation = round(
                $messages->count() / $analytics->total_conversations,
                2
            );
        }

        // Performance metrics
        $completedConversations = $conversations->filter(fn($c) => $c->completed_at || $c->abandoned_at);
        if ($completedConversations->count() > 0) {
            $analytics->avg_conversation_duration_minutes = round(
                $completedConversations->avg(fn($c) => $c->getDurationMinutes()),
                2
            );
        }

        // Booking metrics
        $analytics->successful_bookings = $conversations->whereNotNull('appointment_id')->count();
        $analytics->booking_attempts = $conversations->where('intent', ChatbotConversation::INTENT_BOOKING)->count();

        if ($analytics->booking_attempts > 0) {
            $analytics->booking_conversion_rate = round(
                ($analytics->successful_bookings / $analytics->booking_attempts) * 100,
                2
            );
        }

        // AI metrics
        $aiMessages = $messages->where('ai_processed', true);
        if ($aiMessages->count() > 0) {
            $analytics->avg_ai_confidence = round($aiMessages->avg('ai_confidence'), 2);
            $analytics->low_confidence_count = $aiMessages->where('ai_confidence', '<', 0.7)->count();
        }

        // Human intervention
        $analytics->human_takeover_count = $conversations->whereNotNull('human_takeover_at')->count();

        $analytics->save();

        return $analytics;
    }

    /**
     * Aggregate analytics for a date range
     */
    public static function aggregateForDateRange(int $salonId, Carbon $startDate, Carbon $endDate): void
    {
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            self::aggregateForDate($salonId, $currentDate);
            $currentDate->addDay();
        }
    }

    /**
     * Get summary for date range
     */
    public static function getSummary(int $salonId, Carbon $startDate, Carbon $endDate): array
    {
        $analytics = self::forSalon($salonId)
            ->forDateRange($startDate, $endDate)
            ->get();

        if ($analytics->isEmpty()) {
            return [
                'total_conversations' => 0,
                'successful_bookings' => 0,
                'booking_conversion_rate' => 0,
                'avg_messages_per_conversation' => 0,
                'human_takeover_rate' => 0,
            ];
        }

        $totalConversations = $analytics->sum('total_conversations');
        $successfulBookings = $analytics->sum('successful_bookings');
        $bookingAttempts = $analytics->sum('booking_attempts');
        $humanTakeovers = $analytics->sum('human_takeover_count');

        return [
            'total_conversations' => $totalConversations,
            'new_conversations' => $analytics->sum('new_conversations'),
            'completed_conversations' => $analytics->sum('completed_conversations'),
            'abandoned_conversations' => $analytics->sum('abandoned_conversations'),
            'total_messages_received' => $analytics->sum('total_messages_received'),
            'total_messages_sent' => $analytics->sum('total_messages_sent'),
            'avg_messages_per_conversation' => $totalConversations > 0
                ? round($analytics->avg('avg_messages_per_conversation'), 2)
                : 0,
            'booking_attempts' => $bookingAttempts,
            'successful_bookings' => $successfulBookings,
            'booking_conversion_rate' => $bookingAttempts > 0
                ? round(($successfulBookings / $bookingAttempts) * 100, 2)
                : 0,
            'avg_ai_confidence' => round($analytics->avg('avg_ai_confidence'), 2),
            'low_confidence_count' => $analytics->sum('low_confidence_count'),
            'human_takeover_count' => $humanTakeovers,
            'human_takeover_rate' => $totalConversations > 0
                ? round(($humanTakeovers / $totalConversations) * 100, 2)
                : 0,
        ];
    }

    /**
     * Get daily trend data
     */
    public static function getDailyTrend(int $salonId, int $days = 30): array
    {
        $analytics = self::forSalon($salonId)
            ->recent($days)
            ->orderBy('date')
            ->get();

        return $analytics->map(fn($a) => [
            'date' => $a->date->format('Y-m-d'),
            'conversations' => $a->total_conversations,
            'bookings' => $a->successful_bookings,
            'conversion_rate' => $a->booking_conversion_rate,
        ])->toArray();
    }
}
