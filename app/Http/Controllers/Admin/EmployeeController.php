<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Assignment;
use App\Models\Caste;
use App\Models\DocumentType;
use App\Models\Employee;
use App\Models\EmployeeField;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('field_data', 'like', "%{$search}%");
            });
        }

        // Filter by availability (placed = on an active assignment or cover)
        if ($request->input('availability') === 'available') {
            $query->available();
        } elseif ($request->input('availability') === 'placed') {
            $query->placed();
        }

        $employees = $query->latest()->paginate(20)->withQueryString();

        // Current placements for the listed employees, grouped per employee
        $placements = Assignment::where('is_active', true)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->with(['contract.patient', 'coverFor.employee', 'covers'])
            ->orderBy('start_date')
            ->get()
            ->groupBy('employee_id');

        return view('admin.employees.index', compact('employees', 'placements'));
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
                'redirect' => route('admin.employees.index'),
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
        $relations = [
            'documents.field',
            'assignments' => fn ($q) => $q->orderByDesc('is_active')->orderByDesc('start_date')->orderByDesc('id'),
            'assignments.contract.patient',
            'assignments.service',
            'assignments.contractItem.service',
            'assignments.replacedAssignment.employee',
            'assignments.replacements.employee',
            'assignments.coverFor.employee',
            'assignments.covers',
            'creator',
            'services',
        ];

        // Pay data is only loaded for users allowed to see payroll; the
        // employee pages themselves are open to employees.view.
        $canViewPay = auth()->user()->hasPermission('payouts.view') || auth()->user()->hasPermission('payouts.manage');
        if ($canViewPay) {
            $relations['payouts'] = fn ($q) => $q->orderByDesc('period_start')->orderByDesc('id');
            $relations[] = 'salaries.creator';
        }

        $employee->load($relations);
        $fields = EmployeeField::active()->ordered()->get();

        return view('admin.employees.show', compact('employee', 'fields', 'canViewPay'));
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
        $replacedFiles = [];

        $employee->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'alternate_phone' => $validated['alternate_phone'] ?? null,
            'field_data' => $this->buildFieldData($request, $validated, $employee, $replacedFiles),
            'caste_id' => $validated['caste_id'] ?? null,
            'subcaste' => $validated['subcaste'] ?? null,
        ]);

        // Replaced files are removed only once the new paths are saved.
        foreach ($replacedFiles as [$path, $directory]) {
            $this->deleteEmployeeFile($path, $directory);
        }

        $employee->services()->sync($validated['service_ids']);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Employee updated successfully.',
                'redirect' => route('admin.employees.show', $employee),
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
                'reload' => true,
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
        // Contracts only count active assignments, so deactivating someone who
        // is still placed would leave the patient's contract looking staffed.
        // The admin has to replace or remove them on those contracts first.
        $placements = $employee->assignments()->active()->with('contract.patient')->get();

        if ($placements->isNotEmpty()) {
            $contracts = $placements
                ->map(fn (Assignment $assignment) => $assignment->contract
                    ? $assignment->contract->contract_number.' ('.($assignment->contract->patient->name ?? 'N/A').')'
                    : 'Contract #'.$assignment->contract_id)
                ->unique()
                ->implode(', ');
            $message = "This employee is still assigned to {$contracts}. Replace or remove them on those contracts before deactivating.";

            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 422);
            }

            return redirect()->back()->with('error', $message);
        }

        $employee->update(['status' => 'inactive']);

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Employee deactivated successfully.',
                'reload' => true,
            ]);
        }

        return redirect()->back()
            ->with('success', 'Employee deactivated successfully.');
    }

    /**
     * Stream the employee's photo (private storage, behind the admin login).
     */
    public function photo(Employee $employee)
    {
        $data = is_array($employee->field_data) ? $employee->field_data : [];

        return $this->employeeFileResponse(
            $data['employee_image_path'] ?? null,
            Employee::PHOTO_DIRECTORY,
            $data['employee_image_name'] ?? null
        );
    }

    /**
     * Stream one of the employee's uploaded documents.
     */
    public function document(Employee $employee, string $index)
    {
        $document = $employee->uploadedDocuments()[(int) $index] ?? [];

        return $this->employeeFileResponse(
            $document['file_path'] ?? null,
            Employee::DOCUMENT_DIRECTORY,
            $document['file_name'] ?? null
        );
    }

    private function employeeFileResponse(mixed $path, string $directory, mixed $name)
    {
        $disk = Employee::fileDisk($path, $directory);
        abort_if($disk === null, 404);

        $storage = Storage::disk($disk);

        // Only the types the upload rules accept open in the browser; anything
        // else downloads, so a stored file can never render as a page here.
        $inline = in_array($storage->mimeType($path), ['image/jpeg', 'image/png', 'application/pdf'], true);

        return $storage->response(
            $path,
            $this->downloadName($name, $path),
            ['X-Content-Type-Options' => 'nosniff'],
            $inline ? 'inline' : 'attachment'
        );
    }

    /**
     * Name for the Content-Disposition header. Stored names came from the
     * uploader's browser, and Symfony refuses path separators (and control
     * characters make no sense in a header), so those are stripped. When
     * nothing usable is left, the stored file's own name is used.
     */
    private function downloadName(mixed $name, string $path): string
    {
        $clean = is_string($name) ? preg_replace('/[\p{Cc}\p{Cf}\/\\\\]+/u', '', $name) : null;
        $clean = is_string($clean) ? trim($clean) : '';

        // The ASCII fallback Laravel derives from the name must be clean too.
        if ($clean === '' || preg_match('/[\/\\\\]/', Str::ascii($clean))) {
            return basename($path);
        }

        return $clean;
    }

    /**
     * Delete a file that belonged to this employee, from whichever disk holds it.
     */
    private function deleteEmployeeFile(mixed $path, string $directory): void
    {
        if ($disk = Employee::fileDisk($path, $directory)) {
            Storage::disk($disk)->delete($path);
        }
    }

    /**
     * Build employee field_data payload. Paths of files replaced by new
     * uploads are collected in $replacedFiles for the caller to delete.
     */
    private function buildFieldData(Request $request, array $validated, ?Employee $employee = null, array &$replacedFiles = []): array
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
        $data['documents'] = $this->buildDocuments($request, $validated['documents'] ?? [], $employee, $replacedFiles);

        if ($request->hasFile('employee_image')) {
            if (! empty($data['employee_image_path'])) {
                $replacedFiles[] = [$data['employee_image_path'], Employee::PHOTO_DIRECTORY];
            }

            $employeeImage = $request->file('employee_image');
            $data['employee_image_path'] = $employeeImage->store(Employee::PHOTO_DIRECTORY, Employee::FILE_DISK);
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
            if (! is_array($row)) {
                continue;
            }

            $block = [
                'company_name' => trim((string) ($row['company_name'] ?? '')),
                'job_title' => trim((string) ($row['job_title'] ?? '')),
                'employment_type' => $row['employment_type'] ?? null,
                'start_date' => $row['start_date'] ?? null,
                'end_date' => $row['end_date'] ?? null,
                'currently_working' => ! empty($row['currently_working']),
                'last_salary' => $row['last_salary'] ?? null,
                'reason_for_leaving' => trim((string) ($row['reason_for_leaving'] ?? '')),
                'responsibilities' => trim((string) ($row['responsibilities'] ?? '')),
            ];

            $hasValue = collect($block)
                ->reject(function ($value, $key) {
                    return $key === 'currently_working';
                })
                ->contains(fn ($value) => ! is_null($value) && $value !== '');

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
            if (! is_array($row)) {
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
    private function buildDocuments(Request $request, array $documents, ?Employee $employee, array &$replacedFiles): array
    {
        $documentTypeMap = DocumentType::query()->pluck('name', 'id')->all();
        $normalized = [];

        // A row's existing_file is only a reference: it is honoured when it
        // names one of this employee's stored documents, and each stored
        // document can be claimed once. Client paths are never used as-is.
        $storedByPath = [];
        foreach ($employee?->uploadedDocuments() ?? [] as $stored) {
            if (is_string($stored['file_path'] ?? null)) {
                $storedByPath[$stored['file_path']] = $stored;
            }
        }

        foreach ($documents as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $typeId = $row['type'] ?? null;
            $typeName = $typeId ? ($documentTypeMap[$typeId] ?? null) : null;
            $existingPath = $row['existing_file'] ?? null;
            $existing = is_string($existingPath) ? ($storedByPath[$existingPath] ?? null) : null;
            if ($existing) {
                unset($storedByPath[$existingPath]);
            }
            $uploadedFile = $request->file("documents.$index.file");

            $filePath = $existing['file_path'] ?? null;
            $fileName = $existing['file_name'] ?? null;

            if ($uploadedFile) {
                if ($filePath) {
                    $replacedFiles[] = [$filePath, Employee::DOCUMENT_DIRECTORY];
                }

                $filePath = $uploadedFile->store(Employee::DOCUMENT_DIRECTORY, Employee::FILE_DISK);
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
