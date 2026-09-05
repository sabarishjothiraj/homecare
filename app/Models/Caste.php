<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Caste extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    /**
     * Get employees belonging to this caste
     *
     * Patients no longer carry a caste: the 2026_04_17 address migration
     * dropped patients.caste_id, so castes apply to employees only.
     */
    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * Scope to get only active castes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by display order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('name');
    }
}
