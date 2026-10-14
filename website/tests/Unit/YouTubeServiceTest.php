<?php

namespace Tests\Unit;

use App\Services\YouTubeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class YouTubeServiceTest extends TestCase
{
    private function service(): YouTubeService
    {
        return new YouTubeService();
    }

    // --- parseVideoId ---

    public function test_parse_video_id_handles_watch_youtu_be_embed_shorts_and_bare_id(): void
    {
        $service = $this->service();

        $this->assertSame('dQw4w9WgXcQ', $service->parseVideoId('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
        $this->assertSame('dQw4w9WgXcQ', $service->parseVideoId('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=30s'));
        $this->assertSame('dQw4w9WgXcQ', $service->parseVideoId('https://youtu.be/dQw4w9WgXcQ'));
        $this->assertSame('dQw4w9WgXcQ', $service->parseVideoId('https://www.youtube.com/embed/dQw4w9WgXcQ'));
        $this->assertSame('dQw4w9WgXcQ', $service->parseVideoId('https://www.youtube.com/shorts/dQw4w9WgXcQ'));
        $this->assertSame('dQw4w9WgXcQ', $service->parseVideoId('dQw4w9WgXcQ'));
    }

    public function test_parse_video_id_rejects_invalid_input(): void
    {
        $service = $this->service();

        $this->assertNull($service->parseVideoId('https://example.com/not-youtube'));
        $this->assertNull($service->parseVideoId('not a url at all'));
        $this->assertNull($service->parseVideoId(''));
    }

    // --- parseChannelHandle ---

    public function test_parse_channel_handle_extracts_from_url_or_accepts_bare_handle(): void
    {
        $service = $this->service();

        $this->assertSame('@MOHCambodia', $service->parseChannelHandle('https://www.youtube.com/@MOHCambodia'));
        $this->assertSame('@MOHCambodia', $service->parseChannelHandle('@MOHCambodia'));
        $this->assertNull($service->parseChannelHandle('https://www.youtube.com/channel/UCxxxx'));
    }

    // --- normalizeVideo ---

    public function test_normalize_video_prefers_maxres_thumbnail_and_parses_iso8601_duration(): void
    {
        $service = $this->service();

        $normalized = $service->normalizeVideo([
            'video_id' => 'abc123',
            'title' => 'Test',
            'thumbnails' => [
                'default' => ['url' => 'https://i.ytimg.com/vi/abc123/default.jpg'],
                'high' => ['url' => 'https://i.ytimg.com/vi/abc123/high.jpg'],
                'maxres' => ['url' => 'https://i.ytimg.com/vi/abc123/maxresdefault.jpg'],
            ],
            'duration_iso8601' => 'PT6M25S',
            'published_at' => '2026-01-15T10:00:00Z',
        ]);

        $this->assertSame('https://i.ytimg.com/vi/abc123/maxresdefault.jpg', $normalized['thumbnail_url']);
        $this->assertSame(385, $normalized['duration_seconds']);
        $this->assertSame('2026-01-15', $normalized['published_at']->toDateString());
        $this->assertSame('https://www.youtube.com/watch?v=abc123', $normalized['video_url']);
    }

    // --- getVideoMetadata: Data API path ---

    public function test_get_video_metadata_uses_data_api_when_key_is_configured(): void
    {
        config(['services.youtube.key' => 'fake-key']);
        Http::fake([
            'https://www.googleapis.com/youtube/v3/videos*' => Http::response([
                'items' => [[
                    'id' => 'abc123',
                    'snippet' => [
                        'title' => 'Health Tips',
                        'description' => 'A description here.',
                        'channelTitle' => 'MOH Cambodia',
                        'channelId' => 'UCxyz',
                        'publishedAt' => '2026-02-01T00:00:00Z',
                        'thumbnails' => ['high' => ['url' => 'https://i.ytimg.com/vi/abc123/hqdefault.jpg']],
                    ],
                    'contentDetails' => ['duration' => 'PT4M18S'],
                ]],
            ], 200),
        ]);

        $result = $this->service()->getVideoMetadata('abc123');

        $this->assertSame('Health Tips', $result['title']);
        $this->assertSame('MOH Cambodia', $result['channel_title']);
        $this->assertSame('https://www.youtube.com/channel/UCxyz', $result['channel_url']);
        $this->assertSame(258, $result['duration_seconds']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'key=fake-key'));
    }

    public function test_get_video_metadata_throws_when_data_api_returns_no_items(): void
    {
        config(['services.youtube.key' => 'fake-key']);
        Http::fake([
            'https://www.googleapis.com/youtube/v3/videos*' => Http::response(['items' => []], 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->service()->getVideoMetadata('deleted-video');
    }

    public function test_get_video_metadata_throws_a_clean_message_on_quota_exceeded(): void
    {
        config(['services.youtube.key' => 'fake-key']);
        Http::fake([
            'https://www.googleapis.com/youtube/v3/videos*' => Http::response([
                'error' => ['errors' => [['reason' => 'quotaExceeded']]],
            ], 403),
        ]);

        try {
            $this->service()->getVideoMetadata('abc123');
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Quota', $e->getMessage());
        }
    }

    // --- getVideoMetadata: oEmbed fallback (no API key) ---

    public function test_get_video_metadata_falls_back_to_oembed_when_no_api_key_is_configured(): void
    {
        config(['services.youtube.key' => null]);
        Http::fake([
            'https://www.youtube.com/oembed*' => Http::response([
                'title' => 'Community Health Video',
                'author_name' => 'MOH Cambodia',
                'author_url' => 'https://www.youtube.com/@MOHCambodia',
                'thumbnail_url' => 'https://i.ytimg.com/vi/abc123/hqdefault.jpg',
            ], 200),
        ]);

        $result = $this->service()->getVideoMetadata('abc123');

        $this->assertSame('Community Health Video', $result['title']);
        $this->assertSame('MOH Cambodia', $result['channel_title']);
        $this->assertNull($result['duration_seconds']);
        $this->assertNull($result['description']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'oembed'));
    }

    public function test_get_video_metadata_via_oembed_throws_on_private_or_deleted_video(): void
    {
        config(['services.youtube.key' => null]);
        Http::fake([
            'https://www.youtube.com/oembed*' => Http::response([], 404),
        ]);

        $this->expectException(RuntimeException::class);
        $this->service()->getVideoMetadata('private-video');
    }

    // --- getChannelVideos ---

    public function test_get_channel_videos_requires_an_api_key(): void
    {
        config(['services.youtube.key' => null]);

        $this->expectException(RuntimeException::class);
        $this->service()->getChannelVideos('https://www.youtube.com/@MOHCambodia', 10);
    }

    public function test_get_channel_videos_resolves_uploads_playlist_and_batches_durations(): void
    {
        config(['services.youtube.key' => 'fake-key']);
        Http::fake([
            'https://www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [['contentDetails' => ['relatedPlaylists' => ['uploads' => 'UUuploads123']]]],
            ], 200),
            'https://www.googleapis.com/youtube/v3/playlistItems*' => Http::response([
                'items' => [
                    ['snippet' => [
                        'title' => 'Video One', 'description' => 'Desc one',
                        'channelTitle' => 'MOH Cambodia', 'publishedAt' => '2026-03-01T00:00:00Z',
                        'resourceId' => ['videoId' => 'vid1'], 'thumbnails' => [],
                    ]],
                    ['snippet' => [
                        'title' => 'Video Two', 'description' => 'Desc two',
                        'channelTitle' => 'MOH Cambodia', 'publishedAt' => '2026-03-02T00:00:00Z',
                        'resourceId' => ['videoId' => 'vid2'], 'thumbnails' => [],
                    ]],
                ],
            ], 200),
            'https://www.googleapis.com/youtube/v3/videos*' => Http::response([
                'items' => [
                    ['id' => 'vid1', 'contentDetails' => ['duration' => 'PT3M0S']],
                    ['id' => 'vid2', 'contentDetails' => ['duration' => 'PT5M30S']],
                ],
            ], 200),
        ]);

        $videos = $this->service()->getChannelVideos('https://www.youtube.com/@MOHCambodia', 10);

        $this->assertCount(2, $videos);
        $this->assertSame('vid1', $videos[0]['video_id']);
        $this->assertSame(180, $videos[0]['duration_seconds']);
        $this->assertSame(330, $videos[1]['duration_seconds']);
    }

    public function test_get_channel_videos_result_is_cached_for_repeat_calls(): void
    {
        Cache::flush();
        config(['services.youtube.key' => 'fake-key']);
        Http::fake([
            'https://www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [['contentDetails' => ['relatedPlaylists' => ['uploads' => 'UUuploads123']]]],
            ], 200),
            'https://www.googleapis.com/youtube/v3/playlistItems*' => Http::response(['items' => []], 200),
        ]);

        $service = $this->service();
        $service->getChannelVideos('https://www.youtube.com/@MOHCambodia', 10);
        $service->getChannelVideos('https://www.youtube.com/@MOHCambodia', 10);

        Http::assertSentCount(2); // channels + playlistItems, once total, not twice
    }
}
