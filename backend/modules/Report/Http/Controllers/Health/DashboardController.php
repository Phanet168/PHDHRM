<?php

namespace Modules\Report\Http\Controllers\Health;

use Illuminate\Http\Request;
use Modules\Report\Entities\ReportPeriod;
use Modules\Report\Entities\ReportValue;
use Modules\Report\Services\HealthReportData;

class DashboardController extends HealthController
{
    public function index(Request $request, HealthReportData $data)
    {
        $year = (int) $request->input('year', now()->year);

        // Periods of the year that have values, with their counts, for the KPI period picker.
        $periods = ReportPeriod::query()
            ->where('year', $year)
            ->withCount(['values' => fn ($q) => $q->whereNotNull('value')])
            ->get()
            ->filter(fn ($p) => $p->values_count > 0)
            ->sortBy(fn ($p) => \Modules\Report\Services\PeriodCode::order($p->tag))
            ->values();

        // Default: the month with the most values (falls back to the latest period with data).
        $period = $periods->firstWhere('code', $request->input('period'))
            ?? $periods->filter(fn ($p) => str_starts_with($p->tag, 'M'))->sortByDesc('values_count')->first()
            ?? $periods->last();

        return view('report::health.dashboard', [
            'year' => $year,
            'years' => $this->years(),
            'period' => $period,
            'periods' => $periods,
            'kpis' => $data->kpis($period),
            'series' => $data->completeness($year),
            'activity' => $data->recentActivity(),
        ]);
    }
}
