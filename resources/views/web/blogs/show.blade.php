@extends('layouts.public')

@section('title', $blog->title)
{{-- A null value would open a block @section that is never closed. --}}
@section('meta_description', $blog->excerpt ?: Str::limit(html_entity_decode(strip_tags($blog->content), ENT_QUOTES), 160))

@section('styles')
<style>
    /* The related-post card shell comes from the layout. Everything here is
       specific to reading a single article: the masthead, the prose column
       and the share row. */
    .blog-header {
        background:
            radial-gradient(900px 380px at 12% -10%, rgba(20, 184, 166, 0.28), transparent 60%),
            linear-gradient(135deg, var(--brand-700) 0%, var(--brand-600) 55%, var(--teal-600) 100%);
        color: #fff;
        padding: clamp(3.5rem, 3rem + 3vw, 5rem) 0 clamp(2.5rem, 2rem + 2vw, 3.5rem);
    }

    .blog-header .blog-title {
        color: #fff;
        font-size: var(--step-h1);
        font-weight: 800;
        margin-bottom: 1rem;
        /* The listing clamps titles to two lines; the article must not. */
        display: block;
        overflow: visible;
    }

    .blog-header .blog-meta {
        font-size: 0.95rem;
        color: rgba(255, 255, 255, 0.88);
    }
    .blog-header .blog-meta i { margin-right: 0.4rem; }

    .blog-featured-image {
        width: 100%;
        max-height: 500px;
        object-fit: cover;
        border-radius: var(--r-lg);
        box-shadow: var(--shadow-lg);
    }

    /* Prose column. A measure near 70ch keeps long articles readable. */
    .blog-content {
        font-size: var(--step-lead);
        line-height: 1.85;
        color: var(--ink-600);
        max-width: 70ch;
        padding: 0;
    }

    .blog-content h2 {
        font-size: var(--step-h3);
        margin-top: 2.5rem;
        margin-bottom: 1rem;
    }
    .blog-content h3 {
        font-size: 1.2rem;
        margin-top: 2rem;
        margin-bottom: 0.75rem;
    }
    .blog-content p { margin-bottom: 1.5rem; }
    .blog-content ul, .blog-content ol { margin-bottom: 1.5rem; padding-left: 1.5rem; }
    .blog-content li { margin-bottom: 0.5rem; }
    .blog-content a { color: var(--brand-700); text-decoration: underline; }
    .blog-content blockquote {
        border-left: 3px solid var(--brand-300);
        background: var(--brand-50);
        padding: 1rem 1.5rem;
        border-radius: 0 var(--r-md) var(--r-md) 0;
        margin: 0 0 1.5rem;
        color: var(--ink-700);
    }
    .blog-content img { max-width: 100%; height: auto; border-radius: var(--r-md); }

    .share-buttons {
        background: var(--surface-alt);
        border: 1px solid var(--border);
        border-radius: var(--r-lg);
        padding: 1.5rem;
        margin: 2.5rem 0;
    }
    .share-buttons h5 { margin-bottom: 1rem; font-size: 1rem; }

    .share-btn {
        width: 42px;
        height: 42px;
        display: inline-grid;
        place-items: center;
        border-radius: 50%;
        color: #fff;
        margin-right: 0.5rem;
        transition: transform 0.2s var(--ease), filter 0.2s var(--ease);
    }
    .share-btn:hover { transform: translateY(-3px); filter: brightness(1.08); color: #fff; }

    .share-btn.facebook { background: #1877f2; }
    .share-btn.twitter  { background: #1da1f2; }
    .share-btn.linkedin { background: #0077b5; }
    .share-btn.whatsapp { background: #25d366; }

    .related-blog-card { overflow: hidden; }

    .related-blog-image {
        width: 100%;
        aspect-ratio: 16 / 9;
        object-fit: cover;
        background: linear-gradient(135deg, var(--brand-100), var(--teal-100));
        display: block;
    }

    .related-blog-content { padding: 1.25rem; }

    .related-blog-title {
        font-size: 1.05rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }
    .related-blog-title a {
        color: var(--ink-900);
        transition: color 0.2s var(--ease);
    }
    .related-blog-title a:hover { color: var(--brand-700); }
</style>
@endsection

@section('content')
<!-- Blog Header -->
<section class="blog-header">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <h1 class="blog-title">{{ $blog->title }}</h1>
                <div class="blog-meta">
                    <span class="me-4">
                        <i class="far fa-calendar"></i> {{ $blog->published_at->format('F d, Y') }}
                    </span>
                    @if($blog->author)
                    <span class="me-4">
                        <i class="far fa-user"></i> {{ $blog->author->name }}
                    </span>
                    @endif
                    <span>
                        <i class="far fa-eye"></i> {{ $blog->views }} views
                    </span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Blog Content -->
<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- Featured Image -->
                @if($blog->featured_image)
                <div class="mb-5">
                    <img src="{{ $blog->featured_image_url }}" alt="{{ $blog->title }}" class="blog-featured-image">
                </div>
                @endif

                <!-- Excerpt -->
                @if($blog->excerpt)
                <div class="lead mb-4" style="font-size: 1.2rem; color: #64748b;">
                    {{ $blog->excerpt }}
                </div>
                @endif

                <!-- Content -->
                <div class="blog-content">
                    {!! nl2br(e($blog->content)) !!}
                </div>

                <!-- Share Buttons -->
                <div class="share-buttons">
                    <h5>Share this article</h5>
                    <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(route('blogs.show', $blog->slug)) }}"
                       target="_blank" class="share-btn facebook">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="https://twitter.com/intent/tweet?url={{ urlencode(route('blogs.show', $blog->slug)) }}&text={{ urlencode($blog->title) }}"
                       target="_blank" class="share-btn twitter">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="https://www.linkedin.com/shareArticle?mini=true&url={{ urlencode(route('blogs.show', $blog->slug)) }}&title={{ urlencode($blog->title) }}"
                       target="_blank" class="share-btn linkedin">
                        <i class="fab fa-linkedin-in"></i>
                    </a>
                    <a href="https://wa.me/?text={{ urlencode($blog->title . ' ' . route('blogs.show', $blog->slug)) }}"
                       target="_blank" class="share-btn whatsapp">
                        <i class="fab fa-whatsapp"></i>
                    </a>
                </div>

                <!-- Back to Blog -->
                <div class="text-center mt-4">
                    <a href="{{ route('blogs.index') }}" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Blog
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Related Blogs -->
@if($relatedBlogs->count() > 0)
<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-title">Related Articles</h2>
            <p class="section-subtitle">You might also be interested in</p>
        </div>
        <div class="row g-4">
            @foreach($relatedBlogs as $relatedBlog)
            <div class="col-lg-4 col-md-6">
                <div class="related-blog-card">
                    @if($relatedBlog->featured_image)
                    <img src="{{ $relatedBlog->featured_image_url }}" alt="{{ $relatedBlog->title }}" class="related-blog-image">
                    @else
                    <div class="related-blog-image d-flex align-items-center justify-content-center">
                        <i class="fas fa-newspaper fa-4x text-white"></i>
                    </div>
                    @endif
                    <div class="related-blog-content">
                        <h5 class="related-blog-title">
                            <a href="{{ route('blogs.show', $relatedBlog->slug) }}">{{ $relatedBlog->title }}</a>
                        </h5>
                        <p class="text-muted small mb-2">
                            <i class="far fa-calendar me-1"></i> {{ $relatedBlog->published_at->format('M d, Y') }}
                        </p>
                        <p class="text-muted small">{{ Str::limit($relatedBlog->excerpt, 100) }}</p>
                        <a href="{{ route('blogs.show', $relatedBlog->slug) }}" class="btn btn-sm btn-primary">Read More</a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif
@endsection

