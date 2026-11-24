<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An earning or deduction line on a payslip (bonus, advance recovery, and
 * later allowances / statutory deductions).
 */
class EmployeePayoutItem extends Model
{
    protected $fillable = [
        'employee_payout_id',
        'type',
        'label',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function payout(): BelongsTo
    {
        return $this->belongsTo(EmployeePayout::class, 'employee_payout_id');
    }

    /**
     * Amount with sign: deductions are negative
     */
    public function signedAmount(): float
    {
        return $this->type === 'deduction' ? -(float) $this->amount : (float) $this->amount;
    }
}
