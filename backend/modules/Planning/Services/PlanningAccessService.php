<?php

namespace Modules\Planning\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Modules\Planning\Entities\OrgUnit;
use Modules\Planning\Entities\Plan;

class PlanningAccessService
{
    public function currentOrgUnit(?User $user): ?OrgUnit
    {
        if (!$user) {
            return null;
        }

        $departmentId = (int) ($user->primaryActiveAssignment?->department_id
            ?? $user->latestActiveAssignment?->department_id
            ?? $user->employee?->sub_department_id
            ?? $user->employee?->department_id
            ?? 0);

        if ($departmentId <= 0) {
            return null;
        }

        return OrgUnit::query()->where('source_department_id', $departmentId)->first();
    }

    public function accessibleOrgUnitIds(?User $user): ?array
    {
        if (!$user) {
            return [];
        }

        if ($user->can('planning.manage_master_data') || $user->hasRole('admin') || $user->hasRole('Super Admin')) {
            return null;
        }

        $currentOrgUnit = $this->currentOrgUnit($user);

        if (!$currentOrgUnit) {
            return [];
        }

        if ($user->canAny(['planning.review', 'planning.approve', 'planning.consolidate'])) {
            return $this->reviewableOrgUnitIds($currentOrgUnit);
        }

        return [$currentOrgUnit->id];
    }

    public function visiblePlansQuery(?User $user): Builder
    {
        $query = Plan::query()->with(['orgUnit', 'program', 'subProgram', 'activityCluster', 'items.costs.chartOfAccount']);
        $accessibleOrgUnitIds = $this->accessibleOrgUnitIds($user);

        if ($accessibleOrgUnitIds !== null) {
            $query->whereIn('org_unit_id', $accessibleOrgUnitIds);
        }

        return $query;
    }

    public function canReviewChildren(?User $user): bool
    {
        $currentOrgUnit = $this->currentOrgUnit($user);

        return (bool) $user?->can('planning.review')
            && $currentOrgUnit !== null
            && in_array($currentOrgUnit->unit_type, [
                'operational_district',
                'od_office',
                'provincial_health_department',
            ], true);
    }

    public function canApprove(?User $user): bool
    {
        return (bool) $user?->can('planning.approve');
    }

    public function canConsolidate(?User $user): bool
    {
        $currentOrgUnit = $this->currentOrgUnit($user);

        return (bool) $user?->can('planning.consolidate')
            && $currentOrgUnit !== null
            && in_array($currentOrgUnit->unit_type, [
                'operational_district',
                'od_office',
                'provincial_health_department',
            ], true);
    }

    public function reviewableOrgUnitIds(OrgUnit $orgUnit): array
    {
        return match ($orgUnit->unit_type) {
            'od_office', 'operational_district' => $this->descendantOrgUnitIds($orgUnit, ['health_center'], true),
            'provincial_health_department' => $this->descendantOrgUnitIds($orgUnit, null, true),
            default => [$orgUnit->id],
        };
    }

    public function canReviewPlan(User $user, Plan $plan): bool
    {
        $currentOrgUnit = $this->currentOrgUnit($user);
        if (!$currentOrgUnit || !$this->canReviewChildren($user)) {
            return false;
        }

        return (int) $plan->org_unit_id !== (int) $currentOrgUnit->id
            && in_array((int) $plan->org_unit_id, $this->reviewableOrgUnitIds($currentOrgUnit), true);
    }

    public function canApprovePlan(User $user, Plan $plan): bool
    {
        $currentOrgUnit = $this->currentOrgUnit($user);

        if (!$this->canApprove($user) || !$currentOrgUnit) {
            return false;
        }

        if ($currentOrgUnit->unit_type === 'provincial_health_department') {
            return (int) $plan->org_unit_id !== (int) $currentOrgUnit->id
                && in_array((int) $plan->org_unit_id, $this->reviewableOrgUnitIds($currentOrgUnit), true);
        }

        if (in_array($currentOrgUnit->unit_type, ['operational_district', 'od_office'], true)) {
            $childHealthCenters = $this->descendantOrgUnitIds($currentOrgUnit, ['health_center'], false);

            return in_array((int) $plan->org_unit_id, $childHealthCenters, true);
        }

        return false;
    }

    public function canConsolidatePlan(User $user, Plan $plan): bool
    {
        $currentOrgUnit = $this->currentOrgUnit($user);

        return $this->canConsolidate($user)
            && $currentOrgUnit !== null
            && (int) $plan->org_unit_id === (int) $currentOrgUnit->id;
    }

    public function descendantOrgUnitIds(OrgUnit $orgUnit, ?array $unitTypes = null, bool $includeSelf = true): array
    {
        $path = trim((string) $orgUnit->hierarchy_path, '/');

        $query = OrgUnit::query()->where(function ($builder) use ($path, $orgUnit, $includeSelf) {
            if ($includeSelf) {
                $builder->where('id', $orgUnit->id);
            }

            $builder->orWhere('hierarchy_path', 'like', $path . '/%');
        });

        if ($unitTypes !== null) {
            $query->whereIn('unit_type', $unitTypes);
        }

        return $query->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
