<?php

namespace Modules\HumanResource\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Modules\HumanResource\Entities\WorkflowDefinition;

class WorkflowPolicyService
{
    public function resolveDefinition(string $moduleKey, string $requestTypeKey, array $context = []): ?WorkflowDefinition
    {
        $moduleKey = trim(mb_strtolower($moduleKey));
        $requestTypeKey = trim(mb_strtolower($requestTypeKey));

        if ($moduleKey === '' || $requestTypeKey === '') {
            return null;
        }

        $definitions = WorkflowDefinition::query()
            ->active()
            ->where('module_key', $moduleKey)
            ->where('request_type_key', $requestTypeKey)
            ->with('steps')
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        if ($definitions->isEmpty()) {
            return null;
        }

        foreach ($definitions as $definition) {
            if ($this->matchesCondition((array) ($definition->condition_json ?? []), $context)) {
                return $definition;
            }
        }

        return null;
    }

    public function buildPlan(WorkflowDefinition $definition): array
    {
        $steps = $definition->steps
            ->sortBy('step_order')
            ->values()
            ->map(function ($step) {
                return [
                    'step_order' => (int) $step->step_order,
                    'step_key' => (string) ($step->step_key ?? ''),
                    'step_name' => (string) $step->step_name,
                    'action_type' => (string) $step->action_type,
                    'actor_type' => method_exists($step, 'getEffectiveActorType')
                        ? (string) $step->getEffectiveActorType()
                        : (string) ($step->actor_type ?? 'responsibility'),
                    'actor_user_id' => !empty($step->actor_user_id) ? (int) $step->actor_user_id : null,
                    'actor_position_id' => !empty($step->actor_position_id) ? (int) $step->actor_position_id : null,
                    'actor_responsibility_id' => !empty($step->actor_responsibility_id) ? (int) $step->actor_responsibility_id : null,
                    'actor_role_id' => !empty($step->actor_role_id) ? (int) $step->actor_role_id : null,
                    'org_role' => (string) $step->org_role,
                    'system_role_id' => !empty($step->system_role_id) ? (int) $step->system_role_id : null,
                    'effective_role_code' => method_exists($step, 'getEffectiveRoleCode')
                        ? (string) $step->getEffectiveRoleCode()
                        : (string) $step->org_role,
                    'scope_type' => (string) $step->scope_type,
                    'is_final_approval' => (bool) $step->is_final_approval,
                    'is_required' => (bool) $step->is_required,
                    'can_return' => (bool) $step->can_return,
                    'can_reject' => (bool) $step->can_reject,
                ];
            })
            ->all();

        return [
            'definition_id' => (int) $definition->id,
            'definition_uuid' => (string) $definition->uuid,
            'module_key' => (string) $definition->module_key,
            'request_type_key' => (string) $definition->request_type_key,
            'name' => (string) $definition->name,
            'description' => (string) ($definition->description ?? ''),
            'priority' => (int) $definition->priority,
            'condition_json' => (array) ($definition->condition_json ?? []),
            'steps' => $steps,
        ];
    }

    public function resolveAndBuild(string $moduleKey, string $requestTypeKey, array $context = []): ?array
    {
        $definition = $this->resolveDefinition($moduleKey, $requestTypeKey, $context);
        if (!$definition) {
            return null;
        }

        return $this->buildPlan($definition);
    }

    public function availableDefinitions(string $moduleKey, string $requestTypeKey): Collection
    {
        return WorkflowDefinition::query()
            ->active()
            ->where('module_key', trim(mb_strtolower($moduleKey)))
            ->where('request_type_key', trim(mb_strtolower($requestTypeKey)))
            ->with('steps')
            ->orderBy('priority')
            ->orderBy('id')
            ->get();
    }

    protected function matchesCondition(array $condition, array $context): bool
    {
        if (empty($condition)) {
            return true;
        }

        $days = $this->toNumeric(Arr::get($context, 'days'));
        $minDays = $this->toNumeric(Arr::get($condition, 'min_days'));
        $maxDays = $this->toNumeric(Arr::get($condition, 'max_days'));

        if ($minDays !== null && ($days === null || $days < $minDays)) {
            return false;
        }

        if ($maxDays !== null && ($days === null || $days > $maxDays)) {
            return false;
        }

        if (!$this->matchDepartmentCondition($condition, $context)) {
            return false;
        }

        if (!$this->matchListCondition($condition, $context, 'employee_type_id', 'employee_type_ids')) {
            return false;
        }

        if (!$this->matchListCondition($condition, $context, 'employee_type_code', 'employee_type_codes')) {
            return false;
        }

        if (!$this->matchListCondition($condition, $context, 'org_unit_type_id', 'org_unit_type_ids')) {
            return false;
        }

        if (!$this->matchListCondition($condition, $context, 'org_unit_type_code', 'org_unit_type_codes')) {
            return false;
        }

        if (array_key_exists('is_full_right', $condition)) {
            $required = filter_var($condition['is_full_right'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $actual = filter_var(Arr::get($context, 'is_full_right'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($required !== null && $actual !== $required) {
                return false;
            }
        }

        return true;
    }

    protected function matchListCondition(array $condition, array $context, string $singleKey, string $listKey): bool
    {
        if (!array_key_exists($singleKey, $context) || !array_key_exists($listKey, $condition)) {
            return true;
        }

        $actual = Arr::get($context, $singleKey);
        $allowed = array_map(static function ($item) {
            return is_numeric($item) ? (string) (int) $item : trim(mb_strtolower((string) $item));
        }, (array) Arr::get($condition, $listKey, []));

        if (empty($allowed)) {
            return true;
        }

        $actualNormalized = is_numeric($actual)
            ? (string) (int) $actual
            : trim(mb_strtolower((string) $actual));

        return in_array($actualNormalized, $allowed, true);
    }

    protected function matchDepartmentCondition(array $condition, array $context): bool
    {
        if (!array_key_exists('department_ids', $condition)) {
            return true;
        }

        $allowed = collect((array) Arr::get($condition, 'department_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($allowed)) {
            return true;
        }

        $actualDepartmentId = (int) (Arr::get($context, 'department_id') ?? 0);
        if ($actualDepartmentId <= 0) {
            return false;
        }

        return in_array($actualDepartmentId, $allowed, true);
    }

    protected function toNumeric($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }
}

