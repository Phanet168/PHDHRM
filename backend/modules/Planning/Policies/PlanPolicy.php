<?php

namespace Modules\Planning\Policies;

use App\Models\User;
use Modules\Planning\Entities\Plan;
use Modules\Planning\Services\PlanningAccessService;

class PlanPolicy
{
    public function __construct(private readonly PlanningAccessService $accessService)
    {
    }

    public function viewAny(User $user): bool
    {
        return $user->can('planning.view');
    }

    public function view(User $user, Plan $plan): bool
    {
        if (!$user->can('planning.view')) {
            return false;
        }

        $accessible = $this->accessService->accessibleOrgUnitIds($user);

        return $accessible === null || in_array((int) $plan->org_unit_id, $accessible, true);
    }

    public function create(User $user): bool
    {
        return $user->can('planning.create') && $this->accessService->currentOrgUnit($user) !== null;
    }

    public function update(User $user, Plan $plan): bool
    {
        if (!$user->can('planning.update_own') || !$plan->isEditable()) {
            return false;
        }

        return (int) $plan->org_unit_id === (int) optional($this->accessService->currentOrgUnit($user))->id;
    }

    public function submit(User $user, Plan $plan): bool
    {
        return $this->update($user, $plan);
    }

    public function review(User $user, Plan $plan): bool
    {
        return $this->accessService->canReviewPlan($user, $plan)
            && in_array($plan->workflow_status, [Plan::STATUS_SUBMITTED, Plan::STATUS_APPROVED], true);
    }

    public function approve(User $user, Plan $plan): bool
    {
        return $this->accessService->canApprovePlan($user, $plan)
            && in_array($plan->workflow_status, [Plan::STATUS_SUBMITTED, Plan::STATUS_REJECTED], true);
    }

    public function reject(User $user, Plan $plan): bool
    {
        return $this->accessService->canApprovePlan($user, $plan)
            && in_array($plan->workflow_status, [Plan::STATUS_SUBMITTED, Plan::STATUS_APPROVED], true);
    }

    public function consolidate(User $user, Plan $plan): bool
    {
        return $this->accessService->canConsolidatePlan($user, $plan)
            && in_array($plan->workflow_status, [Plan::STATUS_SUBMITTED, Plan::STATUS_APPROVED], true);
    }

    public function comment(User $user, Plan $plan): bool
    {
        return $user->can('planning.comment') && $this->view($user, $plan);
    }

    public function export(User $user, Plan $plan): bool
    {
        return $user->can('planning.export') && $this->view($user, $plan);
    }
}
