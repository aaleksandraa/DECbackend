<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Appointment extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'client_id',
        'client_name',
        'client_email',
        'client_phone',
        'is_guest',
        'guest_address',
        'salon_id',
        'staff_id',
        'service_id',
        'service_ids', // For multi-service appointments
        'date',
        'time',
        'end_time',
        'status',
        'notes',
        'booking_source',
        'total_price',
        'payment_status',
        'source',
        'import_batch_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date' => 'date',
        'total_price' => 'float',
        'is_guest' => 'boolean', // Keep cast for reading from database
        'service_ids' => 'array', // Cast JSON to array
    ];

    /**
     * Get the client that owns the appointment.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * Get the salon that owns the appointment.
     */
    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    /**
     * Get the staff member that owns the appointment.
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * Get the service that owns the appointment.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Get all services for this appointment (for multi-service appointments).
     * Returns collection of Service models.
     */
    public function services()
    {
        if ($this->service_ids && is_array($this->service_ids)) {
            return Service::whereIn('id', $this->service_ids)->get();
        }

        // Fallback to single service
        if ($this->service_id) {
            return collect([$this->service]);
        }

        return collect([]);
    }

    /**
     * Check if this is a multi-service appointment.
     */
    public function isMultiService(): bool
    {
        return !empty($this->service_ids) && count($this->service_ids) > 1;
    }

    /**
     * Get the review associated with the appointment.
     */
    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    /**
     * Get the import batch that owns the appointment.
     */
    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    /**
     * Scope a query to only include appointments for a specific date.
     */
    public function scopeForDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    /**
     * Scope a query to only include appointments with specific status.
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include upcoming appointments.
     */
    public function scopeUpcoming($query)
    {
        $today = now()->format('Y-m-d');
        $currentTime = now()->format('H:i');

        return $query->where(function ($query) use ($today, $currentTime) {
            $query->where('date', '>', $today)
                  ->orWhere(function ($query) use ($today, $currentTime) {
                      $query->where('date', $today)
                            ->where('time', '>=', $currentTime);
                  });
        })->whereIn('status', ['pending', 'confirmed']);
    }

    /**
     * Scope a query to only include past appointments.
     */
    public function scopePast($query)
    {
        $today = now()->format('Y-m-d');
        $currentTime = now()->format('H:i');

        return $query->where(function ($query) use ($today, $currentTime) {
            $query->where('date', '<', $today)
                  ->orWhere(function ($query) use ($today, $currentTime) {
                      $query->where('date', $today)
                            ->where('time', '<', $currentTime);
                  });
        })->orWhereIn('status', ['completed', 'cancelled', 'no_show']);
    }

    /**
     * Check if the appointment can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'confirmed']);
    }

    /**
     * Check if the appointment can be rescheduled.
     */
    public function canBeRescheduled(): bool
    {
        return in_array($this->status, ['pending', 'confirmed']);
    }

    /**
     * Check if the appointment can be reviewed.
     */
    public function canBeReviewed(): bool
    {
        return $this->status === 'completed' && !$this->review()->exists();
    }

    /**
     * Check if the appointment can be marked as no-show.
     * Only confirmed appointments that have passed their start time can be marked as no-show.
     */
    public function canBeMarkedAsNoShow(): bool
    {
        if ($this->status !== 'confirmed') {
            return false;
        }

        $now = now();
        $appointmentDateTime = \Carbon\Carbon::parse($this->date->format('Y-m-d') . ' ' . $this->time);

        // Can only mark as no-show after the appointment start time has passed
        return $now->greaterThan($appointmentDateTime);
    }

    /**
     * Check if the appointment has expired (end time has passed).
     */
    public function hasExpired(): bool
    {
        $now = now();
        $endDateTime = \Carbon\Carbon::parse($this->date->format('Y-m-d') . ' ' . $this->end_time);

        return $now->greaterThan($endDateTime);
    }
}
