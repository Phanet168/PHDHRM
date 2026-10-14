<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Content;
use App\Models\ContentImage;
use App\Models\Media;
use App\Services\MediaManagerService;
use App\Services\MediaUploader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContentImageController extends Controller
{
    public function store(Request $request, Content $content): RedirectResponse
    {
        $this->authorize('manageGallery', $content);

        $request->validate([
            'images' => ['required', 'array'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $nextOrder = $content->images()->count();
        $folderId = app(MediaManagerService::class)->resolveFolderForRecord('content', $content, "#{$content->id} — {$content->title_km}");

        foreach ($request->file('images') as $file) {
            $path = MediaUploader::store($file, 'content/gallery', MediaUploader::IMAGE_ONLY_EXTENSIONS, MediaUploader::IMAGE_ONLY_MIMES, $folderId);

            $content->images()->create([
                'image' => $path,
                'sort_order' => $nextOrder++,
            ]);
        }

        AuditLog::record('updated', 'Content', $content->id, "បន្ថែមរូបភាពវិចិត្រសាល៖ {$content->title_km}");

        return back()->with('status', 'បានបន្ថែមរូបភាពដោយជោគជ័យ។');
    }

    public function attachFromMedia(Request $request, Content $content): RedirectResponse
    {
        $this->authorize('manageGallery', $content);

        $request->validate([
            'media_ids' => ['required', 'array'],
            'media_ids.*' => ['integer', 'exists:media,id'],
        ]);

        $nextOrder = $content->images()->count();

        foreach (Media::whereIn('id', $request->input('media_ids'))->get() as $media) {
            $content->images()->create([
                'media_id' => $media->id,
                'image' => $media->disk_path,
                'sort_order' => $nextOrder++,
            ]);
        }

        AuditLog::record('updated', 'Content', $content->id, "បន្ថែមរូបភាពពី Media Library៖ {$content->title_km}");

        return back()->with('status', 'បានបន្ថែមរូបភាពពី Media Library ដោយជោគជ័យ។');
    }

    public function update(Request $request, Content $content, ContentImage $image): RedirectResponse
    {
        $this->authorize('manageGallery', $content);
        abort_unless($image->content_id === $content->id, 404);

        $data = $request->validate([
            'caption' => ['nullable', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ]);

        $image->update($data);

        return back()->with('status', 'បានធ្វើបច្ចុប្បន្នភាពរូបភាពដោយជោគជ័យ។');
    }

    public function setFeatured(Content $content, ContentImage $image): RedirectResponse
    {
        $this->authorize('manageGallery', $content);
        abort_unless($image->content_id === $content->id, 404);

        $content->images()->update(['is_featured' => false]);
        $image->update(['is_featured' => true]);

        return back()->with('status', 'បានកំណត់ជារូបភាពគម្របដោយជោគជ័យ។');
    }

    public function destroy(Content $content, ContentImage $image): RedirectResponse
    {
        $this->authorize('manageGallery', $content);
        abort_unless($image->content_id === $content->id, 404);

        if (! $image->media_id) {
            MediaUploader::delete($image->image);
        }
        $image->delete();

        return back()->with('status', 'បានលុបរូបភាពដោយជោគជ័យ។');
    }

    public function reorder(Request $request, Content $content): JsonResponse
    {
        $this->authorize('manageGallery', $content);

        $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:content_images,id'],
            'items.*.sort_order' => ['required', 'integer'],
        ]);

        foreach ($request->input('items') as $row) {
            $image = ContentImage::find($row['id']);
            if ($image && $image->content_id === $content->id) {
                $image->update(['sort_order' => $row['sort_order']]);
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
