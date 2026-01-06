<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WidgetAnalytics extends Model
{
    use HasFactory;

    const UPDATED_AT = null; // No updated_at column

    protected $fillable = [
        'salon_id',
        'widget_setting_id',
        'event_type',
        'referrer_domain',
        'referrer_url',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get the salon that owns the analytics
     */
    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    /**
     * Get the widget setting
     */
    public function widgetSetting(): BelongsTo
    {
        return $this->belongsTo(WidgetSetting::class);
    }

    /**
     * Event types
     */
    const EVENT_VIEW = 'view';
    const EVENT_BOOKING = 'booking';
    const EVENT_ERROR = 'error';
    const EVENT_INTERACTION = 'interaction';

    /**
     * Scope for specific event type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('event_type', $type);
    }

    /**
     * Scope for date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
}
