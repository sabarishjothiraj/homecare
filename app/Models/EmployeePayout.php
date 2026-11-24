<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A payroll record: one employee's pay for one period.
 *
 * `rate` / `pay_type` snapshot the salary it was calculated on, `gross_amount`
 * is what attendance earned, `adjustments_total` is earnings minus deductions
 * from the payslip items, and `amount` is the net pay.
 */
class EmployeePayout extends Model
{
    use HasFactory;

    public const PAYMENT_METHODS = [
        'bank_transfer' => 'Bank Transfer',
        'cash' => 'Cash',
        'online' => 'Online / UPI',
        'cheque' => 'Cheque',
    ];

    protected $fillable = [
        'employee_id',
        'contract_id',
        'frequency',
        'pay_type',
        'rate',
        'period_start',
        'period_end',
        'days_in_period',
        'payable_days',
        'attendance_summary',
        'gross_amount',
        'adjustments_total',
        'amount',
        'status',
        'payment_method',
        'transaction_reference',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'amount' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'adjustments_total' => 'decimal:2',
        'payable_days' => 'decimal:1',
        'attendance_summary' => 'array',
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
     * Earning / deduction lines
     */
    public function items()
    {
        return $this->hasMany(EmployeePayoutItem::class);
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

    public function isEditable(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * How far deductions exceed earnings. Net pay is floored at ₹0, so this
     * part would go unrecovered; such a payslip can't be paid until it's zero.
     */
    public function shortfall(): float
    {
        return max(0.0, round(-((float) $this->gross_amount + (float) $this->adjustments_total), 2));
    }

    public function periodLabel(): string
    {
        if ($this->period_start && $this->period_end
            && $this->period_start->isSameDay($this->period_start->copy()->startOfMonth())
            && $this->period_end->isSameDay($this->period_start->copy()->endOfMonth())) {
            return $this->period_start->format('F Y');
        }

        return ($this->period_start?->format('M d, Y') ?? '?').' – '.($this->period_end?->format('M d, Y') ?? '?');
    }

    public function paymentMethodLabel(): ?string
    {
        return $this->payment_method ? (self::PAYMENT_METHODS[$this->payment_method] ?? ucfirst($this->payment_method)) : null;
    }
}
