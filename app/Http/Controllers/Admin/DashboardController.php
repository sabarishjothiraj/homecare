<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactQuery;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\JoinRequest;
use App\Models\Patient;
use App\Models\Payment;
use App\Services\ContractService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    protected $contractService;

    public function __construct(ContractService $contractService)
    {
        $this->contractService = $contractService;
    }

    /**
     * Display admin dashboard
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $holdsAny = fn (string ...$permissions) => collect($permissions)->contains(fn ($permission) => $user->hasPermission($permission));

        // Every role that logs in lands here, so each panel is only built for
        // users who could open the module it summarises
        $show = [
            'patients' => $holdsAny('patients.view', 'patients.manage'),
            'employees' => $holdsAny('employees.view', 'employees.manage'),
            'contracts' => $holdsAny('contracts.view', 'contracts.manage'),
            'payments' => $holdsAny('payments.view', 'payments.manage'),
            'queries' => $holdsAny('queries.manage'),
        ];

        $data = ['show' => $show];

        if ($show['patients']) {
            $data['totalPatients'] = Patient::count();
            $data['activePatients'] = Patient::active()->count();
        }

        if ($show['employees']) {
            $data['totalEmployees'] = Employee::count();
            $data['activeEmployees'] = Employee::active()->count();
            $data['inactiveEmployees'] = Employee::inactive()->count();
            $data['pendingEmployees'] = Employee::pending()->count();
        }

        if ($show['contracts']) {
            $data['contractStats'] = $this->contractService->getContractStatistics();
            $data['recentContracts'] = Contract::with(['patient', 'service'])
                ->latest()
                ->take(5)
                ->get();
            $data['expiringContracts'] = $this->contractService->getExpiringContracts();
        }

        if ($show['payments']) {
            $data += $this->revenueStatistics();
        }

        if ($show['queries']) {
            $data['recentQueries'] = ContactQuery::new()
                ->latest()
                ->take(5)
                ->get();

            $data['recentJoinRequests'] = JoinRequest::new()
                ->latest()
                ->take(5)
                ->get();
        }

        return view('admin.dashboard', $data);
    }

    /**
     * Revenue, refund and 6-month chart figures
     */
    private function revenueStatistics(): array
    {
        $last7DaysRevenue = Payment::completed()
            ->where('paid_at', '>=', now()->subDays(7))
            ->sum('amount');

        $last30DaysRevenue = Payment::completed()
            ->where('paid_at', '>=', now()->subDays(30))
            ->sum('amount');

        $totalRevenue = Payment::completed()->sum('amount');

        // Refund Statistics (recorded when a contract is closed, dated by closed_at)
        $refundedContracts = Contract::whereNotNull('refund_amount')->where('refund_amount', '>', 0);

        $totalRefunds = (clone $refundedContracts)->sum('refund_amount');
        $refundCount = (clone $refundedContracts)->count();
        $last30DaysRefunds = (clone $refundedContracts)
            ->where('closed_at', '>=', now()->subDays(30))
            ->sum('refund_amount');
        $netRevenue = $totalRevenue - $totalRefunds;

        // Monthly Revenue Chart Data (last 6 months)
        $monthlyRevenue = [];
        for ($i = 5; $i >= 0; $i--) {
            // Step back from the 1st: from the 29th-31st, subMonths() overflows
            // into the following month, repeating one month and skipping another
            $month = now()->startOfMonth()->subMonths($i);
            $revenue = Payment::completed()
                ->whereYear('paid_at', $month->year)
                ->whereMonth('paid_at', $month->month)
                ->sum('amount');

            $refund = Contract::whereNotNull('refund_amount')
                ->whereYear('closed_at', $month->year)
                ->whereMonth('closed_at', $month->month)
                ->sum('refund_amount');

            $monthlyRevenue[] = [
                'month' => $month->format('M Y'),
                'revenue' => (float) $revenue,
                'refund' => (float) $refund,
            ];
        }

        return compact(
            'last7DaysRevenue',
            'last30DaysRevenue',
            'totalRevenue',
            'totalRefunds',
            'refundCount',
            'last30DaysRefunds',
            'netRevenue',
            'monthlyRevenue'
        );
    }
}
