<?php

namespace Modules\HumanResource\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Models\User;
use Modules\HumanResource\Entities\SystemRole;
use Modules\HumanResource\Entities\UserOrgRole;
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

    /**
     * Phase 3B.1 (scope consolidation): this screen's org_role taxonomy is a
     * 1:1 alias of SystemRole codes (verified: UserOrgRole::roleOptions()
     * and SystemRole::pluck('code') are the exact same 10 codes), and every
     * existing user_org_roles row is already synced to a canonical
     * UserAssignment row (upsertFromLegacyPayload()/syncLegacyRoleFromAssignment()
     * in GovernanceAssignmentService always resolve/create one). So this
     * screen has NO unique data-creation capability left -- the view already
     * renders it read-only (`legacy_read_only` -- see index()), but that is
     * presentation only. Per this project's own security principle ("hiding
     * a menu is not security"), the write actions themselves must also
     * refuse, not just be hidden, so a direct request to these routes can no
     * longer create an independent, unpinned generic-scope write path
     * alongside the Access Control Center's Organization Scope tab and
     * modules/HumanResource/Http/Controllers/UserAssignmentController.php.
     */
    private function legacyWriteBlockedMessage(): string
    {
        return localize(
            'user_org_roles_write_disabled',
            'អេក្រង់នេះលែងអាចបង្កើត/កែប្រែ/លុបបានទៀតហើយ។ សូមប្រើ "មជ្ឈមណ្ឌលគ្រប់គ្រងសិទ្ធិ > វិសាលភាពអង្គភាព" ដើម្បីកែប្រែវិសាលភាព ឬប្រើអេក្រង់ "ការចាត់តាំង (User Assignments)" ដើម្បីបង្កើតការទទួលខុសត្រូវថ្មី។'
        );
    }

    public function store(): \Illuminate\Http\RedirectResponse
    {
        return redirect()->route('user-org-roles.index')
            ->withErrors(['user_id' => $this->legacyWriteBlockedMessage()]);
    }

    public function update(UserOrgRole $user_org_role): \Illuminate\Http\RedirectResponse
    {
        return redirect()->route('user-org-roles.index')
            ->withErrors(['user_id' => $this->legacyWriteBlockedMessage()]);
    }

    public function destroy(UserOrgRole $user_org_role): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->legacyWriteBlockedMessage(),
        ], 422);
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
