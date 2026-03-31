<?php

namespace Modules\HumanResource\Support;

use App\Models\User;
use Illuminate\Support\Collection;
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

        $ids = [];
        foreach ($roles as $role) {
            $ids = array_merge($ids, $this->roleScopeBranchIds($role));
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

    protected function roleScopeBranchIds(UserOrgRole $role): array
    {
        $departmentId = (int) ($role->department_id ?? 0);
        if ($departmentId <= 0) {
            return [];
        }

        $scopeType = (string) ($role->scope_type ?: UserOrgRole::SCOPE_SELF_AND_CHILDREN);
        if ($scopeType === UserOrgRole::SCOPE_SELF) {
            return [$departmentId];
        }

        return $this->orgUnitRuleService->branchIdsIncludingSelf($departmentId);
    }
}

