<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;

class PageMenuSyncService
{
    /**
     * Applies the page editor's menu-assignment sidebar in one call. For
     * each of "header"/"footer": create the MenuItem if the checkbox is on
     * and none exists yet, update placement (parent/sort/target) on an
     * existing one, or remove it if the checkbox was turned off. A page's
     * title changing never overwrites a menu item's own label unless the
     * caller explicitly asks to sync it — the label and the page title are
     * independent once a menu item exists.
     */
    public function sync(Page $page, array $config): void
    {
        foreach (['header', 'footer'] as $slug) {
            $section = $config[$slug] ?? null;
            $existing = $this->findMenuItem($page, $slug);

            if (empty($section['enabled'])) {
                if ($existing) {
                    $existing->delete();
                    AuditLog::record('deleted', 'MenuItem', $existing->id, "ដកទំព័រចេញពីម៉ឺនុយ {$slug}៖ {$page->title_km}");
                }
                continue;
            }

            $menu = Menu::where('slug', $slug)->first();
            if (! $menu) {
                continue;
            }

            $attributes = [
                'parent_id' => $section['parent_id'] ?: null,
                'sort_order' => $section['sort_order'] ?? 0,
                'target' => ($section['target'] ?? '_self') === '_blank' ? '_blank' : '_self',
                'is_active' => in_array($page->status, ['published'], true),
            ];

            if ($existing) {
                if (! empty($section['sync_label'])) {
                    $attributes['label_km'] = $section['label'] ?: $page->title_km;
                }
                $existing->update($attributes);
                AuditLog::record('updated', 'MenuItem', $existing->id, "ធ្វើបច្ចុប្បន្នភាពទីតាំងម៉ឺនុយ {$slug}៖ {$page->title_km}");
            } else {
                $item = MenuItem::create([
                    'menu_id' => $menu->id,
                    'parent_id' => $attributes['parent_id'],
                    'label_km' => $section['label'] ?: $page->title_km,
                    'link_type' => 'page',
                    'linkable_type' => Page::class,
                    'linkable_id' => $page->id,
                    'target' => $attributes['target'],
                    'is_active' => $attributes['is_active'],
                    'sort_order' => $attributes['sort_order'],
                ]);
                AuditLog::record('created', 'MenuItem', $item->id, "បន្ថែមទំព័រទៅម៉ឺនុយ {$slug}៖ {$page->title_km}");
            }
        }
    }

    /**
     * Note: these update every linked MenuItem via a bulk query-builder
     * update() rather than per-model save(), which is fine for the rows
     * themselves but does NOT fire MenuItem's saved/deleted events — so
     * Menu::forgetCache() (normally triggered by those events) is called
     * explicitly here, or the public header/footer/mobile nav could keep
     * serving a stale cached tree after a page is unpublished/republished.
     */
    public function deactivateMenuItems(Page $page): void
    {
        if ($page->menuItems()->update(['is_active' => false])) {
            Menu::forgetCache();
        }
    }

    public function reactivateMenuItems(Page $page): void
    {
        if ($page->menuItems()->update(['is_active' => true])) {
            Menu::forgetCache();
        }
    }

    private function findMenuItem(Page $page, string $menuSlug): ?MenuItem
    {
        return $page->menuItems()->whereHas('menu', fn ($q) => $q->where('slug', $menuSlug))->first();
    }
}
