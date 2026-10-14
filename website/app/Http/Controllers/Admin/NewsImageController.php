<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Media;
use App\Models\News;
use App\Models\NewsImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NewsImageController extends Controller
{
    /** Attach existing Media Library images to the gallery — no upload, no duplicate files. */
    public function attachFromMedia(Request $request, News $news): RedirectResponse
    {
        $this->authorize('update', $news);

        $request->validate([
            'media_ids' => ['required', 'array'],
            'media_ids.*' => ['integer', 'exists:media,id'],
        ]);

        $sortOrder = ((int) $news->images()->max('sort_order')) + 1;
        $alreadyAttached = $news->images()->pluck('media_id')->filter()->all();

        foreach (Media::whereIn('id', $request->input('media_ids'))->get() as $media) {
            if (! $media->isImage() || in_array($media->id, $alreadyAttached, true)) {
                continue;
            }

            $news->images()->create([
                'media_id' => $media->id,
                'image' => $media->disk_path,
                'sort_order' => $sortOrder++,
            ]);
            $alreadyAttached[] = $media->id;
        }

        AuditLog::record('updated', 'News', $news->id, "បន្ថែមរូបភាពពី Media Library៖ {$news->title_km}");

        return back()->with('status', 'បានបន្ថែមរូបភាពដោយជោគជ័យ។');
    }

    /** Rename the caption/alt text of a gallery image — never touches the Media record itself. */
    public function update(Request $request, News $news, NewsImage $image): RedirectResponse
    {
        $this->authorize('update', $news);
        abort_unless($image->news_id === $news->id, 404);

        $data = $request->validate([
            'caption' => ['nullable', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ]);
        $image->update($data);

        return back()->with('status', 'បានធ្វើបច្ចុប្បន្នភាពដោយជោគជ័យ។');
    }

    /**
     * Removes an image from this article's gallery only. The Media Library record — and any file
     * it still shares with other articles/pages — is never touched (single source of truth: only
     * the Media Manager itself deletes files).
     */
    public function destroy(News $news, NewsImage $image): RedirectResponse
    {
        $this->authorize('update', $news);
        abort_unless($image->news_id === $news->id, 404);

        $image->delete();

        AuditLog::record('updated', 'News', $news->id, "ដកចេញរូបថតសកម្មភាពពីមាតិកា៖ {$news->title_km}");

        return back()->with('status', 'បានដករូបភាពចេញដោយជោគជ័យ។');
    }

    public function reorder(Request $request, News $news): JsonResponse
    {
        $this->authorize('update', $news);

        $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:news_images,id'],
            'items.*.sort_order' => ['required', 'integer'],
        ]);

        foreach ($request->input('items') as $row) {
            $image = NewsImage::find($row['id']);
            if ($image && $image->news_id === $news->id) {
                $image->update(['sort_order' => $row['sort_order']]);
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
