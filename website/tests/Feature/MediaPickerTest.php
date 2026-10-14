<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaPickerTest extends TestCase
{
    use RefreshDatabase;

    public function test_picker_includes_an_items_full_ancestor_chain_so_selecting_a_parent_folder_reveals_sub_folder_images(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('library/nested.jpg', 'x');

        $parent = MediaFolder::create(['name' => 'សុខភាព', 'slug' => 'health']);
        $child = MediaFolder::create(['name' => 'វ៉ាក់សាំង', 'slug' => 'vaccines', 'parent_id' => $parent->id]);
        $media = Media::create([
            'folder_id' => $child->id,
            'disk_path' => 'library/nested.jpg',
            'original_filename' => 'nested.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1,
        ]);

        $admin = User::factory()->create(['is_active' => true]);
        $this->seed(RolePermissionSeeder::class);
        $admin->assignRole('Super Admin');
        $this->actingAs($admin);

        $html = Blade::render(
            "@include('admin.pages._media-picker', ['name' => 'media_id', 'selected' => null])"
        );

        // Both the child folder (the item's own folder) and the parent folder
        // must appear in the embedded ancestor chain, so selecting EITHER one
        // in the dropdown reveals this image. @js() renders the array as a
        // JSON.parse('...') call with unicode-escaped quotes around each id.
        $this->assertStringContainsString("[\\u0022{$child->id}\\u0022,\\u0022{$parent->id}\\u0022]", $html);
        $this->assertStringContainsString($media->url, $html);
    }

    public function test_default_folder_id_preselects_that_folder_in_the_picker(): void
    {
        $html = Blade::render(
            "@include('admin.pages._media-picker', ['name' => 'media_id', 'selected' => null, 'defaultFolderId' => 7])"
        );

        $this->assertStringContainsString("x-data=\"{ folder: '7' }\"", $html);
    }

    public function test_omitting_default_folder_id_keeps_the_previous_all_folders_default(): void
    {
        $html = Blade::render(
            "@include('admin.pages._media-picker', ['name' => 'media_id', 'selected' => null])"
        );

        $this->assertStringContainsString("x-data=\"{ folder: '' }\"", $html);
    }
}
