<?php

namespace App\Models;

use App\Support\Duration;
use App\Support\VideoEmbed;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HealthContent extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'title_km',
        'title_en',
        'title_original',
        'slug',
        'excerpt',
        'content',
        'educational_message',
        'summary_status',
        'featured_image',
        'is_featured',
        'video_type',
        'video_url',
        'video_media_id',
        'thumbnail_media_id',
        'duration_seconds',
        'youtube_video_id',
        'external_thumbnail_url',
        'source_name',
        'source_channel_url',
        'source_video_url',
        'source_description',
        'source_published_at',
        'status',
        'author_id',
        'updated_by',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'source_published_at' => 'datetime',
        'is_featured' => 'boolean',
        'duration_seconds' => 'integer',
    ];

    /**
     * content_type is deliberately excluded from $fillable — it is derived
     * from video_type/video_url/video_media_id on every save, never set
     * directly from a request, so it can never drift out of sync with
     * whether a record actually has a playable video attached.
     */
    protected static function booted(): void
    {
        static::saving(function (self $content) {
            $hasVideo = ($content->video_type === 'youtube' || $content->video_type === 'facebook') && ! empty($content->video_url)
                || $content->video_type === 'uploaded' && ! empty($content->video_media_id);

            $content->content_type = $hasVideo ? 'video' : 'article';
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(HealthContentCategory::class, 'category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function thumbnailMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'thumbnail_media_id');
    }

    public function videoMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'video_media_id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * Items with an actual playable video attached — used by the homepage's
     * video section, which has nothing to show for a plain text article.
     * content_type is the primary gate (kept in sync by booted()'s saving
     * hook); the per-type column checks are a consistency safeguard.
     */
    public function scopeWithVideo($query)
    {
        return $query->where('content_type', 'video')->where(function ($q) {
            $q->where(fn ($q2) => $q2->where('video_type', 'youtube')->whereNotNull('video_url'))
                ->orWhere(fn ($q2) => $q2->where('video_type', 'facebook')->whereNotNull('video_url'))
                ->orWhere(fn ($q2) => $q2->where('video_type', 'uploaded')->whereNotNull('video_media_id'));
        });
    }

    public function hasVideo(): bool
    {
        return (in_array($this->video_type, ['youtube', 'facebook'], true) && ! empty($this->video_url))
            || ($this->video_type === 'uploaded' && ! empty($this->video_media_id));
    }

    public function videoEmbed(): ?array
    {
        if ($this->video_type === 'uploaded' && $this->videoMedia) {
            return ['type' => 'video', 'src' => $this->videoMedia->url];
        }

        if (in_array($this->video_type, ['youtube', 'facebook'], true)) {
            return VideoEmbed::resolve($this->video_url);
        }

        return null;
    }

    /**
     * Fallback chain so a video card never shows a broken image: an
     * explicitly picked Media Library thumbnail, then a YouTube-hosted
     * thumbnail URL saved at import time, then a freshly-derived YouTube
     * thumbnail, then the legacy plain-upload featured_image (so articles
     * saved before this feature existed keep their image), then the
     * sitewide default.
     */
    public function thumbnailUrl(): string
    {
        if ($this->thumbnailMedia) {
            return $this->thumbnailMedia->url;
        }

        if ($this->external_thumbnail_url) {
            return $this->external_thumbnail_url;
        }

        if ($this->video_type === 'youtube' && ($youtubeId = VideoEmbed::youtubeId($this->video_url))) {
            return "https://img.youtube.com/vi/{$youtubeId}/hqdefault.jpg";
        }

        if ($this->featured_image) {
            return asset($this->featured_image);
        }

        return asset(config('site.default_content_image'));
    }

    public function durationLabel(): ?string
    {
        return Duration::format($this->duration_seconds);
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
