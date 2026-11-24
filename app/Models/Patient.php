<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'emergency_contact_name',
        'emergency_contact_phone',
        'notes',
        'medical_notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * The forms call this field "medical_notes"; it is stored in the `notes` column.
     */
    protected function medicalNotes(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attributes['notes'] ?? null,
            set: fn ($value) => ['notes' => $value],
        );
    }

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
     * Whether any contract still ties this patient to staff or money.
     *
     * activeContracts() is not enough: the nightly job moves past-end-date
     * contracts to "expired" without releasing staff or settling payments.
     * Closing is the settlement point (payments are refused after it), so
     * every contract must be closed, and none may still have staff placed.
     * Any balance left on a closed contract was settled by closing it.
     */
    public function hasUnsettledContracts(): bool
    {
        return $this->contracts()
            ->where(fn ($q) => $q->where('status', '!=', 'closed')
                ->orWhereHas('assignments', fn ($a) => $a->where('is_active', true)))
            ->exists();
    }

    /**
     * Scope for active patients
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for inactive patients
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }
}
