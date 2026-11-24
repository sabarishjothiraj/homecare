<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JoinRequest extends Model
{
    use HasFactory;

    /**
     * status => [label, bootstrap colour], matching the join_requests.status enum.
     */
    public const STATUSES = [
        'new' => ['label' => 'New', 'tone' => 'primary'],
        'reviewing' => ['label' => 'Reviewing', 'tone' => 'info'],
        'approved' => ['label' => 'Approved', 'tone' => 'success'],
        'rejected' => ['label' => 'Rejected', 'tone' => 'danger'],
        'converted' => ['label' => 'Converted', 'tone' => 'dark'],
    ];

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
     * Once an employee exists for this request it must not be reviewed or
     * converted again.
     */
    public function isConverted(): bool
    {
        return $this->status === 'converted' || $this->employee_id !== null;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status]['label'] ?? ucfirst((string) $this->status);
    }

    public function statusTone(): string
    {
        return self::STATUSES[$this->status]['tone'] ?? 'secondary';
    }

    /**
     * Applicant name from either a single "name" field or the default
     * first_name / last_name fields.
     */
    public function applicantName(): ?string
    {
        $name = trim(($this->getField('first_name') ?? '').' '.($this->getField('last_name') ?? ''));

        return $this->getField('name') ?: ($name !== '' ? $name : null);
    }

    /**
     * Get field value
     */
    public function getField(string $key, $default = null)
    {
        return $this->field_data[$key] ?? $default;
    }
}
