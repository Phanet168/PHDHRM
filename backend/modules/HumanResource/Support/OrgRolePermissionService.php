<?php

namespace Modules\HumanResource\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\OrgRoleModulePermission;
use Modules\HumanResource\Entities\ResponsibilityTemplate;
use Modules\HumanResource\Entities\UserAssignment;
use Modules\HumanResource\Entities\UserOrgRole;

class OrgRolePermissionService
{
    protected array $actionRolesCache = [];

    public function __construct(
        protected OrgHierarchyAccessService $orgHierarchyAccessService,
        protected OrgUnitRuleService $orgUnitRuleService,
        protected ModuleTableGovernanceService $moduleTableGovernanceService
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

            $query = OrgRoleModulePermission::query()
                ->active()
                ->where('module_key', $moduleKey)
                ->where('action_key', $actionKey);

            if (Schema::hasTable('system_roles')) {
                $query->with('systemRole:id,code');
            }

            $this->actionRolesCache[$cacheKey] = $query
                ->get()
                ->map(function (OrgRoleModulePermission $p) {
                    // Prefer system_role code, fallback to old org_role string
                    if ($p->systemRole) {
                        return (string) $p->systemRole->code;
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

        $targetDepartmentId = (int) ($targetDepartmentId ?? 0);

        if ($this->moduleTableGovernanceService->canUserPerform(
            $user,
            $moduleKey,
            $actionKey,
            $targetDepartmentId > 0 ? $targetDepartmentId : null
        )) {
            return true;
        }

        if ($this->canUserPerformViaTemplates(
            $user,
            $moduleKey,
            $actionKey,
            $targetDepartmentId > 0 ? $targetDepartmentId : null
        )) {
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

    protected function canUserPerformViaTemplates(
        User $user,
        string $moduleKey,
        string $actionKey,
        ?int $targetDepartmentId = null
    ): bool {
        $moduleKey = trim(mb_strtolower($moduleKey));
        $actionKey = trim(mb_strtolower($actionKey));
        $targetDepartmentId = (int) ($targetDepartmentId ?? 0);

        if ($moduleKey === '' || $actionKey === '') {
            return false;
        }

        $assignments = UserAssignment::query()
            ->withoutGlobalScope('sortByLatest')
            ->effective()
            ->where('user_id', (int) $user->id)
            ->whereNotNull('responsibility_template_id')
            ->with([
                'responsibilityTemplate:id,module_key,action_presets_json,is_active',
            ])
            ->get();

        foreach ($assignments as $assignment) {
            /** @var ResponsibilityTemplate|null $template */
            $template = $assignment->responsibilityTemplate;
            if (!$template || !(bool) $template->is_active) {
                continue;
            }

            $templateModule = trim(mb_strtolower((string) ($template->module_key ?? '')));
            if (!in_array($templateModule, ['', '*', 'all', $moduleKey], true)) {
                continue;
            }

            if (!$template->supportsAction($actionKey)) {
                continue;
            }

            if ($targetDepartmentId > 0 && !$this->assignmentScopeIncludesDepartment($assignment, $targetDepartmentId)) {
                continue;
            }

            return true;
        }

        return false;
    }

    protected function assignmentScopeIncludesDepartment(UserAssignment $assignment, int $targetDepartmentId): bool
    {
        if ($targetDepartmentId <= 0) {
            return true;
        }

        $assignmentDepartmentId = (int) ($assignment->department_id ?? 0);
        if ($assignmentDepartmentId <= 0) {
            return false;
        }

        $scopeType = trim((string) ($assignment->scope_type ?: UserAssignment::SCOPE_SELF_AND_CHILDREN));

        return match ($scopeType) {
            UserAssignment::SCOPE_SELF_ONLY => $assignmentDepartmentId === $targetDepartmentId,
            UserAssignment::SCOPE_SELF_UNIT_ONLY => in_array(
                $targetDepartmentId,
                $this->siblingSameTypeDepartmentIds($assignmentDepartmentId),
                true
            ),
            UserAssignment::SCOPE_ALL => true,
            default => in_array(
                $targetDepartmentId,
                $this->orgUnitRuleService->branchIdsIncludingSelf($assignmentDepartmentId),
                true
            ),
        };
    }

    protected function siblingSameTypeDepartmentIds(int $departmentId): array
    {
        $department = Department::query()
            ->withoutGlobalScopes()
            ->find($departmentId, ['id', 'parent_id', 'unit_type_id']);

        if (!$department || !$department->parent_id || !$department->unit_type_id) {
            return [$departmentId];
        }

        return Department::query()
            ->withoutGlobalScopes()
            ->where('parent_id', (int) $department->parent_id)
            ->where('unit_type_id', (int) $department->unit_type_id)
            ->where('is_active', 1)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
