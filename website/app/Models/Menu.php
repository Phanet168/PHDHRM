<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class Menu extends Model
{
    protected $fillable = ['name', 'slug', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('sort_order');
    }

    public function topLevelItems(): HasMany
    {
        return $this->items()->whereNull('parent_id');
    }

    /**
     * Cached, nested tree of active items for a published menu, by slug.
     * Returns an empty collection if the menu doesn't exist or is inactive.
     */
    public static function tree(string $slug): Collection
    {
        return Cache::rememberForever("menu.tree.{$slug}", function () use ($slug) {
            $menu = static::where('slug', $slug)->where('is_active', true)->first();

            if (! $menu) {
                return collect();
            }

            $items = $menu->items()->where('is_active', true)->with('linkable')->get()
                // Defense in depth: a page-linked item's own is_active flag is
                // kept in sync by PageMenuSyncService whenever the page is
                // published/unpublished/archived, but the public menu must
                // never expose a page that isn't currently published even if
                // that flag were ever stale (e.g. a menu item created/edited
                // directly in Menu Management, bypassing the Page editor) or
                // the linked page was trashed/deleted outright.
                ->reject(fn (MenuItem $item) => $item->link_type === 'page' && $item->linkable?->status !== 'published');

            return static::buildTree($items, null);
        });
    }

    private static function buildTree(Collection $items, ?int $parentId): Collection
    {
        return $items->where('parent_id', $parentId)
            ->sortBy('sort_order')
            ->map(function (MenuItem $item) use ($items) {
                $item->setRelation('children', static::buildTree($items, $item->id));

                return $item;
            })
            ->values();
    }

    public static function forgetCache(): void
    {
        foreach (['header', 'footer', 'mobile'] as $slug) {
            Cache::forget("menu.tree.{$slug}");
        }
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::forgetCache());
        static::deleted(fn () => static::forgetCache());
    }
}
