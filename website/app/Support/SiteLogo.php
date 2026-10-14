<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class SiteLogo
{
    public static function url(?string $path): ?string
    {
        $path = trim($path ?? '');
        if ($path === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $path)) {
            return filter_var($path, FILTER_VALIDATE_URL) ? $path : null;
        }

        // Settings uploads use public-disk paths; also accept public asset paths.
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if (str_contains($path, '..') || str_contains($path, ':')) {
            return null;
        }
        if (str_starts_with($path, 'public/')) {
            $path = substr($path, 7);
        }
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, 8);

            // asset(), not Storage::disk('public')->url(): the latter reads
            // the fixed APP_URL string baked into config/filesystems.php, so
            // the logo would keep pointing at (e.g.) "localhost" even when
            // the site is opened from a different host/IP.
            return Storage::disk('public')->exists($path) ? asset('storage/' . $path) : null;
        }
        if (is_file(public_path($path))) {
            return asset($path);
        }

        return Storage::disk('public')->exists($path) ? asset('storage/' . $path) : null;
    }
}
