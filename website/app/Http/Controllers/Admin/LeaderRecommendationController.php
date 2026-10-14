<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LeaderRecommendation;
use App\Models\Media;
use App\Services\MediaManagerService;
use App\Services\MediaUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaderRecommendationController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', LeaderRecommendation::class);

        $recommendations = LeaderRecommendation::withCount('items')->latest()->paginate(15);

        return view('admin.leader-recommendations.index', compact('recommendations'));
    }

    public function create(): View
    {
        $this->authorize('create', LeaderRecommendation::class);

        return view('admin.leader-recommendations.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', LeaderRecommendation::class);

        $data = $this->validated($request);
        $data['is_active'] = $request->boolean('is_active');

        $recommendation = LeaderRecommendation::create($data);

        $mediaId = $this->resolveMediaId($request, $recommendation);
        if ($mediaId !== null) {
            $recommendation->update(['media_id' => $mediaId]);
        }

        AuditLog::record('created', 'LeaderRecommendation', $recommendation->id, "បង្កើតអនុសាសន៍ថ្នាក់ដឹកនាំ៖ {$recommendation->title_km}");

        return redirect()->route('admin.leader-recommendations.edit', $recommendation)->with('status', 'បានបង្កើតដោយជោគជ័យ។');
    }

    public function edit(LeaderRecommendation $leaderRecommendation): View
    {
        $this->authorize('update', $leaderRecommendation);

        $leaderRecommendation->load(['items', 'media']);
        $recommendationFolderId = app(MediaManagerService::class)->resolveFolderForRecord(
            'leader-recommendations', $leaderRecommendation, "#{$leaderRecommendation->id} — {$leaderRecommendation->title_km}"
        );

        return view('admin.leader-recommendations.edit', ['recommendation' => $leaderRecommendation, 'recommendationFolderId' => $recommendationFolderId]);
    }

    public function update(Request $request, LeaderRecommendation $leaderRecommendation): RedirectResponse
    {
        $this->authorize('update', $leaderRecommendation);

        $data = $this->validated($request);
        $mediaId = $this->resolveMediaId($request, $leaderRecommendation);
        if ($mediaId !== null) {
            $data['media_id'] = $mediaId;
        } elseif ($request->boolean('remove_media')) {
            $data['media_id'] = null;
        }
        $data['is_active'] = $request->boolean('is_active');

        $leaderRecommendation->update($data);

        AuditLog::record('updated', 'LeaderRecommendation', $leaderRecommendation->id, "កែប្រែអនុសាសន៍ថ្នាក់ដឹកនាំ៖ {$leaderRecommendation->title_km}");

        return redirect()->route('admin.leader-recommendations.edit', $leaderRecommendation)->with('status', 'បានរក្សាទុកដោយជោគជ័យ។');
    }

    public function destroy(LeaderRecommendation $leaderRecommendation): RedirectResponse
    {
        $this->authorize('delete', $leaderRecommendation);

        $leaderRecommendation->items()->delete();
        $leaderRecommendation->delete();

        AuditLog::record('deleted', 'LeaderRecommendation', $leaderRecommendation->id, "លុបអនុសាសន៍ថ្នាក់ដឹកនាំ៖ {$leaderRecommendation->title_km}");

        return redirect()->route('admin.leader-recommendations.index')->with('status', 'បានលុបដោយជោគជ័យ។');
    }

    public function toggle(LeaderRecommendation $leaderRecommendation): RedirectResponse
    {
        $this->authorize('update', $leaderRecommendation);

        $leaderRecommendation->update(['is_active' => ! $leaderRecommendation->is_active]);

        AuditLog::record('updated', 'LeaderRecommendation', $leaderRecommendation->id, ($leaderRecommendation->is_active ? 'បើក' : 'បិទ') . "អនុសាសន៍ថ្នាក់ដឹកនាំ៖ {$leaderRecommendation->title_km}");

        return back()->with('status', 'បានផ្លាស់ប្តូរស្ថានភាពដោយជោគជ័យ។');
    }

    public function publish(LeaderRecommendation $leaderRecommendation): RedirectResponse
    {
        $this->authorize('update', $leaderRecommendation);

        $leaderRecommendation->update(['is_active' => true, 'published_at' => $leaderRecommendation->published_at ?? now()]);

        AuditLog::record('published', 'LeaderRecommendation', $leaderRecommendation->id, "បានផ្សាយអនុសាសន៍ថ្នាក់ដឹកនាំ៖ {$leaderRecommendation->title_km}");

        return back()->with('status', 'បានផ្សាយដោយជោគជ័យ។');
    }

    public function unpublish(LeaderRecommendation $leaderRecommendation): RedirectResponse
    {
        $this->authorize('update', $leaderRecommendation);

        $leaderRecommendation->update(['is_active' => false]);

        AuditLog::record('unpublished', 'LeaderRecommendation', $leaderRecommendation->id, "បានដកអនុសាសន៍ថ្នាក់ដឹកនាំពីការផ្សាយ៖ {$leaderRecommendation->title_km}");

        return back()->with('status', 'បានដកចេញពីការផ្សាយ។');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title_km' => ['required', 'string', 'max:255'],
            'subtitle_km' => ['nullable', 'string', 'max:255'],
            'leader_name_km' => ['required', 'string', 'max:255'],
            'leader_position_km' => ['nullable', 'string', 'max:255'],
            'published_at' => ['nullable', 'date'],
        ]);
    }

    private function resolveMediaId(Request $request, ?LeaderRecommendation $recommendation = null): ?int
    {
        if ($request->hasFile('image_file')) {
            $folderId = $recommendation
                ? app(MediaManagerService::class)->resolveFolderForRecord('leader-recommendations', $recommendation, "#{$recommendation->id} — {$recommendation->title_km}")
                : null;
            $path = MediaUploader::store($request->file('image_file'), 'leader-recommendations', MediaUploader::IMAGE_ONLY_EXTENSIONS, MediaUploader::IMAGE_ONLY_MIMES, $folderId);

            return Media::where('disk_path', $path)->value('id');
        }

        if ($request->filled('media_id')) {
            return (int) $request->input('media_id');
        }

        return null;
    }
}
