<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class BlogController extends Controller
{
    /**
     * Display blog listing
     */
    public function index(Request $request)
    {
        $query = Blog::with('author');

        // Filter by status
        if ($request->filled('status')) {
            if ($request->status === 'published') {
                $query->published();
            } elseif ($request->status === 'draft') {
                $query->draft();
            }
        }

        $blogs = $query->latest()->paginate(20);

        return view('admin.blogs.index', compact('blogs'));
    }

    /**
     * Show create form
     */
    public function create()
    {
        return view('admin.blogs.create');
    }

    /**
     * Store new blog
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'excerpt' => 'nullable|string|max:500',
            'featured_image' => 'nullable|image|max:2048',
            'status' => 'required|in:draft,published',
            'published_at' => 'nullable|date',
        ]);

        $validated['slug'] = Str::slug($validated['title']);
        $validated['author_id'] = auth()->id();

        // Handle featured image upload
        if ($request->hasFile('featured_image')) {
            $path = $request->file('featured_image')->store('blog_images', 'public');
            $validated['featured_image'] = $path;
        }

        // Set published_at if status is published
        if ($validated['status'] === 'published' && !isset($validated['published_at'])) {
            $validated['published_at'] = now();
        }

        $blog = Blog::create($validated);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Blog post created successfully.',
                'redirect' => route('admin.blogs.index')
            ]);
        }

        return redirect()->route('admin.blogs.index')
            ->with('success', 'Blog post created successfully.');
    }

    /**
     * Show blog details
     */
    public function show(Blog $blog)
    {
        $blog->load('author');

        return view('admin.blogs.show', compact('blog'));
    }

    /**
     * Show edit form
     */
    public function edit(Blog $blog)
    {
        return view('admin.blogs.edit', compact('blog'));
    }

    /**
     * Update blog
     */
    public function update(Request $request, Blog $blog)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'excerpt' => 'nullable|string|max:500',
            'featured_image' => 'nullable|image|max:2048',
            'status' => 'required|in:draft,published',
            'published_at' => 'nullable|date',
        ]);

        $validated['slug'] = Str::slug($validated['title']);

        // Handle featured image upload
        if ($request->hasFile('featured_image')) {
            // Delete old image
            if ($blog->featured_image && Storage::disk('public')->exists($blog->featured_image)) {
                Storage::disk('public')->delete($blog->featured_image);
            }

            $path = $request->file('featured_image')->store('blog_images', 'public');
            $validated['featured_image'] = $path;
        }

        // Set published_at if status changed to published
        if ($validated['status'] === 'published' && $blog->status === 'draft' && !isset($validated['published_at'])) {
            $validated['published_at'] = now();
        }

        $blog->update($validated);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Blog post updated successfully.',
                'redirect' => route('admin.blogs.index')
            ]);
        }

        return redirect()->route('admin.blogs.index')
            ->with('success', 'Blog post updated successfully.');
    }

    /**
     * Delete blog
     */
    public function destroy(Blog $blog)
    {
        // Delete featured image
        if ($blog->featured_image && Storage::disk('public')->exists($blog->featured_image)) {
            Storage::disk('public')->delete($blog->featured_image);
        }

        $blog->delete();

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Blog post deleted successfully.',
                'redirect' => route('admin.blogs.index')
            ]);
        }

        return redirect()->route('admin.blogs.index')
            ->with('success', 'Blog post deleted successfully.');
    }
}

