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
     * Get contract lines for this service
     */
    public function contractItems()
    {
        return $this->hasMany(ContractItem::class);
    }

    /**
     * Get assignments made for this service
     */
    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * Whether any contract still uses this service. contracts.service_id only
     * holds a multi-line contract's first line; the others are on its items.
     */
    public function isInUse(): bool
    {
        return $this->contracts()->exists()
            || $this->contractItems()->exists()
            || $this->assignments()->exists();
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
        // id breaks ties so equal orders list in a stable sequence.
        return $query->orderBy('order')->orderBy('id');
    }

    /**
     * Resolve the service image, which may be an upload or an absolute URL.
     */
    public function getImageUrlAttribute(): ?string
    {
        return media_url($this->image);
    }
}
