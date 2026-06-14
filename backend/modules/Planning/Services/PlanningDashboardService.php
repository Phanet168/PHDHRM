<?php

namespace Modules\Planning\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Planning\Entities\Plan;

class PlanningDashboardService
{
    public function __construct(private readonly PlanningAccessService $accessService)
    {
    }

    public function summary(?User $user): array
    {
        $query = $this->accessService->visiblePlansQuery($user);

        $statusCounts = (clone $query)
            ->select('workflow_status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('workflow_status')
            ->pluck('aggregate', 'workflow_status')
            ->all();

        $typeCounts = (clone $query)
            ->select('plan_type', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('plan_type')
            ->pluck('aggregate', 'plan_type')
            ->all();

        $totalsByUnit = (clone $query)
            ->join('org_units', 'org_units.id', '=', 'plans.org_unit_id')
            ->select('org_units.name', DB::raw('SUM(plans.total_estimated_cost) as total_cost'))
            ->groupBy('org_units.name')
            ->orderByDesc('total_cost')
            ->limit(8)
            ->get();

        return [
            'total_plans' => (clone $query)->count(),
            'total_budget' => (clone $query)->sum('total_estimated_cost'),
            'pending_reviews' => (int) ($statusCounts[Plan::STATUS_SUBMITTED] ?? 0),
            'approved_plans' => (int) ($statusCounts[Plan::STATUS_APPROVED] ?? 0),
            'status_counts' => $statusCounts,
            'type_counts' => $typeCounts,
            'totals_by_unit' => $totalsByUnit,
        ];
    }
}
