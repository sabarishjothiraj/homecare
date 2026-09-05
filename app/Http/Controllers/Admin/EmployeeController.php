<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Caste;
use App\Models\DocumentType;
use App\Models\Employee;
use App\Models\EmployeeField;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EmployeeController extends Controller
{
    /**
     * Display employee listing
     */
    public function index(Request $request)
    {
        $query = Employee::with(['documents', 'creator']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('field_data', 'like', "%{$search}%");
            });
        }

        $employees = $query->latest()->paginate(20);

        return view('admin.employees.index', compact('employees'));
    }

    /**
     * Show create form
     */
    public function create()
    {
        $castes = Caste::active()->ordered()->get();
        $services = Service::active()->ordered()->get();
        $documentTypes = DocumentType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.employees.create', compact('castes', 'documentTypes', 'services'));
    }

    /**
     * Store new employee
     */
    public function store(StoreEmployeeRequest $request)
    {
        $validated = $request->validated();

        $employee = Employee::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'alternate_phone' => $validated['alternate_phone'] ?? null,
            'field_data' => $this->buildFieldData($request, $validated),
            'caste_id' => $validated['caste_id'] ?? null,
            'subcaste' => $validated['subcaste'] ?? null,
            'created_by' => auth()->id(),
            'status' => 'pending',
        ]);

        $employee->services()->sync($validated['service_ids']);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Employee created successfully.',
                'redirect' => route('admin.employees.index')
            ]);
        }

        return redirect()->route('admin.employees.index')
            ->with('success', 'Employee created successfully.');
    }

    /**
     * Show employee details
     */
    public function show(Employee $employee)
    {
        $employee->load(['documents.field', 'assignments.contract', 'payouts', 'creator', 'services']);
        $fields = EmployeeField::active()->ordered()->get();
        
        return view('admin.employees.show', compact('employee', 'fields'));
    }

    /**
     * Show edit form
     */
    public function edit(Employee $employee)
    {
        $castes = Caste::active()->ordered()->get();
        $services = Service::active()->ordered()->get();
        $documentTypes = DocumentType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.employees.edit', compact('employee', 'castes', 'documentTypes', 'services'));
    }

    /**
     * Update employee
     */
    public function update(UpdateEmployeeRequest $request, Employee $employee)
    {
        $validated = $request->validated();

        $employee->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'alternate_phone' => $validated['alternate_phone'] ?? null,
            'field_data' => $this->buildFieldData($request, $validated, $employee),
            'caste_id' => $validated['caste_id'] ?? null,
            'subcaste' => $validated['subcaste'] ?? null,
        ]);

        $employee->services()->sync($validated['service_ids']);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Employee updated successfully.',
                'redirect' => route('admin.employees.show', $employee)
            ]);
        }

        return redirect()->route('admin.employees.show', $employee)
            ->with('success', 'Employee updated successfully.');
    }

    /**
     * Activate employee
     */
    public function activate(Employee $employee)
    {
        $employee->update(['status' => 'active']);

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Employee activated successfully.',
                'reload' => true
            ]);
        }

        return redirect()->back()
            ->with('success', 'Employee activated successfully.');
    }

    /**
     * Deactivate employee
     */
    public function deactivate(Employee $employee)
    {
        $employee->update(['status' => 'inactive']);

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Employee deactivated successfully.',
                'reload' => true
            ]);
        }

        return redirect()->back()
            ->with('success', 'Employee deactivated successfully.');
    }

    /**
     * Build employee field_data payload.
     */
    private function buildFieldData(Request $request, array $validated, ?Employee $employee = null): array
    {
        $existingData = $employee?->field_data ?? [];
        $data = is_array($existingData) ? $existingData : [];

        $data['present_address'] = $validated['present_address'];
        $data['permanent_address'] = $validated['permanent_address'];
        $data['first_name'] = $validated['first_name'];
        $data['last_name'] = $validated['last_name'];
        $data['email'] = $validated['email'];
        $data['phone'] = $validated['phone'];
        $data['alternate_phone'] = $validated['alternate_phone'] ?? null;
        $data['specialization'] = $validated['specialization'] ?? null;
        $data['experience'] = $this->normalizeExperience($validated['experience'] ?? []);
        $data['emergency_contacts'] = $this->normalizeEmergencyContacts($validated['emergency_contacts'] ?? []);
        $data['documents'] = $this->buildDocuments($request, $validated['documents'] ?? []);

        if ($request->hasFile('employee_image')) {
            if (!empty($data['employee_image_path'])) {
                Storage::disk('public')->delete($data['employee_image_path']);
            }

            $employeeImage = $request->file('employee_image');
            $data['employee_image_path'] = $employeeImage->store('employee_images', 'public');
            $data['employee_image_name'] = $employeeImage->getClientOriginalName();
        }

        return $data;
    }

    /**
     * Keep only non-empty experience blocks.
     */
    private function normalizeExperience(array $experience): array
    {
        $normalized = [];

        foreach ($experience as $row) {
            if (!is_array($row)) {
                continue;
            }

            $block = [
                'company_name' => trim((string) ($row['company_name'] ?? '')),
                'job_title' => trim((string) ($row['job_title'] ?? '')),
                'employment_type' => $row['employment_type'] ?? null,
                'start_date' => $row['start_date'] ?? null,
                'end_date' => $row['end_date'] ?? null,
                'currently_working' => !empty($row['currently_working']),
                'last_salary' => $row['last_salary'] ?? null,
                'reason_for_leaving' => trim((string) ($row['reason_for_leaving'] ?? '')),
                'responsibilities' => trim((string) ($row['responsibilities'] ?? '')),
            ];

            $hasValue = collect($block)
                ->reject(function ($value, $key) {
                    return $key === 'currently_working';
                })
                ->contains(fn ($value) => !is_null($value) && $value !== '');

            if ($hasValue || $block['currently_working']) {
                $normalized[] = $block;
            }
        }

        return $normalized;
    }

    /**
     * Keep non-empty emergency contacts.
     */
    private function normalizeEmergencyContacts(array $contacts): array
    {
        $normalized = [];

        foreach ($contacts as $row) {
            if (!is_array($row)) {
                continue;
            }

            $contact = [
                'name' => trim((string) ($row['name'] ?? '')),
                'relationship' => trim((string) ($row['relationship'] ?? '')),
                'phone' => trim((string) ($row['phone'] ?? '')),
                'alternate_phone' => trim((string) ($row['alternate_phone'] ?? '')),
            ];

            if (collect($contact)->contains(fn ($value) => $value !== '')) {
                $normalized[] = $contact;
            }
        }

        return $normalized;
    }

    /**
     * Store document uploads and keep existing files where applicable.
     */
    private function buildDocuments(Request $request, array $documents): array
    {
        $documentTypeMap = DocumentType::query()->pluck('name', 'id')->all();
        $normalized = [];

        foreach ($documents as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $typeId = $row['type'] ?? null;
            $typeName = $typeId ? ($documentTypeMap[$typeId] ?? null) : null;
            $existingFile = $row['existing_file'] ?? null;
            $existingFileName = $row['existing_file_name'] ?? null;
            $uploadedFile = $request->file("documents.$index.file");

            $filePath = $existingFile;
            $fileName = $existingFileName;

            if ($uploadedFile) {
                if (!empty($existingFile)) {
                    Storage::disk('public')->delete($existingFile);
                }

                $filePath = $uploadedFile->store('employee_documents_dynamic', 'public');
                $fileName = $uploadedFile->getClientOriginalName();
            }

            if ($typeId && $filePath) {
                $normalized[] = [
                    'type' => (int) $typeId,
                    'type_name' => $typeName,
                    'file_path' => $filePath,
                    'file_name' => $fileName,
                ];
            }
        }

        return $normalized;
    }
}

