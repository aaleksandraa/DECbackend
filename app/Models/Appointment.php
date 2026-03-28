<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Appointment extends Model
{
    use HasFactory, SoftDeletes;

    public const REVENUE_BASE_STATUSES = ['pending', 'confirmed', 'in_progress'];

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

    /**
     * Check whether this appointment should be treated as completed for metrics/revenue.
     * Includes historical confirmed/in_progress appointments whose time has passed.
     */
    public function isRecognizedCompleted(?Carbon $referenceTime = null): bool
    {
        if ($this->status === 'completed') {
            return true;
        }

        if (!in_array((string) $this->status, self::REVENUE_BASE_STATUSES, true)) {
            return false;
        }

        $reference = $referenceTime ?: Carbon::now();
        $dateValue = $this->date instanceof Carbon ? $this->date->copy() : Carbon::parse((string) $this->date);
        $referenceDate = $reference->copy()->startOfDay();

        if ($dateValue->lt($referenceDate)) {
            return true;
        }

        if (!$dateValue->isSameDay($reference)) {
            return false;
        }

        $endOrStart = trim((string) ($this->end_time ?: $this->time ?: '00:00:00'));
        $appointmentDateTime = Carbon::parse($dateValue->format('Y-m-d').' '.$endOrStart);

        return $appointmentDateTime->lessThanOrEqualTo($reference);
    }

    /**
     * Apply "recognized completed" filter to a query.
     * Works with both Eloquent and query builders.
     */
    public static function applyRecognizedCompletedFilter($query, ?Carbon $referenceTime = null, ?string $table = null)
    {
        $reference = $referenceTime ?: Carbon::now();
        $today = $reference->format('Y-m-d');
        $currentTime = $reference->format('H:i:s');

        $statusColumn = self::qualifyRecognitionColumn('status', $table);
        $dateColumn = self::qualifyRecognitionColumn('date', $table);
        $endTimeColumn = self::qualifyRecognitionColumn('end_time', $table);
        $timeColumn = self::qualifyRecognitionColumn('time', $table);

        return $query->where(function ($q) use ($statusColumn, $dateColumn, $endTimeColumn, $timeColumn, $today, $currentTime) {
            $q->where($statusColumn, 'completed')
                ->orWhere(function ($sub) use ($statusColumn, $dateColumn, $endTimeColumn, $timeColumn, $today, $currentTime) {
                    $sub->whereIn($statusColumn, self::REVENUE_BASE_STATUSES)
                        ->where(function ($timeAware) use ($dateColumn, $endTimeColumn, $timeColumn, $today, $currentTime) {
                            $timeAware->where($dateColumn, '<', $today)
                                ->orWhere(function ($todayOnly) use ($dateColumn, $endTimeColumn, $timeColumn, $today, $currentTime) {
                                    $todayOnly->where($dateColumn, '=', $today)
                                        ->whereRaw(
                                            "CAST(COALESCE(NULLIF({$endTimeColumn}, ''), NULLIF({$timeColumn}, ''), '00:00') AS TIME) <= ?",
                                            [$currentTime]
                                        );
                                });
                        });
                });
        });
    }

    /**
     * CASE expression for revenue aggregation using recognized-completed logic.
     * Bindings: [today, today, currentTime]
     */
    public static function recognizedRevenueCaseExpression(string $table = 'appointments'): string
    {
        $statusColumn = self::qualifyRecognitionColumn('status', $table);
        $dateColumn = self::qualifyRecognitionColumn('date', $table);
        $endTimeColumn = self::qualifyRecognitionColumn('end_time', $table);
        $timeColumn = self::qualifyRecognitionColumn('time', $table);
        $priceColumn = self::qualifyRecognitionColumn('total_price', $table);
        $statusList = self::sqlInList(self::REVENUE_BASE_STATUSES);

        return "CASE WHEN {$statusColumn} = 'completed'
            OR ({$statusColumn} IN ({$statusList})
                AND ({$dateColumn} < ? OR ({$dateColumn} = ?
                    AND CAST(COALESCE(NULLIF({$endTimeColumn}, ''), NULLIF({$timeColumn}, ''), '00:00') AS TIME) <= ?)))
            THEN COALESCE({$priceColumn}, 0)
            ELSE 0
        END";
    }

    /**
     * CASE expression for count aggregation using recognized-completed logic.
     * Bindings: [today, today, currentTime]
     */
    public static function recognizedCompletedCountCaseExpression(string $table = 'appointments'): string
    {
        $statusColumn = self::qualifyRecognitionColumn('status', $table);
        $dateColumn = self::qualifyRecognitionColumn('date', $table);
        $endTimeColumn = self::qualifyRecognitionColumn('end_time', $table);
        $timeColumn = self::qualifyRecognitionColumn('time', $table);
        $statusList = self::sqlInList(self::REVENUE_BASE_STATUSES);

        return "CASE WHEN {$statusColumn} = 'completed'
            OR ({$statusColumn} IN ({$statusList})
                AND ({$dateColumn} < ? OR ({$dateColumn} = ?
                    AND CAST(COALESCE(NULLIF({$endTimeColumn}, ''), NULLIF({$timeColumn}, ''), '00:00') AS TIME) <= ?)))
            THEN 1
            ELSE 0
        END";
    }

    /**
     * Bindings for recognized CASE expressions.
     */
    public static function recognizedRevenueBindings(?Carbon $referenceTime = null): array
    {
        $reference = $referenceTime ?: Carbon::now();
        $today = $reference->format('Y-m-d');

        return [$today, $today, $reference->format('H:i:s')];
    }

    private static function qualifyRecognitionColumn(string $column, ?string $table = null): string
    {
        return $table ? "{$table}.{$column}" : $column;
    }

    private static function sqlInList(array $values): string
    {
        return implode(', ', array_map(fn ($value) => "'".str_replace("'", "''", (string) $value)."'", $values));
    }
}
