<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Contract;
use App\Models\ContractItem;
use App\Models\Employee;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContractService
{
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
            $totalAmount = $lineItems->sum(fn ($line) => (float) ($line['amount'] ?? 0));
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
                'terms' => $data['terms'] ?? $this->getDefaultTerms(),
                'status' => 'not_assigned',
            ]);

            $maxAssignments = max(1, (int) Setting::get('contract_max_assignments', 10));

            foreach ($lineItems as $lineItem) {
                $employee = Employee::with('services')->find($lineItem['employee_id']);
                if (!$employee || $employee->status !== 'active') {
                    throw ValidationException::withMessages([
                        'line_items' => ['Only active employees can be assigned to the contract.'],
                    ]);
                }

                $serviceId = (int) $lineItem['service_id'];
                if (!$employee->services->pluck('id')->contains($serviceId)) {
                    throw ValidationException::withMessages([
                        'line_items' => ["Employee {$employee->getField('name', '#' . $employee->id)} is not eligible for this service category."],
                    ]);
                }

                if (config('services.razorpay.employee_payment_enabled') && !$employee->hasValidPayment()) {
                    throw ValidationException::withMessages([
                        'line_items' => ["Employee {$employee->getField('name', '#' . $employee->id)} payment has expired."],
                    ]);
                }

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
                    'employee_id' => $lineItem['employee_id'],
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
            $this->generateContractPDF($contract);

            return $contract->fresh();
        });
    }

    /**
     * Update an existing contract and sync its service lines.
     *
     * Each submitted line may carry an "item_id" pointing at an existing
     * ContractItem. Existing lines are updated in place (preserving their
     * assignment / replacement history), new lines are created, and lines
     * that are no longer present are removed.
     */
    public function updateContract(Contract $contract, array $data): Contract
    {
        return DB::transaction(function () use ($contract, $data) {
            $endDate = $this->calculateEndDate(
                $data['start_date'],
                $data['duration_type'],
                $data['duration_value'] ?? null,
                $data['custom_end_date'] ?? null
            );

            $lineItems = collect($data['line_items'] ?? []);
            $totalAmount = $lineItems->sum(fn ($line) => (float) ($line['amount'] ?? 0));
            $primaryServiceId = (int) ($lineItems->first()['service_id'] ?? $contract->service_id);

            $maxAssignments = max(1, (int) Setting::get('contract_max_assignments', 10));
            $assignmentStart = $data['assignment_start_date'] ?? $data['start_date'];
            $assignmentEnd = $data['assignment_end_date'] ?? null;

            $keptItemIds = [];

            foreach ($lineItems as $lineItem) {
                $serviceId = (int) $lineItem['service_id'];
                $employee = Employee::with('services')->find($lineItem['employee_id']);

                if (!$employee || $employee->status !== 'active') {
                    throw ValidationException::withMessages([
                        'line_items' => ['Only active employees can be assigned to the contract.'],
                    ]);
                }

                if (!$employee->services->pluck('id')->contains($serviceId)) {
                    throw ValidationException::withMessages([
                        'line_items' => ["Employee {$employee->getField('name', '#' . $employee->id)} is not eligible for this service category."],
                    ]);
                }

                if (config('services.razorpay.employee_payment_enabled') && !$employee->hasValidPayment()) {
                    throw ValidationException::withMessages([
                        'line_items' => ["Employee {$employee->getField('name', '#' . $employee->id)} payment has expired."],
                    ]);
                }

                $existingItem = !empty($lineItem['item_id'])
                    ? $contract->items()->whereKey($lineItem['item_id'])->first()
                    : null;

                if ($existingItem) {
                    $existingItem->update([
                        'service_id' => $serviceId,
                        'amount' => $lineItem['amount'],
                    ]);

                    // Update the current active assignment for this line in place.
                    $assignment = $existingItem->assignments()
                        ->where('is_active', true)
                        ->latest('id')
                        ->first();

                    if ($assignment) {
                        $assignment->update([
                            'employee_id' => $lineItem['employee_id'],
                            'service_id' => $serviceId,
                            'line_amount' => $lineItem['amount'],
                            'notes' => $lineItem['note'] ?? $assignment->notes,
                        ]);
                    } else {
                        Assignment::create([
                            'contract_id' => $contract->id,
                            'contract_item_id' => $existingItem->id,
                            'employee_id' => $lineItem['employee_id'],
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
                        'employee_id' => $lineItem['employee_id'],
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
                'terms' => $data['terms'] ?? $contract->terms,
            ]);

            $contract->updateStatus();
            $this->generateContractPDF($contract);

            return $contract->fresh();
        });
    }

    /**
     * Calculate end date based on duration type
     */
    protected function calculateEndDate(string $startDate, string $durationType, ?int $durationValue = null, ?string $customEndDate = null): ?string
    {
        $start = Carbon::parse($startDate);
        $value = $durationValue ?? 1;

        return match ($durationType) {
            'daily' => $start->addDays($value)->format('Y-m-d'),
            'weekly' => $start->addWeeks($value)->format('Y-m-d'),
            'monthly' => $start->addMonths($value)->format('Y-m-d'),
            'yearly' => $start->addYears($value)->format('Y-m-d'),
            'custom' => $customEndDate,
            default => null,
        };
    }

    /**
     * Generate contract PDF
     */
    public function generateContractPDF(Contract $contract): string
    {
        $contract->load([
            'patient',
            'service',
            'items.service',
            'assignments.employee',
            'assignments.service',
            'assignments.contractItem',
            'assignments.replacedAssignment.employee',
            'assignments.assignmentNotes.creator',
        ]);

        $data = [
            'contract' => $contract,
            'document_title' => 'Agreement ' . $contract->contract_number,
            'company_name' => Setting::get('company_name', 'Sri Muruga Home Nursing'),
            'company_address' => Setting::get('company_address', ''),
            'company_phone' => Setting::get('company_phone', ''),
            'company_email' => Setting::get('company_email', ''),
            'company_logo' => Setting::get('company_logo', ''),
            'company_reg_no' => Setting::get('company_reg_no', '851/2015'),
        ];

        $filename = 'contract_' . $contract->contract_number . '.pdf';
        $path = 'contracts/' . $filename;

        // Rendered through mPDF rather than DomPDF: the agreement is set in
        // Tamil, and DomPDF has no OpenType shaping — it drops the Tamil font
        // entirely and falls back to a Latin base font with no Tamil glyphs.
        app(TamilPdfService::class)->renderToFile('pdf.contract-tamil', $data, $path);

        $contract->update(['pdf_path' => $path]);

        return $path;
    }

    /**
     * Close a contract
     */
    public function closeContract(Contract $contract, string $reason): bool
    {
        return $contract->update([
            'status' => 'closed',
            'closure_reason' => $reason,
            'closed_at' => now(),
        ]);
    }

    /**
     * Update contract statuses based on dates
     */
    public function updateContractStatuses(): void
    {
        $contracts = Contract::whereNotIn('status', ['closed'])->get();

        foreach ($contracts as $contract) {
            $contract->updateStatus();
        }
    }

    /**
     * Get contracts expiring soon
     */
    public function getExpiringContracts(): \Illuminate\Database\Eloquent\Collection
    {
        $alertDays = Setting::get('contract_alert_days', 7);
        $alertDate = Carbon::now()->addDays($alertDays);

        return Contract::where('status', '!=', 'closed')
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [Carbon::now(), $alertDate])
            ->with(['patient', 'service'])
            ->get();
    }

    /**
     * Get default contract terms
     */
    protected function getDefaultTerms(): string
    {
        return "1. The service provider agrees to provide nursing care as per the agreed schedule.\n" .
               "2. Payment must be made as per the agreed payment method and schedule.\n" .
               "3. Either party may terminate this contract with 7 days written notice.\n" .
               "4. The service provider maintains professional liability insurance.\n" .
               "5. All personal and medical information will be kept confidential.\n" .
               "6. The client agrees to provide a safe working environment for the nurse.\n" .
               "7. Any changes to the service schedule must be communicated 24 hours in advance.\n" .
               "8. This contract is governed by the laws of the applicable jurisdiction.";
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
