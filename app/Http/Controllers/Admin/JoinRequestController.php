<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeField;
use App\Models\JoinRequest;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        if ($joinRequest->isConverted()) {
            return $this->alreadyConverted($request);
        }

        $joinRequest->update($validated + [
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Request status updated successfully.',
                'reload' => true,
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

        if ($joinRequest->isConverted()) {
            return $this->alreadyConverted($request);
        }

        $joinRequest->update([
            'status' => 'approved',
            'admin_notes' => $validated['admin_notes'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Request approved successfully. You can now convert it to an employee.',
                'reload' => true,
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

        if ($joinRequest->isConverted()) {
            return $this->alreadyConverted($request);
        }

        $joinRequest->update([
            'status' => 'rejected',
            'admin_notes' => $validated['admin_notes'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Request rejected.',
                'reload' => true,
            ]);
        }

        return redirect()->back()
            ->with('success', 'Request rejected.');
    }

    /**
     * Convert to employee
     */
    public function convertToEmployee(Request $request, JoinRequest $joinRequest)
    {
        try {
            $result = $this->convert($request, $joinRequest);
        } catch (UniqueConstraintViolationException) {
            // The duplicate check below cannot see a row another request is
            // inserting at the same moment; the unique index still catches it.
            $result = 'An employee with this email or phone already exists.';
        }

        if (is_string($result)) {
            return redirect()->back()->with('error', $result);
        }

        return redirect()->route('admin.employees.show', $result)
            ->with('success', 'Join request converted to employee successfully.');
    }

    /**
     * Create the employee, or return the reason it cannot be created.
     */
    private function convert(Request $request, JoinRequest $joinRequest): Employee|string
    {
        return DB::transaction(function () use ($request, $joinRequest) {
            // Re-read under a row lock so a double submit cannot create two
            // employees from one request.
            $joinRequest = JoinRequest::whereKey($joinRequest->getKey())->lockForUpdate()->firstOrFail();

            if ($joinRequest->isConverted()) {
                return 'This request has already been converted to an employee.';
            }

            if ($joinRequest->status !== 'approved') {
                return 'Only approved requests can be converted to employees.';
            }

            $data = $joinRequest->field_data ?? [];
            $firstName = $data['first_name'] ?? null;
            $lastName = $data['last_name'] ?? null;

            if (! $firstName && ! empty($data['name'])) {
                $parts = preg_split('/\s+/', trim((string) $data['name'])) ?: [];
                $firstName = $parts[0] ?? null;
                $lastName = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : null;
            }

            $email = ($data['email'] ?? null) ?: null;
            $phone = ($data['phone'] ?? null) ?: null;

            // employees.email and employees.phone are unique (deleted rows
            // included); say so instead of failing on the index.
            if ($email || $phone) {
                $existing = Employee::withTrashed()
                    ->where(function ($q) use ($email, $phone) {
                        if ($email) {
                            $q->orWhere('email', $email);
                        }
                        if ($phone) {
                            $q->orWhere('phone', $phone);
                        }
                    })
                    ->first();

                if ($existing) {
                    return "An employee with this email or phone already exists (#{$existing->id}).";
                }
            }

            $employee = Employee::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'alternate_phone' => ($data['alternate_phone'] ?? null) ?: null,
                'field_data' => $data,
                'created_by' => $request->user()->id,
                'status' => 'pending',
                'source' => 'join_form',
            ]);

            $joinRequest->update([
                'status' => 'converted',
                'employee_id' => $employee->id,
            ]);

            return $employee;
        });
    }

    private function alreadyConverted(Request $request)
    {
        $message = 'This request has already been converted to an employee and can no longer be changed.';

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 422);
        }

        return redirect()->back()->with('error', $message);
    }
}
