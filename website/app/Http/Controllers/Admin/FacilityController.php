<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FacilityRequest;
use App\Models\AuditLog;
use App\Models\Facility;
use App\Models\FacilityGallery;
use App\Models\FacilityLeader;
use App\Models\FacilityService;
use App\Models\Partner;
use App\Services\MediaUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FacilityController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Facility::class);

        $query = Facility::query();

        if (auth()->user()->isFacilityScoped()) {
            $query->where('id', auth()->user()->facility_id);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name_km', 'like', '%' . $request->search . '%')
                    ->orWhere('name_en', 'like', '%' . $request->search . '%')
                    ->orWhere('code', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('type')) {
            $query->where('facility_type', $request->type);
        }

        if ($request->filled('district')) {
            $query->where('district', $request->district);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $facilities = $query->latest('updated_at')->paginate(15)->withQueryString();
        $districts = Facility::select('district')->distinct()->orderBy('district')->pluck('district');

        return view('admin.facilities.index', compact('facilities', 'districts'));
    }

    public function create(): View
    {
        $this->authorize('create', Facility::class);

        return view('admin.facilities.create');
    }

    public function store(FacilityRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            $data['logo'] = MediaUploader::store($request->file('logo'), 'facilities/logos');
        }
        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = MediaUploader::store($request->file('cover_image'), 'facilities/covers');
        }

        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        $data['status'] = 'draft';

        $facility = Facility::create($data);

        AuditLog::record('created', 'Facility', $facility->id, "បង្កើតមូលដ្ឋាន៖ {$facility->name_km}");

        return redirect()->route('admin.facilities.edit', $facility)->with('status', 'បានបង្កើតមូលដ្ឋានដោយជោគជ័យ។');
    }

    public function edit(Facility $facility, Request $request): View
    {
        $this->authorize('update', $facility);

        $tab = $request->get('tab', 'general');
        $partners = Partner::orderBy('name')->get();

        return view('admin.facilities.edit', compact('facility', 'tab', 'partners'));
    }

    public function update(FacilityRequest $request, Facility $facility): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            MediaUploader::delete($facility->logo);
            $data['logo'] = MediaUploader::store($request->file('logo'), 'facilities/logos');
        }
        if ($request->hasFile('cover_image')) {
            MediaUploader::delete($facility->cover_image);
            $data['cover_image'] = MediaUploader::store($request->file('cover_image'), 'facilities/covers');
        }

        $data['updated_by'] = auth()->id();

        $facility->update($data);

        AuditLog::record('updated', 'Facility', $facility->id, "កែប្រែមូលដ្ឋាន៖ {$facility->name_km}");

        return redirect()->route('admin.facilities.edit', ['facility' => $facility, 'tab' => $request->get('tab', 'general')])
            ->with('status', 'បានរក្សាទុកដោយជោគជ័យ។');
    }

    public function destroy(Facility $facility): RedirectResponse
    {
        $this->authorize('delete', $facility);

        $facility->delete();

        AuditLog::record('deleted', 'Facility', $facility->id, "លុបមូលដ្ឋាន៖ {$facility->name_km}");

        return redirect()->route('admin.facilities.index')->with('status', 'បានលុបមូលដ្ឋានដោយជោគជ័យ។');
    }

    public function publish(Facility $facility): RedirectResponse
    {
        $this->authorize('publish', $facility);

        $facility->update(['status' => 'published', 'published_at' => now(), 'updated_by' => auth()->id()]);

        AuditLog::record('published', 'Facility', $facility->id, "បានផ្សាយមូលដ្ឋាន៖ {$facility->name_km}");

        return back()->with('status', 'បានផ្សាយមូលដ្ឋានដោយជោគជ័យ។');
    }

    public function archive(Facility $facility): RedirectResponse
    {
        $this->authorize('publish', $facility);

        $facility->update(['status' => 'archived', 'updated_by' => auth()->id()]);

        AuditLog::record('unpublished', 'Facility', $facility->id, "បានទុកមូលដ្ឋានក្នុងឯកសារ៖ {$facility->name_km}");

        return back()->with('status', 'បានទុកមូលដ្ឋានក្នុងឯកសារ។');
    }

    public function storeService(Request $request, Facility $facility): RedirectResponse
    {
        $this->authorize('update', $facility);

        $request->validate([
            'icon' => ['nullable', 'string', 'max:10'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $facility->services()->create([
            'icon' => $request->icon ?: '✚',
            'name' => $request->name,
            'sort_order' => $facility->services()->count(),
        ]);

        return back()->with('status', 'បានបន្ថែមសេវាដោយជោគជ័យ។');
    }

    public function destroyService(Facility $facility, FacilityService $service): RedirectResponse
    {
        $this->authorize('update', $facility);
        abort_unless($service->facility_id === $facility->id, 404);

        $service->delete();

        return back()->with('status', 'បានលុបសេវាដោយជោគជ័យ។');
    }

    public function storeLeader(Request $request, Facility $facility): RedirectResponse
    {
        $this->authorize('update', $facility);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ]);

        $photo = $request->hasFile('photo') ? MediaUploader::store($request->file('photo'), 'facilities/leaders') : null;

        $facility->leaders()->create([
            'name' => $request->name,
            'title' => $request->title,
            'photo' => $photo,
            'sort_order' => $facility->leaders()->count(),
        ]);

        return back()->with('status', 'បានបន្ថែមថ្នាក់ដឹកនាំដោយជោគជ័យ។');
    }

    public function destroyLeader(Facility $facility, FacilityLeader $leader): RedirectResponse
    {
        $this->authorize('update', $facility);
        abort_unless($leader->facility_id === $facility->id, 404);

        $leader->delete();

        return back()->with('status', 'បានលុបថ្នាក់ដឹកនាំដោយជោគជ័យ។');
    }

    public function syncPartners(Request $request, Facility $facility): RedirectResponse
    {
        $this->authorize('update', $facility);

        $facility->partners()->sync($request->input('partner_ids', []));

        return back()->with('status', 'បានធ្វើបច្ចុប្បន្នភាពអង្គការដៃគូដោយជោគជ័យ។');
    }

    public function storeGalleryImage(Request $request, Facility $facility): RedirectResponse
    {
        $this->authorize('update', $facility);

        $request->validate(['image' => ['required', 'image', 'max:4096']]);

        $path = MediaUploader::store($request->file('image'), 'facilities/gallery');

        $facility->gallery()->create([
            'image' => $path,
            'caption' => $request->caption,
            'sort_order' => $facility->gallery()->count(),
        ]);

        return back()->with('status', 'បានបន្ថែមរូបភាពដោយជោគជ័យ។');
    }

    public function destroyGalleryImage(Facility $facility, FacilityGallery $image): RedirectResponse
    {
        $this->authorize('update', $facility);
        abort_unless($image->facility_id === $facility->id, 404);

        MediaUploader::delete($image->image);
        $image->delete();

        return back()->with('status', 'បានលុបរូបភាពដោយជោគជ័យ។');
    }
}
