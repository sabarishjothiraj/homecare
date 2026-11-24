<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\EmployeePayout;
use App\Models\EmployeeSalary;
use App\Models\Patient;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use InvalidArgumentException;

/**
 * Admin reports.
 *
 * Every report row is an associative array whose keys are the column
 * headings, so one table partial and one CSV writer serve them all. Money
 * columns are plain numbers with "(₹)" in the heading: the screen formats
 * them, and the CSV stays summable in a spreadsheet.
 *
 * Each report is a filtered query plus a row mapper. The screen pages through
 * the query and the CSV streams it in chunks, while summary cards and footer
 * totals are aggregate queries over the whole filtered set. No request loads
 * a whole table into memory.
 */
class ReportService
{
    public const CONTRACT_STATUSES = [
        'active' => 'Active',
        'not_assigned' => 'Not Assigned',
        'closing_soon' => 'Closing Soon',
        'expired' => 'Expired',
        'closed' => 'Closed',
    ];

    public const PAYMENT_METHODS = [
        'cash' => 'Cash',
        'razorpay' => 'Razorpay',
        'online' => 'Online',
        'cheque' => 'Cheque',
        'bank_transfer' => 'Bank Transfer',
    ];

    /**
     * Columns drawn from another module's data, with the permissions guarding
     * that module. ReportController drops them, and summary cards built from
     * them, for viewers who hold none of those permissions.
     */
    public const RESTRICTED_COLUMNS = [
        'employees' => [
            ['permissions' => ['payouts.view', 'payouts.manage'], 'columns' => ['Payouts Paid (₹)', 'Payouts Pending (₹)']],
        ],
    ];

    private const CHUNK_SIZE = 500;

    /**
     * Aggregates already computed this request, keyed by report and filters
     */
    private array $aggregateCache = [];

    /**
     * Every row of a report, fetched in chunks as it is iterated
     */
    public function rows(string $report, array $filters = []): LazyCollection
    {
        return $this->query($report, $filters)
            ->lazy(self::CHUNK_SIZE)
            ->map(fn (Model $model) => $this->row($report, $model));
    }

    /**
     * One page of a report for the screen
     */
    public function paginate(string $report, array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        return $this->query($report, $filters)
            ->paginate($perPage)
            ->through(fn (Model $model) => $this->row($report, $model));
    }

    public function getPatientReport(array $filters = []): Collection
    {
        return $this->rows('patients', $filters)->collect();
    }

    public function getEmployeeReport(array $filters = []): Collection
    {
        return $this->rows('employees', $filters)->collect();
    }

    public function getContractReport(array $filters = []): Collection
    {
        return $this->rows('contracts', $filters)->collect();
    }

    public function getAssignmentReport(array $filters = []): Collection
    {
        return $this->rows('assignments', $filters)->collect();
    }

    public function getRevenueReport(array $filters = []): Collection
    {
        return $this->rows('revenue', $filters)->collect();
    }

    public function getPayoutReport(array $filters = []): Collection
    {
        return $this->rows('payouts', $filters)->collect();
    }

    /**
     * Filtered, eager-loaded and ordered query behind a report
     *
     * Every order ends on the primary key so chunks and pages never skip or
     * repeat rows that tie on the main sort column.
     */
    private function query(string $report, array $filters): Builder
    {
        return match ($report) {
            'patients' => $this->patientQuery($filters)->with([
                'creator',
                'contracts' => fn ($q) => $q->withSum(['payments as paid_total' => fn ($p) => $p->where('status', 'completed')], 'amount'),
                'contracts.activeAssignments' => fn ($q) => $q->whereNull('cover_for_assignment_id'),
                'contracts.activeAssignments.employee',
            ])->orderBy('name')->orderBy('id'),

            'employees' => $this->employeeQuery($filters)->with([
                'services',
                'payouts',
                'assignments.contract.patient',
            ])->orderBy('first_name')->orderBy('id'),

            'contracts' => $this->contractQuery($filters)->with([
                'patient',
                'service',
                'items.service',
                'creator',
                'activeAssignments.employee',
            ])->withSum(['payments as paid_total' => fn ($q) => $q->where('status', 'completed')], 'amount')
                ->orderByDesc('start_date')->orderByDesc('id'),

            'assignments' => $this->assignmentQuery($filters)->with([
                'contract.patient',
                'contract.service',
                'contractItem',
                'employee',
                'service',
                'replacedAssignment.employee',
                'replacements.employee',
                'coverFor.employee',
            ])->orderBy('contract_id')->orderBy('start_date')->orderBy('id'),

            'revenue' => $this->paymentQuery($filters)
                ->with(['contract.patient', 'contract.service', 'contract.items.service', 'employee'])
                ->orderByDesc('paid_at')->orderByDesc('id'),

            'payouts' => $this->payoutQuery($filters)
                ->with(['employee', 'contract.patient'])
                ->orderByDesc('period_start')->orderByDesc('id'),

            default => throw new InvalidArgumentException("Unknown report [{$report}]."),
        };
    }

    private function row(string $report, Model $model): array
    {
        return match ($report) {
            'patients' => $this->patientRow($model),
            'employees' => $this->employeeRow($model),
            'contracts' => $this->contractRow($model),
            'assignments' => $this->assignmentRow($model),
            'revenue' => $this->revenueRow($model),
            'payouts' => $this->payoutRow($model),
        };
    }

    /**
     * Patient details with their contract money and current staff
     */
    private function patientQuery(array $filters): Builder
    {
        $query = Patient::query();

        $this->dateRange($query, 'created_at', $filters);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        if (! empty($filters['city'])) {
            $query->where('city', 'like', '%'.$filters['city'].'%');
        }

        if (($filters['status'] ?? '') === 'active') {
            $query->where('is_active', true);
        } elseif (($filters['status'] ?? '') === 'inactive') {
            $query->where('is_active', false);
        }

        return $query;
    }

    private function patientRow(Patient $patient): array
    {
        $contracts = $patient->contracts;
        $openContracts = $contracts->whereIn('status', ['active', 'not_assigned', 'closing_soon']);

        return [
            'ID' => $patient->id,
            'Name' => $patient->name,
            'Phone' => $patient->phone,
            'Email' => $patient->email,
            'Address' => $patient->full_address,
            'Area' => $patient->area,
            'City' => $patient->city,
            'Pincode' => $patient->pincode,
            'Status' => $patient->is_active ? 'Active' : 'Inactive',
            'Emergency Contact' => trim(($patient->emergency_contact_name ?? '').' '.($patient->emergency_contact_phone ? '('.$patient->emergency_contact_phone.')' : '')),
            'Total Contracts' => $contracts->count(),
            'Open Contracts' => $openContracts->count(),
            'Current Employees' => $openContracts->flatMap->activeAssignments->map->employeeName()->unique()->join(', '),
            'Contract Value (₹)' => $this->money($contracts->sum('amount')),
            'Paid (₹)' => $this->money($contracts->sum('paid_total')),
            // Owed per contract: money paid over one contract does not pay off another
            'Balance (₹)' => $this->money($contracts->sum(fn (Contract $contract) => $this->contractBalance($contract))),
            'Refunds (₹)' => $this->money($contracts->sum('refund_amount')),
            'Created By' => $patient->creator->name ?? 'System',
            'Registered On' => $patient->created_at->format('Y-m-d'),
        ];
    }

    /**
     * Employee details with skills, current placement and payouts
     */
    private function employeeQuery(array $filters): Builder
    {
        $query = Employee::query();

        $this->dateRange($query, 'created_at', $filters);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($q) => $q->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        if (! empty($filters['service_id'])) {
            $query->whereHas('services', fn ($q) => $q->where('services.id', $filters['service_id']));
        }

        if (($filters['availability'] ?? '') === 'available') {
            $query->available();
        } elseif (($filters['availability'] ?? '') === 'placed') {
            $query->placed();
        }

        return $query;
    }

    private function employeeRow(Employee $employee): array
    {
        $assignments = $employee->assignments;
        $active = $assignments->where('is_active', true);
        $payouts = $employee->payouts;

        if ($active->isNotEmpty()) {
            $availability = $active->map(fn (Assignment $a) => ($a->isCover() ? 'Covering: ' : 'Assigned: ').($a->contract->patient->name ?? 'Contract #'.$a->contract_id))->join('; ');
        } else {
            $availability = $employee->status === 'active' ? 'Available' : '';
        }

        return [
            'ID' => $employee->id,
            'Name' => $employee->getField('name', ''),
            'Phone' => $employee->getField('phone'),
            'Email' => $employee->getField('email'),
            'Status' => ucfirst($employee->status),
            'Services' => $employee->services->pluck('name')->join(', '),
            'Specialization' => $employee->getField('specialization'),
            'Availability' => $availability,
            'Total Assignments' => $assignments->count(),
            'Active Assignments' => $active->count(),
            'Temporary Covers' => $assignments->filter->isCover()->count(),
            'Payouts Paid (₹)' => $this->money($payouts->where('status', 'paid')->sum('amount')),
            'Payouts Pending (₹)' => $this->money($payouts->where('status', 'pending')->sum('amount')),
            'Activated On' => $employee->activated_at?->format('Y-m-d'),
            'Payment Valid Until' => $employee->payment_valid_until?->format('Y-m-d'),
            'Joined On' => $employee->created_at->format('Y-m-d'),
        ];
    }

    /**
     * Contracts with value, collection, balance, payment details, refund and closure
     */
    private function contractQuery(array $filters): Builder
    {
        $query = Contract::query();

        $this->dateRange($query, 'start_date', $filters);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($q) => $q->where('contract_number', 'like', "%{$search}%")
                ->orWhereHas('patient', fn ($p) => $p->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")));
        }

        if (! empty($filters['status'])) {
            $query->where('contracts.status', $filters['status']);
        }

        if (! empty($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        if (! empty($filters['service_id'])) {
            $serviceId = $filters['service_id'];
            $query->where(fn ($q) => $q->where('service_id', $serviceId)
                ->orWhereHas('items', fn ($i) => $i->where('service_id', $serviceId)));
        }

        if (! empty($filters['payment_status'])) {
            $this->wherePaymentStatus($query, $filters['payment_status']);
        }

        return $query;
    }

    /**
     * Filter on the payment status contractRow() derives, in SQL so the
     * report can be paged
     */
    private function wherePaymentStatus(Builder $query, string $status): void
    {
        if ($status === 'settled') {
            $query->where('contracts.status', 'closed');

            return;
        }

        $query->where('contracts.status', '!=', 'closed');

        match ($status) {
            'unpaid' => $query->where($this->paidSubquery(), '<=', 0),
            'partial' => $query->where($this->paidSubquery(), '>', 0)
                ->where($this->paidSubquery(), '<', DB::raw('contracts.amount')),
            'paid' => $query->where($this->paidSubquery(), '>', 0)
                ->where($this->paidSubquery(), '>=', DB::raw('contracts.amount')),
            default => $query->whereRaw('1 = 0'),
        };
    }

    private function contractRow(Contract $contract): array
    {
        $paid = (float) $contract->paid_total;
        $balance = $this->contractBalance($contract);

        // Closing a contract settles it: the unused part is refunded, not owed
        if ($contract->status === 'closed') {
            $paymentStatus = 'Settled';
        } else {
            $paymentStatus = $paid <= 0 ? 'Unpaid' : ($balance <= 0 ? 'Paid' : 'Partial');
        }

        $services = $contract->items->pluck('service.name')->filter()->unique();
        $employees = $contract->activeAssignments->map(fn (Assignment $a) => $a->employeeName().($a->isCover() ? ' (cover)' : ''));

        return [
            'Contract Number' => $contract->contract_number,
            'Patient' => $contract->patient->name ?? 'N/A',
            'Patient Phone' => $contract->patient->phone ?? '',
            'Services' => $services->isNotEmpty() ? $services->join(', ') : ($contract->service->name ?? ''),
            'Assigned Employees' => $employees->join(', '),
            'Start Date' => $contract->start_date->format('Y-m-d'),
            'End Date' => $contract->end_date?->format('Y-m-d') ?? 'Ongoing',
            'Duration' => $contract->duration_type === 'custom' ? 'Custom' : trim($contract->duration_value.' '.ucfirst($contract->duration_type)),
            'Status' => self::CONTRACT_STATUSES[$contract->status] ?? ucfirst($contract->status),
            'Contract Value (₹)' => $this->money($contract->amount),
            'Paid (₹)' => $this->money($paid),
            'Balance (₹)' => $this->money($balance),
            'Payment Status' => $paymentStatus,
            'Payment Method' => self::PAYMENT_METHODS[$contract->payment_method] ?? ucfirst((string) $contract->payment_method),
            'Payment Details' => $contract->payment_details,
            'Refund (₹)' => $contract->refund_amount !== null ? $this->money($contract->refund_amount) : null,
            'Refund Details' => $contract->refund_details,
            'Closure Reason' => $contract->closure_reason,
            'Closed On' => $contract->closed_at?->format('Y-m-d'),
            'Created By' => $contract->creator->name ?? 'System',
            'Created On' => $contract->created_at->format('Y-m-d'),
        ];
    }

    /**
     * Still owed on a contract (needs paid_total loaded); nothing once closed
     */
    private function contractBalance(Contract $contract): float
    {
        if ($contract->status === 'closed') {
            return 0.0;
        }

        return max(0, (float) $contract->amount - (float) $contract->paid_total);
    }

    /**
     * Patient-employee assignments with replacement / cover history
     */
    private function assignmentQuery(array $filters): Builder
    {
        $query = Assignment::query();

        if (! empty($filters['type']) && array_key_exists($filters['type'], Assignment::TYPES)) {
            $query->ofType($filters['type']);
        }

        if (($filters['status'] ?? '') === 'active') {
            $query->where('is_active', true);
        } elseif (($filters['status'] ?? '') === 'ended') {
            $query->where('is_active', false);
        }

        if (! empty($filters['start_date'])) {
            $query->whereDate('start_date', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate('end_date', '<=', $filters['end_date']);
        }

        if (! empty($filters['patient_id'])) {
            $query->whereHas('contract', fn ($q) => $q->where('patient_id', $filters['patient_id']));
        }

        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        return $query;
    }

    private function assignmentRow(Assignment $assignment): array
    {
        $successor = $assignment->replacedBy();
        $until = $assignment->end_date ?? now();
        $days = $assignment->start_date->lte($until) ? (int) $assignment->start_date->diffInDays($until) + 1 : 0;

        return [
            'Assignment ID' => $assignment->id,
            'Contract Number' => $assignment->contract->contract_number ?? '',
            'Contract Status' => self::CONTRACT_STATUSES[$assignment->contract->status ?? ''] ?? '',
            'Patient Name' => $assignment->contract->patient->name ?? '',
            'Service' => $assignment->service->name ?? ($assignment->contract->service->name ?? 'N/A'),
            'Employee Name' => $assignment->employeeName(),
            'Employee Phone' => $assignment->employee?->getField('phone'),
            'Start Date' => $assignment->start_date->format('Y-m-d'),
            'End Date' => $assignment->end_date ? $assignment->end_date->format('Y-m-d') : 'Ongoing',
            'Days' => $days,
            'Line Amount (₹)' => $assignment->isCover() ? null : $this->money($assignment->line_amount ?? $assignment->contractItem->amount ?? 0),
            'Type' => $assignment->typeLabel(),
            'Replaced / Covering For' => $assignment->isCover()
                ? $assignment->coverFor?->employeeName()
                : $assignment->replacedAssignment?->employeeName(),
            'Replaced By' => $successor?->employeeName(),
            'Replacement Note' => $successor?->notes,
            'Status' => $assignment->is_active ? 'Active' : 'Ended',
            'Ended Reason' => $assignment->endReason(),
        ];
    }

    /**
     * Completed payments (money in)
     */
    private function paymentQuery(array $filters): Builder
    {
        $query = Payment::query()->where('status', 'completed');

        $this->dateRange($query, 'paid_at', $filters);

        if (! empty($filters['payment_method'])) {
            $query->where('method', $filters['payment_method']);
        }

        if (! empty($filters['payment_type'])) {
            $query->where('payment_type', $filters['payment_type']);
        }

        return $query;
    }

    private function revenueRow(Payment $payment): array
    {
        $contract = $payment->contract;
        $services = $contract?->items->pluck('service.name')->filter()->unique();

        return [
            'Payment ID' => $payment->id,
            'Paid At' => $payment->paid_at?->format('Y-m-d H:i'),
            'Type' => $payment->payment_type === 'employee_activation' ? 'Employee Activation' : 'Contract',
            'Contract Number' => $contract?->contract_number,
            'Paid By' => $contract ? ($contract->patient->name ?? '') : ($payment->employee?->getField('name', '') ?? ''),
            'Phone' => $contract ? ($contract->patient->phone ?? '') : $payment->employee?->getField('phone'),
            'Services' => $services && $services->isNotEmpty() ? $services->join(', ') : ($contract?->service->name ?? ''),
            'Amount (₹)' => $this->money($payment->amount),
            'Method' => self::PAYMENT_METHODS[$payment->method] ?? ucfirst((string) $payment->method),
            'Reference' => $payment->reference ?: $payment->razorpay_payment_id,
            'Notes' => $payment->notes,
        ];
    }

    /**
     * Refunds recorded when contracts were closed, within the same date range as revenue
     *
     * Only contract payments can be refunded, so an activation-only view has
     * none. Refunds carry no payment method; the revenue summary leaves them
     * out when a method filter is active rather than guess one.
     */
    public function getRefundTotal(array $filters = []): float
    {
        if (($filters['payment_type'] ?? '') === 'employee_activation') {
            return 0.0;
        }

        $query = Contract::whereNotNull('refund_amount');
        $this->dateRange($query, 'closed_at', $filters);

        return (float) $query->sum('refund_amount');
    }

    /**
     * Employee payouts (money out)
     */
    private function payoutQuery(array $filters): Builder
    {
        $query = EmployeePayout::query();

        if (! empty($filters['start_date'])) {
            $query->whereDate('period_start', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate('period_end', '<=', $filters['end_date']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        return $query;
    }

    private function payoutRow(EmployeePayout $payout): array
    {
        return [
            'Payout ID' => $payout->id,
            'Employee' => $payout->employee?->getField('name', ''),
            'Employee Phone' => $payout->employee?->getField('phone'),
            'Period' => $payout->periodLabel(),
            'Pay Type' => EmployeeSalary::PAY_TYPES[$payout->pay_type] ?? ($payout->pay_type ? ucfirst($payout->pay_type) : ucfirst((string) $payout->frequency)),
            'Salary Rate (₹)' => $this->money($payout->rate),
            'Payable Days' => (float) $payout->payable_days,
            'Unmarked Days' => (int) ($payout->attendance_summary['unmarked'] ?? 0),
            'Gross (₹)' => $this->money($payout->gross_amount),
            'Adjustments (₹)' => $this->money($payout->adjustments_total),
            'Net Pay (₹)' => $this->money($payout->amount),
            'Status' => ucfirst($payout->status),
            'Payment Method' => $payout->paymentMethodLabel(),
            'Reference' => $payout->transaction_reference,
            'Paid On' => $payout->paid_at?->format('Y-m-d'),
            'Notes' => $payout->notes,
        ];
    }

    /**
     * Headline totals shown above a report, over every row the filters match
     *
     * A card built from a single column names it in 'column', so hiding that
     * column also hides the card.
     *
     * @return array<int, array{label: string, value: string, tone?: string, hint?: string, column?: string}>
     */
    public function summary(string $report, array $filters = []): array
    {
        $a = $this->aggregates($report, $filters);

        return match ($report) {
            'patients' => [
                ['label' => 'Patients', 'value' => number_format($a['count'])],
                ['label' => 'Active', 'value' => number_format($a['active']), 'tone' => 'success'],
                ['label' => 'Contract Value', 'value' => format_currency($a['value'])],
                ['label' => 'Balance Due', 'value' => format_currency($a['balance']), 'tone' => 'danger', 'hint' => 'Excludes closed contracts'],
            ],
            'employees' => [
                ['label' => 'Employees', 'value' => number_format($a['count'])],
                ['label' => 'Available', 'value' => number_format($a['available']), 'tone' => 'success'],
                ['label' => 'Placed', 'value' => number_format($a['placed']), 'tone' => 'info'],
                ['label' => 'Payouts Pending', 'value' => format_currency($a['payouts_pending']), 'tone' => 'warning', 'column' => 'Payouts Pending (₹)'],
            ],
            'contracts' => [
                ['label' => 'Contracts', 'value' => number_format($a['count'])],
                ['label' => 'Total Value', 'value' => format_currency($a['value'])],
                ['label' => 'Collected', 'value' => format_currency($a['paid']), 'tone' => 'success'],
                ['label' => 'Outstanding', 'value' => format_currency($a['balance']), 'tone' => 'danger', 'hint' => 'Excludes closed contracts'],
                ['label' => 'Refunds', 'value' => format_currency($a['refunds']), 'tone' => 'warning'],
            ],
            'assignments' => [
                ['label' => 'Assignments', 'value' => number_format($a['count'])],
                ['label' => 'Active', 'value' => number_format($a['active']), 'tone' => 'success'],
                ['label' => 'Replacements', 'value' => number_format($a['replacements']), 'tone' => 'info'],
                ['label' => 'Temporary Covers', 'value' => number_format($a['covers']), 'tone' => 'warning'],
            ],
            'revenue' => array_values(array_filter([
                ['label' => 'Payments', 'value' => number_format($a['count']), 'hint' => $a['by_method']],
                ['label' => 'Collected', 'value' => format_currency($a['collected']), 'tone' => 'success',
                    'hint' => $a['refunds'] === null ? 'Refunds are not recorded per payment method' : null],
                $a['refunds'] === null ? null
                    : ['label' => 'Refunds', 'value' => format_currency($a['refunds']), 'tone' => 'danger', 'hint' => 'Contracts closed in this period'],
                $a['refunds'] === null ? null
                    : ['label' => 'Net', 'value' => format_currency($a['collected'] - $a['refunds']), 'tone' => 'primary'],
            ])),
            'payouts' => [
                ['label' => 'Payslips', 'value' => number_format($a['count'])],
                ['label' => 'Net Payroll', 'value' => format_currency($a['net']), 'hint' => 'Excludes cancelled payslips'],
                ['label' => 'Paid', 'value' => format_currency($a['paid']), 'tone' => 'success'],
                ['label' => 'Pending', 'value' => format_currency($a['pending']), 'tone' => 'warning'],
            ],
            default => [],
        };
    }

    /**
     * Table footer totals over every row the filters match
     *
     * Only columns that add up are listed: an assignment's line amount repeats
     * on every replacement for the same line, and rates are not amounts.
     *
     * @return array{label: string, values: array<string, float>}
     */
    public function totals(string $report, array $filters = []): array
    {
        $a = $this->aggregates($report, $filters);

        return match ($report) {
            'patients' => ['label' => 'Total', 'values' => [
                'Contract Value (₹)' => $a['value'],
                'Paid (₹)' => $a['paid'],
                'Balance (₹)' => $a['balance'],
                'Refunds (₹)' => $a['refunds'],
            ]],
            'employees' => ['label' => 'Total', 'values' => [
                'Payouts Paid (₹)' => $a['payouts_paid'],
                'Payouts Pending (₹)' => $a['payouts_pending'],
            ]],
            'contracts' => ['label' => 'Total', 'values' => [
                'Contract Value (₹)' => $a['value'],
                'Paid (₹)' => $a['paid'],
                'Balance (₹)' => $a['balance'],
                'Refund (₹)' => $a['refunds'],
            ]],
            'revenue' => ['label' => 'Total', 'values' => [
                'Amount (₹)' => $a['collected'],
            ]],
            'payouts' => ['label' => 'Total (excl. cancelled)', 'values' => [
                'Gross (₹)' => $a['gross'],
                'Adjustments (₹)' => $a['adjustments'],
                'Net Pay (₹)' => $a['net'],
            ]],
            default => ['label' => 'Total', 'values' => []],
        };
    }

    /**
     * Counts and sums behind summary() and totals(), as aggregate queries
     */
    private function aggregates(string $report, array $filters): array
    {
        $key = $report.'|'.serialize($filters);

        return $this->aggregateCache[$key] ??= match ($report) {
            'patients' => $this->patientAggregates($filters),
            'employees' => $this->employeeAggregates($filters),
            'contracts' => $this->contractMoney($this->contractQuery($filters)),
            'assignments' => $this->assignmentAggregates($filters),
            'revenue' => $this->revenueAggregates($filters),
            'payouts' => $this->payoutAggregates($filters),
            default => throw new InvalidArgumentException("Unknown report [{$report}]."),
        };
    }

    private function patientAggregates(array $filters): array
    {
        $patients = $this->patientQuery($filters);
        $contracts = Contract::whereIn('patient_id', (clone $patients)->select('patients.id'));

        return array_merge($this->contractMoney($contracts), [
            'count' => (clone $patients)->count(),
            'active' => (clone $patients)->where('is_active', true)->count(),
        ]);
    }

    private function employeeAggregates(array $filters): array
    {
        $employees = $this->employeeQuery($filters);

        $payouts = EmployeePayout::whereIn('employee_id', (clone $employees)->select('employees.id'))
            ->toBase()
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) AS paid")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) AS pending")
            ->first();

        return [
            'count' => (clone $employees)->count(),
            'available' => (clone $employees)->available()->count(),
            'placed' => (clone $employees)->placed()->count(),
            'payouts_paid' => $this->money($payouts->paid),
            'payouts_pending' => $this->money($payouts->pending),
        ];
    }

    /**
     * Value, collected, balance due and refunds over a set of contracts
     *
     * Balance is summed per contract, as contractBalance() does: an overpaid
     * contract does not offset another, and closed contracts owe nothing.
     */
    private function contractMoney(Builder $contracts): array
    {
        $rows = $contracts
            ->select(['contracts.amount', 'contracts.status', 'contracts.refund_amount'])
            ->selectSub($this->paidSubquery(), 'paid_total');

        $totals = DB::query()->fromSub($rows, 'c')
            ->selectRaw('COUNT(*) AS contracts')
            ->selectRaw('COALESCE(SUM(amount), 0) AS value')
            ->selectRaw('COALESCE(SUM(paid_total), 0) AS paid')
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'closed' OR paid_total >= amount THEN 0 ELSE amount - paid_total END), 0) AS balance")
            ->selectRaw('COALESCE(SUM(refund_amount), 0) AS refunds')
            ->first();

        return [
            'count' => (int) $totals->contracts,
            'value' => $this->money($totals->value),
            'paid' => $this->money($totals->paid),
            'balance' => $this->money($totals->balance),
            'refunds' => $this->money($totals->refunds),
        ];
    }

    /**
     * Completed payments on the outer query's contract
     */
    private function paidSubquery(): Builder
    {
        return Payment::query()
            ->selectRaw('COALESCE(SUM(payments.amount), 0)')
            ->whereColumn('payments.contract_id', 'contracts.id')
            ->where('payments.status', 'completed');
    }

    private function assignmentAggregates(array $filters): array
    {
        $assignments = $this->assignmentQuery($filters);

        return [
            'count' => (clone $assignments)->count(),
            'active' => (clone $assignments)->where('is_active', true)->count(),
            'replacements' => (clone $assignments)->ofType('replacement')->count(),
            'covers' => (clone $assignments)->ofType('cover')->count(),
        ];
    }

    private function revenueAggregates(array $filters): array
    {
        $payments = $this->paymentQuery($filters);

        $byMethod = (clone $payments)->toBase()
            ->select('method')
            ->selectRaw('SUM(amount) AS total')
            ->groupBy('method')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($group) => (self::PAYMENT_METHODS[$group->method] ?? ucfirst((string) $group->method)).': '.format_currency($group->total))
            ->join(' · ');

        return [
            'count' => (clone $payments)->count(),
            'collected' => $this->money((clone $payments)->sum('amount')),
            'by_method' => $byMethod,
            'refunds' => empty($filters['payment_method']) ? $this->getRefundTotal($filters) : null,
        ];
    }

    private function payoutAggregates(array $filters): array
    {
        $totals = $this->payoutQuery($filters)->toBase()
            ->selectRaw('COUNT(*) AS payslips')
            ->selectRaw("COALESCE(SUM(CASE WHEN status <> 'cancelled' THEN amount ELSE 0 END), 0) AS net")
            ->selectRaw("COALESCE(SUM(CASE WHEN status <> 'cancelled' THEN gross_amount ELSE 0 END), 0) AS gross")
            ->selectRaw("COALESCE(SUM(CASE WHEN status <> 'cancelled' THEN adjustments_total ELSE 0 END), 0) AS adjustments")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) AS paid")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) AS pending")
            ->first();

        return [
            'count' => (int) $totals->payslips,
            'net' => $this->money($totals->net),
            'gross' => $this->money($totals->gross),
            'adjustments' => $this->money($totals->adjustments),
            'paid' => $this->money($totals->paid),
            'pending' => $this->money($totals->pending),
        ];
    }

    /**
     * Write report rows as CSV to an open stream
     */
    public function writeCsv($handle, iterable $rows): void
    {
        // UTF-8 byte order mark so Excel shows ₹ and Tamil names correctly
        fwrite($handle, "\xEF\xBB\xBF");

        $headed = false;

        foreach ($rows as $row) {
            if (! $headed) {
                fputcsv($handle, array_keys($row));
                $headed = true;
            }

            fputcsv($handle, array_map(fn ($value) => $this->csvCell($value), $row));
        }
    }

    /**
     * Keep a spreadsheet from running typed-in text as a formula
     *
     * Names, notes and references come from staff, and Excel evaluates a cell
     * starting with = + - @ (or tab / carriage return) when the export is
     * opened. Numbers are left alone so money columns stay summable.
     */
    public function csvCell(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && str_contains("=+-@\t\r", $value[0])) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * Inclusive date range on a date or datetime column
     */
    private function dateRange(Builder $query, string $column, array $filters): void
    {
        if (! empty($filters['start_date'])) {
            $query->whereDate($column, '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate($column, '<=', $filters['end_date']);
        }
    }

    private function money($amount): float
    {
        return round((float) $amount, 2);
    }
}
