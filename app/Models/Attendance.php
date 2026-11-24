<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    /**
     * status => [label, short code, bootstrap colour]
     */
    public const STATUSES = [
        'present' => ['label' => 'Present', 'code' => 'P', 'tone' => 'success'],
        'half_day' => ['label' => 'Half Day', 'code' => 'H', 'tone' => 'info'],
        'absent' => ['label' => 'Absent', 'code' => 'A', 'tone' => 'danger'],
        'paid_leave' => ['label' => 'Paid Leave', 'code' => 'L', 'tone' => 'primary'],
        'week_off' => ['label' => 'Week Off', 'code' => 'W', 'tone' => 'secondary'],
    ];

    protected $fillable = [
        'employee_id',
        'date',
        'status',
        'notes',
        'marked_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    public function label(): string
    {
        return self::STATUSES[$this->status]['label'] ?? ucfirst($this->status);
    }

    /**
     * Identifies the mark as a form showed it. A save compares it with the
     * stored mark to spot changes someone made after the form was loaded.
     */
    public function version(): string
    {
        return $this->status.'@'.$this->updated_at?->getTimestamp();
    }
}
