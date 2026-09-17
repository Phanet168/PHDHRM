<?php

namespace Modules\Pharmaceutical\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\UserOrgRole;
use Modules\HumanResource\Services\GovernanceAssignmentService;
use Modules\Pharmaceutical\Traits\PharmScope;

class PharmUserController extends Controller
{
    use PharmScope;

    private function canManageUsers(): bool
    {
        $user = Auth::user();
        return (bool) ($user && (
            (int) $user->user_type_id === 1
            || (method_exists($user, 'hasRole') && $user->hasRole('Super Admin'))
        ));
    }

    /**
     * List all pharm-related user_org_roles (PHD/OD/Hospital/HC departments only).
     */
    public function index(Request $request)
    {
        abort_unless($this->canManageUsers(), 403);
        $level = $this->pharmLevel();

        $search = trim((string) $request->query('search', ''));
        $filterDept = (int) $request->query('department_id', 0);

        $pharmDeptIds = $this->pharmDepartmentIds();

        $query = UserOrgRole::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->whereIn('department_id', $pharmDeptIds)
            ->with(['user', 'department', 'systemRole']);

        if ($filterDept > 0) {
            $query->where('department_id', $filterDept);
        }

        if ($search !== '') {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $roles = $query->orderByDesc('is_active')
            ->orderByDesc('id')
            ->paginate(20)
            ->appends($request->query());

        $departments = Department::withoutGlobalScopes()
            ->whereIn('id', $pharmDeptIds)
            ->orderBy('unit_type_id')
            ->orderBy('department_name')
            ->get(['id', 'department_name', 'unit_type_id']);

        return view('pharmaceutical::users.index', [
            'roles' => $roles,
            'departments' => $departments,
            'roleLabels' => UserOrgRole::roleLabels(),
            'search' => $search,
            'filterDept' => $filterDept,
            'level' => $level,
        ]);
    }

    /**
     * Show create/add form.
     */
    public function create()
    {
        abort_unless($this->canManageUsers(), 403);
        $level = $this->pharmLevel();

        $pharmDeptIds = $this->pharmDepartmentIds();
        $departments = Department::withoutGlobalScopes()
            ->whereIn('id', $pharmDeptIds)
            ->orderBy('unit_type_id')
            ->orderBy('department_name')
            ->get(['id', 'department_name', 'unit_type_id']);

        return view('pharmaceutical::users.create', [
            'departments' => $departments,
            'orgRoles' => UserOrgRole::roleOptions(),
            'roleLabels' => UserOrgRole::roleLabels(),
            'scopeOptions' => UserOrgRole::scopeOptions(),
            'level' => $level,
        ]);
    }

    /**
     * Store new pharmacy user-role assignment.
     *
     * Phase 3B.2: previously wrote directly to UserOrgRole::create(),
     * bypassing GovernanceAssignmentService/UserAssignment entirely -- a
     * third independent generic-scope writer (alongside the now-closed
     * legacy /hr/user-org-roles screen). Now goes through the same
     * canonical service every other assignment screen uses, which also
     * keeps writing the UserOrgRole mirror row this controller's index()/
     * listing already reads, so no read-side change was needed. org_role
     * codes map 1:1 onto SystemRole codes (verified in Phase 3B.1), so no
     * capability is lost.
     */
    public function store(Request $request, GovernanceAssignmentService $assignmentService)
    {
        abort_unless($this->canManageUsers(), 403);

        $pharmDeptIds = $this->pharmDepartmentIds();

        $validated = $request->validate([
            'user_id'       => ['required', 'integer', 'exists:users,id'],
            'department_id' => ['required', 'integer', Rule::in($pharmDeptIds)],
            'org_role'      => ['required', Rule::in(UserOrgRole::roleOptions())],
            'scope_type'    => ['required', Rule::in(UserOrgRole::scopeOptions())],
            'note'          => ['nullable', 'string', 'max:500'],
        ]);

        // Check duplicate active assignment
        $exists = UserOrgRole::withoutGlobalScopes()
            ->where('user_id', $validated['user_id'])
            ->where('department_id', $validated['department_id'])
            ->where('is_active', true)
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'user_id' => localize('user_already_assigned', 'This user already has an active role at this department.'),
            ])->withInput();
        }

        $assignmentService->upsertFromLegacyPayload([
            'user_id'       => (int) $validated['user_id'],
            'department_id' => (int) $validated['department_id'],
            'org_role'      => $validated['org_role'],
            'scope_type'    => $validated['scope_type'],
            'is_active'     => true,
            'effective_from' => now()->toDateString(),
            'note'          => $validated['note'] ?? null,
        ], null, Auth::id());

        return redirect()
            ->route('pharmaceutical.users.index')
            ->with('success', localize('user_added_success', 'User has been added successfully.'));
    }

    /**
     * Toggle active/inactive.
     *
     * Phase 3B.2: routed through GovernanceAssignmentService so the linked
     * canonical UserAssignment row (if one exists) is updated in lockstep
     * instead of only the legacy mirror row.
     */
    public function toggle(string $roleUuid, GovernanceAssignmentService $assignmentService)
    {
        abort_unless($this->canManageUsers(), 403);

        $role = $this->findPharmRoleByUuid($roleUuid);
        $pharmDeptIds = $this->pharmDepartmentIds();
        if (!in_array((int) $role->department_id, $pharmDeptIds, true)) {
            abort(403);
        }

        $willBeActive = !$role->is_active;

        $assignmentService->upsertFromLegacyPayload([
            'user_id' => $role->user_id,
            'department_id' => $role->department_id,
            'org_role' => $role->org_role,
            'scope_type' => $role->scope_type,
            'effective_from' => optional($role->effective_from)->toDateString(),
            'effective_to' => optional($role->effective_to)->toDateString(),
            'is_active' => $willBeActive,
            'note' => $role->note,
        ], $role, Auth::id());

        $status = $willBeActive
            ? localize('user_activated', 'User activated.')
            : localize('user_deactivated', 'User deactivated.');

        return back()->with('success', $status);
    }

    /**
     * Delete pharmacy user-role assignment.
     *
     * Phase 3B.2: routed through GovernanceAssignmentService::deleteByLegacyRecord()
     * so the linked canonical UserAssignment row is deleted too, not just
     * the legacy mirror.
     */
    public function destroy(string $roleUuid, GovernanceAssignmentService $assignmentService)
    {
        abort_unless($this->canManageUsers(), 403);

        $role = $this->findPharmRoleByUuid($roleUuid);
        $pharmDeptIds = $this->pharmDepartmentIds();
        if (!in_array((int) $role->department_id, $pharmDeptIds, true)) {
            abort(403);
        }

        $assignmentService->deleteByLegacyRecord($role, Auth::id());

        return back()->with('success', localize('user_removed', 'User removed.'));
    }

    /**
     * AJAX: Search users for Select2.
     */
    public function searchUsers(Request $request)
    {
        abort_unless($this->canManageUsers(), 403);

        $keyword = trim((string) $request->query('q', ''));
        $limit = min(30, max(5, (int) $request->query('limit', 15)));

        $users = User::query()
            ->withoutGlobalScope('sortByLatest')
            ->when($keyword !== '', function ($q) use ($keyword) {
                $q->where(function ($sq) use ($keyword) {
                    $sq->where('full_name', 'like', "%{$keyword}%")
                       ->orWhere('email', 'like', "%{$keyword}%");
                });
            })
            ->orderBy('full_name')
            ->limit($limit)
            ->get(['id', 'full_name', 'email']);

        return response()->json([
            'results' => $users->map(fn ($u) => [
                'id'   => (int) $u->id,
                'text' => trim($u->full_name) . ($u->email ? " ({$u->email})" : ''),
            ])->values(),
        ]);
    }

    private function findPharmRoleByUuid(string $roleUuid): UserOrgRole
    {
        return UserOrgRole::withoutGlobalScopes()
            ->where('uuid', $roleUuid)
            ->firstOrFail();
    }

    /**
     * Get all PHD/OD/Hospital/HC department IDs.
     */
    private function pharmDepartmentIds(): array
    {
        return Department::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->whereIn('unit_type_id', [1, 4, 6, 7])
            ->where('is_active', 1)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();
    }
}
