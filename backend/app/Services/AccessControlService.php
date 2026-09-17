<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\HumanResource\Entities\UserAssignment;
use Modules\HumanResource\Entities\WorkflowDefinition;
use Modules\HumanResource\Entities\WorkflowDefinitionStep;
use Modules\HumanResource\Entities\WorkflowInstance;
use Modules\HumanResource\Services\DelegationService;
use Modules\HumanResource\Support\OrgHierarchyAccessService;
use Modules\HumanResource\Support\WorkflowActorResolverService;

/**
 * Phase 2 central authorization foundation.
 *
 * This service does NOT implement a second permission system. Every method
 * is a thin, explicit wrapper around the authorization primitives that
 * already exist and are already the source of truth:
 *
 *  - Spatie roles/permissions (Illuminate Gate, via $user->can()/hasRole())
 *  - Spatie model_has_permissions (direct user permission grants)
 *  - Modules\HumanResource\Entities\UserAssignment + scope_type
 *  - Modules\HumanResource\Support\OrgHierarchyAccessService (scope resolution)
 *  - Modules\HumanResource\Support\WorkflowActorResolverService (approval engine)
 *
 * Existing module code (AttendanceUnitScope, MissionAccess, OrgScopeService,
 * PlanningAccessService, Correspondence's own scope/responsibility tables,
 * ...) is intentionally left untouched in Phase 2 -- this class is the common
 * foundation those modules can be migrated onto in a later phase, one at a
 * time. It is purely additive: nothing here is called by existing controllers
 * yet.
 */
class AccessControlService
{
    /** @var array<int, array<string, array<int, string>>> per-user-id role => permission names, memoized for this request */
    private array $rolePermissionMapCache = [];

    public function __construct(
        private readonly OrgHierarchyAccessService $orgHierarchyAccessService,
        private readonly WorkflowActorResolverService $workflowActorResolverService,
        private readonly PermissionCatalogService $permissionCatalog,
        private readonly DelegationService $delegationService,
    ) {
    }

    // ------------------------------------------------------------------
    // Super Admin (section 9: centralize detection, do not change behavior)
    // ------------------------------------------------------------------

    /**
     * Single seam representing "current Super Admin behavior" -- delegates to
     * the same check already used by OrgHierarchyAccessService (user_type_id
     * === 1 OR hasRole('Super Admin')). Existing scattered checks
     * (Gate::before, IsAdmin middleware, User::admin()) are NOT changed or
     * routed through this method in Phase 2; this only gives future code one
     * place to call instead of adding a 14th copy of the same condition.
     */
    public function isSuperAdmin(?User $user): bool
    {
        return $this->orgHierarchyAccessService->isSystemAdmin($user);
    }

    // ------------------------------------------------------------------
    // Roles & permissions (Spatie passthrough -- no second system)
    // ------------------------------------------------------------------

    public function hasRole(User $user, string $role): bool
    {
        return $user->hasRole($role);
    }

    public function hasAnyRole(User $user, array $roles): bool
    {
        return $user->hasAnyRole($roles);
    }

    /** @return string[] */
    public function roleNames(User $user): array
    {
        return $user->getRoleNames()->values()->all();
    }

    /**
     * Goes through Illuminate's Gate (via $user->can()), so it naturally
     * respects the existing Gate::before Super Admin bypass and never throws
     * for a permission name that does not exist.
     */
    public function hasPermission(User $user, string $permission): bool
    {
        return $user->can($permission);
    }

    public function hasAnyPermission(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    /** @return string[] direct-to-user Spatie permission names (model_has_permissions), excluding role-derived ones */
    public function directPermissionNames(User $user): array
    {
        return $user->getDirectPermissions()->pluck('name')->values()->all();
    }

    /** @return string[] all effective permission names (role + direct), does not include the Super Admin bypass */
    public function effectivePermissionNames(User $user): array
    {
        return $user->getAllPermissions()->pluck('name')->values()->all();
    }

    // ------------------------------------------------------------------
    // Organization / data scope (section 5: reuse UserAssignment.scope_type)
    // ------------------------------------------------------------------

    /**
     * Resolve the org-unit scope for a user via the existing
     * OrgHierarchyAccessService (the same resolver already used by HR
     * employee/leave/notice/report authorization). Returns:
     *  - unit_ids = null  -> ORGANIZATION-wide (system admin, or a scope_type
     *                        of "all" on some assignment)
     *  - unit_ids = []    -> no managed units
     *  - unit_ids = [...] -> the concrete department ids in scope
     *
     * "type" reflects the primary active UserAssignment's scope_type when one
     * exists, which today is one of: self_only, self_unit_only,
     * self_and_children, all. DIRECT_REPORTS and SELECTED_UNITS are future
     * values this same field can grow into without changing this method's
     * contract.
     */
    public function resolveScope(User $user): array
    {
        if ($this->isSuperAdmin($user)) {
            return [
                'type' => UserAssignment::SCOPE_ALL,
                'unit_id' => null,
                'unit_ids' => null,
            ];
        }

        $primaryAssignment = $user->userAssignments()
            ->effective()
            ->primary()
            ->first();

        $unitIds = $this->orgHierarchyAccessService->managedBranchIds($user);

        return [
            'type' => $primaryAssignment->scope_type
                ?? ($unitIds === null ? UserAssignment::SCOPE_ALL : null),
            'unit_id' => $primaryAssignment->department_id ?? null,
            'unit_ids' => $unitIds,
        ];
    }

    /**
     * Whether the user's org scope covers a specific unit. Optionally also
     * requires a permission (equivalent to "can they act on this unit for
     * this feature").
     */
    public function canAccessUnit(User $user, int $unitId, ?string $permission = null): bool
    {
        if ($permission !== null && !$this->hasPermission($user, $permission)) {
            return false;
        }

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $unitIds = $this->orgHierarchyAccessService->managedBranchIds($user);
        if ($unitIds === null) {
            return true;
        }

        return in_array($unitId, $unitIds, true);
    }

    // ------------------------------------------------------------------
    // Approval authority (section 8: contextual, not a permission boolean)
    // ------------------------------------------------------------------

    /**
     * "CAN HAVE APPROVAL CAPABILITY" for a department in general -- i.e. is
     * this user positioned (by responsibility/role) to approve *something*
     * for that branch, independent of any specific pending request. Wraps
     * the existing OrgHierarchyAccessService::canApproveDepartment().
     */
    public function hasApprovalCapability(User $user, int $departmentId): bool
    {
        return $this->orgHierarchyAccessService->canApproveDepartment($user, $departmentId);
    }

    /**
     * "CAN APPROVE THIS SPECIFIC REQUEST NOW" -- resolves the workflow
     * instance's current step and asks the existing
     * WorkflowActorResolverService whether this user is a valid actor for it.
     * $sourceDepartmentId must be supplied by the caller (it is
     * domain-specific -- e.g. LeaveController derives it from the
     * requester's employee department; this service does not guess it).
     *
     * Mirrors the exact pattern already used by LeaveController::
     * canCurrentUserActOnLeave() (system-admin bypass checked by the caller
     * before delegating to canUserActOnStep) so behavior stays identical to
     * production for the one workflow already wired to it.
     *
     * Phase 3D.1: this is the ONE integration seam for Delegation (section
     * 16). The original-actor check above is tried FIRST and is completely
     * unchanged; only if it fails do we ask whether the user is an ACTIVE
     * delegate for this exact step/context. DelegationService re-verifies,
     * live, that the delegator still qualifies as an original actor before
     * granting anything -- WorkflowActorResolverService itself is never
     * modified or bypassed (section 33).
     */
    public function canApprove(User $user, WorkflowInstance $instance, int $sourceDepartmentId = 0): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $step = $this->resolveCurrentWorkflowStep($instance);
        if (!$step) {
            return false;
        }

        if ($this->workflowActorResolverService->canUserActOnStep(
            $user,
            $step,
            $sourceDepartmentId,
            (string) $instance->module_key
        )) {
            return true;
        }

        return $this->delegationService->isActiveDelegateFor(
            $user,
            $step,
            (string) $instance->module_key,
            $sourceDepartmentId
        );
    }

    private function resolveCurrentWorkflowStep(WorkflowInstance $instance): ?WorkflowDefinitionStep
    {
        $instance->loadMissing('definition.steps');
        $steps = $instance->definition?->steps;
        if (!$steps || $steps->isEmpty()) {
            return null;
        }

        $order = (int) ($instance->current_step_order ?? 0);
        if ($order <= 0) {
            return $steps->sortBy('step_order')->first();
        }

        return $steps->firstWhere('step_order', $order);
    }

    /**
     * Cross-workflow approval authority for a user: for every step of every
     * ACTIVE workflow definition, whether this user currently qualifies as an
     * actor for that step's type. This answers "is this user POSITIONED to
     * approve this kind of thing at all" (department-unscoped, i.e. the same
     * "0 = no department filter" convention WorkflowActorResolverService
     * already uses) -- it is NOT "can they approve one specific pending
     * request today" (see canApprove() for that live-instance check).
     *
     * @return array<int, array{module_key:string, request_type_key:string, definition_id:int, definition_name:string, step_id:int, step_name:string, action_type:string, is_final_approval:bool, actor_type:string, can_act:bool}>
     */
    public function approvalAuthorityForUser(User $user): array
    {
        $superAdmin = $this->isSuperAdmin($user);

        $definitions = WorkflowDefinition::query()
            ->active()
            ->with(['steps' => fn ($query) => $query->orderBy('step_order')])
            ->orderBy('module_key')
            ->orderBy('request_type_key')
            ->orderBy('priority')
            ->get();

        $rows = [];
        foreach ($definitions as $definition) {
            foreach ($definition->steps as $step) {
                $canAct = $superAdmin || $this->workflowActorResolverService->canUserActOnStep(
                    $user,
                    $step,
                    0,
                    (string) $definition->module_key
                );

                $rows[] = [
                    'module_key' => (string) $definition->module_key,
                    'request_type_key' => (string) $definition->request_type_key,
                    'definition_id' => $definition->id,
                    'definition_name' => (string) $definition->name,
                    'step_id' => $step->id,
                    'step_name' => (string) $step->step_name,
                    'action_type' => (string) $step->action_type,
                    'is_final_approval' => (bool) $step->is_final_approval,
                    'actor_type' => $step->getEffectiveActorType(),
                    'can_act' => $canAct,
                ];
            }
        }

        return $rows;
    }

    // ------------------------------------------------------------------
    // Effective access / permission source explanation (sections 3 & 4)
    // ------------------------------------------------------------------

    /**
     * Normalized explanation of why a user does or does not have a
     * permission-level ability. Approval authority is NOT folded into this
     * (see canApprove()/hasApprovalCapability() -- it is contextual, not a
     * static grant), matching section 8's explicit instruction not to reduce
     * approval to a boolean permission check.
     *
     * @return array{allowed: bool, permission: string, sources: array, scope: array}
     */
    public function explainAccess(User $user, string $permission): array
    {
        $superAdmin = $this->isSuperAdmin($user);
        $sources = [];

        if ($superAdmin) {
            $sources[] = ['type' => 'super_admin'];
        }

        if (in_array($permission, $this->directPermissionNames($user), true)) {
            $sources[] = ['type' => 'direct_user_permission'];
        }

        foreach ($this->rolePermissionMap($user) as $roleName => $permissionNames) {
            if (in_array($permission, $permissionNames, true)) {
                $sources[] = ['type' => 'role', 'name' => $roleName];
            }
        }

        return [
            'allowed' => $superAdmin || !empty($sources) || $user->can($permission),
            'permission' => $permission,
            'sources' => $sources,
            'scope' => $this->resolveScope($user),
        ];
    }

    /**
     * Full effective-access breakdown for the future admin
     * "Effective Access" screen (section 11). Read-only; grouped by module
     * via PermissionCatalogService so it can be rendered without any
     * additional lookups.
     *
     * @param string[] $permissions defaults to every existing permission
     */
    public function effectiveAccess(User $user, array $permissions = []): array
    {
        $permissions = !empty($permissions)
            ? $permissions
            : $this->permissionCatalog->catalog()->pluck('permission')->all();

        $grouped = [];
        foreach ($permissions as $permission) {
            $explanation = $this->explainAccess($user, $permission);
            $catalogEntry = $this->permissionCatalog->entryFor($permission);

            $grouped[$catalogEntry['module']]['label'] = $catalogEntry['module_label'];
            $grouped[$catalogEntry['module']]['permissions'][] = array_merge($explanation, [
                'resource' => $catalogEntry['resource'],
                'action' => $catalogEntry['action'],
                'display_name' => $catalogEntry['display_name'],
            ]);
        }

        return [
            'user_id' => $user->id,
            'super_admin' => $this->isSuperAdmin($user),
            'roles' => $this->roleNames($user),
            'scope' => $this->resolveScope($user),
            'modules' => $grouped,
            'temporary_delegations' => $this->activeIncomingDelegations($user),
        ];
    }

    /**
     * Phase 3D.1 (section 30/31): the TEMPORARY dimension of Effective
     * Access, kept as its own top-level key -- deliberately never merged
     * into the WHAT (roles/permissions) breakdown above, since a delegation
     * is not a role or a permission grant. Module display labels are added
     * by the presentation layer (AccessControlCenterController already owns
     * that mapping for approvalAuthorityForUser() rows) -- this stays
     * data-only, matching how the rest of this method's output works.
     *
     * @return array<int, array{
     *   type: string, authority_module_key: string, from_user_id: int,
     *   from_user_name: ?string, scope_type: string, scope_department_ids: int[],
     *   starts_at: ?string, ends_at: ?string, delegation_uuid: string
     * }>
     */
    private function activeIncomingDelegations(User $user): array
    {
        return $this->delegationService->list(['delegatee_user_id' => $user->id, 'state' => 'active'])
            ->map(fn ($delegation) => array_merge(
                $this->delegationService->explainSource($delegation),
                ['delegation_uuid' => $delegation->uuid]
            ))
            ->values()
            ->all();
    }

    // ------------------------------------------------------------------
    // Capability read model (section 10 -- additive, for future
    // Flutter / Access Control Center consumption)
    // ------------------------------------------------------------------

    public function capabilities(User $user): array
    {
        $superAdmin = $this->isSuperAdmin($user);
        $permissionNames = $superAdmin
            ? $this->permissionCatalog->catalog()->pluck('permission')->all()
            : $this->effectivePermissionNames($user);

        $modules = [];
        foreach ($permissionNames as $permission) {
            $entry = $this->permissionCatalog->entryFor($permission);
            $modules[$entry['module']][$entry['action']] = true;
        }

        return [
            'user' => ['id' => (int) $user->id],
            'super_admin' => $superAdmin,
            'roles' => $this->roleNames($user),
            'permissions' => $permissionNames,
            'assignments' => $this->assignmentsSummary($user),
            'modules' => $modules,
        ];
    }

    /** @return array<int, array{unit_id: ?int, scope_type: ?string, is_primary: bool}> */
    private function assignmentsSummary(User $user): array
    {
        return $user->userAssignments()
            ->effective()
            ->get()
            ->map(fn (UserAssignment $assignment) => [
                'unit_id' => $assignment->department_id,
                'scope_type' => $assignment->scope_type,
                'is_primary' => (bool) $assignment->is_primary,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<int, string>> role name => permission names,
     * memoized per user id for the lifetime of this service instance.
     */
    private function rolePermissionMap(User $user): array
    {
        if (!array_key_exists($user->id, $this->rolePermissionMapCache)) {
            $this->rolePermissionMapCache[$user->id] = $user->roles()
                ->with('permissions:id,name')
                ->get()
                ->mapWithKeys(fn ($role) => [$role->name => $role->permissions->pluck('name')->all()])
                ->all();
        }

        return $this->rolePermissionMapCache[$user->id];
    }
}
