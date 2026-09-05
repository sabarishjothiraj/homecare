@extends('layouts.public')

@section('title', 'Home')

@section('styles')
<style>
    /* Only what is unique to the home page lives here. Cards, buttons,
       section titles and the CTA band all come from the layout's design
       system so this page cannot drift away from the rest of the site. */

    /* ------------------------------------------------------------------
       Banner carousel
       ------------------------------------------------------------------ */
    .banner-carousel .carousel-item {
        height: clamp(360px, 55vh, 560px);
        /* Brand fallback behind the photo. Banner images are remote URLs, so
           a slow or broken one would otherwise leave the white slide copy
           sitting on a white background. */
        background: linear-gradient(135deg, var(--brand-800), var(--brand-600) 55%, var(--teal-600));
    }

    .banner-carousel .carousel-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .banner-overlay {
        position: absolute;
        inset: 0;
        /* Deeper on the left where the text sits, so the copy keeps its
           contrast without dimming the whole photograph. */
        background: linear-gradient(90deg,
                    rgba(15, 23, 42, 0.86) 0%,
                    rgba(15, 23, 42, 0.55) 45%,
                    rgba(15, 23, 42, 0.15) 100%);
        display: flex;
        align-items: center;
    }

    .banner-content { color: #fff; max-width: 40rem; }

    .banner-title {
        color: #fff;
        font-size: var(--step-h1);
        font-weight: 800;
        margin-bottom: 1rem;
    }

    .banner-subtitle {
        color: rgba(255, 255, 255, 0.92);
        font-size: var(--step-lead);
        margin-bottom: 1.75rem;
    }

    .banner-carousel .carousel-indicators { margin-bottom: 1.5rem; }
    .banner-carousel .carousel-indicators [data-bs-target] {
        width: 28px;
        height: 4px;
        border-radius: 2px;
        border: 0;
    }

    /* Controls sit in their own pill at the edge. Bootstrap's default spans
       a wide transparent column, which put the bare chevron on top of the
       slide copy where it was both unreadable and easy to hit by accident. */
    .banner-carousel .carousel-control-prev,
    .banner-carousel .carousel-control-next {
        width: auto;
        padding: 0 1.25rem;
        opacity: 1;
    }

    .banner-carousel .carousel-control-prev-icon,
    .banner-carousel .carousel-control-next-icon {
        width: 44px;
        height: 44px;
        background-size: 45%;
        border-radius: 50%;
        background-color: rgba(15, 23, 42, 0.45);
        border: 1px solid rgba(255, 255, 255, 0.35);
        transition: background-color 0.2s var(--ease);
    }

    .banner-carousel .carousel-control-prev:hover .carousel-control-prev-icon,
    .banner-carousel .carousel-control-next:hover .carousel-control-next-icon {
        background-color: var(--brand-600);
    }

    /* Below the lg breakpoint the copy needs the full width. */
    @media (max-width: 767.98px) {
        .banner-carousel .carousel-control-prev,
        .banner-carousel .carousel-control-next { display: none; }
    }

    /* ------------------------------------------------------------------
       Hero
       ------------------------------------------------------------------ */
    .hero-section {
        position: relative;
        overflow: hidden;
        padding: clamp(4rem, 3rem + 5vw, 7rem) 0;
        background:
            radial-gradient(820px 420px at 8% 0%, rgba(20, 184, 166, 0.30), transparent 62%),
            radial-gradient(760px 420px at 92% 100%, rgba(59, 130, 246, 0.32), transparent 60%),
            linear-gradient(135deg, var(--brand-800) 0%, var(--brand-700) 45%, var(--brand-600) 100%);
        color: #fff;
    }

    /* Faint grid: gives the flat gradient some surface without competing
       with the copy the way the old 15rem ghost icon did. */
    .hero-section::before {
        content: '';
        position: absolute;
        inset: 0;
        background-image:
            linear-gradient(rgba(255, 255, 255, 0.05) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255, 255, 255, 0.05) 1px, transparent 1px);
        background-size: 64px 64px;
        mask-image: radial-gradient(circle at 50% 40%, #000 0%, transparent 78%);
        -webkit-mask-image: radial-gradient(circle at 50% 40%, #000 0%, transparent 78%);
        pointer-events: none;
    }

    .hero-content { position: relative; z-index: 1; }

    .hero-title {
        color: #fff;
        font-size: var(--step-display);
        font-weight: 800;
        line-height: 1.08;
        margin-bottom: 1.25rem;
    }

    .hero-subtitle {
        color: rgba(255, 255, 255, 0.9);
        font-size: var(--step-lead);
        max-width: 34rem;
        margin-bottom: 2.25rem;
    }

    /* Trust row under the CTAs — the three claims the business actually
       competes on, stated once instead of repeated down the page. */
    .hero-trust {
        display: flex;
        flex-wrap: wrap;
        gap: 1.75rem;
        margin-top: 2.75rem;
        padding-top: 1.75rem;
        border-top: 1px solid rgba(255, 255, 255, 0.18);
    }
    .hero-trust-item { display: flex; align-items: center; gap: 0.65rem; }
    .hero-trust-item i { color: var(--teal-100); font-size: 1.1rem; }
    .hero-trust-item span { font-size: 0.92rem; color: rgba(255, 255, 255, 0.88); }

    /* ------------------------------------------------------------------
       Hero visual — a care-summary card replacing the ghost icon
       ------------------------------------------------------------------ */
    .hero-visual { position: relative; z-index: 1; }

    .hero-panel {
        background: rgba(255, 255, 255, 0.10);
        border: 1px solid rgba(255, 255, 255, 0.22);
        border-radius: var(--r-xl);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        padding: 1.75rem;
        box-shadow: 0 28px 60px rgba(2, 12, 40, 0.35);
    }

    .hero-panel-head {
        display: flex;
        align-items: center;
        gap: 0.9rem;
        padding-bottom: 1.25rem;
        margin-bottom: 1.25rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.16);
    }

    .hero-avatar {
        width: 52px;
        height: 52px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.92);
        color: var(--brand-700);
        font-size: 1.35rem;
        flex-shrink: 0;
    }

    .hero-panel-head h3 {
        color: #fff;
        font-size: 1.05rem;
        margin-bottom: 0.15rem;
    }
    .hero-panel-head p {
        margin-bottom: 0;
        font-size: 0.85rem;
        color: rgba(255, 255, 255, 0.75);
    }

    .hero-status {
        margin-left: auto;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.3rem 0.75rem;
        border-radius: var(--r-pill);
        background: rgba(16, 185, 129, 0.22);
        border: 1px solid rgba(16, 185, 129, 0.45);
        color: #d1fae5;
        font-size: 0.75rem;
        font-weight: 600;
        white-space: nowrap;
    }
    .hero-status .dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #34d399;
    }

    .hero-stat-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.75rem;
    }

    .hero-stat {
        background: rgba(255, 255, 255, 0.09);
        border: 1px solid rgba(255, 255, 255, 0.14);
        border-radius: var(--r-md);
        padding: 1rem 0.75rem;
        text-align: center;
    }
    .hero-stat strong {
        display: block;
        font-family: var(--font-display);
        font-size: 1.5rem;
        font-weight: 800;
        color: #fff;
        line-height: 1.15;
    }
    .hero-stat span {
        font-size: 0.75rem;
        color: rgba(255, 255, 255, 0.75);
    }

    /* ------------------------------------------------------------------
       Secondary hero
       When banners are configured the carousel is the page opener, so the
       hero must not present a second full-bleed headline underneath it.
       This variant keeps the same admin-editable copy but drops to a light
       surface and reads as an intro section instead of a competing header.
       ------------------------------------------------------------------ */
    .hero-section.is-secondary {
        background: var(--surface-alt);
        color: var(--text);
        border-bottom: 1px solid var(--border);
        padding: var(--section-y) 0;
    }
    .hero-section.is-secondary::before { display: none; }

    .hero-section.is-secondary .hero-title { color: var(--ink-900); }
    .hero-section.is-secondary .hero-subtitle { color: var(--text-muted); }

    .hero-section.is-secondary .hero-trust { border-top-color: var(--border); }
    .hero-section.is-secondary .hero-trust-item i { color: var(--teal-600); }
    .hero-section.is-secondary .hero-trust-item span { color: var(--ink-600); }

    .hero-section.is-secondary .hero-panel {
        background: var(--surface);
        border-color: var(--border);
        box-shadow: var(--shadow-md);
        backdrop-filter: none;
        -webkit-backdrop-filter: none;
    }
    .hero-section.is-secondary .hero-panel-head { border-bottom-color: var(--border); }
    .hero-section.is-secondary .hero-panel-head h3 { color: var(--ink-900); }
    .hero-section.is-secondary .hero-panel-head p { color: var(--text-muted); }

    .hero-section.is-secondary .hero-avatar {
        background: linear-gradient(135deg, var(--brand-50), var(--teal-50));
        border: 1px solid var(--brand-100);
    }

    .hero-section.is-secondary .hero-status {
        background: #ecfdf5;
        border-color: #a7f3d0;
        color: #047857;
    }

    .hero-section.is-secondary .hero-stat {
        background: var(--surface-alt);
        border-color: var(--border);
    }
    .hero-section.is-secondary .hero-stat strong { color: var(--brand-700); }
    .hero-section.is-secondary .hero-stat span { color: var(--text-muted); }

    @media (max-width: 991.98px) {
        .hero-visual { margin-top: 3rem; }
    }
</style>
@endsection

@section('content')
@php
    // With banners configured the carousel opens the page, so the hero below
    // renders in its lighter secondary form rather than as a second header.
    $hasBanners = $banners->count() > 0;
@endphp

<!-- Banner Carousel -->
@if($hasBanners)
<div id="bannerCarousel" class="carousel slide banner-carousel" data-bs-ride="carousel">
    <div class="carousel-indicators">
        @foreach($banners as $index => $banner)
        <button type="button" data-bs-target="#bannerCarousel" data-bs-slide-to="{{ $index }}"
                class="{{ $index === 0 ? 'active' : '' }}"
                aria-current="{{ $index === 0 ? 'true' : 'false' }}"
                aria-label="Slide {{ $index + 1 }}"></button>
        @endforeach
    </div>
    <div class="carousel-inner">
        @foreach($banners as $index => $banner)
        <div class="carousel-item {{ $index === 0 ? 'active' : '' }}">
            <img src="{{ $banner->image_url }}" alt="{{ $banner->title }}">
            <div class="banner-overlay">
                <div class="container">
                    <div class="banner-content">
                        <h2 class="banner-title">{{ $banner->title }}</h2>
                        @if($banner->subtitle)
                        <p class="banner-subtitle">{{ $banner->subtitle }}</p>
                        @endif
                        @if($banner->link && $banner->button_text)
                        <a href="{{ $banner->link }}" class="btn btn-light btn-lg">
                            {{ $banner->button_text }}
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    <button class="carousel-control-prev" type="button" data-bs-target="#bannerCarousel" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Previous</span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#bannerCarousel" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Next</span>
    </button>
</div>
@endif

<!-- Hero Section -->
<section class="hero-section {{ $hasBanners ? 'is-secondary' : '' }}">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6 hero-content">
                <span class="eyebrow {{ $hasBanners ? '' : 'eyebrow-invert' }}">
                    <i class="fas fa-shield-heart"></i> Trusted Home Healthcare
                </span>
                <h1 class="hero-title">{{ $homeIntroTitle }}</h1>
                <p class="hero-subtitle">{{ $homeIntroContent ?: 'Professional nursing care in the comfort of your home. Our experienced healthcare professionals provide compassionate, personalized care tailored to your needs.' }}</p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="{{ route('services') }}" class="btn {{ $hasBanners ? 'btn-primary' : 'btn-light' }} btn-lg">
                        Our Services <i class="fas fa-arrow-right ms-2"></i>
                    </a>
                    <a href="{{ route('contact') }}" class="btn {{ $hasBanners ? 'btn-outline-primary' : 'btn-outline-light' }} btn-lg">Contact Us</a>
                </div>

                <div class="hero-trust">
                    <div class="hero-trust-item">
                        <i class="fas fa-certificate"></i>
                        <span>Certified nurses</span>
                    </div>
                    <div class="hero-trust-item">
                        <i class="fas fa-clock"></i>
                        <span>24/7 availability</span>
                    </div>
                    <div class="hero-trust-item">
                        <i class="fas fa-house-medical"></i>
                        <span>Care at home</span>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 hero-visual">
                <div class="hero-panel">
                    <div class="hero-panel-head">
                        <span class="hero-avatar" aria-hidden="true"><i class="fas fa-user-nurse"></i></span>
                        <div>
                            <h3>Assigned Care Team</h3>
                            <p>Matched to your needs</p>
                        </div>
                        <span class="hero-status"><span class="dot" aria-hidden="true"></span> On duty</span>
                    </div>
                    <div class="hero-stat-grid">
                        <div class="hero-stat">
                            <strong>24/7</strong>
                            <span>Coverage</span>
                        </div>
                        <div class="hero-stat">
                            <strong>100%</strong>
                            <span>Verified</span>
                        </div>
                        <div class="hero-stat">
                            <strong>At&nbsp;home</strong>
                            <span>Delivered</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Services Section -->
<section class="section">
    <div class="container">
        <div class="text-center">
            <span class="eyebrow"><i class="fas fa-stethoscope"></i> What We Offer</span>
            <h2 class="section-title">Our Services</h2>
            <p class="section-subtitle">Comprehensive healthcare solutions delivered at your doorstep</p>
        </div>
        <div class="row g-4">
            @forelse($services as $service)
            <div class="col-lg-4 col-md-6">
                <div class="service-card">
                    <div class="service-icon">
                        <i class="{{ $service->icon ?: 'fas fa-heartbeat' }}"></i>
                    </div>
                    <h4>{{ $service->name }}</h4>
                    <p>{{ Str::limit($service->description, 120) }}</p>
                    <span class="card-link-row">
                        Learn more <i class="fas fa-arrow-right"></i>
                    </span>
                </div>
            </div>
            @empty
            <div class="col-12">
                <div class="no-services">
                    <i class="fas fa-notes-medical d-block"></i>
                    <p class="mb-0">No services available at the moment.</p>
                </div>
            </div>
            @endforelse
        </div>
        @if($services->count() > 0)
        <div class="text-center mt-5">
            <a href="{{ route('services') }}" class="btn btn-primary btn-lg">View All Services</a>
        </div>
        @endif
    </div>
</section>

<!-- Why Choose Us Section -->
<section class="section section-alt">
    <div class="container">
        <div class="text-center">
            <span class="eyebrow"><i class="fas fa-award"></i> Why Us</span>
            <h2 class="section-title">Why Choose Us</h2>
            <p class="section-subtitle">Excellence in home healthcare services</p>
        </div>
        <div class="row g-4">
            <div class="col-lg-3 col-md-6">
                <div class="benefit-card">
                    <div class="benefit-icon"><i class="fas fa-user-md"></i></div>
                    <h5>Qualified Professionals</h5>
                    <p>Experienced and certified healthcare professionals</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="benefit-card">
                    <div class="benefit-icon"><i class="fas fa-clock"></i></div>
                    <h5>24/7 Availability</h5>
                    <p>Round-the-clock care whenever you need it</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="benefit-card">
                    <div class="benefit-icon"><i class="fas fa-home"></i></div>
                    <h5>Home Comfort</h5>
                    <p>Quality care in the comfort of your home</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="benefit-card">
                    <div class="benefit-icon"><i class="fas fa-indian-rupee-sign"></i></div>
                    <h5>Affordable Pricing</h5>
                    <p>Competitive rates without compromising quality</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Recent Blogs Section -->
@if($recentBlogs->count() > 0)
<section class="section">
    <div class="container">
        <div class="text-center">
            <span class="eyebrow"><i class="fas fa-newspaper"></i> Insights</span>
            <h2 class="section-title">Latest from Our Blog</h2>
            <p class="section-subtitle">Stay informed with healthcare tips and news</p>
        </div>
        <div class="row g-4">
            @foreach($recentBlogs as $blog)
            <div class="col-lg-4 col-md-6">
                <article class="blog-card">
                    @if($blog->featured_image)
                    <img src="{{ $blog->featured_image_url }}" alt="{{ $blog->title }}"
                         class="blog-image" loading="lazy">
                    @else
                    <div class="blog-image d-flex align-items-center justify-content-center">
                        <i class="fas fa-newspaper fa-3x" style="color: var(--brand-300);"></i>
                    </div>
                    @endif
                    <div class="blog-content">
                        <h3 class="blog-title">{{ $blog->title }}</h3>
                        <p class="blog-excerpt">{{ Str::limit($blog->excerpt, 100) }}</p>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="blog-meta">
                                <i class="far fa-calendar me-1"></i> {{ $blog->published_at->format('M d, Y') }}
                            </span>
                            <a href="{{ route('blogs.show', $blog->slug) }}" class="btn btn-sm btn-outline-primary">Read More</a>
                        </div>
                    </div>
                </article>
            </div>
            @endforeach
        </div>
        <div class="text-center mt-5">
            <a href="{{ route('blogs.index') }}" class="btn btn-primary btn-lg">View All Articles</a>
        </div>
    </div>
</section>
@endif

<!-- CTA Section -->
<section class="cta-section">
    <div class="container">
        <span class="eyebrow eyebrow-invert"><i class="fas fa-comments"></i> Get In Touch</span>
        <h2>Ready to Get Started?</h2>
        <p class="mb-4">Contact us today to discuss your healthcare needs</p>
        <div class="d-flex flex-wrap gap-3 justify-content-center">
            <a href="{{ route('contact') }}" class="btn btn-light btn-lg">Contact Us</a>
            <a href="{{ route('join') }}" class="btn btn-outline-light btn-lg">Join Our Team</a>
        </div>
    </div>
</section>
@endsection
