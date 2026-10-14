<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\Page;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PageMediaFolderTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Super Admin');

        return $admin;
    }

    private function page(array $attributes = []): Page
    {
        return Page::create(array_merge([
            'title_km' => 'ទំព័រសាកល្បង',
            'slug' => 'page-'.fake()->uuid(),
            'template' => 'default',
            'status' => 'draft',
        ], $attributes));
    }

    public function test_uploading_a_featured_image_files_it_into_the_pages_own_folder(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $page = $this->page();

        $this->actingAs($admin)->put(route('admin.pages.update', $page), [
            'title_km' => $page->title_km,
            'slug' => $page->slug,
            'template' => 'default',
            'featured_image' => UploadedFile::fake()->image('cover.jpg'),
        ]);

        $folder = MediaFolder::where('owner_type', Page::class)->where('owner_id', $page->id)->first();
        $this->assertNotNull($folder);
        $this->assertSame("#{$page->id} — ទំព័រសាកល្បង", $folder->name);
        $this->assertSame('ទំព័រ', $folder->parent->name);
    }

    public function test_featured_image_and_block_image_upload_share_the_same_page_folder(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $page = $this->page();

        $this->actingAs($admin)->put(route('admin.pages.update', $page), [
            'title_km' => $page->title_km,
            'slug' => $page->slug,
            'template' => 'default',
            'featured_image' => UploadedFile::fake()->image('featured.jpg'),
        ]);

        $this->actingAs($admin)->post(route('admin.pages.blocks.store', $page), [
            'block_type' => 'image',
            'data' => [],
            'image_file' => UploadedFile::fake()->image('block.jpg'),
        ]);

        $folder = MediaFolder::where('owner_type', Page::class)->where('owner_id', $page->id)->firstOrFail();
        $this->assertSame(2, Media::where('folder_id', $folder->id)->count());
    }

    public function test_opening_the_edit_page_preselects_the_pages_own_folder_in_the_featured_image_picker(): void
    {
        $page = $this->page();

        $response = $this->actingAs($this->admin())->get(route('admin.pages.edit', $page));

        $response->assertOk();
        $folder = MediaFolder::where('owner_type', Page::class)->where('owner_id', $page->id)->firstOrFail();
        $response->assertSee("x-data=\"{ folder: '{$folder->id}' }\"", false);
    }
}
