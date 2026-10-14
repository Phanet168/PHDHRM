<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PageManagementTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function headerMenu(): Menu
    {
        return Menu::firstOrCreate(['slug' => 'header'], ['name' => 'Header', 'is_active' => true]);
    }

    public function test_full_lifecycle_create_blocks_menu_review_publish_and_it_becomes_publicly_visible(): void
    {
        $admin = $this->user('Super Admin');
        $this->headerMenu();

        // 1. Create as draft with a unique slug.
        $response = $this->actingAs($admin)->post(route('admin.pages.store'), [
            'title_km' => 'អំពីមន្ទីរ',
            'template' => 'about',
        ]);
        $page = Page::first();
        $response->assertRedirect(route('admin.pages.edit', $page));
        $this->assertSame('draft', $page->status);
        $this->assertNotEmpty($page->slug);

        // 2. Add a heading block and a text block.
        $this->actingAs($admin)->post(route('admin.pages.blocks.store', $page), [
            'block_type' => 'heading',
            'data' => ['text' => 'សូមស្វាគមន៍', 'level' => 'h2'],
        ]);
        $this->actingAs($admin)->post(route('admin.pages.blocks.store', $page), [
            'block_type' => 'text',
            'data' => ['html' => '<p>ខ្លឹមសារអំពីមន្ទីរ។</p>'],
        ]);
        $page->refresh();
        $this->assertCount(2, $page->blocks);

        // 3. Reorder (swap the two blocks).
        $ids = $page->blocks->pluck('id')->all();
        $this->actingAs($admin)->postJson(route('admin.pages.blocks.reorder', $page), [
            'items' => [
                ['id' => $ids[0], 'sort_order' => 1],
                ['id' => $ids[1], 'sort_order' => 0],
            ],
        ])->assertOk();
        $this->assertSame($ids[1], $page->blocks()->orderBy('sort_order')->first()->id);

        // 4. Duplicate a block, then hide it.
        $firstBlock = $page->blocks()->first();
        $this->actingAs($admin)->post(route('admin.pages.blocks.duplicate', [$page, $firstBlock]));
        $this->assertCount(3, $page->blocks()->get());
        $this->actingAs($admin)->post(route('admin.pages.blocks.toggle', [$page, $firstBlock]));
        $this->assertFalse($firstBlock->fresh()->is_active);

        // 5. Assign to the header menu.
        $this->actingAs($admin)->put(route('admin.pages.update', $page), [
            'title_km' => $page->title_km,
            'slug' => $page->slug,
            'template' => 'about',
            'menu_header_enabled' => '1',
            'menu_header_label' => 'អំពីមន្ទីរ',
            'menu_header_sort_order' => '0',
            'menu_header_target' => '_self',
        ]);
        $menuItem = MenuItem::where('linkable_type', Page::class)->where('linkable_id', $page->id)->first();
        $this->assertNotNull($menuItem);
        $this->assertSame('page', $menuItem->link_type);
        $this->assertFalse($menuItem->is_active); // page isn't published yet

        // 6. Submit for review, reviewer approves, then publisher publishes.
        $this->actingAs($admin)->post(route('admin.pages.submit', $page));
        $this->assertSame('pending_review', $page->fresh()->status);

        $reviewer = $this->user('Reviewer');
        $this->actingAs($reviewer)->post(route('admin.pages.approve', $page), ['note' => 'មើលល្អ']);
        $this->assertSame('approved', $page->fresh()->status);

        $this->actingAs($reviewer)->post(route('admin.pages.publish', $page));
        $page->refresh();
        $this->assertSame('published', $page->status);
        $this->assertNotNull($page->published_at);

        // 7. Publishing reactivates the linked menu item.
        $this->assertTrue($menuItem->fresh()->is_active);

        // 8. The page is now publicly visible with its active blocks rendered.
        $response = $this->get(route('pages.show', $page));
        $response->assertOk();
        $response->assertSee('សូមស្វាគមន៍'); // the active (moved) heading
        $response->assertSee('ខ្លឹមសារអំពីមន្ទីរ', false);

        // 9. Unpublishing deactivates the menu item again but keeps it linked.
        $this->actingAs($reviewer)->post(route('admin.pages.unpublish', $page));
        $this->assertFalse($menuItem->fresh()->is_active);
        $this->get(route('pages.show', $page))->assertNotFound();
    }

    public function test_reject_sends_page_back_to_draft_with_a_note(): void
    {
        $editor = $this->user('Editor');
        $reviewer = $this->user('Reviewer');

        $page = Page::create(['title_km' => 'ទំនាក់ទំនង', 'slug' => 'contact-test', 'template' => 'contact', 'status' => 'pending_review', 'author_id' => $editor->id]);

        $this->actingAs($reviewer)->post(route('admin.pages.reject', $page), ['note' => 'សូមកែសម្រួលចំណងជើង']);

        $page->refresh();
        $this->assertSame('draft', $page->status);
        $this->assertSame('rejected', $page->reviewLogs->first()->action);
        $this->assertSame('សូមកែសម្រួលចំណងជើង', $page->reviewLogs->first()->note);
    }

    public function test_duplicate_page_creates_independent_draft_copy_without_menu_or_publish_date(): void
    {
        $admin = $this->user('Super Admin');
        $this->headerMenu();

        $page = Page::create(['title_km' => 'ដើម', 'slug' => 'original', 'template' => 'default', 'status' => 'published', 'published_at' => now(), 'author_id' => $admin->id]);
        PageBlock::create(['page_id' => $page->id, 'block_type' => 'heading', 'data' => ['text' => 'ដើម', 'level' => 'h2'], 'sort_order' => 0]);
        MenuItem::create(['menu_id' => $this->headerMenu()->id, 'label_km' => 'ដើម', 'link_type' => 'page', 'linkable_type' => Page::class, 'linkable_id' => $page->id, 'is_active' => true, 'sort_order' => 0]);

        $response = $this->actingAs($admin)->post(route('admin.pages.duplicate', $page));

        $copy = Page::where('id', '!=', $page->id)->first();
        $response->assertRedirect(route('admin.pages.edit', $copy));
        $this->assertSame('draft', $copy->status);
        $this->assertNull($copy->published_at);
        $this->assertNotSame($page->slug, $copy->slug);
        $this->assertCount(1, $copy->blocks);
        $this->assertCount(0, $copy->menuItems); // duplicate is not auto-added to any menu
    }

    public function test_trash_and_restore(): void
    {
        $admin = $this->user('Super Admin');
        $page = Page::create(['title_km' => 'សាកល្បង', 'slug' => 'trash-test', 'template' => 'default', 'status' => 'draft', 'author_id' => $admin->id]);

        $this->actingAs($admin)->delete(route('admin.pages.destroy', $page));
        $this->assertSoftDeleted('pages', ['id' => $page->id]);

        $this->actingAs($admin)->post(route('admin.pages.restore', $page));
        $this->assertNull($page->fresh()->deleted_at);
    }

    public function test_permanent_delete_is_refused_while_a_menu_item_still_links_to_the_page(): void
    {
        $admin = $this->user('Super Admin');
        $page = Page::create(['title_km' => 'ភ្ជាប់ម៉ឺនុយ', 'slug' => 'linked-page', 'template' => 'default', 'status' => 'draft', 'author_id' => $admin->id]);
        $page->delete();
        MenuItem::create(['menu_id' => $this->headerMenu()->id, 'label_km' => 'ភ្ជាប់ម៉ឺនុយ', 'link_type' => 'page', 'linkable_type' => Page::class, 'linkable_id' => $page->id, 'is_active' => false, 'sort_order' => 0]);

        $response = $this->actingAs($admin)->delete(route('admin.pages.force-delete', $page));

        $response->assertSessionHasErrors('page');
        $this->assertDatabaseHas('pages', ['id' => $page->id]);
    }

    public function test_editor_without_publish_permission_cannot_publish(): void
    {
        $editor = $this->user('Editor');
        $page = Page::create(['title_km' => 'ត', 'slug' => 'perm-test', 'template' => 'default', 'status' => 'approved', 'author_id' => $editor->id]);

        $this->actingAs($editor)->post(route('admin.pages.publish', $page))->assertForbidden();
        $this->assertSame('approved', $page->fresh()->status);
    }

    public function test_html_css_block_requires_custom_html_permission_and_is_sanitized(): void
    {
        $editor = $this->user('Editor'); // has page.create/edit but NOT page.custom_html
        $page = Page::create(['title_km' => 'ត', 'slug' => 'html-test', 'template' => 'default', 'status' => 'draft', 'author_id' => $editor->id]);

        $this->actingAs($editor)->post(route('admin.pages.blocks.store', $page), [
            'block_type' => 'html_css',
            'data' => ['html' => '<script>alert(1)</script><p>hi</p>', 'css' => 'body { color: red; }'],
        ])->assertForbidden();

        $admin = $this->user('Super Admin');
        $this->actingAs($admin)->post(route('admin.pages.blocks.store', $page), [
            'block_type' => 'html_css',
            'data' => ['html' => '<script>alert(1)</script><p>hi</p>', 'css' => 'body { color: red; } @import url(evil.css);'],
        ]);

        $block = $page->blocks()->where('block_type', 'html_css')->first();
        $this->assertStringNotContainsString('<script>', $block->get('html'));
        $this->assertStringNotContainsString('@import', $block->get('css'));
    }

    public function test_seo_fallbacks_render_on_the_public_page(): void
    {
        $admin = $this->user('Super Admin');
        $page = Page::create([
            'title_km' => 'ទំព័រ SEO',
            'slug' => 'seo-test',
            'template' => 'default',
            'status' => 'published',
            'published_at' => now(),
            'excerpt' => 'សេចក្តីសង្ខេបសម្រាប់ SEO',
            'author_id' => $admin->id,
        ]);

        $response = $this->get(route('pages.show', $page));

        $response->assertOk();
        $response->assertSee('name="description" content="សេចក្តីសង្ខេបសម្រាប់ SEO"', false);
        $response->assertSee('property="og:title" content="ទំព័រ SEO"', false);
    }

    public function test_gallery_block_renders_selected_media_and_image_block_can_reuse_a_media_library_file(): void
    {
        Storage::fake('public');
        $admin = $this->user('Super Admin');

        Storage::disk('public')->put('library/a.jpg', 'x');
        Storage::disk('public')->put('library/b.jpg', 'x');
        $m1 = Media::create(['disk_path' => 'library/a.jpg', 'original_filename' => 'a.jpg', 'mime_type' => 'image/jpeg', 'size' => 1]);
        $m2 = Media::create(['disk_path' => 'library/b.jpg', 'original_filename' => 'b.jpg', 'mime_type' => 'image/jpeg', 'size' => 1]);

        $page = Page::create(['title_km' => 'វិចិត្រសាល', 'slug' => 'gallery-test', 'template' => 'default', 'status' => 'published', 'published_at' => now(), 'author_id' => $admin->id]);

        $this->actingAs($admin)->post(route('admin.pages.blocks.store', $page), [
            'block_type' => 'gallery',
            'data' => ['media_ids' => [$m1->id, $m2->id]],
        ]);
        $this->actingAs($admin)->post(route('admin.pages.blocks.store', $page), [
            'block_type' => 'image',
            'data' => ['media_id' => $m1->id, 'alt_text' => 'រូបភាព A'],
        ]);

        $response = $this->get(route('pages.show', $page));
        $response->assertOk();
        $response->assertSee('library/a.jpg', false);
        $response->assertSee('library/b.jpg', false);

        // Renaming/moving the media file must not break the page: the block
        // stores the media_id, and the image block re-resolves its own
        // disk_path independently on next edit rather than losing the file.
        $imageBlock = $page->blocks()->where('block_type', 'image')->first();
        $this->assertSame($m1->id, $imageBlock->get('media_id'));
    }
}
