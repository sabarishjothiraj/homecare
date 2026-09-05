<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->boolean('same_as_present_address')) {
            $this->merge([
                'permanent_address' => $this->input('present_address', []),
            ]);
        }
    }

    public function rules(): array
    {
        /** @var Employee|null $employee */
        $employee = $this->route('employee');
        $hasExistingImage = !empty($employee?->field_data['employee_image_path']);

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('employees', 'email')->ignore($employee?->id)],
            'phone' => ['required', 'regex:/^[0-9]{7,15}$/', Rule::unique('employees', 'phone')->ignore($employee?->id)],
            'alternate_phone' => ['nullable', 'regex:/^[0-9]{7,15}$/'],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['required', 'exists:services,id'],

            'caste_id' => ['nullable', 'exists:castes,id'],
            'subcaste' => ['nullable', 'string', 'max:100'],

            'present_address.address_line1' => ['required', 'string', 'max:255'],
            'present_address.address_line2' => ['nullable', 'string', 'max:255'],
            'present_address.area' => ['nullable', 'string', 'max:255'],
            'present_address.landmark' => ['nullable', 'string', 'max:255'],
            'present_address.city' => ['required', 'string', 'max:100'],
            'present_address.state' => ['required', 'string', 'max:100'],
            'present_address.country' => ['required', 'string', 'max:100'],
            'present_address.pincode' => ['required', 'numeric'],

            'permanent_address.address_line1' => ['required', 'string', 'max:255'],
            'permanent_address.address_line2' => ['nullable', 'string', 'max:255'],
            'permanent_address.area' => ['nullable', 'string', 'max:255'],
            'permanent_address.landmark' => ['nullable', 'string', 'max:255'],
            'permanent_address.city' => ['required', 'string', 'max:100'],
            'permanent_address.state' => ['required', 'string', 'max:100'],
            'permanent_address.country' => ['required', 'string', 'max:100'],
            'permanent_address.pincode' => ['required', 'numeric'],

            'specialization' => ['nullable', 'string'],

            'experience' => ['nullable', 'array'],
            'experience.*.company_name' => ['nullable', 'string', 'max:255'],
            'experience.*.job_title' => ['nullable', 'string', 'max:255'],
            'experience.*.employment_type' => ['nullable', 'in:full_time,part_time,contract,internship,freelance'],
            'experience.*.start_date' => ['nullable', 'date'],
            'experience.*.end_date' => ['nullable', 'date'],
            'experience.*.currently_working' => ['nullable', 'boolean'],
            'experience.*.last_salary' => ['nullable', 'numeric'],
            'experience.*.reason_for_leaving' => ['nullable', 'string', 'max:500'],
            'experience.*.responsibilities' => ['nullable', 'string'],

            'emergency_contacts' => ['required', 'array', 'min:2'],
            'emergency_contacts.*.name' => ['required', 'string', 'max:255'],
            'emergency_contacts.*.relationship' => ['required', 'string', 'max:255'],
            'emergency_contacts.*.phone' => ['required', 'regex:/^[0-9]{7,15}$/'],
            'emergency_contacts.*.alternate_phone' => ['nullable', 'regex:/^[0-9]{7,15}$/'],

            'employee_image' => [
                $hasExistingImage ? 'nullable' : 'required',
                'image',
                'mimes:jpg,jpeg,png',
                'max:2048',
            ],

            'documents' => ['nullable', 'array'],
            'documents.*.type' => ['nullable', 'exists:document_types,id'],
            'documents.*.file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'documents.*.existing_file' => ['nullable', 'string'],
            'documents.*.existing_file_name' => ['nullable', 'string'],

            'same_as_present_address' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateExperience($validator);
            $this->validateDocuments($validator);
        });
    }

    private function validateExperience(Validator $validator): void
    {
        $experience = $this->input('experience', []);

        foreach ($experience as $index => $row) {
            if (!is_array($row) || !$this->hasAnyRowValue($row, [
                'company_name', 'job_title', 'employment_type', 'start_date', 'end_date', 'currently_working', 'last_salary', 'reason_for_leaving', 'responsibilities',
            ])) {
                continue;
            }

            if (empty($row['company_name'])) {
                $validator->errors()->add("experience.$index.company_name", 'Company name is required when adding experience.');
            }

            if (empty($row['start_date'])) {
                $validator->errors()->add("experience.$index.start_date", 'Start date is required when adding experience.');
            }

            $currentlyWorking = !empty($row['currently_working']);
            if (!$currentlyWorking && empty($row['end_date'])) {
                $validator->errors()->add("experience.$index.end_date", 'End date is required unless currently working is selected.');
            }

            if (!empty($row['start_date']) && !empty($row['end_date']) && $row['end_date'] < $row['start_date']) {
                $validator->errors()->add("experience.$index.end_date", 'End date must be after or equal to start date.');
            }
        }
    }

    private function validateDocuments(Validator $validator): void
    {
        $documents = $this->input('documents', []);

        foreach ($documents as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $hasType = !empty($row['type']);
            $hasFile = $this->hasFile("documents.$index.file");
            $hasExistingFile = !empty($row['existing_file']);

            if (!$hasType && !$hasFile && !$hasExistingFile) {
                continue;
            }

            if (!$hasType) {
                $validator->errors()->add("documents.$index.type", 'Document type is required when adding a document row.');
            }

            if (!$hasFile && !$hasExistingFile) {
                $validator->errors()->add("documents.$index.file", 'Document file is required when a document type is selected.');
            }
        }
    }

    private function hasAnyRowValue(array $row, array $keys): bool
    {
        foreach ($keys as $key) {
            if ($key === 'currently_working') {
                if (!empty($row[$key])) {
                    return true;
                }
                continue;
            }

            if (isset($row[$key]) && $row[$key] !== '' && $row[$key] !== null) {
                return true;
            }
        }

        return false;
    }
}
