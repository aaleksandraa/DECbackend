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
     * Check if domain is allowed
     * FIXED: Allow null domain (no referer header)
     */
    public function isDomainAllowed(?string $domain): bool
    {
        // If no domain provided (no referer), allow it
        if (empty($domain)) {
            return true;
        }

        // If no whitelist, allow all
        if (empty($this->allowed_domains)) {
            return true;
        }

        // Check if domain is in whitelist
        return in_array($domain, $this->allowed_domains);
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
