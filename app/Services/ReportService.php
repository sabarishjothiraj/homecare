<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\Employee;
use App\Models\Assignment;
use App\Models\Payment;
use App\Models\Contract;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class ReportService
{
    /**
     * Generate patient details report
     */
    public function getPatientReport(array $filters = []): Collection
    {
        $query = Patient::with(['contracts', 'activeContracts']);

        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        if (!empty($filters['city'])) {
            $query->where('city', 'like', '%' . $filters['city'] . '%');
        }

        return $query->get()->map(function ($patient) {
            return [
                'ID' => $patient->id,
                'Name' => $patient->name,
                'Email' => $patient->email,
                'Phone' => $patient->phone,
                'Area' => $patient->area,
                'City' => $patient->city,
                'Total Contracts' => $patient->contracts->count(),
                'Active Contracts' => $patient->activeContracts->count(),
                'Created At' => $patient->created_at->format('Y-m-d'),
            ];
        });
    }

    /**
     * Generate employee details report
     */
    public function getEmployeeReport(array $filters = []): Collection
    {
        $query = Employee::with(['assignments', 'payouts']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        return $query->get()->map(function ($employee) {
            return [
                'ID' => $employee->id,
                'Name' => $employee->getField('first_name') . ' ' . $employee->getField('last_name'),
                'Email' => $employee->getField('email'),
                'Phone' => $employee->getField('phone'),
                'Status' => ucfirst($employee->status),
                'Total Assignments' => $employee->assignments->count(),
                'Active Assignments' => $employee->assignments()->active()->count(),
                'Total Payouts' => '$' . number_format($employee->payouts->sum('amount'), 2),
                'Created At' => $employee->created_at->format('Y-m-d'),
            ];
        });
    }

    /**
     * Generate patient-employee assignment report
     */
    public function getAssignmentReport(array $filters = []): Collection
    {
        $query = Assignment::with(['contract.patient', 'contract.service', 'employee']);

        if (!empty($filters['start_date'])) {
            $query->where('start_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('end_date', '<=', $filters['end_date']);
        }

        if (!empty($filters['patient_id'])) {
            $query->whereHas('contract', function ($q) use ($filters) {
                $q->where('patient_id', $filters['patient_id']);
            });
        }

        if (!empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        return $query->get()->map(function ($assignment) {
            return [
                'Assignment ID' => $assignment->id,
                'Contract Number' => $assignment->contract->contract_number,
                'Patient Name' => $assignment->contract->patient->name,
                'Service' => $assignment->service->name ?? ($assignment->contract->service->name ?? 'N/A'),
                'Employee Name' => $assignment->employee->getField('first_name') . ' ' . $assignment->employee->getField('last_name'),
                'Start Date' => $assignment->start_date->format('Y-m-d'),
                'End Date' => $assignment->end_date ? $assignment->end_date->format('Y-m-d') : 'Ongoing',
                'Is Emergency' => $assignment->is_emergency_replacement ? 'Yes' : 'No',
                'Status' => $assignment->is_active ? 'Active' : 'Inactive',
            ];
        });
    }

    /**
     * Generate revenue report
     */
    public function getRevenueReport(array $filters = []): Collection
    {
        $query = Payment::with(['contract.patient', 'contract.service'])->where('status', 'completed');

        if (!empty($filters['start_date'])) {
            $query->where('paid_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('paid_at', '<=', $filters['end_date']);
        }

        if (!empty($filters['payment_method'])) {
            $query->where('method', $filters['payment_method']);
        }

        if (!empty($filters['payment_type'])) {
            $query->where('payment_type', $filters['payment_type']);
        }

        return $query->get()->map(function ($payment) {
            return [
                'Payment ID' => $payment->id,
                'Type' => ucfirst(str_replace('_', ' ', $payment->payment_type)),
                'Contract Number' => $payment->contract ? $payment->contract->contract_number : 'N/A',
                'Patient Name' => $payment->contract ? $payment->contract->patient->name : 'N/A',
                'Service' => $payment->contract ? $payment->contract->service->name : 'N/A',
                'Amount' => '$' . number_format($payment->amount, 2),
                'Method' => ucfirst($payment->method),
                'Paid At' => $payment->paid_at->format('Y-m-d H:i:s'),
            ];
        });
    }

    /**
     * Export report to CSV
     */
    public function exportToCSV(Collection $data, string $filename): string
    {
        $path = storage_path('app/public/reports/' . $filename);
        
        // Ensure directory exists
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $file = fopen($path, 'w');
        
        // Add headers
        if ($data->isNotEmpty()) {
            fputcsv($file, array_keys($data->first()));
        }

        // Add data
        foreach ($data as $row) {
            fputcsv($file, $row);
        }

        fclose($file);

        return 'reports/' . $filename;
    }
}

