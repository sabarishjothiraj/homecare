<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Report key => [filter keys, permissions guarding its source data]
     *
     * The report routes require the same permissions. export() takes the
     * report type from the form, so it checks them itself.
     */
    private const REPORTS = [
        'patients' => [['search', 'status', 'city', 'start_date', 'end_date'], ['patients.view', 'patients.manage']],
        'employees' => [['search', 'status', 'service_id', 'availability', 'start_date', 'end_date'], ['employees.view', 'employees.manage']],
        'contracts' => [['search', 'status', 'payment_status', 'payment_method', 'service_id', 'start_date', 'end_date'], ['contracts.view', 'contracts.manage']],
        'assignments' => [['patient_id', 'employee_id', 'type', 'status', 'start_date', 'end_date'], ['contracts.view', 'contracts.manage']],
        'revenue' => [['payment_method', 'payment_type', 'start_date', 'end_date'], ['payments.view', 'payments.manage']],
        'payouts' => [['employee_id', 'status', 'start_date', 'end_date'], ['payouts.view', 'payouts.manage']],
    ];

    private const PER_PAGE = 50;

    protected $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Display reports dashboard
     */
    public function index(Request $request)
    {
        // Only the reports this user may open are offered
        $available = array_values(array_filter(
            array_keys(self::REPORTS),
            fn (string $type) => $this->canView($request->user(), $type)
        ));

        return view('admin.reports.index', compact('available'));
    }

    public function patients(Request $request)
    {
        return $this->report($request, 'patients');
    }

    public function employees(Request $request)
    {
        return $this->report($request, 'employees', [
            'services' => Service::orderBy('name')->get(),
        ]);
    }

    public function contracts(Request $request)
    {
        return $this->report($request, 'contracts', [
            'services' => Service::orderBy('name')->get(),
        ]);
    }

    public function assignments(Request $request)
    {
        // All patients/employees, so history for inactive ones can still be reported
        return $this->report($request, 'assignments', [
            'patients' => Patient::orderBy('name')->get(['id', 'name', 'is_active']),
            'employees' => $this->employeeOptions(),
        ]);
    }

    public function revenue(Request $request)
    {
        return $this->report($request, 'revenue');
    }

    public function payouts(Request $request)
    {
        return $this->report($request, 'payouts', [
            'employees' => $this->employeeOptions(),
        ]);
    }

    /**
     * Quick export from the reports dashboard
     */
    public function export(Request $request)
    {
        $validated = $request->validate([
            'report_type' => 'required|in:'.implode(',', array_keys(self::REPORTS)),
            'filters' => 'nullable|array',
        ]);

        $type = $validated['report_type'];

        // The route only requires reports.view; the report's own data
        // permission is checked here, as the report routes do
        abort_unless($this->canView($request->user(), $type), 403, 'You do not have permission to access this report.');

        return $this->download($request->user(), $type, $this->filters($type, $validated['filters'] ?? []));
    }

    private function report(Request $request, string $type, array $viewData = [])
    {
        $filters = $this->filters($type, $request->all());

        if ($request->has('export')) {
            return $this->download($request->user(), $type, $filters);
        }

        $hidden = $this->hiddenColumns($request->user(), $type);

        $data = $this->reportService->paginate($type, $filters, self::PER_PAGE)
            ->withQueryString()
            ->through(fn (array $row) => array_diff_key($row, $hidden));

        $summary = array_values(array_filter(
            $this->reportService->summary($type, $filters),
            fn (array $card) => ! isset($card['column'], $hidden[$card['column']])
        ));

        $totals = $this->reportService->totals($type, $filters);
        $totals['values'] = array_diff_key($totals['values'], $hidden);

        return view("admin.reports.{$type}", array_merge(compact('data', 'filters', 'summary', 'totals'), $viewData));
    }

    /**
     * Stream the whole filtered report as CSV
     *
     * Nothing is written to disk, so there is no file under a guessable name
     * to fetch or to be left behind, and parallel exports cannot collide.
     */
    private function download(User $user, string $type, array $filters)
    {
        $hidden = $this->hiddenColumns($user, $type);
        $rows = $this->reportService->rows($type, $filters)
            ->map(fn (array $row) => array_diff_key($row, $hidden));

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            $this->reportService->writeCsv($out, $rows);
            fclose($out);
        }, "{$type}_report_".now()->format('Y-m-d_His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * The report's own filters, keeping only filled-in plain values
     */
    private function filters(string $type, array $input): array
    {
        [$keys] = self::REPORTS[$type];

        return array_filter(
            array_intersect_key($input, array_flip($keys)),
            fn ($value) => is_scalar($value) && $value !== ''
        );
    }

    private function canView(User $user, string $type): bool
    {
        [, $permissions] = self::REPORTS[$type];

        return $this->holdsAny($user, $permissions);
    }

    /**
     * Columns from modules this user cannot open, as heading => true
     */
    private function hiddenColumns(User $user, string $type): array
    {
        $hidden = [];

        foreach (ReportService::RESTRICTED_COLUMNS[$type] ?? [] as $group) {
            if (! $this->holdsAny($user, $group['permissions'])) {
                $hidden += array_fill_keys($group['columns'], true);
            }
        }

        return $hidden;
    }

    private function holdsAny(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Just what the employee dropdowns show
     */
    private function employeeOptions()
    {
        return Employee::orderBy('first_name')->get(['id', 'first_name', 'last_name', 'field_data', 'status']);
    }
}
