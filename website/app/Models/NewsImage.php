<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsImage extends Model
{
    protected $fillable = [
        'news_id',
        'media_id',
        'image',
        'caption',
        'alt_text',
        'sort_order',
    ];

    public function news(): BelongsTo
    {
        return $this->belongsTo(News::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /** Same resolution order as News::featuredImageUrl() — see that method's note. */
    public function url(): string
    {
        if ($this->media_id) {
            return $this->media && $this->media->isImage()
                ? $this->media->url
                : asset(config('site.default_news_image'));
        }

        return Media::resolveLegacyPath($this->image) ?? asset(config('site.default_news_image'));
    }

    public function altText(): ?string
    {
        return $this->alt_text ?: $this->media?->alt_text;
    }

    public function captionText(): ?string
    {
        return $this->caption ?: $this->media?->caption;
    }
}
