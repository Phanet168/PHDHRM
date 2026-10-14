<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\News;
use App\Services\MediaManagerService;
use App\Services\MediaUploader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaAutoFolderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_news_upload_is_filed_into_its_collection_folder(): void
    {
        Storage::fake('public');

        $path = MediaUploader::store(UploadedFile::fake()->image('photo.jpg'), 'news');

        $media = Media::where('disk_path', $path)->first();
        $this->assertNotNull($media->folder_id);
        $this->assertSame('ព័ត៌មាន និងសកម្មភាព', $media->folder->name);
    }

    public function test_nested_facility_collection_creates_parent_and_child_folder(): void
    {
        Storage::fake('public');

        $path = MediaUploader::store(UploadedFile::fake()->image('logo.png'), 'facilities/logos');

        $media = Media::where('disk_path', $path)->first();
        $folder = $media->folder;
        $this->assertSame('ស្លាកសញ្ញា', $folder->name);
        $this->assertSame('មូលដ្ឋានសុខាភិបាល', $folder->parent->name);
    }

    public function test_repeated_uploads_reuse_the_same_folder_instead_of_creating_duplicates(): void
    {
        Storage::fake('public');

        MediaUploader::store(UploadedFile::fake()->image('a.jpg'), 'news');
        MediaUploader::store(UploadedFile::fake()->image('b.jpg'), 'news');

        $this->assertSame(1, MediaFolder::where('name', 'ព័ត៌មាន និងសកម្មភាព')->count());
    }

    public function test_same_named_uploads_in_the_same_folder_get_disambiguated_display_names(): void
    {
        Storage::fake('public');

        $path1 = MediaUploader::store(UploadedFile::fake()->image('stock-1.jpg'), 'content/gallery');
        $path2 = MediaUploader::store(UploadedFile::fake()->image('stock-1.jpg'), 'content/gallery');
        $path3 = MediaUploader::store(UploadedFile::fake()->image('stock-1.jpg'), 'content/gallery');

        $names = Media::whereIn('disk_path', [$path1, $path2, $path3])->orderBy('id')->pluck('original_filename');

        $this->assertSame(['stock-1.jpg', 'stock-1 (2).jpg', 'stock-1 (3).jpg'], $names->all());
    }

    public function test_content_gallery_uploads_reuse_the_existing_folder_by_name(): void
    {
        Storage::fake('public');
        $existing = MediaFolder::create(['name' => 'វិចិត្រសាលអត្ថបទ', 'slug' => 'existing-gallery']);

        $path = MediaUploader::store(UploadedFile::fake()->image('gallery.jpg'), 'content/gallery');

        $media = Media::where('disk_path', $path)->first();
        $this->assertSame($existing->id, $media->folder_id);
        $this->assertSame(1, MediaFolder::where('name', 'វិចិត្រសាលអត្ថបទ')->count());
    }

    public function test_library_direct_uploads_are_not_auto_foldered_and_respect_explicit_choice(): void
    {
        Storage::fake('public');

        $path = MediaUploader::store(
            UploadedFile::fake()->image('direct.jpg'),
            'library',
            MediaUploader::LIBRARY_EXTENSIONS,
            MediaUploader::LIBRARY_MIMES
        );

        $media = Media::where('disk_path', $path)->first();
        $this->assertNull($media->folder_id);
    }

    private function article(array $attributes = []): News
    {
        return News::create(array_merge([
            'title_km' => 'ព័ត៌មានសុខភាព',
            'slug' => 'news-'.fake()->uuid(),
            'excerpt' => 'សកម្មភាពសុខភាពសហគមន៍',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ], $attributes));
    }

    public function test_resolve_folder_for_record_creates_a_dedicated_folder_nested_under_the_collection_container(): void
    {
        $news = $this->article();
        $manager = app(MediaManagerService::class);

        $folderId = $manager->resolveFolderForRecord('news', $news, "#{$news->id} — {$news->title_km}");
        $folder = MediaFolder::find($folderId);

        $this->assertSame("#{$news->id} — {$news->title_km}", $folder->name);
        $this->assertSame('ព័ត៌មាន និងសកម្មភាព', $folder->parent->name);
        $this->assertSame(News::class, $folder->owner_type);
        $this->assertSame($news->id, $folder->owner_id);
    }

    public function test_resolve_folder_for_record_reuses_the_same_folder_on_repeated_calls(): void
    {
        $news = $this->article();
        $manager = app(MediaManagerService::class);

        $firstId = $manager->resolveFolderForRecord('news', $news, "#{$news->id} — {$news->title_km}");
        $secondId = $manager->resolveFolderForRecord('news', $news, "#{$news->id} — {$news->title_km}");

        $this->assertSame($firstId, $secondId);
        $this->assertSame(1, MediaFolder::where('owner_type', News::class)->where('owner_id', $news->id)->count());
    }

    public function test_resolve_folder_for_record_renames_the_folder_when_the_label_changes_without_creating_a_duplicate(): void
    {
        $news = $this->article();
        $manager = app(MediaManagerService::class);

        $folderId = $manager->resolveFolderForRecord('news', $news, "#{$news->id} — ចំណងជើងចាស់");
        $renamedId = $manager->resolveFolderForRecord('news', $news, "#{$news->id} — ចំណងជើងថ្មី");

        $this->assertSame($folderId, $renamedId);
        $this->assertSame("#{$news->id} — ចំណងជើងថ្មី", MediaFolder::find($folderId)->name);
        $this->assertSame(1, MediaFolder::where('owner_type', News::class)->where('owner_id', $news->id)->count());
    }

    public function test_two_different_records_of_the_same_type_get_distinct_folders(): void
    {
        $news1 = $this->article();
        $news2 = $this->article();
        $manager = app(MediaManagerService::class);

        $folderId1 = $manager->resolveFolderForRecord('news', $news1, "#{$news1->id} — {$news1->title_km}");
        $folderId2 = $manager->resolveFolderForRecord('news', $news2, "#{$news2->id} — {$news2->title_km}");

        $this->assertNotSame($folderId1, $folderId2);
    }

    public function test_media_uploader_store_accepts_an_explicit_folder_id_bypassing_the_collection_folder(): void
    {
        Storage::fake('public');
        $folder = MediaFolder::create(['name' => 'ថតជាក់លាក់', 'slug' => 'specific-folder']);

        $path = MediaUploader::store(UploadedFile::fake()->image('photo.jpg'), 'news', folderId: $folder->id);

        $media = Media::where('disk_path', $path)->first();
        $this->assertSame($folder->id, $media->folder_id);
    }
}
