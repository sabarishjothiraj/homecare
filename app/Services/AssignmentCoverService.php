<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\AssignmentNote;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Temporary covers for an employee on leave.
 *
 * Unlike a replacement, a cover leaves the primary assignment active - the
 * employee stays placed on the contract and resumes when the cover ends. The
 * cover is its own assignment (so the stand-in is reserved and shows up in
 * placement counts) and does not use up the service line's change limit.
 */
class AssignmentCoverService
{
    /**
     * Arrange a cover for $primary between $data['start_date'] and $data['end_date'].
     *
     * @throws ValidationException
     */
    public function assignCover(Assignment $primary, array $data): Assignment
    {
        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();

        $this->guardPrimary($primary);
        $this->guardDates($primary, $start, $end);

        return DB::transaction(function () use ($primary, $start, $end, $data) {
            // Inside the transaction: the employee row lock serialises this
            // "already placed" check with Replace and contract create/edit.
            $employee = $this->guardEmployee($primary, (int) $data['employee_id']);

            $cover = Assignment::create([
                'contract_id' => $primary->contract_id,
                'contract_item_id' => $primary->contract_item_id,
                'employee_id' => $employee->id,
                'service_id' => $primary->service_id,
                // The line is already priced on the primary; a cover adds no cost.
                'line_amount' => null,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'is_emergency_replacement' => false,
                'replaced_assignment_id' => null,
                'cover_for_assignment_id' => $primary->id,
                'notes' => $data['notes'] ?? null,
                'is_active' => true,
            ]);

            if (! empty($data['notes'])) {
                AssignmentNote::create([
                    'assignment_id' => $cover->id,
                    'note' => $data['notes'],
                    'created_by' => auth()->id(),
                ]);
            }

            return $cover;
        });
    }

    /**
     * End a cover now. A cover that hasn't started yet is cancelled outright.
     */
    public function endCover(Assignment $cover): void
    {
        if (! $cover->isCover() || ! $cover->is_active) {
            throw ValidationException::withMessages(['cover' => 'This cover is not active.']);
        }

        $today = now()->startOfDay();

        if ($cover->start_date->gt($today)) {
            $cover->update(['is_active' => false]);
            $cover->delete();

            return;
        }

        $cover->update([
            'is_active' => false,
            'end_date' => $today->toDateString(),
        ]);
    }

    /**
     * End every active cover of $primary (used when the primary itself ends).
     */
    public function endCoversFor(Assignment $primary): void
    {
        $primary->covers()->where('is_active', true)->get()->each(fn (Assignment $cover) => $this->endCover($cover));
    }

    /**
     * Nightly: release covers whose last day has passed. The primary was never
     * deactivated, so the original employee simply carries on.
     */
    public function endExpiredCovers(): int
    {
        return Assignment::coverAssignments()
            ->where('is_active', true)
            ->whereDate('end_date', '<', now()->toDateString())
            ->update(['is_active' => false]);
    }

    private function guardPrimary(Assignment $primary): void
    {
        if ($primary->isCover()) {
            $this->fail('A temporary cover cannot itself be covered.');
        }

        if (! $primary->is_active) {
            $this->fail('Only an active assignment can be covered.');
        }

        $contract = $primary->contract;
        if ($contract && in_array($contract->status, ['closed', 'expired'], true)) {
            $this->fail('Cannot arrange a cover on a closed or expired contract.');
        }
    }

    private function guardDates(Assignment $primary, Carbon $start, Carbon $end): void
    {
        if ($end->lt($start)) {
            $this->fail('Cover end date must be on or after the start date.');
        }

        if ($end->lt(now()->startOfDay())) {
            $this->fail('Cover end date cannot be in the past.');
        }

        if ($primary->start_date && $start->lt($primary->start_date)) {
            $this->fail('Cover cannot start before the assignment starts.');
        }

        $contractEnd = $primary->contract?->end_date;
        if ($contractEnd && $end->gt($contractEnd)) {
            $this->fail('Cover cannot run past the contract end date ('.$contractEnd->format('M d, Y').').');
        }

        $overlaps = $primary->covers()
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->exists();

        if ($overlaps) {
            $this->fail('This employee already has a cover arranged for part of these dates.');
        }
    }

    private function guardEmployee(Assignment $primary, int $employeeId): Employee
    {
        if ($employeeId === (int) $primary->employee_id) {
            $this->fail('Cover employee must be different from the employee on leave.');
        }

        $employee = Employee::with('services')->lockForUpdate()->find($employeeId);
        if (! $employee || $employee->status !== 'active') {
            $this->fail('Cover employee must be active.');
        }

        $serviceId = (int) ($primary->contractItem->service_id ?? $primary->service_id);
        if ($serviceId && ! $employee->services->pluck('id')->contains($serviceId)) {
            $this->fail('Cover employee does not match this service category.');
        }

        if (config('services.razorpay.employee_payment_enabled') && ! $employee->hasValidPayment()) {
            $this->fail('Cover employee payment has expired.');
        }

        if (Assignment::where('is_active', true)->where('employee_id', $employeeId)->exists()) {
            $this->fail('This employee is already assigned to an active contract.');
        }

        return $employee;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['cover' => $message]);
    }
}
