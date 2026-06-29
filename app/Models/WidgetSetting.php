<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WidgetSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'salon_id',
        'api_key',
        'is_active',
        'allowed_domains',
        'theme',
        'settings',
        'last_used_at',
        'total_bookings',
    ];

    protected $casts = [
        'is_active' => 'boolean', // Laravel auto-converts SMALLINT 0/1 to boolean
        'allowed_domains' => 'array',
        'theme' => 'array',
        'settings' => 'array',
        'last_used_at' => 'datetime',
        'total_bookings' => 'integer',
    ];

    protected $hidden = [
        'api_key', // Hide API key from general queries
    ];

    /**
     * Get the salon that owns the widget
     */
    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    /**
     * Check if domain is allowed.
     *
     * Backward compatible: when no whitelist is configured, all domains are
     * allowed (so existing unconfigured widgets keep working). When a whitelist
     * IS configured, a missing/empty origin is denied - otherwise the whitelist
     * could be bypassed simply by omitting the Referer header.
     */
    public function isDomainAllowed(?string $domain): bool
    {
        // No whitelist configured -> allow all (backward compatible).
        if (empty($this->allowed_domains)) {
            return true;
        }

        // A whitelist exists: an unknown/missing origin must not bypass it.
        if (empty($domain)) {
            return false;
        }

        return in_array($domain, $this->allowed_domains, true);
    }

    /**
     * Get default theme
     */
    public function getDefaultTheme(): array
    {
        return [
            'primaryColor' => '#FF6B35',
            'secondaryColor' => '#F7931E',
            'fontFamily' => 'Inter, sans-serif',
            'borderRadius' => '12px',
        ];
    }

    /**
     * Get merged theme (default + custom)
     */
    public function getMergedTheme(): array
    {
        return array_merge($this->getDefaultTheme(), $this->theme ?? []);
    }
}
