<?php

namespace Modules\HumanResource\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Modules\HumanResource\DataTables\SubDepartmentDataTable;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\EmployeeUnitPosting;
use Modules\HumanResource\Entities\GovSalaryScale;
use Modules\HumanResource\Support\OrgUnitRuleService;

class DivisionController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:read_sub_departments', ['only' => ['index']]);
        $this->middleware('permission:create_sub_departments', ['only' => ['store']]);
        $this->middleware('permission:update_sub_departments', ['only' => ['edit', 'update']]);
        $this->middleware('permission:delete_sub_departments', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(SubDepartmentDataTable $dataTable, OrgUnitRuleService $orgUnitRuleService)
    {
        $departments = $orgUnitRuleService->hierarchyOptions();

        return $dataTable->render('humanresource::division.index', [
            'departments' => $departments,
            'unit_types' => $orgUnitRuleService->activeTypes(),
            'ssl_types' => $this->activeSslTypes(),
            'allowed_parent_types_map' => $orgUnitRuleService->allowedParentTypeIdsByChildType(),
            'hierarchy_tree' => $orgUnitRuleService->hierarchyTree(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request, OrgUnitRuleService $orgUnitRuleService)
    {
        $parentId = $request->parent_id ? (int) $request->parent_id : null;

        $request->validate([
            'division_name' => 'required|string|max:255',
            'unit_type_id' => 'required|integer|exists:org_unit_types,id',
            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
                'max:9999',
                Rule::unique('departments', 'sort_order')->where(function ($query) use ($parentId) {
                    if ($parentId) {
                        return $query->where('parent_id', $parentId);
                    }

                    return $query->whereNull('parent_id');
                }),
            ],
            'ssl_type_id' => 'nullable|integer|exists:gov_salary_scales,id',
            'parent_id' => 'nullable|integer|exists:departments,id',
            'location_code' => 'nullable|string|max:100|unique:departments,location_code',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $orgUnitRuleService->validateParentRule(
            (int) $request->unit_type_id,
            $parentId
        );

        $department = new Department();
        $department->department_name = $request->division_name;
        $department->unit_type_id = $request->unit_type_id;
        $department->sort_order = (int) ($request->sort_order ?? 0);
        $department->ssl_type_id = $this->nullableInt($request->ssl_type_id);
        $department->location_code = $this->nullIfEmpty($request->location_code);
        $department->latitude = $this->nullIfEmpty($request->latitude);
        $department->longitude = $this->nullIfEmpty($request->longitude);
        $department->parent_id = $this->nullableInt($request->parent_id);
        $department->is_active = $request->has('is_active') ? (int) $request->is_active : 1;
        $department->save();

        Toastr::success('Division added successfully :)', 'Success');
        return redirect()->route('divisions.index');
    }

    public function edit($uuid, OrgUnitRuleService $orgUnitRuleService)
    {
        $division = Department::with(['unitType', 'sslType'])->where('uuid', $uuid)->firstOrFail();
        $departments = $orgUnitRuleService->hierarchyOptions($division->id);

        return view('humanresource::division.modal.edit', [
            'division' => $division,
            'departments' => $departments,
            'unit_types' => $orgUnitRuleService->activeTypes(),
            'ssl_types' => $this->activeSslTypes(),
            'allowed_parent_types_map' => $orgUnitRuleService->allowedParentTypeIdsByChildType(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(Request $request, $uuid, OrgUnitRuleService $orgUnitRuleService)
    {
        $department = Department::where('uuid', $uuid)->firstOrFail();
        $parentId = $request->parent_id ? (int) $request->parent_id : null;

        $request->validate([
            'division_name' => 'required|string|max:255',
            'unit_type_id' => 'required|integer|exists:org_unit_types,id',
            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
                'max:9999',
                Rule::unique('departments', 'sort_order')
                    ->ignore($department->id)
                    ->where(function ($query) use ($parentId) {
                        if ($parentId) {
                            return $query->where('parent_id', $parentId);
                        }

                        return $query->whereNull('parent_id');
                    }),
            ],
            'ssl_type_id' => 'nullable|integer|exists:gov_salary_scales,id',
            'parent_id' => 'nullable|integer|exists:departments,id',
            'location_code' => 'nullable|string|max:100|unique:departments,location_code,' . $department->id,
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $orgUnitRuleService->validateParentRule(
            (int) $request->unit_type_id,
            $parentId,
            $department
        );

        $department->department_name = $request->division_name;
        $department->unit_type_id = $request->unit_type_id;
        $department->sort_order = (int) ($request->sort_order ?? 0);
        $department->ssl_type_id = $this->nullableInt($request->ssl_type_id);
        $department->location_code = $this->nullIfEmpty($request->location_code);
        $department->latitude = $this->nullIfEmpty($request->latitude);
        $department->longitude = $this->nullIfEmpty($request->longitude);
        $department->parent_id = $this->nullableInt($request->parent_id);
        $department->is_active = $request->has('is_active') ? (int) $request->is_active : 1;
        $department->update();

        Toastr::success('Division Updated successfully :)', 'Success');
        return redirect()->route('divisions.index');
    }

    /**
     * Remove the specified resource from storage.
     * @param int $uuid
     * @return Renderable
     */
    public function destroy($uuid)
    {
        $division = Department::withTrashed()->where('uuid', $uuid)->firstOrFail();

        $hasChildren = Department::withTrashed()
            ->where('parent_id', $division->id)
            ->exists();

        if ($hasChildren) {
            return response()->json([
                'message' => 'Cannot delete this unit because it still has child units.',
            ], 422);
        }

        $isUsedByEmployees = Employee::query()
            ->where('department_id', $division->id)
            ->orWhere('sub_department_id', $division->id)
            ->exists();

        if ($isUsedByEmployees) {
            return response()->json([
                'message' => 'Cannot delete this unit because it is assigned to employees.',
            ], 422);
        }

        $isUsedByPostings = EmployeeUnitPosting::query()
            ->where('department_id', $division->id)
            ->exists();

        if ($isUsedByPostings) {
            return response()->json([
                'message' => 'Cannot delete this unit because it is used in unit postings.',
            ], 422);
        }

        $division->forceDelete();

        Toastr::success('Division deleted successfully :)', 'Success');
        return response()->json(['success' => 'success']);
    }

    protected function activeSslTypes()
    {
        if (!Schema::hasTable('gov_salary_scales')) {
            return collect();
        }

        return GovSalaryScale::query()
            ->where('is_active', true)
            ->get(['id', 'name_en', 'name_km']);
    }

    protected function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    protected function nullIfEmpty($value)
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $trimmed;
    }
}
