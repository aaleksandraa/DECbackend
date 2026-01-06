<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Salon extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'description',
        'address',
        'city',
        'city_slug',
        'postal_code',
        'country',
        'phone',
        'email',
        'website',
        'working_hours',
        'location',
        'latitude',
        'longitude',
        'google_maps_url',
        'target_audience',
        'amenities',
        'social_media',
        'rating',
        'review_count',
        'owner_id',
        'status',
        'is_verified',
        'auto_confirm',
        'booking_slot_interval',
        'show_service_gallery',
        'category_order',
        'email_notifications_enabled',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'working_hours' => 'json',
        'location' => 'json',
        'category_order' => 'json',
        'show_service_gallery' => 'boolean',
        'target_audience' => 'json',
        'amenities' => 'json',
        'social_media' => 'json',
        'meta_keywords' => 'json',
        'is_verified' => 'boolean',
        'auto_confirm' => 'boolean',
        'email_notifications_enabled' => 'boolean',
        'rating' => 'float',
        'review_count' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'booking_slot_interval' => 'integer',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($salon) {
            if (empty($salon->slug)) {
                $salon->slug = static::generateUniqueSlug($salon->name);
            }
            if (empty($salon->city_slug) && !empty($salon->city)) {
                $salon->city_slug = Str::slug($salon->city);
            }
        });

        static::updating(function ($salon) {
            // Regenerate slug if name changed
            if ($salon->isDirty('name') && !$salon->isDirty('slug')) {
                $salon->slug = static::generateUniqueSlug($salon->name, $salon->id);
            }
            // Regenerate city_slug if city changed
            if ($salon->isDirty('city')) {
                $salon->city_slug = Str::slug($salon->city);
            }
        });
    }

    /**
     * Generate a unique slug for the salon.
     */
    public static function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        $query = static::where('slug', $slug);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        while ($query->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;

            $query = static::where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
        }

        return $slug;
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'id'; // Keep ID for admin routes, use slug for public
    }

    /**
     * Resolve route binding by slug or ID.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        // If numeric, find by ID; otherwise, find by slug
        if (is_numeric($value)) {
            return $this->where('id', $value)->first();
        }

        return $this->where('slug', $value)->first();
    }

    /**
     * Get the owner of the salon.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the staff members for the salon.
     */
    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }

    /**
     * Get the settings for the salon.
     */
    public function settings(): HasOne
    {
        return $this->hasOne(SalonSettings::class);
    }

    /**
     * Get the services for the salon.
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    /**
     * Get the appointments for the salon.
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Get the reviews for the salon.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Get the salon breaks for the salon.
     */
    public function salonBreaks(): HasMany
    {
        return $this->hasMany(SalonBreak::class);
    }

    /**
     * Get the salon vacations for the salon.
     */
    public function salonVacations(): HasMany
    {
        return $this->hasMany(SalonVacation::class);
    }

    /**
     * Get the images for the salon.
     */
    public function images(): HasMany
    {
        return $this->hasMany(SalonImage::class);
    }

    /**
     * Get the users who favorited this salon.
     */
    public function favoritedBy(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    /**
     * Scope a query to only include approved salons.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope a query to only include pending salons.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include suspended salons.
     */
    public function scopeSuspended($query)
    {
        return $query->where('status', 'suspended');
    }

    /**
     * Scope a query to filter by target audience.
     */
    public function scopeForAudience($query, array $audience)
    {
        foreach ($audience as $type => $value) {
            if ($value) {
                $query->whereJsonContains('target_audience->' . $type, true);
            }
        }

        return $query;
    }

    /**
     * Calculate the average rating for the salon.
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
}
