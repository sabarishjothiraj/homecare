<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JoinRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'field_data',
        'status',
        'employee_id',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'field_data' => 'array',
        'reviewed_at' => 'datetime',
    ];

    /**
     * Get the employee (if converted)
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the reviewer
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Scope for new requests
     */
    public function scopeNew($query)
    {
        return $query->where('status', 'new');
    }

    /**
     * Scope for approved requests
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope for rejected requests
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Get field value
     */
    public function getField(string $key, $default = null)
    {
        return $this->field_data[$key] ?? $default;
    }
}

