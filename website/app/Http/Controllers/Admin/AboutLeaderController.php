<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\FacilityLeader;
use App\Models\Media;
use App\Services\MediaManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Department leadership: the existing facility_leaders rows with facility_id NULL. */
class AboutLeaderController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('about.manage');
        $trashed = $request->boolean('trashed');

        $leaders = FacilityLeader::department()->with('portrait')->orderBy('sort_order')->orderBy('id')
            ->when($trashed, fn ($query) => $query->onlyTrashed())->get();

        return view('admin.about.leaders.index', [
            'leaders' => $leaders,
            'trashed' => $trashed,
            'trashedCount' => FacilityLeader::department()->onlyTrashed()->count(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('about.manage');

        return $this->form(new FacilityLeader(['is_active' => true, 'sort_order' => FacilityLeader::department()->count()]));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('about.manage');

        $leader = FacilityLeader::create(['facility_id' => null, ...$this->validated($request)]);
        AuditLog::record('created', 'FacilityLeader', $leader->id, 'បន្ថែមថ្នាក់ដឹកនាំ៖ '.$leader->name);

        return redirect()->route('admin.about.leaders.index')->with('status', 'បានបន្ថែមដោយជោគជ័យ។');
    }

    public function edit(FacilityLeader $leader): View
    {
        $this->authorize('about.manage');
        abort_unless($leader->facility_id === null, 404);

        return $this->form($leader);
    }

    public function update(Request $request, FacilityLeader $leader): RedirectResponse
    {
        $this->authorize('about.manage');
        abort_unless($leader->facility_id === null, 404);

        $leader->update($this->validated($request));
        AuditLog::record('updated', 'FacilityLeader', $leader->id, 'កែសម្រួលថ្នាក់ដឹកនាំ៖ '.$leader->name);

        return redirect()->route('admin.about.leaders.index')->with('status', 'បានរក្សាទុកដោយជោគជ័យ។');
    }

    public function toggle(FacilityLeader $leader): RedirectResponse
    {
        $this->authorize('about.manage');
        abort_unless($leader->facility_id === null, 404);

        $leader->update(['is_active' => ! $leader->is_active]);
        AuditLog::record($leader->is_active ? 'published' : 'unpublished', 'FacilityLeader', $leader->id, ($leader->is_active ? 'បើកប្រើ' : 'បិទប្រើ').'ថ្នាក់ដឹកនាំ៖ '.$leader->name);

        return back()->with('status', 'បានផ្លាស់ប្តូរស្ថានភាពដោយជោគជ័យ។');
    }

    public function destroy(FacilityLeader $leader): RedirectResponse
    {
        $this->authorize('about.manage');
        abort_unless($leader->facility_id === null, 404);

        // Soft delete only; the portrait stays in the Media Library.
        $leader->delete();
        AuditLog::record('deleted', 'FacilityLeader', $leader->id, 'លុបថ្នាក់ដឹកនាំ៖ '.$leader->name);

        return back()->with('status', 'បានលុបដោយជោគជ័យ។ អាចស្ដារវិញពីធុងសំរាម។');
    }

    public function restore(int $leader): RedirectResponse
    {
        $this->authorize('about.manage');
        $leader = FacilityLeader::department()->onlyTrashed()->findOrFail($leader);

        $leader->restore();
        AuditLog::record('restored', 'FacilityLeader', $leader->id, 'ស្ដារថ្នាក់ដឹកនាំ៖ '.$leader->name);

        return back()->with('status', 'បានស្ដារវិញដោយជោគជ័យ។');
    }

    public function forceDestroy(int $leader): RedirectResponse
    {
        $this->authorize('about.manage');
        $leader = FacilityLeader::department()->onlyTrashed()->findOrFail($leader);

        $leader->forceDelete();
        AuditLog::record('force_deleted', 'FacilityLeader', $leader->id, 'លុបថ្នាក់ដឹកនាំជាអចិន្ត្រៃយ៍៖ '.$leader->name);

        return back()->with('status', 'បានលុបជាអចិន្ត្រៃយ៍។ រូបថតក្នុង Media Library មិនត្រូវបានលុបទេ។');
    }

    public function reorder(Request $request): JsonResponse
    {
        $this->authorize('about.manage');
        $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($request->input('items') as $row) {
            FacilityLeader::department()->whereKey($row['id'])->update(['sort_order' => $row['sort_order']]);
        }
        AuditLog::record('reordered', 'FacilityLeader', null, 'តម្រៀបលំដាប់ថ្នាក់ដឹកនាំ');

        return response()->json(['status' => 'ok']);
    }

    private function form(FacilityLeader $leader): View
    {
        return view('admin.about.leaders.form', [
            'leader' => $leader,
            'folderId' => app(MediaManagerService::class)->resolveFolderForCollection('about'),
        ]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'portrait_media_id' => ['nullable', 'integer'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if (filled($data['portrait_media_id'] ?? null)) {
            $media = Media::find($data['portrait_media_id']);
            abort_unless($media && $media->isImage(), 422, 'ឯកសារដែលបានជ្រើសរើសមិនមែនជារូបភាពទេ។');
        } else {
            $data['portrait_media_id'] = null;
        }
        $data['bio'] = filled($data['bio'] ?? null) ? strip_tags($data['bio']) : null;
        $data['sort_order'] ??= FacilityLeader::department()->count();
        $data['is_active'] = $request->boolean('is_active');
        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_demo'] = false;

        return $data;
    }
}
