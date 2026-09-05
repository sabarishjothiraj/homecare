<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'alternate_phone',
        'field_data',
        'caste_id',
        'subcaste',
        'created_by',
        'status',
        'source',
        'activated_at',
        'payment_valid_until',
    ];

    protected $casts = [
        'field_data' => 'array',
        'activated_at' => 'datetime',
        'payment_valid_until' => 'datetime',
    ];

    /**
     * Get the caste that the employee belongs to
     */
    public function caste()
    {
        return $this->belongsTo(Caste::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get employee documents
     */
    public function documents()
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    /**
     * Get employee assignments
     */
    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * Get employee payouts
     */
    public function payouts()
    {
        return $this->hasMany(EmployeePayout::class);
    }

    /**
     * Get employee payments
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get employee categories/services
     */
    public function services()
    {
        return $this->belongsToMany(Service::class, 'employee_service')->withTimestamps();
    }

    /**
     * Scope for active employees
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for inactive employees
     */
    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    /**
     * Scope for pending employees
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Check if employee payment is valid
     */
    public function hasValidPayment(): bool
    {
        if (!$this->payment_valid_until) {
            return true; // No payment required
        }
        return $this->payment_valid_until->isFuture();
    }

    /**
     * Get field value
     */
    public function getField(string $key, $default = null)
    {
        if ($key === 'name') {
            $fullName = trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
            if ($fullName !== '') {
                return $fullName;
            }
        }

        if (in_array($key, ['email', 'phone', 'alternate_phone'], true) && !empty($this->{$key})) {
            return $this->{$key};
        }

        return $this->field_data[$key] ?? $default;
    }

    /**
     * Set field value
     */
    public function setField(string $key, $value): void
    {
        $data = $this->field_data ?? [];
        $data[$key] = $value;
        $this->field_data = $data;
    }
}

