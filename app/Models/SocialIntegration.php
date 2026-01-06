<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

/**
 * Social Integration Model
 *
 * Manages Meta (Facebook/Instagram) integrations for salons.
 * Stores OAuth tokens, configuration, and integration status.
 *
 * @property int $id
 * @property int $salon_id
 * @property string $provider
 * @property string $platform
 * @property string|null $fb_page_id
 * @property string|null $fb_page_name
 * @property string|null $ig_business_account_id
 * @property string|null $ig_username
 * @property string $access_token (encrypted)
 * @property string $token_type
 * @property \Carbon\Carbon|null $token_expires_at
 * @property string|null $refresh_token (encrypted)
 * @property array|null $granted_scopes
 * @property string $status
 * @property \Carbon\Carbon|null $last_verified_at
 * @property \Carbon\Carbon|null $last_message_at
 * @property int|null $connected_by_user_id
 * @property string|null $meta_user_id
 * @property bool $webhook_verified
 * @property bool $auto_reply_enabled
 * @property bool $business_hours_only
 * @property int $max_response_time_seconds
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class SocialIntegration extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'salon_id',
        'provider',
        'platform',
        'fb_page_id',
        'fb_page_name',
        'ig_business_account_id',
        'ig_username',
        'access_token',
        'token_type',
        'token_expires_at',
        'refresh_token',
        'granted_scopes',
        'status',
        'last_verified_at',
        'last_message_at',
        'connected_by_user_id',
        'meta_user_id',
        'webhook_verified',
        'auto_reply_enabled',
        'business_hours_only',
        'max_response_time_seconds',
    ];

    protected $casts = [
        'granted_scopes' => 'array',
        'token_expires_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'last_message_at' => 'datetime',
        'auto_reply_enabled' => 'boolean',
        'business_hours_only' => 'boolean',
        'webhook_verified' => 'boolean',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    // ==========================================
    // Relationships
    // ==========================================

    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by_user_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(ChatbotConversation::class);
    }

    // ==========================================
    // Accessors & Mutators
    // ==========================================

    /**
     * Encrypt access token before saving
     */
    public function setAccessTokenAttribute($value): void
    {
        if ($value) {
            $this->attributes['access_token'] = encrypt($value);
        }
    }

    /**
     * Decrypt access token when retrieving
     */
    public function getAccessTokenAttribute($value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return decrypt($value);
        } catch (\Exception $e) {
            \Log::error('Failed to decrypt access token', [
                'integration_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Encrypt refresh token before saving
     */
    public function setRefreshTokenAttribute($value): void
    {
        if ($value) {
            $this->attributes['refresh_token'] = encrypt($value);
        }
    }

    /**
     * Decrypt refresh token when retrieving
     */
    public function getRefreshTokenAttribute($value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return decrypt($value);
        } catch (\Exception $e) {
            \Log::error('Failed to decrypt refresh token', [
                'integration_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    // ==========================================
    // Scopes
    // ==========================================

    /**
     * Scope to only active integrations (status = active)
     *
     * ✅ FIXED: Separated from auto_reply_enabled
     * This allows admins to see integrations even when auto-reply is disabled
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to only integrations with auto-reply enabled
     *
     * Use this when you need to filter for chatbot functionality
     */
    public function scopeAutoReplyEnabled(Builder $query): Builder
    {
        return $query->where('auto_reply_enabled', true);
    }

    /**
     * Scope to filter by platform
     */
    public function scopeForPlatform(Builder $query, string $platform): Builder
    {
        return $query->where(function($q) use ($platform) {
            $q->where('platform', $platform)
              ->orWhere('platform', 'both');
        });
    }

    /**
     * Scope to find integrations with expiring tokens
     */
    public function scopeExpiringTokens(Builder $query, int $daysThreshold = 7): Builder
    {
        return $query->where('status', 'active')
                    ->whereNotNull('token_expires_at')
                    ->where('token_expires_at', '<=', now()->addDays($daysThreshold));
    }

    /**
     * Scope to find integrations needing verification
     */
    public function scopeNeedsVerification(Builder $query): Builder
    {
        return $query->where('status', 'active')
                    ->where(function($q) {
                        $q->whereNull('last_verified_at')
                          ->orWhere('last_verified_at', '<', now()->subDays(1));
                    });
    }

    // ==========================================
    // Methods
    // ==========================================

    /**
     * Check if access token is expired
     */
    public function isTokenExpired(): bool
    {
        return $this->token_expires_at && $this->token_expires_at->isPast();
    }

    /**
     * Check if integration should auto-reply to messages
     */
    public function shouldAutoReply(): bool
    {
        // Must be enabled
        if (!$this->auto_reply_enabled) {
            return false;
        }

        // Must be active
        if ($this->status !== 'active') {
            return false;
        }

        // If business hours only, check salon hours
        if ($this->business_hours_only) {
            return $this->salon->isWithinBusinessHours();
        }

        return true;
    }

    /**
     * Mark that a message was received
     */
    public function markMessageReceived(): void
    {
        $this->update(['last_message_at' => now()]);
    }

    /**
     * Mark integration as verified
     */
    public function markAsVerified(): void
    {
        $this->update([
            'last_verified_at' => now(),
            'status' => 'active',
        ]);
    }

    /**
     * Mark integration as expired
     */
    public function markAsExpired(): void
    {
        $this->update(['status' => 'expired']);
    }

    /**
     * Mark integration as revoked
     */
    public function markAsRevoked(): void
    {
        $this->update(['status' => 'revoked']);
    }

    /**
     * Get display name for the integration
     */
    public function getDisplayName(): string
    {
        if ($this->platform === 'both') {
            return "{$this->fb_page_name} + @{$this->ig_username}";
        }

        if ($this->platform === 'instagram') {
            return "@{$this->ig_username}";
        }

        return $this->fb_page_name ?? 'Facebook Page';
    }

    /**
     * Check if integration supports Instagram
     */
    public function supportsInstagram(): bool
    {
        return in_array($this->platform, ['instagram', 'both']);
    }

    /**
     * Check if integration supports Facebook
     */
    public function supportsFacebook(): bool
    {
        return in_array($this->platform, ['facebook', 'both']);
    }

    /**
     * Get statistics for this integration
     */
    public function getStats(int $days = 30): array
    {
        $startDate = now()->subDays($days);

        $conversations = $this->conversations()
            ->where('started_at', '>=', $startDate)
            ->get();

        $successfulBookings = $conversations->whereNotNull('appointment_id')->count();
        $bookingAttempts = $conversations->where('intent', 'booking')->count();

        return [
            'total_conversations' => $conversations->count(),
            'successful_bookings' => $successfulBookings,
            'booking_attempts' => $bookingAttempts,
            'booking_conversion_rate' => $bookingAttempts > 0
                ? round(($successfulBookings / $bookingAttempts) * 100, 2)
                : 0,
            'avg_messages_per_conversation' => $conversations->avg('message_count') ?? 0,
            'human_takeover_count' => $conversations->whereNotNull('human_takeover_at')->count(),
        ];
    }
}
