<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaExplorerBulkActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_move_and_bulk_delete_and_single_move_item(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Super Admin');

        $folderA = MediaFolder::create(['name' => 'Explorer Test A', 'slug' => 'explorer-test-a']);
        $folderB = MediaFolder::create(['name' => 'Explorer Test B', 'slug' => 'explorer-test-b']);

        $media1 = Media::create([
            'folder_id' => $folderA->id,
            'disk_path' => 'library/explorer-test-1.jpg',
            'original_filename' => 'explorer-test-1.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1234,
        ]);
        $media2 = Media::create([
            'folder_id' => $folderA->id,
            'disk_path' => 'library/explorer-test-2.jpg',
            'original_filename' => 'explorer-test-2.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1234,
        ]);

        // Bulk move: media1 + folderA itself moved under folderB's parent (root),
        // and media2 moved into folderB.
        $response = $this->actingAs($admin)->postJson(route('admin.media.bulk-action'), [
            'action' => 'move',
            'items' => [
                ['type' => 'media', 'id' => $media1->id],
                ['type' => 'media', 'id' => $media2->id],
            ],
            'folder_id' => $folderB->id,
        ]);
        $response->assertOk();
        $response->assertJson(['succeeded' => 2, 'blocked' => []]);
        $this->assertSame($folderB->id, $media1->fresh()->folder_id);
        $this->assertSame($folderB->id, $media2->fresh()->folder_id);

        // Single-item drag-and-drop style move back to root.
        $response = $this->actingAs($admin)->postJson(route('admin.media.move-item'), [
            'type' => 'media',
            'id' => $media1->id,
            'folder_id' => null,
        ]);
        $response->assertOk();
        $this->assertNull($media1->fresh()->folder_id);

        // Circular folder move must be blocked via bulk-action (folderB into itself).
        $response = $this->actingAs($admin)->postJson(route('admin.media.bulk-action'), [
            'action' => 'move',
            'items' => [
                ['type' => 'folder', 'id' => $folderB->id],
            ],
            'folder_id' => $folderB->id,
        ]);
        $response->assertOk();
        $response->assertJson(['succeeded' => 0]);
        $this->assertCount(1, $response->json('blocked'));

        // Bulk delete (soft delete) of media2.
        $response = $this->actingAs($admin)->postJson(route('admin.media.bulk-action'), [
            'action' => 'delete',
            'items' => [
                ['type' => 'media', 'id' => $media2->id],
            ],
        ]);
        $response->assertOk();
        $response->assertJson(['succeeded' => 1, 'blocked' => []]);
        $this->assertSoftDeleted('media', ['id' => $media2->id]);

        // A user without media.move permission must be forbidden.
        $unprivileged = User::factory()->create(['is_active' => true]);
        $response = $this->actingAs($unprivileged)->postJson(route('admin.media.move-item'), [
            'type' => 'media',
            'id' => $media1->id,
            'folder_id' => $folderA->id,
        ]);
        $response->assertForbidden();
    }
}
