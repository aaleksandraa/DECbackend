<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalonBreak extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'salon_id',
        'title',
        'type',
        'start_time',
        'end_time',
        'days',
        'date',
        'start_date',
        'end_date',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'days' => 'array',
        'date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Get the salon that owns the break.
     */
    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    /**
     * Check if the break applies to a specific date.
     * @param string $date Date in ISO format (Y-m-d)
     */
    public function appliesTo(string $date): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $checkDate = Carbon::parse($date);

        switch ($this->type) {
            case 'daily':
                return true;

            case 'weekly':
                $dayOfWeek = strtolower($checkDate->format('l'));
                return in_array($dayOfWeek, $this->days ?? []);

            case 'specific_date':
                return $this->date && $checkDate->isSameDay($this->date);

            case 'date_range':
                if (!$this->start_date || !$this->end_date) {
                    return false;
                }
                return $checkDate->between($this->start_date, $this->end_date);

            default:
                return false;
        }
    }

    /**
     * Format dates for JSON serialization.
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        // Format dates in European format for frontend
        if ($this->date) {
            $array['date'] = $this->date->format('d.m.Y');
        }
        if ($this->start_date) {
            $array['start_date'] = $this->start_date->format('d.m.Y');
        }
        if ($this->end_date) {
            $array['end_date'] = $this->end_date->format('d.m.Y');
        }

        return $array;
    }
}
