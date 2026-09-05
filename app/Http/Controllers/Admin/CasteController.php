<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Caste;
use Illuminate\Http\Request;

class CasteController extends Controller
{
    /**
     * Display caste listing
     */
    public function index()
    {
        $castes = Caste::withCount('employees')->ordered()->paginate(20);

        return view('admin.castes.index', compact('castes'));
    }

    /**
     * Show create form
     */
    public function create()
    {
        return view('admin.castes.create');
    }

    /**
     * Store new caste
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:castes,name',
            'is_active' => 'boolean',
            'display_order' => 'nullable|integer|min:0',
        ]);

        $validated['is_active'] = $request->has('is_active');

        // Auto-set display order if not provided
        if (!isset($validated['display_order'])) {
            $validated['display_order'] = Caste::max('display_order') + 1;
        }

        $caste = Caste::create($validated);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Caste created successfully.',
                'redirect' => route('admin.castes.index')
            ]);
        }

        return redirect()->route('admin.castes.index')
            ->with('success', 'Caste created successfully.');
    }

    /**
     * Show edit form
     */
    public function edit(Caste $caste)
    {
        return view('admin.castes.edit', compact('caste'));
    }

    /**
     * Update caste
     */
    public function update(Request $request, Caste $caste)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:castes,name,' . $caste->id,
            'is_active' => 'boolean',
            'display_order' => 'nullable|integer|min:0',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $caste->update($validated);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Caste updated successfully.',
                'redirect' => route('admin.castes.index')
            ]);
        }

        return redirect()->route('admin.castes.index')
            ->with('success', 'Caste updated successfully.');
    }

    /**
     * Delete caste
     */
    public function destroy(Caste $caste)
    {
        // Check if caste has patients or employees
        $patientsCount = $caste->patients()->count();
        $employeesCount = $caste->employees()->count();

        if ($patientsCount > 0 || $employeesCount > 0) {
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete caste. It is assigned to {$patientsCount} patient(s) and {$employeesCount} employee(s)."
                ], 422);
            }
            return redirect()->back()
                ->with('error', "Cannot delete caste. It is assigned to {$patientsCount} patient(s) and {$employeesCount} employee(s).");
        }

        $caste->delete();

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Caste deleted successfully.',
                'redirect' => route('admin.castes.index')
            ]);
        }

        return redirect()->route('admin.castes.index')
            ->with('success', 'Caste deleted successfully.');
    }
}
