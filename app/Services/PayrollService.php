<?php

namespace App\Services;

use App\Exceptions\PayrollException;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeePayout;
use App\Models\EmployeePayoutItem;
use App\Models\EmployeeSalary;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Salary from attendance.
 *
 * Each day in the period is paid at the salary in force that day:
 *   - Monthly fixed: day rate = monthly amount / days in that month
 *   - Per day:       day rate = the daily amount
 * multiplied by the day's payable weight for its attendance mark. A fully
 * marked month of payable days therefore earns exactly the monthly salary,
 * and absences reduce it pro rata. Unmarked days are not paid and are flagged
 * so they get marked before the payroll is paid.
 *
 * Every write to a payslip re-reads it under a row lock and re-checks it is
 * still pending, so a paid payslip can't be changed by a request that
 * started before it was paid.
 */
class PayrollService
{
    /**
     * Payable share of a day per attendance status, by pay type
     */
    public const PAYABLE_WEIGHTS = [
        'monthly' => ['present' => 1, 'half_day' => 0.5, 'paid_leave' => 1, 'week_off' => 1, 'absent' => 0],
        'daily' => ['present' => 1, 'half_day' => 0.5, 'paid_leave' => 1, 'week_off' => 0, 'absent' => 0],
    ];

    /**
     * First day of a 'Y-m' month, or null if it isn't one. The '!' resets the
     * fields the format leaves out; without it the day comes from today, and
     * on the 29th-31st a shorter month rolls over into the next one.
     */
    public static function parseMonth(?string $value): ?Carbon
    {
        try {
            $month = $value ? Carbon::createFromFormat('!Y-m', $value) : null;
        } catch (\Throwable) {
            return null;
        }

        return $month && $month->format('Y-m') === $value ? $month : null;
    }

    /**
     * Work out pay for an employee over a period from salary history and attendance.
     *
     * @return array{salary: ?EmployeeSalary, days_in_period: int, salaried_days: int, payable_days: float, gross: float, summary: array<string, int>}
     */
    public function calculate(Employee $employee, Carbon $start, Carbon $end): array
    {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->startOfDay();

        $employee->loadMissing('salaries');
        $marks = $employee->attendances()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->pluck('status', 'date')
            ->mapWithKeys(fn ($status, $date) => [Carbon::parse($date)->toDateString() => $status]);

        $summary = array_fill_keys(array_keys(Attendance::STATUSES), 0) + ['unmarked' => 0];
        $payableDays = 0.0;
        $gross = 0.0;
        $salariedDays = 0;

        foreach (CarbonPeriod::create($start, $end) as $day) {
            $salary = $employee->salaryOn($day);
            if (! $salary) {
                continue; // not on salary yet
            }

            $salariedDays++;
            $status = $marks[$day->toDateString()] ?? null;

            if ($status === null) {
                $summary['unmarked']++;

                continue;
            }

            $summary[$status] = ($summary[$status] ?? 0) + 1;

            $weight = self::PAYABLE_WEIGHTS[$salary->pay_type][$status] ?? 0;
            $dayRate = $salary->pay_type === 'daily'
                ? (float) $salary->amount
                : (float) $salary->amount / $day->daysInMonth;

            $payableDays += $weight;
            $gross += $weight * $dayRate;
        }

        return [
            'salary' => $employee->salaryOn($end),
            'days_in_period' => (int) $start->diffInDays($end) + 1,
            'salaried_days' => $salariedDays,
            'payable_days' => round($payableDays, 1),
            'gross' => round($gross, 2),
            'summary' => $summary,
        ];
    }

    /**
     * Create or refresh draft payouts for a month.
     *
     * Paid payouts are never touched; pending ones are recalculated and keep
     * their adjustment lines.
     *
     * @param  array<int>|null  $employeeIds  limit to these employees
     * @return array{created: int, updated: int, skipped_paid: int, without_salary: array<int, string>, zeroed: array<int, string>, unmarked: int}
     */
    public function generateForMonth(Carbon $month, ?array $employeeIds = null): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $result = ['created' => 0, 'updated' => 0, 'skipped_paid' => 0, 'without_salary' => [], 'zeroed' => [], 'unmarked' => 0];

        // Active employees, plus anyone who worked in the month and has since left
        $employees = Employee::with('salaries')
            ->when($employeeIds, fn ($q) => $q->whereIn('id', $employeeIds))
            ->where(fn ($q) => $q->where('status', 'active')
                ->orWhereHas('attendances', fn ($a) => $a->whereBetween('date', [$start->toDateString(), $end->toDateString()])))
            ->get();

        foreach ($employees as $employee) {
            $name = $employee->getField('name', 'Employee #'.$employee->id);
            $hasSalary = $employee->salaries->contains(fn (EmployeeSalary $s) => $s->effective_from->lte($end));

            // The employee's row is the lock: two generate requests can't both
            // find no payslip and each insert one. Retried on deadlock, so the
            // closure reports its outcome rather than counting as it goes.
            $outcome = DB::transaction(function () use ($employee, $hasSalary, $start, $end) {
                Employee::whereKey($employee->id)->lockForUpdate()->first();
                $existing = $this->payoutFor($employee, $start, $end);

                if ($existing?->status === 'paid') {
                    return ['skipped_paid'];
                }

                if (! $hasSalary && ! $existing) {
                    return ['without_salary'];
                }

                // Without a salary, a draft left from one since removed goes to ₹0,
                // as Recalculate would, instead of staying payable at the old amount
                $payout = $this->savePayout($employee, $start, $end, $existing);

                return [$existing ? 'updated' : 'created', (int) ($payout->attendance_summary['unmarked'] ?? 0)];
            }, 3);

            if ($outcome[0] === 'without_salary') {
                $result['without_salary'][] = $name;
            } else {
                $result[$outcome[0]]++;
            }

            if (! $hasSalary && $outcome[0] === 'updated') {
                $result['zeroed'][] = $name;
            }
            $result['unmarked'] += $outcome[1] ?? 0;
        }

        return $result;
    }

    /**
     * Recalculate a pending payout from current salary and attendance
     */
    public function recalculate(EmployeePayout $payout): EmployeePayout
    {
        return DB::transaction(function () use ($payout) {
            $payout = $this->lockPending($payout, 'Only pending payouts can be recalculated.');

            return $this->savePayout($payout->employee, $payout->period_start, $payout->period_end, $payout);
        });
    }

    /**
     * Add an earning or deduction line.
     *
     * A deduction can't take net pay below zero: net is floored at ₹0, so the
     * part that couldn't be withheld would silently go unrecovered.
     */
    public function addItem(EmployeePayout $payout, array $data): EmployeePayoutItem
    {
        return DB::transaction(function () use ($payout, $data) {
            $payout = $this->lockPending($payout, 'Paid or cancelled payouts cannot be changed.');
            $available = round((float) $payout->gross_amount + (float) $payout->adjustments_total, 2);

            if ($data['type'] === 'deduction' && round((float) $data['amount'], 2) > $available) {
                throw new PayrollException('A deduction can\'t be more than the net pay left on this payslip (₹'.number_format(max(0, $available), 2).'). Recover the rest from a later payslip.');
            }

            $item = $payout->items()->create($data);
            $this->refreshTotals($payout);

            return $item;
        });
    }

    public function removeItem(EmployeePayout $payout, EmployeePayoutItem $item): void
    {
        DB::transaction(function () use ($payout, $item) {
            $payout = $this->lockPending($payout, 'Paid or cancelled payouts cannot be changed.');

            $payout->items()->whereKey($item->id)->delete();
            $this->refreshTotals($payout);
        });
    }

    /**
     * Delete a draft payslip (it can be generated again)
     */
    public function deleteDraft(EmployeePayout $payout): void
    {
        DB::transaction(fn () => $this->lockPending($payout, 'Cannot delete a paid payout.')->delete());
    }

    /**
     * Re-total net pay after payslip lines change.
     *
     * Net pay is floored at ₹0; a payslip whose deductions exceed its earnings
     * has a shortfall() and can't be paid until that is resolved.
     */
    public function refreshTotals(EmployeePayout $payout): EmployeePayout
    {
        $payout->load('items');
        $adjustments = round($payout->items->sum(fn ($item) => $item->signedAmount()), 2);

        $payout->update([
            'adjustments_total' => $adjustments,
            'amount' => max(0, round((float) $payout->gross_amount + $adjustments, 2)),
        ]);

        return $payout;
    }

    /**
     * Pay a payslip and lock it.
     *
     * The payslip is checked against current salary and attendance first. If
     * either has changed since it was calculated, it is recalculated and the
     * payment refused, so nobody pays an amount they haven't seen.
     *
     * @throws PayrollException
     */
    public function markPaid(EmployeePayout $payout, array $data): void
    {
        $recalculated = DB::transaction(function () use ($payout, $data) {
            $payout = $this->lockPending($payout, 'Only pending payouts can be marked as paid.');
            $calc = $this->calculate($payout->employee, $payout->period_start, $payout->period_end);

            if ($this->isStale($payout, $calc)) {
                return $this->savePayout($payout->employee, $payout->period_start, $payout->period_end, $payout, $calc);
            }

            if (isset($data['expected_amount']) && round((float) $data['expected_amount'], 2) !== round((float) $payout->amount, 2)) {
                throw new PayrollException('This payslip changed after you opened it; net pay is now ₹'.number_format((float) $payout->amount, 2).'. Check it and mark it paid again.');
            }

            if ($shortfall = $payout->shortfall()) {
                throw new PayrollException('Deductions are ₹'.number_format($shortfall, 2).' more than this payslip earns. Reduce or remove a deduction before paying.');
            }

            $gaps = $this->incompleteReasons($payout, $calc);
            if ($gaps && empty($data['allow_incomplete'])) {
                throw new PayrollException('This payslip is incomplete ('.implode('; ', $gaps).'). Tick "Pay anyway" to pay it as it stands; it is locked once paid.');
            }

            $payout->update([
                'status' => 'paid',
                'payment_method' => $data['payment_method'],
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'paid_at' => isset($data['paid_at']) ? Carbon::parse($data['paid_at']) : now(),
                'notes' => trim(($payout->notes ? $payout->notes."\n" : '').($data['paid_notes'] ?? '')) ?: null,
            ]);

            return null;
        });

        if ($recalculated) {
            throw new PayrollException('Salary or attendance changed since this payslip was calculated, so it has been recalculated; net pay is now ₹'.number_format((float) $recalculated->amount, 2).'. Check it and mark it paid again.');
        }
    }

    /**
     * Why paying this payslip now would pay an unfinished month (empty if it wouldn't)
     *
     * @return array<int, string>
     */
    public function incompleteReasons(EmployeePayout $payout, ?array $calc = null): array
    {
        $unmarked = (int) (($calc['summary'] ?? $payout->attendance_summary)['unmarked'] ?? 0);
        $reasons = [];

        if ($payout->period_end?->gt(today())) {
            $reasons[] = 'the period runs until '.$payout->period_end->format('M d, Y');
        }
        if ($unmarked) {
            $reasons[] = $unmarked.' '.Str::plural('day', $unmarked).' not marked';
        }

        return $reasons;
    }

    /**
     * Re-read a payout under a row lock and check it is still pending, so a
     * concurrent Mark Paid can't land between the check and the write. Call
     * inside a transaction.
     */
    private function lockPending(EmployeePayout $payout, string $error): EmployeePayout
    {
        $locked = EmployeePayout::whereKey($payout->id)->lockForUpdate()->first();

        if (! $locked?->isEditable()) {
            throw new PayrollException($error);
        }

        return $locked;
    }

    /**
     * Whether a fresh calculation no longer matches what the payslip saved.
     * Payouts that predate attendance-based payroll have nothing to compare.
     */
    private function isStale(EmployeePayout $payout, array $calc): bool
    {
        if ($payout->attendance_summary === null) {
            return false;
        }

        return round((float) $payout->gross_amount, 2) !== $calc['gross']
            || round((float) $payout->payable_days, 1) !== $calc['payable_days']
            || round((float) $payout->rate, 2) !== round((float) ($calc['salary']?->amount ?? 0), 2)
            || $payout->pay_type !== $calc['salary']?->pay_type
            || (int) $payout->days_in_period !== $calc['days_in_period']
            || $payout->attendance_summary != $calc['summary'];
    }

    /**
     * The live payslip for a period, locked. Plain column equality (not
     * whereDate) so the locking read uses the period key instead of scanning,
     * and locking, the whole table.
     */
    private function payoutFor(Employee $employee, Carbon $start, Carbon $end): ?EmployeePayout
    {
        return EmployeePayout::where('employee_id', $employee->id)
            ->where('period_start', $start->toDateString())
            ->where('period_end', $end->toDateString())
            ->where('status', '!=', 'cancelled')
            ->lockForUpdate()
            ->first();
    }

    private function savePayout(Employee $employee, Carbon $start, Carbon $end, ?EmployeePayout $payout, ?array $calc = null): EmployeePayout
    {
        $calc ??= $this->calculate($employee, $start, $end);

        return DB::transaction(function () use ($employee, $start, $end, $payout, $calc) {
            $attributes = [
                'employee_id' => $employee->id,
                'frequency' => 'monthly',
                'pay_type' => $calc['salary']?->pay_type,
                'rate' => (float) ($calc['salary']?->amount ?? 0),
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'days_in_period' => $calc['days_in_period'],
                'payable_days' => $calc['payable_days'],
                'attendance_summary' => $calc['summary'],
                'gross_amount' => $calc['gross'],
            ];

            if ($payout) {
                $payout->update($attributes);
            } else {
                $payout = EmployeePayout::create($attributes + ['status' => 'pending', 'amount' => $calc['gross']]);
            }

            return $this->refreshTotals($payout);
        });
    }
}
