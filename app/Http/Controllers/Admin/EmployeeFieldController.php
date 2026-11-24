<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeField;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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

        $validated['is_required'] = $request->boolean('is_required');
        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_document'] = $request->boolean('is_document');
        $validated['is_mandatory'] = false; // Only seeded fields are mandatory
        $validated['options'] = $this->selectOptions($validated);

        // The column is "order" (the form calls it display_order); new fields
        // go to the end unless a position is given.
        $validated['order'] = $validated['display_order'] ?? ((int) EmployeeField::max('order') + 1);
        unset($validated['display_order']);

        EmployeeField::create($validated);

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
        // A mandatory field's definition is fixed: it can only be switched
        // on or off and moved. Everything else in the request is ignored.
        if ($employeeField->is_mandatory) {
            $validated = $request->validate([
                'is_active' => 'boolean',
                'display_order' => 'nullable|integer|min:0',
            ]);

            $employeeField->update([
                'is_active' => $request->boolean('is_active'),
                'order' => $validated['display_order'] ?? $employeeField->order,
            ]);
        } else {
            $validated = $request->validate([
                'name' => 'required|string|max:100|unique:employee_fields,name,'.$employeeField->id,
                'label' => 'required|string|max:255',
                'type' => 'required|in:text,textarea,email,phone,number,date,select,file',
                'options' => 'nullable|string',
                'is_required' => 'boolean',
                'is_active' => 'boolean',
                'is_document' => 'boolean',
                'display_order' => 'nullable|integer|min:0',
            ]);

            $validated['is_required'] = $request->boolean('is_required');
            $validated['is_active'] = $request->boolean('is_active');
            $validated['is_document'] = $request->boolean('is_document');

            if ($validated['type'] === 'select') {
                $validated['options'] = $this->selectOptions($validated);
            } else {
                // Other types may carry their own metadata (e.g. file formats); keep it.
                unset($validated['options']);
            }

            // Blank keeps the current position.
            if (isset($validated['display_order'])) {
                $validated['order'] = $validated['display_order'];
            }
            unset($validated['display_order']);

            $employeeField->update($validated);
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Employee field updated successfully.',
                'redirect' => route('admin.employee-fields.index'),
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
                    'message' => 'Mandatory fields cannot be deleted.',
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
                'redirect' => route('admin.employee-fields.index'),
            ]);
        }

        return redirect()->route('admin.employee-fields.index')
            ->with('success', 'Employee field deleted successfully.');
    }

    /**
     * Select options as the list the model's array cast expects (the form
     * sends one comma-separated string). Other field types get none.
     */
    private function selectOptions(array $validated): ?array
    {
        if ($validated['type'] !== 'select') {
            return null;
        }

        $options = EmployeeField::parseOptions($validated['options'] ?? null);

        if ($options === []) {
            throw ValidationException::withMessages([
                'options' => 'Enter at least one option for a select field, separated by commas.',
            ]);
        }

        return $options;
    }
}
