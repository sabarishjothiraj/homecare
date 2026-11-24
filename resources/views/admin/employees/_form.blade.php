@php
    $isEdit = isset($employee) && $employee;
    $fieldData = $isEdit ? ($employee->field_data ?? []) : [];

    $presentAddress = old('present_address', $fieldData['present_address'] ?? []);
    $permanentAddress = old('permanent_address', $fieldData['permanent_address'] ?? []);

    $experienceItems = old('experience', $fieldData['experience'] ?? []);
    if (empty($experienceItems)) {
        $experienceItems = [[]];
    }

    $specialization = old('specialization', $fieldData['specialization'] ?? '');

    $emergencyContacts = old('emergency_contacts', $fieldData['emergency_contacts'] ?? []);
    if (empty($emergencyContacts)) {
        $emergencyContacts = [[], []];
    }
    while (count($emergencyContacts) < 2) {
        $emergencyContacts[] = [];
    }

    $documents = old('documents', $fieldData['documents'] ?? []);
    if (empty($documents)) {
        $documents = [[]];
    }

    // Uploads are private: link to them through the authenticated file routes.
    $employeeImagePath = $fieldData['employee_image_path'] ?? null;
    $employeeImageUrl = ($isEdit && $employeeImagePath) ? route('admin.employees.photo', $employee) : null;

    $storedDocumentIndexes = [];
    if ($isEdit) {
        foreach ($employee->uploadedDocuments() as $storedIndex => $storedDocument) {
            if (is_string($storedDocument['file_path'] ?? null)) {
                $storedDocumentIndexes[$storedDocument['file_path']] = $storedIndex;
            }
        }
    }

    $sameAsPresent = old('same_as_present_address', false);

    $employmentTypeOptions = [
        'full_time' => 'Full Time',
        'part_time' => 'Part Time',
        'contract' => 'Contract',
        'internship' => 'Internship',
        'freelance' => 'Freelance',
    ];
@endphp

<form action="{{ $formAction }}" method="POST" enctype="multipart/form-data" data-ajax="true">
    @csrf
    @if($formMethod !== 'POST')
        @method($formMethod)
    @endif

    <div class="row g-3 mb-2">
        <div class="col-md-6">
            <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('first_name') is-invalid @enderror" id="first_name" name="first_name" value="{{ old('first_name', $isEdit ? $employee->first_name : ($fieldData['first_name'] ?? '')) }}" required>
            @error('first_name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('last_name') is-invalid @enderror" id="last_name" name="last_name" value="{{ old('last_name', $isEdit ? $employee->last_name : ($fieldData['last_name'] ?? '')) }}" required>
            @error('last_name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $isEdit ? $employee->email : ($fieldData['email'] ?? '')) }}" required>
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <label for="phone" class="form-label">Phone <span class="text-danger">*</span></label>
            <input type="tel" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone', $isEdit ? $employee->phone : ($fieldData['phone'] ?? '')) }}" required>
            @error('phone')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <label for="alternate_phone" class="form-label">Alternate Phone</label>
            <input type="tel" class="form-control @error('alternate_phone') is-invalid @enderror" id="alternate_phone" name="alternate_phone" value="{{ old('alternate_phone', $isEdit ? $employee->alternate_phone : ($fieldData['alternate_phone'] ?? '')) }}">
            @error('alternate_phone')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <label for="service_ids" class="form-label">Employee Categories <span class="text-danger">*</span></label>
            @php
                $selectedServiceIds = collect(old('service_ids', $isEdit ? $employee->services->pluck('id')->all() : []));
            @endphp
            <select class="form-select @error('service_ids') is-invalid @enderror" id="service_ids" name="service_ids[]" multiple required>
                @foreach($services as $service)
                    <option value="{{ $service->id }}" {{ $selectedServiceIds->contains($service->id) ? 'selected' : '' }}>{{ $service->name }}</option>
                @endforeach
            </select>
            <small class="text-muted">Hold Ctrl (Windows) or Cmd (Mac) to select multiple categories.</small>
            @error('service_ids')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            @error('service_ids.*')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <hr class="my-4">

    <div class="row g-3 mb-2">
        <div class="col-md-6">
            <label for="caste_id" class="form-label">Caste</label>
            <select class="form-select @error('caste_id') is-invalid @enderror" id="caste_id" name="caste_id">
                <option value="">Select Caste</option>
                @foreach($castes as $caste)
                    <option value="{{ $caste->id }}" {{ (string) old('caste_id', $isEdit ? $employee->caste_id : '') === (string) $caste->id ? 'selected' : '' }}>
                        {{ $caste->name }}
                    </option>
                @endforeach
            </select>
            @error('caste_id')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <label for="subcaste" class="form-label">Subcaste</label>
            <input type="text"
                   class="form-control @error('subcaste') is-invalid @enderror"
                   id="subcaste"
                   name="subcaste"
                   value="{{ old('subcaste', $isEdit ? $employee->subcaste : '') }}"
                   maxlength="100">
            @error('subcaste')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <hr class="my-4">
    <h6 class="mb-3"><i class="fas fa-map-marker-alt me-2"></i>Present Address</h6>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Address Line 1 <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('present_address.address_line1') is-invalid @enderror" name="present_address[address_line1]" value="{{ $presentAddress['address_line1'] ?? '' }}" required>
            @error('present_address.address_line1')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Address Line 2</label>
            <input type="text" class="form-control @error('present_address.address_line2') is-invalid @enderror" name="present_address[address_line2]" value="{{ $presentAddress['address_line2'] ?? '' }}">
            @error('present_address.address_line2')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label">Area</label>
            <input type="text" class="form-control @error('present_address.area') is-invalid @enderror" name="present_address[area]" value="{{ $presentAddress['area'] ?? '' }}">
            @error('present_address.area')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label">Landmark</label>
            <input type="text" class="form-control @error('present_address.landmark') is-invalid @enderror" name="present_address[landmark]" value="{{ $presentAddress['landmark'] ?? '' }}">
            @error('present_address.landmark')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label">City <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('present_address.city') is-invalid @enderror" name="present_address[city]" value="{{ $presentAddress['city'] ?? '' }}" required>
            @error('present_address.city')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label">State <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('present_address.state') is-invalid @enderror" name="present_address[state]" value="{{ $presentAddress['state'] ?? '' }}" required>
            @error('present_address.state')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label">Country <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('present_address.country') is-invalid @enderror" name="present_address[country]" value="{{ $presentAddress['country'] ?? '' }}" required>
            @error('present_address.country')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label">Pincode <span class="text-danger">*</span></label>
            <input type="number" class="form-control @error('present_address.pincode') is-invalid @enderror" name="present_address[pincode]" value="{{ $presentAddress['pincode'] ?? '' }}" required>
            @error('present_address.pincode')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <hr class="my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0"><i class="fas fa-map me-2"></i>Permanent Address</h6>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="same-as-present-address" name="same_as_present_address" value="1" {{ $sameAsPresent ? 'checked' : '' }}>
            <label class="form-check-label" for="same-as-present-address">Same as Present Address</label>
        </div>
    </div>

    <div class="row g-3" id="permanent-address-wrapper">
        <div class="col-md-6">
            <label class="form-label">Address Line 1 <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('permanent_address.address_line1') is-invalid @enderror" name="permanent_address[address_line1]" value="{{ $permanentAddress['address_line1'] ?? '' }}" required>
            @error('permanent_address.address_line1')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Address Line 2</label>
            <input type="text" class="form-control @error('permanent_address.address_line2') is-invalid @enderror" name="permanent_address[address_line2]" value="{{ $permanentAddress['address_line2'] ?? '' }}">
            @error('permanent_address.address_line2')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label">Area</label>
            <input type="text" class="form-control @error('permanent_address.area') is-invalid @enderror" name="permanent_address[area]" value="{{ $permanentAddress['area'] ?? '' }}">
            @error('permanent_address.area')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label">Landmark</label>
            <input type="text" class="form-control @error('permanent_address.landmark') is-invalid @enderror" name="permanent_address[landmark]" value="{{ $permanentAddress['landmark'] ?? '' }}">
            @error('permanent_address.landmark')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label">City <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('permanent_address.city') is-invalid @enderror" name="permanent_address[city]" value="{{ $permanentAddress['city'] ?? '' }}" required>
            @error('permanent_address.city')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label">State <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('permanent_address.state') is-invalid @enderror" name="permanent_address[state]" value="{{ $permanentAddress['state'] ?? '' }}" required>
            @error('permanent_address.state')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label">Country <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('permanent_address.country') is-invalid @enderror" name="permanent_address[country]" value="{{ $permanentAddress['country'] ?? '' }}" required>
            @error('permanent_address.country')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label">Pincode <span class="text-danger">*</span></label>
            <input type="number" class="form-control @error('permanent_address.pincode') is-invalid @enderror" name="permanent_address[pincode]" value="{{ $permanentAddress['pincode'] ?? '' }}" required>
            @error('permanent_address.pincode')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <hr class="my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0"><i class="fas fa-briefcase me-2"></i>Experience</h6>
        <button type="button" class="btn btn-sm btn-outline-primary" id="add-experience-btn">
            <i class="fas fa-plus me-1"></i>Add Experience
        </button>
    </div>
    @error('experience')
        <div class="text-danger small mb-2">{{ $message }}</div>
    @enderror
    <div id="experience-container" data-next-index="{{ count($experienceItems) }}">
        @foreach($experienceItems as $index => $experience)
            <div class="card border mb-3 experience-item" data-index="{{ $index }}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Experience #{{ $loop->iteration }}</h6>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-experience-btn">Remove</button>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Company Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error("experience.$index.company_name") is-invalid @enderror" name="experience[{{ $index }}][company_name]" value="{{ $experience['company_name'] ?? '' }}">
                            @error("experience.$index.company_name")<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Job Title</label>
                            <input type="text" class="form-control @error("experience.$index.job_title") is-invalid @enderror" name="experience[{{ $index }}][job_title]" value="{{ $experience['job_title'] ?? '' }}">
                            @error("experience.$index.job_title")<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Employment Type</label>
                            <select class="form-select @error("experience.$index.employment_type") is-invalid @enderror" name="experience[{{ $index }}][employment_type]">
                                <option value="">Select Type</option>
                                @foreach($employmentTypeOptions as $value => $label)
                                    <option value="{{ $value }}" {{ ($experience['employment_type'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error("experience.$index.employment_type")<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Start Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control @error("experience.$index.start_date") is-invalid @enderror" name="experience[{{ $index }}][start_date]" value="{{ $experience['start_date'] ?? '' }}">
                            @error("experience.$index.start_date")<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control experience-end-date @error("experience.$index.end_date") is-invalid @enderror" name="experience[{{ $index }}][end_date]" value="{{ $experience['end_date'] ?? '' }}" {{ !empty($experience['currently_working']) ? 'disabled' : '' }}>
                            @error("experience.$index.end_date")<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-check mt-2">
                                <input type="checkbox" class="form-check-input currently-working-checkbox" id="experience-currently-working-{{ $index }}" name="experience[{{ $index }}][currently_working]" value="1" {{ !empty($experience['currently_working']) ? 'checked' : '' }}>
                                <label class="form-check-label" for="experience-currently-working-{{ $index }}">Currently Working</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Salary</label>
                            <input type="number" step="0.01" class="form-control @error("experience.$index.last_salary") is-invalid @enderror" name="experience[{{ $index }}][last_salary]" value="{{ $experience['last_salary'] ?? '' }}">
                            @error("experience.$index.last_salary")<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reason For Leaving</label>
                            <input type="text" class="form-control @error("experience.$index.reason_for_leaving") is-invalid @enderror" name="experience[{{ $index }}][reason_for_leaving]" value="{{ $experience['reason_for_leaving'] ?? '' }}">
                            @error("experience.$index.reason_for_leaving")<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Responsibilities</label>
                            <textarea class="form-control @error("experience.$index.responsibilities") is-invalid @enderror" rows="3" name="experience[{{ $index }}][responsibilities]">{{ $experience['responsibilities'] ?? '' }}</textarea>
                            @error("experience.$index.responsibilities")<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <hr class="my-4">
    <h6 class="mb-3"><i class="fas fa-star me-2"></i>Specialization</h6>
    <textarea class="form-control @error('specialization') is-invalid @enderror" name="specialization" rows="4" placeholder="Write specialization details...">{{ $specialization }}</textarea>
    @error('specialization')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

    <hr class="my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0"><i class="fas fa-phone-alt me-2"></i>Emergency Contacts (Min 2)</h6>
        <button type="button" class="btn btn-sm btn-outline-primary" id="add-emergency-contact-btn">
            <i class="fas fa-plus me-1"></i>Add Contact
        </button>
    </div>
    @error('emergency_contacts')
        <div class="text-danger small mb-2">{{ $message }}</div>
    @enderror
    <div id="emergency-contacts-container" data-next-index="{{ count($emergencyContacts) }}">
        @foreach($emergencyContacts as $index => $contact)
            <div class="card border mb-3 emergency-contact-item" data-index="{{ $index }}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Contact #{{ $loop->iteration }}</h6>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-emergency-contact-btn">Remove</button>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error("emergency_contacts.$index.name") is-invalid @enderror" name="emergency_contacts[{{ $index }}][name]" value="{{ $contact['name'] ?? '' }}" required>
                            @error("emergency_contacts.$index.name")<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Relationship <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error("emergency_contacts.$index.relationship") is-invalid @enderror" name="emergency_contacts[{{ $index }}][relationship]" value="{{ $contact['relationship'] ?? '' }}" required>
                            @error("emergency_contacts.$index.relationship")<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control @error("emergency_contacts.$index.phone") is-invalid @enderror" name="emergency_contacts[{{ $index }}][phone]" value="{{ $contact['phone'] ?? '' }}" required>
                            @error("emergency_contacts.$index.phone")<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Alternate Phone</label>
                            <input type="tel" class="form-control @error("emergency_contacts.$index.alternate_phone") is-invalid @enderror" name="emergency_contacts[{{ $index }}][alternate_phone]" value="{{ $contact['alternate_phone'] ?? '' }}">
                            @error("emergency_contacts.$index.alternate_phone")<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <hr class="my-4">
    <h6 class="mb-3"><i class="fas fa-image me-2"></i>Employee Image</h6>
    <div class="row g-3 align-items-start">
        <div class="col-md-8">
            <input type="file"
                   class="form-control @error('employee_image') is-invalid @enderror"
                   name="employee_image"
                   id="employee-image-input"
                   accept=".jpg,.jpeg,.png"
                   {{ (!$isEdit || empty($employeeImagePath)) ? 'required' : '' }}>
            <small class="text-muted">Accepted formats: JPG, JPEG, PNG. Max size: 2MB.</small>
            @error('employee_image')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <img src="{{ $employeeImageUrl ?: '' }}" id="employee-image-preview" class="img-fluid rounded border {{ $employeeImageUrl ? '' : 'd-none' }}" alt="Employee image preview">
        </div>
    </div>

    <hr class="my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0"><i class="fas fa-file-upload me-2"></i>Documents</h6>
        <button type="button" class="btn btn-sm btn-outline-primary" id="add-document-row-btn">
            <i class="fas fa-plus me-1"></i>Add Document
        </button>
    </div>
    @error('documents')
        <div class="text-danger small mb-2">{{ $message }}</div>
    @enderror
    <div id="documents-container" data-next-index="{{ count($documents) }}">
        @foreach($documents as $index => $document)
            @php
                $existingFilePath = $document['file_path'] ?? ($document['existing_file'] ?? null);
                $existingFileName = $document['file_name'] ?? ($document['existing_file_name'] ?? null);
                $storedDocumentIndex = is_string($existingFilePath) ? ($storedDocumentIndexes[$existingFilePath] ?? null) : null;
            @endphp
            <div class="card border mb-3 document-item" data-index="{{ $index }}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Document #{{ $loop->iteration }}</h6>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-document-btn">Remove</button>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label">Document Type</label>
                            <select class="form-select @error("documents.$index.type") is-invalid @enderror" name="documents[{{ $index }}][type]">
                                <option value="">Select Type</option>
                                @foreach($documentTypes as $documentType)
                                    <option value="{{ $documentType->id }}" {{ (string) ($document['type'] ?? '') === (string) $documentType->id ? 'selected' : '' }}>{{ $documentType->name }}</option>
                                @endforeach
                            </select>
                            @error("documents.$index.type")<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">File</label>
                            <input type="file" class="form-control @error("documents.$index.file") is-invalid @enderror" name="documents[{{ $index }}][file]" accept=".jpg,.jpeg,.png,.pdf">
                            @error("documents.$index.file")<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @if($storedDocumentIndex !== null)
                                <div class="small mt-2">
                                    <a href="{{ route('admin.employees.documents.show', [$employee, $storedDocumentIndex]) }}" target="_blank">Current: {{ $existingFileName ?: basename($existingFilePath) }}</a>
                                </div>
                            @endif
                            <input type="hidden" name="documents[{{ $index }}][existing_file]" value="{{ $existingFilePath }}">
                            <input type="hidden" name="documents[{{ $index }}][existing_file_name]" value="{{ $existingFileName }}">
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-4">
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-2"></i>{{ $submitLabel }}
        </button>
        <a href="{{ $cancelUrl }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

@push('scripts')
<script>
    (function () {
        const sameAsCheckbox = document.getElementById('same-as-present-address');
        const permanentWrapper = document.getElementById('permanent-address-wrapper');

        function getPresentAddressValue(key) {
            const input = document.querySelector('[name="present_address[' + key + ']"]');
            return input ? input.value : '';
        }

        function applySameAsPresent() {
            if (!sameAsCheckbox || !permanentWrapper) {
                return;
            }

            const permanentInputs = permanentWrapper.querySelectorAll('input, select, textarea');
            const isChecked = sameAsCheckbox.checked;

            permanentInputs.forEach(function (input) {
                const nameMatch = input.name.match(/permanent_address\[(.*)\]/);
                if (isChecked && nameMatch && nameMatch[1]) {
                    input.value = getPresentAddressValue(nameMatch[1]);
                }
                input.disabled = isChecked;
            });
        }

        if (sameAsCheckbox) {
            sameAsCheckbox.addEventListener('change', applySameAsPresent);
            document.querySelectorAll('[name^="present_address["]').forEach(function (input) {
                input.addEventListener('input', function () {
                    if (sameAsCheckbox.checked) {
                        applySameAsPresent();
                    }
                });
            });
            applySameAsPresent();
        }

        const employeeImageInput = document.getElementById('employee-image-input');
        const employeeImagePreview = document.getElementById('employee-image-preview');

        if (employeeImageInput && employeeImagePreview) {
            employeeImageInput.addEventListener('change', function (event) {
                const file = event.target.files && event.target.files[0];
                if (!file) {
                    return;
                }

                const reader = new FileReader();
                reader.onload = function (e) {
                    employeeImagePreview.src = e.target.result;
                    employeeImagePreview.classList.remove('d-none');
                };
                reader.readAsDataURL(file);
            });
        }

        const experienceContainer = document.getElementById('experience-container');
        const addExperienceBtn = document.getElementById('add-experience-btn');

        function experienceTemplate(index) {
            return `
                <div class="card border mb-3 experience-item" data-index="${index}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Experience</h6>
                            <button type="button" class="btn btn-sm btn-outline-danger remove-experience-btn">Remove</button>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Company Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="experience[${index}][company_name]">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Job Title</label>
                                <input type="text" class="form-control" name="experience[${index}][job_title]">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Employment Type</label>
                                <select class="form-select" name="experience[${index}][employment_type]">
                                    <option value="">Select Type</option>
                                    <option value="full_time">Full Time</option>
                                    <option value="part_time">Part Time</option>
                                    <option value="contract">Contract</option>
                                    <option value="internship">Internship</option>
                                    <option value="freelance">Freelance</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Start Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="experience[${index}][start_date]">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">End Date</label>
                                <input type="date" class="form-control experience-end-date" name="experience[${index}][end_date]">
                                <div class="form-check mt-2">
                                    <input type="checkbox" class="form-check-input currently-working-checkbox" id="experience-currently-working-${index}" name="experience[${index}][currently_working]" value="1">
                                    <label class="form-check-label" for="experience-currently-working-${index}">Currently Working</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last Salary</label>
                                <input type="number" step="0.01" class="form-control" name="experience[${index}][last_salary]">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Reason For Leaving</label>
                                <input type="text" class="form-control" name="experience[${index}][reason_for_leaving]">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Responsibilities</label>
                                <textarea class="form-control" rows="3" name="experience[${index}][responsibilities]"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        if (addExperienceBtn && experienceContainer) {
            addExperienceBtn.addEventListener('click', function () {
                const index = Number(experienceContainer.dataset.nextIndex || 0);
                experienceContainer.insertAdjacentHTML('beforeend', experienceTemplate(index));
                experienceContainer.dataset.nextIndex = String(index + 1);
            });

            experienceContainer.addEventListener('click', function (event) {
                if (event.target.classList.contains('remove-experience-btn')) {
                    event.target.closest('.experience-item').remove();
                }
            });

            experienceContainer.addEventListener('change', function (event) {
                if (!event.target.classList.contains('currently-working-checkbox')) {
                    return;
                }

                const card = event.target.closest('.experience-item');
                const endDateInput = card.querySelector('.experience-end-date');
                if (!endDateInput) {
                    return;
                }

                if (event.target.checked) {
                    endDateInput.value = '';
                    endDateInput.disabled = true;
                } else {
                    endDateInput.disabled = false;
                }
            });
        }

        const emergencyContainer = document.getElementById('emergency-contacts-container');
        const addEmergencyBtn = document.getElementById('add-emergency-contact-btn');

        function emergencyTemplate(index) {
            return `
                <div class="card border mb-3 emergency-contact-item" data-index="${index}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Contact</h6>
                            <button type="button" class="btn btn-sm btn-outline-danger remove-emergency-contact-btn">Remove</button>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="emergency_contacts[${index}][name]" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Relationship <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="emergency_contacts[${index}][relationship]" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" name="emergency_contacts[${index}][phone]" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Alternate Phone</label>
                                <input type="tel" class="form-control" name="emergency_contacts[${index}][alternate_phone]">
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        if (addEmergencyBtn && emergencyContainer) {
            addEmergencyBtn.addEventListener('click', function () {
                const index = Number(emergencyContainer.dataset.nextIndex || 0);
                emergencyContainer.insertAdjacentHTML('beforeend', emergencyTemplate(index));
                emergencyContainer.dataset.nextIndex = String(index + 1);
            });

            emergencyContainer.addEventListener('click', function (event) {
                if (!event.target.classList.contains('remove-emergency-contact-btn')) {
                    return;
                }

                if (emergencyContainer.querySelectorAll('.emergency-contact-item').length <= 2) {
                    alert('At least 2 emergency contacts are required.');
                    return;
                }

                event.target.closest('.emergency-contact-item').remove();
            });
        }

        const documentsContainer = document.getElementById('documents-container');
        const addDocumentBtn = document.getElementById('add-document-row-btn');
        const documentTypeOptions = @json($documentTypes->map(function ($item) {
            return ['id' => $item->id, 'name' => $item->name];
        })->values());

        function documentOptionsHtml() {
            let options = '<option value="">Select Type</option>';
            documentTypeOptions.forEach(function (item) {
                options += `<option value="${item.id}">${item.name}</option>`;
            });
            return options;
        }

        function documentTemplate(index) {
            return `
                <div class="card border mb-3 document-item" data-index="${index}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Document</h6>
                            <button type="button" class="btn btn-sm btn-outline-danger remove-document-btn">Remove</button>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label">Document Type</label>
                                <select class="form-select" name="documents[${index}][type]">
                                    ${documentOptionsHtml()}
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">File</label>
                                <input type="file" class="form-control" name="documents[${index}][file]" accept=".jpg,.jpeg,.png,.pdf">
                                <input type="hidden" name="documents[${index}][existing_file]" value="">
                                <input type="hidden" name="documents[${index}][existing_file_name]" value="">
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        if (addDocumentBtn && documentsContainer) {
            addDocumentBtn.addEventListener('click', function () {
                const index = Number(documentsContainer.dataset.nextIndex || 0);
                documentsContainer.insertAdjacentHTML('beforeend', documentTemplate(index));
                documentsContainer.dataset.nextIndex = String(index + 1);
            });

            documentsContainer.addEventListener('click', function (event) {
                if (event.target.classList.contains('remove-document-btn')) {
                    event.target.closest('.document-item').remove();
                }
            });
        }
    })();
</script>
@endpush
