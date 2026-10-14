<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HeroSlideRequest;
use App\Models\AuditLog;
use App\Models\HeroSlide;
use App\Services\MediaManagerService;
use App\Services\MediaUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class HeroSlideController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', HeroSlide::class);

        $slides = HeroSlide::orderBy('sort_order')->paginate(15);

        return view('admin.hero-slides.index', compact('slides'));
    }

    public function create(): View
    {
        $this->authorize('create', HeroSlide::class);

        return view('admin.hero-slides.create');
    }

    public function store(HeroSlideRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // image is NOT NULL at the DB level; store a placeholder so the
        // record can be created first (its id is needed to resolve its own
        // dedicated media folder), then overwrite it below in this same
        // request — the placeholder is never visible in any response.
        $data['image'] = '';
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = $data['sort_order'] ?? HeroSlide::count();

        $slide = HeroSlide::create($data);

        $folderId = app(MediaManagerService::class)->resolveFolderForRecord('hero-slides', $slide, "#{$slide->id} — {$slide->title_km}");
        $slide->update(['image' => MediaUploader::store($request->file('image'), 'hero-slides', folderId: $folderId)]);

        AuditLog::record('created', 'HeroSlide', $slide->id, "បង្កើត Hero Slide៖ {$slide->title_km}");

        return redirect()->route('admin.hero-slides.index')->with('status', 'បានបង្កើត Slide ដោយជោគជ័យ។');
    }

    public function edit(HeroSlide $heroSlide): View
    {
        $this->authorize('update', $heroSlide);

        return view('admin.hero-slides.edit', ['slide' => $heroSlide]);
    }

    public function update(HeroSlideRequest $request, HeroSlide $heroSlide): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            MediaUploader::delete($heroSlide->image);
            $label = '#'.$heroSlide->id.' — '.($data['title_km'] ?? $heroSlide->title_km);
            $folderId = app(MediaManagerService::class)->resolveFolderForRecord('hero-slides', $heroSlide, $label);
            $data['image'] = MediaUploader::store($request->file('image'), 'hero-slides', folderId: $folderId);
        }
        $data['is_active'] = $request->boolean('is_active');

        $heroSlide->update($data);

        AuditLog::record('updated', 'HeroSlide', $heroSlide->id, "កែប្រែ Hero Slide៖ {$heroSlide->title_km}");

        return redirect()->route('admin.hero-slides.index')->with('status', 'បានរក្សាទុកដោយជោគជ័យ។');
    }

    public function destroy(HeroSlide $heroSlide): RedirectResponse
    {
        $this->authorize('delete', $heroSlide);

        MediaUploader::delete($heroSlide->image);
        $heroSlide->delete();

        AuditLog::record('deleted', 'HeroSlide', $heroSlide->id, "លុប Hero Slide៖ {$heroSlide->title_km}");

        return redirect()->route('admin.hero-slides.index')->with('status', 'បានលុប Slide ដោយជោគជ័យ។');
    }
}
