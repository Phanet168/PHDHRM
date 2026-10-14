<?php

namespace App\Services;

use App\Support\Duration;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Pure YouTube API access — no database writes. Prefers the official Data
 * API v3 (when YOUTUBE_API_KEY is configured) and falls back to the
 * key-less oEmbed endpoint for single-video metadata otherwise. Never
 * scrapes youtube.com HTML.
 */
class YouTubeService
{
    /**
     * Accepts a full YouTube URL (watch/youtu.be/embed/shorts/live) or a
     * bare 11-character video ID, as pasted by an admin.
     */
    public function parseVideoId(string $input): ?string
    {
        $input = trim($input);

        if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $input)) {
            return $input;
        }

        if (preg_match('#(?:youtube\.com/(?:watch\?v=|embed/|shorts/|live/)|youtu\.be/)([a-zA-Z0-9_-]{11})#', $input, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Extracts an "@handle" from a channel URL, or accepts one pasted bare.
     */
    public function parseChannelHandle(string $channelUrl): ?string
    {
        $input = trim($channelUrl);

        if (preg_match('#youtube\.com/(@[\w.-]+)#i', $input, $m)) {
            return $m[1];
        }

        if (str_starts_with($input, '@')) {
            return $input;
        }

        return null;
    }

    /**
     * @return array{video_id:string,title:string,description:?string,channel_title:?string,channel_url:?string,video_url:string,thumbnail_url:?string,duration_seconds:?int,published_at:?Carbon}
     */
    public function getVideoMetadata(string $videoId): array
    {
        try {
            return $this->hasApiKey() ? $this->fetchViaDataApi($videoId) : $this->fetchViaOEmbed($videoId);
        } catch (ConnectionException) {
            throw new RuntimeException('មិនអាចភ្ជាប់ទៅកាន់ YouTube បានទេ។ សូមពិនិត្យអ៊ីនធឺណិត ហើយព្យាយាមម្តងទៀត។');
        }
    }

    /**
     * Requires YOUTUBE_API_KEY — there is no official, non-scraping way to
     * resolve a channel handle to its uploads playlist without the Data API.
     *
     * @return array<int, array{video_id:string,title:string,description:?string,channel_title:?string,channel_url:?string,video_url:string,thumbnail_url:?string,duration_seconds:?int,published_at:?Carbon}>
     */
    public function getChannelVideos(string $channelUrl, int $limit): array
    {
        if (! $this->hasApiKey()) {
            throw new RuntimeException('ការទាញយកវីដេអូពី Channel ត្រូវការ YOUTUBE_API_KEY។ សូមកំណត់រចនាសម្ព័ន្ធក្នុងឯកសារ .env សិន។');
        }

        $handle = $this->parseChannelHandle($channelUrl);
        if (! $handle) {
            throw new RuntimeException('អាសយដ្ឋាន Channel មិនត្រឹមត្រូវទេ។');
        }

        $limit = max(1, min($limit, 50));

        try {
            return Cache::remember("youtube.channel.{$handle}.{$limit}", now()->addMinutes(10), function () use ($handle, $limit) {
                $playlistId = $this->resolveUploadsPlaylistId($handle);
                $items = $this->fetchPlaylistItems($playlistId, $limit);
                $durations = $this->fetchDurations(array_column($items, 'video_id'));

                return array_map(function ($item) use ($durations) {
                    $item['duration_seconds'] = $durations[$item['video_id']] ?? null;

                    return $this->normalizeVideo($item);
                }, $items);
            });
        } catch (ConnectionException) {
            throw new RuntimeException('មិនអាចភ្ជាប់ទៅកាន់ YouTube បានទេ។ សូមពិនិត្យអ៊ីនធឺណិត ហើយព្យាយាមម្តងទៀត។');
        }
    }

    /**
     * Funnels every source (Data API single-video, oEmbed, Data API
     * playlist item) into one consistent shape.
     */
    public function normalizeVideo(array $fields): array
    {
        $publishedAt = $fields['published_at'] ?? null;

        return [
            'video_id' => $fields['video_id'],
            'title' => $fields['title'] ?? '',
            'description' => $fields['description'] ?? null,
            'channel_title' => $fields['channel_title'] ?? null,
            'channel_url' => $fields['channel_url'] ?? null,
            'video_url' => 'https://www.youtube.com/watch?v='.$fields['video_id'],
            'thumbnail_url' => $fields['thumbnail_url'] ?? $this->bestThumbnail($fields['thumbnails'] ?? []),
            'duration_seconds' => array_key_exists('duration_iso8601', $fields)
                ? Duration::parseIso8601($fields['duration_iso8601'])
                : ($fields['duration_seconds'] ?? null),
            'published_at' => $publishedAt ? Carbon::parse($publishedAt) : null,
        ];
    }

    private function fetchViaDataApi(string $videoId): array
    {
        $response = Http::get('https://www.googleapis.com/youtube/v3/videos', [
            'part' => 'snippet,contentDetails',
            'id' => $videoId,
            'key' => config('services.youtube.key'),
        ]);

        if ($response->failed()) {
            $this->throwForFailedResponse($response);
        }

        $items = $response->json('items', []);
        if (empty($items)) {
            throw new RuntimeException('រកមិនឃើញវីដេអូនេះទេ។ វាអាចត្រូវបានលុប ឬកំណត់ជាឯកជន។');
        }

        $item = $items[0];
        $snippet = $item['snippet'] ?? [];

        return $this->normalizeVideo([
            'video_id' => $videoId,
            'title' => $snippet['title'] ?? '',
            'description' => $snippet['description'] ?? null,
            'channel_title' => $snippet['channelTitle'] ?? null,
            'channel_url' => isset($snippet['channelId']) ? 'https://www.youtube.com/channel/'.$snippet['channelId'] : null,
            'thumbnails' => $snippet['thumbnails'] ?? [],
            'published_at' => $snippet['publishedAt'] ?? null,
            'duration_iso8601' => $item['contentDetails']['duration'] ?? null,
        ]);
    }

    private function fetchViaOEmbed(string $videoId): array
    {
        $response = Http::get('https://www.youtube.com/oembed', [
            'url' => 'https://www.youtube.com/watch?v='.$videoId,
            'format' => 'json',
        ]);

        if ($response->failed()) {
            throw new RuntimeException('រកមិនឃើញវីដេអូនេះទេ។ វាអាចត្រូវបានលុប ឬកំណត់ជាឯកជន។');
        }

        $data = $response->json();

        return $this->normalizeVideo([
            'video_id' => $videoId,
            'title' => $data['title'] ?? '',
            'description' => null,
            'channel_title' => $data['author_name'] ?? null,
            'channel_url' => $data['author_url'] ?? null,
            'thumbnail_url' => $data['thumbnail_url'] ?? null,
            'published_at' => null,
            'duration_seconds' => null,
        ]);
    }

    private function resolveUploadsPlaylistId(string $handle): string
    {
        $response = Http::get('https://www.googleapis.com/youtube/v3/channels', [
            'part' => 'contentDetails',
            'forHandle' => $handle,
            'key' => config('services.youtube.key'),
        ]);

        if ($response->failed()) {
            $this->throwForFailedResponse($response);
        }

        $items = $response->json('items', []);
        if (empty($items)) {
            throw new RuntimeException('រកមិនឃើញ Channel នេះទេ។');
        }

        return $items[0]['contentDetails']['relatedPlaylists']['uploads'];
    }

    private function fetchPlaylistItems(string $playlistId, int $limit): array
    {
        $response = Http::get('https://www.googleapis.com/youtube/v3/playlistItems', [
            'part' => 'snippet',
            'playlistId' => $playlistId,
            'maxResults' => $limit,
            'key' => config('services.youtube.key'),
        ]);

        if ($response->failed()) {
            $this->throwForFailedResponse($response);
        }

        return array_map(function ($item) {
            $snippet = $item['snippet'];

            return [
                'video_id' => $snippet['resourceId']['videoId'],
                'title' => $snippet['title'] ?? '',
                'description' => $snippet['description'] ?? null,
                'channel_title' => $snippet['channelTitle'] ?? null,
                'channel_url' => isset($snippet['videoOwnerChannelId'])
                    ? 'https://www.youtube.com/channel/'.$snippet['videoOwnerChannelId']
                    : null,
                'thumbnails' => $snippet['thumbnails'] ?? [],
                'published_at' => $snippet['publishedAt'] ?? null,
            ];
        }, $response->json('items', []));
    }

    /**
     * One batched call for up to 50 video IDs at once — minimizes quota
     * usage versus fetching each video's duration individually. Duration is
     * a nice-to-have for the preview list, so a failure here degrades
     * gracefully (missing durations) instead of failing the whole search.
     */
    private function fetchDurations(array $videoIds): array
    {
        if (empty($videoIds)) {
            return [];
        }

        $response = Http::get('https://www.googleapis.com/youtube/v3/videos', [
            'part' => 'contentDetails',
            'id' => implode(',', array_slice($videoIds, 0, 50)),
            'key' => config('services.youtube.key'),
        ]);

        if ($response->failed()) {
            return [];
        }

        $durations = [];
        foreach ($response->json('items', []) as $item) {
            $durations[$item['id']] = Duration::parseIso8601($item['contentDetails']['duration'] ?? null);
        }

        return $durations;
    }

    private function bestThumbnail(array $thumbnails): ?string
    {
        foreach (['maxres', 'standard', 'high', 'medium', 'default'] as $quality) {
            if (isset($thumbnails[$quality]['url'])) {
                return $thumbnails[$quality]['url'];
            }
        }

        return null;
    }

    private function hasApiKey(): bool
    {
        return filled(config('services.youtube.key'));
    }

    private function throwForFailedResponse(Response $response): never
    {
        if ($response->json('error.errors.0.reason') === 'quotaExceeded') {
            throw new RuntimeException('លើសកម្រិត Quota របស់ YouTube API ប្រចាំថ្ងៃ។ សូមព្យាយាមម្តងទៀតនៅពេលក្រោយ។');
        }

        throw new RuntimeException('មិនអាចទាញយកទិន្នន័យពី YouTube បានទេ។ សូមព្យាយាមម្តងទៀត។');
    }
}
