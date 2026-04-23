<?php

namespace Modules\HumanResource\Http\Controllers;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use App\Models\User;
use Modules\HumanResource\Entities\SystemRole;
use Modules\HumanResource\Entities\UserOrgRole;
use Modules\HumanResource\Services\GovernanceAssignmentService;
use Modules\HumanResource\Support\OrgUnitRuleService;

class UserOrgRoleController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:read_org_governance|read_department', ['only' => ['index', 'userOptions']]);
        $this->middleware('permission:create_org_governance|create_department', ['only' => ['store']]);
        $this->middleware('permission:update_org_governance|update_department', ['only' => ['update']]);
        $this->middleware('permission:delete_org_governance|delete_department', ['only' => ['destroy']]);
    }

    public function index(Request $request, OrgUnitRuleService $orgUnitRuleService)
    {
        $selectedUserId = (int) $request->integer('user_id');
        $selectedStatus = $request->get('is_active', '');

        $roles = UserOrgRole::query()
            ->withoutGlobalScope('sortByLatest')
            ->with([
                'user:id,full_name,email',
                'department:id,department_name',
                'systemRole:id,code,name,name_km',
            ]);

        if ($selectedUserId > 0) {
            $roles->where('user_id', $selectedUserId);
        }

        if ($selectedStatus === '1' || $selectedStatus === '0') {
            $roles->where('is_active', (int) $selectedStatus);
        }

        $roles = $roles
            ->orderByDesc('is_active')
            ->orderBy('department_id')
            ->orderBy('org_role')
            ->orderBy('id')
            ->get();

        $departments = $orgUnitRuleService->hierarchyOptions();

        $systemRoles = SystemRole::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'code', 'name', 'name_km']);

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

        return view('humanresource::master-data.user-org-roles.index', [
            'roles' => $roles,
            'departments' => $departments,
            'system_roles' => $systemRoles,
            'org_role_options' => UserOrgRole::roleOptions(),
            'role_labels' => UserOrgRole::roleLabels(),
            'scope_options' => UserOrgRole::scopeOptions(),
            'selected_user_id' => $selectedUserId,
            'selected_status' => $selectedStatus,
            'selected_user_text' => $selectedUser ? $this->buildUserLabel($selectedUser) : '',
            'old_user_id' => $oldUserId,
            'old_user_text' => $oldUser ? $this->buildUserLabel($oldUser) : '',
            'canonical_assignments_route' => route('user-assignments.index', array_filter([
                'user_id' => $selectedUserId > 0 ? $selectedUserId : null,
            ])),
            'legacy_read_only' => true,
        ]);
    }

    public function userOptions(Request $request)
    {
        $keyword = trim((string) $request->get('q', ''));
        $page = max(1, (int) $request->get('page', 1));
        $perPage = 20;

        $query = User::query()
            ->withoutGlobalScope('sortByLatest')
            ->with('employee:id,user_id,employee_id,official_id_10')
            ->select(['id', 'full_name', 'email']);

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $like = '%' . $keyword . '%';
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

        $results = $users->map(function (User $user) {
            return [
                'id' => (string) $user->id,
                'text' => $this->buildUserLabel($user),
            ];
        })->values();

        return response()->json([
            'results' => $results,
            'pagination' => [
                'more' => ($page * $perPage) < $total,
            ],
        ]);
    }

    public function store(Request $request, GovernanceAssignmentService $assignmentService)
    {
        $validated = $request->validate($this->rules());
        $roleCode = trim((string) ($validated['org_role'] ?? ''));
        $systemRoleId = UserOrgRole::resolveSystemRoleIdByCode($roleCode);

        $exists = UserOrgRole::query()
            ->withoutGlobalScope('sortByLatest')
            ->where('user_id', (int) $validated['user_id'])
            ->where('department_id', (int) $validated['department_id'])
            ->where(function ($query) use ($roleCode, $systemRoleId) {
                $query->where('org_role', $roleCode);
                if ($systemRoleId) {
                    $query->orWhere('system_role_id', $systemRoleId);
                }
            })
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            return redirect()->back()
                ->withErrors([
                    'user_id' => localize('duplicate_org_role_assignment', 'អ្នកប្រើនេះមានតួនាទីដូចគ្នា ក្នុងអង្គភាពនេះរួចហើយ។'),
                ])
                ->withInput();
        }

        $assignmentService->upsertFromLegacyPayload([
            'user_id' => (int) $validated['user_id'],
            'department_id' => (int) $validated['department_id'],
            'org_role' => $roleCode,
            'system_role_id' => $systemRoleId,
            'scope_type' => (string) $validated['scope_type'],
            'effective_from' => !empty($validated['effective_from']) ? $validated['effective_from'] : null,
            'effective_to' => !empty($validated['effective_to']) ? $validated['effective_to'] : null,
            'is_active' => (bool) $validated['is_active'],
            'note' => !empty($validated['note']) ? trim((string) $validated['note']) : null,
            'is_primary' => false,
        ], null, auth()->id());

        Toastr::success(localize('data_save', 'Data saved'));
        return redirect()->route('user-org-roles.index');
    }

    public function update(
        Request $request,
        UserOrgRole $user_org_role,
        GovernanceAssignmentService $assignmentService
    )
    {
        $validated = $request->validate($this->rules());
        $roleCode = trim((string) $validated['org_role']);
        $systemRoleId = UserOrgRole::resolveSystemRoleIdByCode($roleCode);

        $exists = UserOrgRole::query()
            ->withoutGlobalScope('sortByLatest')
            ->where('id', '!=', (int) $user_org_role->id)
            ->where('user_id', (int) $validated['user_id'])
            ->where('department_id', (int) $validated['department_id'])
            ->where(function ($query) use ($roleCode, $systemRoleId) {
                $query->where('org_role', $roleCode);
                if ($systemRoleId) {
                    $query->orWhere('system_role_id', $systemRoleId);
                }
            })
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            return redirect()->back()
                ->withErrors([
                    'user_id' => localize('duplicate_org_role_assignment', 'អ្នកប្រើនេះមានតួនាទីដូចគ្នា ក្នុងអង្គភាពនេះរួចហើយ។'),
                ])
                ->withInput();
        }

        $assignmentService->upsertFromLegacyPayload([
            'user_id' => (int) $validated['user_id'],
            'department_id' => (int) $validated['department_id'],
            'org_role' => $roleCode,
            'system_role_id' => $systemRoleId,
            'scope_type' => (string) $validated['scope_type'],
            'effective_from' => !empty($validated['effective_from']) ? $validated['effective_from'] : null,
            'effective_to' => !empty($validated['effective_to']) ? $validated['effective_to'] : null,
            'is_active' => (bool) $validated['is_active'],
            'note' => !empty($validated['note']) ? trim((string) $validated['note']) : null,
            'is_primary' => false,
        ], $user_org_role, auth()->id());

        Toastr::success(localize('data_update', 'Data updated'));
        return redirect()->route('user-org-roles.index');
    }

    public function destroy(UserOrgRole $user_org_role, GovernanceAssignmentService $assignmentService)
    {
        $assignmentService->deleteByLegacyRecord($user_org_role, auth()->id());

        return response()->json([
            'success' => true,
            'message' => localize('data_delete', 'Deleted successfully'),
        ]);
    }

    protected function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
            'org_role' => ['required', Rule::in(UserOrgRole::roleOptions())],
            'scope_type' => ['required', Rule::in(UserOrgRole::scopeOptions())],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['required', 'boolean'],
            'note' => ['nullable', 'string'],
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
