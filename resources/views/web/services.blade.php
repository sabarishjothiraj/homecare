@extends('layouts.public')

@section('title', 'Our Services')

@section('styles')
<style>
    /* Card shape, icon tiles and empty states come from the layout. The
       accent rule is what distinguishes a full service card from the
       compact one used on the home page. */
    .service-detail-card { border-top: 4px solid var(--brand-600); }
</style>
@endsection

@section('content')
<!-- Page Header -->
<section class="page-header">
    <div class="container">
        <h1>Our Services</h1>
        <p class="lead">Comprehensive healthcare solutions tailored to your needs</p>
    </div>
</section>

<!-- Services List -->
<section class="py-5">
    <div class="container">
        @forelse($services as $service)
        <div class="row mb-4">
            <div class="col-12">
                <div class="service-detail-card">
                    <div class="row align-items-center">
                        <div class="col-lg-2 col-md-3 text-center mb-3 mb-md-0">
                            <div class="service-icon-large">
                                <i class="{{ $service->icon ?: 'fas fa-heartbeat' }}"></i>
                            </div>
                        </div>
                        <div class="col-lg-10 col-md-9">
                            <h3>{{ $service->name }}</h3>
                            <p>{{ $service->description }}</p>
                            @if($service->image)
                            <div class="mt-3">
                                <img src="{{ $service->image_url }}" alt="{{ $service->name }}" class="img-fluid rounded" style="max-height: 300px;">
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="no-services">
            <i class="fas fa-hospital"></i>
            <h3>No Services Available</h3>
            <p class="text-muted">We're currently updating our services. Please check back soon or contact us for more information.</p>
            <a href="{{ route('contact') }}" class="btn btn-primary mt-3">Contact Us</a>
        </div>
        @endforelse
    </div>
</section>

<!-- CTA Section -->
@if($services->count() > 0)
<section class="py-5 bg-light">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h2 class="mb-3">Need a Customized Care Plan?</h2>
                <p class="text-muted mb-0">Our team can create a personalized healthcare solution based on your specific requirements.</p>
            </div>
            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                <a href="{{ route('contact') }}" class="btn btn-primary btn-lg">Get in Touch</a>
            </div>
        </div>
    </div>
</section>
@endif

<!-- Why Choose Our Services -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-title">Why Choose Our Services</h2>
            <p class="section-subtitle">Quality care you can trust</p>
        </div>
        <div class="row g-4">
            <div class="col-lg-3 col-md-6 text-center">
                <i class="fas fa-user-nurse fa-4x text-primary mb-3"></i>
                <h5>Certified Professionals</h5>
                <p class="text-muted">All our nurses are certified and experienced</p>
            </div>
            <div class="col-lg-3 col-md-6 text-center">
                <i class="fas fa-clipboard-check fa-4x text-primary mb-3"></i>
                <h5>Personalized Care</h5>
                <p class="text-muted">Customized care plans for each patient</p>
            </div>
            <div class="col-lg-3 col-md-6 text-center">
                <i class="fas fa-headset fa-4x text-primary mb-3"></i>
                <h5>24/7 Support</h5>
                <p class="text-muted">Round-the-clock assistance and monitoring</p>
            </div>
            <div class="col-lg-3 col-md-6 text-center">
                <i class="fas fa-hand-holding-medical fa-4x text-primary mb-3"></i>
                <h5>Compassionate Care</h5>
                <p class="text-muted">Treating patients with dignity and respect</p>
            </div>
        </div>
    </div>
</section>
@endsection

