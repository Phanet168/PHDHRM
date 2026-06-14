<?php

namespace Modules\Planning\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Planning\Entities\Plan;
use Modules\Planning\Entities\PlanApproval;
use Modules\Planning\Entities\PlanRollup;

class PlanningWorkflowService
{
    public function __construct(private readonly PlanningAccessService $accessService)
    {
    }

    public function submit(Plan $plan, User $user, ?string $comment = null): Plan
    {
        return DB::transaction(function () use ($plan, $user, $comment) {
            $fromStatus = $plan->workflow_status;

            $plan->forceFill([
                'workflow_status' => Plan::STATUS_SUBMITTED,
                'submitted_at' => now(),
                'submitted_by' => $user->id,
                'is_locked' => true,
            ])->save();

            $this->record($plan, $user, 'submitted', $fromStatus, $plan->workflow_status, $comment, $this->reviewScope($user));

            return $plan->fresh(['orgUnit', 'items.costs', 'approvals']);
        });
    }

    public function approve(Plan $plan, User $user, ?string $comment = null): Plan
    {
        return DB::transaction(function () use ($plan, $user, $comment) {
            $fromStatus = $plan->workflow_status;

            $plan->forceFill([
                'workflow_status' => Plan::STATUS_APPROVED,
                'approved_at' => now(),
                'approved_by' => $user->id,
                'rejected_at' => null,
                'rejected_by' => null,
                'rejection_reason' => null,
                'is_locked' => true,
            ])->save();

            $this->record($plan, $user, 'approved', $fromStatus, $plan->workflow_status, $comment, $this->reviewScope($user));

            return $plan->fresh(['orgUnit', 'items.costs', 'approvals']);
        });
    }

    public function reject(Plan $plan, User $user, string $comment): Plan
    {
        return DB::transaction(function () use ($plan, $user, $comment) {
            $fromStatus = $plan->workflow_status;

            $plan->forceFill([
                'workflow_status' => Plan::STATUS_REJECTED,
                'rejected_at' => now(),
                'rejected_by' => $user->id,
                'rejection_reason' => $comment,
                'is_locked' => false,
            ])->save();

            $this->record($plan, $user, 'rejected', $fromStatus, $plan->workflow_status, $comment, $this->reviewScope($user));

            return $plan->fresh(['orgUnit', 'items.costs', 'approvals']);
        });
    }

    public function review(Plan $plan, User $user, ?string $comment = null): Plan
    {
        return DB::transaction(function () use ($plan, $user, $comment) {
            $plan->forceFill([
                'reviewed_at' => now(),
                'reviewed_by' => $user->id,
            ])->save();

            $this->record($plan, $user, 'reviewed', $plan->workflow_status, $plan->workflow_status, $comment, $this->reviewScope($user));

            return $plan->fresh(['orgUnit', 'items.costs', 'approvals']);
        });
    }

    public function consolidate(Plan $plan, User $user, ?string $comment = null): Plan
    {
        return DB::transaction(function () use ($plan, $user, $comment) {
            $fromStatus = $plan->workflow_status;
            $childPlans = $this->eligibleChildPlansForConsolidation($plan, $user);

            PlanRollup::query()->where('parent_plan_id', $plan->id)->delete();

            foreach ($childPlans as $childPlan) {
                PlanRollup::create([
                    'parent_plan_id' => $plan->id,
                    'child_plan_id' => $childPlan->id,
                    'rollup_org_unit_id' => $childPlan->org_unit_id,
                    'rolled_cost' => $childPlan->total_estimated_cost,
                    'rolled_revenue' => $childPlan->total_revenue_amount,
                    'rolled_items_count' => $childPlan->items()->count(),
                    'rolled_at' => now(),
                    'rolled_by' => $user->id,
                ]);
            }

            $plan->forceFill([
                'workflow_status' => Plan::STATUS_CONSOLIDATED,
                'consolidated_at' => now(),
                'consolidated_by' => $user->id,
                'is_locked' => true,
            ])->save();

            $this->record($plan, $user, 'consolidated', $fromStatus, $plan->workflow_status, $comment, $this->reviewScope($user));

            return $plan->fresh(['orgUnit', 'items.costs', 'approvals', 'childRollups.childPlan.orgUnit']);
        });
    }

    private function record(Plan $plan, User $user, string $action, ?string $fromStatus, ?string $toStatus, ?string $comment, ?string $reviewScope): void
    {
        PlanApproval::create([
            'plan_id' => $plan->id,
            'org_unit_id' => $this->accessService->currentOrgUnit($user)?->id,
            'workflow_level' => (int) ($this->accessService->currentOrgUnit($user)?->level ?? 1),
            'review_scope' => $reviewScope,
            'acted_by' => $user->id,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'comment' => $comment,
            'acted_at' => now(),
        ]);
    }

    private function reviewScope(User $user): ?string
    {
        $orgUnit = $this->accessService->currentOrgUnit($user);

        return match ($orgUnit?->unit_type) {
            'od_office', 'operational_district' => 'child_health_centers',
            'provincial_health_department' => 'province_wide',
            default => 'own_unit',
        };
    }

    private function eligibleChildPlansForConsolidation(Plan $plan, User $user)
    {
        $currentOrgUnit = $this->accessService->currentOrgUnit($user);
        if (!$currentOrgUnit) {
            return collect();
        }

        $childOrgUnitIds = array_values(array_filter(
            $this->accessService->reviewableOrgUnitIds($currentOrgUnit),
            fn ($id) => (int) $id !== (int) $currentOrgUnit->id
        ));

        if ($childOrgUnitIds === []) {
            return collect();
        }

        return Plan::query()
            ->whereIn('org_unit_id', $childOrgUnitIds)
            ->where('year', $plan->year)
            ->where('plan_type', $plan->plan_type)
            ->where('workflow_status', Plan::STATUS_APPROVED)
            ->when($plan->program_id, fn ($query) => $query->where('program_id', $plan->program_id))
            ->when($plan->sub_program_id, fn ($query) => $query->where('sub_program_id', $plan->sub_program_id))
            ->when($plan->activity_cluster_id, fn ($query) => $query->where('activity_cluster_id', $plan->activity_cluster_id))
            ->get();
    }
}
