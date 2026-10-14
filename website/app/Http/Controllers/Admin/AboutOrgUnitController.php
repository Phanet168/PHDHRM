<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AboutOrgUnit;
use App\Models\AuditLog;
use App\Models\Facility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** About-page organization structure: groups of units, one nesting level, optional facility link. */
class AboutOrgUnitController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('about.manage');
        $trashed = $request->boolean('trashed');

        $units = AboutOrgUnit::with(['facility', 'parent'])->orderBy('sort_order')->orderBy('id')
            ->when($trashed, fn ($query) => $query->onlyTrashed())->get();

        return view('admin.about.organization.index', [
            'units' => $units,
            'trashed' => $trashed,
            'trashedCount' => AboutOrgUnit::onlyTrashed()->count(),
            'importable' => $this->importableFacilities()->count(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('about.manage');

        return $this->form(new AboutOrgUnit(['type' => 'office', 'is_active' => true, 'sort_order' => AboutOrgUnit::count()]));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('about.manage');

        $unit = AboutOrgUnit::create($this->validated($request));
        AuditLog::record('created', 'AboutOrgUnit', $unit->id, 'បន្ថែមអង្គភាព៖ '.$unit->name);

        return redirect()->route('admin.about.organization.index')->with('status', 'បានបន្ថែមដោយជោគជ័យ។');
    }

    public function edit(AboutOrgUnit $unit): View
    {
        $this->authorize('about.manage');

        return $this->form($unit);
    }

    public function update(Request $request, AboutOrgUnit $unit): RedirectResponse
    {
        $this->authorize('about.manage');

        $unit->update($this->validated($request, $unit));
        AuditLog::record('updated', 'AboutOrgUnit', $unit->id, 'កែសម្រួលអង្គភាព៖ '.$unit->name);

        return redirect()->route('admin.about.organization.index')->with('status', 'បានរក្សាទុកដោយជោគជ័យ។');
    }

    public function toggle(AboutOrgUnit $unit): RedirectResponse
    {
        $this->authorize('about.manage');

        $unit->update(['is_active' => ! $unit->is_active]);
        AuditLog::record($unit->is_active ? 'published' : 'unpublished', 'AboutOrgUnit', $unit->id, ($unit->is_active ? 'បើកប្រើ' : 'បិទប្រើ').'អង្គភាព៖ '.$unit->name);

        return back()->with('status', 'បានផ្លាស់ប្តូរស្ថានភាពដោយជោគជ័យ។');
    }

    public function destroy(AboutOrgUnit $unit): RedirectResponse
    {
        $this->authorize('about.manage');

        // Children keep their rows and show at the top level of their group while the parent is trashed.
        $unit->delete();
        AuditLog::record('deleted', 'AboutOrgUnit', $unit->id, 'លុបអង្គភាព៖ '.$unit->name);

        return back()->with('status', 'បានលុបដោយជោគជ័យ។ អាចស្ដារវិញពីធុងសំរាម។');
    }

    public function restore(int $unit): RedirectResponse
    {
        $this->authorize('about.manage');
        $unit = AboutOrgUnit::onlyTrashed()->findOrFail($unit);

        $unit->restore();
        AuditLog::record('restored', 'AboutOrgUnit', $unit->id, 'ស្ដារអង្គភាព៖ '.$unit->name);

        return back()->with('status', 'បានស្ដារវិញដោយជោគជ័យ។');
    }

    public function forceDestroy(int $unit): RedirectResponse
    {
        $this->authorize('about.manage');
        $unit = AboutOrgUnit::onlyTrashed()->findOrFail($unit);

        $unit->forceDelete();
        AuditLog::record('force_deleted', 'AboutOrgUnit', $unit->id, 'លុបអង្គភាពជាអចិន្ត្រៃយ៍៖ '.$unit->name);

        return back()->with('status', 'បានលុបជាអចិន្ត្រៃយ៍។');
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
            AboutOrgUnit::whereKey($row['id'])->update(['sort_order' => $row['sort_order']]);
        }
        AuditLog::record('reordered', 'AboutOrgUnit', null, 'តម្រៀបលំដាប់រចនាសម្ព័ន្ធអង្គភាព');

        return response()->json(['status' => 'ok']);
    }

    /** Adds published hospitals / OD offices that are not linked yet, reusing the real facility records. */
    public function importFacilities(): RedirectResponse
    {
        $this->authorize('about.manage');

        $order = AboutOrgUnit::withTrashed()->max('sort_order') ?? 0;
        $facilities = $this->importableFacilities();
        foreach ($facilities as $facility) {
            AboutOrgUnit::create([
                'facility_id' => $facility->id,
                'name' => $facility->name_km,
                'type' => AboutOrgUnit::FACILITY_TYPE_MAP[$facility->facility_type],
                'is_active' => true,
                'sort_order' => ++$order,
            ]);
        }
        AuditLog::record('created', 'AboutOrgUnit', null, "នាំចូលអង្គភាពពីមូលដ្ឋានសុខាភិបាល ({$facilities->count()})");

        return back()->with('status', "បាននាំចូល {$facilities->count()} អង្គភាព។");
    }

    private function importableFacilities()
    {
        // Trashed links count as "already handled": an admin who deleted one should not see it return.
        $linked = AboutOrgUnit::withTrashed()->whereNotNull('facility_id')->pluck('facility_id');

        return Facility::published()->whereIn('facility_type', array_keys(AboutOrgUnit::FACILITY_TYPE_MAP))
            ->whereNotIn('id', $linked)->orderBy('name_km')->get();
    }

    private function form(AboutOrgUnit $unit): View
    {
        return view('admin.about.organization.form', [
            'unit' => $unit,
            // One nesting level: parents are top-level units other than this one.
            'parents' => AboutOrgUnit::whereNull('parent_id')->when($unit->exists, fn ($query) => $query->whereKeyNot($unit->id))
                ->orderBy('sort_order')->pluck('name', 'id'),
            'facilities' => Facility::orderBy('facility_type')->orderBy('name_km')->get(['id', 'name_km', 'facility_type', 'status'])
                ->mapWithKeys(fn ($facility) => [$facility->id => $facility->name_km.' — '.Facility::typeLabel($facility->facility_type).($facility->status === 'published' ? '' : ' (មិនទាន់ផ្សាយ)')]),
        ]);
    }

    private function validated(Request $request, ?AboutOrgUnit $unit = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(AboutOrgUnit::TYPES))],
            'parent_id' => ['nullable', 'integer', Rule::exists('about_org_units', 'id')->whereNull('parent_id')->whereNull('deleted_at')],
            'facility_id' => ['nullable', 'integer', Rule::exists('facilities', 'id')],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($unit && filled($data['parent_id'] ?? null)) {
            abort_if((int) $data['parent_id'] === $unit->id || $unit->children()->exists(), 422, 'អង្គភាពដែលមានអង្គភាពរង មិនអាចដាក់ក្រោមអង្គភាពផ្សេងបានទេ។');
        }
        $data['description'] = filled($data['description'] ?? null) ? strip_tags($data['description']) : null;
        $data['sort_order'] ??= AboutOrgUnit::count();
        $data['is_active'] = $request->boolean('is_active');
        $data['is_demo'] = false;

        return $data;
    }
}
