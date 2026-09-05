<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\Employee;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\ContactQuery;
use App\Models\JoinRequest;
use App\Services\ContractService;
use Carbon\Carbon;

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
    public function index()
    {
        // Patient Statistics
        $totalPatients = Patient::count();
        $activePatients = Patient::active()->count();

        // Employee Statistics
        $totalEmployees = Employee::count();
        $activeEmployees = Employee::active()->count();
        $inactiveEmployees = Employee::inactive()->count();
        $pendingEmployees = Employee::pending()->count();

        // Contract Statistics
        $contractStats = $this->contractService->getContractStatistics();
        
        // Recent Contracts
        $recentContracts = Contract::with(['patient', 'service'])
            ->latest()
            ->take(5)
            ->get();

        // Expiring Contracts
        $expiringContracts = $this->contractService->getExpiringContracts();

        // Revenue Statistics
        $last7DaysRevenue = Payment::completed()
            ->where('paid_at', '>=', Carbon::now()->subDays(7))
            ->sum('amount');

        $last30DaysRevenue = Payment::completed()
            ->where('paid_at', '>=', Carbon::now()->subDays(30))
            ->sum('amount');

        $totalRevenue = Payment::completed()->sum('amount');

        // Recent Queries
        $recentQueries = ContactQuery::new()
            ->latest()
            ->take(5)
            ->get();

        // Recent Join Requests
        $recentJoinRequests = JoinRequest::new()
            ->latest()
            ->take(5)
            ->get();

        // Monthly Revenue Chart Data (last 6 months)
        $monthlyRevenue = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $revenue = Payment::completed()
                ->whereYear('paid_at', $month->year)
                ->whereMonth('paid_at', $month->month)
                ->sum('amount');
            
            $monthlyRevenue[] = [
                'month' => $month->format('M Y'),
                'revenue' => $revenue,
            ];
        }

        return view('admin.dashboard', compact(
            'totalPatients',
            'activePatients',
            'totalEmployees',
            'activeEmployees',
            'inactiveEmployees',
            'pendingEmployees',
            'contractStats',
            'recentContracts',
            'expiringContracts',
            'last7DaysRevenue',
            'last30DaysRevenue',
            'totalRevenue',
            'recentQueries',
            'recentJoinRequests',
            'monthlyRevenue'
        ));
    }
}

