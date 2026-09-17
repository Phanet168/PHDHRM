<?php

namespace Modules\HumanResource\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\SystemRole;
use Modules\HumanResource\Entities\UserAssignment;
use Modules\HumanResource\Entities\UserOrgRole;

class OrgHierarchyAccessService
{
    public function __construct(private readonly OrgUnitRuleService $orgUnitRuleService)
    {
    }

    public function isSystemAdmin(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ((int) $user->user_type_id === 1) {
            return true;
        }

        return method_exists($user, 'hasRole') && $user->hasRole('Super Admin');
    }

    public function effectiveOrgRoles(?User $user): Collection
    {
        if (!$user) {
            return collect();
        }

        $assignmentRoles = UserAssignment::query()
            ->withoutGlobalScope('sortByLatest')
            ->with('responsibility:id,code,can_approve')
            ->effective()
            ->where('user_id', (int) $user->id)
            ->orderByDesc('is_primary')
            ->orderBy('department_id')
            ->orderBy('id')
            ->get()
            ->map(function (UserAssignment $assignment) {
                $role = new UserOrgRole();
                $role->id = null;
                $role->user_id = (int) ($assignment->user_id ?? 0);
                $role->user_assignment_id = (int) ($assignment->id ?? 0);
                $role->department_id = (int) ($assignment->department_id ?? 0);
                $role->org_role = (string) ($assignment->responsibility?->code ?? '');
                $role->system_role_id = !empty($assignment->responsibility_id)
                    ? (int) $assignment->responsibility_id
                    : null;
                $role->scope_type = (string) ($assignment->scope_type ?: UserOrgRole::SCOPE_SELF_AND_CHILDREN);
                $role->is_active = (bool) ($assignment->is_active ?? true);
                if ($assignment->relationLoaded('responsibility') && $assignment->responsibility) {
                    $role->setRelation('systemRole', $assignment->responsibility);
                }

                return $role;
            })
            ->filter(function (UserOrgRole $role) {
                return (int) ($role->department_id ?? 0) > 0
                    && trim((string) $role->getEffectiveRoleCode()) !== '';
            })
            ->values();

        if ($assignmentRoles->isNotEmpty()) {
            return $assignmentRoles;
        }

        return UserOrgRole::query()
            ->withoutGlobalScope('sortByLatest')
            ->with('systemRole:id,code,can_approve')
            ->effective()
            ->where('user_id', (int) $user->id)
            ->orderBy('department_id')
            ->orderBy('org_role')
            ->get();
    }

    public function hasAnyOrgRoleAssignment(?User $user): bool
    {
        return $this->effectiveOrgRoles($user)->isNotEmpty();
    }

    /**
     * Phase 3B.2, section 20: reusable query-scope helper so report/list
     * endpoints stop copying `whereIn(department_id, ...)` scope logic (or,
     * worse, applying none at all when no department filter was submitted).
     *
     * Returns the effective department ids a report/list may show data for:
     * null means unrestricted (system admin, or an unrestricted user with no
     * specific filter requested). $requestedBranchIds lets a caller offer an
     * admin a unit picker (already expanded to a branch, e.g. via
     * OrgUnitRuleService::branchIdsIncludingSelf()) -- it can only NARROW a
     * restricted user's own scope (via intersection), never widen it, and a
     * restricted user who requests nothing still gets their own full scope
     * as the default instead of no restriction at all.
     *
     * @param int[]|null $requestedBranchIds
     * @return int[]|null
     */
    public function effectiveReportDepartmentIds(?User $user, ?array $requestedBranchIds = null): ?array
    {
        $managedBranchIds = $this->managedBranchIds($user);

        if ($managedBranchIds === null) {
            return $requestedBranchIds;
        }

        if ($requestedBranchIds === null) {
            return $managedBranchIds;
        }

        return array_values(array_intersect($managedBranchIds, $requestedBranchIds));
    }

    /**
     * Assert (abort 403) that a single department is within the user's
     * managed scope. No-op for system admins / unrestricted scope. Reusable
     * form of the inline check ReportController::staffAttendanceDetailReport()
     * already applied.
     */
    public function assertDepartmentInScope(?User $user, ?int $departmentId): void
    {
        $managedBranchIds = $this->managedBranchIds($user);
        if ($managedBranchIds === null) {
            return;
        }

        if (!$departmentId || !in_array($departmentId, $managedBranchIds, true)) {
            abort(403);
        }
    }

    /**
     * Return null when user is system admin, otherwise return managed branch ids.
     */
    public function managedBranchIds(?User $user): ?array
    {
        if ($this->isSystemAdmin($user)) {
            return null;
        }

        $roles = $this->effectiveOrgRoles($user);
        if ($roles->isEmpty()) {
            return [];
        }

        return $this->expandRolesToBranchIds($roles);
    }

    /**
     * Department ids the given user may act on for ONE specific responsibility
     * code (e.g. 'manager', 'head'), expanded per each matching assignment's
     * scope_type. Null means unrestricted (system admin, or a scope_type of
     * 'all' on a matching assignment); empty array means the user holds no
     * (or no unrestricted) assignment for that responsibility.
     *
     * Public wrapper so callers that need a single-responsibility view (e.g.
     * a mission/leave/notice approval queue keyed on "manager" vs "head")
     * don't need to re-implement scope-type expansion themselves -- see
     * Modules\HumanResource\Support\MissionAccess::departmentIdsForResponsibility()
     * for the original call site this was extracted from (Phase 3B.1).
     *
     * @return int[]|null
     */
    public function departmentIdsForResponsibility(?User $user, string $responsibilityCode): ?array
    {
        if ($this->isSystemAdmin($user)) {
            return null;
        }

        $roles = $this->effectiveOrgRoles($user)
            ->filter(fn (UserOrgRole $role) => $role->getEffectiveRoleCode() === $responsibilityCode);

        if ($roles->isEmpty()) {
            return [];
        }

        return $this->expandRolesToBranchIds($roles);
    }

    /**
     * @param Collection<int, UserOrgRole> $roles
     * @return int[]|null null means "all departments" (a matching role has scope_type 'all')
     */
    private function expandRolesToBranchIds(Collection $roles): ?array
    {
        $ids = [];
        foreach ($roles as $role) {
            $branchIds = $this->roleScopeBranchIds($role);
            if ($branchIds === null) {
                return null; // 'all' scope
            }
            $ids = array_merge($ids, $branchIds);
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    public function canManageDepartment(?User $user, int $departmentId): bool
    {
        if ($departmentId <= 0) {
            return false;
        }

        if ($this->isSystemAdmin($user)) {
            return true;
        }

        $roles = $this->effectiveOrgRoles($user)
            ->filter(function (UserOrgRole $role) {
                return in_array($role->getEffectiveRoleCode(), [
                    UserOrgRole::ROLE_HEAD,
                    UserOrgRole::ROLE_DEPUTY_HEAD,
                    UserOrgRole::ROLE_MANAGER,
                ], true);
            });

        foreach ($roles as $role) {
            if (in_array($departmentId, $this->roleScopeBranchIds($role), true)) {
                return true;
            }
        }

        return false;
    }

    public function canApproveDepartment(?User $user, int $departmentId): bool
    {
        if ($departmentId <= 0) {
            return false;
        }

        if ($this->isSystemAdmin($user)) {
            return true;
        }

        $roles = $this->effectiveOrgRoles($user)
            ->filter(function (UserOrgRole $role) {
                // Prefer systemRole.can_approve, fallback to old logic
                if ($role->systemRole) {
                    return (bool) $role->systemRole->can_approve;
                }
                return in_array($role->getEffectiveRoleCode(), [
                    UserOrgRole::ROLE_HEAD,
                    UserOrgRole::ROLE_DEPUTY_HEAD,
                ], true);
            });

        foreach ($roles as $role) {
            if (in_array($departmentId, $this->roleScopeBranchIds($role), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Public read-side wrapper around roleScopeBranchIds(), for presentation
     * layers (e.g. the Access Control Center's Organization Scope tab) that
     * need to preview what a given scope_type + department resolves to,
     * without duplicating this expansion logic elsewhere. Does not change
     * behavior for any existing caller of roleScopeBranchIds() itself.
     *
     * @return int[]|null null means "all departments" (the 'all' scope)
     */
    public function expandScopeBranchIds(string $scopeType, int $departmentId): ?array
    {
        $role = new UserOrgRole();
        $role->department_id = $departmentId;
        $role->scope_type = $scopeType;

        return $this->roleScopeBranchIds($role);
    }

    /**
     * Expand a role assignment to the department IDs it covers.
     *
     * @return int[]|null  null means all departments ('all' scope)
     */
    protected function roleScopeBranchIds(UserOrgRole $role): ?array
    {
        $departmentId = (int) ($role->department_id ?? 0);
        if ($departmentId <= 0) {
            return [];
        }

        $scopeType = (string) ($role->scope_type ?: UserOrgRole::SCOPE_SELF_AND_CHILDREN);

        return match ($scopeType) {
            UserOrgRole::SCOPE_SELF_ONLY,
            UserOrgRole::SCOPE_SELF,  // backward compat
                => [$departmentId],

            UserOrgRole::SCOPE_SELF_UNIT_ONLY
                => $this->siblingSameTypeIds($departmentId),

            UserOrgRole::SCOPE_ALL
                => null,

            default  // self_and_children
                // Use the same standard hierarchy expansion for hospitals and all other units.
                => $this->orgUnitRuleService->branchIdsIncludingSelf($departmentId),
        };
    }

    /**
     * Get sibling department IDs with the same unit_type under the same parent.
     */
    protected function siblingSameTypeIds(int $departmentId): array
    {
        $dept = Department::withoutGlobalScopes()
            ->select('id', 'parent_id', 'unit_type_id')
            ->find($departmentId);

        if (!$dept || !$dept->parent_id || !$dept->unit_type_id) {
            return [$departmentId];
        }

        return Department::withoutGlobalScopes()
            ->where('parent_id', $dept->parent_id)
            ->where('unit_type_id', $dept->unit_type_id)
            ->where('is_active', 1)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}

