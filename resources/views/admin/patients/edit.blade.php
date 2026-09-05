@extends('layouts.admin')

@section('title', 'Edit Patient')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Edit Patient</h2>
            <p class="text-muted mb-0">Update patient information</p>
        </div>
        <a href="{{ route('admin.patients.show', $patient) }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Patient
        </a>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-user-injured me-2"></i>Patient Information</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.patients.update', $patient) }}" method="POST" data-ajax="true">
                        @csrf
                        @method('PUT')

                        <div class="row g-3">
                            <!-- Name -->
                            <div class="col-md-6">
                                <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror"
                                       id="name" name="name" value="{{ old('name', $patient->name) }}"
                                       required maxlength="255">
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Phone -->
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control @error('phone') is-invalid @enderror"
                                       id="phone" name="phone" value="{{ old('phone', $patient->phone) }}"
                                       required maxlength="20" data-validate="phone">
                                @error('phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Email -->
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror"
                                       id="email" name="email" value="{{ old('email', $patient->email) }}"
                                       required maxlength="255">
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Address Line 1 -->
                            <div class="col-md-12">
                                <label for="address1" class="form-label">Address Line 1 <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('address1') is-invalid @enderror"
                                       id="address1" name="address1" value="{{ old('address1', $patient->address1) }}"
                                       required maxlength="255">
                                @error('address1')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Address Line 2 -->
                            <div class="col-md-12">
                                <label for="address2" class="form-label">Address Line 2</label>
                                <input type="text" class="form-control @error('address2') is-invalid @enderror"
                                       id="address2" name="address2" value="{{ old('address2', $patient->address2) }}"
                                       maxlength="255">
                                @error('address2')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Area -->
                            <div class="col-md-6">
                                <label for="area" class="form-label">Area</label>
                                <input type="text" class="form-control @error('area') is-invalid @enderror"
                                       id="area" name="area" value="{{ old('area', $patient->area) }}"
                                       maxlength="255">
                                @error('area')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- City -->
                            <div class="col-md-6">
                                <label for="city" class="form-label">City <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('city') is-invalid @enderror"
                                       id="city" name="city" value="{{ old('city', $patient->city) }}"
                                       required maxlength="255">
                                @error('city')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- State -->
                            <div class="col-md-4">
                                <label for="state" class="form-label">State <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('state') is-invalid @enderror"
                                       id="state" name="state" value="{{ old('state', $patient->state) }}"
                                       required maxlength="255">
                                @error('state')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Country -->
                            <div class="col-md-4">
                                <label for="country" class="form-label">Country <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('country') is-invalid @enderror"
                                       id="country" name="country" value="{{ old('country', $patient->country) }}"
                                       required maxlength="255">
                                @error('country')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Pincode -->
                            <div class="col-md-4">
                                <label for="pincode" class="form-label">Pincode <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('pincode') is-invalid @enderror"
                                       id="pincode" name="pincode" value="{{ old('pincode', $patient->pincode) }}"
                                       required maxlength="20">
                                @error('pincode')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Emergency Contact Name -->
                            <div class="col-md-6">
                                <label for="emergency_contact_name" class="form-label">Emergency Contact Name</label>
                                <input type="text" class="form-control @error('emergency_contact_name') is-invalid @enderror"
                                       id="emergency_contact_name" name="emergency_contact_name"
                                       value="{{ old('emergency_contact_name', $patient->emergency_contact_name) }}"
                                       maxlength="255">
                                @error('emergency_contact_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Emergency Contact Phone -->
                            <div class="col-md-6">
                                <label for="emergency_contact_phone" class="form-label">Emergency Contact Phone</label>
                                <input type="tel" class="form-control @error('emergency_contact_phone') is-invalid @enderror"
                                       id="emergency_contact_phone" name="emergency_contact_phone"
                                       value="{{ old('emergency_contact_phone', $patient->emergency_contact_phone) }}"
                                       maxlength="20" data-validate="phone">
                                @error('emergency_contact_phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Medical Notes -->
                            <div class="col-md-12">
                                <label for="medical_notes" class="form-label">Medical Notes</label>
                                <textarea class="form-control @error('medical_notes') is-invalid @enderror" 
                                          id="medical_notes" name="medical_notes" rows="4" 
                                          placeholder="Any medical conditions, allergies, or special requirements...">{{ old('medical_notes', $patient->medical_notes) }}</textarea>
                                @error('medical_notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Is Active -->
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                           {{ old('is_active', $patient->is_active) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">
                                        Active Patient
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Patient
                            </button>
                            <a href="{{ route('admin.patients.show', $patient) }}" class="btn btn-outline-secondary">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Patient Details</h6>
                </div>
                <div class="card-body">
                    <p class="small mb-2"><strong>Created:</strong> {{ $patient->created_at->format('M d, Y') }}</p>
                    <p class="small mb-0"><strong>Last Updated:</strong> {{ $patient->updated_at->diffForHumans() }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

