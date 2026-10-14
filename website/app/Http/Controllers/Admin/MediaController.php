<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Media;
use App\Models\MediaFolder;
use App\Services\MediaManagerService;
use App\Services\MediaUploader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;

class MediaController extends Controller
{
    public function index(Request $request, MediaManagerService $manager): View
    {
        $this->authorize('viewAny', Media::class);

        $folderId = $request->filled('folder') ? (int) $request->input('folder') : null;
        $currentFolder = $folderId ? MediaFolder::findOrFail($folderId) : null;
        $sort = $request->input('sort', 'name_asc');

        $foldersQuery = MediaFolder::where('parent_id', $folderId)->with('creator')->withCount(['media', 'children']);
        $query = Media::with(['uploader', 'folder']);

        if ($request->boolean('include_subfolders') && $currentFolder) {
            $query->whereIn('folder_id', $currentFolder->descendantIds());
        } else {
            $query->where('folder_id', $folderId);
        }

        if ($request->filled('q')) {
            $search = $request->input('q');
            $foldersQuery->where('name', 'like', "%{$search}%");
            $query->where(function ($q) use ($search) {
                $q->where('original_filename', 'like', "%{$search}%")
                    ->orWhere('alt_text', 'like', "%{$search}%")
                    ->orWhere('caption', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type')) {
            match ($request->input('type')) {
                'image' => $query->where('mime_type', 'like', 'image/%'),
                'pdf' => $query->where('mime_type', 'application/pdf'),
                'video' => $query->where('mime_type', 'like', 'video/%'),
                'document' => $query->whereIn('mime_type', [
                    'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ]),
                default => null,
            };
        }

        [$foldersQuery, $query] = $this->applySort($foldersQuery, $query, $sort);

        $folders = $foldersQuery->get();

        // "Used"/"Unused" (spec §22) isn't a column any query can filter by directly — it's
        // computed by cross-checking every other module's tables (see usedMediaIds()). Only pay
        // for that scan, and only fall back to collection-level pagination, when this filter is
        // actually requested; the common case (no usage filter) stays a normal DB pagination.
        $usage = in_array($request->input('usage'), ['used', 'unused'], true) ? $request->input('usage') : null;

        if ($usage) {
            $all = $query->get();
            $usedIds = $manager->usedMediaIds($all);
            $filtered = $usage === 'used'
                ? $all->filter(fn (Media $m) => isset($usedIds[$m->id]))
                : $all->filter(fn (Media $m) => ! isset($usedIds[$m->id]));

            $page = (int) $request->input('page', 1);
            $perPage = 24;
            $media = new \Illuminate\Pagination\LengthAwarePaginator(
                $filtered->slice(($page - 1) * $perPage, $perPage)->values(),
                $filtered->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            $media = $query->paginate(24)->withQueryString();
            $usedIds = $manager->usedMediaIds($media->getCollection());
        }

        $folderTree = MediaFolder::whereNull('parent_id')->with('allChildren')->orderBy('name')->get();
        $breadcrumb = $currentFolder?->breadcrumbTrail() ?? [];
        $view = $request->input('view', 'grid') === 'list' ? 'list' : 'grid';
        $storage = $manager->storageUsage();

        return view('admin.media.index', compact(
            'folders', 'media', 'folderTree', 'currentFolder', 'breadcrumb', 'view', 'sort', 'storage', 'usage', 'usedIds'
        ));
    }

    public function trash(Request $request): View
    {
        $this->authorize('viewAny', Media::class);

        $folders = MediaFolder::onlyTrashed()->with('parent')->latest('deleted_at')->get();
        $media = Media::onlyTrashed()->with(['uploader', 'folder'])->latest('deleted_at')->paginate(24);

        return view('admin.media.trash', compact('folders', 'media'));
    }

    private function applySort($foldersQuery, $mediaQuery, string $sort): array
    {
        switch ($sort) {
            case 'name_desc':
                $foldersQuery->orderByDesc('name');
                $mediaQuery->orderByDesc('original_filename');
                break;
            case 'oldest':
                $foldersQuery->orderBy('created_at');
                $mediaQuery->orderBy('created_at');
                break;
            case 'size_desc':
                $foldersQuery->orderBy('name');
                $mediaQuery->orderByDesc('size');
                break;
            case 'latest':
                $foldersQuery->orderByDesc('created_at');
                $mediaQuery->orderByDesc('created_at');
                break;
            default:
                $foldersQuery->orderBy('name');
                $mediaQuery->orderBy('original_filename');
        }

        return [$foldersQuery, $mediaQuery];
    }

    public function store(Request $request, MediaManagerService $manager): RedirectResponse
    {
        $this->authorize('create', Media::class);

        $request->validate([
            'file' => ['required_without:files', 'file', 'max:51200'],
            'files' => ['required_without:file', 'array'],
            'files.*' => ['file', 'max:51200'],
            'folder_id' => ['nullable', 'integer', 'exists:media_folders,id'],
        ]);

        $files = $request->hasFile('files') ? $request->file('files') : [$request->file('file')];
        $folderId = $request->input('folder_id') ? (int) $request->input('folder_id') : null;
        $uploaded = 0;

        foreach (array_filter($files) as $file) {
            try {
                $path = MediaUploader::store($file, 'library', MediaUploader::LIBRARY_EXTENSIONS, MediaUploader::LIBRARY_MIMES);
                $dimensions = null;
                if (str_starts_with($file->getMimeType(), 'image/')) {
                    $dimensions = @getimagesize($file->getRealPath());
                }

                // MediaUploader::store() already gave the file a unique display
                // name, but it checked uniqueness against the root folder since
                // "library" uploads pick their real destination here — redo the
                // check now that the file is actually moving into $folderId.
                $medium = Media::where('disk_path', $path)->first();
                $name = $manager->uniqueFilenameInFolder($medium->original_filename, $folderId);

                $medium->update([
                    'folder_id' => $folderId,
                    'original_filename' => $name,
                    'width' => $dimensions[0] ?? null,
                    'height' => $dimensions[1] ?? null,
                ]);
                $uploaded++;
            } catch (RuntimeException $e) {
                return back()->withErrors(['file' => $e->getMessage()]);
            }
        }

        AuditLog::record('created', 'Media', null, "បានបញ្ចូលឯកសារថ្មី {$uploaded} ទៅឃ្លាំងមេឌា");

        return back()->with('status', 'បានបញ្ចូលឯកសារដោយជោគជ័យ។');
    }

    public function update(Request $request, Media $medium): RedirectResponse
    {
        $this->authorize('update', $medium);

        $data = $request->validate([
            'alt_text' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:1000'],
        ]);

        $medium->update($data);

        return back()->with('status', 'បានធ្វើបច្ចុប្បន្នភាពព័ត៌មានឯកសារដោយជោគជ័យ។');
    }

    public function rename(Request $request, Media $medium, MediaManagerService $manager): RedirectResponse
    {
        $this->authorize('update', $medium);

        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $manager->renameMedia($medium, $data['name']);

        AuditLog::record('updated', 'Media', $medium->id, "ប្តូរឈ្មោះឯកសារ៖ {$medium->original_filename}");

        return back()->with('status', 'បានប្តូរឈ្មោះឯកសារដោយជោគជ័យ។');
    }

    public function duplicate(Media $medium, MediaManagerService $manager): RedirectResponse
    {
        $this->authorize('copy', $medium);

        $copy = $manager->duplicateMediaInPlace($medium);

        AuditLog::record('created', 'Media', $copy->id, "ចម្លងឯកសារ៖ {$medium->original_filename}");

        return back()->with('status', 'បានចម្លងឯកសារដោយជោគជ័យ។');
    }

    public function replace(Request $request, Media $medium): RedirectResponse
    {
        $this->authorize('update', $medium);

        $request->validate(['file' => ['required', 'file', 'max:51200']]);
        $file = $request->file('file');

        if (! Storage::disk('public')->exists($medium->disk_path)) {
            return back()->withErrors(['file' => 'រកមិនឃើញឯកសារដើមទេ។']);
        }

        // Overwrite the file at the exact same disk path so every existing
        // reference (facility images, hero slides, article galleries, etc.)
        // keeps pointing at a valid, updated file with no broken links.
        Storage::disk('public')->put($medium->disk_path, file_get_contents($file->getRealPath()));

        $dimensions = str_starts_with($file->getMimeType(), 'image/') ? @getimagesize($file->getRealPath()) : null;

        $medium->update([
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'width' => $dimensions[0] ?? null,
            'height' => $dimensions[1] ?? null,
        ]);

        AuditLog::record('updated', 'Media', $medium->id, "ជំនួសឯកសារ៖ {$medium->original_filename}");

        return back()->with('status', 'បានជំនួសឯកសារដោយជោគជ័យ។');
    }

    public function move(Request $request, Media $medium, MediaManagerService $manager): RedirectResponse
    {
        $this->authorize('move', $medium);

        $request->validate(['folder_id' => ['nullable', 'integer', 'exists:media_folders,id']]);

        $manager->moveMedia($medium, $request->input('folder_id') ? (int) $request->input('folder_id') : null);

        return back()->with('status', 'បានផ្លាស់ទីឯកសារដោយជោគជ័យ។');
    }

    /**
     * Drag-and-drop move for a single item (either a media file or a
     * folder), used by the Explorer-style JS so a drop doesn't navigate
     * away from the current view.
     */
    public function moveItem(Request $request, MediaManagerService $manager): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:media,folder'],
            'id' => ['required', 'integer'],
            'folder_id' => ['nullable', 'integer', 'exists:media_folders,id'],
        ]);

        $targetFolderId = $data['folder_id'] ?? null;

        try {
            if ($data['type'] === 'media') {
                $medium = Media::findOrFail($data['id']);
                $this->authorize('move', $medium);
                $manager->moveMedia($medium, $targetFolderId);
            } else {
                $folder = MediaFolder::findOrFail($data['id']);
                $this->authorize('move', $folder);
                $manager->moveFolder($folder, $targetFolderId);
            }
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Bulk move/delete/duplicate for a multi-selected set of media + folder
     * items. Backs the toolbar's Cut+Paste (move), Copy+Paste (duplicate),
     * and Delete actions, plus the drag-and-drop multi-item drop.
     */
    public function bulkAction(Request $request, MediaManagerService $manager): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:move,delete,duplicate'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.type' => ['required', 'in:media,folder'],
            'items.*.id' => ['required', 'integer'],
            'folder_id' => ['nullable', 'integer', 'exists:media_folders,id'],
            'force' => ['nullable', 'boolean'],
        ]);

        $succeeded = 0;
        $blocked = [];

        foreach ($data['items'] as $item) {
            try {
                if ($item['type'] === 'media') {
                    $medium = Media::find($item['id']);
                    if (! $medium) continue;

                    match ($data['action']) {
                        'move' => $this->authorizeThen('move', $medium, fn () => $manager->moveMedia($medium, $data['folder_id'] ?? null)),
                        'duplicate' => $this->authorizeThen('copy', $medium, fn () => $manager->duplicateMedia($medium, $data['folder_id'] ?? null)),
                        'delete' => $this->authorizeThen('delete', $medium, fn () => $manager->deleteMedia($medium, (bool) ($data['force'] ?? false))),
                    };
                } else {
                    $folder = MediaFolder::find($item['id']);
                    if (! $folder) continue;

                    match ($data['action']) {
                        'move' => $this->authorizeThen('move', $folder, fn () => $manager->moveFolder($folder, $data['folder_id'] ?? null)),
                        'duplicate' => $this->authorizeThen('copy', $folder, fn () => $manager->duplicateFolder($folder, $data['folder_id'] ?? null)),
                        'delete' => $this->authorizeThen('delete', $folder, fn () => $manager->deleteFolder($folder, (bool) ($data['force'] ?? false))),
                    };
                }
                $succeeded++;
            } catch (RuntimeException $e) {
                $blocked[] = ['type' => $item['type'], 'id' => $item['id'], 'message' => $e->getMessage()];
            } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
                $blocked[] = ['type' => $item['type'], 'id' => $item['id'], 'message' => 'អ្នកមិនមានសិទ្ធិសម្រាប់ធាតុនេះទេ។'];
            }
        }

        if ($succeeded) {
            $label = match ($data['action']) {
                'move' => 'ផ្លាស់ទី', 'duplicate' => 'ចម្លង', 'delete' => 'លុប',
            };
            AuditLog::record($data['action'] === 'delete' ? 'deleted' : 'updated', 'Media', null, "សកម្មភាព{$label}ជាបណ្តុំលើឯកសារ/ថត {$succeeded} ក្នុងឃ្លាំងមេឌា");
        }

        return response()->json(['succeeded' => $succeeded, 'blocked' => $blocked]);
    }

    private function authorizeThen(string $ability, $subject, callable $callback): void
    {
        $this->authorize($ability, $subject);
        $callback();
    }

    public function references(Media $medium, MediaManagerService $manager): JsonResponse
    {
        $this->authorize('delete', $medium);

        return response()->json(['references' => $manager->findMediaReferences($medium)]);
    }

    /** Latest Library images, for lightweight "insert image" pickers (e.g. the rich text editor's toolbar). */
    public function picker(): JsonResponse
    {
        $this->authorize('viewAny', Media::class);

        $items = Media::where('mime_type', 'like', 'image/%')->latest()->take(60)
            ->get(['id', 'original_filename', 'disk_path'])
            ->map(fn (Media $media) => ['id' => $media->id, 'name' => $media->original_filename, 'url' => $media->url]);

        return response()->json($items);
    }

    public function destroy(Request $request, Media $medium, MediaManagerService $manager): RedirectResponse
    {
        $this->authorize('delete', $medium);

        try {
            $manager->deleteMedia($medium, $request->boolean('force'));
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()])
                ->with('confirm_delete_media', $medium->id)
                ->with('media_references', $manager->findMediaReferences($medium));
        }

        AuditLog::record('deleted', 'Media', $medium->id, "លុបឯកសារ៖ {$medium->original_filename}");

        return back()->with('status', 'បានទុកឯកសារក្នុងធុងសម្រាមដោយជោគជ័យ។');
    }

    public function restore(Media $medium, MediaManagerService $manager): RedirectResponse
    {
        $this->authorize('restore', $medium);

        $manager->restoreMedia($medium);

        AuditLog::record('updated', 'Media', $medium->id, "សង្គ្រោះឯកសារ៖ {$medium->original_filename}");

        return back()->with('status', 'បានសង្គ្រោះឯកសារដោយជោគជ័យ។');
    }

    public function forceDestroy(Request $request, Media $medium, MediaManagerService $manager): RedirectResponse
    {
        $this->authorize('forceDelete', $medium);

        try {
            $manager->permanentlyDeleteMedia($medium, $request->boolean('force'));
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()])
                ->with('confirm_permanent_delete_media', $medium->id)
                ->with('media_references', $manager->findMediaReferences($medium));
        }

        AuditLog::record('deleted', 'Media', null, "លុបឯកសារជាអចិន្ត្រៃយ៍៖ {$medium->original_filename}");

        return back()->with('status', 'បានលុបឯកសារជាអចិន្ត្រៃយ៍ដោយជោគជ័យ។');
    }

    /**
     * Wipes the whole Trash unconditionally: unlike a single permanent delete, this already
     * carries its own strong "cannot be undone" confirmation in the UI, so every item goes with
     * $force — an admin who reaches for "empty trash" means to clear it out completely, not be
     * blocked partway through by a handful of still-referenced items.
     */
    public function emptyTrash(MediaManagerService $manager): RedirectResponse
    {
        $trashedFolders = MediaFolder::onlyTrashed()->get();
        $trashedIds = $trashedFolders->pluck('id')->all();

        // Only process a trashed folder that isn't nested under another
        // trashed folder: its own permanentlyDeleteFolder() call cascades
        // to every trashed descendant, so processing those separately too
        // would just re-query rows the cascade already force-deleted.
        foreach ($trashedFolders as $folder) {
            if ($folder->parent_id && in_array($folder->parent_id, $trashedIds, true)) {
                continue;
            }
            $this->authorize('forceDelete', $folder);
            $manager->permanentlyDeleteFolder($folder, force: true);
        }

        foreach (Media::onlyTrashed()->get() as $medium) {
            $this->authorize('forceDelete', $medium);
            $manager->permanentlyDeleteMedia($medium, force: true);
        }

        AuditLog::record('deleted', 'Media', null, 'សម្អាតធុងសម្រាមឃ្លាំងមេឌាទាំងអស់');

        return back()->with('status', 'បានសម្អាតធុងសម្រាមដោយជោគជ័យ។');
    }
}
