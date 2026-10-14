<?php

namespace App\Support;

class Duration
{
    /**
     * Seconds -> "mm:ss", or null when there's nothing to show (the caller
     * hides the duration badge entirely rather than displaying "00:00").
     */
    public static function format(?int $seconds): ?string
    {
        if (! $seconds) {
            return null;
        }

        return sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    /**
     * YouTube's ISO-8601 duration ("PT1H2M10S", "PT6M25S", "PT45S") -> total
     * seconds, or null if unparseable/absent.
     */
    public static function parseIso8601(?string $duration): ?int
    {
        if (! $duration || ! preg_match('/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $duration, $m)) {
            return null;
        }

        return ((int) ($m[1] ?? 0)) * 3600 + ((int) ($m[2] ?? 0)) * 60 + (int) ($m[3] ?? 0);
    }
}
