<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MediaFolderRequest;
use App\Models\AuditLog;
use App\Models\MediaFolder;
use App\Services\MediaManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class MediaFolderController extends Controller
{
    public function store(MediaFolderRequest $request, MediaManagerService $manager): RedirectResponse
    {
        $folder = $manager->createFolder(
            $request->input('parent_id') ? (int) $request->input('parent_id') : null,
            $request->input('name'),
            auth()->id()
        );

        AuditLog::record('created', 'MediaFolder', $folder->id, "បង្កើតថត៖ {$folder->name}");

        return back()->with('status', 'បានបង្កើតថតដោយជោគជ័យ។')->with('open_folder', $folder->id);
    }

    public function update(MediaFolderRequest $request, MediaFolder $folder, MediaManagerService $manager): RedirectResponse
    {
        $manager->renameFolder($folder, $request->input('name'));

        AuditLog::record('updated', 'MediaFolder', $folder->id, "ប្តូរឈ្មោះថត៖ {$folder->name}");

        return back()->with('status', 'បានប្តូរឈ្មោះថតដោយជោគជ័យ។')->with('open_folder', $folder->id);
    }

    public function move(Request $request, MediaFolder $folder, MediaManagerService $manager): RedirectResponse
    {
        $this->authorize('move', $folder);

        $request->validate(['parent_id' => ['nullable', 'integer', 'exists:media_folders,id']]);

        try {
            $manager->moveFolder($folder, $request->input('parent_id') ? (int) $request->input('parent_id') : null);
        } catch (RuntimeException $e) {
            return back()->withErrors(['parent_id' => $e->getMessage()]);
        }

        AuditLog::record('updated', 'MediaFolder', $folder->id, "ផ្លាស់ទីថត៖ {$folder->name}");

        return back()->with('status', 'បានផ្លាស់ទីថតដោយជោគជ័យ។')->with('open_folder', $folder->parent_id);
    }

    public function destroy(Request $request, MediaFolder $folder, MediaManagerService $manager): RedirectResponse
    {
        $this->authorize('delete', $folder);

        $parentId = $folder->parent_id;

        try {
            $manager->deleteFolder($folder, $request->boolean('cascade'));
        } catch (RuntimeException $e) {
            return back()->withErrors(['folder' => $e->getMessage()])->with('confirm_delete_folder', $folder->id);
        }

        AuditLog::record('deleted', 'MediaFolder', $folder->id, "លុបថត៖ {$folder->name}");

        return back()->with('status', 'បានទុកថតក្នុងធុងសម្រាមដោយជោគជ័យ។')->with('open_folder', $parentId);
    }

    public function restore(MediaFolder $folder, MediaManagerService $manager): RedirectResponse
    {
        $this->authorize('restore', $folder);

        $manager->restoreFolder($folder);

        AuditLog::record('updated', 'MediaFolder', $folder->id, "សង្គ្រោះថត៖ {$folder->name}");

        return back()->with('status', 'បានសង្គ្រោះថតដោយជោគជ័យ។');
    }

    public function duplicate(MediaFolder $folder, MediaManagerService $manager): RedirectResponse
    {
        $this->authorize('copy', $folder);

        $copy = $manager->duplicateFolderInPlace($folder);

        AuditLog::record('created', 'MediaFolder', $copy->id, "ចម្លងថត៖ {$folder->name}");

        return back()->with('status', 'បានចម្លងថតដោយជោគជ័យ។');
    }

    public function forceDestroy(Request $request, MediaFolder $folder, MediaManagerService $manager): RedirectResponse
    {
        $this->authorize('forceDelete', $folder);

        try {
            $manager->permanentlyDeleteFolder($folder, $request->boolean('force'));
        } catch (RuntimeException $e) {
            return back()->withErrors(['folder' => $e->getMessage()])->with('confirm_permanent_delete_folder', $folder->id);
        }

        AuditLog::record('deleted', 'MediaFolder', null, "លុបថតជាអចិន្ត្រៃយ៍៖ {$folder->name}");

        return back()->with('status', 'បានលុបថតជាអចិន្ត្រៃយ៍ដោយជោគជ័យ។');
    }

    public function properties(MediaFolder $folder): JsonResponse
    {
        $this->authorize('viewAny', MediaFolder::class);

        return response()->json([
            'name' => $folder->name,
            'created_at' => $folder->created_at->format('d-m-Y H:i'),
            'created_by' => $folder->creator?->name,
            'media_count' => $folder->media()->count(),
            'folder_count' => $folder->children()->count(),
            'total_size' => $folder->media()->sum('size'),
        ]);
    }
}
