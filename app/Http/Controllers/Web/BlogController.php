<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Blog;

class BlogController extends Controller
{
    /**
     * Display blog listing
     */
    public function index()
    {
        try {
            $blogs = Blog::published()
                ->with('author')
                ->latest('published_at')
                ->paginate(9);
        } catch (\Exception $e) {
            $blogs = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 9);
        }

        return view('web.blogs.index', compact('blogs'));
    }

    /**
     * Display blog detail
     */
    public function show(string $slug)
    {
        try {
            $blog = Blog::published()
                ->with('author')
                ->where('slug', $slug)
                ->firstOrFail();

            // Increment views
            $blog->incrementViews();

            // Get related blogs (same author or recent)
            $relatedBlogs = Blog::published()
                ->where('id', '!=', $blog->id)
                ->latest('published_at')
                ->take(3)
                ->get();
        } catch (\Exception $e) {
            abort(404);
        }

        return view('web.blogs.show', compact('blog', 'relatedBlogs'));
    }
}
