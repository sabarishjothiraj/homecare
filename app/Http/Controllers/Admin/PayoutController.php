<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeePayout;
use App\Models\Employee;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PayoutController extends Controller
{
    /**
     * Display payout listing
     */
    public function index(Request $request)
    {
        $query = EmployeePayout::with('employee');

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by employee
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        // Filter by payment method
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        // Date range filter
        if ($request->filled('start_date')) {
            $query->whereDate('payout_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('payout_date', '<=', $request->end_date);
        }

        $payouts = $query->latest('payout_date')->paginate(20);

        $employees = Employee::active()->get();

        // Calculate totals
        $totalPaid = EmployeePayout::paid()->sum('amount');
        $totalPending = EmployeePayout::pending()->sum('amount');

        return view('admin.payouts.index', compact('payouts', 'employees', 'totalPaid', 'totalPending'));
    }

    /**
     * Show create form
     */
    public function create()
    {
        $employees = Employee::active()->get();

        return view('admin.payouts.create', compact('employees'));
    }

    /**
     * Store new payout
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'amount' => 'required|numeric|min:0',
            'payout_date' => 'required|date',
            'payment_method' => 'required|in:cash,bank_transfer,cheque,online',
            'description' => 'nullable|string|max:500',
            'notes' => 'nullable|string',
        ]);

        $validated['status'] = 'pending';

        $payout = EmployeePayout::create($validated);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Payout created successfully.',
                'redirect' => route('admin.payouts.show', $payout)
            ]);
        }

        return redirect()->route('admin.payouts.show', $payout)
            ->with('success', 'Payout created successfully.');
    }

    /**
     * Show payout details
     */
    public function show(EmployeePayout $payout)
    {
        $payout->load('employee');

        return view('admin.payouts.show', compact('payout'));
    }

    /**
     * Show edit form
     */
    public function edit(EmployeePayout $payout)
    {
        if ($payout->status === 'paid') {
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot edit a paid payout.'
                ], 422);
            }
            return redirect()->back()
                ->with('error', 'Cannot edit a paid payout.');
        }

        $employees = Employee::active()->get();

        return view('admin.payouts.edit', compact('payout', 'employees'));
    }

    /**
     * Update payout
     */
    public function update(Request $request, EmployeePayout $payout)
    {
        if ($payout->status === 'paid') {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update a paid payout.'
                ], 422);
            }
            return redirect()->back()
                ->with('error', 'Cannot update a paid payout.');
        }

        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'amount' => 'required|numeric|min:0',
            'payout_date' => 'required|date',
            'payment_method' => 'required|in:cash,bank_transfer,cheque,online',
            'description' => 'nullable|string|max:500',
            'notes' => 'nullable|string',
        ]);

        $payout->update($validated);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Payout updated successfully.',
                'redirect' => route('admin.payouts.show', $payout)
            ]);
        }

        return redirect()->route('admin.payouts.show', $payout)
            ->with('success', 'Payout updated successfully.');
    }

    /**
     * Mark payout as paid
     */
    public function markPaid(Request $request, EmployeePayout $payout)
    {
        $validated = $request->validate([
            'transaction_reference' => 'nullable|string|max:255',
            'paid_notes' => 'nullable|string',
        ]);

        $payout->update([
            'status' => 'paid',
            'paid_at' => Carbon::now(),
            'transaction_reference' => $validated['transaction_reference'] ?? null,
            'notes' => $payout->notes . "\n\nPaid Notes: " . ($validated['paid_notes'] ?? ''),
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Payout marked as paid successfully.',
                'redirect' => route('admin.payouts.show', $payout)
            ]);
        }

        return redirect()->route('admin.payouts.show', $payout)
            ->with('success', 'Payout marked as paid successfully.');
    }

    /**
     * Delete payout
     */
    public function destroy(EmployeePayout $payout)
    {
        if ($payout->status === 'paid') {
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete a paid payout.'
                ], 422);
            }
            return redirect()->back()
                ->with('error', 'Cannot delete a paid payout.');
        }

        $payout->delete();

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Payout deleted successfully.',
                'redirect' => route('admin.payouts.index')
            ]);
        }

        return redirect()->route('admin.payouts.index')
            ->with('success', 'Payout deleted successfully.');
    }
}

