<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assignment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'contract_id',
        'contract_item_id',
        'employee_id',
        'service_id',
        'line_amount',
        'start_date',
        'end_date',
        'is_emergency_replacement',
        'replaced_assignment_id',
        'cover_for_assignment_id',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'line_amount' => 'decimal:2',
        'is_emergency_replacement' => 'boolean',
        'is_active' => 'boolean',
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
     * Get line item
     */
    public function contractItem()
    {
        return $this->belongsTo(ContractItem::class);
    }

    /**
     * Get assignment service
     */
    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Get the replaced assignment
     */
    public function replacedAssignment()
    {
        return $this->belongsTo(Assignment::class, 'replaced_assignment_id');
    }

    /**
     * Get replacement assignments
     */
    public function replacements()
    {
        return $this->hasMany(Assignment::class, 'replaced_assignment_id');
    }

    /**
     * The primary assignment this temporary cover stands in for
     */
    public function coverFor()
    {
        return $this->belongsTo(Assignment::class, 'cover_for_assignment_id');
    }

    /**
     * Temporary covers arranged while this assignment's employee is on leave
     */
    public function covers()
    {
        return $this->hasMany(Assignment::class, 'cover_for_assignment_id');
    }

    public function isCover(): bool
    {
        return $this->cover_for_assignment_id !== null;
    }

    public const TYPES = [
        'primary' => 'Primary',
        'replacement' => 'Replacement',
        'temporary' => 'Temporary Replacement',
        'cover' => 'Temporary Cover',
    ];

    /**
     * How this employee came onto the line: primary, replacement, legacy temporary replacement or cover
     */
    public function type(): string
    {
        return match (true) {
            $this->isCover() => 'cover',
            (bool) $this->is_emergency_replacement => 'temporary',
            $this->replaced_assignment_id !== null => 'replacement',
            default => 'primary',
        };
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type()];
    }

    public function employeeName(): string
    {
        return $this->employee?->getField('name', 'Employee #'.$this->employee_id) ?? 'Employee #'.$this->employee_id;
    }

    /**
     * The assignment that took over this line from this one, if it was replaced
     */
    public function replacedBy(): ?Assignment
    {
        return $this->replacements->sortBy('id')->first();
    }

    /**
     * Why an inactive assignment ended (null while still active)
     */
    public function endReason(): ?string
    {
        if ($this->is_active) {
            return null;
        }

        if ($successor = $this->replacedBy()) {
            return 'Replaced by '.$successor->employeeName().' on '.$successor->start_date->format('M d, Y');
        }

        if ($this->isCover()) {
            return 'Cover ended';
        }

        return match ($this->contract?->status) {
            'closed' => 'Contract closed',
            'expired' => 'Contract expired',
            default => 'Ended',
        };
    }

    /**
     * Active cover whose period includes today, if the employee is on leave now
     */
    public function currentCover(): ?Assignment
    {
        $today = now()->startOfDay();

        return $this->covers
            ->where('is_active', true)
            ->first(fn ($cover) => $cover->start_date->lte($today) && $cover->end_date?->gte($today));
    }

    /**
     * Active covers that haven't started yet
     */
    public function upcomingCovers()
    {
        return $this->covers
            ->where('is_active', true)
            ->filter(fn ($cover) => $cover->start_date->isFuture())
            ->sortBy('start_date');
    }

    /**
     * Get assignment notes
     */
    public function assignmentNotes()
    {
        return $this->hasMany(AssignmentNote::class)->latest();
    }

    /**
     * Scope for active assignments
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for emergency replacements
     */
    public function scopeEmergencyReplacements($query)
    {
        return $query->where('is_emergency_replacement', true);
    }

    /**
     * Scope for regular (non-cover) assignments
     */
    public function scopePrimary($query)
    {
        return $query->whereNull('cover_for_assignment_id');
    }

    /**
     * Scope for temporary covers
     */
    public function scopeCoverAssignments($query)
    {
        return $query->whereNotNull('cover_for_assignment_id');
    }

    /**
     * Filter by type key from self::TYPES
     */
    public function scopeOfType($query, string $type)
    {
        return match ($type) {
            'cover' => $query->whereNotNull('cover_for_assignment_id'),
            'temporary' => $query->whereNull('cover_for_assignment_id')->where('is_emergency_replacement', true),
            'replacement' => $query->whereNull('cover_for_assignment_id')->where('is_emergency_replacement', false)->whereNotNull('replaced_assignment_id'),
            'primary' => $query->whereNull('cover_for_assignment_id')->where('is_emergency_replacement', false)->whereNull('replaced_assignment_id'),
            default => $query,
        };
    }

    /**
     * Scope for inactive assignments
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }
}
