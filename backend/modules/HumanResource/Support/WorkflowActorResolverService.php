<?php

namespace Modules\HumanResource\Support;

use App\Models\User;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\SystemRole;
use Modules\HumanResource\Entities\UserAssignment;
use Modules\HumanResource\Entities\UserOrgRole;
use Modules\HumanResource\Entities\WorkflowDefinitionStep;
use Spatie\Permission\Models\Role;

class WorkflowActorResolverService
{
    public function __construct(private readonly OrgUnitRuleService $orgUnitRuleService)
    {
    }

    public function previewPlan(array $plan, array $context = []): array
    {
        $steps = collect((array) ($plan['steps'] ?? []))
            ->map(function (array $step) use ($context) {
                $resolvedType = $this->resolveStepActorTypeFromArray($step);
                $sourceDepartmentId = $this->resolveSourceDepartmentId($context);
                $candidates = $this->resolveCandidatesFromArray($step, $resolvedType, $sourceDepartmentId);

                $step['resolved_actor_type'] = $resolvedType;
                $step['resolution_priority'] = [
                    WorkflowDefinitionStep::ACTOR_TYPE_SPECIFIC_USER,
                    WorkflowDefinitionStep::ACTOR_TYPE_POSITION,
                    WorkflowDefinitionStep::ACTOR_TYPE_RESPONSIBILITY,
                    WorkflowDefinitionStep::ACTOR_TYPE_SPATIE_ROLE,
                ];
                $step['source_department_id'] = $sourceDepartmentId > 0 ? $sourceDepartmentId : null;
                $step['resolved_candidates'] = $candidates;
                $step['resolved_candidate_count'] = count($candidates);

                return $step;
            })
            ->values()
            ->all();

        $plan['steps'] = $steps;

        return $plan;
    }

    public function canUserActOnStep(User $user, WorkflowDefinitionStep $step, int $sourceDepartmentId = 0): bool
    {
        $actorType = $step->getEffectiveActorType();

        return match ($actorType) {
            WorkflowDefinitionStep::ACTOR_TYPE_SPECIFIC_USER
                => (int) $step->actor_user_id === (int) $user->id,

            WorkflowDefinitionStep::ACTOR_TYPE_POSITION
                => $this->userHasMatchingAssignment(
                    $user,
                    $sourceDepartmentId,
                    static fn (UserAssignment $assignment): bool => (int) $assignment->position_id === (int) $step->actor_position_id
                ),

            WorkflowDefinitionStep::ACTOR_TYPE_RESPONSIBILITY
                => $this->userHasResponsibilityForStep($user, $step, $sourceDepartmentId),

            WorkflowDefinitionStep::ACTOR_TYPE_SPATIE_ROLE
                => $this->userHasSpatieRole($user, (int) $step->actor_role_id),

            default => false,
        };
    }

    protected function resolveStepActorTypeFromArray(array $step): string
    {
        if (!empty($step['actor_user_id'])) {
            return WorkflowDefinitionStep::ACTOR_TYPE_SPECIFIC_USER;
        }
        if (!empty($step['actor_position_id'])) {
            return WorkflowDefinitionStep::ACTOR_TYPE_POSITION;
        }
        if (!empty($step['actor_responsibility_id']) || !empty($step['system_role_id']) || !empty($step['org_role'])) {
            return WorkflowDefinitionStep::ACTOR_TYPE_RESPONSIBILITY;
        }
        if (!empty($step['actor_role_id'])) {
            return WorkflowDefinitionStep::ACTOR_TYPE_SPATIE_ROLE;
        }

        return WorkflowDefinitionStep::ACTOR_TYPE_RESPONSIBILITY;
    }

    protected function resolveCandidatesFromArray(array $step, string $resolvedType, int $sourceDepartmentId): array
    {
        return match ($resolvedType) {
            WorkflowDefinitionStep::ACTOR_TYPE_SPECIFIC_USER
                => $this->specificUserCandidates((int) ($step['actor_user_id'] ?? 0)),

            WorkflowDefinitionStep::ACTOR_TYPE_POSITION
                => $this->assignmentCandidates(
                    $sourceDepartmentId,
                    static fn (UserAssignment $assignment): bool => (int) $assignment->position_id === (int) ($step['actor_position_id'] ?? 0)
                ),

            WorkflowDefinitionStep::ACTOR_TYPE_RESPONSIBILITY
                => $this->assignmentCandidates(
                    $sourceDepartmentId,
                    function (UserAssignment $assignment) use ($step): bool {
                        $responsibilityId = (int) ($step['actor_responsibility_id'] ?? 0);
                        if ($responsibilityId <= 0) {
                            $responsibilityId = (int) ($step['system_role_id'] ?? 0);
                        }
                        if ($responsibilityId <= 0 && !empty($step['org_role'])) {
                            $responsibilityId = (int) (SystemRole::query()
                                ->where('code', (string) $step['org_role'])
                                ->value('id') ?? 0);
                        }

                        return $responsibilityId > 0 && (int) $assignment->responsibility_id === $responsibilityId;
                    }
                ),

            WorkflowDefinitionStep::ACTOR_TYPE_SPATIE_ROLE
                => $this->spatieRoleCandidates((int) ($step['actor_role_id'] ?? 0), $sourceDepartmentId),

            default => [],
        };
    }

    protected function specificUserCandidates(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $user = User::query()
            ->withoutGlobalScope('sortByLatest')
            ->find($userId, ['id', 'full_name', 'email']);

        if (!$user) {
            return [];
        }

        return [[
            'user_id' => (int) $user->id,
            'full_name' => (string) $user->full_name,
            'email' => (string) ($user->email ?? ''),
            'source' => WorkflowDefinitionStep::ACTOR_TYPE_SPECIFIC_USER,
        ]];
    }

    protected function assignmentCandidates(int $sourceDepartmentId, callable $predicate): array
    {
        $assignments = UserAssignment::query()
            ->withoutGlobalScope('sortByLatest')
            ->with([
                'user:id,full_name,email',
                'department:id,department_name,parent_id,unit_type_id',
                'position:id,position_name,position_name_km',
                'responsibility:id,code,name,name_km',
            ])
            ->effective()
            ->get();

        $results = [];
        foreach ($assignments as $assignment) {
            if (!$predicate($assignment)) {
                continue;
            }

            if ($sourceDepartmentId > 0 && !$this->assignmentScopeIncludesDepartment($assignment, $sourceDepartmentId)) {
                continue;
            }

            if (!$assignment->user) {
                continue;
            }

            $results[] = [
                'user_id' => (int) $assignment->user_id,
                'full_name' => (string) ($assignment->user->full_name ?? ''),
                'email' => (string) ($assignment->user->email ?? ''),
                'department_id' => (int) ($assignment->department_id ?? 0),
                'department_name' => (string) ($assignment->department->department_name ?? ''),
                'position_id' => (int) ($assignment->position_id ?? 0),
                'position_name' => (string) ($assignment->position->position_name_km ?: ($assignment->position->position_name ?? '')),
                'responsibility_id' => (int) ($assignment->responsibility_id ?? 0),
                'responsibility_code' => (string) ($assignment->responsibility->code ?? ''),
                'scope_type' => (string) $assignment->scope_type,
                'is_primary' => (bool) $assignment->is_primary,
                'source' => 'user_assignments',
            ];
        }

        return collect($results)
            ->unique('user_id')
            ->values()
            ->all();
    }

    protected function spatieRoleCandidates(int $roleId, int $sourceDepartmentId): array
    {
        if ($roleId <= 0) {
            return [];
        }

        $users = User::query()
            ->withoutGlobalScope('sortByLatest')
            ->select('users.id', 'users.full_name', 'users.email')
            ->join('model_has_roles', function ($join) use ($roleId) {
                $join->on('model_has_roles.model_id', '=', 'users.id')
                    ->where('model_has_roles.model_type', User::class)
                    ->where('model_has_roles.role_id', $roleId);
            })
            ->orderBy('users.full_name')
            ->get();

        if ($sourceDepartmentId <= 0) {
            return $users->map(fn (User $user) => [
                'user_id' => (int) $user->id,
                'full_name' => (string) $user->full_name,
                'email' => (string) ($user->email ?? ''),
                'source' => WorkflowDefinitionStep::ACTOR_TYPE_SPATIE_ROLE,
            ])->values()->all();
        }

        $filtered = [];
        foreach ($users as $user) {
            $hasScopedAssignment = UserAssignment::query()
                ->withoutGlobalScope('sortByLatest')
                ->effective()
                ->where('user_id', (int) $user->id)
                ->get()
                ->contains(fn (UserAssignment $assignment) => $this->assignmentScopeIncludesDepartment($assignment, $sourceDepartmentId));

            if (!$hasScopedAssignment) {
                continue;
            }

            $filtered[] = [
                'user_id' => (int) $user->id,
                'full_name' => (string) $user->full_name,
                'email' => (string) ($user->email ?? ''),
                'source' => WorkflowDefinitionStep::ACTOR_TYPE_SPATIE_ROLE,
            ];
        }

        return $filtered;
    }

    protected function userHasMatchingAssignment(User $user, int $sourceDepartmentId, callable $predicate): bool
    {
        $assignments = UserAssignment::query()
            ->withoutGlobalScope('sortByLatest')
            ->effective()
            ->where('user_id', (int) $user->id)
            ->get();

        foreach ($assignments as $assignment) {
            if (!$predicate($assignment)) {
                continue;
            }
            if ($sourceDepartmentId > 0 && !$this->assignmentScopeIncludesDepartment($assignment, $sourceDepartmentId)) {
                continue;
            }
            return true;
        }

        return false;
    }

    protected function userHasResponsibilityForStep(User $user, WorkflowDefinitionStep $step, int $sourceDepartmentId): bool
    {
        $responsibilityId = (int) ($step->actor_responsibility_id ?: $step->system_role_id);
        if ($responsibilityId <= 0 && !empty($step->org_role)) {
            $responsibilityId = (int) (SystemRole::query()
                ->where('code', (string) $step->org_role)
                ->value('id') ?? 0);
        }

        if ($responsibilityId > 0) {
            $found = $this->userHasMatchingAssignment(
                $user,
                $sourceDepartmentId,
                static fn (UserAssignment $assignment): bool => (int) $assignment->responsibility_id === $responsibilityId
            );
            if ($found) {
                return true;
            }
        }

        // Backward compatibility fallback during transition.
        $roleCode = $step->getEffectiveRoleCode();
        if ($roleCode === '') {
            return false;
        }

        $legacyRoles = UserOrgRole::query()
            ->withoutGlobalScope('sortByLatest')
            ->effective()
            ->where('user_id', (int) $user->id)
            ->get();

        foreach ($legacyRoles as $legacyRole) {
            if ((string) $legacyRole->getEffectiveRoleCode() !== $roleCode) {
                continue;
            }

            if ($sourceDepartmentId <= 0) {
                return true;
            }

            $legacyScopeIds = $this->legacyScopeDepartmentIds($legacyRole);
            if (in_array($sourceDepartmentId, $legacyScopeIds, true)) {
                return true;
            }
        }

        return false;
    }

    protected function userHasSpatieRole(User $user, int $roleId): bool
    {
        if ($roleId <= 0) {
            return false;
        }

        $role = Role::query()->find($roleId, ['id', 'name']);
        if (!$role) {
            return false;
        }

        return method_exists($user, 'hasRole') && $user->hasRole((string) $role->name);
    }

    protected function assignmentScopeIncludesDepartment(UserAssignment $assignment, int $sourceDepartmentId): bool
    {
        if ($sourceDepartmentId <= 0) {
            return true;
        }

        $assignmentDepartmentId = (int) ($assignment->department_id ?? 0);
        if ($assignmentDepartmentId <= 0) {
            return false;
        }

        $scopeType = trim((string) ($assignment->scope_type ?: UserAssignment::SCOPE_SELF_AND_CHILDREN));

        return match ($scopeType) {
            UserAssignment::SCOPE_SELF_ONLY => $assignmentDepartmentId === $sourceDepartmentId,
            UserAssignment::SCOPE_SELF_UNIT_ONLY => in_array(
                $sourceDepartmentId,
                $this->siblingSameTypeDepartmentIds($assignmentDepartmentId),
                true
            ),
            UserAssignment::SCOPE_ALL => true,
            default => in_array(
                $sourceDepartmentId,
                $this->orgUnitRuleService->branchIdsIncludingSelf($assignmentDepartmentId),
                true
            ),
        };
    }

    protected function legacyScopeDepartmentIds(UserOrgRole $legacyRole): array
    {
        $departmentId = (int) ($legacyRole->department_id ?? 0);
        if ($departmentId <= 0) {
            return [];
        }

        $scopeType = trim((string) ($legacyRole->scope_type ?: UserOrgRole::SCOPE_SELF_AND_CHILDREN));
        if (in_array($scopeType, [UserOrgRole::SCOPE_SELF, UserOrgRole::SCOPE_SELF_ONLY], true)) {
            return [$departmentId];
        }

        if ($scopeType === UserOrgRole::SCOPE_SELF_UNIT_ONLY) {
            return $this->siblingSameTypeDepartmentIds($departmentId);
        }

        if ($scopeType === UserOrgRole::SCOPE_ALL) {
            return Department::query()
                ->withoutGlobalScopes()
                ->where('is_active', 1)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return $this->orgUnitRuleService->branchIdsIncludingSelf($departmentId);
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

    protected function resolveSourceDepartmentId(array $context): int
    {
        $departmentId = (int) ($context['department_id'] ?? 0);
        if ($departmentId > 0) {
            return $departmentId;
        }

        $employeeId = (int) ($context['employee_id'] ?? 0);
        if ($employeeId <= 0) {
            return 0;
        }

        $employee = Employee::query()
            ->withoutGlobalScopes()
            ->with(['primaryUnitPosting:id,employee_id,department_id'])
            ->find($employeeId, ['id', 'department_id', 'sub_department_id']);

        if (!$employee) {
            return 0;
        }

        $postingDepartmentId = (int) ($employee->primaryUnitPosting?->department_id ?? 0);
        if ($postingDepartmentId > 0) {
            return $postingDepartmentId;
        }

        $subDepartmentId = (int) ($employee->sub_department_id ?? 0);
        if ($subDepartmentId > 0) {
            return $subDepartmentId;
        }

        return (int) ($employee->department_id ?? 0);
    }
}
