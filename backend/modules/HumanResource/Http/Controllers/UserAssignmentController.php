<?php

namespace Modules\HumanResource\Http\Controllers;

use App\Models\User;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\HumanResource\Entities\Position;
use Modules\HumanResource\Entities\SystemRole;
use Modules\HumanResource\Entities\UserAssignment;
use Modules\HumanResource\Http\Requests\StoreUserAssignmentRequest;
use Modules\HumanResource\Http\Requests\UpdateUserAssignmentRequest;
use Modules\HumanResource\Services\GovernanceAssignmentService;
use Modules\HumanResource\Services\ResponsibilityTemplateService;
use Modules\HumanResource\Support\OrgUnitRuleService;

class UserAssignmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:read_org_governance|read_department')->only(['index', 'userOptions', 'userPlacement']);
        $this->middleware('permission:create_org_governance|create_department')->only(['store']);
        $this->middleware('permission:update_org_governance|update_department')->only(['update']);
        $this->middleware('permission:delete_org_governance|delete_department')->only(['destroy']);
    }

    public function index(
        Request $request,
        OrgUnitRuleService $orgUnitRuleService,
        ResponsibilityTemplateService $templateService
    )
    {
        $selectedUserId = (int) $request->integer('user_id');
        $selectedStatus = (string) $request->query('is_active', '');

        $query = UserAssignment::query()
            ->withoutGlobalScope('sortByLatest')
            ->with([
                'user:id,full_name,email',
                'department:id,department_name',
                'position:id,position_name,position_name_km',
                'responsibilityTemplate:id,module_key,template_key,name,name_km,responsibility_id',
                'responsibility:id,code,name,name_km',
                'legacyOrgRole:id,user_assignment_id,user_id,department_id,org_role,system_role_id,scope_type,is_active',
            ]);

        if ($selectedUserId > 0) {
            $query->where('user_id', $selectedUserId);
        }

        if ($selectedStatus === '1' || $selectedStatus === '0') {
            $query->where('is_active', (int) $selectedStatus);
        }

        $assignments = $query
            ->orderByDesc('is_primary')
            ->orderBy('department_id')
            ->orderBy('id')
            ->get();

        $departments = $orgUnitRuleService->hierarchyOptions();
        $positions = Position::query()
            ->withoutGlobalScope('sortByLatest')
            ->where('is_active', 1)
            ->orderBy('position_name')
            ->get(['id', 'position_name', 'position_name_km']);
        $responsibilities = SystemRole::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'code', 'name', 'name_km']);
        $templateGroups = $templateService->groupedOptions();
        $templateModuleOptions = collect($templateService->moduleOptions())
            ->merge(array_keys($templateGroups))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $selectedUser = null;
        if ($selectedUserId > 0) {
            $selectedUser = User::query()
                ->withoutGlobalScope('sortByLatest')
                ->with('employee:id,user_id,employee_id,official_id_10')
                ->find($selectedUserId, ['id', 'full_name', 'email']);
        }

        $oldUserId = (int) $request->session()->getOldInput('user_id', 0);
        $oldUser = null;
        if ($oldUserId > 0) {
            $oldUser = User::query()
                ->withoutGlobalScope('sortByLatest')
                ->with('employee:id,user_id,employee_id,official_id_10')
                ->find($oldUserId, ['id', 'full_name', 'email']);
        }

        return view('humanresource::master-data.user-assignments.index', [
            'assignments' => $assignments,
            'departments' => $departments,
            'positions' => $positions,
            'responsibilities' => $responsibilities,
            'template_groups' => $templateGroups,
            'template_module_options' => $templateModuleOptions,
            'scope_options' => UserAssignment::scopeOptions(),
            'scope_labels' => $this->scopeLabels(),
            'selected_user_id' => $selectedUserId,
            'selected_status' => $selectedStatus,
            'selected_user_text' => $selectedUser ? $this->buildUserLabel($selectedUser) : '',
            'old_user_id' => $oldUserId,
            'old_user_text' => $oldUser ? $this->buildUserLabel($oldUser) : '',
            'legacy_index_route' => route('user-org-roles.index', array_filter([
                'user_id' => $selectedUserId > 0 ? $selectedUserId : null,
            ])),
        ]);
    }

    public function userOptions(Request $request): JsonResponse
    {
        $keyword = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 20;

        $query = User::query()
            ->withoutGlobalScope('sortByLatest')
            ->with('employee:id,user_id,employee_id,official_id_10')
            ->select(['id', 'full_name', 'email']);

        if ($keyword !== '') {
            $like = '%' . $keyword . '%';
            $query->where(function ($q) use ($like) {
                $q->where('full_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhereHas('employee', function ($eq) use ($like) {
                        $eq->where('employee_id', 'like', $like)
                            ->orWhere('official_id_10', 'like', $like);
                    });
            });
        }

        $total = (clone $query)->count();
        $users = $query
            ->orderBy('full_name')
            ->forPage($page, $perPage)
            ->get();

        return response()->json([
            'results' => $users->map(function (User $user) {
                return [
                    'id' => (string) $user->id,
                    'text' => $this->buildUserLabel($user),
                ];
            })->values(),
            'pagination' => [
                'more' => ($page * $perPage) < $total,
            ],
        ]);
    }

    public function userPlacement(User $user): JsonResponse
    {
        $user = User::query()
            ->withoutGlobalScope('sortByLatest')
            ->with([
                'employee:id,user_id,department_id,sub_department_id,position_id',
                'employee.primaryUnitPosting' => function ($query): void {
                    $query->select([
                        'employee_unit_postings.id',
                        'employee_unit_postings.employee_id',
                        'employee_unit_postings.department_id',
                        'employee_unit_postings.position_id',
                    ]);
                },
            ])
            ->find((int) $user->id, ['id']);

        if (!$user || !$user->employee) {
            return response()->json([
                'department_id' => null,
                'position_id' => null,
            ]);
        }

        $employee = $user->employee;
        $departmentId = (int) ($employee->primaryUnitPosting?->department_id
            ?: $employee->sub_department_id
            ?: $employee->department_id
            ?: 0);
        $positionId = (int) ($employee->primaryUnitPosting?->position_id
            ?: $employee->position_id
            ?: 0);

        return response()->json([
            'department_id' => $departmentId > 0 ? $departmentId : null,
            'position_id' => $positionId > 0 ? $positionId : null,
        ]);
    }

    public function store(StoreUserAssignmentRequest $request, GovernanceAssignmentService $service)
    {
        $service->createFromCanonicalPayload($request->validated(), auth()->id());

        Toastr::success(localize('data_save', 'Data saved'));
        return redirect()->route('user-assignments.index');
    }

    public function update(
        UpdateUserAssignmentRequest $request,
        UserAssignment $user_assignment,
        GovernanceAssignmentService $service
    ) {
        $service->updateFromCanonicalPayload($user_assignment, $request->validated(), auth()->id());

        Toastr::success(localize('data_update', 'Data updated'));
        return redirect()->route('user-assignments.index');
    }

    public function destroy(UserAssignment $user_assignment, GovernanceAssignmentService $service): JsonResponse
    {
        $service->deleteAssignment($user_assignment, auth()->id());

        return response()->json([
            'success' => true,
            'message' => localize('data_delete', 'Deleted successfully'),
        ]);
    }

    protected function scopeLabels(): array
    {
        return [
            UserAssignment::SCOPE_SELF_ONLY => localize('scope_self_only', 'Self only'),
            UserAssignment::SCOPE_SELF_UNIT_ONLY => localize('scope_self_unit_only', 'Self unit only'),
            UserAssignment::SCOPE_SELF_AND_CHILDREN => localize('scope_self_and_children', 'Self and children'),
            UserAssignment::SCOPE_ALL => localize('scope_all', 'All'),
        ];
    }

    private function buildUserLabel(User $user): string
    {
        $name = trim((string) $user->full_name);
        $email = trim((string) $user->email);

        $employeeCode = '';
        if ($user->relationLoaded('employee') && $user->employee) {
            $employeeCode = trim((string) ($user->employee->official_id_10 ?: $user->employee->employee_id));
        } elseif ($user->employee) {
            $employeeCode = trim((string) ($user->employee->official_id_10 ?: $user->employee->employee_id));
        }

        $parts = [];
        if ($employeeCode !== '') {
            $parts[] = $employeeCode;
        }
        if ($name !== '') {
            $parts[] = $name;
        }
        if ($email !== '') {
            $parts[] = '(' . $email . ')';
        }

        return trim(implode(' - ', array_filter($parts)));
    }
}
