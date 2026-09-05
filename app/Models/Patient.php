<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address1',
        'address2',
        'area',
        'city',
        'state',
        'country',
        'pincode',
        'created_by',
        'stay_with_them',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Full postal address assembled from the individual address columns.
     *
     * Replaces the single `address` / `location` columns that the
     * 2026_04_17 migration split into address1..pincode.
     */
    public function getFullAddressAttribute(): string
    {
        return collect([
            $this->address1,
            $this->address2,
            $this->area,
            $this->city,
            $this->state,
            $this->country,
            $this->pincode,
        ])->filter()->implode(', ');
    }

    /**
     * Short "area, city" label for listings.
     */
    public function getShortAddressAttribute(): string
    {
        return collect([$this->area, $this->city])->filter()->implode(', ');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get contracts for this patient
     */
    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * Get active contracts
     */
    public function activeContracts()
    {
        return $this->contracts()->whereIn('status', ['active', 'not_assigned', 'closing_soon']);
    }

    /**
     * Scope for active patients
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

