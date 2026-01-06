<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobAd extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'salon_id',
        'created_by',
        'company_name',
        'position_title',
        'description',
        'gender_requirement',
        'contact_email',
        'contact_phone',
        'city',
        'deadline',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'deadline' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Get the salon that owns the job ad.
     */
    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    /**
     * Get the user who created the job ad.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope a query to only include active job ads.
     */
    public function scopeActive($query)
    {
        return $query->whereRaw('is_active = true')
            ->where(function ($q) {
                $q->whereNull('deadline')
                  ->orWhere('deadline', '>=', now()->toDateString());
            });
    }

    /**
     * Check if the job ad is expired.
     */
    public function isExpired(): bool
    {
        return $this->deadline && $this->deadline->isPast();
    }

    /**
     * Get the gender requirement label.
     */
    public function getGenderLabelAttribute(): string
    {
        return match($this->gender_requirement) {
            'male' => 'Muškarac',
            'female' => 'Žena',
            'any' => 'Bilo koji spol',
            default => 'Bilo koji spol',
        };
    }
}
