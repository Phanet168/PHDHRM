<?php

namespace Modules\HumanResource\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Modules\HumanResource\Entities\OrgRoleModulePermission;
use Modules\HumanResource\Entities\SystemRole;
use Modules\HumanResource\Entities\UserOrgRole;

class OrgRolePermissionService
{
    protected array $actionRolesCache = [];

    public function __construct(
        protected OrgHierarchyAccessService $orgHierarchyAccessService,
        protected OrgUnitRuleService $orgUnitRuleService
    ) {}

    public function hasConfiguredAction(string $moduleKey, string $actionKey): bool
    {
        $roles = $this->configuredRolesForAction($moduleKey, $actionKey);
        return $roles->isNotEmpty();
    }

    public function configuredRolesForAction(string $moduleKey, string $actionKey): Collection
    {
        $moduleKey = trim(mb_strtolower($moduleKey));
        $actionKey = trim(mb_strtolower($actionKey));
        $cacheKey = $moduleKey . '::' . $actionKey;

        if (!array_key_exists($cacheKey, $this->actionRolesCache)) {
            if (!Schema::hasTable('org_role_module_permissions')) {
                $this->actionRolesCache[$cacheKey] = collect();
                return collect();
            }

            $this->actionRolesCache[$cacheKey] = OrgRoleModulePermission::query()
                ->active()
                ->where('module_key', $moduleKey)
                ->where('action_key', $actionKey)
                ->get()
                ->map(function (OrgRoleModulePermission $p) {
                    // Prefer system_role code, fallback to old org_role string
                    if ($p->system_role_id) {
                        $sr = SystemRole::find($p->system_role_id);
                        return $sr ? $sr->code : (string) $p->org_role;
                    }
                    return (string) $p->org_role;
                })
                ->filter()
                ->unique()
                ->values();
        }

        return collect($this->actionRolesCache[$cacheKey]);
    }

    public function canUserPerform(
        ?User $user,
        string $moduleKey,
        string $actionKey,
        ?int $targetDepartmentId = null,
        array $fallbackRoles = []
    ): bool {
        if (!$user) {
            return false;
        }

        if ($this->orgHierarchyAccessService->isSystemAdmin($user)) {
            return true;
        }

        $configuredRoles = $this->configuredRolesForAction($moduleKey, $actionKey);
        $requiredRoles = $configuredRoles->isNotEmpty()
            ? $configuredRoles->all()
            : array_values(array_unique(array_filter($fallbackRoles)));

        if (empty($requiredRoles)) {
            return false;
        }

        $effectiveRoles = $this->orgHierarchyAccessService
            ->effectiveOrgRoles($user)
            ->filter(function (UserOrgRole $role) use ($requiredRoles) {
                $code = $role->getEffectiveRoleCode();
                return in_array($code, $requiredRoles, true);
            });

        if ($effectiveRoles->isEmpty()) {
            return false;
        }

        $targetDepartmentId = (int) ($targetDepartmentId ?? 0);
        if ($targetDepartmentId <= 0) {
            return true;
        }

        foreach ($effectiveRoles as $role) {
            $roleDepartmentId = (int) ($role->department_id ?? 0);
            if ($roleDepartmentId <= 0) {
                continue;
            }

            $scopeType = (string) ($role->scope_type ?: UserOrgRole::SCOPE_SELF_AND_CHILDREN);

            // 'all' scope means access to everything
            if ($scopeType === UserOrgRole::SCOPE_ALL) {
                return true;
            }

            if ($scopeType === UserOrgRole::SCOPE_SELF_ONLY || $scopeType === UserOrgRole::SCOPE_SELF) {
                if ($roleDepartmentId === $targetDepartmentId) {
                    return true;
                }
                continue;
            }

            if ($scopeType === UserOrgRole::SCOPE_SELF_UNIT_ONLY) {
                $branchIds = $this->orgHierarchyAccessService->managedBranchIds($user);
                if ($branchIds === null || in_array($targetDepartmentId, $branchIds, true)) {
                    return true;
                }
                continue;
            }

            // self_and_children (default)
            $branchIds = $this->orgUnitRuleService->branchIdsIncludingSelf($roleDepartmentId);
            if (in_array($targetDepartmentId, $branchIds, true)) {
                return true;
            }
        }

        return false;
    }
}
