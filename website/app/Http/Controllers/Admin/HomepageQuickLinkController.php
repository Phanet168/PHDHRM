<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\HomepageQuickLink;
use App\Models\Media;
use App\Services\MediaManagerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\View\View;

class HomepageQuickLinkController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', HomepageQuickLink::class);

        $links = HomepageQuickLink::withCount([])->with('media')->orderBy('sort_order')->paginate(15);

        return view('admin.homepage-quick-links.index', compact('links'));
    }

    public function create(): View
    {
        $this->authorize('create', HomepageQuickLink::class);

        return view('admin.homepage-quick-links.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', HomepageQuickLink::class);

        $data = $this->validated($request);
        $data['media_id'] = $this->resolveMediaId($request);
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = $data['sort_order'] ?? HomepageQuickLink::count();

        $link = HomepageQuickLink::create($data);

        AuditLog::record('created', 'HomepageQuickLink', $link->id, "បង្កើតកាតទំព័រសំខាន់៖ {$link->title_km}");

        return redirect()->route('admin.homepage-quick-links.index')->with('status', 'បានបង្កើតដោយជោគជ័យ។');
    }

    public function edit(HomepageQuickLink $homepageQuickLink): View
    {
        $this->authorize('update', $homepageQuickLink);

        $homepageQuickLink->load('media');
        $linkFolderId = app(MediaManagerService::class)->resolveFolderForRecord(
            'homepage-quick-links', $homepageQuickLink, "#{$homepageQuickLink->id} — {$homepageQuickLink->title_km}"
        );

        return view('admin.homepage-quick-links.edit', ['link' => $homepageQuickLink, 'linkFolderId' => $linkFolderId]);
    }

    public function update(Request $request, HomepageQuickLink $homepageQuickLink): RedirectResponse
    {
        $this->authorize('update', $homepageQuickLink);

        $data = $this->validated($request);
        // The image picker lives in its own <form>, separate from the main
        // info form (which has no media_id field at all). Only touch
        // media_id when this specific request actually included that field
        // — otherwise saving the title/description would silently wipe out
        // whatever image was already selected.
        if ($request->has('media_id')) {
            $data['media_id'] = $this->resolveMediaId($request);
        }
        $data['is_active'] = $request->boolean('is_active');

        $homepageQuickLink->update($data);

        AuditLog::record('updated', 'HomepageQuickLink', $homepageQuickLink->id, "កែប្រែកាតទំព័រសំខាន់៖ {$homepageQuickLink->title_km}");

        return redirect()->route('admin.homepage-quick-links.edit', $homepageQuickLink)->with('status', 'បានរក្សាទុកដោយជោគជ័យ។');
    }

    public function destroy(HomepageQuickLink $homepageQuickLink): RedirectResponse
    {
        $this->authorize('delete', $homepageQuickLink);

        $homepageQuickLink->delete();

        AuditLog::record('deleted', 'HomepageQuickLink', $homepageQuickLink->id, "លុបកាតទំព័រសំខាន់៖ {$homepageQuickLink->title_km}");

        return redirect()->route('admin.homepage-quick-links.index')->with('status', 'បានលុបដោយជោគជ័យ។');
    }

    public function toggle(HomepageQuickLink $homepageQuickLink): RedirectResponse
    {
        $this->authorize('update', $homepageQuickLink);

        $homepageQuickLink->update(['is_active' => ! $homepageQuickLink->is_active]);

        AuditLog::record('updated', 'HomepageQuickLink', $homepageQuickLink->id, ($homepageQuickLink->is_active ? 'បើក' : 'បិទ') . "កាតទំព័រសំខាន់៖ {$homepageQuickLink->title_km}");

        return back()->with('status', 'បានផ្លាស់ប្តូរស្ថានភាពដោយជោគជ័យ។');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title_km' => ['required', 'string', 'max:255'],
            'description_km' => ['nullable', 'string', 'max:255'],
            'icon' => ['required', 'string', 'max:50'],
            'link_type' => ['required', 'in:route,url'],
            'route_name' => ['required_if:link_type,route', 'nullable', 'string', 'max:255'],
            'url' => ['required_if:link_type,url', 'nullable', 'string', 'max:2048'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($data['link_type'] === 'route' && ! RouteFacade::has($data['route_name'] ?? '')) {
            abort(422, 'ផ្លូវ (route) ដែលបានជ្រើសរើសមិនត្រឹមត្រូវទេ។');
        }

        return $data;
    }

    private function resolveMediaId(Request $request): ?int
    {
        if (! $request->filled('media_id')) {
            return null;
        }

        $media = Media::find($request->input('media_id'));

        abort_unless($media && $media->isImage(), 422, 'ឯកសារដែលបានជ្រើសរើសមិនមែនជារូបភាពទេ។');

        return $media->id;
    }
}
