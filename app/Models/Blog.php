<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Blog extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'featured_image',
        'author_id',
        'status',
        'published_at',
        'views',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($blog) {
            if (! $blog->slug) {
                $blog->slug = static::uniqueSlug($blog->title);
            }
        });
    }

    /**
     * Build a non-empty slug from the title that no other post (including
     * soft-deleted ones, which the unique index still covers) is using.
     */
    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        // Str::slug() drops scripts it cannot transliterate, e.g. Tamil,
        // leaving '' - which would make route('blogs.show') point at /blogs.
        $base = rtrim(Str::limit(Str::slug($title), 240, ''), '-')
            ?: 'post-'.Str::lower(Str::random(8));

        $slug = $base;
        $suffix = 2;

        while (static::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * Get the author
     */
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Scope for published blogs
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * Scope for draft blogs
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Increment views
     */
    public function incrementViews(): void
    {
        $this->increment('views');
    }

    /**
     * Resolve the featured image, which may be an upload or an absolute URL.
     */
    public function getFeaturedImageUrlAttribute(): ?string
    {
        return media_url($this->featured_image);
    }
}
