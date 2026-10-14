<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\NewsRequest;
use App\Models\AuditLog;
use App\Models\Facility;
use App\Models\Media;
use App\Models\News;
use App\Models\NewsCategory;
use App\Services\MediaManagerService;
use App\Services\MediaUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NewsController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', News::class);

        // status=trash is a distinct listing (soft-deleted articles), not a value of the status column.
        $isTrash = $request->input('status') === 'trash';
        $query = $isTrash
            ? News::onlyTrashed()->with(['category', 'facility', 'author'])
            : News::with(['category', 'facility', 'author']);

        if (auth()->user()->isFacilityScoped()) {
            $query->where('facility_id', auth()->user()->facility_id);
        }

        if ($request->filled('search')) {
            $query->where('title_km', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }
        if ($request->filled('facility')) {
            $query->where('facility_id', $request->facility);
        }
        if ($request->filled('status') && ! $isTrash) {
            $query->where('status', $request->status);
        }

        [$sort, $direction] = $this->applySort($query, $request, $isTrash);

        $news = $query->paginate(15)->withQueryString();
        $categories = NewsCategory::orderBy('name_km')->get();
        $facilities = Facility::orderBy('name_km')->get();
        $trashedCount = auth()->user()->can('news.restore')
            ? News::onlyTrashed()->when(auth()->user()->isFacilityScoped(), fn ($q) => $q->where('facility_id', auth()->user()->facility_id))->count()
            : 0;

        return view('admin.news.index', compact('news', 'categories', 'facilities', 'isTrash', 'trashedCount', 'sort', 'direction'));
    }

    /**
     * Column-header sort for the index table: `sort` is checked against a whitelist (never used
     * as a raw column/table name), so a tampered request can at worst fall back to the default
     * order, never reach an arbitrary column. Sorting by category/facility joins in that table's
     * own name column, so the admin gets an alphabetical sort instead of one by the numeric
     * foreign key.
     */
    private function applySort($query, Request $request, bool $isTrash): array
    {
        $columns = $isTrash
            ? ['title' => 'news.title_km', 'category' => 'news_categories.name_km', 'facility' => 'facilities.name_km', 'deleted_at' => 'news.deleted_at']
            : ['title' => 'news.title_km', 'category' => 'news_categories.name_km', 'facility' => 'facilities.name_km', 'status' => 'news.status', 'date' => 'news.published_at'];

        $sort = $request->input('sort');
        if (! $sort || ! array_key_exists($sort, $columns)) {
            $sort = $isTrash ? 'deleted_at' : 'date';
        }
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';

        if ($sort === 'category') {
            $query->leftJoin('news_categories', 'news_categories.id', '=', 'news.category_id')->select('news.*');
        } elseif ($sort === 'facility') {
            $query->leftJoin('facilities', 'facilities.id', '=', 'news.facility_id')->select('news.*');
        }

        $query->orderBy($columns[$sort], $direction);
        if ($sort === 'date') {
            // published_at is null for drafts — break ties (and rank undated drafts) by updated_at.
            $query->orderBy('news.updated_at', $direction);
        } else {
            $query->orderBy('news.id', $direction);
        }

        return [$sort, $direction];
    }

    public function create(): View
    {
        $this->authorize('create', News::class);

        $categories = NewsCategory::orderBy('name_km')->get();
        $facilities = auth()->user()->isFacilityScoped()
            ? Facility::where('id', auth()->user()->facility_id)->get()
            : Facility::orderBy('name_km')->get();

        return view('admin.news.create', compact('categories', 'facilities'));
    }

    public function store(NewsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        unset(
            $data['featured_image'], $data['featured_media_id'], $data['remove_featured_image'],
            $data['images'], $data['gallery_media_ids'], $data['attachments'], $data['attachment_media_ids'],
        );

        $data['content'] = \App\Services\HtmlSanitizer::clean($data['content'] ?? null);
        $data['author_id'] = auth()->id();
        $data['status'] = 'draft';

        $news = News::create($data);

        $this->syncFeaturedImage($request, $news);
        $this->storeGalleryImages($request, $news);
        $this->storeAttachments($request, $news);

        AuditLog::record('created', 'News', $news->id, "បង្កើតព័ត៌មាន៖ {$news->title_km}");

        return redirect()->route('admin.news.edit', $news)->with('status', 'បានបង្កើតព័ត៌មានដោយជោគជ័យ។');
    }

    public function edit(News $news): View
    {
        $this->authorize('update', $news);

        $categories = NewsCategory::orderBy('name_km')->get();
        $facilities = auth()->user()->isFacilityScoped()
            ? Facility::where('id', auth()->user()->facility_id)->get()
            : Facility::orderBy('name_km')->get();

        $news->load(['featuredMedia', 'images.media', 'attachments.media']);
        $newsFolderId = app(MediaManagerService::class)->resolveFolderForRecord(
            'news', $news, "#{$news->id} — {$news->title_km}"
        );

        return view('admin.news.edit', compact('news', 'categories', 'facilities', 'newsFolderId'));
    }

    public function update(NewsRequest $request, News $news): RedirectResponse
    {
        $data = $request->validated();
        unset(
            $data['featured_image'], $data['featured_media_id'], $data['remove_featured_image'],
            $data['images'], $data['gallery_media_ids'], $data['attachments'], $data['attachment_media_ids'],
        );

        $data['content'] = \App\Services\HtmlSanitizer::clean($data['content'] ?? null);
        $news->update($data);
        $this->syncFeaturedImage($request, $news);
        $this->storeGalleryImages($request, $news);
        $this->storeAttachments($request, $news);

        AuditLog::record('updated', 'News', $news->id, "កែប្រែព័ត៌មាន៖ {$news->title_km}");

        return redirect()->route('admin.news.edit', $news)->with('status', 'បានរក្សាទុកដោយជោគជ័យ។');
    }

    /**
     * Featured image resolution order, matching the form: a freshly uploaded file wins (and is
     * itself immediately registered as a Media record, so the two never disagree); otherwise an
     * explicit Media Library pick; otherwise the explicit remove action; otherwise unchanged —
     * a plain re-save of the other fields must never silently touch the featured image.
     */
    private function syncFeaturedImage(NewsRequest $request, News $news): void
    {
        if ($request->hasFile('featured_image')) {
            MediaUploader::delete($news->featured_image);
            $label = '#'.$news->id.' — '.$news->title_km;
            $folderId = app(MediaManagerService::class)->resolveFolderForRecord('news', $news, $label);
            $path = MediaUploader::store(
                $request->file('featured_image'), 'news',
                MediaUploader::IMAGE_ONLY_EXTENSIONS, MediaUploader::IMAGE_ONLY_MIMES, $folderId,
            );

            $news->update([
                'featured_image' => $path,
                'featured_media_id' => Media::where('disk_path', $path)->value('id'),
            ]);
        } elseif ($request->filled('featured_media_id')) {
            $media = Media::find($request->input('featured_media_id'));
            $news->update(['featured_media_id' => $media->id, 'featured_image' => $media->disk_path]);
        } elseif ($request->boolean('remove_featured_image')) {
            $news->update(['featured_media_id' => null, 'featured_image' => null]);
        }
    }

    private function storeGalleryImages(NewsRequest $request, News $news): void
    {
        $sortOrder = ((int) $news->images()->max('sort_order')) + 1;

        if ($request->hasFile('images')) {
            $folderId = app(MediaManagerService::class)->resolveFolderForRecord('news', $news, "#{$news->id} — {$news->title_km}");

            foreach ($request->file('images') as $file) {
                $path = MediaUploader::store(
                    $file,
                    'news/gallery',
                    MediaUploader::IMAGE_ONLY_EXTENSIONS,
                    MediaUploader::IMAGE_ONLY_MIMES,
                    $folderId,
                );

                $news->images()->create([
                    'media_id' => Media::where('disk_path', $path)->value('id'),
                    'image' => $path,
                    'alt_text' => 'រូបភាពសកម្មភាព៖ '.$news->title_km,
                    'sort_order' => $sortOrder++,
                ]);
            }
        }

        // Picking existing Media Library images alongside (or instead of) a fresh upload:
        // never duplicates a file already attached to this article's gallery.
        $alreadyAttached = $news->images()->pluck('media_id')->filter()->all();
        foreach ($request->input('gallery_media_ids', []) as $mediaId) {
            if (in_array((int) $mediaId, $alreadyAttached, true)) {
                continue;
            }
            $media = Media::find($mediaId);
            $news->images()->create([
                'media_id' => $media->id,
                'image' => $media->disk_path,
                'alt_text' => 'រូបភាពសកម្មភាព៖ '.$news->title_km,
                'sort_order' => $sortOrder++,
            ]);
            $alreadyAttached[] = $media->id;
        }
    }

    private function storeAttachments(NewsRequest $request, News $news): void
    {
        $sortOrder = ((int) $news->attachments()->max('sort_order')) + 1;

        if ($request->hasFile('attachments')) {
            $folderId = app(MediaManagerService::class)->resolveFolderForRecord('news', $news, "#{$news->id} — {$news->title_km}");

            foreach ($request->file('attachments') as $file) {
                $path = MediaUploader::store($file, 'news/attachments', MediaUploader::LIBRARY_EXTENSIONS, MediaUploader::LIBRARY_MIMES, $folderId);
                $news->attachments()->create([
                    'media_id' => Media::where('disk_path', $path)->value('id'),
                    'sort_order' => $sortOrder++,
                ]);
            }
        }

        $alreadyAttached = $news->attachments()->pluck('media_id')->all();
        foreach ($request->input('attachment_media_ids', []) as $mediaId) {
            if (in_array((int) $mediaId, $alreadyAttached, true)) {
                continue;
            }
            $news->attachments()->create(['media_id' => (int) $mediaId, 'sort_order' => $sortOrder++]);
            $alreadyAttached[] = (int) $mediaId;
        }
    }

    /** A soft delete: the article leaves every public listing immediately, but stays recoverable in Trash. */
    public function destroy(News $news): RedirectResponse
    {
        $this->authorize('delete', $news);

        $news->delete();

        AuditLog::record('deleted', 'News', $news->id, "លុបព័ត៌មាន៖ {$news->title_km}");

        return redirect()->route('admin.news.index')->with('status', 'បានលុបព័ត៌មានទៅធុងសម្រាមដោយជោគជ័យ។');
    }

    public function restore(News $news): RedirectResponse
    {
        $this->authorize('restore', $news);

        $news->restore();

        AuditLog::record('updated', 'News', $news->id, "សង្គ្រោះព័ត៌មាន៖ {$news->title_km}");

        return redirect()->route('admin.news.index', ['status' => 'trash'])->with('status', 'បានសង្គ្រោះព័ត៌មានដោយជោគជ័យ។');
    }

    /**
     * Permanently deletes the article row (and, via cascadeOnDelete, its own gallery/attachment
     * LINK rows). The Media Library files those links pointed at are never touched — permanent
     * file deletion only ever happens from the Media Library itself.
     */
    public function forceDestroy(News $news): RedirectResponse
    {
        $this->authorize('forceDelete', $news);

        $news->forceDelete();

        AuditLog::record('deleted', 'News', $news->id, "លុបព័ត៌មានជាអចិន្ត្រៃយ៍៖ {$news->title_km}");

        return redirect()->route('admin.news.index', ['status' => 'trash'])->with('status', 'បានលុបព័ត៌មានជាអចិន្ត្រៃយ៍ដោយជោគជ័យ។');
    }

    public function submit(News $news): RedirectResponse
    {
        $this->authorize('submit', $news);

        $news->update(['status' => 'pending_review']);

        AuditLog::record('submitted_for_review', 'News', $news->id, "ដាក់ព័ត៌មានសម្រាប់ត្រួតពិនិត្យ៖ {$news->title_km}");

        return back()->with('status', 'បានដាក់ស្នើសម្រាប់ការត្រួតពិនិត្យ។');
    }

    public function approve(News $news): RedirectResponse
    {
        $this->authorize('approve', $news);

        $news->update([
            'status' => 'approved',
            'reviewer_id' => auth()->id(),
            'approved_at' => now(),
        ]);

        AuditLog::record('approved', 'News', $news->id, "អនុម័តព័ត៌មាន៖ {$news->title_km}");

        return back()->with('status', 'បានអនុម័តព័ត៌មានដោយជោគជ័យ។');
    }

    public function publish(News $news): RedirectResponse
    {
        $this->authorize('publish', $news);

        $news->update([
            'status' => 'published',
            'published_at' => $news->published_at ?? now(),
        ]);

        AuditLog::record('published', 'News', $news->id, "បានផ្សាយព័ត៌មាន៖ {$news->title_km}");

        return back()->with('status', 'បានផ្សាយព័ត៌មានដោយជោគជ័យ។');
    }

    public function archive(News $news): RedirectResponse
    {
        $this->authorize('publish', $news);

        $news->update(['status' => 'archived']);

        AuditLog::record('unpublished', 'News', $news->id, "បានទុកព័ត៌មានក្នុងឯកសារ៖ {$news->title_km}");

        return back()->with('status', 'បានទុកព័ត៌មានក្នុងឯកសារ។');
    }
}
