<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateContractPdf;
use App\Models\Assignment;
use App\Models\AssignmentNote;
use App\Models\Contract;
use App\Models\ContractItem;
use App\Models\Employee;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Service;
use App\Services\AssignmentCoverService;
use App\Services\ContractService;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContractController extends Controller
{
    protected $contractService;

    protected $paymentService;

    public function __construct(ContractService $contractService, PaymentService $paymentService)
    {
        $this->contractService = $contractService;
        $this->paymentService = $paymentService;
    }

    /**
     * Display contract listing
     */
    public function index(Request $request)
    {
        $query = Contract::with(['patient', 'service', 'activeAssignments.employee', 'creator']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by patient
        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->patient_id);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('contract_number', 'like', "%{$search}%")
                    ->orWhereHas('patient', function ($pq) use ($search) {
                        $pq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $contracts = $query->latest()->paginate(20);
        $patients = Patient::active()->orderBy('name')->get();

        return view('admin.contracts.index', compact('contracts', 'patients'));
    }

    /**
     * Show create form
     */
    public function create()
    {
        $patients = Patient::active()->orderBy('name')->get();
        $services = Service::active()->ordered()->get();
        // Employees already placed elsewhere would be refused on save
        $employees = Employee::available()->with('services')->orderBy('id')->get();

        return view('admin.contracts.create', compact('patients', 'services', 'employees'));
    }

    /**
     * Store new contract
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => ['required', Rule::exists('patients', 'id')->whereNull('deleted_at')],
            'duration_type' => 'required|in:daily,weekly,monthly,yearly,custom',
            'duration_value' => 'required_unless:duration_type,custom|nullable|integer|min:1',
            'start_date' => 'required|date',
            'payment_method' => 'required|in:cash,razorpay,online,cheque,bank_transfer',
            'payment_details' => 'required_if:payment_method,cheque,bank_transfer|nullable|string|max:500',
            'custom_end_date' => 'required_if:duration_type,custom|nullable|date|after_or_equal:start_date',
            'terms' => 'nullable|string',
            'line_items' => 'required|array|min:1',
            'line_items.*.service_id' => 'required|exists:services,id',
            'line_items.*.employee_id' => 'required|exists:employees,id',
            'line_items.*.amount' => 'required|numeric|min:0|max:'.Contract::MAX_AMOUNT,
            'line_items.*.note' => 'nullable|string|max:1000',
            'assignment_start_date' => 'nullable|date',
            'assignment_end_date' => 'nullable|date|after_or_equal:assignment_start_date',
            'assignment_note' => 'nullable|string|max:2000',
        ]);

        $contract = $this->contractService->createContract($validated);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Contract created and employees assigned. Continue to payment.',
                'redirect' => route('admin.contracts.payment', $contract),
            ]);
        }

        return redirect()->route('admin.contracts.payment', $contract)
            ->with('success', 'Contract created and employees assigned. Continue to payment.');
    }

    /**
     * Show contract details
     */
    public function show(Contract $contract)
    {
        $contract->load([
            // A patient may be deleted once their contracts have ended
            'patient' => fn ($query) => $query->withTrashed(),
            'service',
            'items.service',
            'creator',
            'assignments.employee',
            'assignments.service',
            'assignments.contractItem',
            'assignments.replacedAssignment.employee',
            'assignments.coverFor.employee',
            'assignments.covers.employee',
            'assignments.replacements.employee',
            'assignments.assignmentNotes.creator',
            'payments',
        ]);

        $employees = Employee::active()->with('services')->orderBy('id')->get();

        // Employees already placed on an active assignment (any contract) can't be picked as replacements
        $assignedEmployeeIds = Assignment::where('is_active', true)
            ->distinct()
            ->pluck('employee_id')
            ->all();

        return view('admin.contracts.show', compact('contract', 'employees', 'assignedEmployeeIds'));
    }

    /**
     * Show edit form
     */
    public function edit(Contract $contract)
    {
        if ($contract->status === 'closed') {
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot edit a closed contract.',
                ], 422);
            }

            return redirect()->back()
                ->with('error', 'Cannot edit a closed contract.');
        }

        $contract->load(['items.assignments.employee']);

        // The contract's own patient stays selectable even if since deleted
        $patients = Patient::withTrashed()
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query->active()->whereNull('deleted_at'))
                ->orWhere('id', $contract->patient_id))
            ->orderBy('name')
            ->get();
        $services = Service::active()->ordered()->get();

        // Free employees, plus whoever already works this contract's lines
        $currentEmployeeIds = $contract->assignments()->primary()->where('is_active', true)->pluck('employee_id');
        $employees = Employee::with('services')
            ->where(fn ($query) => $query->available()->orWhereIn('id', $currentEmployeeIds))
            ->orderBy('id')
            ->get();

        return view('admin.contracts.edit', compact('contract', 'patients', 'services', 'employees'));
    }

    /**
     * Update contract
     */
    public function update(Request $request, Contract $contract)
    {
        if ($contract->status === 'closed') {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update a closed contract.',
                ], 422);
            }

            return redirect()->back()
                ->with('error', 'Cannot update a closed contract.');
        }

        $validated = $request->validate([
            // A deleted patient cannot be given a contract, but the one already on it may stay
            'patient_id' => [
                'required',
                Rule::exists('patients', 'id')->where(fn ($query) => $query->whereNull('deleted_at')->orWhere('id', $contract->patient_id)),
            ],
            'duration_type' => 'required|in:daily,weekly,monthly,yearly,custom',
            'duration_value' => 'required_unless:duration_type,custom|nullable|integer|min:1',
            'start_date' => 'required|date',
            'payment_method' => 'required|in:cash,razorpay,online,cheque,bank_transfer',
            'payment_details' => 'required_if:payment_method,cheque,bank_transfer|nullable|string|max:500',
            'custom_end_date' => 'required_if:duration_type,custom|nullable|date|after_or_equal:start_date',
            'terms' => 'nullable|string',
            'line_items' => 'required|array|min:1',
            'line_items.*.item_id' => 'nullable|integer',
            'line_items.*.service_id' => 'required|exists:services,id',
            'line_items.*.employee_id' => 'required|exists:employees,id',
            'line_items.*.amount' => 'required|numeric|min:0|max:'.Contract::MAX_AMOUNT,
            'line_items.*.note' => 'nullable|string|max:1000',
            'assignment_start_date' => 'nullable|date',
            'assignment_end_date' => 'nullable|date|after_or_equal:assignment_start_date',
        ]);

        $contract = $this->contractService->updateContract($contract, $validated);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Contract updated successfully.',
                'redirect' => route('admin.contracts.show', $contract),
            ]);
        }

        return redirect()->route('admin.contracts.show', $contract)
            ->with('success', 'Contract updated successfully.');
    }

    /**
     * Show the payment / collection page for a contract
     */
    public function payment(Contract $contract)
    {
        $contract->load([
            'patient' => fn ($query) => $query->withTrashed(),
            'items.service',
            'assignments.employee',
            'assignments.service',
            'payments',
        ]);

        // Razorpay online payments are disabled for now; offline recording only.
        $razorpayEnabled = false;

        return view('admin.contracts.payment', compact('contract', 'razorpayEnabled'));
    }

    /**
     * Refuse collection on a closed contract.
     *
     * Call inside a transaction: the row lock holds off a concurrent close
     * until the payment has been written (closeContract takes the same lock).
     */
    protected function assertCollectable(Contract $contract): void
    {
        $status = Contract::whereKey($contract->getKey())->lockForUpdate()->value('status');

        if ($status === 'closed') {
            throw ValidationException::withMessages([
                'amount' => ['This contract is closed. No further payments can be collected.'],
            ]);
        }
    }

    /**
     * Record an offline (manual) payment against a contract
     */
    public function storePayment(Request $request, Contract $contract)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|in:cash,cheque,bank_transfer,online',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'paid_at' => 'nullable|date',
        ]);

        try {
            DB::transaction(function () use ($contract, $validated) {
                $this->assertCollectable($contract);
                $this->paymentService->recordManualContractPayment($contract, $validated);
            });
        } catch (ValidationException $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', $e->validator->errors()->first());
        }

        return redirect()->route('admin.contracts.payment', $contract)
            ->with('success', 'Payment of ₹'.number_format($validated['amount'], 2).' recorded successfully.');
    }

    /**
     * Create a Razorpay order for an online (partial/full) contract payment
     */
    public function razorpayOrder(Request $request, Contract $contract)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        try {
            $result = DB::transaction(function () use ($contract, $validated) {
                $this->assertCollectable($contract);

                return $this->paymentService->createContractOrder($contract, (float) $validated['amount']);
            });
        } catch (ValidationException $e) {
            $result = ['success' => false, 'error' => $e->validator->errors()->first()];
        }

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Verify a completed Razorpay online payment
     */
    public function razorpayVerify(Request $request, Contract $contract)
    {
        $validated = $request->validate([
            'razorpay_order_id' => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        // The order must belong to the contract in the URL, or a payment for
        // one contract could be confirmed from another contract's page.
        $belongsToContract = Payment::where('razorpay_order_id', $validated['razorpay_order_id'])
            ->where('contract_id', $contract->id)
            ->exists();

        if (! $belongsToContract) {
            return response()->json([
                'success' => false,
                'message' => 'This payment does not belong to this contract.',
            ], 422);
        }

        $success = $this->paymentService->processSuccessfulPayment(
            $validated['razorpay_order_id'],
            $validated['razorpay_payment_id'],
            $validated['razorpay_signature']
        );

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Payment verified successfully.' : 'Payment verification failed.',
            'redirect' => route('admin.contracts.payment', $contract),
        ], $success ? 200 : 422);
    }

    /**
     * Close contract
     */
    public function close(Request $request, Contract $contract)
    {
        $validated = $request->validate([
            'closure_reason' => 'required|string|max:1000',
            'refund_amount' => 'nullable|numeric|min:0|max:99999999.99',
            'refund_details' => 'nullable|string|max:1000',
        ]);

        try {
            $this->contractService->closeContract($contract, $validated['closure_reason'], [
                'refund_amount' => $validated['refund_amount'] ?? null,
                'refund_details' => $validated['refund_details'] ?? null,
            ]);
        } catch (ValidationException $e) {
            if ($request->wantsJson() || $request->ajax()) {
                throw $e;
            }

            // The close form lives in a modal, so surface the reason as a flash
            return redirect()->back()->withInput()->with('error', collect($e->errors())->flatten()->first());
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Contract closed successfully.',
                'redirect' => route('admin.contracts.show', $contract),
            ]);
        }

        return redirect()->route('admin.contracts.show', $contract)
            ->with('success', 'Contract closed successfully.');
    }

    /**
     * Download contract PDF
     */
    public function download(Contract $contract)
    {
        // Renders on demand if the queued job has not caught up yet, so a
        // freshly changed contract is never a dead link or a stale document.
        $pdfPath = $this->contractService->ensurePdf($contract);
        $disk = Storage::disk(ContractService::PDF_DISK);

        if (request()->boolean('inline')) {
            return response()->file($disk->path($pdfPath), [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="Contract-'.$contract->contract_number.'.pdf"',
            ]);
        }

        return $disk->download($pdfPath, "Contract-{$contract->contract_number}.pdf");
    }

    /**
     * View contract PDF in browser
     */
    public function viewPdf(Contract $contract)
    {
        // Renders on demand if the queued job has not caught up yet, so a
        // freshly changed contract is never a dead link or a stale document.
        $pdfPath = $this->contractService->ensurePdf($contract);

        return response()->file(Storage::disk(ContractService::PDF_DISK)->path($pdfPath), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Contract-'.$contract->contract_number.'.pdf"',
        ]);
    }

    /**
     * PDF print helper page
     */
    public function printPdf(Contract $contract)
    {
        // Renders on demand if the queued job has not caught up yet, so a
        // freshly changed contract is never a dead link or a stale document.
        $this->contractService->ensurePdf($contract);

        $pdfUrl = route('admin.contracts.view-pdf', $contract);

        return view('admin.contracts.print', compact('contract', 'pdfUrl'));
    }

    /**
     * Add employees to contract
     */
    public function addEmployees(Request $request, Contract $contract)
    {
        if ($contract->items()->exists()) {
            return redirect()->back()->with('error', 'Add employee is managed per service line only.');
        }

        if (in_array($contract->status, ['closed', 'expired'])) {
            return redirect()->back()->with('error', 'Cannot modify employees for this contract status.');
        }

        $validated = $request->validate([
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'required|exists:employees,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'notes' => 'nullable|string|max:2000',
        ]);

        $existingEmployeeIds = $contract->assignments()->active()->pluck('employee_id')->all();
        foreach (collect($validated['employee_ids'])->unique() as $employeeId) {
            if (in_array((int) $employeeId, $existingEmployeeIds, true)) {
                continue;
            }

            $employee = Employee::find($employeeId);
            if (! $employee || $employee->status !== 'active') {
                return redirect()->back()->with('error', 'Only active employees can be assigned.');
            }

            if (config('services.razorpay.employee_payment_enabled') && ! $employee->hasValidPayment()) {
                return redirect()->back()->with('error', 'Employee payment has expired. Please renew before assigning.');
            }

            Assignment::create([
                'contract_id' => $contract->id,
                'employee_id' => $employeeId,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'] ?? null,
                'is_emergency_replacement' => false,
                'replaced_assignment_id' => null,
                'notes' => $validated['notes'] ?? null,
                'is_active' => true,
            ]);
        }

        $contract->updateStatus();
        GenerateContractPdf::dispatch($contract);

        return redirect()->route('admin.contracts.show', $contract)->with('success', 'Employees added to contract successfully.');
    }

    /**
     * Remove assigned employee from contract
     */
    public function removeEmployee(Contract $contract, Assignment $assignment, AssignmentCoverService $covers)
    {
        if ($assignment->contract_id !== $contract->id) {
            abort(404);
        }

        // Nobody left to cover for
        $covers->endCoversFor($assignment);

        $assignment->delete();
        $contract->updateStatus();
        GenerateContractPdf::dispatch($contract);

        return redirect()->route('admin.contracts.show', $contract)->with('success', 'Assigned employee removed successfully.');
    }

    /**
     * Replace assigned employee (temporary or permanent)
     */
    public function replaceEmployee(Request $request, Contract $contract, Assignment $assignment, AssignmentCoverService $covers)
    {
        if ($assignment->contract_id !== $contract->id) {
            abort(404);
        }

        if (in_array($contract->status, ['closed', 'expired'], true)) {
            return redirect()->back()->with('error', 'Cannot change employees on a closed or expired contract.');
        }

        if ($assignment->isCover()) {
            return redirect()->back()->with('error', 'A temporary cover cannot be replaced. End the cover instead.');
        }

        // Only the line's current employee can hand over. Replacing a row that
        // was already replaced left two employees active on one line.
        if (! $assignment->is_active) {
            return redirect()->back()->with('error', 'Only the current employee on a service line can be replaced.');
        }

        $assignment->loadMissing('contractItem', 'service');
        $contractItem = $assignment->contractItem;
        if (! $contractItem) {
            return redirect()->back()->with('error', 'This assignment is not linked to a service line.');
        }

        if ($contractItem->assignment_count >= $contractItem->max_assignments) {
            return redirect()->back()->with('error', 'Maximum employee changes reached for this service line.');
        }

        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'replacement_start_date' => 'required|date',
            'replacement_end_date' => 'nullable|date|after_or_equal:replacement_start_date',
            'is_temporary' => 'nullable|boolean',
            'notes' => 'nullable|string|max:2000',
        ]);

        if ((int) $validated['employee_id'] === (int) $assignment->employee_id) {
            return redirect()->back()->with('error', 'Replacement employee must be different from the current employee.');
        }

        $replacementEmployee = Employee::with('services')->find($validated['employee_id']);
        if (! $replacementEmployee || $replacementEmployee->status !== 'active') {
            return redirect()->back()->with('error', 'Replacement employee must be active.');
        }

        if (! $replacementEmployee->services->pluck('id')->contains((int) $contractItem->service_id)) {
            return redirect()->back()->with('error', 'Replacement employee does not match this service category.');
        }

        if (config('services.razorpay.employee_payment_enabled') && ! $replacementEmployee->hasValidPayment()) {
            return redirect()->back()->with('error', 'Replacement employee payment has expired.');
        }

        try {
            DB::transaction(function () use ($contract, $assignment, $contractItem, $validated, $covers) {
                // Re-checked under row locks (contract, line, outgoing
                // assignment, incoming employee) so a concurrent close, a
                // double submit, or the same employee picked for two lines
                // cannot slip past the checks.
                $status = Contract::whereKey($contract->id)->lockForUpdate()->value('status');
                $line = ContractItem::whereKey($contractItem->id)->lockForUpdate()->firstOrFail();
                $current = Assignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();
                Employee::whereKey($validated['employee_id'])->lockForUpdate()->first();

                if (in_array($status, ['closed', 'expired'], true)) {
                    throw ValidationException::withMessages(['employee_id' => 'Cannot change employees on a closed or expired contract.']);
                }

                if (! $current->is_active) {
                    throw ValidationException::withMessages(['employee_id' => 'This assignment has already been replaced.']);
                }

                if ($line->assignment_count >= $line->max_assignments) {
                    throw ValidationException::withMessages(['employee_id' => 'Maximum employee changes reached for this service line.']);
                }

                // The employee currently covering this assignment may be made permanent
                $alreadyAssigned = Assignment::where('is_active', true)
                    ->where('employee_id', $validated['employee_id'])
                    ->where(fn ($q) => $q->whereNull('cover_for_assignment_id')->orWhere('cover_for_assignment_id', '!=', $current->id))
                    ->exists();
                if ($alreadyAssigned) {
                    throw ValidationException::withMessages(['employee_id' => 'This employee is already assigned to an active contract.']);
                }

                // The outgoing employee's leave covers end with their assignment
                $covers->endCoversFor($current);

                $current->update([
                    'is_active' => false,
                    'end_date' => Carbon::parse($validated['replacement_start_date']),
                ]);

                $replacement = Assignment::create([
                    'contract_id' => $contract->id,
                    'contract_item_id' => $line->id,
                    'employee_id' => $validated['employee_id'],
                    'service_id' => $line->service_id,
                    'line_amount' => $line->amount,
                    'start_date' => $validated['replacement_start_date'],
                    'end_date' => $validated['replacement_end_date'] ?? null,
                    'is_emergency_replacement' => (bool) ($validated['is_temporary'] ?? false),
                    'replaced_assignment_id' => $current->id,
                    'notes' => $validated['notes'] ?? null,
                    'is_active' => true,
                ]);

                $line->increment('assignment_count');

                if (! empty($validated['notes'])) {
                    AssignmentNote::create([
                        'assignment_id' => $replacement->id,
                        'note' => $validated['notes'],
                        'created_by' => auth()->id(),
                    ]);
                }
            });
        } catch (ValidationException $e) {
            return redirect()->back()->with('error', collect($e->errors())->flatten()->first());
        }

        $contract->updateStatus();
        GenerateContractPdf::dispatch($contract);

        $message = ($validated['is_temporary'] ?? false)
            ? 'Temporary replacement assigned successfully.'
            : 'Employee replaced successfully.';

        return redirect()->route('admin.contracts.show', $contract)->with('success', $message);
    }

    /**
     * Arrange a temporary cover while the assigned employee is on leave
     */
    public function assignCover(Request $request, Contract $contract, Assignment $assignment, AssignmentCoverService $covers)
    {
        if ($assignment->contract_id !== $contract->id) {
            abort(404);
        }

        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'cover_start_date' => 'required|date',
            'cover_end_date' => 'required|date|after_or_equal:cover_start_date',
            'notes' => 'nullable|string|max:2000',
        ]);

        try {
            $cover = $covers->assignCover($assignment, [
                'employee_id' => $validated['employee_id'],
                'start_date' => $validated['cover_start_date'],
                'end_date' => $validated['cover_end_date'],
                'notes' => $validated['notes'] ?? null,
            ]);
        } catch (ValidationException $e) {
            return redirect()->back()->withInput()->with('error', collect($e->errors())->flatten()->first());
        }

        $contract->updateStatus();
        GenerateContractPdf::dispatch($contract);

        return redirect()->route('admin.contracts.show', $contract)->with(
            'success',
            'Temporary cover assigned from '.$cover->start_date->format('M d, Y').' to '.$cover->end_date->format('M d, Y').'.'
        );
    }

    /**
     * End a temporary cover early (or cancel one that hasn't started)
     */
    public function endCover(Contract $contract, Assignment $assignment, AssignmentCoverService $covers)
    {
        if ($assignment->contract_id !== $contract->id) {
            abort(404);
        }

        $wasUpcoming = $assignment->start_date->isFuture();

        try {
            $covers->endCover($assignment);
        } catch (ValidationException $e) {
            return redirect()->back()->with('error', collect($e->errors())->flatten()->first());
        }

        $contract->updateStatus();
        GenerateContractPdf::dispatch($contract);

        return redirect()->route('admin.contracts.show', $contract)
            ->with('success', $wasUpcoming ? 'Scheduled cover cancelled.' : 'Temporary cover ended.');
    }

    /**
     * Add note for assigned employee in contract
     */
    public function addEmployeeNote(Request $request, Contract $contract, Assignment $assignment)
    {
        if ($assignment->contract_id !== $contract->id) {
            abort(404);
        }

        $validated = $request->validate([
            'note' => 'required|string|max:2000',
        ]);

        AssignmentNote::create([
            'assignment_id' => $assignment->id,
            'note' => $validated['note'],
            'created_by' => auth()->id(),
        ]);

        GenerateContractPdf::dispatch($contract);

        return redirect()->route('admin.contracts.show', $contract)->with('success', 'Employee note added successfully.');
    }

    /**
     * Regenerate contract PDF
     */
    public function regeneratePdf(Contract $contract)
    {
        $this->contractService->generateContractPDF($contract);

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Contract PDF regenerated successfully.',
                'reload' => true,
            ]);
        }

        return redirect()->back()
            ->with('success', 'Contract PDF regenerated successfully.');
    }
}
