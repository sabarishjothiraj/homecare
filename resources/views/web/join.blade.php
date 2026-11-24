@extends('layouts.public')

@section('title', 'Join With Us')

@section('content')
<!-- Page Header -->
<section class="page-header">
    <div class="container">
        <h1>Join Our Team</h1>
        <p class="lead">Be part of a dedicated team making a difference in people's lives</p>
    </div>
</section>

<!-- Benefits Section -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-title">Why Join Us</h2>
            <p class="section-subtitle">Benefits of working with our team</p>
        </div>
        
        @if($joinBenefits)
        <div class="mb-5">
            <div class="card border-0 shadow-sm" style="border-radius: 15px;">
                <div class="card-body p-4">
                    {!! nl2br(e($joinBenefits)) !!}
                </div>
            </div>
        </div>
        @endif
        
        <div class="row g-4 mb-5">
            <div class="col-lg-3 col-md-6">
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <h5>Competitive Pay</h5>
                    <p class="text-muted">Attractive compensation packages</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h5>Flexible Schedule</h5>
                    <p class="text-muted">Work-life balance options</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <h5>Training & Growth</h5>
                    <p class="text-muted">Continuous learning opportunities</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="benefit-card">
                    <div class="benefit-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h5>Supportive Team</h5>
                    <p class="text-muted">Collaborative work environment</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Application Form -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-lg" style="border-radius: 15px;">
                    <div class="card-body p-5">
                        <h2 class="text-center mb-4">Application Form</h2>
                        <p class="text-center text-muted mb-4">Fill out the form below to apply for a position with us</p>
                        
                        @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        @endif

                        {{-- e.g. the public-forms rate limit after too many submissions --}}
                        @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        @endif

                        @if($errors->any())
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>Your application was not sent. Please correct the following:
                            <ul class="mb-0 mt-2">
                                @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                        @endif

                        <form action="{{ route('join.store') }}" method="POST">
                            @csrf
                            
                            <div class="row">
                                @foreach($employeeFields as $field)
                                <div class="col-md-{{ in_array($field->type, ['textarea']) ? '12' : '6' }} mb-3">
                                    <label for="{{ $field->name }}" class="form-label">
                                        {{ $field->label }}
                                        @if($field->is_required)
                                        <span class="text-danger">*</span>
                                        @endif
                                    </label>
                                    
                                    @if($field->type === 'textarea')
                                    <textarea class="form-control @error($field->name) is-invalid @enderror"
                                              id="{{ $field->name }}"
                                              name="{{ $field->name }}"
                                              rows="4"
                                              {{ $field->is_required ? 'required' : '' }}>{{ old($field->name) }}</textarea>

                                    @elseif($field->type === 'select' && count($field->optionList()) > 0)
                                    <select class="form-select @error($field->name) is-invalid @enderror"
                                            id="{{ $field->name }}"
                                            name="{{ $field->name }}"
                                            {{ $field->is_required ? 'required' : '' }}>
                                        <option value="">Select {{ $field->label }}</option>
                                        @foreach($field->optionList() as $option)
                                        <option value="{{ $option }}" {{ old($field->name) == $option ? 'selected' : '' }}>
                                            {{ $option }}
                                        </option>
                                        @endforeach
                                    </select>

                                    @elseif($field->type === 'date')
                                    <input type="date"
                                           class="form-control @error($field->name) is-invalid @enderror"
                                           id="{{ $field->name }}"
                                           name="{{ $field->name }}"
                                           value="{{ old($field->name) }}"
                                           {{ $field->is_required ? 'required' : '' }}>

                                    @elseif($field->type === 'number')
                                    <input type="number"
                                           class="form-control @error($field->name) is-invalid @enderror"
                                           id="{{ $field->name }}"
                                           name="{{ $field->name }}"
                                           value="{{ old($field->name) }}"
                                           {{ $field->is_required ? 'required' : '' }}>

                                    @elseif($field->type === 'email')
                                    <input type="email"
                                           class="form-control @error($field->name) is-invalid @enderror"
                                           id="{{ $field->name }}"
                                           name="{{ $field->name }}"
                                           value="{{ old($field->name) }}"
                                           {{ $field->is_required ? 'required' : '' }}>

                                    @elseif($field->type === 'phone')
                                    <input type="tel"
                                           class="form-control @error($field->name) is-invalid @enderror"
                                           id="{{ $field->name }}"
                                           name="{{ $field->name }}"
                                           value="{{ old($field->name) }}"
                                           {{ $field->is_required ? 'required' : '' }}>

                                    @else
                                    <input type="text"
                                           class="form-control @error($field->name) is-invalid @enderror"
                                           id="{{ $field->name }}"
                                           name="{{ $field->name }}"
                                           value="{{ old($field->name) }}"
                                           {{ $field->is_required ? 'required' : '' }}>
                                    @endif

                                    @error($field->name)
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                @endforeach
                            </div>

                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-primary btn-lg px-5">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Application
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Requirements Section -->
<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 class="text-center mb-4">Requirements</h2>
                <div class="card border-0 shadow-sm" style="border-radius: 15px;">
                    <div class="card-body p-4">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-3"><i class="fas fa-check-circle text-success me-2"></i> Valid nursing license or certification</li>
                            <li class="mb-3"><i class="fas fa-check-circle text-success me-2"></i> Minimum 2 years of experience in healthcare</li>
                            <li class="mb-3"><i class="fas fa-check-circle text-success me-2"></i> Excellent communication and interpersonal skills</li>
                            <li class="mb-3"><i class="fas fa-check-circle text-success me-2"></i> Compassionate and patient-centered approach</li>
                            <li class="mb-3"><i class="fas fa-check-circle text-success me-2"></i> Ability to work independently and as part of a team</li>
                            <li class="mb-0"><i class="fas fa-check-circle text-success me-2"></i> Clean background check and references</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

