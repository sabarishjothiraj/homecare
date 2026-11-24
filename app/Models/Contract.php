<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Largest value the DECIMAL(10,2) money columns can hold.
     */
    public const MAX_AMOUNT = 99999999.99;

    protected $fillable = [
        'contract_number',
        'patient_id',
        'service_id',
        'created_by',
        'duration_type',
        'duration_value',
        'start_date',
        'end_date',
        'amount',
        'payment_method',
        'payment_details',
        'status',
        'terms',
        'pdf_path',
        'closure_reason',
        'refund_amount',
        'refund_details',
        'closed_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'amount' => 'decimal:2',
        'closed_at' => 'datetime',
    ];

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($contract) {
            if (! $contract->contract_number) {
                $contract->contract_number = 'CNT-'.strtoupper(uniqid());
            }
        });
    }

    /**
     * Get the patient
     */
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the service
     */
    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Get assignments
     */
    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * Get service line items
     */
    public function items()
    {
        return $this->hasMany(ContractItem::class);
    }

    /**
     * Get active assignment
     */
    public function activeAssignment()
    {
        return $this->hasOne(Assignment::class)->where('is_active', true)->latest();
    }

    /**
     * Get active assignments
     */
    public function activeAssignments()
    {
        return $this->hasMany(Assignment::class)->where('is_active', true)->latest();
    }

    /**
     * Get payments
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Scope for active contracts
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for closing soon
     */
    public function scopeClosingSoon($query)
    {
        return $query->where('status', 'closing_soon');
    }

    /**
     * Scope for not assigned
     */
    public function scopeNotAssigned($query)
    {
        return $query->where('status', 'not_assigned');
    }

    /**
     * Total amount already paid (completed payments only)
     */
    public function paidAmount(): float
    {
        return (float) $this->payments()->where('status', 'completed')->sum('amount');
    }

    /**
     * Outstanding balance on the contract
     */
    public function balanceDue(): float
    {
        return max(0, (float) $this->amount - $this->paidAmount());
    }

    /**
     * How long an unpaid gateway order keeps holding the balance it opened for.
     */
    public static function orderTtlMinutes(): int
    {
        return max(1, (int) config('services.razorpay.order_ttl_minutes', 30));
    }

    /**
     * Amount held by gateway orders that are open but not yet captured.
     *
     * A pending order is money the customer may still be in the middle of
     * paying. Ignoring it lets a second checkout open for the same balance,
     * and both can then capture. Orders past their TTL are treated as
     * abandoned so an unfinished checkout cannot lock the balance forever.
     */
    public function reservedAmount(): float
    {
        return (float) $this->payments()
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subMinutes(static::orderTtlMinutes()))
            ->sum('amount');
    }

    /**
     * What may still be charged: the total, less what is collected and less
     * what open orders are already holding.
     */
    public function availableToCharge(): float
    {
        return max(0, (float) $this->amount - $this->paidAmount() - $this->reservedAmount());
    }

    /**
     * Payment status: unpaid | partial | paid
     */
    public function paymentStatus(): string
    {
        $paid = $this->paidAmount();

        if ($paid <= 0) {
            return 'unpaid';
        }

        return $this->balanceDue() <= 0 ? 'paid' : 'partial';
    }

    /**
     * Check if contract is expiring soon
     */
    public function isExpiringSoon(): bool
    {
        if (! $this->end_date) {
            return false;
        }

        $alertDays = Setting::get('contract_alert_days', 7);
        $daysUntilExpiry = Carbon::now()->diffInDays($this->end_date, false);

        return $daysUntilExpiry >= 0 && $daysUntilExpiry <= $alertDays;
    }

    /**
     * Check if contract is expired
     */
    public function isExpired(): bool
    {
        if (! $this->end_date) {
            return false;
        }

        return Carbon::now()->isAfter($this->end_date);
    }

    /**
     * Update contract status based on dates and assignments
     *
     * @return bool whether the status changed
     */
    public function updateStatus(): bool
    {
        if ($this->status === 'closed') {
            return false;
        }

        // Staffing is checked before the expiry window: an unstaffed contract
        // needs to read as "not_assigned" so it stays actionable, even when it
        // happens to be inside the closing-soon alert period.
        if ($this->isExpired()) {
            $this->status = 'expired';
            $this->releaseAssignments();
        } elseif (! $this->assignments()->active()->exists()) {
            $this->status = 'not_assigned';
        } elseif ($this->isExpiringSoon()) {
            $this->status = 'closing_soon';
        } else {
            $this->status = 'active';
        }

        $changed = $this->isDirty('status');

        $this->save();

        return $changed;
    }

    /**
     * End every assignment still active on an expired contract.
     *
     * Availability checks key on is_active alone, so staff left active on a
     * finished contract read as placed and could not be given new work.
     */
    protected function releaseAssignments(): void
    {
        $endDate = $this->end_date->toDateString();

        // Nobody works past the contract, but an earlier end stays as recorded
        $this->assignments()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>', $endDate))
            ->update(['end_date' => $endDate]);

        $this->assignments()->where('is_active', true)->update(['is_active' => false]);
    }
}
