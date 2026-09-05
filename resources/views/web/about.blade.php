@extends('layouts.public')

@section('title', 'About Us')

@section('styles')
<style>
    /* Page headers, stat cards and value cards all come from the layout's
       design system. Only the long-form body copy is specific to this page. */
    .about-content {
        font-size: var(--step-lead);
        line-height: 1.85;
        color: var(--ink-600);
    }
    .about-content p { margin-bottom: 1.25rem; }
</style>
@endsection

@section('content')
<!-- Page Header -->
<section class="page-header">
    <div class="container">
        <h1>About Us</h1>
        <p class="lead">Learn more about our mission and commitment to quality healthcare</p>
    </div>
</section>

<!-- About Content -->
<section class="py-5">
    <div class="container">
        <div class="row align-items-center mb-5">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <h2 class="section-title">{{ $companyName }}</h2>
                <div class="about-content">
                    @if($aboutContent)
                        {!! nl2br(e($aboutContent)) !!}
                    @else
                        <p>We are a leading provider of professional home nursing and healthcare services. Our mission is to deliver compassionate, high-quality care in the comfort of your home.</p>
                        <p>With years of experience and a team of dedicated healthcare professionals, we provide personalized care solutions tailored to meet the unique needs of each patient.</p>
                        <p>Our commitment to excellence, combined with our patient-centered approach, makes us the trusted choice for families seeking reliable home healthcare services.</p>
                    @endif
                </div>
            </div>
            <div class="col-lg-6">
                <div class="text-center">
                    <i class="fas fa-hospital-user" style="font-size: 15rem; color: var(--primary-color); opacity: 0.1;"></i>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Stats Section -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3 col-md-6">
                <div class="stats-card">
                    <div class="stats-number">500+</div>
                    <div class="stats-label">Happy Patients</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stats-card">
                    <div class="stats-number">50+</div>
                    <div class="stats-label">Qualified Nurses</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stats-card">
                    <div class="stats-number">10+</div>
                    <div class="stats-label">Years Experience</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stats-card">
                    <div class="stats-number">24/7</div>
                    <div class="stats-label">Availability</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Our Values -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-title">Our Core Values</h2>
            <p class="section-subtitle">The principles that guide our work</p>
        </div>
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="value-card">
                    <h4><i class="fas fa-heart text-primary me-2"></i> Compassion</h4>
                    <p>We treat every patient with empathy, kindness, and respect, understanding their unique needs and concerns.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="value-card">
                    <h4><i class="fas fa-award text-primary me-2"></i> Excellence</h4>
                    <p>We maintain the highest standards of care through continuous training and adherence to best practices.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="value-card">
                    <h4><i class="fas fa-shield-alt text-primary me-2"></i> Integrity</h4>
                    <p>We operate with honesty and transparency, building trust with our patients and their families.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Contact Info -->
@if($companyEmail || $companyPhone || $companyAddress)
<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-title">Get In Touch</h2>
            <p class="section-subtitle">We're here to answer your questions</p>
        </div>
        <div class="row g-4 justify-content-center">
            @if($companyPhone)
            <div class="col-lg-4 col-md-6 text-center">
                <i class="fas fa-phone fa-3x text-primary mb-3"></i>
                <h5>Phone</h5>
                <p class="text-muted">{{ $companyPhone }}</p>
            </div>
            @endif
            @if($companyEmail)
            <div class="col-lg-4 col-md-6 text-center">
                <i class="fas fa-envelope fa-3x text-primary mb-3"></i>
                <h5>Email</h5>
                <p class="text-muted">{{ $companyEmail }}</p>
            </div>
            @endif
            @if($companyAddress)
            <div class="col-lg-4 col-md-6 text-center">
                <i class="fas fa-map-marker-alt fa-3x text-primary mb-3"></i>
                <h5>Address</h5>
                <p class="text-muted">{{ $companyAddress }}</p>
            </div>
            @endif
        </div>
    </div>
</section>
@endif
@endsection

