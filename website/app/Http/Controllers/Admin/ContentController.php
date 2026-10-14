<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContentRequest;
use App\Models\AuditLog;
use App\Models\Content;
use App\Models\ContentCategory;
use App\Services\MediaManagerService;
use App\Services\MediaUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContentController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Content::class);

        $query = Content::with(['category', 'author']);

        if ($request->filled('search')) {
            $query->where('title_km', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $contents = $query->latest('updated_at')->paginate(15)->withQueryString();
        $categories = ContentCategory::orderBy('name_km')->get();

        return view('admin.content.index', compact('contents', 'categories'));
    }

    public function create(): View
    {
        $this->authorize('create', Content::class);

        $categories = ContentCategory::orderBy('name_km')->get();

        return view('admin.content.create', compact('categories'));
    }

    public function store(ContentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        unset($data['featured_image']);

        $data['is_featured'] = $request->boolean('is_featured');
        $data['tags'] = $this->parseTags($data['tags'] ?? null);
        $data['author_id'] = auth()->id();
        $data['status'] = 'draft';

        $content = Content::create($data);

        if ($request->hasFile('featured_image')) {
            $folderId = app(MediaManagerService::class)->resolveFolderForRecord('content', $content, "#{$content->id} — {$content->title_km}");
            $content->update(['featured_image' => MediaUploader::store(
                $request->file('featured_image'), 'content',
                MediaUploader::IMAGE_ONLY_EXTENSIONS, MediaUploader::IMAGE_ONLY_MIMES, $folderId
            )]);
        }

        AuditLog::record('created', 'Content', $content->id, "បង្កើតអត្ថបទ៖ {$content->title_km}");

        return redirect()->route('admin.content.edit', $content)->with('status', 'បានបង្កើតអត្ថបទដោយជោគជ័យ។');
    }

    public function edit(Content $content): View
    {
        $this->authorize('update', $content);

        $categories = ContentCategory::orderBy('name_km')->get();
        $content->load(['images' => fn ($q) => $q->orderBy('sort_order'), 'blocks' => fn ($q) => $q->orderBy('sort_order')]);
        $contentFolderId = app(MediaManagerService::class)->resolveFolderForRecord('content', $content, "#{$content->id} — {$content->title_km}");

        return view('admin.content.edit', compact('content', 'categories', 'contentFolderId'));
    }

    public function update(ContentRequest $request, Content $content): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('featured_image')) {
            MediaUploader::delete($content->featured_image);
            $label = '#'.$content->id.' — '.($data['title_km'] ?? $content->title_km);
            $folderId = app(MediaManagerService::class)->resolveFolderForRecord('content', $content, $label);
            $data['featured_image'] = MediaUploader::store(
                $request->file('featured_image'), 'content',
                MediaUploader::IMAGE_ONLY_EXTENSIONS, MediaUploader::IMAGE_ONLY_MIMES, $folderId
            );
        }

        $data['is_featured'] = $request->boolean('is_featured');
        $data['tags'] = $this->parseTags($data['tags'] ?? null);

        $content->update($data);

        AuditLog::record('updated', 'Content', $content->id, "កែប្រែអត្ថបទ៖ {$content->title_km}");

        return redirect()->route('admin.content.edit', $content)->with('status', 'បានរក្សាទុកដោយជោគជ័យ។');
    }

    public function destroy(Content $content): RedirectResponse
    {
        $this->authorize('delete', $content);

        $content->delete();

        AuditLog::record('deleted', 'Content', $content->id, "លុបអត្ថបទ៖ {$content->title_km}");

        return redirect()->route('admin.content.index')->with('status', 'បានលុបអត្ថបទដោយជោគជ័យ។');
    }

    public function submit(Content $content): RedirectResponse
    {
        $this->authorize('submitReview', $content);

        $content->update(['status' => 'pending_review']);

        AuditLog::record('submitted_for_review', 'Content', $content->id, "ដាក់អត្ថបទសម្រាប់ត្រួតពិនិត្យ៖ {$content->title_km}");

        return back()->with('status', 'បានដាក់ស្នើសម្រាប់ការត្រួតពិនិត្យ។');
    }

    public function approve(Content $content): RedirectResponse
    {
        $this->authorize('approve', $content);

        $content->update([
            'status' => 'approved',
            'reviewer_id' => auth()->id(),
            'approved_at' => now(),
        ]);

        AuditLog::record('approved', 'Content', $content->id, "អនុម័តអត្ថបទ៖ {$content->title_km}");

        return back()->with('status', 'បានអនុម័តអត្ថបទដោយជោគជ័យ។');
    }

    public function publish(Content $content): RedirectResponse
    {
        $this->authorize('publish', $content);

        $content->update([
            'status' => 'published',
            'published_at' => $content->published_at ?? now(),
        ]);

        AuditLog::record('published', 'Content', $content->id, "បានផ្សាយអត្ថបទ៖ {$content->title_km}");

        return back()->with('status', 'បានផ្សាយអត្ថបទដោយជោគជ័យ។');
    }

    public function archive(Content $content): RedirectResponse
    {
        $this->authorize('publish', $content);

        $content->update(['status' => 'archived']);

        AuditLog::record('unpublished', 'Content', $content->id, "បានទុកអត្ថបទក្នុងឯកសារ៖ {$content->title_km}");

        return back()->with('status', 'បានទុកអត្ថបទក្នុងឯកសារ។');
    }

    private function parseTags(?string $tags): ?array
    {
        if (! $tags) {
            return null;
        }

        $parsed = array_values(array_filter(array_map('trim', explode(',', $tags))));

        return $parsed ?: null;
    }
}
