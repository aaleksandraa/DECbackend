<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class HomepageCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'title',
        'description',
        'image_url',
        'link_type',
        'link_value',
        'is_enabled',
        'display_order',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'display_order' => 'integer',
    ];

    /**
     * Get enabled categories ordered by display_order.
     */
    public static function getEnabled()
    {
        return static::where('is_enabled', true)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Get full image URL.
     */
    public function getImageUrlAttribute($value)
    {
        if (!$value) {
            return null;
        }

        // If it's already a full URL, return as is
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        // If it's a storage path, generate URL
        if (Storage::disk('public')->exists($value)) {
            return Storage::disk('public')->url($value);
        }

        return $value;
    }

    /**
     * Generate search URL based on link configuration.
     */
    public function getSearchUrl(): string
    {
        // If link_value starts with /, it's already a full path
        if (str_starts_with($this->link_value, '/')) {
            return $this->link_value;
        }

        // If link_type is url, return as is
        if ($this->link_type === 'url') {
            return $this->link_value;
        }

        // Otherwise, prepend /saloni
        return '/saloni?' . $this->link_value;
    }

    /**
     * Scope to get only enabled categories.
     */
    public function scopeEnabled($query)
    {
        return $query->whereRaw('is_enabled = true');
    }

    /**
     * Scope to order by display order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('id');
    }
}
