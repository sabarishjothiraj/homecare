<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\Employee;
use App\Services\ReportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    protected $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Display reports dashboard
     */
    public function index()
    {
        return view('admin.reports.index');
    }

    /**
     * Generate patient report
     */
    public function patients(Request $request)
    {
        $filters = $request->only(['start_date', 'end_date', 'city']);
        $data = $this->reportService->getPatientReport($filters);

        if ($request->has('export')) {
            $filename = 'patient_report_' . date('Y-m-d_His') . '.csv';
            $path = $this->reportService->exportToCSV($data, $filename);
            
            return response()->download(storage_path('app/public/' . $path))
                ->deleteFileAfterSend(true);
        }

        return view('admin.reports.patients', compact('data', 'filters'));
    }

    /**
     * Generate employee report
     */
    public function employees(Request $request)
    {
        $filters = $request->only(['status', 'start_date', 'end_date']);
        $data = $this->reportService->getEmployeeReport($filters);

        if ($request->has('export')) {
            $filename = 'employee_report_' . date('Y-m-d_His') . '.csv';
            $path = $this->reportService->exportToCSV($data, $filename);
            
            return response()->download(storage_path('app/public/' . $path))
                ->deleteFileAfterSend(true);
        }

        return view('admin.reports.employees', compact('data', 'filters'));
    }

    /**
     * Generate assignment report
     */
    public function assignments(Request $request)
    {
        $filters = $request->only(['start_date', 'end_date', 'patient_id', 'employee_id']);
        $data = $this->reportService->getAssignmentReport($filters);

        $patients = Patient::active()->orderBy('name')->get();
        $employees = Employee::active()->get();

        if ($request->has('export')) {
            $filename = 'assignment_report_' . date('Y-m-d_His') . '.csv';
            $path = $this->reportService->exportToCSV($data, $filename);
            
            return response()->download(storage_path('app/public/' . $path))
                ->deleteFileAfterSend(true);
        }

        return view('admin.reports.assignments', compact('data', 'filters', 'patients', 'employees'));
    }

    /**
     * Generate revenue report
     */
    public function revenue(Request $request)
    {
        $filters = $request->only(['start_date', 'end_date', 'payment_method', 'payment_type']);
        $data = $this->reportService->getRevenueReport($filters);

        if ($request->has('export')) {
            $filename = 'revenue_report_' . date('Y-m-d_His') . '.csv';
            $path = $this->reportService->exportToCSV($data, $filename);
            
            return response()->download(storage_path('app/public/' . $path))
                ->deleteFileAfterSend(true);
        }

        return view('admin.reports.revenue', compact('data', 'filters'));
    }

    /**
     * Export report
     */
    public function export(Request $request)
    {
        $validated = $request->validate([
            'report_type' => 'required|in:patients,employees,assignments,revenue',
            'filters' => 'nullable|array',
        ]);

        $reportType = $validated['report_type'];
        $filters = $validated['filters'] ?? [];

        // Generate report data
        switch ($reportType) {
            case 'patients':
                $data = $this->reportService->getPatientReport($filters);
                break;
            case 'employees':
                $data = $this->reportService->getEmployeeReport($filters);
                break;
            case 'assignments':
                $data = $this->reportService->getAssignmentReport($filters);
                break;
            case 'revenue':
                $data = $this->reportService->getRevenueReport($filters);
                break;
        }

        $filename = "{$reportType}_report_" . date('Y-m-d_His') . '.csv';
        $path = $this->reportService->exportToCSV($data, $filename);

        return response()->download(storage_path('app/public/' . $path))
            ->deleteFileAfterSend(true);
    }
}

