<?php

namespace App\Services;

use App\Models\HealthContent;
use App\Models\Media;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * DB-writing orchestration on top of YouTubeService's pure API access:
 * duplicate detection, draft creation, metadata refresh, and thumbnail
 * download for already-imported videos.
 */
class YouTubeImportService
{
    public function __construct(private YouTubeService $youtube, private MediaManagerService $mediaManager)
    {
    }

    /**
     * @return array{content: HealthContent, duplicate: bool}
     */
    public function importByUrlOrId(string $input): array
    {
        $videoId = $this->youtube->parseVideoId($input);
        if (! $videoId) {
            throw new RuntimeException('តំណភ្ជាប់ ឬលេខសម្គាល់វីដេអូ YouTube មិនត្រឹមត្រូវទេ។');
        }

        $existing = HealthContent::where('youtube_video_id', $videoId)->first();
        if ($existing) {
            return ['content' => $existing, 'duplicate' => true];
        }

        $metadata = $this->youtube->getVideoMetadata($videoId);
        $title = $metadata['title'] !== '' ? $metadata['title'] : $videoId;

        $content = HealthContent::create([
            'title_km' => $title,
            'title_original' => $title,
            'slug' => $this->uniqueSlug($videoId),
            // All three derived from the SAME real description text, at
            // different lengths — never invented, never the full text.
            // excerpt: a one-line teaser. content: a short body the admin
            // can expand into a real article. educational_message: the
            // shortest, most focused public-health takeaway.
            'excerpt' => $this->truncateDescription($metadata['description'], 160),
            'content' => $this->truncateDescription($metadata['description'], 600),
            'educational_message' => $this->truncateDescription($metadata['description'], 280),
            'summary_status' => 'needs_review',
            'video_type' => 'youtube',
            'video_url' => $metadata['video_url'],
            'youtube_video_id' => $videoId,
            'external_thumbnail_url' => $metadata['thumbnail_url'],
            'duration_seconds' => $metadata['duration_seconds'],
            'source_name' => 'ក្រសួងសុខាភិបាល Ministry of Health',
            'source_channel_url' => $metadata['channel_url'],
            'source_video_url' => $metadata['video_url'],
            'source_description' => $metadata['description'],
            'source_published_at' => $metadata['published_at'],
            'status' => 'draft',
            'author_id' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return ['content' => $content, 'duplicate' => false];
    }

    /**
     * Fetches a channel's latest videos and annotates each one with whether
     * it's already been imported (by youtube_video_id), so the admin's
     * selection checklist can show "មានក្នុងប្រព័ន្ធរួច" and disable it —
     * one query for the whole batch, no N+1.
     */
    public function previewChannelVideos(string $channelUrl, int $limit): array
    {
        $videos = $this->youtube->getChannelVideos($channelUrl, $limit);

        $existingSlugs = HealthContent::whereIn('youtube_video_id', array_column($videos, 'video_id'))
            ->pluck('slug', 'youtube_video_id');

        return array_map(function ($video) use ($existingSlugs) {
            $slug = $existingSlugs[$video['video_id']] ?? null;
            $video['already_imported'] = $slug !== null;
            $video['existing_edit_url'] = $slug ? route('admin.health-content.edit', $slug) : null;

            return $video;
        }, $videos);
    }

    /**
     * Re-fetches from YouTube and refreshes only the source-provided fields
     * — never touches title_km, educational_message, category_id, status,
     * or is_featured, all of which belong to the admin from here on.
     */
    public function refreshMetadata(HealthContent $content): HealthContent
    {
        if (! $content->youtube_video_id) {
            throw new RuntimeException('មាតិកានេះមិនមែនជាវីដេអូដែលនាំចូលពី YouTube ទេ។');
        }

        $metadata = $this->youtube->getVideoMetadata($content->youtube_video_id);

        $content->update([
            'title_original' => $metadata['title'] !== '' ? $metadata['title'] : $content->title_original,
            'duration_seconds' => $metadata['duration_seconds'],
            'source_published_at' => $metadata['published_at'],
            'source_description' => $metadata['description'],
            'external_thumbnail_url' => $metadata['thumbnail_url'],
            'updated_by' => Auth::id(),
        ]);

        return $content->fresh();
    }

    /**
     * Downloads the best available YouTube-hosted thumbnail into the Media
     * Library (the record's own per-record folder) and sets
     * thumbnail_media_id, so thumbnailUrl() prefers it from then on.
     * maxresdefault/hqdefault don't exist for every video — YouTube serves
     * a small fixed-size gray placeholder (still HTTP 200, image/jpeg)
     * instead of a 404, so a minimum byte-size check is what actually
     * detects "this quality doesn't exist" here.
     */
    public function saveThumbnailToLibrary(HealthContent $content): HealthContent
    {
        if (! $content->youtube_video_id) {
            throw new RuntimeException('មាតិកានេះមិនមែនជាវីដេអូដែលនាំចូលពី YouTube ទេ។');
        }

        $bytes = null;
        foreach (['maxresdefault', 'hqdefault'] as $quality) {
            $response = Http::get("https://img.youtube.com/vi/{$content->youtube_video_id}/{$quality}.jpg");
            if ($response->successful() && $this->isImageResponse($response) && strlen($response->body()) > 2000) {
                $bytes = $response->body();
                break;
            }
        }

        if (! $bytes) {
            $response = Http::get("https://img.youtube.com/vi/{$content->youtube_video_id}/mqdefault.jpg");
            if ($response->successful() && $this->isImageResponse($response)) {
                $bytes = $response->body();
            }
        }

        if (! $bytes) {
            throw new RuntimeException('មិនអាចទាញយករូបភាពតំណាងបានទេ។');
        }

        $folderId = $this->mediaManager->resolveFolderForRecord('health-content', $content, "#{$content->id} — {$content->title_km}");
        $path = 'library/'.Str::uuid()->toString().'.jpg';
        Storage::disk('public')->put($path, $bytes);

        $media = Media::create([
            'folder_id' => $folderId,
            'original_filename' => "youtube-{$content->youtube_video_id}.jpg",
            'disk_path' => $path,
            'mime_type' => 'image/jpeg',
            'size' => strlen($bytes),
            'uploaded_by' => Auth::id(),
        ]);

        $content->update(['thumbnail_media_id' => $media->id]);

        return $content->fresh();
    }

    private function isImageResponse($response): bool
    {
        return str_starts_with((string) $response->header('Content-Type'), 'image/');
    }

    /**
     * Never the full YouTube description (copyrighted third-party text) —
     * only a short, word-boundary-safe excerpt the admin is expected to
     * review and rewrite into a real 2-4 sentence educational message.
     */
    public function truncateDescription(?string $description, int $maxChars = 280): string
    {
        if (! $description) {
            return '';
        }

        $description = trim(preg_replace('/\s+/', ' ', $description));

        if (mb_strlen($description) <= $maxChars) {
            return $description;
        }

        $truncated = mb_substr($description, 0, $maxChars);
        $lastSpace = mb_strrpos($truncated, ' ');
        if ($lastSpace !== false) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }

        return rtrim($truncated).'…';
    }

    /**
     * Base the slug on the video ID itself (stable, ASCII-safe, and already
     * effectively unique) rather than slugifying a possibly-Khmer or
     * highly generic title. The collision-suffix loop is a safety net —
     * the youtube_video_id unique column is the real duplicate guard.
     */
    private function uniqueSlug(string $videoId): string
    {
        $base = 'video-'.$videoId;
        $slug = $base;
        $i = 2;

        while (HealthContent::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
