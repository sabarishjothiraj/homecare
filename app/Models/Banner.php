<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'subtitle',
        'image',
        'link',
        'button_text',
        'order',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    /**
     * Scope a query to only include active banners.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to order banners by order field.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order', 'asc');
    }

    /**
     * Get the image URL.
     */
    public function getImageUrlAttribute(): string
    {
        return (string) media_url($this->image);
    }
}

