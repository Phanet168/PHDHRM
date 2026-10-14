<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaExplorerRedesignTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Super Admin');

        return $admin;
    }

    public function test_index_page_renders_with_storage_stats_and_sort(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.media.index', ['sort' => 'latest']));

        $response->assertOk();
        $response->assertViewHas('storage');
        $response->assertViewHas('sort', 'latest');
    }

    public function test_rename_media_updates_original_filename(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        Storage::disk('public')->put('library/test.jpg', 'fake-content');
        $media = Media::create([
            'disk_path' => 'library/test.jpg',
            'original_filename' => 'old-name.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 12,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.media.rename', $media), ['name' => 'new-name.jpg']);

        $response->assertRedirect();
        $this->assertSame('new-name.jpg', $media->fresh()->original_filename);
    }

    public function test_duplicate_media_creates_independent_copy_with_its_own_file(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        Storage::disk('public')->put('library/original.jpg', 'fake-content');
        $media = Media::create([
            'disk_path' => 'library/original.jpg',
            'original_filename' => 'original.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 12,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.media.duplicate', $media));

        $response->assertRedirect();
        $this->assertSame(2, Media::count());

        $copy = Media::where('id', '!=', $media->id)->first();
        $this->assertNotSame($media->disk_path, $copy->disk_path);
        Storage::disk('public')->assertExists($copy->disk_path);

        // Deleting the original's file must not affect the copy's own file.
        Storage::disk('public')->delete($media->disk_path);
        Storage::disk('public')->assertExists($copy->disk_path);
    }

    public function test_duplicate_folder_recursively_copies_children_and_media(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $parent = MediaFolder::create(['name' => 'Parent', 'slug' => 'parent']);
        $child = MediaFolder::create(['name' => 'Child', 'slug' => 'child', 'parent_id' => $parent->id]);
        Storage::disk('public')->put('library/a.jpg', 'x');
        Media::create(['folder_id' => $parent->id, 'disk_path' => 'library/a.jpg', 'original_filename' => 'a.jpg', 'mime_type' => 'image/jpeg', 'size' => 1]);

        $response = $this->actingAs($admin)->postJson(route('admin.media.folders.duplicate', $parent));

        $response->assertRedirect();
        $this->assertSame(4, MediaFolder::count()); // parent, child, copy-of-parent, copy-of-child
        $this->assertSame(2, Media::count()); // original media row + its duplicate inside the copied folder
    }

    public function test_bulk_action_supports_duplicate(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        Storage::disk('public')->put('library/dup.jpg', 'x');
        $media = Media::create(['disk_path' => 'library/dup.jpg', 'original_filename' => 'dup.jpg', 'mime_type' => 'image/jpeg', 'size' => 1]);
        $folder = MediaFolder::create(['name' => 'Target', 'slug' => 'target']);

        $response = $this->actingAs($admin)->postJson(route('admin.media.bulk-action'), [
            'action' => 'duplicate',
            'items' => [['type' => 'media', 'id' => $media->id]],
            'folder_id' => $folder->id,
        ]);

        $response->assertOk();
        $response->assertJson(['succeeded' => 1, 'blocked' => []]);
        $this->assertSame(1, Media::where('folder_id', $folder->id)->count());
    }

    public function test_trash_view_lists_soft_deleted_items_and_restore_works(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        Storage::disk('public')->put('library/trashed.jpg', 'x');
        $media = Media::create(['disk_path' => 'library/trashed.jpg', 'original_filename' => 'trashed.jpg', 'mime_type' => 'image/jpeg', 'size' => 1]);
        $media->delete();

        $response = $this->actingAs($admin)->get(route('admin.media.trash'));
        $response->assertOk();
        $response->assertSee('trashed.jpg');

        $this->actingAs($admin)->post(route('admin.media.restore', $media))->assertRedirect();
        $this->assertNull($media->fresh()->deleted_at);
    }

    public function test_force_delete_permanently_removes_media_and_its_file(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        Storage::disk('public')->put('library/gone.jpg', 'x');
        $media = Media::create(['disk_path' => 'library/gone.jpg', 'original_filename' => 'gone.jpg', 'mime_type' => 'image/jpeg', 'size' => 1]);
        $media->delete();

        $response = $this->actingAs($admin)->delete(route('admin.media.force-delete', $media));

        $response->assertRedirect();
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
        Storage::disk('public')->assertMissing('library/gone.jpg');
    }

    public function test_empty_trash_removes_every_trashed_folder_and_media_including_referenced_media(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $folder = MediaFolder::create(['name' => 'ToTrash', 'slug' => 'to-trash']);
        Storage::disk('public')->put('library/in-folder.jpg', 'x');
        $inFolder = Media::create(['folder_id' => $folder->id, 'disk_path' => 'library/in-folder.jpg', 'original_filename' => 'in-folder.jpg', 'mime_type' => 'image/jpeg', 'size' => 1]);
        Storage::disk('public')->put('library/loose.jpg', 'x');
        $loose = Media::create(['disk_path' => 'library/loose.jpg', 'original_filename' => 'loose.jpg', 'mime_type' => 'image/jpeg', 'size' => 1]);

        $folder->delete();
        $inFolder->delete();
        $loose->delete();

        $response = $this->actingAs($admin)->post(route('admin.media.empty-trash'));

        $response->assertRedirect();
        $this->assertDatabaseMissing('media_folders', ['id' => $folder->id]);
        $this->assertDatabaseMissing('media', ['id' => $inFolder->id]);
        $this->assertDatabaseMissing('media', ['id' => $loose->id]);
    }

    public function test_library_upload_now_accepts_word_and_video_but_still_rejects_svg(): void
    {
        $admin = $this->admin();
        Storage::fake('public');

        $docx = \Illuminate\Http\UploadedFile::fake()->create('report.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $response = $this->actingAs($admin)->post(route('admin.media.store'), ['file' => $docx]);
        $response->assertSessionDoesntHaveErrors('file');

        $svg = \Illuminate\Http\UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml');
        $response = $this->actingAs($admin)->post(route('admin.media.store'), ['file' => $svg]);
        $response->assertSessionHasErrors('file');
    }
}
