<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Staff extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'avatar_url',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'salon_id',
        'name',
        'slug',
        'role',
        'title',
        'bio',
        'bio_long',
        'avatar',
        'profile_image',
        'working_hours',
        'specialties',
        'years_experience',
        'education',
        'achievements',
        'languages',
        'instagram',
        'facebook',
        'tiktok',
        'rating',
        'review_count',
        'is_active',
        'is_public',
        'accepts_bookings',
        'booking_note',
        'auto_confirm',
        'display_order',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'working_hours' => 'json',
        'specialties' => 'json',
        'education' => 'json',
        'achievements' => 'json',
        'languages' => 'json',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'accepts_bookings' => 'boolean',
        'auto_confirm' => 'boolean',
        'rating' => 'float',
        'review_count' => 'integer',
        'years_experience' => 'integer',
    ];

    /**
     * Get the user that owns the staff profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the salon that the staff member belongs to.
     */
    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    /**
     * Get the services that the staff member can perform.
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'staff_services');
    }

    /**
     * Get the appointments for the staff member.
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Get the reviews for the staff member.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Get the breaks for the staff member.
     */
    public function breaks(): HasMany
    {
        return $this->hasMany(StaffBreak::class);
    }

    /**
     * Get the vacations for the staff member.
     */
    public function vacations(): HasMany
    {
        return $this->hasMany(StaffVacation::class);
    }

    /**
     * Get the portfolio items for the staff member.
     */
    public function portfolio(): HasMany
    {
        return $this->hasMany(StaffPortfolio::class)->orderBy('order');
    }

    /**
     * Get featured portfolio items.
     */
    public function featuredPortfolio(): HasMany
    {
        return $this->hasMany(StaffPortfolio::class)->where('is_featured', true)->orderBy('order');
    }

    /**
     * Scope for public staff members.
     */
    public function scopePublic($query)
    {
        return $query->whereRaw('is_public = true')->whereRaw('is_active = true');
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($staff) {
            if (empty($staff->slug)) {
                $staff->slug = Str::slug($staff->name);

                // Ensure uniqueness
                $count = 1;
                $originalSlug = $staff->slug;
                while (static::where('slug', $staff->slug)->exists()) {
                    $staff->slug = $originalSlug . '-' . $count;
                    $count++;
                }
            }
        });
    }

    /**
     * Calculate the average rating for the staff member.
     */
    public function calculateRating(): void
    {
        $reviews = $this->reviews()->get();
        $count = $reviews->count();

        if ($count > 0) {
            $this->rating = $reviews->avg('rating');
            $this->review_count = $count;
            $this->save();
        }
    }

    /**
     * Get the avatar URL attribute.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if ($this->avatar) {
            return asset('storage/' . $this->avatar);
        }
        return null;
    }

    /**
     * Check if staff member is available on a specific date and time.
     */
    public function isAvailable(string $date, string $time, int $duration = 30): bool
    {
        // Convert date format if needed (from European DD.MM.YYYY to ISO YYYY-MM-DD)
        $isoDate = $date;
        if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $date)) {
            $isoDate = \Carbon\Carbon::createFromFormat('d.m.Y', $date)->format('Y-m-d');
        }

        // Convert date to day of week
        $dayOfWeek = strtolower(date('l', strtotime($isoDate)));

        // Check working hours
        $workingHours = $this->working_hours[$dayOfWeek] ?? null;
        if (!$workingHours || !$workingHours['is_working']) {
            return false;
        }

        // Check if time is within working hours
        $startTime = strtotime($workingHours['start']);
        $endTime = strtotime($workingHours['end']);
        $appointmentTime = strtotime($time);
        $appointmentEndTime = strtotime("+{$duration} minutes", $appointmentTime);

        if ($appointmentTime < $startTime || $appointmentEndTime > $endTime) {
            return false;
        }

        // Check for salon breaks (applies to all staff in salon)
        foreach ($this->salon->salonBreaks as $break) {
            if (!$break->is_active) continue;

            if ($break->appliesTo($isoDate)) {
                $breakStart = strtotime($break->start_time);
                $breakEnd = strtotime($break->end_time);

                // Check if appointment overlaps with break
                if (($appointmentTime < $breakEnd) && ($appointmentEndTime > $breakStart)) {
                    return false;
                }
            }
        }

        // Check for salon vacations (applies to all staff in salon)
        foreach ($this->salon->salonVacations as $vacation) {
            if (!$vacation->is_active) continue;

            if ($vacation->isActiveFor($isoDate)) {
                return false;
            }
        }

        // Check for staff breaks
        foreach ($this->breaks as $break) {
            if (!$break->is_active) continue;

            if ($break->appliesTo($isoDate)) {
                $breakStart = strtotime($break->start_time);
                $breakEnd = strtotime($break->end_time);

                // Check if appointment overlaps with break
                if (($appointmentTime < $breakEnd) && ($appointmentEndTime > $breakStart)) {
                    return false;
                }
            }
        }

        // Check for staff vacations
        foreach ($this->vacations as $vacation) {
            if (!$vacation->is_active) continue;

            if ($vacation->isActiveFor($isoDate)) {
                return false;
            }
        }

        // Check for existing appointments (including pending - they also block slots)
        // CRITICAL FIX: Use Carbon to parse the date and compare properly
        // The date field is cast as 'date' in Appointment model, so it's a Carbon instance
        $carbonDate = \Carbon\Carbon::parse($isoDate);

        $existingAppointments = $this->appointments()
            ->whereDate('date', $carbonDate->format('Y-m-d'))
            ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ->get();

        // Only log when appointments are found to avoid spam
        if ($existingAppointments->count() > 0) {
            \Log::info('Staff availability check - appointments found', [
                'staff_id' => $this->id,
                'date_input' => $date,
                'iso_date' => $isoDate,
                'carbon_date' => $carbonDate->format('Y-m-d'),
                'time_checking' => $time,
                'duration' => $duration,
                'appointments_found' => $existingAppointments->count(),
                'appointments' => $existingAppointments->map(fn($a) => [
                    'id' => $a->id,
                    'date_raw' => $a->getRawOriginal('date'),
                    'date_formatted' => $a->date ? $a->date->format('Y-m-d') : null,
                    'time' => $a->time,
                    'end_time' => $a->end_time,
                    'status' => $a->status
                ])->toArray()
            ]);
        }

        foreach ($existingAppointments as $appointment) {
            $existingStart = strtotime($appointment->time);
            $existingEnd = strtotime($appointment->end_time);

            // Check if appointment overlaps with existing appointment
            if (($appointmentTime < $existingEnd) && ($appointmentEndTime > $existingStart)) {
                \Log::info('Slot blocked by existing appointment', [
                    'staff_id' => $this->id,
                    'date' => $isoDate,
                    'requested_time' => $time,
                    'requested_end' => date('H:i', $appointmentEndTime),
                    'blocking_appointment_id' => $appointment->id,
                    'blocking_time' => $appointment->time,
                    'blocking_end' => $appointment->end_time
                ]);
                return false;
            }
        }

        return true;
    }
}
