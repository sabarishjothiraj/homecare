<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\PayrollException;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeePayout;
use App\Models\EmployeePayoutItem;
use App\Services\PayrollService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Monthly payroll: generate payslips from salary + attendance, adjust, pay.
 */
class PayoutController extends Controller
{
    public function __construct(private PayrollService $payroll) {}

    /**
     * Payroll for a month
     */
    public function index(Request $request)
    {
        $month = PayrollService::parseMonth($request->input('month')) ?? now()->startOfMonth();

        $query = EmployeePayout::with('employee')
            ->whereDate('period_start', '>=', $month->copy()->startOfMonth()->toDateString())
            ->whereDate('period_end', '<=', $month->copy()->endOfMonth()->toDateString());

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        $payouts = $query->get()->sortBy(fn ($payout) => strtolower($payout->employee?->getField('name', '') ?? ''))->values();

        $active = $payouts->where('status', '!=', 'cancelled');
        $totals = [
            'net' => $active->sum('amount'),
            'paid' => $payouts->where('status', 'paid')->sum('amount'),
            'pending' => $payouts->where('status', 'pending')->sum('amount'),
            'unmarked' => $active->sum(fn ($payout) => (int) ($payout->attendance_summary['unmarked'] ?? 0)),
        ];

        $employees = Employee::orderBy('first_name')->get();
        $employeesWithoutSalary = Employee::active()->whereDoesntHave('salaries')->count();
        $canGenerate = $month->lte(now()->startOfMonth());

        return view('admin.payouts.index', compact('month', 'payouts', 'totals', 'employees', 'employeesWithoutSalary', 'canGenerate'));
    }

    /**
     * Create / refresh draft payslips for a month
     */
    public function generate(Request $request)
    {
        $validated = $request->validate([
            'month' => 'required|date_format:Y-m',
        ]);

        $month = PayrollService::parseMonth($validated['month']);

        // Drafts for the current month are fine (paying one needs an explicit
        // override); a month that hasn't started has nothing to pay
        if ($month->gt(now()->startOfMonth())) {
            return redirect()->route('admin.payouts.index', ['month' => $month->format('Y-m')])
                ->with('error', 'Payroll can\'t be generated for '.$month->format('F Y').' until the month starts.');
        }

        $result = $this->payroll->generateForMonth($month);

        $parts = [];
        if ($result['created']) {
            $parts[] = "{$result['created']} created";
        }
        if ($result['updated']) {
            $parts[] = "{$result['updated']} recalculated";
        }
        if ($result['skipped_paid']) {
            $parts[] = "{$result['skipped_paid']} already paid (unchanged)";
        }

        $message = 'Payroll for '.$month->format('F Y').': '.($parts ? implode(', ', $parts) : 'nothing to generate').'.';

        $redirect = redirect()->route('admin.payouts.index', ['month' => $month->format('Y-m')])->with('success', $message);

        $warnings = [];
        if ($result['without_salary']) {
            $warnings[] = count($result['without_salary']).' active '.str('employee')->plural(count($result['without_salary'])).' skipped with no salary set: '.$this->names($result['without_salary']).'.';
        }
        if ($result['zeroed']) {
            $warnings[] = count($result['zeroed']).' draft '.str('payslip')->plural(count($result['zeroed'])).' recalculated to ₹0 because no salary is set any more: '.$this->names($result['zeroed']).'.';
        }
        if ($result['unmarked']) {
            $warnings[] = "{$result['unmarked']} attendance ".str('day')->plural($result['unmarked']).' are not marked and were not paid. Mark attendance, then recalculate.';
        }

        return $warnings ? $redirect->with('error', implode(' ', $warnings)) : $redirect;
    }

    /**
     * Payslip
     */
    public function show(EmployeePayout $payout)
    {
        $payout->load(['employee.salaries', 'employee.services', 'items']);

        $salaryChanges = $payout->employee
            ? $payout->employee->salaries->filter(fn ($s) => $s->effective_from->between($payout->period_start->copy()->addDay(), $payout->period_end))
            : collect();

        $incomplete = $payout->isEditable() ? $this->payroll->incompleteReasons($payout) : [];

        return view('admin.payouts.show', compact('payout', 'salaryChanges', 'incomplete'));
    }

    public function recalculate(EmployeePayout $payout)
    {
        try {
            $this->payroll->recalculate($payout);
        } catch (PayrollException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.payouts.show', $payout)->with('success', 'Payslip recalculated from current salary and attendance.');
    }

    /**
     * Add an earning or deduction line
     */
    public function storeItem(Request $request, EmployeePayout $payout)
    {
        if (! $payout->isEditable()) {
            return back()->with('error', 'Paid or cancelled payouts cannot be changed.');
        }

        $validated = $request->validate([
            'type' => ['required', Rule::in(['earning', 'deduction'])],
            'label' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01|max:9999999',
        ]);

        try {
            $this->payroll->addItem($payout, $validated);
        } catch (PayrollException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.payouts.show', $payout)->with('success', ucfirst($validated['type']).' added.');
    }

    public function destroyItem(EmployeePayout $payout, EmployeePayoutItem $item)
    {
        abort_unless($item->employee_payout_id === $payout->id, 404);

        try {
            $this->payroll->removeItem($payout, $item);
        } catch (PayrollException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.payouts.show', $payout)->with('success', 'Line removed.');
    }

    public function markPaid(Request $request, EmployeePayout $payout)
    {
        if (! $payout->isEditable()) {
            return back()->with('error', 'Only pending payouts can be marked as paid.');
        }

        $validated = $request->validate([
            'payment_method' => ['required', Rule::in(array_keys(EmployeePayout::PAYMENT_METHODS))],
            'transaction_reference' => 'nullable|string|max:255',
            'paid_at' => 'required|date',
            'paid_notes' => 'nullable|string|max:1000',
            'expected_amount' => 'nullable|numeric',
            'allow_incomplete' => 'nullable|boolean',
        ]);

        try {
            $this->payroll->markPaid($payout, $validated);
        } catch (PayrollException $e) {
            return redirect()->route('admin.payouts.show', $payout)->with('error', $e->getMessage());
        }

        return redirect()->route('admin.payouts.show', $payout)->with('success', 'Payout marked as paid.');
    }

    /**
     * Delete a draft payslip (it can be generated again)
     */
    public function destroy(EmployeePayout $payout)
    {
        try {
            $this->payroll->deleteDraft($payout);
        } catch (PayrollException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.payouts.index', array_filter(['month' => $payout->period_start?->format('Y-m')]))
            ->with('success', 'Draft payslip deleted.');
    }

    /**
     * @param  array<int, string>  $names
     */
    private function names(array $names): string
    {
        return implode(', ', array_slice($names, 0, 5)).(count($names) > 5 ? '…' : '');
    }
}
