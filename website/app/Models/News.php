<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class News extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'news';

    protected $fillable = [
        'category_id',
        'facility_id',
        'title_km',
        'title_en',
        'slug',
        'excerpt',
        'content',
        'featured_image',
        'featured_media_id',
        'status',
        'is_featured',
        'views_count',
        'published_at',
        'meta_title',
        'meta_description',
        'author_id',
        'reviewer_id',
        'approved_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'approved_at' => 'datetime',
        'is_featured' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(NewsCategory::class, 'category_id');
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
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
        return $this->hasMany(NewsImage::class)->orderBy('sort_order');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(NewsAttachment::class)->orderBy('sort_order');
    }

    public function featuredMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_media_id');
    }

    /**
     * The Media Library is the source of truth. `featured_image` (a disk path, not a public
     * URL) is only read as a fallback for rows saved before featured_media_id existed. Once
     * featured_media_id IS set, it alone decides the outcome: if that Media can no longer be
     * resolved (soft/force-deleted), this counts as missing and goes straight to the site
     * default — featured_image is by then just a mirror of the Media's own path, not an
     * independent source of truth, so it is not read as a second fallback in that case.
     *
     * The legacy path itself is resolved via Media::resolveLegacyPath() rather than assumed to
     * exist under storage/app/public: some older seeded News rows point at a static demo asset
     * under public/ instead, and a path that resolves under neither convention (a since-deleted
     * or never-uploaded file) falls back to the site default too, instead of rendering broken.
     */
    public function featuredImageUrl(): string
    {
        if ($this->featured_media_id) {
            return $this->featuredMedia && $this->featuredMedia->isImage()
                ? $this->featuredMedia->url
                : asset(config('site.default_news_image'));
        }

        return Media::resolveLegacyPath($this->featured_image) ?? asset(config('site.default_news_image'));
    }

    public function featuredImageAlt(): string
    {
        return $this->featuredMedia?->alt_text ?: $this->title_km;
    }

    /**
     * Safe HTML for the public page. New saves are already sanitized (see
     * HtmlSanitizer::clean(), applied in the admin controller before this is stored), so this
     * mostly re-sanitizes defensively. Rows saved before the rich editor existed hold plain
     * text — they are detected by having no "<" at all and are escaped + line-break-converted
     * instead, so old articles keep their paragraph breaks instead of collapsing onto one line.
     */
    public function renderedContentHtml(): string
    {
        $content = (string) $this->content;

        if ($content === '') {
            return '';
        }

        if (! str_contains($content, '<')) {
            return nl2br(e($content));
        }

        return \App\Services\HtmlSanitizer::clean($content);
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
}
