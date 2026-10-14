<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\PageBanner;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PageBannerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Super Admin');

        return $admin;
    }

    private function image(string $name = 'banner.jpg'): Media
    {
        return Media::create(['disk_path' => 'library/'.$name, 'original_filename' => $name, 'mime_type' => 'image/jpeg', 'size' => 1]);
    }

    // --- Public rendering ---

    public function test_every_managed_page_shows_its_default_banner_when_nothing_is_configured(): void
    {
        foreach (PageBanner::PAGES as $page) {
            if ($page['route'] === null) {
                continue;
            }

            $this->get(route($page['route']))
                ->assertOk()
                ->assertSee($page['default'], false);
        }
    }

    public function test_a_configured_banner_replaces_the_default_on_its_page_only(): void
    {
        $media = $this->image('custom-about.jpg');
        PageBanner::create(['page_key' => 'about', 'media_id' => $media->id]);

        $this->get(route('about'))
            ->assertOk()
            ->assertSee('library/custom-about.jpg', false)
            ->assertDontSee(PageBanner::PAGES['about']['default'], false);

        $this->get(route('contact'))
            ->assertOk()
            ->assertDontSee('library/custom-about.jpg', false);
    }

    public function test_health_content_index_uses_its_managed_banner(): void
    {
        $media = $this->image('health-hero.jpg');
        PageBanner::create(['page_key' => 'health-content', 'media_id' => $media->id]);

        $this->get(route('health-content.index'))
            ->assertOk()
            ->assertSee('library/health-hero.jpg', false);
    }

    public function test_a_soft_deleted_media_falls_back_to_the_default_banner(): void
    {
        $media = $this->image('gone.jpg');
        PageBanner::create(['page_key' => 'news', 'media_id' => $media->id]);
        $media->delete();

        $this->get(route('news.index'))
            ->assertOk()
            ->assertDontSee('library/gone.jpg', false)
            ->assertSee(PageBanner::PAGES['news']['default'], false);
    }

    public function test_a_non_image_media_is_never_used_as_a_banner(): void
    {
        $video = Media::create(['disk_path' => 'library/v.mp4', 'original_filename' => 'v.mp4', 'mime_type' => 'video/mp4', 'size' => 1]);
        PageBanner::create(['page_key' => 'contact', 'media_id' => $video->id]);

        $this->get(route('contact'))
            ->assertOk()
            ->assertDontSee('library/v.mp4', false)
            ->assertSee(PageBanner::PAGES['contact']['default'], false);
    }

    // --- Admin access ---

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('admin.page-banners.index'))->assertRedirect();
    }

    public function test_users_without_the_permission_get_403(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $reviewer = User::factory()->create(['is_active' => true]);
        $reviewer->assignRole('Reviewer');

        $this->actingAs($reviewer)->get(route('admin.page-banners.index'))->assertForbidden();
        $this->actingAs($reviewer)->get(route('admin.page-banners.edit', 'about'))->assertForbidden();
        $this->actingAs($reviewer)->put(route('admin.page-banners.update', 'about'), ['reset_to_default' => 1])->assertForbidden();
    }

    public function test_index_lists_every_managed_page(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.page-banners.index'));

        $response->assertOk();
        foreach (PageBanner::PAGES as $page) {
            $response->assertSee($page['label']);
        }
    }

    public function test_unknown_page_key_is_a_404(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.page-banners.edit', 'nonexistent'))->assertNotFound();
        $this->actingAs($admin)->put(route('admin.page-banners.update', 'nonexistent'), [])->assertNotFound();
        $this->assertSame(0, PageBanner::count());
    }

    public function test_edit_page_renders_and_creates_its_row_and_folder_on_demand(): void
    {
        $this->actingAs($this->admin())->get(route('admin.page-banners.edit', 'about'))->assertOk();

        $banner = PageBanner::where('page_key', 'about')->firstOrFail();
        $folder = MediaFolder::where('owner_type', PageBanner::class)->where('owner_id', $banner->id)->first();
        $this->assertNotNull($folder);
        $this->assertSame('ផ្ទាំងបដាទំព័រ', $folder->parent->name);
    }

    // --- Admin updates ---

    public function test_admin_can_choose_an_existing_library_image(): void
    {
        $media = $this->image('picked.jpg');

        $this->actingAs($this->admin())
            ->put(route('admin.page-banners.update', 'facilities'), ['media_id' => $media->id])
            ->assertRedirect(route('admin.page-banners.edit', 'facilities'));

        $this->assertSame($media->id, PageBanner::where('page_key', 'facilities')->value('media_id'));
        $this->get(route('facilities.index'))->assertSee('library/picked.jpg', false);
    }

    public function test_choosing_a_non_image_media_is_rejected(): void
    {
        $video = Media::create(['disk_path' => 'library/v.mp4', 'original_filename' => 'v.mp4', 'mime_type' => 'video/mp4', 'size' => 1]);

        $this->actingAs($this->admin())
            ->put(route('admin.page-banners.update', 'facilities'), ['media_id' => $video->id])
            ->assertStatus(422);

        $this->assertNull(PageBanner::where('page_key', 'facilities')->value('media_id'));
    }

    public function test_choosing_a_missing_media_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.page-banners.update', 'facilities'), ['media_id' => 99999])
            ->assertStatus(422);
    }

    public function test_uploading_a_new_image_files_it_into_the_pages_own_folder(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())
            ->put(route('admin.page-banners.update', 'legal-documents'), [
                'banner_file' => UploadedFile::fake()->image('legal.jpg', 1600, 500),
            ])
            ->assertRedirect();

        $banner = PageBanner::where('page_key', 'legal-documents')->firstOrFail();
        $this->assertNotNull($banner->media_id);

        $folder = MediaFolder::where('owner_type', PageBanner::class)->where('owner_id', $banner->id)->firstOrFail();
        $this->assertSame($folder->id, $banner->media->folder_id);
        $this->assertSame("#{$banner->id} — ".PageBanner::label('legal-documents'), $folder->name);
    }

    public function test_uploading_a_non_image_is_rejected(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())
            ->put(route('admin.page-banners.update', 'legal-documents'), [
                'banner_file' => UploadedFile::fake()->create('evil.pdf', 10, 'application/pdf'),
            ])
            ->assertSessionHasErrors('banner_file');

        $this->assertNull(PageBanner::where('page_key', 'legal-documents')->value('media_id'));
    }

    public function test_reset_to_default_clears_the_custom_banner(): void
    {
        $media = $this->image('temp.jpg');
        PageBanner::create(['page_key' => 'about', 'media_id' => $media->id]);

        $this->actingAs($this->admin())
            ->put(route('admin.page-banners.update', 'about'), ['reset_to_default' => 1])
            ->assertRedirect();

        $this->assertNull(PageBanner::where('page_key', 'about')->value('media_id'));
        $this->get(route('about'))->assertSee(PageBanner::PAGES['about']['default'], false);
    }

    public function test_submitting_the_unchanged_picker_keeps_the_current_banner(): void
    {
        $media = $this->image('keep.jpg');
        PageBanner::create(['page_key' => 'about', 'media_id' => $media->id]);

        // The picker's "none" radio posts an empty media_id when nothing new is chosen.
        $this->actingAs($this->admin())
            ->put(route('admin.page-banners.update', 'about'), ['media_id' => ''])
            ->assertRedirect();

        $this->assertSame($media->id, PageBanner::where('page_key', 'about')->value('media_id'));
    }

    public function test_deleting_the_media_row_nulls_the_reference_instead_of_breaking(): void
    {
        $media = $this->image('force.jpg');
        PageBanner::create(['page_key' => 'about', 'media_id' => $media->id]);

        $media->forceDelete();

        $this->assertNull(PageBanner::where('page_key', 'about')->value('media_id'));
        $this->get(route('about'))->assertOk()->assertSee(PageBanner::PAGES['about']['default'], false);
    }

    public function test_updates_are_audit_logged(): void
    {
        $media = $this->image('audit.jpg');

        $this->actingAs($this->admin())
            ->put(route('admin.page-banners.update', 'about'), ['media_id' => $media->id]);

        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'PageBanner', 'action' => 'updated']);
    }
}
