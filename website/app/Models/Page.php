<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Page extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'slug', 'title_km', 'title_en', 'content', 'excerpt', 'template',
        'featured_media_id', 'status', 'author_id', 'reviewer_id', 'approved_at',
        'published_at', 'meta_title', 'meta_description', 'canonical_url',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function blocks(): HasMany
    {
        return $this->hasMany(PageBlock::class)->orderBy('sort_order');
    }

    public function reviewLogs(): HasMany
    {
        return $this->hasMany(PageReviewLog::class)->latest();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function featuredMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_media_id');
    }

    /**
     * Every MenuItem (header/footer/mobile, or several at once) that links
     * to this page via the polymorphic linkable relation.
     */
    public function menuItems(): MorphMany
    {
        return $this->morphMany(MenuItem::class, 'linkable');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
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
            default => $status,
        };
    }

    public function featuredImageUrl(): string
    {
        return $this->featuredMedia?->url ?? asset(config('site.default_content_image'));
    }

    public function metaTitleOrFallback(): string
    {
        return $this->meta_title ?: $this->title_km;
    }

    public function metaDescriptionOrFallback(): ?string
    {
        return $this->meta_description ?: ($this->excerpt ?: null);
    }

    /**
     * Menu::tree() is cached forever and only busts on Menu/MenuItem saves
     * (see Menu::booted()). A page's own edits — slug, title, status — never
     * touched that cache, so e.g. renaming a slug after it was already
     * cached left the public menu pointing at the old URL. Mirroring
     * Menu/MenuItem's own pattern here closes that gap for every page
     * attribute, not just the publish-state toggle PageMenuSyncService
     * already handles explicitly.
     */
    protected static function booted(): void
    {
        static::saved(fn () => Menu::forgetCache());
        static::deleted(fn () => Menu::forgetCache());
    }
}
