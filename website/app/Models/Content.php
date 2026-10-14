<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Content extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'title_km',
        'title_en',
        'slug',
        'excerpt',
        'featured_image',
        'meta_title',
        'meta_description',
        'tags',
        'status',
        'is_featured',
        'views_count',
        'published_at',
        'author_id',
        'reviewer_id',
        'approved_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'approved_at' => 'datetime',
        'is_featured' => 'boolean',
        'tags' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ContentCategory::class, 'category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ContentImage::class)->orderBy('sort_order');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(ContentBlock::class)->orderBy('sort_order');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')->where('published_at', '<=', now());
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'សេចក្តីព្រាង',
            'pending_review' => 'កំពុងរង់ចាំត្រួតពិនិត្យ',
            'approved' => 'បានអនុម័ត',
            'published' => 'បានផ្សាយ',
            'archived' => 'បានទុកក្នុងឯកសារ',
        };
    }

    /**
     * Resolves the display image: explicit featured_image, else the first
     * gallery image, else the site-wide configured fallback image.
     */
    public function featuredImageUrl(): string
    {
        if ($this->featured_image) {
            return Storage::disk('public')->url($this->featured_image);
        }

        $featuredGalleryImage = $this->images->firstWhere('is_featured', true) ?? $this->images->first();
        if ($featuredGalleryImage) {
            return Storage::disk('public')->url($featuredGalleryImage->image);
        }

        return asset(config('site.default_content_image'));
    }
}
