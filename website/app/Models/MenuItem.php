<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Route as RouteFacade;

class MenuItem extends Model
{
    protected $fillable = [
        'menu_id',
        'parent_id',
        'label_km',
        'label_en',
        'link_type',
        'route_name',
        'url',
        'linkable_type',
        'linkable_id',
        'icon',
        'target',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    public function linkSummary(): string
    {
        return match ($this->link_type) {
            'route' => 'Route: ' . $this->route_name,
            'url' => 'URL: ' . $this->url,
            'page' => 'Page: ' . ($this->linkable?->title_km ?? '—'),
            'facility' => 'Facility: ' . ($this->linkable?->name_km ?? '—'),
            'news_category' => 'News Category: ' . ($this->linkable?->name_km ?? '—'),
            'content_category' => 'Content Category: ' . ($this->linkable?->name_km ?? '—'),
            'content' => 'Content: ' . ($this->linkable?->title_km ?? '—'),
            default => '—',
        };
    }

    public function isActiveLink(): bool
    {
        if ($this->link_type === 'route' && $this->route_name) {
            if (request()->routeIs($this->route_name)) {
                return true;
            }

            $prefix = \Illuminate\Support\Str::before($this->route_name, '.');

            return request()->routeIs($prefix . '.*');
        }

        return false;
    }

    public function resolveUrl(): string
    {
        return match ($this->link_type) {
            'route' => $this->route_name && RouteFacade::has($this->route_name) ? route($this->route_name) : '#',
            'url' => $this->url ?: '#',
            'page' => $this->linkable ? route('pages.show', $this->linkable) : '#',
            'facility' => $this->linkable ? route('facilities.show', $this->linkable) : '#',
            'news_category' => $this->linkable ? route('news.index', ['category' => $this->linkable->slug]) : '#',
            'content_category' => $this->linkable ? route('category.show', $this->linkable) : '#',
            'content' => $this->linkable ? route('content.show', $this->linkable) : '#',
            default => '#',
        };
    }

    protected static function booted(): void
    {
        static::saved(fn () => Menu::forgetCache());
        static::deleted(fn () => Menu::forgetCache());
    }
}
