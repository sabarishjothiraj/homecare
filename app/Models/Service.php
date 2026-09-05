<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'icon',
        'image',
        'is_active',
        'order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get contracts for this service
     */
    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * Get employees tagged for this service
     */
    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'employee_service')->withTimestamps();
    }

    /**
     * Scope for active services
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for ordered services
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    /**
     * Resolve the service image, which may be an upload or an absolute URL.
     */
    public function getImageUrlAttribute(): ?string
    {
        return media_url($this->image);
    }
}

