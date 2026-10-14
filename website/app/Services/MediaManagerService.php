<?php

namespace App\Services;

use App\Models\Content;
use App\Models\ContentImage;
use App\Models\Facility;
use App\Models\FacilityGallery;
use App\Models\HealthContent;
use App\Models\HeroSlide;
use App\Models\LegalDocument;
use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\News;
use App\Models\NewsImage;
use App\Models\Partner;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class MediaManagerService
{
    /**
     * Where each upload source's files get auto-filed in the Media Library,
     * keyed by the "collection" string each MediaUploader::store() caller
     * already passes. Keeping every module's uploads inside this folder
     * tree (instead of dumped unfiled at the root) is what makes the
     * library actually browsable once many modules are uploading into it.
     * 'library' (the Media Library's own direct upload) is deliberately
     * absent: that flow lets the user pick the destination folder itself.
     */
    private const COLLECTION_FOLDER_PATHS = [
        'news' => ['ព័ត៌មាន និងសកម្មភាព'],
        'content' => ['អត្ថបទ'],
        'content/blocks' => ['អត្ថបទ', 'ប្លុកខ្លឹមសារ'],
        'content/gallery' => ['វិចិត្រសាលអត្ថបទ'],
        'hero-slides' => ['Hero Slider'],
        'partners' => ['អង្គការដៃគូ'],
        'settings' => ['ការកំណត់ប្រព័ន្ធ'],
        'health-content' => ['មាតិកាអប់រំសុខភាព'],
        'legal-documents' => ['លិខិតបទដ្ឋានគតិយុត្ត'],
        'facilities/logos' => ['មូលដ្ឋានសុខាភិបាល', 'ស្លាកសញ្ញា'],
        'facilities/covers' => ['មូលដ្ឋានសុខាភិបាល', 'រូបភាពគម្រប'],
        'facilities/leaders' => ['មូលដ្ឋានសុខាភិបាល', 'អ្នកដឹកនាំ'],
        'facilities/gallery' => ['មូលដ្ឋានសុខាភិបាល', 'វិចិត្រសាល'],
        'pages' => ['ទំព័រ'],
        'pages/blocks' => ['ទំព័រ', 'ប្លុកទំព័រ'],
        'homepage-quick-links' => ['តំណភ្ជាប់រហ័សទំព័រដើម'],
        'page-banners' => ['ផ្ទាំងបដាទំព័រ'],
        'leader-recommendations' => ['អនុសាសន៍ថ្នាក់ដឹកនាំ'],
        'about' => ['អំពីមន្ទីរ'],
    ];

    /**
     * Resolves (creating as needed) the folder a given upload "collection"
     * belongs in. Idempotent: repeated uploads reuse the same folder
     * instead of creating a new one each time.
     */
    public function resolveFolderForCollection(string $collection): ?int
    {
        $path = self::COLLECTION_FOLDER_PATHS[$collection] ?? null;

        return $path ? $this->resolveFolderPath($path) : null;
    }

    public function resolveFolderPath(array $segments): int
    {
        $parentId = null;

        foreach ($segments as $name) {
            $folder = MediaFolder::where('parent_id', $parentId)->where('name', $name)->first();
            $folder ??= $this->createFolder($parentId, $name, null);
            $parentId = $folder->id;
        }

        return $parentId;
    }

    /**
     * Get-or-create the ONE dedicated folder for a single content record
     * (one News row, one Content article, one Page, ...), nested under that
     * type's shared container folder (see resolveFolderForCollection()).
     * Identity is by (owner_type, owner_id), never by name, so renaming the
     * record's title never orphans or duplicates its folder — the display
     * name is simply kept in sync with $label via the existing
     * renameFolder(). Callers uploading different kinds of files for the
     * SAME record (featured image, gallery, blocks) should pass the same
     * $collection so everything lands in one folder, not split across
     * several.
     */
    public function resolveFolderForRecord(string $collection, Model $record, string $label): int
    {
        $folder = MediaFolder::where('owner_type', $record::class)
            ->where('owner_id', $record->getKey())
            ->first();

        if ($folder) {
            if ($folder->name !== $label) {
                $this->renameFolder($folder, $label);
            }

            return $folder->id;
        }

        $containerId = $this->resolveFolderForCollection($collection);

        return $this->createFolder($containerId, $label, Auth::id(), $record::class, $record->getKey())->id;
    }

    /**
     * Appends " (2)", " (3)", etc. when a file with the same displayed name
     * already exists in the destination folder, so the library never shows
     * multiple entries under the exact same name (e.g. many "stock-1.jpg"
     * uploads from different articles all landing in one place).
     */
    public function uniqueFilenameInFolder(string $name, ?int $folderId): string
    {
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $base = $extension ? substr($name, 0, -(strlen($extension) + 1)) : $name;
        $candidate = $name;
        $i = 2;

        while (Media::where('folder_id', $folderId)->where('original_filename', $candidate)->exists()) {
            $candidate = $extension ? "{$base} ({$i}).{$extension}" : "{$base} ({$i})";
            $i++;
        }

        return $candidate;
    }

    public function createFolder(?int $parentId, string $name, ?int $userId, ?string $ownerType = null, ?int $ownerId = null): MediaFolder
    {
        return MediaFolder::create([
            'parent_id' => $parentId,
            'name' => $name,
            'slug' => $this->uniqueSlug($name, $parentId),
            'created_by' => $userId,
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
        ]);
    }

    public function renameFolder(MediaFolder $folder, string $name): void
    {
        $folder->update([
            'name' => $name,
            'slug' => $this->uniqueSlug($name, $folder->parent_id, $folder->id),
        ]);
    }

    public function moveFolder(MediaFolder $folder, ?int $newParentId): void
    {
        if ($newParentId === $folder->id) {
            throw new RuntimeException('មិនអាចផ្លាស់ទីថតទៅក្នុងខ្លួនវាបានទេ។');
        }

        if ($newParentId !== null && in_array($newParentId, $folder->descendantIds(), true)) {
            throw new RuntimeException('មិនអាចផ្លាស់ទីថតទៅក្នុងថតរងរបស់វាបានទេ។');
        }

        $folder->update([
            'parent_id' => $newParentId,
            'slug' => $this->uniqueSlug($folder->name, $newParentId, $folder->id),
        ]);
    }

    public function moveMedia(Media $media, ?int $folderId): void
    {
        $media->update(['folder_id' => $folderId]);
    }

    public function folderHasContents(MediaFolder $folder): bool
    {
        return $folder->media()->exists() || $folder->children()->exists();
    }

    /**
     * Soft-deletes a folder. A non-empty folder requires $cascadeTrash to
     * confirm the caller wants every descendant folder and media item
     * trashed along with it (soft delete, so restore() can undo it).
     */
    public function deleteFolder(MediaFolder $folder, bool $cascadeTrash = false): void
    {
        if ($this->folderHasContents($folder) && ! $cascadeTrash) {
            throw new RuntimeException('ថតនេះមានរូបភាព ឬថតរងនៅខាងក្នុង។');
        }

        foreach ($folder->children as $child) {
            $this->deleteFolder($child, true);
        }

        $folder->media()->get()->each(fn (Media $media) => $media->delete());
        $folder->delete();
    }

    /**
     * Restores a folder and cascades to every descendant folder and media
     * item that was trashed alongside it, mirroring deleteFolder()'s
     * cascade so a restored folder comes back with its contents intact.
     */
    public function restoreFolder(MediaFolder $folder): void
    {
        $folder->restore();

        foreach (MediaFolder::onlyTrashed()->where('parent_id', $folder->id)->get() as $child) {
            $this->restoreFolder($child);
        }

        Media::onlyTrashed()->where('folder_id', $folder->id)->get()
            ->each(fn (Media $media) => $media->restore());
    }

    public function restoreMedia(Media $media): void
    {
        $media->restore();
    }

    /**
     * Copies a media file's disk content under a new UUID name and inserts
     * a new Media row pointing at it, so the copy is fully independent
     * (rename/delete/move on one never touches the other).
     */
    public function duplicateMediaInPlace(Media $media): Media
    {
        return $this->duplicateMedia($media, $media->folder_id);
    }

    public function duplicateMedia(Media $media, ?int $folderId): Media
    {
        $extension = pathinfo($media->disk_path, PATHINFO_EXTENSION);
        $newPath = 'library/' . Str::uuid()->toString() . ($extension ? '.' . $extension : '');
        Storage::disk('public')->copy($media->disk_path, $newPath);

        return Media::create([
            'folder_id' => $folderId,
            'original_filename' => $this->copyLabel($media->original_filename),
            'disk_path' => $newPath,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'width' => $media->width,
            'height' => $media->height,
            'alt_text' => $media->alt_text,
            'caption' => $media->caption,
            'collection' => $media->collection,
            'uploaded_by' => Auth::id(),
        ]);
    }

    /**
     * Recursively duplicates a folder along with its media and
     * sub-folders into (optionally) a different parent.
     */
    public function duplicateFolderInPlace(MediaFolder $folder): MediaFolder
    {
        return $this->duplicateFolder($folder, $folder->parent_id);
    }

    public function duplicateFolder(MediaFolder $folder, ?int $newParentId): MediaFolder
    {
        $copy = $this->createFolder($newParentId, $this->copyLabel($folder->name), Auth::id());

        foreach ($folder->media as $media) {
            $this->duplicateMedia($media, $copy->id);
        }

        foreach ($folder->children as $child) {
            $this->duplicateFolder($child, $copy->id);
        }

        return $copy;
    }

    public function renameMedia(Media $media, string $name): void
    {
        $media->update(['original_filename' => $name]);
    }

    /**
     * Permanently removes a soft-deleted media row and its file. Only meant to be called from
     * the Trash view on already-trashed items. A trashed item can still be referenced (it was
     * either force-trashed past the same warning deleteMedia() shows, or something started
     * referencing it after it was trashed), so this re-checks and blocks the same way, unless
     * the caller passes $force after showing that warning to the user — the last safety net
     * before a file is gone for good.
     */
    public function permanentlyDeleteMedia(Media $media, bool $force = false): void
    {
        $references = $this->findMediaReferences($media);

        if ($references && ! $force) {
            throw new RuntimeException('ឯកសារនេះកំពុងត្រូវបានប្រើប្រាស់');
        }

        MediaUploader::delete($media->disk_path);
        $media->forceDelete();
    }

    /**
     * Permanently removes a soft-deleted folder and, recursively, every trashed descendant
     * folder/media beneath it. Every descendant media item is reference-checked BEFORE anything
     * is deleted (never partway through the cascade), so a folder holding even one still-used
     * file is refused outright rather than left half-deleted; $force skips the check, exactly
     * like permanentlyDeleteMedia()'s own $force, for a caller that already showed the warning.
     */
    public function permanentlyDeleteFolder(MediaFolder $folder, bool $force = false): void
    {
        if (! $force) {
            $blocked = $this->collectFolderMediaRecursively($folder)
                ->filter(fn (Media $media) => $this->findMediaReferences($media))
                ->count();

            if ($blocked > 0) {
                throw new RuntimeException("ថតនេះមានឯកសារ {$blocked} កំពុងត្រូវបានប្រើប្រាស់នៅកន្លែងផ្សេង។");
            }
        }

        foreach (MediaFolder::onlyTrashed()->where('parent_id', $folder->id)->get() as $child) {
            $this->permanentlyDeleteFolder($child, force: true);
        }

        Media::onlyTrashed()->where('folder_id', $folder->id)->get()
            ->each(fn (Media $media) => $this->permanentlyDeleteMedia($media, force: true));

        $folder->forceDelete();
    }

    /** Every trashed media item inside $folder or any of its trashed descendant folders. */
    private function collectFolderMediaRecursively(MediaFolder $folder): \Illuminate\Support\Collection
    {
        $media = Media::onlyTrashed()->where('folder_id', $folder->id)->get();

        foreach (MediaFolder::onlyTrashed()->where('parent_id', $folder->id)->get() as $child) {
            $media = $media->merge($this->collectFolderMediaRecursively($child));
        }

        return $media;
    }

    /**
     * Real, non-fake storage stats for the sidebar usage card: total bytes
     * used by non-trashed media, plus the actual free/total space of the
     * disk the storage lives on (no invented quota).
     */
    public function storageUsage(): array
    {
        $used = (int) Media::sum('size');
        $path = Storage::disk('public')->path('');
        $total = @disk_total_space($path) ?: null;
        $free = @disk_free_space($path) ?: null;

        return [
            'used' => $used,
            'disk_total' => $total,
            'disk_free' => $free,
        ];
    }

    private function copyLabel(string $name): string
    {
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $base = $extension ? substr($name, 0, -(strlen($extension) + 1)) : $name;

        return $extension ? "{$base} (ចម្លង).{$extension}" : "{$base} (ចម្លង)";
    }

    /**
     * Best-effort scan of every place a media file's stored path could be referenced. These
     * tables hold plain path strings rather than a media_id foreign key (aside from
     * content_images/news_images), so this matches on disk_path — reliable since every
     * reference is populated through the same MediaUploader/media-library flow.
     *
     * Each match also carries up to 5 {title, url} links to the record's own admin edit page
     * (per spec: "each reference can link to its Admin edit page if route is available"). A
     * reference type with no per-record admin route (facility gallery photos and settings link
     * to their PARENT facility/settings page instead) still degrades to a plain label+count
     * rather than a guessed/broken link.
     */
    public function findMediaReferences(Media $media): array
    {
        $path = $media->disk_path;
        $references = [];

        $checks = [
            'ស្លាយក្បាល (Hero Slider)' => fn () => HeroSlide::where('image', $path)->get()
                ->map(fn (HeroSlide $s) => ['title' => $s->title_km ?: ('#'.$s->id), 'url' => route('admin.hero-slides.edit', $s)]),
            'មូលដ្ឋានសុខាភិបាល (រូបសញ្ញា/គម្រប)' => fn () => Facility::where('logo', $path)->orWhere('cover_image', $path)->get()
                ->map(fn (Facility $f) => ['title' => $f->name_km, 'url' => route('admin.facilities.edit', $f)]),
            'វិចិត្រសាលមូលដ្ឋាន' => fn () => FacilityGallery::with('facility')->where('image', $path)->get()
                ->map(fn (FacilityGallery $g) => [
                    'title' => ($g->facility?->name_km ?? 'មូលដ្ឋាន #'.$g->facility_id).' (វិចិត្រសាល)',
                    'url' => $g->facility ? route('admin.facilities.edit', $g->facility) : null,
                ]),
            'អង្គការដៃគូ' => fn () => Partner::where('logo', $path)->get()
                ->map(fn (Partner $p) => ['title' => $p->name, 'url' => route('admin.partners.edit', $p)]),
            'ព័ត៌មាន និងសកម្មភាព' => fn () => News::where('featured_media_id', $media->id)->orWhere('featured_image', $path)->get()
                ->map(fn (News $n) => ['title' => $n->title_km, 'url' => route('admin.news.edit', $n)]),
            'វិចិត្រសាលព័ត៌មាន' => fn () => NewsImage::with('news')->where('media_id', $media->id)->orWhere('image', $path)->get()
                ->map(fn (NewsImage $i) => [
                    'title' => ($i->news?->title_km ?? 'អត្ថបទ #'.$i->news_id).' (វិចិត្រសាល)',
                    'url' => $i->news ? route('admin.news.edit', $i->news) : null,
                ]),
            'មាតិកាអប់រំសុខភាព' => fn () => HealthContent::where('featured_image', $path)->get()
                ->map(fn (HealthContent $h) => ['title' => $h->title_km, 'url' => route('admin.health-content.edit', $h)]),
            'លិខិតបទដ្ឋានគតិយុត្ត' => fn () => LegalDocument::where('file_path', $path)->get()
                ->map(fn (LegalDocument $l) => ['title' => $l->title, 'url' => route('admin.legal-documents.edit', $l)]),
            'អត្ថបទ (Content)' => fn () => Content::where('featured_image', $path)->get()
                ->map(fn (Content $c) => ['title' => $c->title_km, 'url' => route('admin.content.edit', $c)]),
            'វិចិត្រសាលអត្ថបទ' => fn () => ContentImage::with('content')->where('media_id', $media->id)->orWhere('image', $path)->get()
                ->map(fn (ContentImage $i) => [
                    'title' => ($i->content?->title_km ?? 'អត្ថបទ #'.$i->content_id).' (វិចិត្រសាល)',
                    'url' => $i->content ? route('admin.content.edit', $i->content) : null,
                ]),
            'ការកំណត់ប្រព័ន្ធ (Logo/Favicon)' => fn () => Setting::where('value', $path)->get()
                ->map(fn (Setting $s) => ['title' => $s->key, 'url' => route('admin.settings.edit')]),
        ];

        foreach ($checks as $label => $check) {
            $items = $check();
            if ($items->isNotEmpty()) {
                $references[] = ['label' => $label, 'count' => $items->count(), 'links' => $items->take(5)->values()->all()];
            }
        }

        return $references;
    }

    /**
     * Soft-deletes a media item. Throws if it is still referenced anywhere
     * on the site, unless the caller passes $force after showing the
     * warning to the user.
     */
    public function deleteMedia(Media $media, bool $force = false): void
    {
        $references = $this->findMediaReferences($media);

        if ($references && ! $force) {
            throw new RuntimeException('ឯកសារនេះកំពុងត្រូវបានប្រើប្រាស់');
        }

        $media->delete();
    }

    /**
     * Bulk "is this media referenced anywhere" check for a whole page of items at once — powers
     * the list view's Usage column and the Used/Unused filter. Unlike findMediaReferences()
     * (a full per-reference-type breakdown for exactly ONE selected file, safe to run on
     * demand), this runs a small FIXED number of queries per reference table regardless of how
     * many media items are being checked — never one query per item per table, which would be
     * 24 items × 11 tables = 264 queries on a single index page load.
     *
     * Returns the set of used media ids from $mediaItems, as [id => true].
     */
    public function usedMediaIds(\Illuminate\Support\Collection $mediaItems): array
    {
        if ($mediaItems->isEmpty()) {
            return [];
        }

        $idsByPath = $mediaItems->pluck('id', 'disk_path');
        $paths = $idsByPath->keys()->all();
        $ids = $mediaItems->pluck('id')->all();
        $used = [];

        $markByPath = function (array $matchedPaths) use (&$used, $idsByPath): void {
            foreach ($matchedPaths as $path) {
                if (isset($idsByPath[$path])) {
                    $used[$idsByPath[$path]] = true;
                }
            }
        };
        $markByMediaId = function (array $matchedIds) use (&$used): void {
            foreach ($matchedIds as $id) {
                $used[$id] = true;
            }
        };

        $markByPath(HeroSlide::whereIn('image', $paths)->pluck('image')->all());
        $markByPath(Facility::whereIn('logo', $paths)->pluck('logo')->all());
        $markByPath(Facility::whereIn('cover_image', $paths)->pluck('cover_image')->all());
        $markByPath(FacilityGallery::whereIn('image', $paths)->pluck('image')->all());
        $markByPath(Partner::whereIn('logo', $paths)->pluck('logo')->all());
        $markByMediaId(News::whereIn('featured_media_id', $ids)->pluck('featured_media_id')->all());
        $markByPath(News::whereIn('featured_image', $paths)->pluck('featured_image')->all());
        $markByMediaId(NewsImage::whereIn('media_id', $ids)->pluck('media_id')->all());
        $markByPath(NewsImage::whereIn('image', $paths)->pluck('image')->all());
        $markByPath(HealthContent::whereIn('featured_image', $paths)->pluck('featured_image')->all());
        $markByPath(LegalDocument::whereIn('file_path', $paths)->pluck('file_path')->all());
        $markByPath(Content::whereIn('featured_image', $paths)->pluck('featured_image')->all());
        $markByMediaId(ContentImage::whereIn('media_id', $ids)->pluck('media_id')->all());
        $markByPath(ContentImage::whereIn('image', $paths)->pluck('image')->all());
        $markByPath(Setting::whereIn('value', $paths)->pluck('value')->all());

        return $used;
    }

    private function uniqueSlug(string $name, ?int $parentId, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'folder';
        $slug = $base;
        $i = 2;

        while (
            MediaFolder::withTrashed()
                ->where('parent_id', $parentId)
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
