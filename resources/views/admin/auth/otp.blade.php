@extends('admin.auth.layout')

@section('title', 'Login Approval')
@section('icon', 'fa-shield-halved')
@section('heading', 'Login Approval')

@section('content')
                <p class="text-muted small mb-4">
                    Your password was accepted. A login code has been sent to the account owner
                    for approval - ask them for it and enter it below. The code expires in
                    {{ $expiresInMinutes }} {{ Str::plural('minute', $expiresInMinutes) }}.
                </p>

                <form method="POST" action="{{ route('admin.login.otp.verify') }}">
                    @csrf

                    <div class="mb-4">
                        <label for="otp" class="form-label">Login Code</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-key"></i></span>
                            <input type="text"
                                   class="form-control @error('otp') is-invalid @enderror"
                                   id="otp"
                                   name="otp"
                                   inputmode="numeric"
                                   autocomplete="one-time-code"
                                   maxlength="16"
                                   placeholder="Enter the code"
                                   required
                                   autofocus>
                        </div>
                        @error('otp')
                            <div class="text-danger mt-1 small">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-login">
                        <i class="fas fa-check me-2"></i>Verify and Sign In
                    </button>
                </form>

                <div class="text-center mt-3">
                    <a href="{{ route('admin.login') }}" class="small text-muted">Start over</a>
                </div>
@endsection
