<?php

namespace Modules\Planning\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Modules\Planning\Services\PlanningDashboardService;
use Modules\Planning\Services\PlanningModuleStateService;

class PlanningDashboardController extends Controller
{
    public function __construct(
        private readonly PlanningDashboardService $dashboardService,
        private readonly PlanningModuleStateService $moduleStateService
    )
    {
    }

    public function index(): View
    {
        if (!$this->moduleStateService->isInstalled()) {
            return view('planning::setup.index');
        }

        return view('planning::dashboard.index', [
            'summary' => $this->dashboardService->summary(auth()->user()),
        ]);
    }
}
