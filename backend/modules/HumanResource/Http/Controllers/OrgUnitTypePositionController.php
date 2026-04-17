<?php

namespace Modules\HumanResource\Http\Controllers;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\HumanResource\Entities\OrgUnitType;
use Modules\HumanResource\Entities\OrgUnitTypePosition;
use Modules\HumanResource\Entities\Position;

class OrgUnitTypePositionController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:read_positions', ['only' => ['index']]);
        $this->middleware('permission:create_positions', ['only' => ['store']]);
        $this->middleware('permission:update_positions', ['only' => ['update']]);
        $this->middleware('permission:delete_positions', ['only' => ['destroy']]);
    }

    public function index()
    {
        $items = OrgUnitTypePosition::query()
            ->with([
                'unitType:id,name,name_km',
                'position:id,position_name,position_name_km,position_rank',
            ])
            ->orderBy('unit_type_id')
            ->orderByRaw('CASE WHEN hierarchy_rank IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('hierarchy_rank')
            ->orderBy('id')
            ->get();

        $unitTypes = OrgUnitType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'name_km']);

        $positions = Position::query()
            ->withoutGlobalScope('sortByLatest')
            ->where('is_active', true)
            ->orderByRaw('CASE WHEN position_rank IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('position_rank')
            ->orderBy('position_name')
            ->get(['id', 'position_name', 'position_name_km', 'position_rank']);

        return view('humanresource::master-data.org-unit-type-positions.index', [
            'items' => $items,
            'unitTypes' => $unitTypes,
            'positions' => $positions,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        $exists = OrgUnitTypePosition::query()
            ->where('unit_type_id', (int) $validated['unit_type_id'])
            ->where('position_id', (int) $validated['position_id'])
            ->exists();

        if ($exists) {
            return redirect()->back()
                ->withErrors([
                    'position_id' => localize('duplicate_mapping', 'This org unit type already has this position mapping.'),
                ])
                ->withInput();
        }

        OrgUnitTypePosition::create([
            'unit_type_id' => (int) $validated['unit_type_id'],
            'position_id' => (int) $validated['position_id'],
            'hierarchy_rank' => !empty($validated['hierarchy_rank']) ? (int) $validated['hierarchy_rank'] : null,
            'is_leadership' => (bool) $validated['is_leadership'],
            'can_approve' => (bool) $validated['can_approve'],
            'is_active' => (bool) $validated['is_active'],
            'note' => !empty($validated['note']) ? trim((string) $validated['note']) : null,
        ]);

        Toastr::success(localize('data_save', 'Data saved'));
        return redirect()->route('org-unit-type-positions.index');
    }

    public function update(Request $request, OrgUnitTypePosition $org_unit_type_position)
    {
        $validated = $request->validate($this->rules());

        $exists = OrgUnitTypePosition::query()
            ->where('id', '!=', (int) $org_unit_type_position->id)
            ->where('unit_type_id', (int) $validated['unit_type_id'])
            ->where('position_id', (int) $validated['position_id'])
            ->exists();

        if ($exists) {
            return redirect()->back()
                ->withErrors([
                    'position_id' => localize('duplicate_mapping', 'This org unit type already has this position mapping.'),
                ])
                ->withInput();
        }

        $org_unit_type_position->update([
            'unit_type_id' => (int) $validated['unit_type_id'],
            'position_id' => (int) $validated['position_id'],
            'hierarchy_rank' => !empty($validated['hierarchy_rank']) ? (int) $validated['hierarchy_rank'] : null,
            'is_leadership' => (bool) $validated['is_leadership'],
            'can_approve' => (bool) $validated['can_approve'],
            'is_active' => (bool) $validated['is_active'],
            'note' => !empty($validated['note']) ? trim((string) $validated['note']) : null,
        ]);

        Toastr::success(localize('data_update', 'Data updated'));
        return redirect()->route('org-unit-type-positions.index');
    }

    public function destroy(OrgUnitTypePosition $org_unit_type_position)
    {
        $org_unit_type_position->delete();

        return response()->json([
            'success' => true,
            'message' => localize('data_delete', 'Deleted successfully'),
        ]);
    }

    protected function rules(): array
    {
        return [
            'unit_type_id' => ['required', 'integer', Rule::exists('org_unit_types', 'id')],
            'position_id' => ['required', 'integer', Rule::exists('positions', 'id')],
            'hierarchy_rank' => ['nullable', 'integer', 'min:1', 'max:255'],
            'is_leadership' => ['required', 'boolean'],
            'can_approve' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
