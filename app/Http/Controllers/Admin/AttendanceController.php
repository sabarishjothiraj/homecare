<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeePayout;
use App\Services\PayrollService;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Attendance feeds payroll: marked days are paid and unmarked days are not.
 * So days can't be marked before they happen, and days on a paid payslip are
 * locked so the attendance keeps matching what was paid.
 */
class AttendanceController extends Controller
{
    /**
     * Daily attendance sheet for one date
     */
    public function index(Request $request)
    {
        $date = $this->parseDate($request->input('date')) ?? now()->startOfDay();

        $employees = $this->employeesFor($request, $date->toDateString(), $date->toDateString());

        $marks = Attendance::whereDate('date', $date->toDateString())
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get()
            ->keyBy('employee_id');

        $placements = $this->placementsFor($employees->pluck('id')->all());

        $counts = collect(Attendance::STATUSES)->map(fn ($meta, $status) => $marks->where('status', $status)->count());
        $unmarked = $employees->count() - $marks->count();

        $lockedIds = $this->paidPeriods($employees->pluck('id')->all(), $date, $date)->keys();
        $isFuture = $date->gt(today());

        return view('admin.attendance.index', compact('date', 'employees', 'marks', 'placements', 'counts', 'unmarked', 'lockedIds', 'isFuture'));
    }

    /**
     * Save the daily sheet. A blank status clears that employee's mark.
     *
     * Each row posts back the version of the mark it showed. A row is written
     * only if it differs from the stored mark and nobody changed that mark
     * after the sheet was loaded, so saving an older copy of the sheet can't
     * wipe or overwrite someone else's marks.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'records' => 'required|array',
            'records.*.status' => ['nullable', Rule::in(array_keys(Attendance::STATUSES))],
            'records.*.notes' => 'nullable|string|max:255',
            'records.*.version' => 'nullable|string|max:40',
        ]);

        $date = Carbon::parse($validated['date'])->startOfDay();
        $day = $date->toDateString();
        $redirect = redirect()->route('admin.attendance.index', array_filter([
            'date' => $day,
            'search' => $request->input('search'),
            'placement' => $request->input('placement'),
        ]));

        // Rows whose inputs were disabled on the sheet post no status at all
        $records = collect($validated['records'])->filter(fn ($record) => is_array($record) && array_key_exists('status', $record));

        if ($date->gt(today()) && $records->contains(fn ($record) => filled($record['status']))) {
            return $redirect->with('error', 'Attendance can\'t be marked for a future date.');
        }

        // Keys are employee ids: only the people this date's sheet lists
        $onSheet = Employee::whereIn('id', $records->keys()->filter(fn ($id) => is_int($id))->all())
            ->where(fn ($q) => $q->where('status', 'active')
                ->orWhereHas('attendances', fn ($a) => $a->where('date', $day)))
            ->pluck('id');
        $ignored = $records->count() - $onSheet->count();
        $records = $records->only($onSheet->all());

        $counts = DB::transaction(function () use ($records, $date, $day) {
            $counts = ['saved' => 0, 'cleared' => 0, 'conflicts' => 0, 'locked' => 0];

            // Payslips first, then marks, the same order bulk() takes them in
            $locked = $this->paidPeriods($records->keys()->all(), $date, $date, lock: true)->keys();

            // Locked so the version check and the write can't interleave with another save
            $current = Attendance::whereIn('employee_id', $records->keys()->all())
                ->where('date', $day)
                ->lockForUpdate()
                ->get()
                ->keyBy('employee_id');

            foreach ($records as $employeeId => $record) {
                $mark = $current->get($employeeId);
                $status = $record['status'] ?: null;
                $notes = $record['notes'] ?? null;
                $shown = $record['version'] ?? '';

                if ($status === $mark?->status && ($status === null || $notes === $mark->notes)) {
                    continue; // nothing to change
                }

                if ($locked->contains($employeeId)) {
                    $counts['locked']++;

                    continue;
                }

                // Changed by someone else after the sheet was loaded: keep their mark.
                // A row that was blank and left blank is no conflict at all.
                if ($shown !== ($mark?->version() ?? '')) {
                    if ($shown !== '' || $status !== null) {
                        $counts['conflicts']++;
                    }

                    continue;
                }

                if ($status === null) {
                    $mark->delete();
                    $counts['cleared']++;

                    continue;
                }

                $values = ['status' => $status, 'notes' => $notes, 'marked_by' => auth()->id()];
                $mark ? $mark->update($values) : Attendance::create($values + ['employee_id' => $employeeId, 'date' => $day]);
                $counts['saved']++;
            }

            return $counts;
        }, 3);

        $label = $date->format('M d, Y');
        $message = $counts['saved'] || $counts['cleared']
            ? "Attendance saved for {$counts['saved']} ".str('employee')->plural($counts['saved'])." on {$label}.".($counts['cleared'] ? " {$counts['cleared']} cleared." : '')
            : "No attendance changes to save for {$label}.";

        $problems = [];
        if ($counts['conflicts']) {
            $problems[] = "{$counts['conflicts']} ".str('mark')->plural($counts['conflicts']).' changed by someone else after you opened this sheet '.($counts['conflicts'] === 1 ? 'was' : 'were').' left as '.($counts['conflicts'] === 1 ? 'it is' : 'they are').'. Check and save again.';
        }
        if ($counts['locked']) {
            $problems[] = "{$counts['locked']} ".str('employee')->plural($counts['locked']).' already paid for this date; that attendance is locked.';
        }
        if ($ignored) {
            $problems[] = "{$ignored} ".str('row')->plural($ignored).' ignored: not an active employee on this sheet.';
        }

        $redirect->with('success', $message);

        return $problems ? $redirect->with('error', implode(' ', $problems)) : $redirect;
    }

    /**
     * Mark a status for many employees across a date range
     */
    public function bulk(Request $request)
    {
        $validated = $request->validate([
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'integer|exists:employees,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => ['required', Rule::in(array_keys(Attendance::STATUSES))],
            'overwrite' => 'nullable|boolean',
            'return_month' => 'nullable|date_format:Y-m',
        ]);

        $start = Carbon::parse($validated['start_date'])->startOfDay();
        $end = Carbon::parse($validated['end_date'])->startOfDay();

        if ($start->diffInDays($end) > 61) {
            return back()->withInput()->with('error', 'Bulk marking is limited to 62 days at a time.');
        }

        if ($end->gt(today())) {
            return back()->withInput()->with('error', 'Attendance can\'t be marked for future dates. Choose an end date on or before '.today()->format('M d, Y').'.');
        }

        $overwrite = (bool) ($validated['overwrite'] ?? false);
        $employeeIds = collect($validated['employee_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $counts = DB::transaction(function () use ($employeeIds, $start, $end, $overwrite, $validated) {
            $counts = ['saved' => 0, 'skipped' => 0, 'locked' => 0];
            $paid = $this->paidPeriods($employeeIds->all(), $start, $end, lock: true);

            foreach ($employeeIds as $employeeId) {
                $existing = Attendance::where('employee_id', $employeeId)
                    ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                    ->pluck('date')
                    ->map(fn ($date) => Carbon::parse($date)->toDateString())
                    ->all();
                $paidPeriods = $paid->get($employeeId, collect());

                foreach (CarbonPeriod::create($start, $end) as $day) {
                    if ($paidPeriods->contains(fn (EmployeePayout $payout) => $day->between($payout->period_start, $payout->period_end))) {
                        $counts['locked']++;

                        continue;
                    }

                    if (in_array($day->toDateString(), $existing, true) && ! $overwrite) {
                        $counts['skipped']++;

                        continue;
                    }

                    Attendance::updateOrCreate(
                        ['employee_id' => $employeeId, 'date' => $day->toDateString()],
                        ['status' => $validated['status'], 'marked_by' => auth()->id()]
                    );
                    $counts['saved']++;
                }
            }

            return $counts;
        }, 3);

        $label = Attendance::STATUSES[$validated['status']]['label'];
        $message = "Marked {$counts['saved']} ".str('day')->plural($counts['saved'])." as {$label}.";
        if ($counts['skipped']) {
            $message .= " {$counts['skipped']} already-marked ".str('day')->plural($counts['skipped']).' left unchanged.';
        }
        if ($counts['locked']) {
            $message .= " {$counts['locked']} ".str('day')->plural($counts['locked']).' on paid payslips left unchanged.';
        }

        return redirect()->route('admin.attendance.register', ['month' => $validated['return_month'] ?? $start->format('Y-m')])
            ->with('success', $message);
    }

    /**
     * Monthly register: employees × days
     */
    public function register(Request $request, PayrollService $payroll)
    {
        $month = PayrollService::parseMonth($request->input('month')) ?? now()->startOfMonth();
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $employees = $this->employeesFor($request, $start->toDateString(), $end->toDateString());
        $employees->load('salaries');

        $marks = Attendance::whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get()
            ->groupBy('employee_id')
            ->map(fn ($rows) => $rows->keyBy(fn ($row) => $row->date->toDateString()));

        $totals = $employees->mapWithKeys(fn (Employee $employee) => [
            $employee->id => $payroll->calculate($employee, $start, $end),
        ]);

        $days = collect(CarbonPeriod::create($start, $end))->map(fn ($day) => $day->copy());

        return view('admin.attendance.register', compact('month', 'start', 'end', 'employees', 'marks', 'totals', 'days'));
    }

    /**
     * Active employees (plus anyone already marked in the range), with optional filters
     */
    private function employeesFor(Request $request, string $from, string $to)
    {
        return Employee::query()
            ->where(fn ($q) => $q->where('status', 'active')
                ->orWhereHas('attendances', fn ($a) => $a->whereBetween('date', [$from, $to])))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->input('search');
                $q->where(fn ($s) => $s->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%"));
            })
            ->when($request->input('placement') === 'placed', fn ($q) => $q->placed())
            ->when($request->input('placement') === 'available', fn ($q) => $q->available())
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    /**
     * Current patient(s) per employee, for context on the sheet
     */
    private function placementsFor(array $employeeIds)
    {
        return Assignment::where('is_active', true)
            ->whereIn('employee_id', $employeeIds)
            ->with('contract.patient')
            ->get()
            ->groupBy('employee_id')
            ->map(fn ($rows) => $rows->map(fn (Assignment $a) => ($a->contract->patient->name ?? 'Contract #'.$a->contract_id).($a->isCover() ? ' (cover)' : ''))->unique()->join(', '));
    }

    /**
     * Paid payslip periods overlapping a date range, grouped by employee id.
     *
     * With $lock (inside the attendance transaction), every live payslip over
     * those days is row-locked, pending ones included. A Mark Paid running
     * alongside then either commits first, so its days show as paid here, or
     * waits and afterwards sees the new attendance and recalculates instead.
     */
    private function paidPeriods(array $employeeIds, Carbon $from, Carbon $to, bool $lock = false)
    {
        return EmployeePayout::whereIn('employee_id', $employeeIds)
            ->where('period_start', '<=', $to->toDateString())
            ->where('period_end', '>=', $from->toDateString())
            ->where('status', '!=', 'cancelled')
            ->when($lock, fn ($q) => $q->lockForUpdate())
            ->get(['id', 'employee_id', 'period_start', 'period_end', 'status'])
            ->where('status', 'paid')
            ->groupBy('employee_id');
    }

    private function parseDate(?string $value): ?Carbon
    {
        try {
            return $value ? Carbon::parse($value)->startOfDay() : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
