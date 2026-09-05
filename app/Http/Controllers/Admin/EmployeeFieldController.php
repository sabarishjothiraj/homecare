<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeField;
use Illuminate\Http\Request;

class EmployeeFieldController extends Controller
{
    /**
     * Display employee field listing
     */
    public function index()
    {
        $fields = EmployeeField::ordered()->get();

        return view('admin.employee-fields.index', compact('fields'));
    }

    /**
     * Show create form
     */
    public function create()
    {
        return view('admin.employee-fields.create');
    }

    /**
     * Store new field
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:employee_fields,name',
            'label' => 'required|string|max:255',
            'type' => 'required|in:text,textarea,email,phone,number,date,select,file',
            'options' => 'nullable|string',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'is_document' => 'boolean',
            'display_order' => 'nullable|integer|min:0',
        ]);

        $validated['is_required'] = $request->has('is_required');
        $validated['is_active'] = $request->has('is_active');
        $validated['is_document'] = $request->has('is_document');
        $validated['is_mandatory'] = false; // Only seeded fields are mandatory

        // Auto-set display order if not provided
        if (!isset($validated['display_order'])) {
            $validated['display_order'] = EmployeeField::max('display_order') + 1;
        }

        $field = EmployeeField::create($validated);

        return redirect()->route('admin.employee-fields.index')
            ->with('success', 'Employee field created successfully.');
    }

    /**
     * Show edit form
     */
    public function edit(EmployeeField $employeeField)
    {
        return view('admin.employee-fields.edit', compact('employeeField'));
    }

    /**
     * Update field
     */
    public function update(Request $request, EmployeeField $employeeField)
    {
        // Cannot edit mandatory fields
        if ($employeeField->is_mandatory) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mandatory fields cannot be edited. You can only activate/deactivate them.'
                ], 422);
            }
            return redirect()->back()
                ->with('error', 'Mandatory fields cannot be edited. You can only activate/deactivate them.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:employee_fields,name,' . $employeeField->id,
            'label' => 'required|string|max:255',
            'type' => 'required|in:text,textarea,email,phone,number,date,select,file',
            'options' => 'nullable|string',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'is_document' => 'boolean',
            'display_order' => 'nullable|integer|min:0',
        ]);

        $validated['is_required'] = $request->has('is_required');
        $validated['is_active'] = $request->has('is_active');
        $validated['is_document'] = $request->has('is_document');

        $employeeField->update($validated);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Employee field updated successfully.',
                'redirect' => route('admin.employee-fields.index')
            ]);
        }

        return redirect()->route('admin.employee-fields.index')
            ->with('success', 'Employee field updated successfully.');
    }

    /**
     * Delete field
     */
    public function destroy(EmployeeField $employeeField)
    {
        // Cannot delete mandatory fields
        if ($employeeField->is_mandatory) {
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mandatory fields cannot be deleted.'
                ], 422);
            }
            return redirect()->back()
                ->with('error', 'Mandatory fields cannot be deleted.');
        }

        $employeeField->delete();

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Employee field deleted successfully.',
                'redirect' => route('admin.employee-fields.index')
            ]);
        }

        return redirect()->route('admin.employee-fields.index')
            ->with('success', 'Employee field deleted successfully.');
    }
}

