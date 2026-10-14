<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Route as RouteFacade;

class HomepageQuickLink extends Model
{
    protected $fillable = [
        'title_km',
        'description_km',
        'icon',
        'link_type',
        'route_name',
        'url',
        'media_id',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function resolveUrl(): string
    {
        return match ($this->link_type) {
            'route' => $this->route_name && RouteFacade::has($this->route_name) ? route($this->route_name) : '#',
            'url' => $this->url ?: '#',
            default => '#',
        };
    }

    /**
     * Soft-deleted or missing media falls back to null automatically (the
     * belongsTo relation already excludes trashed Media rows), so the
     * homepage never needs to special-case a broken reference here.
     */
    public function imageUrl(): ?string
    {
        return $this->media?->url;
    }

    /**
     * A card-specific fallback (keyed by destination, not by record id) used
     * only when no media_id is selected — never one single image reused for
     * every card. Falls back to the general site stock image for any
     * destination not in this map (e.g. a future admin-added custom URL card).
     */
    public function defaultImagePath(): string
    {
        return match ($this->route_name) {
            'facilities.index' => 'images/stock/stock-1.jpg',
            'news.index' => 'images/stock/stock-3.jpg',
            'health-content.index' => 'images/stock/stock-2.jpg',
            'about' => 'images/stock/stock-4.jpg',
            'legal-documents.index' => 'images/stock/stock-4.jpg',
            'contact' => 'images/stock/stock-1.jpg',
            default => 'images/stock/stock-2.jpg',
        };
    }

    /**
     * Per-card icon badge color (keyed by destination, matching the approved
     * reference design), not a single fixed color for every card.
     */
    public function iconColor(): string
    {
        return match ($this->route_name) {
            'facilities.index' => '#005cad',
            'news.index' => '#6366f1',
            'legal-documents.index' => '#7c3aed',
            'health-content.index' => '#0ea962',
            'about' => '#005cad',
            'contact' => '#0891b2',
            default => '#005cad',
        };
    }
}
