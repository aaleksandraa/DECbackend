<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffVacation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'staff_id',
        'title',
        'start_date',
        'end_date',
        'type',
        'notes',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Get the staff member that owns the vacation.
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * Check if the vacation is active for a specific date.
     * @param string $date Date in ISO format (Y-m-d)
     */
    public function isActiveFor(string $date): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if (!$this->start_date || !$this->end_date) {
            return false;
        }

        $checkDate = Carbon::parse($date);
        return $checkDate->between($this->start_date, $this->end_date);
    }

    /**
     * Format dates for JSON serialization.
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        // Format dates in European format for frontend
        if ($this->start_date) {
            $array['start_date'] = $this->start_date->format('d.m.Y');
        }
        if ($this->end_date) {
            $array['end_date'] = $this->end_date->format('d.m.Y');
        }

        return $array;
    }
}
