<?php

namespace Modules\Pharmaceutical\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\UserOrgRole;
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
     * Store new user_org_role.
     */
    public function store(Request $request)
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

        UserOrgRole::create([
            'user_id'       => (int) $validated['user_id'],
            'department_id' => (int) $validated['department_id'],
            'org_role'      => $validated['org_role'],
            'system_role_id' => UserOrgRole::resolveSystemRoleIdByCode((string) $validated['org_role']),
            'scope_type'    => $validated['scope_type'],
            'is_active'     => true,
            'effective_from' => now()->toDateString(),
            'note'          => $validated['note'] ?? null,
            'created_by'    => Auth::id(),
            'updated_by'    => Auth::id(),
        ]);

        return redirect()
            ->route('pharmaceutical.users.index')
            ->with('success', localize('user_added_success', 'User has been added successfully.'));
    }

    /**
     * Toggle active/inactive.
     */
    public function toggle(string $roleUuid)
    {
        abort_unless($this->canManageUsers(), 403);

        $role = $this->findPharmRoleByUuid($roleUuid);
        $pharmDeptIds = $this->pharmDepartmentIds();
        if (!in_array((int) $role->department_id, $pharmDeptIds, true)) {
            abort(403);
        }

        $role->update([
            'is_active'   => !$role->is_active,
            'updated_by'  => Auth::id(),
        ]);

        $status = $role->is_active
            ? localize('user_activated', 'User activated.')
            : localize('user_deactivated', 'User deactivated.');

        return back()->with('success', $status);
    }

    /**
     * Delete user_org_role.
     */
    public function destroy(string $roleUuid)
    {
        abort_unless($this->canManageUsers(), 403);

        $role = $this->findPharmRoleByUuid($roleUuid);
        $pharmDeptIds = $this->pharmDepartmentIds();
        if (!in_array((int) $role->department_id, $pharmDeptIds, true)) {
            abort(403);
        }

        $role->update(['deleted_by' => Auth::id()]);
        $role->delete();

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
