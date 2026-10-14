<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContentMediaFolderTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Super Admin');

        return $admin;
    }

    private function article(array $attributes = []): Content
    {
        return Content::create(array_merge([
            'title_km' => 'អត្ថបទសាកល្បង',
            'slug' => 'content-'.fake()->uuid(),
            'status' => 'draft',
        ], $attributes));
    }

    public function test_creating_content_with_a_featured_image_files_it_into_the_articles_own_folder(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin())->post(route('admin.content.store'), [
            'title_km' => 'អត្ថបទថ្មី',
            'slug' => 'new-article',
            'featured_image' => UploadedFile::fake()->image('cover.jpg'),
        ]);

        $response->assertRedirect();
        $content = Content::where('slug', 'new-article')->firstOrFail();

        $folder = MediaFolder::where('owner_type', Content::class)->where('owner_id', $content->id)->first();
        $this->assertNotNull($folder);
        $this->assertSame("#{$content->id} — អត្ថបទថ្មី", $folder->name);
        $this->assertSame('អត្ថបទ', $folder->parent->name);
    }

    public function test_featured_image_gallery_upload_and_block_image_upload_all_share_one_folder(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $content = $this->article();

        $this->actingAs($admin)->put(route('admin.content.update', $content), [
            'title_km' => $content->title_km,
            'slug' => $content->slug,
            'featured_image' => UploadedFile::fake()->image('featured.jpg'),
        ]);

        $this->actingAs($admin)->post(route('admin.content.images.store', $content), [
            'images' => [UploadedFile::fake()->image('gallery-1.jpg')],
        ]);

        $this->actingAs($admin)->post(route('admin.content.blocks.store', $content), [
            'block_type' => 'image',
            'image_file' => UploadedFile::fake()->image('block-image.jpg'),
        ]);

        $folder = MediaFolder::where('owner_type', Content::class)->where('owner_id', $content->id)->firstOrFail();
        $this->assertSame(3, Media::where('folder_id', $folder->id)->count());
    }

    public function test_gallery_attach_from_media_can_still_reuse_images_from_anywhere_in_the_library(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $content = $this->article();
        $otherFolder = MediaFolder::create(['name' => 'ថតផ្សេង', 'slug' => 'other-folder']);
        $media = Media::create([
            'folder_id' => $otherFolder->id,
            'disk_path' => 'library/unrelated.jpg',
            'original_filename' => 'unrelated.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.content.images.from-media', $content), [
            'media_ids' => [$media->id],
        ]);

        $response->assertRedirect();
        $this->assertSame(1, $content->images()->where('media_id', $media->id)->count());
    }

    public function test_renaming_the_article_renames_its_folder_without_duplicating_it(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $content = $this->article(['title_km' => 'ចំណងជើងចាស់']);

        $this->actingAs($admin)->put(route('admin.content.update', $content), [
            'title_km' => 'ចំណងជើងថ្មី',
            'slug' => $content->slug,
            'featured_image' => UploadedFile::fake()->image('cover.jpg'),
        ]);

        $folders = MediaFolder::where('owner_type', Content::class)->where('owner_id', $content->id)->get();
        $this->assertCount(1, $folders);
        $this->assertSame("#{$content->id} — ចំណងជើងថ្មី", $folders->first()->name);
    }
}
