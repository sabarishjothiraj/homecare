<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    /**
     * Prefix written into notes when Razorpay captured money that could not
     * be credited (the order was cancelled or already paid). Staff must
     * refund it at the gateway.
     */
    public const REFUND_MARKER = '[REFUND REQUIRED]';

    protected $fillable = [
        'payment_type',
        'contract_id',
        'employee_id',
        'razorpay_order_id',
        'razorpay_payment_id',
        'razorpay_signature',
        'amount',
        'status',
        'method',
        'notes',
        'reference',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
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
     * Whether a gateway capture on this row is waiting to be refunded.
     */
    public function needsRefund(): bool
    {
        return $this->status !== 'refunded'
            && str_contains((string) $this->notes, self::REFUND_MARKER);
    }

    /**
     * Scope for completed payments
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for pending payments
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for contract payments
     */
    public function scopeContractPayments($query)
    {
        return $query->where('payment_type', 'contract');
    }

    /**
     * Scope for employee activation payments
     */
    public function scopeEmployeePayments($query)
    {
        return $query->where('payment_type', 'employee_activation');
    }
}
