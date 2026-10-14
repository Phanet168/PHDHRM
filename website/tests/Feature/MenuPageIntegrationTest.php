<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuPageIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Super Admin');

        return $admin;
    }

    private function headerMenu(): Menu
    {
        return Menu::create(['name' => 'Header', 'slug' => 'header', 'is_active' => true]);
    }

    /**
     * The spec's own test plan, steps 1-7: create a Custom Page, publish it,
     * link it from a menu item created directly through Menu Management
     * (not the Page editor's shortcut), confirm the public menu resolves it,
     * confirm a slug change doesn't break the link, and confirm
     * unpublish/republish correctly hides/shows it again.
     */
    public function test_menu_item_created_via_menu_management_tracks_the_linked_pages_publish_state(): void
    {
        $admin = $this->admin();
        $menu = $this->headerMenu();
        $parent = MenuItem::create(['menu_id' => $menu->id, 'label_km' => 'អំពីមន្ទីរ', 'link_type' => 'route', 'route_name' => 'about', 'is_active' => true, 'sort_order' => 0]);

        $page = Page::create(['title_km' => 'ប្រវត្តិមន្ទីរសុខាភិបាលនៃរដ្ឋបាលខេត្តស្ទឹងត្រែង', 'slug' => 'history', 'template' => 'default', 'status' => 'draft', 'author_id' => $admin->id]);

        // Menu item is created through the Menu Management UI directly, with
        // a deliberately SHORT custom label, is_active left checked (the
        // form's default) even though the page is still a draft.
        $response = $this->actingAs($admin)->post(route('admin.menus.items.store', $menu), [
            'parent_id' => $parent->id,
            'label_km' => 'ប្រវត្តិមន្ទីរ',
            'link_type' => 'page',
            'linkable_id' => $page->id,
            'target' => '_self',
            'is_active' => '1',
        ]);
        $response->assertRedirect();

        $item = MenuItem::where('linkable_type', Page::class)->where('linkable_id', $page->id)->first();
        $this->assertNotNull($item);
        $this->assertSame('ប្រវត្តិមន្ទីរ', $item->label_km); // independent from the page's own title

        // Draft page: even though the menu item's own is_active is true, the
        // public menu must not expose it.
        $tree = Menu::tree('header');
        $this->assertMenuTreeContainsLabel($tree, 'ប្រវត្តិមន្ទីរ', false);

        // Publish -> now it must appear. No manual cache-busting here: saving
        // a Page must invalidate the cached menu tree on its own.
        $page->update(['status' => 'published', 'published_at' => now()]);
        $tree = Menu::tree('header');
        $this->assertMenuTreeContainsLabel($tree, 'ប្រវត្តិមន្ទីរ', true);
        $child = $this->findMenuTreeItem($tree, 'ប្រវត្តិមន្ទីរ');
        $this->assertSame(route('pages.show', $page), $child->resolveUrl());

        // Change the page's slug: the menu links by ID, so it must keep working.
        $page->update(['slug' => 'department-history']);
        $tree = Menu::tree('header');
        $child = $this->findMenuTreeItem($tree, 'ប្រវត្តិមន្ទីរ');
        $this->assertSame('department-history', $page->fresh()->slug);
        $this->assertSame(route('pages.show', $page->fresh()), $child->resolveUrl());
        $this->assertStringContainsString('department-history', $child->resolveUrl());

        // Unpublish -> hidden from the public menu again, item NOT deleted.
        $page->update(['status' => 'draft']);
        $this->assertMenuTreeContainsLabel(Menu::tree('header'), 'ប្រវត្តិមន្ទីរ', false);
        $this->assertDatabaseHas('menu_items', ['id' => $item->id]);

        // Publish again -> visible again automatically, no menu edits needed.
        $page->update(['status' => 'published']);
        $this->assertMenuTreeContainsLabel(Menu::tree('header'), 'ប្រវត្តិមន្ទីរ', true);
    }

    public function test_publishing_and_unpublishing_through_the_page_editor_busts_the_menu_cache(): void
    {
        // Regression test for a real bug found during this audit: the menu
        // sync used a bulk query-builder update() to flip is_active, which
        // does not fire MenuItem's saved event, so Menu::forgetCache() was
        // never triggered and the public nav could keep serving a stale
        // cached tree after a page was published/unpublished.
        $admin = $this->admin();
        $menu = $this->headerMenu();
        $page = Page::create(['title_km' => 'សាកល្បង', 'slug' => 'cache-test', 'template' => 'default', 'status' => 'approved', 'author_id' => $admin->id]);
        MenuItem::create(['menu_id' => $menu->id, 'label_km' => 'សាកល្បង', 'link_type' => 'page', 'linkable_type' => Page::class, 'linkable_id' => $page->id, 'is_active' => false, 'sort_order' => 0]);

        // Prime the cache while the item is inactive.
        $this->assertMenuTreeContainsLabel(Menu::tree('header'), 'សាកល្បង', false);

        $this->actingAs($admin)->post(route('admin.pages.publish', $page));

        // Without the fix this would still read the stale cached (empty) tree.
        $this->assertMenuTreeContainsLabel(Menu::tree('header'), 'សាកល្បង', true);

        $this->actingAs($admin)->post(route('admin.pages.unpublish', $page));
        $this->assertMenuTreeContainsLabel(Menu::tree('header'), 'សាកល្បង', false);
    }

    public function test_system_route_menu_item_opens_the_existing_facilities_module_unchanged(): void
    {
        $admin = $this->admin();
        $menu = $this->headerMenu();

        $response = $this->actingAs($admin)->post(route('admin.menus.items.store', $menu), [
            'label_km' => 'មូលដ្ឋានសុខាភិបាល',
            'link_type' => 'route',
            'route_name' => 'facilities.index',
            'target' => '_self',
            'is_active' => '1',
        ]);
        $response->assertRedirect();

        $item = MenuItem::where('route_name', 'facilities.index')->first();
        $this->assertSame(route('facilities.index'), $item->resolveUrl());

        $this->assertMenuTreeContainsLabel(Menu::tree('header'), 'មូលដ្ឋានសុខាភិបាល', true);
    }

    public function test_custom_url_menu_item_keeps_its_external_link(): void
    {
        $admin = $this->admin();
        $menu = $this->headerMenu();

        $this->actingAs($admin)->post(route('admin.menus.items.store', $menu), [
            'label_km' => 'ក្រសួងសុខាភិបាល',
            'link_type' => 'url',
            'url' => 'https://moh.gov.kh',
            'target' => '_blank',
            'is_active' => '1',
        ]);

        $item = MenuItem::where('label_km', 'ក្រសួងសុខាភិបាល')->first();
        $this->assertSame('https://moh.gov.kh', $item->resolveUrl());
        $this->assertSame('_blank', $item->target);
    }

    public function test_content_category_and_content_link_types_pass_validation(): void
    {
        // Regression test for a second bug found during this audit: these
        // two link types already existed in the DB enum, the admin form,
        // and the controller's mapping, but the FormRequest's whitelist
        // never included them, so submitting either always failed validation.
        $admin = $this->admin();
        $menu = $this->headerMenu();
        $category = \App\Models\ContentCategory::create(['name_km' => 'សុខភាព', 'slug' => 'health', 'is_active' => true]);

        $response = $this->actingAs($admin)->post(route('admin.menus.items.store', $menu), [
            'label_km' => 'ប្រភេទសុខភាព',
            'link_type' => 'content_category',
            'linkable_id' => $category->id,
            'target' => '_self',
            'is_active' => '1',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('menu_items', ['label_km' => 'ប្រភេទសុខភាព', 'link_type' => 'content_category']);
    }

    public function test_moving_the_pages_featured_media_to_another_folder_does_not_break_the_page(): void
    {
        // Sanity check for "moving/renaming media must not break Page
        // content": the page stores a stable media_id, not a raw path.
        \Illuminate\Support\Facades\Storage::fake('public');
        $admin = $this->admin();
        \Illuminate\Support\Facades\Storage::disk('public')->put('library/cover.jpg', 'x');
        $media = \App\Models\Media::create(['disk_path' => 'library/cover.jpg', 'original_filename' => 'cover.jpg', 'mime_type' => 'image/jpeg', 'size' => 1]);
        $page = Page::create(['title_km' => 'ត', 'slug' => 'media-safety', 'template' => 'default', 'status' => 'published', 'published_at' => now(), 'author_id' => $admin->id, 'featured_media_id' => $media->id]);

        $folder = \App\Models\MediaFolder::create(['name' => 'ថតថ្មី', 'slug' => 'new-folder']);
        $media->update(['folder_id' => $folder->id, 'disk_path' => 'library/renamed-cover.jpg']);

        $this->assertSame($media->id, $page->fresh()->featured_media_id);
        $this->assertStringContainsString('renamed-cover.jpg', $page->fresh()->featuredImageUrl());
    }

    private function assertMenuTreeContainsLabel($tree, string $label, bool $shouldExist): void
    {
        $found = $this->findMenuTreeItem($tree, $label);
        $shouldExist ? $this->assertNotNull($found, "Expected \"{$label}\" to be in the menu tree.") : $this->assertNull($found, "Expected \"{$label}\" to NOT be in the menu tree.");
    }

    private function findMenuTreeItem($tree, string $label): ?MenuItem
    {
        foreach ($tree as $item) {
            if ($item->label_km === $label) {
                return $item;
            }
            $found = $this->findMenuTreeItem($item->children, $label);
            if ($found) {
                return $found;
            }
        }

        return null;
    }
}
