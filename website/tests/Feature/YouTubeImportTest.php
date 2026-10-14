<?php

namespace Tests\Feature;

use App\Models\HealthContent;
use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\User;
use App\Services\YouTubeImportService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class YouTubeImportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Super Admin');

        return $admin;
    }

    private function fakeOEmbed(string $videoId, array $overrides = []): void
    {
        Http::fake([
            'https://www.youtube.com/oembed*' => Http::response(array_merge([
                'title' => 'Community Health Tips',
                'author_name' => 'MOH Cambodia',
                'author_url' => 'https://www.youtube.com/@MOHCambodia',
                'thumbnail_url' => "https://i.ytimg.com/vi/{$videoId}/hqdefault.jpg",
            ], $overrides), 200),
        ]);
    }

    public function test_unauthorized_user_cannot_import(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $editor = User::factory()->create(['is_active' => true]);
        // A role with no health-content.import permission at all.
        $editor->assignRole('Reviewer');

        $response = $this->actingAs($editor)->post(route('admin.health-content.youtube-import.import'), [
            'url_or_id' => 'https://www.youtube.com/watch?v=abc12345678',
        ]);

        $response->assertForbidden();
        $this->assertSame(0, HealthContent::count());
    }

    public function test_valid_import_creates_a_draft_with_source_attribution_and_needs_review_summary(): void
    {
        $this->fakeOEmbed('abc12345678');

        $response = $this->actingAs($this->admin())->post(route('admin.health-content.youtube-import.import'), [
            'url_or_id' => 'https://www.youtube.com/watch?v=abc12345678',
        ]);

        $response->assertRedirect();
        $content = HealthContent::where('youtube_video_id', 'abc12345678')->firstOrFail();

        $this->assertSame('draft', $content->status);
        $this->assertSame('video', $content->content_type);
        $this->assertSame('youtube', $content->video_type);
        $this->assertSame('needs_review', $content->summary_status);
        $this->assertSame('Community Health Tips', $content->title_km);
        $this->assertSame('Community Health Tips', $content->title_original);
        $this->assertSame('ក្រសួងសុខាភិបាល Ministry of Health', $content->source_name);
        $this->assertSame('https://www.youtube.com/@MOHCambodia', $content->source_channel_url);
        $this->assertSame('https://www.youtube.com/watch?v=abc12345678', $content->source_video_url);
        $this->assertSame("https://i.ytimg.com/vi/abc12345678/hqdefault.jpg", $content->external_thumbnail_url);
        $this->assertSame('video-abc12345678', $content->slug);
        // oEmbed carries no description at all.
        $this->assertSame('', $content->educational_message);
        $this->assertSame('', $content->excerpt);
        $this->assertSame('', $content->content);
    }

    public function test_import_derives_excerpt_content_and_educational_message_from_the_same_description_at_different_lengths(): void
    {
        config(['services.youtube.key' => 'fake-key']);
        $description = str_repeat('សុខភាពល្អចាប់ផ្តើមពីអនាម័យផ្ទាល់ខ្លួន និងទម្លាប់ល្អប្រចាំថ្ងៃ។ ', 30);

        Http::fake([
            'https://www.googleapis.com/youtube/v3/videos*' => Http::response([
                'items' => [[
                    'id' => 'longdesc001',
                    'snippet' => ['title' => 'អនាម័យផ្ទាល់ខ្លួន', 'description' => $description, 'channelTitle' => 'MOH Cambodia'],
                    'contentDetails' => ['duration' => 'PT2M0S'],
                ]],
            ], 200),
        ]);

        $this->actingAs($this->admin())->post(route('admin.health-content.youtube-import.import'), [
            'url_or_id' => 'https://youtu.be/longdesc001',
        ]);

        $content = HealthContent::where('youtube_video_id', 'longdesc001')->firstOrFail();

        $this->assertNotSame('', $content->excerpt);
        $this->assertNotSame('', $content->content);
        $this->assertNotSame('', $content->educational_message);
        $this->assertLessThanOrEqual(mb_strlen($content->content), mb_strlen($content->excerpt));
        $this->assertLessThanOrEqual(mb_strlen($content->content) + 1, mb_strlen($content->educational_message) + 1);
        $this->assertNotSame($description, $content->content); // never the full source text
        $this->assertSame($description, $content->source_description); // full text kept only as a private admin reference
    }

    public function test_import_truncates_a_long_description_instead_of_copying_it_in_full(): void
    {
        Http::fake([
            'https://www.youtube.com/oembed*' => Http::response([
                'title' => 'Long Description Video',
                'author_name' => 'MOH Cambodia',
            ], 200),
        ]);
        // oEmbed never returns a description, so exercise the truncation
        // logic directly — it's what the Data API path feeds through.
        $service = app(YouTubeImportService::class);
        $long = str_repeat('This is a long official YouTube description sentence. ', 20);

        $truncated = $service->truncateDescription($long, 100);

        $this->assertLessThanOrEqual(101, mb_strlen($truncated)); // +1 for the trailing "…"
        $this->assertStringEndsWith('…', $truncated);
        $this->assertNotSame($long, $truncated);
    }

    public function test_invalid_url_is_rejected_and_creates_no_record(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.health-content.youtube-import.import'), [
            'url_or_id' => 'https://example.com/not-a-youtube-link',
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422);
        $this->assertSame(0, HealthContent::count());
    }

    public function test_duplicate_video_is_not_reimported_and_links_to_the_existing_record(): void
    {
        $this->fakeOEmbed('dupvideo001');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.health-content.youtube-import.import'), [
            'url_or_id' => 'https://www.youtube.com/watch?v=dupvideo001',
        ]);
        $this->assertSame(1, HealthContent::count());

        $response = $this->actingAs($admin)->post(route('admin.health-content.youtube-import.import'), [
            'url_or_id' => 'https://youtu.be/dupvideo001',
        ], ['Accept' => 'application/json']);

        $response->assertOk()->assertJson(['duplicate' => true]);
        $this->assertSame(1, HealthContent::count());
    }

    public function test_channel_search_requires_an_api_key_and_reports_a_clean_error_without_one(): void
    {
        config(['services.youtube.key' => null]);

        $response = $this->actingAs($this->admin())->postJson(route('admin.health-content.youtube-import.search'), [
            'channel_url' => 'https://www.youtube.com/@MOHCambodia',
            'limit' => 10,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('YOUTUBE_API_KEY', $response->json('message'));
    }

    public function test_channel_search_annotates_already_imported_videos_and_unauthorized_user_is_blocked(): void
    {
        config(['services.youtube.key' => 'fake-key']);
        $admin = $this->admin();
        HealthContent::create(['title_km' => 'មានស្រាប់', 'slug' => 'video-existingvid1', 'youtube_video_id' => 'existingvid1', 'status' => 'draft']);

        Http::fake([
            'https://www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [['contentDetails' => ['relatedPlaylists' => ['uploads' => 'UUuploads1']]]],
            ], 200),
            'https://www.googleapis.com/youtube/v3/playlistItems*' => Http::response([
                'items' => [
                    ['snippet' => ['title' => 'Already Here', 'resourceId' => ['videoId' => 'existingvid1'], 'thumbnails' => []]],
                    ['snippet' => ['title' => 'Brand New', 'resourceId' => ['videoId' => 'newvideo0001'], 'thumbnails' => []]],
                ],
            ], 200),
            'https://www.googleapis.com/youtube/v3/videos*' => Http::response(['items' => []], 200),
        ]);

        $response = $this->actingAs($admin)->postJson(route('admin.health-content.youtube-import.search'), [
            'channel_url' => 'https://www.youtube.com/@MOHCambodia',
            'limit' => 10,
        ]);

        $response->assertOk();
        $videos = $response->json('videos');
        $this->assertTrue($videos[0]['already_imported']);
        $this->assertNotNull($videos[0]['existing_edit_url']);
        $this->assertFalse($videos[1]['already_imported']);
        $this->assertNull($videos[1]['existing_edit_url']);

        $this->seed(RolePermissionSeeder::class);
        $editor = User::factory()->create(['is_active' => true]);
        $editor->assignRole('Reviewer');
        $this->actingAs($editor)->postJson(route('admin.health-content.youtube-import.search'), [
            'channel_url' => 'https://www.youtube.com/@MOHCambodia', 'limit' => 10,
        ])->assertForbidden();
    }

    public function test_import_page_renders_for_an_authorized_user_with_the_channel_prefilled(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.health-content.youtube-import.create'));

        $response->assertOk();
        $response->assertSee('https://www.youtube.com/@MOHCambodia');
    }

    public function test_import_page_is_forbidden_for_a_user_without_the_permission(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $editor = User::factory()->create(['is_active' => true]);
        $editor->assignRole('Reviewer');

        $this->actingAs($editor)->get(route('admin.health-content.youtube-import.create'))->assertForbidden();
    }

    // --- Refresh metadata / save thumbnail ---

    public function test_refresh_metadata_updates_source_fields_but_never_touches_admin_owned_fields(): void
    {
        $admin = $this->admin();
        $content = HealthContent::create([
            'title_km' => 'ចំណងជើងកែសម្រួលដោយអ្នកគ្រប់គ្រង', 'slug' => 'video-refreshme01',
            'youtube_video_id' => 'refreshme01', 'video_type' => 'youtube', 'video_url' => 'https://www.youtube.com/watch?v=refreshme01',
            'educational_message' => 'សារអប់រំដែលបានកែសម្រួលរួច', 'category_id' => null,
            'status' => 'published', 'is_featured' => true,
        ]);

        Http::fake([
            'https://www.youtube.com/oembed*' => Http::response([
                'title' => 'Updated Title From YouTube',
                'author_name' => 'MOH Cambodia',
                'thumbnail_url' => 'https://i.ytimg.com/vi/refreshme01/hqdefault_v2.jpg',
            ], 200),
        ]);

        $this->actingAs($admin)->post(route('admin.health-content.refresh-metadata', $content));

        $fresh = $content->fresh();
        $this->assertSame('Updated Title From YouTube', $fresh->title_original);
        $this->assertSame('https://i.ytimg.com/vi/refreshme01/hqdefault_v2.jpg', $fresh->external_thumbnail_url);
        // Untouched — these belong to the admin from the moment of import onward.
        $this->assertSame('ចំណងជើងកែសម្រួលដោយអ្នកគ្រប់គ្រង', $fresh->title_km);
        $this->assertSame('សារអប់រំដែលបានកែសម្រួលរួច', $fresh->educational_message);
        $this->assertSame('published', $fresh->status);
        $this->assertTrue($fresh->is_featured);
    }

    public function test_refresh_metadata_fails_gracefully_for_a_non_imported_record(): void
    {
        $admin = $this->admin();
        $content = HealthContent::create(['title_km' => 'អត្ថបទធម្មតា', 'slug' => 'plain-article-1', 'status' => 'draft']);

        $response = $this->actingAs($admin)->post(route('admin.health-content.refresh-metadata', $content));

        $response->assertRedirect();
        $response->assertSessionHasErrors('metadata');
    }

    public function test_save_thumbnail_skips_the_undersized_placeholder_and_uses_the_first_real_quality(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $content = HealthContent::create([
            'title_km' => 'វីដេអូសាកល្បង', 'slug' => 'video-thumbtest01',
            'youtube_video_id' => 'thumbtest01', 'video_type' => 'youtube', 'video_url' => 'https://www.youtube.com/watch?v=thumbtest01',
            'status' => 'draft',
        ]);

        Http::fake([
            'https://img.youtube.com/vi/thumbtest01/maxresdefault.jpg' => Http::response(str_repeat('x', 500), 200, ['Content-Type' => 'image/jpeg']),
            'https://img.youtube.com/vi/thumbtest01/hqdefault.jpg' => Http::response(str_repeat('x', 5000), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $this->actingAs($admin)->post(route('admin.health-content.save-thumbnail', $content));

        $fresh = $content->fresh();
        $this->assertNotNull($fresh->thumbnail_media_id);
        $folder = MediaFolder::where('owner_type', HealthContent::class)->where('owner_id', $fresh->id)->first();
        $this->assertSame($folder->id, $fresh->thumbnailMedia->folder_id);
    }

    public function test_save_thumbnail_fails_gracefully_when_every_quality_is_unavailable(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $content = HealthContent::create([
            'title_km' => 'វីដេអូបរាជ័យ', 'slug' => 'video-thumbfail01',
            'youtube_video_id' => 'thumbfail01', 'video_type' => 'youtube', 'video_url' => 'https://www.youtube.com/watch?v=thumbfail01',
            'status' => 'draft',
        ]);

        Http::fake([
            'https://img.youtube.com/vi/thumbfail01/*' => Http::response('', 404),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.health-content.save-thumbnail', $content));

        $response->assertRedirect();
        $response->assertSessionHasErrors('thumbnail');
        $this->assertNull($content->fresh()->thumbnail_media_id);
        $this->assertSame(0, Media::count());
    }

    // --- Edit form reference panel ---

    public function test_source_description_renders_escaped_and_the_panel_is_hidden_for_non_imported_content(): void
    {
        $admin = $this->admin();

        $imported = HealthContent::create([
            'title_km' => 'វីដេអូមានប្រភព', 'slug' => 'video-xsscheck01',
            'youtube_video_id' => 'xsscheck01', 'video_type' => 'youtube', 'video_url' => 'https://www.youtube.com/watch?v=xsscheck01',
            'source_name' => 'ក្រសួងសុខាភិបាល Ministry of Health',
            'source_description' => '<script>alert(1)</script> Safe text here.',
            'summary_status' => 'needs_review',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.health-content.edit', $imported));

        $response->assertOk();
        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertSee('alert(1)'); // escaped, so the text itself is still visible
        $response->assertSee('ត្រូវការពិនិត្យ');

        $plain = HealthContent::create(['title_km' => 'អត្ថបទធម្មតា', 'slug' => 'plain-panel-test', 'status' => 'draft']);
        $this->actingAs($admin)->get(route('admin.health-content.edit', $plain))
            ->assertDontSee('ព័ត៌មានប្រភព (នាំចូលពី YouTube)');
    }

    public function test_slug_collision_safety_net_appends_a_numeric_suffix(): void
    {
        HealthContent::create([
            'title_km' => 'ស្រាប់ជាមុន', 'slug' => 'video-safetynet01', 'status' => 'draft',
        ]);
        $this->fakeOEmbed('safetynet02');
        Http::fake([
            'https://www.youtube.com/oembed*' => Http::response(['title' => 'Another Video'], 200),
        ]);

        // Force a collision by pre-creating the slug importByUrlOrId would
        // otherwise generate for a different video id.
        HealthContent::create(['title_km' => 'ស្រាប់', 'slug' => 'video-safetynet02', 'status' => 'draft']);

        $result = app(YouTubeImportService::class)->importByUrlOrId('https://youtu.be/safetynet02');

        $this->assertSame('video-safetynet02-2', $result['content']->slug);
    }
}
