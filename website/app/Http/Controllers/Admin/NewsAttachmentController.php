<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Media;
use App\Models\News;
use App\Models\NewsAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NewsAttachmentController extends Controller
{
    /** Attach existing Media Library files (PDF/DOC/XLS…) as article attachments — no re-upload. */
    public function attachFromMedia(Request $request, News $news): RedirectResponse
    {
        $this->authorize('update', $news);

        $request->validate([
            'media_ids' => ['required', 'array'],
            'media_ids.*' => ['integer', 'exists:media,id'],
        ]);

        $sortOrder = ((int) $news->attachments()->max('sort_order')) + 1;
        $alreadyAttached = $news->attachments()->pluck('media_id')->all();

        foreach (Media::whereIn('id', $request->input('media_ids'))->get() as $media) {
            if (in_array($media->id, $alreadyAttached, true)) {
                continue;
            }

            $news->attachments()->create(['media_id' => $media->id, 'sort_order' => $sortOrder++]);
            $alreadyAttached[] = $media->id;
        }

        AuditLog::record('updated', 'News', $news->id, "បន្ថែមឯកសារភ្ជាប់ពី Media Library៖ {$news->title_km}");

        return back()->with('status', 'បានបន្ថែមឯកសារដោយជោគជ័យ។');
    }

    /** Rename the attachment's display label — never touches the Media record itself. */
    public function update(Request $request, News $news, NewsAttachment $attachment): RedirectResponse
    {
        $this->authorize('update', $news);
        abort_unless($attachment->news_id === $news->id, 404);

        $data = $request->validate(['label' => ['nullable', 'string', 'max:255']]);
        $attachment->update($data);

        return back()->with('status', 'បានធ្វើបច្ចុប្បន្នភាពដោយជោគជ័យ។');
    }

    /** Detaches the attachment from this article only — the Media Library file is never deleted here. */
    public function destroy(News $news, NewsAttachment $attachment): RedirectResponse
    {
        $this->authorize('update', $news);
        abort_unless($attachment->news_id === $news->id, 404);

        $attachment->delete();

        AuditLog::record('updated', 'News', $news->id, "ដកឯកសារភ្ជាប់ចេញពីមាតិកា៖ {$news->title_km}");

        return back()->with('status', 'បានដកឯកសារចេញដោយជោគជ័យ។');
    }

    public function reorder(Request $request, News $news): JsonResponse
    {
        $this->authorize('update', $news);

        $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:news_attachments,id'],
            'items.*.sort_order' => ['required', 'integer'],
        ]);

        foreach ($request->input('items') as $row) {
            $attachment = NewsAttachment::find($row['id']);
            if ($attachment && $attachment->news_id === $news->id) {
                $attachment->update(['sort_order' => $row['sort_order']]);
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
