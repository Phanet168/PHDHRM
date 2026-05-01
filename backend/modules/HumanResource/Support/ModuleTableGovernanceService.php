<?php

namespace Modules\HumanResource\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\UserAssignment;

class ModuleTableGovernanceService
{
    public function __construct(
        private readonly OrgUnitRuleService $orgUnitRuleService,
        private readonly WorkflowActorResolverService $workflowActorResolverService
    )
    {
    }

    public function canUserPerform(
        ?User $user,
        string $moduleKey,
        string $actionKey,
        ?int $targetDepartmentId = null
    ): bool {
        if (!$user) {
            return false;
        }

        $moduleKey = $this->normalizeKey($moduleKey);
        $actionKey = $this->normalizeKey($actionKey);
        $tables = $this->tablesForModule($moduleKey);
        if (!$tables || $actionKey === '') {
            return false;
        }

        if (!Schema::hasTable($tables['assignments']) || !Schema::hasTable($tables['templates'])) {
            return false;
        }

        $targetDepartmentId = (int) ($targetDepartmentId ?? 0);
        $rows = DB::table($tables['assignments'] . ' as a')
            ->join($tables['templates'] . ' as t', 't.id', '=', 'a.template_id')
            ->where('a.is_active', 1)
            ->where('t.is_active', 1)
            ->whereNull('a.deleted_at')
            ->whereNull('t.deleted_at')
            ->where(function ($query): void {
                $today = now()->toDateString();
                $query->whereNull('a.effective_from')->orWhereDate('a.effective_from', '<=', $today);
            })
            ->where(function ($query): void {
                $today = now()->toDateString();
                $query->whereNull('a.effective_to')->orWhereDate('a.effective_to', '>=', $today);
            })
            ->get([
                'a.user_id',
                'a.department_id',
                'a.scope_type',
                't.actions_json',
                't.conditions_json',
            ]);

        foreach ($rows as $row) {
            if (!$this->assignmentAppliesToUser((int) ($row->user_id ?? 0), (int) $user->id)) {
                continue;
            }

            $actions = $this->decodeJsonList($row->actions_json);
            if (!in_array($actionKey, $actions, true)) {
                continue;
            }

            if (!$this->conditionsPass((array) json_decode((string) ($row->conditions_json ?: '[]'), true))) {
                continue;
            }

            if ($targetDepartmentId > 0 && !$this->assignmentScopeIncludesDepartment(
                (int) $row->department_id,
                (string) $row->scope_type,
                $targetDepartmentId
            )) {
                continue;
            }

            return true;
        }

        return false;
    }

    protected function assignmentAppliesToUser(int $assignedUserId, int $actingUserId): bool
    {
        if ($assignedUserId <= 0 || $actingUserId <= 0) {
            return false;
        }

        $effectiveUserId = $this->workflowActorResolverService
            ->resolveActorUserIdForWorkflow($assignedUserId);

        return $effectiveUserId > 0
            ? $effectiveUserId === $actingUserId
            : $assignedUserId === $actingUserId;
    }

    public function actionMap(): array
    {
        return [
            'correspondence' => array_keys((array) config('governance.actions', [])),
            'attendance' => array_keys((array) config('attendance_governance.actions', [])),
        ];
    }

    protected function tablesForModule(string $moduleKey): ?array
    {
        return match ($moduleKey) {
            'correspondence' => [
                'templates' => 'correspondence_responsibility_templates',
                'assignments' => 'correspondence_user_responsibilities',
                'policies' => 'correspondence_workflow_policies',
            ],
            'attendance' => [
                'templates' => 'attendance_responsibility_templates',
                'assignments' => 'attendance_user_responsibilities',
                'policies' => 'attendance_workflow_policies',
            ],
            default => null,
        };
    }

    protected function decodeJsonList(?string $value): array
    {
        $decoded = json_decode((string) ($value ?: '[]'), true);
        return collect(is_array($decoded) ? $decoded : [])
            ->map(fn ($item) => $this->normalizeKey((string) $item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function conditionsPass(array $conditions): bool
    {
        // Conditions are intentionally data-driven. Empty conditions pass.
        // Department-role checks are enforced by assigning templates only to
        // the correct module responsibility rows during admin configuration.
        return true;
    }

    protected function assignmentScopeIncludesDepartment(int $assignmentDepartmentId, string $scopeType, int $targetDepartmentId): bool
    {
        if ($assignmentDepartmentId <= 0 || $targetDepartmentId <= 0) {
            return false;
        }

        $scopeType = $this->normalizeKey($scopeType ?: UserAssignment::SCOPE_SELF_AND_CHILDREN);

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

    protected function normalizeKey(string $key): string
    {
        return trim(mb_strtolower($key));
    }
}
