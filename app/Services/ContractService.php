<?php

namespace App\Services;

use App\Jobs\GenerateContractPdf;
use App\Models\Assignment;
use App\Models\Contract;
use App\Models\ContractItem;
use App\Models\Employee;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ContractService
{
    /**
     * Tolerance for float comparison on rupee amounts.
     */
    protected const EPSILON = 0.01;

    /**
     * Agreement PDFs carry patient and staff details, so they live on the
     * private disk and are only reachable through the permission-checked
     * download / view / print routes.
     */
    public const PDF_DISK = 'local';

    /**
     * Create a new contract
     */
    public function createContract(array $data): Contract
    {
        return DB::transaction(function () use ($data) {
            $endDate = $this->calculateEndDate(
                $data['start_date'],
                $data['duration_type'],
                $data['duration_value'] ?? null,
                $data['custom_end_date'] ?? null
            );

            $lineItems = collect($data['line_items'] ?? []);
            $totalAmount = $this->lineTotal($lineItems);
            $this->assertDistinctEmployees($lineItems);
            $primaryServiceId = (int) ($lineItems->first()['service_id'] ?? $data['service_id']);

            $contract = Contract::create([
                'patient_id' => $data['patient_id'],
                'service_id' => $primaryServiceId,
                'created_by' => auth()->id(),
                'duration_type' => $data['duration_type'],
                'duration_value' => $data['duration_value'] ?? null,
                'start_date' => $data['start_date'],
                'end_date' => $endDate,
                'amount' => $totalAmount,
                'payment_method' => $data['payment_method'],
                'payment_details' => $data['payment_method'] === 'cash' ? null : ($data['payment_details'] ?? null),
                'terms' => $data['terms'] ?? $this->getDefaultTerms(),
                'status' => 'not_assigned',
            ]);

            $maxAssignments = max(1, (int) Setting::get('contract_max_assignments', 10));

            foreach ($lineItems as $lineItem) {
                $serviceId = (int) $lineItem['service_id'];
                $employee = $this->eligibleEmployee((int) $lineItem['employee_id'], $serviceId);
                $this->assertNotPlaced($employee);

                $contractItem = ContractItem::create([
                    'contract_id' => $contract->id,
                    'service_id' => $serviceId,
                    'amount' => $lineItem['amount'],
                    'max_assignments' => $maxAssignments,
                    'assignment_count' => 1,
                ]);

                Assignment::create([
                    'contract_id' => $contract->id,
                    'contract_item_id' => $contractItem->id,
                    'employee_id' => $employee->id,
                    'service_id' => $serviceId,
                    'line_amount' => $lineItem['amount'],
                    'start_date' => $data['assignment_start_date'] ?? $data['start_date'],
                    'end_date' => $data['assignment_end_date'] ?? null,
                    'is_emergency_replacement' => false,
                    'replaced_assignment_id' => null,
                    'notes' => $lineItem['note'] ?? ($data['assignment_note'] ?? null),
                    'is_active' => true,
                ]);
            }

            $contract->updateStatus();

            // Queued, and explicitly after commit: rendering inline held the
            // contract's row lock for the whole six-second render.
            GenerateContractPdf::dispatch($contract)->afterCommit();

            return $contract->fresh();
        });
    }

    /**
     * Update an existing contract and sync its service lines.
     *
     * Each submitted line may carry an "item_id" pointing at an existing
     * ContractItem. Existing lines are updated in place (preserving their
     * assignment / replacement history), new lines are created, and lines
     * that are no longer present are removed. The employee on a staffed line
     * is changed through Replace, not here.
     */
    public function updateContract(Contract $contract, array $data): Contract
    {
        return DB::transaction(function () use ($contract, $data) {
            // Payment collection and closing lock the same row, so the paid
            // total checked below cannot move while this edit is saved.
            $locked = Contract::whereKey($contract->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === 'closed') {
                throw ValidationException::withMessages([
                    'line_items' => ['Cannot update a closed contract.'],
                ]);
            }

            $endDate = $this->calculateEndDate(
                $data['start_date'],
                $data['duration_type'],
                $data['duration_value'] ?? null,
                $data['custom_end_date'] ?? null
            );

            $lineItems = collect($data['line_items'] ?? []);
            $totalAmount = $this->lineTotal($lineItems);
            $this->assertDistinctEmployees($lineItems);

            // Money already collected, or held by an open checkout, cannot be
            // edited out of the total: the excess would vanish from every
            // balance and an open order could still capture on top of it.
            $committed = $locked->paidAmount() + $locked->reservedAmount();
            if ($totalAmount + self::EPSILON < $committed) {
                throw ValidationException::withMessages([
                    'line_items' => ['The contract total cannot be less than the ₹'.number_format($committed, 2).' already collected or held by open payments.'],
                ]);
            }

            $primaryServiceId = (int) ($lineItems->first()['service_id'] ?? $contract->service_id);

            $maxAssignments = max(1, (int) Setting::get('contract_max_assignments', 10));
            $assignmentStart = $data['assignment_start_date'] ?? $data['start_date'];
            $assignmentEnd = $data['assignment_end_date'] ?? null;

            // An expired contract has already released its staff; editing it
            // (short of extending it) must not put them back on the line.
            $staysExpired = $endDate !== null && Carbon::parse($endDate)->lte(today());

            // Staff on lines this edit removes are released below, so they may
            // move to another line in the same save.
            $keptIds = $contract->items()->whereKey($lineItems->pluck('item_id')->filter()->all())->pluck('id')->all();
            $releasedAssignmentIds = $contract->assignments()
                ->where('is_active', true)
                ->whereNotNull('contract_item_id')
                ->whereNotIn('contract_item_id', $keptIds)
                ->pluck('id')
                ->all();

            $keptItemIds = [];

            foreach ($lineItems as $lineItem) {
                $serviceId = (int) $lineItem['service_id'];
                $employee = $this->eligibleEmployee((int) $lineItem['employee_id'], $serviceId);

                $existingItem = ! empty($lineItem['item_id'])
                    ? $contract->items()->whereKey($lineItem['item_id'])->first()
                    : null;

                if ($existingItem) {
                    // The line's current (non-cover) assignment
                    $assignment = $existingItem->assignments()
                        ->primary()
                        ->where('is_active', true)
                        ->latest('id')
                        ->first();

                    // Swapping the employee in place skipped the line's change
                    // limit and erased the outgoing employee's placement.
                    if ($assignment && (int) $assignment->employee_id !== $employee->id) {
                        throw ValidationException::withMessages([
                            'line_items' => ['The employee on an existing service line cannot be changed here. Use Replace on the contract page, which keeps the placement history and the line\'s change limit.'],
                        ]);
                    }

                    $existingItem->update([
                        'service_id' => $serviceId,
                        'amount' => $lineItem['amount'],
                    ]);

                    if ($assignment) {
                        $assignment->update([
                            'service_id' => $serviceId,
                            'line_amount' => $lineItem['amount'],
                            'notes' => $lineItem['note'] ?? $assignment->notes,
                        ]);
                    } elseif (! $staysExpired) {
                        $this->assertNotPlaced($employee, $releasedAssignmentIds);

                        Assignment::create([
                            'contract_id' => $contract->id,
                            'contract_item_id' => $existingItem->id,
                            'employee_id' => $employee->id,
                            'service_id' => $serviceId,
                            'line_amount' => $lineItem['amount'],
                            'start_date' => $assignmentStart,
                            'end_date' => $assignmentEnd,
                            'is_emergency_replacement' => false,
                            'replaced_assignment_id' => null,
                            'notes' => $lineItem['note'] ?? null,
                            'is_active' => true,
                        ]);
                    }

                    $keptItemIds[] = $existingItem->id;
                } else {
                    $this->assertNotPlaced($employee, $releasedAssignmentIds);

                    $contractItem = ContractItem::create([
                        'contract_id' => $contract->id,
                        'service_id' => $serviceId,
                        'amount' => $lineItem['amount'],
                        'max_assignments' => $maxAssignments,
                        'assignment_count' => 1,
                    ]);

                    Assignment::create([
                        'contract_id' => $contract->id,
                        'contract_item_id' => $contractItem->id,
                        'employee_id' => $employee->id,
                        'service_id' => $serviceId,
                        'line_amount' => $lineItem['amount'],
                        'start_date' => $assignmentStart,
                        'end_date' => $assignmentEnd,
                        'is_emergency_replacement' => false,
                        'replaced_assignment_id' => null,
                        'notes' => $lineItem['note'] ?? null,
                        'is_active' => true,
                    ]);

                    $keptItemIds[] = $contractItem->id;
                }
            }

            // Remove service lines that were deleted in the form. Both models
            // soft-delete, so the history survives; deactivate and close out
            // the assignments first so a restored row never comes back still
            // flagged as an open, active placement.
            $removedItems = $contract->items()->whereNotIn('id', $keptItemIds)->get();
            foreach ($removedItems as $removedItem) {
                $removedItem->assignments()->where('is_active', true)->update([
                    'is_active' => false,
                    'end_date' => now()->toDateString(),
                ]);
                $removedItem->assignments()->delete();
                $removedItem->delete();
            }

            $contract->update([
                'patient_id' => $data['patient_id'],
                'service_id' => $primaryServiceId,
                'duration_type' => $data['duration_type'],
                'duration_value' => $data['duration_value'] ?? null,
                'start_date' => $data['start_date'],
                'end_date' => $endDate,
                'amount' => $totalAmount,
                'payment_method' => $data['payment_method'],
                'payment_details' => $data['payment_method'] === 'cash' ? null : ($data['payment_details'] ?? null),
                'terms' => $data['terms'] ?? $contract->terms,
            ]);

            $contract->updateStatus();

            // Queued, and explicitly after commit: rendering inline held the
            // contract's row lock for the whole six-second render.
            GenerateContractPdf::dispatch($contract)->afterCommit();

            return $contract->fresh();
        });
    }

    /**
     * Sum the service lines, refusing a total the DECIMAL(10,2) columns
     * cannot hold (MySQL strict mode would otherwise fail the insert).
     */
    protected function lineTotal(Collection $lineItems): float
    {
        $total = (float) $lineItems->sum(fn ($line) => (float) ($line['amount'] ?? 0));

        if ($total > Contract::MAX_AMOUNT) {
            throw ValidationException::withMessages([
                'line_items' => ['The contract total cannot exceed ₹'.number_format(Contract::MAX_AMOUNT, 2).'.'],
            ]);
        }

        return $total;
    }

    /**
     * One employee cannot staff two lines of the same contract.
     */
    protected function assertDistinctEmployees(Collection $lineItems): void
    {
        $employeeIds = $lineItems->map(fn ($line) => (int) ($line['employee_id'] ?? 0));

        if ($employeeIds->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'line_items' => ['The same employee cannot be assigned to more than one service line.'],
            ]);
        }
    }

    /**
     * Load a line's employee and check they may work its service.
     *
     * The row is locked so two concurrent placements of the same employee
     * (from here or from Replace) serialise on the "already placed" check.
     */
    protected function eligibleEmployee(int $employeeId, int $serviceId): Employee
    {
        $employee = Employee::with('services')->lockForUpdate()->find($employeeId);

        if (! $employee || $employee->status !== 'active') {
            throw ValidationException::withMessages([
                'line_items' => ['Only active employees can be assigned to the contract.'],
            ]);
        }

        if (! $employee->services->pluck('id')->contains($serviceId)) {
            throw ValidationException::withMessages([
                'line_items' => ["Employee {$employee->getField('name', '#'.$employee->id)} is not eligible for this service category."],
            ]);
        }

        if (config('services.razorpay.employee_payment_enabled') && ! $employee->hasValidPayment()) {
            throw ValidationException::withMessages([
                'line_items' => ["Employee {$employee->getField('name', '#'.$employee->id)} payment has expired."],
            ]);
        }

        return $employee;
    }

    /**
     * Refuse an employee already placed on an active assignment, the same
     * rule Replace and Temporary Cover enforce.
     */
    protected function assertNotPlaced(Employee $employee, array $ignoreAssignmentIds = []): void
    {
        $placed = Assignment::where('is_active', true)
            ->where('employee_id', $employee->id)
            ->whereNotIn('id', $ignoreAssignmentIds)
            ->exists();

        if ($placed) {
            throw ValidationException::withMessages([
                'line_items' => ["Employee {$employee->getField('name', '#'.$employee->id)} is already assigned to an active contract."],
            ]);
        }
    }

    /**
     * Calculate end date based on duration type
     */
    protected function calculateEndDate(string $startDate, string $durationType, ?int $durationValue = null, ?string $customEndDate = null): ?string
    {
        $start = Carbon::parse($startDate);
        $value = $durationValue ?? 1;

        // NoOverflow: Jan 31 + 1 month is Feb 28, not Mar 3.
        return match ($durationType) {
            'daily' => $start->addDays($value)->format('Y-m-d'),
            'weekly' => $start->addWeeks($value)->format('Y-m-d'),
            'monthly' => $start->addMonthsNoOverflow($value)->format('Y-m-d'),
            'yearly' => $start->addYearsNoOverflow($value)->format('Y-m-d'),
            'custom' => $customEndDate ? Carbon::parse($customEndDate)->format('Y-m-d') : null,
            default => null,
        };
    }

    /**
     * Render the agreement PDF for the contract's current state.
     */
    public function generateContractPDF(Contract $contract): string
    {
        ['html' => $html, 'path' => $path] = $this->agreement($contract);

        // Rendered through mPDF rather than DomPDF: the agreement is set in
        // Tamil, and DomPDF has no OpenType shaping — it drops the Tamil font
        // entirely and falls back to a Latin base font with no Tamil glyphs.
        app(TamilPdfService::class)->htmlToFile($html, $path, 'Agreement '.$contract->contract_number, self::PDF_DISK);

        $previous = $contract->pdf_path;
        $contract->update(['pdf_path' => $path]);

        // The superseded render, wherever an earlier release left it
        if ($previous && $previous !== $path) {
            Storage::disk(self::PDF_DISK)->delete($previous);
            Storage::disk('public')->delete($previous);
        }

        return $path;
    }

    /**
     * Return a usable PDF path, rendering it now if the file on disk is
     * missing or no longer matches the contract.
     *
     * Renders are queued, so between a contract changing and the worker
     * picking the job up there is a window where pdf_path is missing or
     * stale. Rendering on demand closes that window: the queue becomes a
     * warm-up, never a precondition, and the reader waits at most once.
     */
    public function ensurePdf(Contract $contract): string
    {
        $this->movePdfOffPublicDisk($contract->pdf_path);

        $path = $this->pdfPathFor($contract);

        if ($contract->pdf_path === $path && Storage::disk(self::PDF_DISK)->exists($path)) {
            return $path;
        }

        return $this->generateContractPDF($contract);
    }

    /**
     * Where the PDF for the contract's current content lives.
     *
     * The name carries a keyed hash of the rendered agreement, so any change
     * to what would be printed (an edit, a replacement, a renamed patient)
     * points at a new file and a stale one is never served as current. Keyed
     * on the app key, the name also cannot be guessed from the contract number.
     */
    public function pdfPathFor(Contract $contract): string
    {
        return $this->agreement($contract)['path'];
    }

    /**
     * Move a PDF written by an earlier release from the public disk to the
     * private one. Those files were readable at /storage/contracts/... by
     * anyone, with no login or permission check.
     */
    public function movePdfOffPublicDisk(?string $path): void
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return;
        }

        $private = Storage::disk(self::PDF_DISK);
        $contents = Storage::disk('public')->get($path);

        // Only drop the public copy once the private one is safely written
        if ($private->exists($path) || ($contents !== null && $private->put($path, $contents))) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * The agreement's HTML and the file it renders to.
     *
     * @return array{html: string, path: string}
     */
    protected function agreement(Contract $contract): array
    {
        $contract->load([
            // A patient deleted after the contract ended still signed it
            'patient' => fn ($query) => $query->withTrashed(),
            'service',
            'items.service',
            'assignments.employee',
            'assignments.service',
            'assignments.contractItem',
            'assignments.replacedAssignment.employee',
            'assignments.coverFor.employee',
            'assignments.assignmentNotes.creator',
        ]);

        $data = [
            'contract' => $contract,
            'special_terms' => $this->specialTerms($contract),
            'document_title' => 'Agreement '.$contract->contract_number,
            'company_name' => Setting::get('company_name', 'Sri Muruga Home Nursing'),
            'company_address' => Setting::get('company_address', ''),
            'company_phone' => Setting::get('company_phone', ''),
            'company_email' => Setting::get('company_email', ''),
            'company_logo' => Setting::get('company_logo', ''),
            'company_reg_no' => Setting::get('company_reg_no', '851/2015'),
        ];

        $html = view('pdf.contract-tamil', $data)->render();
        $fingerprint = substr(hash_hmac('sha256', $html, (string) config('app.key')), 0, 32);

        return [
            'html' => $html,
            'path' => 'contracts/contract_'.$contract->contract_number.'_'.$fingerprint.'.pdf',
        ];
    }

    /**
     * Terms written for this contract, or null when it still carries the
     * generic English defaults - the Tamil agreement already prints the
     * company's standard conditions.
     */
    protected function specialTerms(Contract $contract): ?string
    {
        $terms = trim(str_replace("\r\n", "\n", (string) $contract->terms));

        return $terms === '' || $terms === $this->getDefaultTerms() ? null : $terms;
    }

    /**
     * Close a contract
     *
     * @throws ValidationException when it is already closed or the refund exceeds what was paid
     */
    public function closeContract(Contract $contract, string $reason, array $refund = []): bool
    {
        return DB::transaction(function () use ($contract, $reason, $refund) {
            // Payment collection locks the same row, so no money can land
            // between the checks below and the close.
            $locked = Contract::whereKey($contract->getKey())->lockForUpdate()->firstOrFail();

            // Closing is final. A second close overwrote the refund and moved
            // closed_at, rewriting refund figures already reported by month.
            if ($locked->status === 'closed') {
                throw ValidationException::withMessages([
                    'closure_reason' => ['This contract is already closed.'],
                ]);
            }

            $refundAmount = $refund['refund_amount'] ?? null;
            $paid = $locked->paidAmount();
            if ($refundAmount !== null && (float) $refundAmount > $paid + self::EPSILON) {
                throw ValidationException::withMessages([
                    'refund_amount' => ['Refund cannot exceed the ₹'.number_format($paid, 2).' collected on this contract.'],
                ]);
            }

            $closedAt = now();

            // Release the staff. Leaving assignments active on a closed
            // contract left employees reading as placed and unavailable for
            // new work, and kept the contract in "currently staffed" counts.
            $contract->assignments()
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'end_date' => $closedAt->toDateString(),
                ]);

            // A checkout opened before closure must not capture against a
            // closed contract; a late capture is flagged for refund instead.
            app(PaymentService::class)->cancelOpenOrdersForContract($locked);

            return $contract->update([
                'status' => 'closed',
                'closure_reason' => $reason,
                'refund_amount' => $refundAmount,
                'refund_details' => $refund['refund_details'] ?? null,
                'closed_at' => $closedAt,
            ]);
        });
    }

    /**
     * Update contract statuses based on dates
     *
     * @return int the number of contracts whose status changed
     */
    public function updateContractStatuses(): int
    {
        $this->endLapsedAssignments();

        $updated = 0;

        foreach (Contract::whereNotIn('status', ['closed'])->get() as $contract) {
            if ($contract->updateStatus()) {
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * End primary and replacement assignments whose own end date has passed.
     *
     * The nightly cover job only ever released covers, so a line given an end
     * date kept its employee flagged as placed long after they had finished.
     */
    public function endLapsedAssignments(): int
    {
        $covers = app(AssignmentCoverService::class);

        $lapsed = Assignment::primary()
            ->where('is_active', true)
            ->whereDate('end_date', '<', today()->toDateString())
            ->get();

        foreach ($lapsed as $assignment) {
            $covers->endCoversFor($assignment);
            $assignment->update(['is_active' => false]);
        }

        return $lapsed->count();
    }

    /**
     * Get contracts expiring soon
     */
    public function getExpiringContracts(): \Illuminate\Database\Eloquent\Collection
    {
        $alertDays = (int) Setting::get('contract_alert_days', 7);

        // end_date is a DATE column, so compare whole days: against now() a
        // contract ending today sorts below the current time and drops out.
        return Contract::where('status', '!=', 'closed')
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [today()->toDateString(), today()->addDays($alertDays)->toDateString()])
            ->with(['patient', 'service'])
            ->get();
    }

    /**
     * Get default contract terms
     */
    protected function getDefaultTerms(): string
    {
        return "1. The service provider agrees to provide nursing care as per the agreed schedule.\n".
               "2. Payment must be made as per the agreed payment method and schedule.\n".
               "3. Either party may terminate this contract with 7 days written notice.\n".
               "4. The service provider maintains professional liability insurance.\n".
               "5. All personal and medical information will be kept confidential.\n".
               "6. The client agrees to provide a safe working environment for the nurse.\n".
               "7. Any changes to the service schedule must be communicated 24 hours in advance.\n".
               '8. This contract is governed by the laws of the applicable jurisdiction.';
    }

    /**
     * Generate contract statistics
     */
    public function getContractStatistics(): array
    {
        return [
            'total' => Contract::count(),
            'active' => Contract::where('status', 'active')->count(),
            'not_assigned' => Contract::where('status', 'not_assigned')->count(),
            'closing_soon' => Contract::where('status', 'closing_soon')->count(),
            'expired' => Contract::where('status', 'expired')->count(),
            'closed' => Contract::where('status', 'closed')->count(),
        ];
    }
}
