<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\News;
use App\Models\NewsImage;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NewsMediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Super Admin');

        return $admin;
    }

    private function image(string $name = 'library.jpg'): Media
    {
        return Media::create(['disk_path' => 'library/'.$name, 'original_filename' => $name, 'mime_type' => 'image/jpeg', 'size' => 1]);
    }

    private function news(array $attributes = []): News
    {
        static $n = 0;
        $n++;

        return News::create(array_merge([
            'title_km' => 'ព័ត៌មានសាកល្បង '.$n,
            'slug' => 'news-media-'.$n,
            'status' => 'published',
            'published_at' => now()->subDay(),
        ], $attributes));
    }

    // --- Featured image: create/edit/replace/remove ---

    public function test_news_can_be_created_with_a_featured_image_chosen_from_the_media_library(): void
    {
        $media = $this->image('cover.jpg');

        $this->actingAs($this->admin())->post(route('admin.news.store'), [
            'title_km' => 'ព័ត៌មានជាមួយរូបគម្រប',
            'slug' => 'news-with-library-cover',
            'featured_media_id' => $media->id,
        ])->assertRedirect();

        $news = News::where('slug', 'news-with-library-cover')->firstOrFail();
        $this->assertSame($media->id, $news->featured_media_id);
        $this->assertSame($media->disk_path, $news->featured_image);
    }

    public function test_editing_other_fields_leaves_the_featured_image_unchanged(): void
    {
        $media = $this->image();
        $admin = $this->admin();
        $news = $this->news(['featured_media_id' => $media->id, 'featured_image' => $media->disk_path]);

        $this->actingAs($admin)->put(route('admin.news.update', $news), [
            'title_km' => 'ចំណងជើងថ្មី', 'slug' => $news->slug,
        ])->assertRedirect();

        $this->assertSame($media->id, $news->fresh()->featured_media_id);
    }

    public function test_the_featured_image_can_be_replaced_with_a_different_library_item(): void
    {
        $admin = $this->admin();
        $first = $this->image('first.jpg');
        $second = $this->image('second.jpg');
        $news = $this->news(['featured_media_id' => $first->id, 'featured_image' => $first->disk_path]);

        $this->actingAs($admin)->put(route('admin.news.update', $news), [
            'title_km' => $news->title_km, 'slug' => $news->slug,
            'featured_media_id' => $second->id,
        ])->assertRedirect();

        $this->assertSame($second->id, $news->fresh()->featured_media_id);
    }

    public function test_the_featured_image_can_be_replaced_by_uploading_a_new_file(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $first = $this->image('first.jpg');
        $news = $this->news(['featured_media_id' => $first->id, 'featured_image' => $first->disk_path]);

        $this->actingAs($admin)->put(route('admin.news.update', $news), [
            'title_km' => $news->title_km, 'slug' => $news->slug,
            'featured_image' => UploadedFile::fake()->image('new-cover.jpg'),
        ])->assertRedirect();

        $news->refresh();
        $this->assertNotNull($news->featured_media_id);
        $this->assertNotSame($first->id, $news->featured_media_id);
        $this->assertSame(Media::find($news->featured_media_id)->disk_path, $news->featured_image);
    }

    public function test_the_featured_image_can_be_removed_without_deleting_the_media_record(): void
    {
        $admin = $this->admin();
        $media = $this->image();
        $news = $this->news(['featured_media_id' => $media->id, 'featured_image' => $media->disk_path]);

        $this->actingAs($admin)->put(route('admin.news.update', $news), [
            'title_km' => $news->title_km, 'slug' => $news->slug,
            'remove_featured_image' => '1',
        ])->assertRedirect();

        $news->refresh();
        $this->assertNull($news->featured_media_id);
        $this->assertNull($news->featured_image);
        $this->assertDatabaseHas('media', ['id' => $media->id]);
    }

    public function test_a_non_image_media_is_rejected_as_a_featured_image(): void
    {
        $pdf = Media::create(['disk_path' => 'library/doc.pdf', 'original_filename' => 'doc.pdf', 'mime_type' => 'application/pdf', 'size' => 1]);

        $this->actingAs($this->admin())->post(route('admin.news.store'), [
            'title_km' => 'ព័ត៌មានមិនត្រឹមត្រូវ', 'slug' => 'news-invalid-featured',
            'featured_media_id' => $pdf->id,
        ])->assertSessionHasErrors('featured_media_id');

        $this->assertSame(0, News::where('slug', 'news-invalid-featured')->count());
    }

    public function test_a_missing_featured_media_id_falls_back_to_the_legacy_path_then_the_site_default(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('news/legacy.jpg', 'fake-bytes');

        $withLegacyPath = $this->news(['featured_image' => 'news/legacy.jpg', 'featured_media_id' => null]);
        $this->assertSame(asset('storage/news/legacy.jpg'), $withLegacyPath->featuredImageUrl());

        // A legacy path that resolves under neither the storage disk nor a bundled public/
        // asset must never be built into a broken <img> URL — it falls back to the default too.
        $withUnresolvableLegacyPath = $this->news(['featured_image' => 'news/never-uploaded.jpg', 'featured_media_id' => null]);
        $this->assertSame(asset(config('site.default_news_image')), $withUnresolvableLegacyPath->featuredImageUrl());

        $withNothing = $this->news(['featured_image' => null, 'featured_media_id' => null]);
        $this->assertSame(asset(config('site.default_news_image')), $withNothing->featuredImageUrl());
    }

    public function test_a_soft_deleted_featured_media_falls_back_to_the_default_image(): void
    {
        $media = $this->image();
        $news = $this->news(['featured_media_id' => $media->id, 'featured_image' => $media->disk_path]);
        $media->delete();

        $this->assertSame(asset(config('site.default_news_image')), $news->fresh()->featuredImageUrl());
    }

    public function test_featured_image_alt_text_prefers_the_media_alt_text_then_falls_back_to_the_title(): void
    {
        $media = $this->image();
        $media->update(['alt_text' => 'ការពិពណ៌នារូបភាព']);
        $withAlt = $this->news(['featured_media_id' => $media->id, 'title_km' => 'ចំណងជើង']);
        $this->assertSame('ការពិពណ៌នារូបភាព', $withAlt->featuredImageAlt());

        $withoutAlt = $this->news(['featured_media_id' => null, 'title_km' => 'ចំណងជើងតែឯង']);
        $this->assertSame('ចំណងជើងតែឯង', $withoutAlt->featuredImageAlt());
    }

    // --- Gallery: add from library, reorder ---

    public function test_existing_library_images_can_be_attached_to_the_gallery_without_duplicating_files(): void
    {
        $admin = $this->admin();
        $news = $this->news();
        $media = $this->image();

        $this->actingAs($admin)->post(route('admin.news.images.from-media', $news), [
            'media_ids' => [$media->id],
        ])->assertRedirect();

        $this->assertCount(1, $news->fresh()->images);
        $this->assertSame($media->id, $news->images()->first()->media_id);
        $this->assertSame(1, Media::where('disk_path', $media->disk_path)->count());
    }

    public function test_attaching_the_same_library_image_twice_does_not_duplicate_the_gallery_row(): void
    {
        $admin = $this->admin();
        $news = $this->news();
        $media = $this->image();

        $this->actingAs($admin)->post(route('admin.news.images.from-media', $news), ['media_ids' => [$media->id]]);
        $this->actingAs($admin)->post(route('admin.news.images.from-media', $news), ['media_ids' => [$media->id]]);

        $this->assertCount(1, $news->fresh()->images);
    }

    public function test_a_non_image_media_is_skipped_when_attaching_gallery_images(): void
    {
        $admin = $this->admin();
        $news = $this->news();
        $pdf = Media::create(['disk_path' => 'library/doc.pdf', 'original_filename' => 'doc.pdf', 'mime_type' => 'application/pdf', 'size' => 1]);

        $this->actingAs($admin)->post(route('admin.news.images.from-media', $news), ['media_ids' => [$pdf->id]])->assertRedirect();

        $this->assertCount(0, $news->fresh()->images);
    }

    public function test_gallery_media_ids_submitted_through_the_main_form_are_attached_and_deduplicated(): void
    {
        $admin = $this->admin();
        $media = $this->image();

        $this->actingAs($admin)->post(route('admin.news.store'), [
            'title_km' => 'ព័ត៌មានវិចិត្រសាល', 'slug' => 'news-gallery-from-form',
            'gallery_media_ids' => [$media->id, $media->id],
        ])->assertRedirect();

        $news = News::where('slug', 'news-gallery-from-form')->firstOrFail();
        $this->assertCount(1, $news->images);
        $this->assertSame($media->id, $news->images->first()->media_id);
    }

    public function test_gallery_can_be_reordered_and_persists(): void
    {
        $admin = $this->admin();
        $news = $this->news();
        $a = NewsImage::create(['news_id' => $news->id, 'media_id' => $this->image('a.jpg')->id, 'image' => 'library/a.jpg', 'sort_order' => 0]);
        $b = NewsImage::create(['news_id' => $news->id, 'media_id' => $this->image('b.jpg')->id, 'image' => 'library/b.jpg', 'sort_order' => 1]);

        $this->actingAs($admin)->postJson(route('admin.news.images.reorder', $news), [
            'items' => [
                ['id' => $b->id, 'sort_order' => 0],
                ['id' => $a->id, 'sort_order' => 1],
            ],
        ])->assertOk();

        $ordered = $news->fresh()->images;
        $this->assertSame($b->id, $ordered->first()->id);
        $this->assertSame($a->id, $ordered->last()->id);
    }

    public function test_reorder_cannot_touch_another_articles_gallery_image(): void
    {
        $admin = $this->admin();
        $newsOne = $this->news();
        $newsTwo = $this->news();
        $image = NewsImage::create(['news_id' => $newsOne->id, 'media_id' => $this->image()->id, 'image' => 'library/x.jpg', 'sort_order' => 0]);

        $this->actingAs($admin)->postJson(route('admin.news.images.reorder', $newsTwo), [
            'items' => [['id' => $image->id, 'sort_order' => 5]],
        ])->assertOk();

        $this->assertSame(0, $image->fresh()->sort_order);
    }

    // --- URL resolution / fallback / legacy compatibility ---

    public function test_gallery_image_url_prefers_media_then_legacy_path_then_default(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('news/gallery/legacy.jpg', 'fake-bytes');

        $media = $this->image('gallery.jpg');
        $viaMedia = NewsImage::create(['news_id' => $this->news()->id, 'media_id' => $media->id, 'image' => $media->disk_path]);
        $this->assertSame($media->url, $viaMedia->url());

        // Legacy row: predates media_id, only the raw disk path is known.
        $legacyOnly = NewsImage::create(['news_id' => $this->news()->id, 'media_id' => null, 'image' => 'news/gallery/legacy.jpg']);
        $this->assertSame(asset('storage/news/gallery/legacy.jpg'), $legacyOnly->url());

        // media_id was set but that Media is now gone: counts as missing, never falls back to the
        // (now-orphaned) legacy path that only ever mirrored it — goes straight to the site default.
        $orphaned = NewsImage::create(['news_id' => $this->news()->id, 'media_id' => $media->id, 'image' => $media->disk_path]);
        $media->delete();
        $this->assertSame(asset(config('site.default_news_image')), $orphaned->fresh()->url());
    }

    // --- Public pages actually use the resolved Media URL ---

    public function test_public_news_index_and_show_pages_render_the_media_library_url(): void
    {
        $media = $this->image('public-cover.jpg');
        $news = $this->news(['featured_media_id' => $media->id, 'featured_image' => $media->disk_path]);

        $this->get(route('news.index'))->assertOk()->assertSee($media->url, false);
        $this->get(route('news.show', $news))->assertOk()->assertSee($media->url, false);
    }

    public function test_public_news_page_never_shows_a_bare_disk_path_as_the_image_source(): void
    {
        // The historical bug: featured_image (a disk path like "news/xyz.jpg") used directly as
        // the <img src>, producing a broken image because it is missing the "storage/" prefix.
        Storage::fake('public');
        Storage::disk('public')->put('news/legacy-without-prefix.jpg', 'fake-bytes');

        $news = $this->news(['featured_image' => 'news/legacy-without-prefix.jpg', 'featured_media_id' => null]);

        $html = $this->get(route('news.show', $news))->assertOk()->getContent();

        $this->assertStringContainsString('/storage/news/legacy-without-prefix.jpg', $html);
        $this->assertStringNotContainsString('src="news/legacy-without-prefix.jpg"', $html);
        $this->assertStringNotContainsString('src="http://localhost/news/legacy-without-prefix.jpg"', $html);
    }
}
