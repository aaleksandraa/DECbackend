<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class SalonSettings extends Model
{
    use HasFactory;

    protected $fillable = [
        'salon_id',
        'daily_report_enabled',
        'daily_report_time',
        'daily_report_email',
        'daily_report_include_staff',
        'daily_report_include_services',
        'daily_report_include_capacity',
        'daily_report_include_cancellations',
        'notification_preferences',
        'business_hours_override',
    ];

    protected $casts = [
        'daily_report_enabled' => 'boolean', // Laravel auto-converts SMALLINT 0/1 to boolean
        'daily_report_include_staff' => 'boolean',
        'daily_report_include_services' => 'boolean',
        'daily_report_include_capacity' => 'boolean',
        'daily_report_include_cancellations' => 'boolean',
        'notification_preferences' => 'array',
        'business_hours_override' => 'array',
    ];

    /**
     * Override the create method to handle PostgreSQL boolean casting.
     */
    public static function createForPostgres(array $attributes): self
    {
        $booleanFields = [
            'daily_report_enabled',
            'daily_report_include_staff',
            'daily_report_include_services',
            'daily_report_include_capacity',
            'daily_report_include_cancellations',
        ];

        foreach ($booleanFields as $field) {
            if (isset($attributes[$field])) {
                $attributes[$field] = DB::raw($attributes[$field] ? 'true' : 'false');
            }
        }

        return static::create($attributes);
    }

    /**
     * Get the salon that owns the settings.
     */
    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    /**
     * Get the email address for daily reports.
     * Falls back to salon owner email if not set.
     */
    public function getReportEmailAttribute(): string
    {
        return $this->daily_report_email ?? $this->salon->owner->email;
    }
}
