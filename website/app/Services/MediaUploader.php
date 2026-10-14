<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class MediaUploader
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf'];

    private const ALLOWED_MIMES = [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf',
    ];

    public const IMAGE_ONLY_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public const IMAGE_ONLY_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * Broader allow-list for the Media Library only (documents/video in
     * addition to images). SVG is deliberately excluded even though the
     * UI can render its icon: an uploaded SVG is served as a raw static
     * file, and a hostile SVG can carry an embedded <script>, so allowing
     * it here would open a stored-XSS path with no sanitizer in place.
     */
    public const LIBRARY_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'mp4', 'webm'];

    public const LIBRARY_MIMES = [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'video/mp4', 'video/webm',
    ];

    public static function store(UploadedFile $file, string $collection, ?array $allowedExtensions = null, ?array $allowedMimes = null, ?int $folderId = null): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = $file->getMimeType();

        $allowedExtensions ??= self::ALLOWED_EXTENSIONS;
        $allowedMimes ??= self::ALLOWED_MIMES;

        if (! in_array($extension, $allowedExtensions, true) || ! in_array($mime, $allowedMimes, true)) {
            throw new RuntimeException('ប្រភេទឯកសារនេះមិនត្រូវបានអនុញ្ញាតទេ។');
        }

        $filename = Str::uuid()->toString() . '.' . $extension;
        $path = $file->storeAs($collection, $filename, 'public');

        $manager = app(MediaManagerService::class);
        $folderId ??= $manager->resolveFolderForCollection($collection);
        $name = $manager->uniqueFilenameInFolder($file->getClientOriginalName(), $folderId);

        Media::create([
            'folder_id' => $folderId,
            'original_filename' => $name,
            'disk_path' => $path,
            'mime_type' => $mime,
            'size' => $file->getSize(),
            'collection' => $collection,
            'uploaded_by' => Auth::id(),
        ]);

        return $path;
    }

    public static function delete(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
