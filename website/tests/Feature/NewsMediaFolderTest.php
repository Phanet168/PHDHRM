<?php

namespace Tests\Feature;

use App\Models\MediaFolder;
use App\Models\News;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NewsMediaFolderTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Super Admin');

        return $admin;
    }

    public function test_creating_news_with_a_featured_image_files_it_into_the_articles_own_folder(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin())->post(route('admin.news.store'), [
            'title_km' => 'ព័ត៌មានសាកល្បង',
            'slug' => 'test-article',
            'featured_image' => UploadedFile::fake()->image('cover.jpg'),
        ]);

        $response->assertRedirect();
        $news = News::where('slug', 'test-article')->firstOrFail();
        $this->assertNotEmpty($news->featured_image);

        $folder = MediaFolder::where('owner_type', News::class)->where('owner_id', $news->id)->first();
        $this->assertNotNull($folder);
        $this->assertSame("#{$news->id} — ព័ត៌មានសាកល្បង", $folder->name);
        $this->assertSame('ព័ត៌មាន និងសកម្មភាព', $folder->parent->name);
    }

    public function test_creating_news_with_multiple_gallery_images_attaches_them_to_the_article(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin())->post(route('admin.news.store'), [
            'title_km' => 'ព័ត៌មានមានរូបច្រើន',
            'slug' => 'article-with-gallery',
            'images' => [
                UploadedFile::fake()->image('activity-one.jpg'),
                UploadedFile::fake()->image('activity-two.jpg'),
            ],
        ]);

        $response->assertRedirect();
        $news = News::where('slug', 'article-with-gallery')->firstOrFail();
        $this->assertCount(2, $news->images);

        foreach ($news->images as $image) {
            Storage::disk('public')->assertExists($image->image);
            // Every upload is now also a Media Library record, and that record is the source of truth for the URL.
            $this->assertNotNull($image->media_id);
            $this->assertSame($image->media->url, $image->url());
        }
    }

    public function test_gallery_upload_has_no_cap_on_the_number_of_images(): void
    {
        Storage::fake('public');
        $images = [];
        for ($index = 1; $index <= 15; $index++) {
            $images[] = UploadedFile::fake()->image("activity-{$index}.jpg");
        }

        $response = $this->actingAs($this->admin())->post(route('admin.news.store'), [
            'title_km' => 'ព័ត៌មានរូបច្រើនណាស់',
            'slug' => 'article-many-images',
            'featured_image' => UploadedFile::fake()->image('cover.jpg'),
            'images' => $images,
        ]);

        $response->assertSessionDoesntHaveErrors('images');
        $news = News::where('slug', 'article-many-images')->firstOrFail();
        $this->assertCount(15, $news->images);
    }

    public function test_gallery_keeps_growing_across_repeated_saves(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.news.store'), [
            'title_km' => 'ព័ត៌មានបន្ថែមរូបជាបណ្តើរ',
            'slug' => 'article-incremental-gallery',
        ]);
        $news = News::where('slug', 'article-incremental-gallery')->firstOrFail();

        for ($batch = 1; $batch <= 3; $batch++) {
            $this->actingAs($admin)->put(route('admin.news.update', $news), [
                'title_km' => 'ព័ត៌មានបន្ថែមរូបជាបណ្តើរ',
                'slug' => 'article-incremental-gallery',
                'images' => [
                    UploadedFile::fake()->image("batch-{$batch}-a.jpg"),
                    UploadedFile::fake()->image("batch-{$batch}-b.jpg"),
                ],
            ])->assertRedirect();
        }

        $this->assertCount(6, $news->fresh()->images);
    }

    public function test_updating_news_appends_multiple_gallery_images(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.news.store'), [
            'title_km' => 'ព័ត៌មានសម្រាប់កែប្រែរូប',
            'slug' => 'article-gallery-update',
        ]);
        $news = News::where('slug', 'article-gallery-update')->firstOrFail();

        $response = $this->actingAs($admin)->put(route('admin.news.update', $news), [
            'title_km' => 'ព័ត៌មានសម្រាប់កែប្រែរូប',
            'slug' => 'article-gallery-update',
            'images' => [
                UploadedFile::fake()->image('update-one.jpg'),
                UploadedFile::fake()->image('update-two.jpg'),
            ],
        ]);

        $response->assertRedirect();
        $this->assertCount(2, $news->fresh()->images);
    }

    public function test_removing_a_gallery_image_only_detaches_it_never_deletes_the_file_or_media_record(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.news.store'), [
            'title_km' => 'ព័ត៌មានសម្រាប់លុបរូប',
            'slug' => 'article-delete-image',
            'images' => [
                UploadedFile::fake()->image('keep.jpg'),
                UploadedFile::fake()->image('remove.jpg'),
            ],
        ]);
        $news = News::where('slug', 'article-delete-image')->firstOrFail();
        $this->assertCount(2, $news->images);
        $toDelete = $news->images()->latest('id')->first();
        $keptPath = $news->images()->where('id', '!=', $toDelete->id)->first()->image;
        $deletedPath = $toDelete->image;
        $deletedMediaId = $toDelete->media_id;
        $this->assertNotNull($deletedMediaId, 'the seed upload should already be linked to a Media record');

        $response = $this->actingAs($admin)->delete(route('admin.news.images.destroy', [$news, $toDelete]));

        $response->assertRedirect();
        $this->assertCount(1, $news->fresh()->images);
        $this->assertDatabaseMissing('news_images', ['id' => $toDelete->id]);
        // The News-gallery row is gone, but the underlying file and its Media Library record survive.
        Storage::disk('public')->assertExists($deletedPath);
        Storage::disk('public')->assertExists($keptPath);
        $this->assertDatabaseHas('media', ['id' => $deletedMediaId]);
    }

    public function test_deleting_a_gallery_image_from_a_different_article_is_rejected(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.news.store'), [
            'title_km' => 'អត្ថបទទីមួយ', 'slug' => 'article-one',
            'images' => [UploadedFile::fake()->image('a.jpg')],
        ]);
        $this->actingAs($admin)->post(route('admin.news.store'), [
            'title_km' => 'អត្ថបទទីពីរ', 'slug' => 'article-two',
        ]);
        $articleOne = News::where('slug', 'article-one')->firstOrFail();
        $articleTwo = News::where('slug', 'article-two')->firstOrFail();
        $image = $articleOne->images->first();

        $this->actingAs($admin)->delete(route('admin.news.images.destroy', [$articleTwo, $image]))->assertNotFound();
        $this->assertDatabaseHas('news_images', ['id' => $image->id]);
    }

    public function test_deleting_a_gallery_image_requires_permission(): void
    {
        Storage::fake('public');
        $this->seed(RolePermissionSeeder::class);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.news.store'), [
            'title_km' => 'អត្ថបទការពារសិទ្ធិ', 'slug' => 'article-permission',
            'images' => [UploadedFile::fake()->image('a.jpg')],
        ]);
        $news = News::where('slug', 'article-permission')->firstOrFail();
        $image = $news->images->first();

        $reviewer = User::factory()->create(['is_active' => true]);
        $reviewer->assignRole('Reviewer');

        $this->actingAs($reviewer)->delete(route('admin.news.images.destroy', [$news, $image]))->assertForbidden();
        $this->assertDatabaseHas('news_images', ['id' => $image->id]);
    }

    public function test_updating_news_saves_the_publication_date(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.news.store'), [
            'title_km' => 'ព័ត៌មានកាលបរិច្ឆេទ',
            'slug' => 'article-publication-date',
        ]);
        $news = News::where('slug', 'article-publication-date')->firstOrFail();

        $response = $this->actingAs($admin)->put(route('admin.news.update', $news), [
            'title_km' => 'ព័ត៌មានកាលបរិច្ឆេទ',
            'slug' => 'article-publication-date',
            'published_at' => '2026-09-25',
        ]);

        $response->assertRedirect();
        $this->assertSame('2026-09-25', $news->fresh()->published_at->toDateString());
    }

    public function test_replacing_the_featured_image_on_update_reuses_the_same_article_folder(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.news.store'), [
            'title_km' => 'ព័ត៌មានទីមួយ',
            'slug' => 'first-article',
            'featured_image' => UploadedFile::fake()->image('first.jpg'),
        ]);
        $news = News::where('slug', 'first-article')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.news.update', $news), [
            'title_km' => 'ព័ត៌មានទីមួយ',
            'slug' => 'first-article',
            'featured_image' => UploadedFile::fake()->image('second.jpg'),
        ]);

        $this->assertSame(
            1,
            MediaFolder::where('owner_type', News::class)->where('owner_id', $news->id)->count()
        );
    }

    public function test_renaming_the_article_on_update_renames_its_folder_without_duplicating_it(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.news.store'), [
            'title_km' => 'ចំណងជើងចាស់',
            'slug' => 'renamed-article',
            'featured_image' => UploadedFile::fake()->image('cover.jpg'),
        ]);
        $news = News::where('slug', 'renamed-article')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.news.update', $news), [
            'title_km' => 'ចំណងជើងថ្មី',
            'slug' => 'renamed-article',
            'featured_image' => UploadedFile::fake()->image('new-cover.jpg'),
        ]);

        $folders = MediaFolder::where('owner_type', News::class)->where('owner_id', $news->id)->get();
        $this->assertCount(1, $folders);
        $this->assertSame("#{$news->id} — ចំណងជើងថ្មី", $folders->first()->name);
    }
}
