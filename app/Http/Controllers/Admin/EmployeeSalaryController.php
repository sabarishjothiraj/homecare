<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeSalaryController extends Controller
{
    /**
     * Set or revise an employee's salary from a date
     */
    public function store(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'pay_type' => ['required', Rule::in(array_keys(EmployeeSalary::PAY_TYPES))],
            'amount' => 'required|numeric|min:1|max:9999999',
            'effective_from' => 'required|date',
            'notes' => 'nullable|string|max:500',
        ]);

        // Same effective date replaces that revision instead of stacking a duplicate
        EmployeeSalary::updateOrCreate(
            ['employee_id' => $employee->id, 'effective_from' => $validated['effective_from']],
            [
                'pay_type' => $validated['pay_type'],
                'amount' => $validated['amount'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]
        );

        return redirect()->route('admin.employees.show', $employee)->with('success', 'Salary saved.');
    }

    public function destroy(Employee $employee, EmployeeSalary $salary)
    {
        abort_unless($salary->employee_id === $employee->id, 404);

        $salary->delete();

        return redirect()->route('admin.employees.show', $employee)->with('success', 'Salary revision removed.');
    }
}
