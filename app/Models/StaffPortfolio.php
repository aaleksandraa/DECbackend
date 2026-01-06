<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffPortfolio extends Model
{
    use HasFactory;

    protected $table = 'staff_portfolio';

    protected $fillable = [
        'staff_id',
        'image_url',
        'title',
        'description',
        'category',
        'tags',
        'order',
        'is_featured',
    ];

    protected $casts = [
        'tags' => 'array',
        'is_featured' => 'boolean',
        'order' => 'integer',
    ];

    /**
     * Get the staff member that owns this portfolio item
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
