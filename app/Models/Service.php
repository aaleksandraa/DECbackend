<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'duration',
        'price',
        'discount_price',
        'category',
        'display_order',
        'salon_id',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'duration' => 'integer',
        'price' => 'float',
        'discount_price' => 'float',
        'is_active' => 'boolean',
    ];

    /**
     * Get the salon that owns the service.
     */
    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    /**
     * Get the staff members that can perform this service.
     */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(Staff::class, 'staff_services');
    }

    /**
     * Get the appointments for this service.
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Get the images for this service.
     */
    public function images(): HasMany
    {
        return $this->hasMany(ServiceImage::class)->orderBy('order');
    }

    /**
     * Get featured images for this service.
     */
    public function featuredImages(): HasMany
    {
        return $this->hasMany(ServiceImage::class)->where('is_featured', 1)->orderBy('order');
    }

    /**
     * Scope a query to only include active services.
     */
    public function scopeActive($query)
    {
        return $query->whereRaw('is_active = true');
    }

    /**
     * Scope a query to filter by category.
     */
    public function scopeCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope a query to filter by price range.
     */
    public function scopePriceRange($query, $min, $max)
    {
        return $query->whereBetween('price', [$min, $max]);
    }

    /**
     * Scope a query to filter by duration range.
     */
    public function scopeDurationRange($query, $min, $max)
    {
        return $query->whereBetween('duration', [$min, $max]);
    }

    /**
     * Scope a query to search by name or description.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($query) use ($search) {
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
        });
    }
}
