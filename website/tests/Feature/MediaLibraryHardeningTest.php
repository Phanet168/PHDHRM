<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\HeroSlide;
use App\Models\Media;
use App\Models\News;
use App\Models\User;
use App\Services\MediaManagerService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Covers the Media Library standardization pass: permanent-delete safety (blocked when
 * referenced, including the folder-cascade case), usage detection (single-item "Used In" with
 * links, and the bulk Used/Unused computation), missing-file fallback, the type/usage filters,
 * and permission/path-safety guards.
 */
class MediaLibraryHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Super Admin');

        return $admin;
    }

    private function trashedImage(string $path = 'library/a.jpg'): Media
    {
        Storage::disk('public')->put($path, 'x');
        $media = Media::create(['disk_path' => $path, 'original_filename' => basename($path), 'mime_type' => 'image/jpeg', 'size' => 1]);
        $media->delete();

        return $media;
    }

    // --- Permanent delete blocked when referenced (spec §20) ---

    public function test_permanent_delete_is_blocked_when_the_media_is_still_referenced(): void
    {
        Storage::fake('public');
        $media = $this->trashedImage();
        HeroSlide::create(['image' => $media->disk_path, 'title_km' => 'ស្លាយ']);

        $response = $this->actingAs($this->admin())->delete(route('admin.media.force-delete', $media));

        $response->assertRedirect();
        $this->assertDatabaseHas('media', ['id' => $media->id]);
        Storage::disk('public')->assertExists($media->disk_path);
    }

    public function test_permanent_delete_succeeds_with_force_after_being_blocked(): void
    {
        Storage::fake('public');
        $media = $this->trashedImage();
        HeroSlide::create(['image' => $media->disk_path, 'title_km' => 'ស្លាយ']);

        $this->actingAs($this->admin())->delete(route('admin.media.force-delete', $media), ['force' => 1]);

        $this->assertDatabaseMissing('media', ['id' => $media->id]);
        Storage::disk('public')->assertMissing($media->disk_path);
    }

    public function test_permanent_delete_of_a_folder_is_blocked_wholesale_when_any_descendant_media_is_referenced(): void
    {
        Storage::fake('public');
        $manager = app(MediaManagerService::class);
        $folder = $manager->createFolder(null, 'ThemeFolder', null);

        Storage::disk('public')->put('library/used.jpg', 'x');
        $used = Media::create(['folder_id' => $folder->id, 'disk_path' => 'library/used.jpg', 'original_filename' => 'used.jpg', 'mime_type' => 'image/jpeg', 'size' => 1]);
        Storage::disk('public')->put('library/unused.jpg', 'x');
        $unused = Media::create(['folder_id' => $folder->id, 'disk_path' => 'library/unused.jpg', 'original_filename' => 'unused.jpg', 'mime_type' => 'image/jpeg', 'size' => 1]);
        HeroSlide::create(['image' => 'library/used.jpg', 'title_km' => 'ស្លាយ']);

        $folder->delete();
        $used->delete();
        $unused->delete();

        $response = $this->actingAs($this->admin())->delete(route('admin.media.folders.force-delete', $folder));

        $response->assertRedirect();
        // Nothing was deleted — never a partial cascade.
        $this->assertDatabaseHas('media_folders', ['id' => $folder->id]);
        $this->assertDatabaseHas('media', ['id' => $used->id]);
        $this->assertDatabaseHas('media', ['id' => $unused->id]);
    }

    public function test_permanent_delete_of_a_folder_succeeds_with_force_and_removes_everything(): void
    {
        Storage::fake('public');
        $manager = app(MediaManagerService::class);
        $folder = $manager->createFolder(null, 'ThemeFolder', null);

        Storage::disk('public')->put('library/used.jpg', 'x');
        $used = Media::create(['folder_id' => $folder->id, 'disk_path' => 'library/used.jpg', 'original_filename' => 'used.jpg', 'mime_type' => 'image/jpeg', 'size' => 1]);
        HeroSlide::create(['image' => 'library/used.jpg', 'title_km' => 'ស្លាយ']);
        $folder->delete();
        $used->delete();

        $this->actingAs($this->admin())->delete(route('admin.media.folders.force-delete', $folder), ['force' => 1]);

        $this->assertDatabaseMissing('media_folders', ['id' => $folder->id]);
        $this->assertDatabaseMissing('media', ['id' => $used->id]);
    }

    public function test_empty_trash_still_wipes_referenced_media_without_needing_force(): void
    {
        // Deliberate: "Empty Trash" already carries its own strong confirmation in the UI, so
        // it intentionally bypasses the per-item block that a single Permanent Delete respects.
        Storage::fake('public');
        $media = $this->trashedImage('library/still-used.jpg');
        HeroSlide::create(['image' => $media->disk_path, 'title_km' => 'ស្លាយ']);

        $this->actingAs($this->admin())->post(route('admin.media.empty-trash'));

        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }

    // --- Usage detection: single-item references with links (spec §21) ---

    public function test_find_media_references_includes_a_working_link_to_the_referencing_articles_edit_page(): void
    {
        $media = Media::create(['disk_path' => 'library/cover.jpg', 'original_filename' => 'cover.jpg', 'mime_type' => 'image/jpeg', 'size' => 1]);
        $news = News::create(['title_km' => 'អត្ថបទប្រើរូប', 'slug' => 'article-uses-media', 'status' => 'draft', 'featured_media_id' => $media->id]);

        $response = $this->actingAs($this->admin())->getJson(route('admin.media.references', $media));

        $response->assertOk();
        $references = $response->json('references');
        $this->assertNotEmpty($references);
        $newsRef = collect($references)->firstWhere('label', 'ព័ត៌មាន និងសកម្មភាព');
        $this->assertNotNull($newsRef);
        $this->assertSame(route('admin.news.edit', $news), $newsRef['links'][0]['url']);
        $this->assertSame('អត្ថបទប្រើរូប', $newsRef['links'][0]['title']);
    }

    public function test_find_media_references_is_empty_for_an_unused_file(): void
    {
        $media = Media::create(['disk_path' => 'library/unused.jpg', 'original_filename' => 'unused.jpg', 'mime_type' => 'image/jpeg', 'size' => 1]);

        $response = $this->actingAs($this->admin())->getJson(route('admin.media.references', $media));

        $response->assertOk()->assertJson(['references' => []]);
    }

    // --- Bulk usage detection (spec §21/§22) ---

    public function test_used_media_ids_correctly_separates_used_from_unused_in_bulk(): void
    {
        $used = Media::create(['disk_path' => 'library/used.jpg', 'original_filename' => 'used.jpg', 'mime_type' => 'image/jpeg', 'size' => 1]);
        $unused = Media::create(['disk_path' => 'library/unused.jpg', 'original_filename' => 'unused.jpg', 'mime_type' => 'image/jpeg', 'size' => 1]);
        Facility::create(['code' => 'FAC1', 'name_km' => 'មូលដ្ឋាន', 'slug' => 'fac-usage-test', 'facility_type' => 'health_center', 'district' => 'ស្រុក', 'commune' => 'ឃុំ', 'logo' => 'library/used.jpg']);

        $manager = app(MediaManagerService::class);
        $usedIds = $manager->usedMediaIds(collect([$used, $unused]));

        $this->assertArrayHasKey($used->id, $usedIds);
        $this->assertArrayNotHasKey($unused->id, $usedIds);
    }

    public function test_usage_filter_on_index_shows_only_used_or_only_unused_media(): void
    {
        $used = Media::create(['disk_path' => 'library/referenced.jpg', 'original_filename' => 'referenced.jpg', 'mime_type' => 'image/jpeg', 'size' => 1]);
        $unused = Media::create(['disk_path' => 'library/orphaned.jpg', 'original_filename' => 'orphaned.jpg', 'mime_type' => 'image/jpeg', 'size' => 1]);
        HeroSlide::create(['image' => 'library/referenced.jpg', 'title_km' => 'ស្លាយ']);
        $admin = $this->admin();

        $usedOnly = $this->actingAs($admin)->get(route('admin.media.index', ['usage' => 'used']));
        $usedOnly->assertOk()->assertSee('referenced.jpg')->assertDontSee('orphaned.jpg');

        $unusedOnly = $this->actingAs($admin)->get(route('admin.media.index', ['usage' => 'unused']));
        $unusedOnly->assertOk()->assertSee('orphaned.jpg')->assertDontSee('referenced.jpg');
    }

    public function test_type_filter_on_index_only_shows_the_requested_mime_family(): void
    {
        Media::create(['disk_path' => 'library/photo.jpg', 'original_filename' => 'photo.jpg', 'mime_type' => 'image/jpeg', 'size' => 1]);
        Media::create(['disk_path' => 'library/report.pdf', 'original_filename' => 'report.pdf', 'mime_type' => 'application/pdf', 'size' => 1]);

        $response = $this->actingAs($this->admin())->get(route('admin.media.index', ['type' => 'pdf']));

        $response->assertOk()->assertSee('report.pdf')->assertDontSee('photo.jpg');
    }

    // --- Missing file fallback (spec §29) ---

    public function test_media_file_missing_is_true_when_the_physical_file_is_gone(): void
    {
        Storage::fake('public');
        $media = Media::create(['disk_path' => 'library/ghost.jpg', 'original_filename' => 'ghost.jpg', 'mime_type' => 'image/jpeg', 'size' => 1]);

        $this->assertTrue($media->fileMissing());

        Storage::disk('public')->put('library/ghost.jpg', 'x');
        $this->assertFalse($media->fresh()->fileMissing());
    }

    public function test_index_page_shows_a_missing_file_badge_instead_of_a_broken_image(): void
    {
        Storage::fake('public');
        Media::create(['disk_path' => 'library/ghost2.jpg', 'original_filename' => 'ghost2.jpg', 'mime_type' => 'image/jpeg', 'size' => 1]);

        $response = $this->actingAs($this->admin())->get(route('admin.media.index'));

        $response->assertOk()->assertSee('ឯកសារបាត់');
    }

    // --- Permissions (spec §37) ---

    public function test_a_user_without_any_media_permission_is_forbidden_from_the_index(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get(route('admin.media.index'))->assertForbidden();
    }

    public function test_a_user_without_the_delete_permission_cannot_permanently_delete(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $user->givePermissionTo('media.view');
        $media = $this->trashedImage('library/protected.jpg');

        $this->actingAs($user)->delete(route('admin.media.force-delete', $media))->assertForbidden();
        $this->assertDatabaseHas('media', ['id' => $media->id]);
    }

    // --- Path safety (spec §26/§27) ---

    public function test_folder_names_cannot_contain_path_traversal_characters(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.media.folders.store'), [
            'name' => '../../etc',
        ]);

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseMissing('media_folders', ['name' => '../../etc']);
    }

    public function test_a_folder_can_still_be_created_with_a_normal_name(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.media.folders.store'), [
            'name' => 'Homepage 2026',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('media_folders', ['name' => 'Homepage 2026']);
    }
}
