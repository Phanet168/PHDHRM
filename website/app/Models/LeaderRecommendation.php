<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaderRecommendation extends Model
{
    protected $fillable = [
        'title_km', 'subtitle_km', 'leader_name_km', 'leader_position_km',
        'media_id', 'is_active', 'published_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LeaderRecommendationItem::class, 'recommendation_id')->orderBy('sort_order');
    }

    public function activeItems(): HasMany
    {
        return $this->items()->where('is_active', true);
    }

    /**
     * "Active + published": on, and either never gated behind a publish
     * date or that date has already passed — mirrors HeroSlide's own
     * active() scope so the two homepage-managed modules behave the same way.
     */
    public function scopePublished($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }

    public function leaderImageUrl(): ?string
    {
        return $this->media?->url;
    }
}
