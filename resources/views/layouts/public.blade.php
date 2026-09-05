<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="@yield('meta_description', 'Professional home nursing and healthcare services')">
    <meta name="theme-color" content="#0b5ed7">
    <title>@yield('title', 'Home Nursing Care') - {{ setting('company_name', 'Home Nursing Care') }}</title>

    {{-- Warm up the CDN sockets before the CSS requests land. --}}
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <style>
        /* ==================================================================
           Design tokens
           One source of truth for colour, type, spacing, radius and shadow.
           Pages compose these instead of re-declaring their own palettes,
           which is what let the five public pages drift apart visually.
           ================================================================== */
        :root {
            /* Brand ramp — a calm clinical blue, warmed by a teal secondary.
               The old blue→purple gradient read as generic SaaS rather than
               healthcare, so purple is gone from the brand surface entirely. */
            --brand-50:  #eff6ff;
            --brand-100: #dbeafe;
            --brand-200: #bfdbfe;
            --brand-300: #93c5fd;
            --brand-500: #3b82f6;
            --brand-600: #2563eb;
            --brand-700: #1d4ed8;
            --brand-800: #1e40af;

            --teal-50:  #f0fdfa;
            --teal-100: #ccfbf1;
            --teal-500: #14b8a6;
            --teal-600: #0d9488;
            --teal-700: #0f766e;

            /* Neutral ramp */
            --ink-900: #0f172a;
            --ink-800: #1e293b;
            --ink-700: #334155;
            --ink-600: #475569;
            --ink-500: #64748b;
            --ink-400: #94a3b8;
            --ink-300: #cbd5e1;
            --ink-200: #e2e8f0;
            --ink-100: #f1f5f9;
            --ink-50:  #f8fafc;

            --success: #10b981;
            --warning: #f59e0b;
            --danger:  #ef4444;

            /* Semantic surfaces */
            --surface:      #ffffff;
            --surface-alt:  var(--ink-50);
            --surface-ink:  var(--ink-900);
            --text:         var(--ink-800);
            --text-muted:   var(--ink-500);
            --border:       var(--ink-200);

            /* Legacy aliases — older page-level CSS still references these. */
            --primary-color:   var(--brand-600);
            --secondary-color: var(--teal-600);
            --accent-color:    var(--teal-500);
            --dark-color:      var(--ink-900);
            --light-color:     var(--ink-50);
            --success-color:   var(--success);
            --danger-color:    var(--danger);

            /* Type */
            --font-display: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', sans-serif;
            --font-body: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;

            /* Fluid type scale: no media queries needed for headings. */
            --step-display: clamp(2.5rem, 1.6rem + 3.6vw, 4rem);
            --step-h1: clamp(2rem, 1.4rem + 2.4vw, 3rem);
            --step-h2: clamp(1.75rem, 1.35rem + 1.6vw, 2.5rem);
            --step-h3: clamp(1.25rem, 1.1rem + 0.6vw, 1.5rem);
            --step-lead: clamp(1.05rem, 1rem + 0.35vw, 1.25rem);

            /* Spacing rhythm */
            --section-y: clamp(4rem, 3rem + 4vw, 7rem);

            /* Radius */
            --r-sm: 8px;
            --r-md: 14px;
            --r-lg: 20px;
            --r-xl: 28px;
            --r-pill: 999px;

            /* Elevation — tuned as a set so cards read on one plane. */
            --shadow-xs: 0 1px 2px rgba(15, 23, 42, 0.05);
            --shadow-sm: 0 2px 8px rgba(15, 23, 42, 0.06);
            --shadow-md: 0 8px 24px rgba(15, 23, 42, 0.08);
            --shadow-lg: 0 18px 45px rgba(15, 23, 42, 0.12);
            --shadow-brand: 0 12px 28px rgba(37, 99, 235, 0.28);

            --ring: 0 0 0 3px var(--brand-200);
            --ease: cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* ==================================================================
           Base
           ================================================================== */
        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: var(--font-body);
            color: var(--text);
            background: var(--surface);
            line-height: 1.65;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }

        h1, h2, h3, h4, h5, h6, .navbar-brand {
            font-family: var(--font-display);
            color: var(--ink-900);
            font-weight: 700;
            line-height: 1.2;
            letter-spacing: -0.02em;
        }

        a { text-decoration: none; }

        /* A single visible focus treatment for every interactive element.
           The previous stylesheet removed outlines without replacing them,
           which left keyboard users with no position indicator at all. */
        a:focus-visible,
        button:focus-visible,
        .btn:focus-visible,
        .form-control:focus-visible,
        .form-select:focus-visible {
            outline: 2px solid var(--brand-600);
            outline-offset: 2px;
            border-radius: var(--r-sm);
        }

        .skip-link {
            position: absolute;
            left: 1rem;
            top: -4rem;
            z-index: 2000;
            padding: 0.75rem 1.25rem;
            background: var(--brand-700);
            color: #fff;
            border-radius: 0 0 var(--r-md) var(--r-md);
            font-weight: 600;
            transition: top 0.2s var(--ease);
        }
        .skip-link:focus { top: 0; color: #fff; }

        ::selection { background: var(--brand-200); color: var(--ink-900); }

        /* ==================================================================
           Navbar
           ================================================================== */
        .navbar {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: saturate(180%) blur(12px);
            -webkit-backdrop-filter: saturate(180%) blur(12px);
            border-bottom: 1px solid transparent;
            padding: 0.9rem 0;
            transition: box-shadow 0.25s var(--ease),
                        border-color 0.25s var(--ease),
                        padding 0.25s var(--ease);
        }

        /* Toggled by the scroll listener so the bar only gains weight once
           content is actually moving underneath it. */
        .navbar.is-scrolled {
            box-shadow: var(--shadow-sm);
            border-bottom-color: var(--border);
            padding: 0.6rem 0;
        }

        .navbar-brand {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--ink-900) !important;
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
        }

        /* Mark rather than a bare glyph: gives the wordmark something to sit
           against and survives the logo being unset. */
        .brand-mark {
            width: 40px;
            height: 40px;
            display: grid;
            place-items: center;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--brand-600), var(--teal-600));
            color: #fff;
            font-size: 1.05rem;
            box-shadow: var(--shadow-brand);
        }

        .brand-logo { height: 40px; width: auto; border-radius: 10px; }

        .navbar .nav-link {
            color: var(--ink-600) !important;
            font-weight: 500;
            font-size: 0.97rem;
            padding: 0.5rem 0.2rem !important;
            margin: 0 0.85rem;
            position: relative;
            transition: color 0.2s var(--ease);
        }

        /* Underline indicator scales from the centre — clearer than a colour
           change alone, which was the only active cue before. */
        .navbar .nav-link::after {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            bottom: -2px;
            height: 2px;
            border-radius: 2px;
            background: var(--brand-600);
            transform: scaleX(0);
            transition: transform 0.25s var(--ease);
        }

        .navbar .nav-link:hover,
        .navbar .nav-link.active { color: var(--brand-700) !important; }
        .navbar .nav-link:hover::after,
        .navbar .nav-link.active::after { transform: scaleX(1); }

        .navbar-toggler {
            border: 1px solid var(--border);
            border-radius: var(--r-sm);
            padding: 0.45rem 0.6rem;
        }
        .navbar-toggler:focus { box-shadow: var(--ring); }

        /* Selector has to out-specify `.navbar .nav-link`, which also sets an
           !important colour — matching it on `.nav-cta` alone lost, leaving
           dark link text on the blue pill. */
        .navbar .nav-link.nav-cta {
            margin-left: 0.75rem;
            padding: 0.6rem 1.35rem !important;
            border-radius: var(--r-pill);
            background: var(--brand-600);
            color: #fff !important;
            font-weight: 600;
            box-shadow: var(--shadow-brand);
            transition: transform 0.2s var(--ease), background 0.2s var(--ease);
        }
        .navbar .nav-link.nav-cta::after { display: none; }
        .navbar .nav-link.nav-cta:hover,
        .navbar .nav-link.nav-cta.active {
            background: var(--brand-700);
            color: #fff !important;
            transform: translateY(-1px);
        }

        @media (max-width: 991.98px) {
            .navbar-collapse {
                margin-top: 0.85rem;
                padding: 0.75rem;
                background: var(--surface);
                border: 1px solid var(--border);
                border-radius: var(--r-md);
                box-shadow: var(--shadow-md);
            }
            .navbar .nav-link { margin: 0; padding: 0.65rem 0.5rem !important; }
            .navbar .nav-link::after { display: none; }
            .navbar .nav-link.active {
                background: var(--brand-50);
                border-radius: var(--r-sm);
            }
            .navbar .nav-link.nav-cta { margin: 0.5rem 0 0; text-align: center; }
        }

        /* ==================================================================
           Buttons
           ================================================================== */
        .btn {
            font-family: var(--font-display);
            font-weight: 600;
            border-radius: var(--r-pill);
            padding: 0.7rem 1.6rem;
            transition: transform 0.2s var(--ease),
                        box-shadow 0.2s var(--ease),
                        background 0.2s var(--ease),
                        color 0.2s var(--ease);
        }

        .btn-lg { padding: 0.9rem 2rem; font-size: 1rem; }
        .btn-sm { padding: 0.45rem 1.1rem; font-size: 0.85rem; }

        /* Flat brand fill instead of the old gradient: gradients on small
           controls muddy the label and shift hue between sizes. */
        .btn-primary {
            background: var(--brand-600);
            border: 1px solid var(--brand-600);
            color: #fff;
            box-shadow: var(--shadow-brand);
        }
        .btn-primary:hover,
        .btn-primary:focus {
            background: var(--brand-700);
            border-color: var(--brand-700);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 16px 32px rgba(37, 99, 235, 0.32);
        }

        .btn-outline-primary {
            border: 1px solid var(--brand-600);
            color: var(--brand-700);
            background: transparent;
        }
        .btn-outline-primary:hover {
            background: var(--brand-600);
            border-color: var(--brand-600);
            color: #fff;
            transform: translateY(-2px);
        }

        .btn-light {
            background: #fff;
            border: 1px solid #fff;
            color: var(--brand-700);
            box-shadow: var(--shadow-md);
        }
        .btn-light:hover {
            background: #fff;
            color: var(--brand-800);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-outline-light {
            border: 1px solid rgba(255, 255, 255, 0.65);
            color: #fff;
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(4px);
        }
        .btn-outline-light:hover {
            background: rgba(255, 255, 255, 0.18);
            border-color: #fff;
            color: #fff;
            transform: translateY(-2px);
        }

        /* ==================================================================
           Section furniture
           ================================================================== */
        .section { padding: var(--section-y) 0; }
        .section-alt { background: var(--surface-alt); }

        .section-title {
            font-size: var(--step-h2);
            font-weight: 800;
            color: var(--ink-900);
            margin-bottom: 0.85rem;
        }

        .section-subtitle {
            color: var(--text-muted);
            font-size: var(--step-lead);
            max-width: 60ch;
            margin: 0 auto 3rem;
        }

        /* Small capsule label that sits above a section title. */
        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 0.95rem;
            border-radius: var(--r-pill);
            background: var(--brand-50);
            color: var(--brand-700);
            border: 1px solid var(--brand-100);
            font-family: var(--font-display);
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin-bottom: 1rem;
        }
        .eyebrow-invert {
            background: rgba(255, 255, 255, 0.14);
            border-color: rgba(255, 255, 255, 0.28);
            color: #fff;
        }

        /* Page header used by the inner pages. */
        .page-header {
            background:
                radial-gradient(900px 380px at 12% -10%, rgba(20, 184, 166, 0.28), transparent 60%),
                linear-gradient(135deg, var(--brand-700) 0%, var(--brand-600) 55%, var(--teal-600) 100%);
            color: #fff;
            padding: clamp(4rem, 3rem + 4vw, 6.5rem) 0 clamp(3rem, 2.5rem + 2vw, 4.5rem);
            text-align: center;
        }
        .page-header h1 {
            color: #fff;
            font-size: var(--step-h1);
            font-weight: 800;
            margin-bottom: 0.75rem;
        }
        .page-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: var(--step-lead);
            max-width: 62ch;
            margin: 0 auto;
        }

        /* ==================================================================
           Cards — one shared shape for every card variant on the site
           ================================================================== */
        .service-card,
        .blog-card,
        .value-card,
        .benefit-card,
        .contact-card,
        .stats-card,
        .service-detail-card,
        .related-blog-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            box-shadow: var(--shadow-sm);
            height: 100%;
            transition: transform 0.28s var(--ease),
                        box-shadow 0.28s var(--ease),
                        border-color 0.28s var(--ease);
        }

        .service-card,
        .value-card,
        .benefit-card,
        .contact-card,
        .service-detail-card {
            padding: 2rem 1.75rem;
        }

        .service-card:hover,
        .blog-card:hover,
        .value-card:hover,
        .benefit-card:hover,
        .contact-card:hover,
        .service-detail-card:hover,
        .related-blog-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-lg);
            border-color: var(--brand-200);
        }

        /* Icon sits in a tinted tile rather than floating loose — gives the
           card a consistent optical anchor at any icon size. */
        .service-icon,
        .benefit-icon,
        .contact-icon,
        .service-icon-large {
            width: 60px;
            height: 60px;
            display: grid;
            place-items: center;
            border-radius: var(--r-md);
            background: linear-gradient(135deg, var(--brand-50), var(--teal-50));
            color: var(--brand-600);
            font-size: 1.5rem;
            margin-bottom: 1.35rem;
            border: 1px solid var(--brand-100);
            transition: background 0.28s var(--ease), color 0.28s var(--ease);
        }
        .service-icon-large { width: 72px; height: 72px; font-size: 1.9rem; }

        .service-card:hover .service-icon,
        .benefit-card:hover .benefit-icon,
        .contact-card:hover .contact-icon {
            background: linear-gradient(135deg, var(--brand-600), var(--teal-600));
            color: #fff;
        }

        .service-card h4,
        .value-card h4,
        .benefit-card h5,
        .contact-card h5 {
            font-size: var(--step-h3);
            margin-bottom: 0.6rem;
        }

        .service-card p,
        .value-card p,
        .benefit-card p,
        .contact-card p { color: var(--text-muted); margin-bottom: 0; }

        /* Blog cards */
        .blog-card { overflow: hidden; display: flex; flex-direction: column; }

        .blog-image {
            width: 100%;
            aspect-ratio: 16 / 10;
            object-fit: cover;
            background: linear-gradient(135deg, var(--brand-100), var(--teal-100));
            display: block;
        }

        .blog-content { padding: 1.5rem; display: flex; flex-direction: column; flex: 1; }

        .blog-title {
            font-size: 1.15rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            /* Keeps every card's headline block the same height. */
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .blog-excerpt {
            color: var(--text-muted);
            font-size: 0.94rem;
            margin-bottom: 1.25rem;
            flex: 1;
        }

        .blog-meta { font-size: 0.85rem; color: var(--ink-400); }

        /* Stats */
        .stats-card { padding: 1.75rem 1.25rem; text-align: center; }
        .stats-number {
            font-family: var(--font-display);
            font-size: clamp(1.9rem, 1.5rem + 1.4vw, 2.6rem);
            font-weight: 800;
            color: var(--brand-600);
            line-height: 1.1;
        }
        .stats-label {
            color: var(--text-muted);
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 0;
        }

        /* ==================================================================
           CTA band
           ================================================================== */
        .cta-section {
            background:
                radial-gradient(700px 320px at 85% 110%, rgba(20, 184, 166, 0.35), transparent 60%),
                linear-gradient(135deg, var(--brand-700) 0%, var(--brand-600) 60%, var(--teal-600) 100%);
            color: #fff;
            padding: var(--section-y) 0;
            text-align: center;
        }
        .cta-section h2 {
            color: #fff;
            font-size: var(--step-h2);
            font-weight: 800;
            margin-bottom: 0.85rem;
        }
        .cta-section p { color: rgba(255, 255, 255, 0.9); font-size: var(--step-lead); }

        /* ==================================================================
           Empty states and inline card links
           ================================================================== */
        .no-services, .no-blogs {
            border: 1px dashed var(--border);
            border-radius: var(--r-lg);
            background: var(--surface-alt);
            padding: 3rem 1.5rem;
            text-align: center;
            color: var(--text-muted);
        }
        .no-services i, .no-blogs i {
            display: block;
            font-size: 2rem;
            color: var(--ink-300);
            margin-bottom: 0.75rem;
        }

        .card-link-row {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            margin-top: 1.1rem;
            color: var(--brand-700);
            font-family: var(--font-display);
            font-weight: 600;
            font-size: 0.9rem;
        }
        .card-link-row i { transition: transform 0.2s var(--ease); }
        .service-card:hover .card-link-row i,
        .service-detail-card:hover .card-link-row i { transform: translateX(4px); }

        /* ==================================================================
           Forms
           ================================================================== */
        .form-control, .form-select {
            border: 1px solid var(--border);
            border-radius: var(--r-md);
            padding: 0.75rem 1rem;
            font-size: 0.97rem;
            transition: border-color 0.2s var(--ease), box-shadow 0.2s var(--ease);
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--brand-500);
            box-shadow: var(--ring);
        }
        .form-label { font-weight: 600; color: var(--ink-700); margin-bottom: 0.4rem; font-size: 0.92rem; }

        .alert { border-radius: var(--r-md); border: 1px solid transparent; padding: 1rem 1.25rem; }
        .alert-success { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
        .alert-danger  { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
        .alert-info    { background: var(--brand-50); border-color: var(--brand-100); color: var(--brand-800); }

        /* ==================================================================
           Footer
           ================================================================== */
        .footer {
            background: var(--ink-900);
            color: var(--ink-300);
            padding: clamp(3.5rem, 3rem + 2vw, 5rem) 0 1.5rem;
            margin-top: 0;
        }

        .footer h5 {
            color: #fff;
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: 0.01em;
            margin-bottom: 1.35rem;
        }

        .footer p { color: var(--ink-400); }

        .footer a {
            color: var(--ink-300);
            transition: color 0.2s var(--ease), padding-left 0.2s var(--ease);
        }
        .footer a:hover { color: #fff; }
        .footer ul a:hover { padding-left: 4px; }

        .footer-brand {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            color: #fff;
            font-family: var(--font-display);
            font-weight: 700;
        }

        .social-link {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
            color: var(--ink-300);
            transition: background 0.2s var(--ease),
                        color 0.2s var(--ease),
                        transform 0.2s var(--ease);
        }
        .social-link:hover {
            background: var(--brand-600);
            color: #fff;
            transform: translateY(-2px);
        }

        .footer-contact li {
            display: flex;
            gap: 0.75rem;
            align-items: flex-start;
            margin-bottom: 0.85rem;
            color: var(--ink-400);
        }
        .footer-contact i { color: var(--teal-500); margin-top: 0.3rem; }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            margin-top: 2.5rem;
            padding-top: 1.5rem;
            text-align: center;
            color: var(--ink-500);
            font-size: 0.9rem;
        }

        /* ==================================================================
           Motion preferences
           ================================================================== */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
                scroll-behavior: auto !important;
            }
            .service-card:hover,
            .blog-card:hover,
            .btn:hover { transform: none; }
        }
    </style>

    @yield('styles')
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg sticky-top" id="siteNav">
        <div class="container">
            <a class="navbar-brand" href="{{ route('home') }}">
                @if(setting('company_logo'))
                    <img src="{{ media_url(setting('company_logo')) }}"
                         alt="{{ setting('company_name', 'Home Nursing Care') }}"
                         class="brand-logo">
                @else
                    <span class="brand-mark" aria-hidden="true"><i class="fas fa-heartbeat"></i></span>
                @endif
                {{ setting('company_name', 'Home Nursing Care') }}
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#navbarNav" aria-controls="navbarNav"
                    aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('about') ? 'active' : '' }}" href="{{ route('about') }}" @if(request()->routeIs('about')) aria-current="page" @endif>About Us</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('services') ? 'active' : '' }}" href="{{ route('services') }}" @if(request()->routeIs('services')) aria-current="page" @endif>Services</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('blogs.*') ? 'active' : '' }}" href="{{ route('blogs.index') }}" @if(request()->routeIs('blogs.*')) aria-current="page" @endif>Blog</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('contact') ? 'active' : '' }}" href="{{ route('contact') }}" @if(request()->routeIs('contact')) aria-current="page" @endif>Contact</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-cta {{ request()->routeIs('join') ? 'active' : '' }}" href="{{ route('join') }}">Join With Us</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main id="main-content">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <span class="footer-brand mb-3">
                        <span class="brand-mark" aria-hidden="true"><i class="fas fa-heartbeat"></i></span>
                        {{ setting('company_name', 'Home Nursing Care') }}
                    </span>
                    <p class="mt-3">{{ setting('company_description', 'Professional home nursing and healthcare services delivered with compassion and expertise.') }}</p>
                    <div class="d-flex gap-2 mt-4">
                        <a href="#" class="social-link" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-link" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-link" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-link" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="{{ route('home') }}">Home</a></li>
                        <li class="mb-2"><a href="{{ route('about') }}">About Us</a></li>
                        <li class="mb-2"><a href="{{ route('services') }}">Services</a></li>
                        <li class="mb-2"><a href="{{ route('blogs.index') }}">Blog</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6">
                    <h5>Services</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="{{ route('services') }}">ICU Care</a></li>
                        <li class="mb-2"><a href="{{ route('services') }}">Post-Surgery Care</a></li>
                        <li class="mb-2"><a href="{{ route('services') }}">Elderly Care</a></li>
                        <li class="mb-2"><a href="{{ route('services') }}">View All Services</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6">
                    <h5>Contact Us</h5>
                    <ul class="list-unstyled footer-contact">
                        <li>
                            <i class="fas fa-phone"></i>
                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', setting('company_phone', '+1 234 567 8900')) }}">{{ setting('company_phone', '+1 234 567 8900') }}</a>
                        </li>
                        <li>
                            <i class="fas fa-envelope"></i>
                            <a href="mailto:{{ setting('company_email', 'info@homenursing.com') }}">{{ setting('company_email', 'info@homenursing.com') }}</a>
                        </li>
                        <li>
                            <i class="fas fa-map-marker-alt"></i>
                            <span>{{ setting('company_address', '123 Healthcare St, City') }}</span>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p class="mb-0">&copy; {{ date('Y') }} {{ setting('company_name', 'Home Nursing Care') }}. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Elevate the navbar only once the page has actually scrolled.
        (function () {
            var nav = document.getElementById('siteNav');
            if (!nav) return;

            var onScroll = function () {
                nav.classList.toggle('is-scrolled', window.scrollY > 8);
            };

            onScroll();
            window.addEventListener('scroll', onScroll, { passive: true });
        })();
    </script>

    @yield('scripts')
</body>
</html>
