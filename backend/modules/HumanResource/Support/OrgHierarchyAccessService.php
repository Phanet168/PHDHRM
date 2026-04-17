<?php

namespace Modules\HumanResource\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\SystemRole;
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

        $allNull = false;
        $ids = [];
        foreach ($roles as $role) {
            $branchIds = $this->roleScopeBranchIds($role);
            if ($branchIds === null) {
                $allNull = true;
                break;
            }
            $ids = array_merge($ids, $branchIds);
        }

        if ($allNull) {
            return null; // 'all' scope
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
                return in_array((string) $role->org_role, [
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
                return in_array((string) $role->org_role, [
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
                => $this->isOwnOnlyDepartment($departmentId)
                    ? [$departmentId]
                    : $this->orgUnitRuleService->branchIdsIncludingSelf($departmentId),
        };
    }

    /**
     * Provincial hospital should manage own unit only.
     */
    protected function isOwnOnlyDepartment(int $departmentId): bool
    {
        $dept = Department::withoutGlobalScopes()
            ->with('unitType:id,code')
            ->select('id', 'unit_type_id')
            ->find($departmentId);

        if (!$dept) {
            return false;
        }

        $unitTypeCode = (string) optional($dept->unitType)->code;
        if ($unitTypeCode !== '') {
            return $unitTypeCode === 'provincial_hospital';
        }

        // Fallback for legacy data where relation/code is unavailable.
        return (int) $dept->unit_type_id === 6;
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

