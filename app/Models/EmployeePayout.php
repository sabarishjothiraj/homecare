<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeePayout extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'contract_id',
        'frequency',
        'rate',
        'period_start',
        'period_end',
        'amount',
        'status',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'amount' => 'decimal:2',
        'period_start' => 'date',
        'period_end' => 'date',
        'paid_at' => 'datetime',
    ];

    /**
     * Get the employee
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the contract
     */
    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * Scope for pending payouts
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for paid payouts
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }
}

