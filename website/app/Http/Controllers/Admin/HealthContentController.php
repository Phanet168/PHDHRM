<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HealthContentRequest;
use App\Models\AuditLog;
use App\Models\HealthContent;
use App\Models\HealthContentCategory;
use App\Models\Media;
use App\Services\MediaManagerService;
use App\Services\MediaUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HealthContentController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', HealthContent::class);

        $query = HealthContent::with(['category', 'author']);

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
        $categories = HealthContentCategory::orderBy('name_km')->get();

        return view('admin.health-content.index', compact('contents', 'categories'));
    }

    public function create(): View
    {
        $this->authorize('create', HealthContent::class);

        $categories = HealthContentCategory::orderBy('name_km')->get();

        return view('admin.health-content.create', compact('categories'));
    }

    public function store(HealthContentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        unset($data['video_file'], $data['duration']);

        $data['is_featured'] = $request->boolean('is_featured');
        $data['video_type'] = $request->input('video_type') ?: null;
        $data['duration_seconds'] = $this->parseDuration($request->input('duration'));
        if (filled($data['educational_message'] ?? null)) {
            $data['summary_status'] = 'reviewed';
        }
        $data['author_id'] = auth()->id();
        $data['updated_by'] = auth()->id();
        $data['status'] = 'draft';

        $content = HealthContent::create($data);

        if ($request->hasFile('video_file')) {
            $folderId = app(MediaManagerService::class)->resolveFolderForRecord('health-content', $content, "#{$content->id} — {$content->title_km}");
            $content->update(['video_media_id' => $this->storeVideoFile($request, $folderId)]);
        }

        AuditLog::record('created', 'HealthContent', $content->id, "បង្កើតមាតិកា៖ {$content->title_km}");

        return redirect()->route('admin.health-content.edit', $content)->with('status', 'បានបង្កើតមាតិកាដោយជោគជ័យ។');
    }

    public function edit(HealthContent $healthContent): View
    {
        $this->authorize('update', $healthContent);

        $categories = HealthContentCategory::orderBy('name_km')->get();
        $contentFolderId = app(MediaManagerService::class)->resolveFolderForRecord(
            'health-content', $healthContent, "#{$healthContent->id} — {$healthContent->title_km}"
        );

        return view('admin.health-content.edit', ['content' => $healthContent, 'categories' => $categories, 'contentFolderId' => $contentFolderId]);
    }

    public function update(HealthContentRequest $request, HealthContent $healthContent): RedirectResponse
    {
        $data = $request->validated();
        unset($data['video_file'], $data['duration']);

        $data['is_featured'] = $request->boolean('is_featured');
        $data['video_type'] = $request->input('video_type') ?: null;
        $data['duration_seconds'] = $this->parseDuration($request->input('duration'));
        $data['updated_by'] = auth()->id();
        if (filled($data['educational_message'] ?? null)) {
            $data['summary_status'] = 'reviewed';
        }

        if ($request->hasFile('video_file')) {
            $label = '#'.$healthContent->id.' — '.($data['title_km'] ?? $healthContent->title_km);
            $folderId = app(MediaManagerService::class)->resolveFolderForRecord('health-content', $healthContent, $label);
            $data['video_media_id'] = $this->storeVideoFile($request, $folderId);
        }

        $healthContent->update($data);

        AuditLog::record('updated', 'HealthContent', $healthContent->id, "កែប្រែមាតិកា៖ {$healthContent->title_km}");

        return redirect()->route('admin.health-content.edit', $healthContent)->with('status', 'បានរក្សាទុកដោយជោគជ័យ។');
    }

    public function destroy(HealthContent $healthContent): RedirectResponse
    {
        $this->authorize('delete', $healthContent);

        $healthContent->delete();

        AuditLog::record('deleted', 'HealthContent', $healthContent->id, "លុបមាតិកា៖ {$healthContent->title_km}");

        return redirect()->route('admin.health-content.index')->with('status', 'បានលុបមាតិកាដោយជោគជ័យ។');
    }

    public function submit(HealthContent $healthContent): RedirectResponse
    {
        $this->authorize('submit', $healthContent);

        $healthContent->update(['status' => 'pending_review']);

        AuditLog::record('submitted_for_review', 'HealthContent', $healthContent->id, "ដាក់មាតិកាសម្រាប់ត្រួតពិនិត្យ៖ {$healthContent->title_km}");

        return back()->with('status', 'បានដាក់ស្នើសម្រាប់ការត្រួតពិនិត្យ។');
    }

    public function approve(HealthContent $healthContent): RedirectResponse
    {
        $this->authorize('approve', $healthContent);

        $healthContent->update(['status' => 'approved']);

        AuditLog::record('approved', 'HealthContent', $healthContent->id, "អនុម័តមាតិកា៖ {$healthContent->title_km}");

        return back()->with('status', 'បានអនុម័តមាតិកាដោយជោគជ័យ។');
    }

    public function publish(HealthContent $healthContent): RedirectResponse
    {
        $this->authorize('publish', $healthContent);

        $healthContent->update(['status' => 'published', 'published_at' => $healthContent->published_at ?? now()]);

        AuditLog::record('published', 'HealthContent', $healthContent->id, "បានផ្សាយមាតិកា៖ {$healthContent->title_km}");

        return back()->with('status', 'បានផ្សាយមាតិកាដោយជោគជ័យ។');
    }

    public function archive(HealthContent $healthContent): RedirectResponse
    {
        $this->authorize('publish', $healthContent);

        $healthContent->update(['status' => 'archived']);

        AuditLog::record('unpublished', 'HealthContent', $healthContent->id, "បានទុកមាតិកាក្នុងឯកសារ៖ {$healthContent->title_km}");

        return back()->with('status', 'បានទុកមាតិកាក្នុងឯកសារ។');
    }

    private function storeVideoFile(Request $request, int $folderId): ?int
    {
        $path = MediaUploader::store(
            $request->file('video_file'), 'health-content',
            ['mp4', 'webm'], ['video/mp4', 'video/webm'], $folderId
        );

        return Media::where('disk_path', $path)->value('id');
    }

    private function parseDuration(?string $duration): ?int
    {
        if (! $duration || ! preg_match('/^(\d{1,3}):(\d{2})$/', $duration, $m)) {
            return null;
        }

        return ((int) $m[1]) * 60 + (int) $m[2];
    }
}
