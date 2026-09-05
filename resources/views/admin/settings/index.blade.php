@extends('layouts.admin')

@section('title', 'Settings')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">System Settings</h2>
            <p class="text-muted mb-0">Manage application configuration</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form action="{{ route('admin.settings.update') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <!-- Company Information -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-building me-2"></i>Company Information</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="company_name" class="form-label">Company Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('company_name') is-invalid @enderror" 
                               id="company_name" name="company_name" 
                               value="{{ old('company_name', $settings['company']['company_name']->value ?? '') }}" required>
                        @error('company_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="company_email" class="form-label">Company Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control @error('company_email') is-invalid @enderror" 
                               id="company_email" name="company_email" 
                               value="{{ old('company_email', $settings['company']['company_email']->value ?? '') }}" required>
                        @error('company_email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="company_phone" class="form-label">Company Phone <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('company_phone') is-invalid @enderror" 
                               id="company_phone" name="company_phone" 
                               value="{{ old('company_phone', $settings['company']['company_phone']->value ?? '') }}" required>
                        @error('company_phone')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="company_logo" class="form-label">Company Logo</label>
                        <input type="file" class="form-control @error('company_logo') is-invalid @enderror" 
                               id="company_logo" name="company_logo" accept="image/*">
                        @error('company_logo')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        @if(isset($settings['company']['company_logo']) && $settings['company']['company_logo']->value)
                            <small class="text-muted">Current: <a href="{{ media_url($settings['company']['company_logo']->value) }}" target="_blank">View Logo</a></small>
                        @endif
                    </div>

                    <div class="col-12">
                        <label for="company_address" class="form-label">Company Address <span class="text-danger">*</span></label>
                        <textarea class="form-control @error('company_address') is-invalid @enderror" 
                                  id="company_address" name="company_address" rows="2" required>{{ old('company_address', $settings['company']['company_address']->value ?? '') }}</textarea>
                        @error('company_address')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label for="company_description" class="form-label">Company Description</label>
                        <textarea class="form-control @error('company_description') is-invalid @enderror" 
                                  id="company_description" name="company_description" rows="3">{{ old('company_description', $settings['company']['company_description']->value ?? '') }}</textarea>
                        @error('company_description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <!-- Website Settings -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-globe me-2"></i>Website Settings</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="website_title" class="form-label">Website Title</label>
                        <input type="text" class="form-control @error('website_title') is-invalid @enderror" 
                               id="website_title" name="website_title" 
                               value="{{ old('website_title', $settings['website']['website_title']->value ?? '') }}">
                        @error('website_title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="home_intro_title" class="form-label">Home Intro Title</label>
                        <input type="text" class="form-control @error('home_intro_title') is-invalid @enderror" 
                               id="home_intro_title" name="home_intro_title" 
                               value="{{ old('home_intro_title', $settings['website']['home_intro_title']->value ?? '') }}">
                        @error('home_intro_title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label for="home_intro_content" class="form-label">Home Intro Content</label>
                        <textarea class="form-control @error('home_intro_content') is-invalid @enderror" 
                                  id="home_intro_content" name="home_intro_content" rows="3">{{ old('home_intro_content', $settings['website']['home_intro_content']->value ?? '') }}</textarea>
                        @error('home_intro_content')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label for="about_content" class="form-label">About Page Content</label>
                        <textarea class="form-control @error('about_content') is-invalid @enderror" 
                                  id="about_content" name="about_content" rows="5">{{ old('about_content', $settings['website']['about_content']->value ?? '') }}</textarea>
                        @error('about_content')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label for="join_benefits" class="form-label">Join Benefits Content</label>
                        <textarea class="form-control @error('join_benefits') is-invalid @enderror" 
                                  id="join_benefits" name="join_benefits" rows="5">{{ old('join_benefits', $settings['website']['join_benefits']->value ?? '') }}</textarea>
                        @error('join_benefits')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <!-- System Settings -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-cog me-2"></i>System Settings</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="contract_alert_days" class="form-label">Contract Alert Days <span class="text-danger">*</span></label>
                        <input type="number" class="form-control @error('contract_alert_days') is-invalid @enderror" 
                               id="contract_alert_days" name="contract_alert_days" 
                               value="{{ old('contract_alert_days', $settings['system']['contract_alert_days']->value ?? 7) }}" 
                               min="1" max="30" required>
                        @error('contract_alert_days')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">Days before contract end to show alert</small>
                    </div>

                    <div class="col-md-6">
                        <label for="contract_max_assignments" class="form-label">Max Employee Changes Per Service Line <span class="text-danger">*</span></label>
                        <input type="number" class="form-control @error('contract_max_assignments') is-invalid @enderror"
                               id="contract_max_assignments" name="contract_max_assignments"
                               value="{{ old('contract_max_assignments', $settings['system']['contract_max_assignments']->value ?? 10) }}"
                               min="1" max="50" required>
                        @error('contract_max_assignments')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">Includes initial assignment and replacements.</small>
                    </div>

                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="notification_enabled" name="notification_enabled" 
                                   {{ old('notification_enabled', $settings['system']['notification_enabled']->value ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="notification_enabled">
                                <strong>Enable Notifications</strong>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- OTP Settings -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-mobile-screen me-2"></i>Login OTP</h5>
            </div>
            <div class="card-body">
                @php($otpChannel = config('otp.channel', 'mail'))

                <div class="alert alert-info py-2 px-3">
                    <small>
                        <i class="fas fa-circle-info me-1"></i>
                        Codes are currently delivered by <strong>{{ strtoupper($otpChannel) }}</strong>.
                        @if($otpChannel === 'log')
                            They are written to the application log, not actually sent.
                        @elseif($otpChannel === 'mail')
                            The email address below receives every staff login request.
                        @else
                            The phone number below receives every staff login request.
                        @endif
                        Change the channel with <code>OTP_CHANNEL</code> in your .env file.
                    </small>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="otp_email" class="form-label">
                            Approver Email
                            @if($otpChannel === 'mail')<span class="badge bg-primary ms-1">In use</span>@endif
                        </label>
                        <input type="email" class="form-control @error('otp_email') is-invalid @enderror"
                               id="otp_email" name="otp_email"
                               value="{{ old('otp_email', $otpEmail) }}"
                               placeholder="owner@example.com" maxlength="255">
                        @error('otp_email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">
                            Every staff login code is emailed here, along with who is signing in.
                            Leave blank to block OTP logins entirely.
                        </small>
                    </div>

                    <div class="col-md-6">
                        <label for="otp_phone_number" class="form-label">
                            Approver Phone
                            @if($otpChannel === 'sms')<span class="badge bg-primary ms-1">In use</span>@endif
                        </label>
                        <input type="text" class="form-control @error('otp_phone_number') is-invalid @enderror"
                               id="otp_phone_number" name="otp_phone_number"
                               value="{{ old('otp_phone_number', $otpPhoneNumber) }}"
                               placeholder="+919876543210" maxlength="20">
                        @error('otp_phone_number')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">
                            Used when the channel is set to SMS. Include the country code.
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-4">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-save me-2"></i>Save Settings
            </button>
        </div>
    </form>
</div>
@endsection

