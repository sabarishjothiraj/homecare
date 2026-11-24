<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PatientController extends Controller
{
    /**
     * Display patient listing
     */
    public function index(Request $request)
    {
        $query = Patient::with('creator')->withCount(['contracts', 'activeContracts']);

        // Filter by status
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->active();
            } elseif ($request->status === 'inactive') {
                $query->inactive();
            }
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $patients = $query->latest()->paginate(20);

        return view('admin.patients.index', compact('patients'));
    }

    /**
     * Show create form
     */
    public function create()
    {
        return view('admin.patients.create');
    }

    /**
     * Store new patient
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            // Deleted patients cannot be restored from the UI, so they must
            // not keep holding their email against a returning patient.
            'email' => ['required', 'email', 'max:255', Rule::unique('patients', 'email')->whereNull('deleted_at')],
            'phone' => 'required|digits:10',
            'address1' => 'required|string|max:255',
            'address2' => 'nullable|string|max:255',
            'area' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'pincode' => 'required|string|max:20',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|digits:10',
            'medical_notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');
        $validated['created_by'] = auth()->id();

        $patient = Patient::create($validated);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Patient created successfully.',
                'redirect' => route('admin.patients.show', $patient),
            ]);
        }

        return redirect()->route('admin.patients.show', $patient)
            ->with('success', 'Patient created successfully.');
    }

    /**
     * Show patient details
     */
    public function show(Patient $patient)
    {
        $patient->load(['contracts.service', 'activeContracts', 'creator']);

        return view('admin.patients.show', compact('patient'));
    }

    /**
     * Show edit form
     */
    public function edit(Patient $patient)
    {
        return view('admin.patients.edit', compact('patient'));
    }

    /**
     * Update patient
     */
    public function update(Request $request, Patient $patient)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('patients', 'email')->ignore($patient->id)->whereNull('deleted_at')],
            'phone' => 'required|digits:10',
            'address1' => 'required|string|max:255',
            'address2' => 'nullable|string|max:255',
            'area' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'pincode' => 'required|string|max:20',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|digits:10',
            'medical_notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $patient->update($validated);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Patient updated successfully.',
                'redirect' => route('admin.patients.show', $patient),
            ]);
        }

        return redirect()->route('admin.patients.show', $patient)
            ->with('success', 'Patient updated successfully.');
    }

    /**
     * Delete patient
     */
    public function destroy(Patient $patient)
    {
        // Expired contracts can still have staff placed or money owed, and
        // deleting the patient would orphan them.
        if ($patient->hasUnsettledContracts()) {
            $message = 'Cannot delete a patient until all of their contracts are closed and no staff are still placed on them.';

            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 422);
            }

            return redirect()->back()->with('error', $message);
        }

        $patient->delete();

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Patient deleted successfully.',
                'redirect' => route('admin.patients.index'),
            ]);
        }

        return redirect()->route('admin.patients.index')
            ->with('success', 'Patient deleted successfully.');
    }
}
