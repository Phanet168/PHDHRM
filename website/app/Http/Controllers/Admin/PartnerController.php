<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PartnerRequest;
use App\Models\AuditLog;
use App\Models\Partner;
use App\Services\MediaUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PartnerController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Partner::class);

        $partners = Partner::orderBy('sort_order')->paginate(15);

        return view('admin.partners.index', compact('partners'));
    }

    public function create(): View
    {
        $this->authorize('create', Partner::class);

        return view('admin.partners.create');
    }

    public function store(PartnerRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            $data['logo'] = MediaUploader::store($request->file('logo'), 'partners');
        }
        $data['active'] = $request->boolean('active');

        $partner = Partner::create($data);

        AuditLog::record('created', 'Partner', $partner->id, "បង្កើតអង្គការដៃគូ៖ {$partner->name}");

        return redirect()->route('admin.partners.index')->with('status', 'បានបង្កើតអង្គការដៃគូដោយជោគជ័យ។');
    }

    public function edit(Partner $partner): View
    {
        $this->authorize('update', $partner);

        return view('admin.partners.edit', compact('partner'));
    }

    public function update(PartnerRequest $request, Partner $partner): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            MediaUploader::delete($partner->logo);
            $data['logo'] = MediaUploader::store($request->file('logo'), 'partners');
        }
        $data['active'] = $request->boolean('active');

        $partner->update($data);

        AuditLog::record('updated', 'Partner', $partner->id, "កែប្រែអង្គការដៃគូ៖ {$partner->name}");

        return redirect()->route('admin.partners.index')->with('status', 'បានរក្សាទុកដោយជោគជ័យ។');
    }

    public function destroy(Partner $partner): RedirectResponse
    {
        $this->authorize('delete', $partner);

        MediaUploader::delete($partner->logo);
        $partner->delete();

        AuditLog::record('deleted', 'Partner', $partner->id, "លុបអង្គការដៃគូ៖ {$partner->name}");

        return redirect()->route('admin.partners.index')->with('status', 'បានលុបអង្គការដៃគូដោយជោគជ័យ។');
    }
}
