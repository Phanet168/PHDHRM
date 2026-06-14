<?php

namespace Modules\Planning\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Planning\Entities\Plan;
use Modules\Planning\Entities\PlanRollup;
use Modules\Planning\Services\PlanningAccessService;
use Modules\Planning\Services\PlanningModuleStateService;

class PlanningConsolidationController extends Controller
{
    public function __construct(
        private readonly PlanningAccessService $accessService,
        private readonly PlanningModuleStateService $moduleStateService
    )
    {
    }

    public function index(Request $request): View
    {
        if (!$this->moduleStateService->isInstalled()) {
            return view('planning::setup.index');
        }

        $plansQuery = $this->accessService->visiblePlansQuery($request->user());

        $plans = (clone $plansQuery)
            ->with('orgUnit')
            ->orderBy('year', 'desc')
            ->orderBy('plan_type')
            ->paginate(20)
            ->withQueryString();

        $rollups = (clone $plansQuery)
            ->join('org_units', 'org_units.id', '=', 'plans.org_unit_id')
            ->select(
                'org_units.name as org_unit_name',
                'plans.plan_type',
                DB::raw('COUNT(plans.id) as plans_count'),
                DB::raw('SUM(plans.total_estimated_cost) as total_cost')
            )
            ->groupBy('org_units.name', 'plans.plan_type')
            ->orderBy('org_units.name')
            ->get();

        $statusRollups = (clone $plansQuery)
            ->select('workflow_status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('workflow_status')
            ->pluck('aggregate', 'workflow_status');

        $consolidatedChildren = PlanRollup::query()
            ->with(['parentPlan.orgUnit', 'childPlan.orgUnit'])
            ->latest('rolled_at')
            ->limit(20)
            ->get();

        return view('planning::consolidation.index', [
            'plans' => $plans,
            'rollups' => $rollups,
            'statusRollups' => $statusRollups,
            'consolidatedChildren' => $consolidatedChildren,
            'canConsolidate' => $request->user()?->can('planning.consolidate') ?? false,
        ]);
    }
}
