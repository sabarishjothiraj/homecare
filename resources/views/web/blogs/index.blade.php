@extends('layouts.public')

@section('title', 'Blog')

@section('styles')
<style>
    /* Blog cards, the page header and the empty state come from the
       layout's design system. Only pagination and the card title link
       are specific to this listing. */
    .blog-title a {
        color: var(--ink-900);
        transition: color 0.2s var(--ease);
    }
    .blog-title a:hover { color: var(--brand-700); }

    .pagination { margin-top: 3rem; }

    .pagination .page-link {
        color: var(--brand-700);
        border: 1px solid var(--border);
        border-radius: var(--r-sm);
        margin: 0 0.25rem;
        padding: 0.55rem 0.9rem;
        font-weight: 600;
        transition: background 0.2s var(--ease), color 0.2s var(--ease);
    }
    .pagination .page-link:hover {
        background: var(--brand-50);
        color: var(--brand-800);
        border-color: var(--brand-200);
    }
    .pagination .page-item.active .page-link {
        background: var(--brand-600);
        border-color: var(--brand-600);
        color: #fff;
    }
</style>
@endsection

@section('content')
<!-- Page Header -->
<section class="page-header">
    <div class="container">
        <h1>Our Blog</h1>
        <p class="lead">Healthcare tips, news, and insights from our experts</p>
    </div>
</section>

<!-- Blog Listing -->
<section class="py-5">
    <div class="container">
        @if($blogs->count() > 0)
        <div class="row g-4">
            @foreach($blogs as $blog)
            <div class="col-lg-4 col-md-6">
                <div class="blog-card">
                    @if($blog->featured_image)
                    <img src="{{ $blog->featured_image_url }}" alt="{{ $blog->title }}" class="blog-image">
                    @else
                    <div class="blog-image d-flex align-items-center justify-content-center">
                        <i class="fas fa-newspaper fa-5x text-white"></i>
                    </div>
                    @endif
                    <div class="blog-content">
                        <h3 class="blog-title">
                            <a href="{{ route('blogs.show', $blog->slug) }}">{{ $blog->title }}</a>
                        </h3>
                        <p class="blog-excerpt">{{ Str::limit($blog->excerpt, 150) }}</p>
                        <div class="blog-meta">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>
                                    <i class="far fa-calendar"></i> {{ $blog->published_at->format('M d, Y') }}
                                </span>
                                <span>
                                    <i class="far fa-eye"></i> {{ $blog->views }} views
                                </span>
                            </div>
                            @if($blog->author)
                            <div class="mt-2">
                                <i class="far fa-user"></i> {{ $blog->author->name }}
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        
        <!-- Pagination -->
        <div class="d-flex justify-content-center mt-5">
            {{ $blogs->links() }}
        </div>
        @else
        <div class="no-blogs">
            <i class="fas fa-newspaper"></i>
            <h3>No Blog Posts Yet</h3>
            <p class="text-muted">Check back soon for healthcare tips and insights from our experts.</p>
        </div>
        @endif
    </div>
</section>
@endsection

