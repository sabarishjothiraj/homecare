<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentNote;
use App\Models\Contract;
use App\Models\ContractItem;
use App\Models\Employee;
use App\Models\Patient;
use App\Models\Setting;
use App\Models\Service;
use App\Services\ContractService;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            $query->where(function($q) use ($search) {
                $q->where('contract_number', 'like', "%{$search}%")
                  ->orWhereHas('patient', function($pq) use ($search) {
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
        $employees = Employee::active()->with('services')->orderBy('id')->get();

        return view('admin.contracts.create', compact('patients', 'services', 'employees'));
    }

    /**
     * Store new contract
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'duration_type' => 'required|in:daily,weekly,monthly,yearly,custom',
            'duration_value' => 'required_unless:duration_type,custom|nullable|integer|min:1',
            'start_date' => 'required|date',
            'payment_method' => 'required|in:cash,razorpay,online,cheque,bank_transfer',
            'terms' => 'nullable|string',
            'line_items' => 'required|array|min:1',
            'line_items.*.service_id' => 'required|exists:services,id',
            'line_items.*.employee_id' => 'required|exists:employees,id',
            'line_items.*.amount' => 'required|numeric|min:0',
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
            'patient',
            'service',
            'items.service',
            'creator',
            'assignments.employee',
            'assignments.service',
            'assignments.contractItem',
            'assignments.replacedAssignment.employee',
            'assignments.assignmentNotes.creator',
            'payments',
        ]);

        $employees = Employee::active()->with('services')->orderBy('id')->get();

        return view('admin.contracts.show', compact('contract', 'employees'));
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
                    'message' => 'Cannot edit a closed contract.'
                ], 422);
            }
            return redirect()->back()
                ->with('error', 'Cannot edit a closed contract.');
        }

        $contract->load(['items.assignments.employee']);

        $patients = Patient::active()->orderBy('name')->get();
        $services = Service::active()->ordered()->get();
        $employees = Employee::active()->with('services')->orderBy('id')->get();

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
                    'message' => 'Cannot update a closed contract.'
                ], 422);
            }
            return redirect()->back()
                ->with('error', 'Cannot update a closed contract.');
        }

        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'duration_type' => 'required|in:daily,weekly,monthly,yearly,custom',
            'duration_value' => 'required_unless:duration_type,custom|nullable|integer|min:1',
            'start_date' => 'required|date',
            'payment_method' => 'required|in:cash,razorpay,online,cheque,bank_transfer',
            'terms' => 'nullable|string',
            'line_items' => 'required|array|min:1',
            'line_items.*.item_id' => 'nullable|integer',
            'line_items.*.service_id' => 'required|exists:services,id',
            'line_items.*.employee_id' => 'required|exists:employees,id',
            'line_items.*.amount' => 'required|numeric|min:0',
            'line_items.*.note' => 'nullable|string|max:1000',
            'assignment_start_date' => 'nullable|date',
            'assignment_end_date' => 'nullable|date|after_or_equal:assignment_start_date',
        ]);

        $contract = $this->contractService->updateContract($contract, $validated);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Contract updated successfully.',
                'redirect' => route('admin.contracts.show', $contract)
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
        $contract->load(['patient', 'items.service', 'assignments.employee', 'assignments.service', 'payments']);

        // Razorpay online payments are disabled for now; offline recording only.
        $razorpayEnabled = false;

        return view('admin.contracts.payment', compact('contract', 'razorpayEnabled'));
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
            $this->paymentService->recordManualContractPayment($contract, $validated);
        } catch (ValidationException $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', $e->validator->errors()->first());
        }

        return redirect()->route('admin.contracts.payment', $contract)
            ->with('success', 'Payment of ₹' . number_format($validated['amount'], 2) . ' recorded successfully.');
    }

    /**
     * Create a Razorpay order for an online (partial/full) contract payment
     */
    public function razorpayOrder(Request $request, Contract $contract)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        $result = $this->paymentService->createContractOrder($contract, (float) $validated['amount']);

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
        ]);

        $this->contractService->closeContract($contract, $validated['closure_reason']);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Contract closed successfully.',
                'redirect' => route('admin.contracts.show', $contract)
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
        if (!$contract->pdf_path || !Storage::disk('public')->exists($contract->pdf_path)) {
            return redirect()->back()
                ->with('error', 'Contract PDF not found. Please regenerate it.');
        }

        if (request()->boolean('inline')) {
            return response()->file(storage_path('app/public/' . $contract->pdf_path), [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="Contract-' . $contract->contract_number . '.pdf"',
            ]);
        }

        return Storage::disk('public')->download($contract->pdf_path, "Contract-{$contract->contract_number}.pdf");
    }

    /**
     * View contract PDF in browser
     */
    public function viewPdf(Contract $contract)
    {
        if (!$contract->pdf_path || !Storage::disk('public')->exists($contract->pdf_path)) {
            return redirect()->back()->with('error', 'Contract PDF not found. Please regenerate it.');
        }

        return response()->file(storage_path('app/public/' . $contract->pdf_path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Contract-' . $contract->contract_number . '.pdf"',
        ]);
    }

    /**
     * PDF print helper page
     */
    public function printPdf(Contract $contract)
    {
        if (!$contract->pdf_path || !Storage::disk('public')->exists($contract->pdf_path)) {
            return redirect()->route('admin.contracts.show', $contract)
                ->with('error', 'Contract PDF not found. Please regenerate it.');
        }

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
            if (!$employee || $employee->status !== 'active') {
                return redirect()->back()->with('error', 'Only active employees can be assigned.');
            }

            if (config('services.razorpay.employee_payment_enabled') && !$employee->hasValidPayment()) {
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
        $this->contractService->generateContractPDF($contract);

        return redirect()->route('admin.contracts.show', $contract)->with('success', 'Employees added to contract successfully.');
    }

    /**
     * Remove assigned employee from contract
     */
    public function removeEmployee(Contract $contract, Assignment $assignment)
    {
        if ($assignment->contract_id !== $contract->id) {
            abort(404);
        }

        $assignment->delete();
        $contract->updateStatus();
        $this->contractService->generateContractPDF($contract);

        return redirect()->route('admin.contracts.show', $contract)->with('success', 'Assigned employee removed successfully.');
    }

    /**
     * Replace assigned employee (temporary or permanent)
     */
    public function replaceEmployee(Request $request, Contract $contract, Assignment $assignment)
    {
        if ($assignment->contract_id !== $contract->id) {
            abort(404);
        }

        $assignment->loadMissing('contractItem', 'service');
        $contractItem = $assignment->contractItem;
        if (!$contractItem) {
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

        $replacementEmployee = Employee::with('services')->find($validated['employee_id']);
        if (!$replacementEmployee || $replacementEmployee->status !== 'active') {
            return redirect()->back()->with('error', 'Replacement employee must be active.');
        }

        if (!$replacementEmployee->services->pluck('id')->contains((int) $contractItem->service_id)) {
            return redirect()->back()->with('error', 'Replacement employee does not match this service category.');
        }

        if (config('services.razorpay.employee_payment_enabled') && !$replacementEmployee->hasValidPayment()) {
            return redirect()->back()->with('error', 'Replacement employee payment has expired.');
        }

        $assignment->update([
            'is_active' => false,
            'end_date' => Carbon::parse($validated['replacement_start_date']),
        ]);

        $replacement = Assignment::create([
            'contract_id' => $contract->id,
            'contract_item_id' => $contractItem->id,
            'employee_id' => $validated['employee_id'],
            'service_id' => $contractItem->service_id,
            'line_amount' => $contractItem->amount,
            'start_date' => $validated['replacement_start_date'],
            'end_date' => $validated['replacement_end_date'] ?? null,
            'is_emergency_replacement' => (bool) ($validated['is_temporary'] ?? false),
            'replaced_assignment_id' => $assignment->id,
            'notes' => $validated['notes'] ?? null,
            'is_active' => true,
        ]);

        $contractItem->increment('assignment_count');

        if (!empty($validated['notes'])) {
            AssignmentNote::create([
                'assignment_id' => $replacement->id,
                'note' => $validated['notes'],
                'created_by' => auth()->id(),
            ]);
        }

        $contract->updateStatus();
        $this->contractService->generateContractPDF($contract);

        $message = ($validated['is_temporary'] ?? false)
            ? 'Temporary replacement assigned successfully.'
            : 'Employee replaced successfully.';

        return redirect()->route('admin.contracts.show', $contract)->with('success', $message);
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

        $this->contractService->generateContractPDF($contract);

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
                'reload' => true
            ]);
        }

        return redirect()->back()
            ->with('success', 'Contract PDF regenerated successfully.');
    }
}

