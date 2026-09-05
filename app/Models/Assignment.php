<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assignment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'contract_id',
        'contract_item_id',
        'employee_id',
        'service_id',
        'line_amount',
        'start_date',
        'end_date',
        'is_emergency_replacement',
        'replaced_assignment_id',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'line_amount' => 'decimal:2',
        'is_emergency_replacement' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the contract
     */
    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * Get the employee
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get line item
     */
    public function contractItem()
    {
        return $this->belongsTo(ContractItem::class);
    }

    /**
     * Get assignment service
     */
    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Get the replaced assignment
     */
    public function replacedAssignment()
    {
        return $this->belongsTo(Assignment::class, 'replaced_assignment_id');
    }

    /**
     * Get replacement assignments
     */
    public function replacements()
    {
        return $this->hasMany(Assignment::class, 'replaced_assignment_id');
    }

    /**
     * Get assignment notes
     */
    public function assignmentNotes()
    {
        return $this->hasMany(AssignmentNote::class)->latest();
    }

    /**
     * Scope for active assignments
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for emergency replacements
     */
    public function scopeEmergencyReplacements($query)
    {
        return $query->where('is_emergency_replacement', true);
    }

    /**
     * Scope for inactive assignments
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }
}

