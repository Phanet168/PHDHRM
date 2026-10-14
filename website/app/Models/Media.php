<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use SoftDeletes;

    protected $table = 'media';

    protected $fillable = [
        'folder_id',
        'original_filename',
        'disk_path',
        'mime_type',
        'size',
        'width',
        'height',
        'alt_text',
        'caption',
        'collection',
        'uploaded_by',
    ];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'folder_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Deliberately NOT Storage::disk('public')->url(): that reads the fixed
     * APP_URL string baked into config/filesystems.php, so every image would
     * keep pointing at (e.g.) "localhost" even when the site is opened from
     * a different host/IP (LAN address, real domain, etc.), breaking every
     * <img> tag site-wide for anyone not on that exact host. asset() instead
     * builds the URL from the CURRENT request's own host when one exists,
     * and only falls back to APP_URL in contexts with no request (CLI,
     * queued jobs, tests) — where a fixed default is the correct behavior.
     */
    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->disk_path);
    }

    /**
     * Resolves a legacy path string (News/NewsImage's old featured_image/image columns, from
     * before those records were backed by a real Media row) to a working public URL, or null
     * if no file exists at that path under either convention this codebase has used: a real
     * upload under storage/app/public (checked first), or a static demo asset committed
     * directly under public/ (how some older seeded News rows were bundled). Callers fall back
     * to the site default image when this returns null, instead of ever rendering a broken
     * <img> for a path nothing exists at.
     */
    public static function resolveLegacyPath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (Storage::disk('public')->exists($path)) {
            return asset('storage/'.$path);
        }

        if (file_exists(public_path($path))) {
            return asset($path);
        }

        return null;
    }

    /**
     * True when the DB row exists but the physical file is gone (deleted outside the app,
     * a failed/interrupted move, disk cleared, etc). Views use this to show a clear "Missing
     * file" placeholder instead of a broken <img> icon — see spec §29.
     */
    public function fileMissing(): bool
    {
        return ! Storage::disk('public')->exists($this->disk_path);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function isVideo(): bool
    {
        return str_starts_with($this->mime_type, 'video/');
    }

    public function iconType(): string
    {
        return match (true) {
            $this->isImage() => 'image',
            $this->isVideo() => 'video',
            $this->mime_type === 'application/pdf' => 'pdf',
            in_array($this->mime_type, ['application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'], true) => 'word',
            in_array($this->mime_type, ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'], true) => 'excel',
            default => 'file',
        };
    }

    public function typeLabel(): string
    {
        return match ($this->iconType()) {
            'pdf' => 'PDF',
            'word' => 'DOC',
            'excel' => 'XLS',
            'video' => 'VIDEO',
            default => strtoupper(pathinfo($this->original_filename, PATHINFO_EXTENSION)),
        };
    }

    public function dimensions(): ?string
    {
        return $this->width && $this->height ? "{$this->width}×{$this->height}" : null;
    }

    public function formattedSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->size;
        $i = 0;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, 1) . ' ' . $units[$i];
    }
}
