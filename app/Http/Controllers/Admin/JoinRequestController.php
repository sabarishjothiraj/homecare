<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JoinRequest;
use App\Models\Employee;
use App\Models\EmployeeField;
use Illuminate\Http\Request;

class JoinRequestController extends Controller
{
    /**
     * Display join request listing
     */
    public function index(Request $request)
    {
        $query = JoinRequest::query();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $requests = $query->latest()->paginate(20);

        // Count by status
        $newCount = JoinRequest::new()->count();
        $approvedCount = JoinRequest::approved()->count();
        $rejectedCount = JoinRequest::rejected()->count();

        return view('admin.join-requests.index', compact('requests', 'newCount', 'approvedCount', 'rejectedCount'));
    }

    /**
     * Show request details
     */
    public function show(JoinRequest $joinRequest)
    {
        $fields = EmployeeField::active()->ordered()->get();

        return view('admin.join-requests.show', compact('joinRequest', 'fields'));
    }

    /**
     * Update request status
     */
    public function update(Request $request, JoinRequest $joinRequest)
    {
        $validated = $request->validate([
            'status' => 'required|in:new,approved,rejected',
        ]);

        $joinRequest->update($validated);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Request status updated successfully.',
                'reload' => true
            ]);
        }

        return redirect()->back()
            ->with('success', 'Request status updated successfully.');
    }

    /**
     * Approve request
     */
    public function approve(Request $request, JoinRequest $joinRequest)
    {
        $validated = $request->validate([
            'admin_notes' => 'nullable|string',
        ]);

        $joinRequest->update([
            'status' => 'approved',
            'admin_notes' => $validated['admin_notes'] ?? null,
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Request approved successfully. You can now convert it to an employee.',
                'reload' => true
            ]);
        }

        return redirect()->back()
            ->with('success', 'Request approved successfully. You can now convert it to an employee.');
    }

    /**
     * Reject request
     */
    public function reject(Request $request, JoinRequest $joinRequest)
    {
        $validated = $request->validate([
            'admin_notes' => 'required|string',
        ]);

        $joinRequest->update([
            'status' => 'rejected',
            'admin_notes' => $validated['admin_notes'],
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Request rejected.',
                'reload' => true
            ]);
        }

        return redirect()->back()
            ->with('success', 'Request rejected.');
    }

    /**
     * Convert to employee
     */
    public function convertToEmployee(JoinRequest $joinRequest)
    {
        if ($joinRequest->status !== 'approved') {
            return redirect()->back()
                ->with('error', 'Only approved requests can be converted to employees.');
        }

        // Create employee from join request data
        $employee = Employee::create([
            'field_data' => $joinRequest->field_data,
            'status' => 'pending',
        ]);

        // Update join request
        $joinRequest->update([
            'converted_to_employee_id' => $employee->id,
        ]);

        return redirect()->route('admin.employees.show', $employee)
            ->with('success', 'Join request converted to employee successfully.');
    }
}

