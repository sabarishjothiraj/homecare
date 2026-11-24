@extends('layouts.public')

@section('title', 'Contact Us')

@section('content')
<!-- Page Header -->
<section class="page-header">
    <div class="container">
        <h1>Contact Us</h1>
        <p class="lead">We're here to help and answer any questions you might have</p>
    </div>
</section>

<!-- Contact Info -->
<section class="py-5">
    <div class="container">
        <div class="row g-4 mb-5">
            @if($companyPhone)
            <div class="col-lg-4 col-md-6">
                <div class="contact-card">
                    <div class="contact-icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <h5>Phone</h5>
                    <p>{{ $companyPhone }}</p>
                    <a href="tel:{{ $companyPhone }}" class="btn btn-sm btn-primary mt-2">Call Now</a>
                </div>
            </div>
            @endif
            
            @if($companyEmail)
            <div class="col-lg-4 col-md-6">
                <div class="contact-card">
                    <div class="contact-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <h5>Email</h5>
                    <p>{{ $companyEmail }}</p>
                    <a href="mailto:{{ $companyEmail }}" class="btn btn-sm btn-primary mt-2">Send Email</a>
                </div>
            </div>
            @endif
            
            @if($companyAddress)
            <div class="col-lg-4 col-md-6">
                <div class="contact-card">
                    <div class="contact-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <h5>Address</h5>
                    <p>{{ $companyAddress }}</p>
                </div>
            </div>
            @endif
        </div>
    </div>
</section>

<!-- Contact Form -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-lg" style="border-radius: 15px;">
                    <div class="card-body p-5">
                        <h2 class="text-center mb-4">Send Us a Message</h2>
                        
                        @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        @endif

                        @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        @endif

                        <form action="{{ route('contact.store') }}" method="POST">
                            @csrf

                            {{-- Honeypot: hidden from people, filled in by form-spamming bots. --}}
                            <div style="position: absolute; left: -10000px;" aria-hidden="true">
                                <label for="hp_extra">Leave this field empty</label>
                                <input type="text" id="hp_extra" name="hp_extra" value="" tabindex="-1" autocomplete="off">
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                           id="name" name="name" value="{{ old('name') }}" required>
                                    @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control @error('email') is-invalid @enderror" 
                                           id="email" name="email" value="{{ old('email') }}" required>
                                    @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control @error('phone') is-invalid @enderror"
                                           id="phone" name="phone" value="{{ old('phone') }}">
                                    @error('phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="subject" class="form-label">Subject</label>
                                    <input type="text" class="form-control @error('subject') is-invalid @enderror"
                                           id="subject" name="subject" value="{{ old('subject') }}">
                                    @error('subject')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="message" class="form-label">Message <span class="text-danger">*</span></label>
                                <textarea class="form-control @error('message') is-invalid @enderror"
                                          id="message" name="message" rows="5" required>{{ old('message') }}</textarea>
                                @error('message')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="text-center">
                                <button type="submit" class="btn btn-primary btn-lg px-5">
                                    <i class="fas fa-paper-plane me-2"></i>Send Message
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Map or Additional Info -->
<section class="py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <h2 class="mb-4">Visit Our Office</h2>
                <p class="text-muted mb-4">We welcome you to visit our office during business hours. Our friendly staff is ready to assist you with any inquiries.</p>
                <ul class="list-unstyled">
                    <li class="mb-2"><i class="fas fa-clock text-primary me-2"></i> <strong>Monday - Friday:</strong> 9:00 AM - 6:00 PM</li>
                    <li class="mb-2"><i class="fas fa-clock text-primary me-2"></i> <strong>Saturday:</strong> 10:00 AM - 4:00 PM</li>
                    <li class="mb-2"><i class="fas fa-clock text-primary me-2"></i> <strong>Sunday:</strong> Closed</li>
                </ul>
            </div>
            <div class="col-lg-6">
                <div class="bg-light p-5 rounded text-center">
                    <i class="fas fa-map-marked-alt fa-5x text-primary mb-3"></i>
                    <h4>{{ setting('company_address', '123 Healthcare Street, City, State') }}</h4>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

