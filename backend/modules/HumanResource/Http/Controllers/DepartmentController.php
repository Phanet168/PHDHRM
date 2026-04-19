<?php

namespace Modules\HumanResource\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\GovSalaryScale;
use Modules\HumanResource\Support\OrgUnitRuleService;

class DepartmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:read_department|read_sub_departments', ['only' => ['index', 'getSubDepartments', 'importTemplate']]);
        $this->middleware('permission:create_department|create_sub_departments', ['only' => ['store']]);
        $this->middleware('permission:update_department|update_sub_departments', ['only' => ['edit', 'update']]);
        $this->middleware('permission:delete_department|delete_sub_departments', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(Request $request, OrgUnitRuleService $orgUnitRuleService)
    {
        $selectedOrgUnitId = (int) $request->query('org_unit_id', 0);
        $hierarchyTree = $orgUnitRuleService->hierarchyTree();
        $selectedUnit = null;
        $selectedPath = null;
        $selectedChildCount = 0;
        $selectedEmployeeCount = 0;

        if ($selectedOrgUnitId > 0) {
            $selectedUnit = Department::query()
                ->with(['unitType', 'parentDept', 'sslType'])
                ->find($selectedOrgUnitId);
        }

        if ($selectedUnit) {
            $selectedPath = $this->findPathInTree($hierarchyTree, (int) $selectedUnit->id);
            $selectedChildCount = Department::query()
                ->where('parent_id', $selectedUnit->id)
                ->count();
            $selectedEmployeeCount = Employee::query()
                ->where('department_id', $selectedUnit->id)
                ->orWhere('sub_department_id', $selectedUnit->id)
                ->count();
        }

        $departments = Department::whereNull('parent_id')->with('unitType')->paginate(5);
        $sub_departments = Department::whereNotNull('parent_id')->get();

        return view('humanresource::department.index', [
            'departments' => $departments,
            'sub_departments' => $sub_departments,
            'unit_types' => $orgUnitRuleService->activeTypes(),
            'ssl_types' => $this->activeSslTypes(),
            'parent_units' => $orgUnitRuleService->hierarchyOptions(),
            'allowed_parent_types_map' => $orgUnitRuleService->allowedParentTypeIdsByChildType(),
            'hierarchy_tree' => $hierarchyTree,
            'selected_org_unit_id' => $selectedOrgUnitId,
            'selected_unit' => $selectedUnit,
            'selected_unit_path' => $selectedPath,
            'selected_unit_child_count' => $selectedChildCount,
            'selected_unit_employee_count' => $selectedEmployeeCount,
        ]);
    }

    public function getSubDepartments(OrgUnitRuleService $orgUnitRuleService)
    {
        $sub_departments = Department::whereNotNull('parent_id')->with(['parentDept', 'unitType'])->paginate();
        $departments = Department::whereNull('parent_id')->paginate(5);

        return view('humanresource::department.sub-department-index', [
            'departments' => $sub_departments,
            'all_departments' => $departments,
            'unit_types' => $orgUnitRuleService->activeTypes(),
            'ssl_types' => $this->activeSslTypes(),
            'parent_units' => $orgUnitRuleService->hierarchyOptions(),
            'allowed_parent_types_map' => $orgUnitRuleService->allowedParentTypeIdsByChildType(),
            'hierarchy_tree' => $orgUnitRuleService->hierarchyTree(),
        ]);
    }

    public function store(Request $request, OrgUnitRuleService $orgUnitRuleService)
    {
        $parentId = $request->parent_id ? (int) $request->parent_id : null;
        $locationCode = trim((string) $request->input('location_code', ''));
        if ($locationCode === '') {
            $locationCode = $this->generateLegacyChildLocationCode($parentId);
        }

        $request->merge([
            'location_code' => $locationCode !== '' ? $locationCode : null,
        ]);

        $request->validate([
            'department_name' => 'required|string|max:255',
            'unit_type_id' => 'required|integer|exists:org_unit_types,id',
            'ssl_type_id' => 'nullable|integer|exists:gov_salary_scales,id',
            'parent_id' => 'nullable|integer|exists:departments,id',
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
            'location_code' => 'nullable|string|max:100|unique:departments,location_code',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'geofence_latitude' => 'nullable|numeric|between:-90,90',
            'geofence_longitude' => 'nullable|numeric|between:-180,180',
            'geofence_radius_meters' => 'nullable|integer|min:50|max:50000',
        ]);

        $orgUnitRuleService->validateParentRule((int) $request->unit_type_id, $parentId);

        $department = Department::create($request->only([
            'department_name',
            'unit_type_id',
            'ssl_type_id',
            'sort_order',
            'location_code',
            'latitude',
            'longitude',
            'geofence_latitude',
            'geofence_longitude',
            'geofence_radius_meters',
            'parent_id',
            'is_active',
        ]));
        Toastr::success('Department added successfully :)', 'Success');
        return redirect()->route('departments.index', ['org_unit_id' => (int) $department->id]);
    }

    public function edit($id, OrgUnitRuleService $orgUnitRuleService)
    {
        $department = Department::with(['unitType', 'sslType'])->where('uuid', $id)->firstOrFail();
        $parentUnits = $orgUnitRuleService->hierarchyOptions($department->id);

        return view('humanresource::department.modal.edit', [
            'department' => $department,
            'unit_types' => $orgUnitRuleService->activeTypes(),
            'ssl_types' => $this->activeSslTypes(),
            'parent_units' => $parentUnits,
            'allowed_parent_types_map' => $orgUnitRuleService->allowedParentTypeIdsByChildType(),
        ]);
    }


    public function update(Request $request, $uuid, OrgUnitRuleService $orgUnitRuleService)
    {
        $department = Department::where('uuid', $uuid)->firstOrFail();

        $parentId = $request->parent_id ? (int) $request->parent_id : null;
        $locationCode = trim((string) $request->input('location_code', ''));
        if ($locationCode === '') {
            $locationCode = trim((string) ($department->location_code ?? ''));
            if ($locationCode === '') {
                $locationCode = $this->generateLegacyChildLocationCode($parentId, (int) $department->id);
            }
        }

        $request->merge([
            'location_code' => $locationCode !== '' ? $locationCode : null,
        ]);

        $request->validate([
            'department_name' => 'required|string|max:255',
            'unit_type_id' => 'required|integer|exists:org_unit_types,id',
            'ssl_type_id' => 'nullable|integer|exists:gov_salary_scales,id',
            'parent_id' => 'nullable|integer|exists:departments,id',
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
            'location_code' => 'nullable|string|max:100|unique:departments,location_code,' . $department->id,
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'geofence_latitude' => 'nullable|numeric|between:-90,90',
            'geofence_longitude' => 'nullable|numeric|between:-180,180',
            'geofence_radius_meters' => 'nullable|integer|min:50|max:50000',
        ]);

        $orgUnitRuleService->validateParentRule((int) $request->unit_type_id, $parentId, $department);

        $department->update($request->only([
            'department_name',
            'unit_type_id',
            'ssl_type_id',
            'sort_order',
            'location_code',
            'latitude',
            'longitude',
            'geofence_latitude',
            'geofence_longitude',
            'geofence_radius_meters',
            'parent_id',
            'is_active',
        ]));

        Toastr::success('Department Updated successfully :)', 'Success');
        return redirect()->route('departments.index', ['org_unit_id' => (int) $department->id]);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($uuid)
    {
        $department = Department::where('uuid', $uuid)->first();
        if (!$department) {
            Toastr::error('Department not found.', 'Error');
            return response()->json([
                'success' => false,
                'message' => 'Department not found.',
            ], 404);
        }

        $has_sub_departments = Department::where('parent_id', $department->id)->count();
        if ($has_sub_departments > 0) {
            Toastr::error('Department has already sub department. Can not delete.', 'Error');
            return response()->json([
                'success' => false,
                'message' => 'This org unit has sub units. Delete sub units first.',
            ], 422);
        }

        $hasEmployees = Employee::query()
            ->where('department_id', $department->id)
            ->orWhere('sub_department_id', $department->id)
            ->exists();

        if ($hasEmployees) {
            Toastr::error('Department has employees. Can not delete.', 'Error');
            return response()->json([
                'success' => false,
                'message' => 'This org unit has employees. Re-assign employees first.',
            ], 422);
        }

        $department->delete();
        Toastr::success('Department deleted successfully :)', 'Success');
        return response()->json([
            'success' => true,
            'message' => 'Department deleted successfully.',
        ]);
    }

    protected function findPathInTree(array $nodes, int $targetId): ?string
    {
        foreach ($nodes as $node) {
            if ((int) ($node['id'] ?? 0) === $targetId) {
                return $node['path'] ?? $node['name'] ?? null;
            }

            $children = $node['children'] ?? [];
            if (!empty($children)) {
                $path = $this->findPathInTree($children, $targetId);
                if ($path) {
                    return $path;
                }
            }
        }

        return null;
    }

    protected function generateLegacyChildLocationCode(?int $parentId, ?int $excludeDepartmentId = null): ?string
    {
        if (!$parentId) {
            return null;
        }

        $parent = Department::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->find($parentId);

        if (!$parent) {
            return null;
        }

        $parentCode = trim((string) ($parent->location_code ?? ''));
        if ($parentCode === '' || strpos($parentCode, 'LEGACY-WP-') !== 0) {
            return null;
        }

        $siblings = Department::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('parent_id', $parent->id)
            ->when($excludeDepartmentId, function ($query) use ($excludeDepartmentId) {
                $query->where('id', '!=', $excludeDepartmentId);
            })
            ->pluck('location_code');

        $maxSequence = 0;
        $prefix = $parentCode . '-';

        foreach ($siblings as $siblingCode) {
            $code = trim((string) $siblingCode);
            if ($code === '' || strpos($code, $prefix) !== 0) {
                continue;
            }

            $suffix = substr($code, strlen($prefix));
            if ($suffix === '' || !ctype_digit($suffix)) {
                continue;
            }

            $maxSequence = max($maxSequence, (int) $suffix);
        }

        return $prefix . ($maxSequence + 1);
    }

    public function getEmployees(Request $request, OrgUnitRuleService $orgUnitRuleService)
    {
        $workplaceId = (int) ($request->input('workplace_id') ?: $request->input('id'));
        if ($workplaceId <= 0) {
            return response()->json([]);
        }

        $branchIds = $orgUnitRuleService->branchIdsIncludingSelf($workplaceId);

        $employees = Employee::query()
            ->where(function ($q) use ($branchIds) {
                $q->whereIn('department_id', $branchIds)
                    ->orWhereIn('sub_department_id', $branchIds);
            })
            ->where('is_active', true)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'middle_name', 'employee_id']);
        return response()->json($employees);
    }

    public function importTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="org-unit-import-template.csv"',
        ];

        $columns = [
            'unit_name',
            'unit_type_code',
            'parent_location_code',
            'sort_order',
            'location_code',
            'latitude',
            'longitude',
            'status',
        ];

        $rows = [
            [
                'Provincial Health Department',
                'phd',
                '',
                '1',
                'PHD-KPT-001',
                '11.5612300',
                '104.8904500',
                'active',
            ],
            [
                'Administration Office',
                'office',
                'PHD-KPT-001',
                '1',
                'PHD-KPT-ADM',
                '11.5620000',
                '104.8910000',
                'active',
            ],
            [
                'Disease Control Program',
                'program',
                'PHD-KPT-ADM',
                '1',
                'PHD-KPT-ADM-PROG-001',
                '11.5623000',
                '104.8914000',
                'active',
            ],
            [
                'Operational District 01',
                'operational_district',
                'PHD-KPT-001',
                '2',
                'OD-KPT-01',
                '11.5900000',
                '104.9050000',
                'active',
            ],
            [
                'OD Planning Section',
                'od_section',
                'OD-KPT-01',
                '1',
                'OD-KPT-01-SEC-PLAN',
                '11.5912000',
                '104.9058000',
                'active',
            ],
            [
                'Health Center A',
                'health_center',
                'OD-KPT-01-SEC-PLAN',
                '1',
                'HC-KPT-001',
                '11.6020000',
                '104.9300000',
                'active',
            ],
            [
                'Health Post A1',
                'health_post',
                'HC-KPT-001',
                '1',
                'HP-KPT-001',
                '11.6030000',
                '104.9310000',
                'active',
            ],
        ];

        return response()->streamDownload(function () use ($columns, $rows) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($rows as $row) {
                fputcsv($file, $row);
            }

            fclose($file);
        }, 'org-unit-import-template.csv', $headers);
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
}
