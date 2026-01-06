<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'city_slug',
        'postal_code',
        'entity',
        'canton',
        'region',
        'latitude',
        'longitude',
        'is_active',
        'population',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'is_active' => 'boolean',
        'population' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($location) {
            if (empty($location->city_slug)) {
                $location->city_slug = Str::slug($location->name);
            }
        });
    }

    /**
     * Scope for active locations
     */
    public function scopeActive($query)
    {
        // PostgreSQL requires explicit boolean cast
        return $query->whereRaw('is_active = true');
    }

    /**
     * Scope for FBiH
     */
    public function scopeFbih($query)
    {
        return $query->where('entity', 'FBiH');
    }

    /**
     * Scope for RS
     */
    public function scopeRs($query)
    {
        return $query->where('entity', 'RS');
    }

    /**
     * Get salons in this location
     */
    public function salons()
    {
        return $this->hasMany(Salon::class, 'city_slug', 'city_slug');
    }
}
