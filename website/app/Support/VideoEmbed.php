<?php

namespace App\Support;

class VideoEmbed
{
    /**
     * Parses a YouTube, Vimeo, or direct video-file URL into an embeddable
     * {type, src} pair. Returns null for anything else (including Facebook
     * links, which aren't supported without their SDK/app id).
     */
    public static function resolve(?string $url): ?array
    {
        if (! $url) {
            return null;
        }

        if ($id = self::youtubeId($url)) {
            return ['type' => 'iframe', 'src' => "https://www.youtube.com/embed/{$id}"];
        }

        if (preg_match('/vimeo\.com\/(\d+)/', $url, $m)) {
            return ['type' => 'iframe', 'src' => "https://player.vimeo.com/video/{$m[1]}"];
        }

        if (preg_match('/\.(mp4|webm|ogg)(\?.*)?$/i', $url)) {
            return ['type' => 'video', 'src' => $url];
        }

        return null;
    }

    public static function youtubeId(?string $url): ?string
    {
        if ($url && preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]+)/', $url, $m)) {
            return $m[1];
        }

        return null;
    }
}
