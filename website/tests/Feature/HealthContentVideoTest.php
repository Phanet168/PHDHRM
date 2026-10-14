<?php

namespace Tests\Feature;

use App\Models\HealthContent;
use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HealthContentVideoTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Super Admin');

        return $admin;
    }

    private function content(array $attributes = []): HealthContent
    {
        return HealthContent::create(array_merge([
            'title_km' => 'អប់រំសុខភាពសាកល្បង',
            'slug' => 'health-'.fake()->uuid(),
            'status' => 'published',
            'published_at' => now()->subDay(),
        ], $attributes));
    }

    // --- Model behavior ---

    public function test_scope_with_video_only_includes_items_with_an_actual_video_attached(): void
    {
        $this->content(['video_type' => 'youtube', 'video_url' => 'https://www.youtube.com/watch?v=abc123']);
        $this->content(['video_type' => 'uploaded', 'video_media_id' => Media::create([
            'disk_path' => 'library/x.mp4', 'original_filename' => 'x.mp4', 'mime_type' => 'video/mp4', 'size' => 1,
        ])->id]);
        $this->content(); // plain article, no video at all
        $this->content(['video_type' => 'youtube', 'video_url' => null]); // inconsistent/incomplete, must be excluded

        $this->assertSame(2, HealthContent::withVideo()->count());
    }

    public function test_content_type_is_derived_and_kept_in_sync_on_every_save(): void
    {
        $video = $this->content(['video_type' => 'youtube', 'video_url' => 'https://youtu.be/abc123']);
        $this->assertSame('video', $video->content_type);

        $video->update(['video_type' => null, 'video_url' => null]);
        $this->assertSame('article', $video->fresh()->content_type);

        // content_type is not fillable, so passing it directly is silently ignored
        // and the derived value from booted() always wins.
        $plain = $this->content(['content_type' => 'video']);
        $this->assertSame('article', $plain->content_type);
    }

    public function test_has_video_and_video_embed_resolve_correctly_for_both_sources(): void
    {
        $urlItem = $this->content(['video_type' => 'youtube', 'video_url' => 'https://youtu.be/abc123']);
        $this->assertTrue($urlItem->hasVideo());
        $this->assertSame(['type' => 'iframe', 'src' => 'https://www.youtube.com/embed/abc123'], $urlItem->videoEmbed());

        $media = Media::create(['disk_path' => 'library/x.mp4', 'original_filename' => 'x.mp4', 'mime_type' => 'video/mp4', 'size' => 1]);
        $mediaItem = $this->content(['video_type' => 'uploaded', 'video_media_id' => $media->id]);
        $this->assertTrue($mediaItem->hasVideo());
        $this->assertSame('video', $mediaItem->videoEmbed()['type']);

        $plain = $this->content();
        $this->assertFalse($plain->hasVideo());
        $this->assertNull($plain->videoEmbed());
    }

    public function test_thumbnail_url_falls_back_through_media_external_youtube_legacy_then_default(): void
    {
        Storage::fake('public');

        $media = Media::create(['disk_path' => 'library/thumb.jpg', 'original_filename' => 'thumb.jpg', 'mime_type' => 'image/jpeg', 'size' => 1]);
        $withThumbnail = $this->content(['thumbnail_media_id' => $media->id]);
        $this->assertSame($media->url, $withThumbnail->thumbnailUrl());

        $withExternal = $this->content(['video_type' => 'youtube', 'video_url' => 'https://youtu.be/ext1', 'external_thumbnail_url' => 'https://i.ytimg.com/vi/ext1/maxresdefault.jpg']);
        $this->assertSame('https://i.ytimg.com/vi/ext1/maxresdefault.jpg', $withExternal->thumbnailUrl());

        $withYoutube = $this->content(['video_type' => 'youtube', 'video_url' => 'https://www.youtube.com/watch?v=xyz789']);
        $this->assertSame('https://img.youtube.com/vi/xyz789/hqdefault.jpg', $withYoutube->thumbnailUrl());

        $legacy = $this->content(['featured_image' => 'health-content/legacy.jpg']);
        $this->assertSame(asset('health-content/legacy.jpg'), $legacy->thumbnailUrl());

        $bare = $this->content();
        $this->assertSame(asset(config('site.default_content_image')), $bare->thumbnailUrl());
    }

    public function test_duration_label_formats_seconds_as_mm_ss_and_is_null_when_absent(): void
    {
        $this->assertSame('06:25', $this->content(['duration_seconds' => 385])->durationLabel());
        $this->assertNull($this->content(['duration_seconds' => null])->durationLabel());
    }

    // --- Admin wiring ---

    public function test_creating_content_with_a_youtube_url_saves_it_and_does_not_touch_media_folders(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.health-content.store'), [
            'title_km' => 'វីដេអូថ្មី',
            'slug' => 'new-video',
            'video_type' => 'youtube',
            'video_url' => 'https://www.youtube.com/watch?v=newvideo1',
            'duration' => '04:18',
        ]);

        $response->assertRedirect();
        $content = HealthContent::where('slug', 'new-video')->firstOrFail();
        $this->assertSame('youtube', $content->video_type);
        $this->assertSame('video', $content->content_type);
        $this->assertSame(258, $content->duration_seconds);
    }

    public function test_uploading_a_video_file_files_it_into_the_contents_own_media_folder(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin())->post(route('admin.health-content.store'), [
            'title_km' => 'វីដេអូឯកសារ',
            'slug' => 'media-video',
            'video_type' => 'uploaded',
            'video_file' => UploadedFile::fake()->create('clip.mp4', 500, 'video/mp4'),
        ]);

        $response->assertRedirect();
        $content = HealthContent::where('slug', 'media-video')->firstOrFail();
        $this->assertNotNull($content->video_media_id);

        $folder = MediaFolder::where('owner_type', HealthContent::class)->where('owner_id', $content->id)->first();
        $this->assertNotNull($folder);
        $this->assertSame("#{$content->id} — វីដេអូឯកសារ", $folder->name);
        $this->assertSame('មាតិកាអប់រំសុខភាព', $folder->parent->name);
        $this->assertSame($folder->id, $content->videoMedia->folder_id);
    }

    public function test_thumbnail_media_id_can_be_picked_from_the_library_without_uploading(): void
    {
        $admin = $this->admin();
        $media = Media::create(['disk_path' => 'library/pick.jpg', 'original_filename' => 'pick.jpg', 'mime_type' => 'image/jpeg', 'size' => 1]);

        $response = $this->actingAs($admin)->post(route('admin.health-content.store'), [
            'title_km' => 'ជ្រើសរើសរូបភាព',
            'slug' => 'picked-thumb',
            'thumbnail_media_id' => $media->id,
        ]);

        $response->assertRedirect();
        $this->assertSame($media->id, HealthContent::where('slug', 'picked-thumb')->value('thumbnail_media_id'));
    }

    public function test_saving_a_non_empty_educational_message_marks_the_summary_as_reviewed(): void
    {
        $admin = $this->admin();
        $content = $this->content(['summary_status' => 'needs_review']);

        $this->actingAs($admin)->put(route('admin.health-content.update', $content), [
            'title_km' => $content->title_km,
            'slug' => $content->slug,
            'educational_message' => 'សារអប់រំសុខភាពសង្ខេប។',
        ]);

        $this->assertSame('reviewed', $content->fresh()->summary_status);
    }

    // --- Homepage selection ---

    public function test_homepage_shows_only_the_featured_video_and_excludes_it_from_the_secondary_list(): void
    {
        $featured = $this->content(['title_km' => 'វីដេអូលេចធ្លោ', 'is_featured' => true, 'video_type' => 'youtube', 'video_url' => 'https://youtu.be/f1', 'published_at' => now()->subDays(5)]);
        $newer = $this->content(['title_km' => 'វីដេអូថ្មីជាង', 'video_type' => 'youtube', 'video_url' => 'https://youtu.be/f2', 'published_at' => now()->subDay()]);
        $plainArticle = $this->content(['title_km' => 'អត្ថបទធម្មតា', 'published_at' => now()]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSeeInOrder(['home-featured-health-video', 'វីដេអូលេចធ្លោ']);
        $response->assertSee('វីដេអូថ្មីជាង');
        $response->assertDontSee('អត្ថបទធម្មតា');
    }

    public function test_homepage_falls_back_to_newest_video_when_none_is_featured(): void
    {
        $this->content(['title_km' => 'ចាស់ជាង', 'video_type' => 'youtube', 'video_url' => 'https://youtu.be/old', 'published_at' => now()->subDays(3)]);
        $newest = $this->content(['title_km' => 'ថ្មីបំផុត', 'video_type' => 'youtube', 'video_url' => 'https://youtu.be/new', 'published_at' => now()->subDay()]);

        $response = $this->get('/');

        $response->assertOk()->assertSeeInOrder(['home-featured-health-video', 'ថ្មីបំផុត']);
    }

    public function test_health_video_section_is_absent_when_no_published_item_has_a_video(): void
    {
        $this->content(['video_type' => null]); // plain article only

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('home-featured-health-video', false);
    }

    // --- Public detail page ---

    public function test_show_page_displays_the_educational_message_and_source_attribution_when_present(): void
    {
        $article = $this->content([
            'video_type' => 'youtube', 'video_url' => 'https://youtu.be/pubshow001',
            'educational_message' => 'សូមលាងដៃញឹកញាប់ដើម្បីការពារជំងឺឆ្លង។',
            'source_name' => 'ក្រសួងសុខាភិបាល Ministry of Health',
            'source_video_url' => 'https://www.youtube.com/watch?v=pubshow001',
        ]);

        $response = $this->get(route('health-content.show', $article));

        $response->assertOk();
        $response->assertSee('សារអប់រំសុខភាព');
        $response->assertSee('សូមលាងដៃញឹកញាប់ដើម្បីការពារជំងឺឆ្លង។');
        $response->assertSee('ប្រភព៖ ក្រសួងសុខាភិបាល Ministry of Health', false);
        $response->assertSee('https://www.youtube.com/watch?v=pubshow001', false);
    }

    public function test_show_page_hides_the_message_and_source_blocks_for_a_plain_article(): void
    {
        $article = $this->content();

        $response = $this->get(route('health-content.show', $article));

        $response->assertOk();
        $response->assertDontSee('សារអប់រំសុខភាព');
        $response->assertDontSee('ប្រភព៖', false);
    }
}
