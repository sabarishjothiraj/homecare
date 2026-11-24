<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalary extends Model
{
    public const PAY_TYPES = [
        'monthly' => 'Monthly Fixed',
        'daily' => 'Per Day',
    ];

    protected $fillable = [
        'employee_id',
        'pay_type',
        'amount',
        'effective_from',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'effective_from' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payTypeLabel(): string
    {
        return self::PAY_TYPES[$this->pay_type] ?? ucfirst($this->pay_type);
    }

    /**
     * e.g. "₹24,000 / month" or "₹800 / day"
     */
    public function rateLabel(): string
    {
        return '₹'.number_format((float) $this->amount, 0).($this->pay_type === 'daily' ? ' / day' : ' / month');
    }
}
