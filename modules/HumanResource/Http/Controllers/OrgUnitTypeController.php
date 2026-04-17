<?php

namespace Modules\HumanResource\Http\Controllers;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\HumanResource\Entities\OrgUnitType;

class OrgUnitTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:read_department', ['only' => ['index']]);
        $this->middleware('permission:create_department', ['only' => ['store']]);
        $this->middleware('permission:update_department', ['only' => ['update']]);
        $this->middleware('permission:delete_department', ['only' => ['destroy']]);
    }

    public function index()
    {
        return view('humanresource::master-data.org-unit-types.index', [
            'org_unit_types' => OrgUnitType::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|regex:/^[a-z0-9_]+$/|unique:org_unit_types,code',
            'name_km' => 'required|string|max:150',
            'name' => 'required|string|max:150',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'is_active' => 'required|boolean',
        ]);

        OrgUnitType::create([
            'code' => strtolower(trim((string) $validated['code'])),
            'name_km' => trim((string) $validated['name_km']),
            'name' => trim((string) $validated['name']),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) $validated['is_active'],
        ]);

        Toastr::success('បានបង្កើតប្រភេទអង្គភាពដោយជោគជ័យ', 'ជោគជ័យ');
        return redirect()->route('org-unit-types.index');
    }

    public function update(Request $request, int $id)
    {
        $orgUnitType = OrgUnitType::query()->findOrFail($id);

        $validated = $request->validate([
            'code' => 'required|string|max:50|regex:/^[a-z0-9_]+$/|unique:org_unit_types,code,' . $orgUnitType->id,
            'name_km' => 'required|string|max:150',
            'name' => 'required|string|max:150',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'is_active' => 'required|boolean',
        ]);

        $orgUnitType->update([
            'code' => strtolower(trim((string) $validated['code'])),
            'name_km' => trim((string) $validated['name_km']),
            'name' => trim((string) $validated['name']),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) $validated['is_active'],
        ]);

        Toastr::success('បានកែប្រែប្រភេទអង្គភាពដោយជោគជ័យ', 'ជោគជ័យ');
        return redirect()->route('org-unit-types.index');
    }

    public function destroy(int $id)
    {
        $orgUnitType = OrgUnitType::query()->findOrFail($id);

        $inUse = \Modules\HumanResource\Entities\Department::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('unit_type_id', $orgUnitType->id)
            ->exists();

        if ($inUse) {
            return response()->json([
                'success' => false,
                'message' => 'ប្រភេទអង្គភាពនេះកំពុងប្រើប្រាស់ក្នុងអង្គភាព។ មិនអាចលុបបានទេ។',
            ], 422);
        }

        $orgUnitType->delete();

        return response()->json([
            'success' => true,
            'message' => 'បានលុបប្រភេទអង្គភាពដោយជោគជ័យ',
        ]);
    }
}

