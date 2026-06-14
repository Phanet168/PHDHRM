<?php

namespace Modules\Planning\Http\Controllers;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PPhatDev\LunarDate\KhmerDate;
use Modules\Planning\Exports\DailyActivityCalendarExport;
use Modules\Planning\Exports\MonthlyActivityCalendarExport;
use Modules\Planning\Exports\QuarterlyActivityCalendarExport;
use Modules\Planning\Exports\AnnualActivityCalendarExport;
use Modules\Planning\Entities\OrgUnit;
use Modules\Planning\Services\PlanningAccessService;
use Modules\Planning\Services\PlanningModuleStateService;

class PlanningReportController extends Controller
{
    public function __construct(
        private readonly PlanningAccessService $accessService,
        private readonly PlanningModuleStateService $moduleStateService
    ) {
    }

    public function index(Request $request): View
    {
        if (!$this->moduleStateService->isInstalled()) {
            return view('planning::setup.index');
        }

        $reportType = $request->string('report')->toString() ?: 'annual';
        $selectedYear = $request->filled('year') ? (int) $request->input('year') : (int) now()->year;
        $query = $this->accessService->visiblePlansQuery($request->user());

        if ($request->filled('year')) {
            $query->where('year', (int) $request->string('year'));
        }

        $summaryByType = (clone $query)
            ->select('plan_type', DB::raw('COUNT(*) as plans_count'), DB::raw('SUM(total_estimated_cost) as total_cost'))
            ->groupBy('plan_type')
            ->orderBy('plan_type')
            ->get();

        $visiblePlanIds = (clone $query)->pluck('plans.id');
        $selectedMonth = $this->resolveDailyReportMonth($request, $visiblePlanIds);

        $summaryByUnit = (clone $query)
            ->join('org_units', 'org_units.id', '=', 'plans.org_unit_id')
            ->select('org_units.name', DB::raw('COUNT(plans.id) as plans_count'), DB::raw('SUM(plans.total_estimated_cost) as total_cost'))
            ->groupBy('org_units.name')
            ->orderBy('org_units.name')
            ->get();

        $costByAccount = DB::table('plan_item_costs')
            ->join('plan_items', 'plan_items.id', '=', 'plan_item_costs.plan_item_id')
            ->join('plans', 'plans.id', '=', 'plan_items.plan_id')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'plan_item_costs.chart_of_account_id')
            ->when(($orgUnitIds = $this->accessService->accessibleOrgUnitIds($request->user())) !== null, function ($builder) use ($orgUnitIds) {
                $builder->whereIn('plans.org_unit_id', $orgUnitIds);
            })
            ->when($request->filled('year'), function ($builder) use ($request) {
                $builder->where('plans.year', (int) $request->string('year'));
            })
            ->select(
                'chart_of_accounts.code',
                'chart_of_accounts.name',
                'plan_item_costs.chapter_code',
                'plan_item_costs.account_code',
                'plan_item_costs.subaccount_code',
                DB::raw('SUM(plan_item_costs.total_cost) as total_cost')
            )
            ->groupBy(
                'chart_of_accounts.code',
                'chart_of_accounts.name',
                'plan_item_costs.chapter_code',
                'plan_item_costs.account_code',
                'plan_item_costs.subaccount_code'
            )
            ->orderBy('chart_of_accounts.code')
            ->get();

        $annualActivityReport = $this->buildAnnualActivityReport($visiblePlanIds);
        $quarterlyActivityReport = $this->buildQuarterlyActivityReport($visiblePlanIds);

        $monthlyActivityReport = $this->buildMonthlyActivityReport($visiblePlanIds);
        $currentOrgUnit = $this->accessService->currentOrgUnit($request->user());
        $reportOrgName = trim((string) ($currentOrgUnit?->name_km ?: $currentOrgUnit?->name ?: '....................................'));
        $orgHeaderLines = $this->buildOrgHeaderLines($currentOrgUnit);
        [$lunarPrintDateLabel, $solarPrintDateLabel] = $this->buildKhmerPrintDateLabels();

        return view('planning::reports.index', [
            'annualActivityReport' => $annualActivityReport,
            'quarterlyActivityReport' => $quarterlyActivityReport,
            'monthlyActivityReport' => $monthlyActivityReport,
            'dailyActivityReport' => $this->buildDailyActivityReport($visiblePlanIds, $selectedMonth),
            'dailyTimelineDays' => $this->buildDailyTimelineDays($selectedYear, $selectedMonth),
            'dailyMonthLabel' => $this->monthLabel($selectedMonth),
            'dailyReportYear' => $selectedYear,
            'filters' => [
                'year' => $request->input('year'),
                'month' => $selectedMonth,
            ],
            'reportType' => $reportType,
            'reportOrgName' => $reportOrgName,
            'orgHeaderLines' => $orgHeaderLines,
            'lunarPrintDateLabel' => $lunarPrintDateLabel,
            'solarPrintDateLabel' => $solarPrintDateLabel,
        ]);
    }

    public function export(Request $request)
    {
        if (!$this->moduleStateService->isInstalled()) {
            return view('planning::setup.index');
        }

        abort_unless($request->user()?->can('planning.export'), 403);

        $query = $this->accessService->visiblePlansQuery($request->user());

        if ($request->filled('year')) {
            $query->where('year', (int) $request->string('year'));
        }

        $visiblePlanIds = (clone $query)->pluck('plans.id');
        $reportType = $request->string('report')->toString() ?: 'annual';
        $selectedYear = $request->filled('year') ? (int) $request->input('year') : (int) now()->year;
        $selectedMonth = $reportType === 'daily'
            ? $this->resolveDailyReportMonth($request, $visiblePlanIds)
            : ($request->filled('month') ? (int) $request->input('month') : null);
        $format = strtolower((string) $request->input('format', 'xlsx'));
        $currentOrgUnit = $this->accessService->currentOrgUnit($request->user());
        $reportOrgName = trim((string) ($currentOrgUnit?->name_km ?: $currentOrgUnit?->name ?: '....................................'));
        $orgHeaderLines = $this->buildOrgHeaderLines($currentOrgUnit);
        $printDateLabel = 'ថ្ងៃ ' . now()->day . ' ខែ ' . $this->monthLabel((int) now()->month) . ' ឆ្នាំ ' . now()->year;
        [$lunarPrintDateLabel, $solarPrintDateLabel] = $this->buildKhmerPrintDateLabels();

        if ($reportType === 'daily') {
            $dailyRows = $this->buildDailyActivityReport($visiblePlanIds, $selectedMonth);
            $timelineDays = $this->buildDailyTimelineDays($selectedYear, $selectedMonth);

            if ($format === 'pdf') {
                $browserResponse = $this->renderPdfWithBrowser(
                    'planning::reports.exports.daily-browser-pdf',
                    [
                        'rows' => $dailyRows,
                        'timelineDays' => $timelineDays,
                        'monthLabel' => $this->monthLabel($selectedMonth),
                        'reportYear' => $selectedYear,
                        'reportOrgName' => $reportOrgName,
                        'orgHeaderLines' => $orgHeaderLines,
                        'printDateLabel' => $printDateLabel,
                        'lunarPrintDateLabel' => $lunarPrintDateLabel,
                        'solarPrintDateLabel' => $solarPrintDateLabel,
                    ],
                    'planning-daily-report.pdf'
                );

                if ($browserResponse !== null) {
                    return $browserResponse;
                }

                $pdf = Pdf::loadView('planning::reports.exports.daily-pdf', [
                    'rows' => $dailyRows,
                    'timelineDays' => $timelineDays,
                    'monthLabel' => $this->monthLabel($selectedMonth),
                    'reportYear' => $selectedYear,
                    'reportOrgName' => $reportOrgName,
                    'printDateLabel' => $printDateLabel,
                    'lunarPrintDateLabel' => $lunarPrintDateLabel,
                    'solarPrintDateLabel' => $solarPrintDateLabel,
                ])->setPaper('a4', 'landscape');

                return $pdf->download('planning-daily-report.pdf');
            }

            return Excel::download(
                new DailyActivityCalendarExport(
                    $dailyRows,
                    $timelineDays,
                    $this->monthLabel($selectedMonth),
                    $selectedYear,
                    $reportOrgName,
                    $orgHeaderLines,
                    $printDateLabel
                ),
                'planning-daily-report.xlsx'
            );
        } elseif ($reportType === 'monthly') {
            $monthlyRows = $this->buildMonthlyActivityReport($visiblePlanIds);
            $timelineMonths = $this->buildMonthlyTimeline();

            if ($format === 'pdf') {
                $browserResponse = $this->renderPdfWithBrowser(
                    'planning::reports.exports.monthly-browser-pdf',
                    [
                        'rows' => $monthlyRows,
                        'timelineMonths' => $timelineMonths,
                        'reportYear' => $selectedYear,
                        'reportOrgName' => $reportOrgName,
                        'orgHeaderLines' => $orgHeaderLines,
                    ],
                    'planning-monthly-report.pdf'
                );

                if ($browserResponse !== null) {
                    return $browserResponse;
                }

                $pdf = Pdf::loadView('planning::reports.exports.monthly-pdf', [
                    'rows' => $monthlyRows,
                    'timelineMonths' => $timelineMonths,
                    'reportYear' => $selectedYear,
                    'reportOrgName' => $reportOrgName,
                    'orgHeaderLines' => $orgHeaderLines,
                ])->setPaper('a4', 'landscape');

                return $pdf->download('planning-monthly-report.pdf');
            }

            return Excel::download(
                new MonthlyActivityCalendarExport(
                    $monthlyRows,
                    $timelineMonths,
                    $selectedYear,
                    $reportOrgName,
                    $orgHeaderLines
                ),
                'planning-monthly-report.xlsx'
            );
        } elseif ($reportType === 'quarterly') {
            $quarterlyRows = $this->buildQuarterlyActivityReport($visiblePlanIds);
            $timelineQuarters = $this->buildQuarterlyTimeline();

            if ($format === 'pdf') {
                $browserResponse = $this->renderPdfWithBrowser(
                    'planning::reports.exports.quarterly-browser-pdf',
                    [
                        'rows' => $quarterlyRows,
                        'timelineQuarters' => $timelineQuarters,
                        'reportYear' => $selectedYear,
                        'reportOrgName' => $reportOrgName,
                        'orgHeaderLines' => $orgHeaderLines,
                    ],
                    'planning-quarterly-report.pdf'
                );

                if ($browserResponse !== null) {
                    return $browserResponse;
                }

                $pdf = Pdf::loadView('planning::reports.exports.quarterly-pdf', [
                    'rows' => $quarterlyRows,
                    'timelineQuarters' => $timelineQuarters,
                    'reportYear' => $selectedYear,
                    'reportOrgName' => $reportOrgName,
                    'orgHeaderLines' => $orgHeaderLines,
                ])->setPaper('a4', 'landscape');

                return $pdf->download('planning-quarterly-report.pdf');
            }

            return Excel::download(
                new QuarterlyActivityCalendarExport(
                    $quarterlyRows,
                    $timelineQuarters,
                    $selectedYear,
                    $reportOrgName,
                    $orgHeaderLines
                ),
                'planning-quarterly-report.xlsx'
            );
        } else {
            $annualRows = $this->buildAnnualActivityReport($visiblePlanIds);
            $timelineQuarters = $this->buildQuarterlyTimeline();

            if ($format === 'pdf') {
                $browserResponse = $this->renderPdfWithBrowser(
                    'planning::reports.exports.annual-browser-pdf',
                    [
                        'rows' => $annualRows,
                        'timelineQuarters' => $timelineQuarters,
                        'reportYear' => $selectedYear,
                        'reportOrgName' => $reportOrgName,
                        'orgHeaderLines' => $orgHeaderLines,
                    ],
                    'planning-annual-report.pdf'
                );

                if ($browserResponse !== null) {
                    return $browserResponse;
                }

                $pdf = Pdf::loadView('planning::reports.exports.annual-pdf', [
                    'rows' => $annualRows,
                    'timelineQuarters' => $timelineQuarters,
                    'reportYear' => $selectedYear,
                    'reportOrgName' => $reportOrgName,
                    'orgHeaderLines' => $orgHeaderLines,
                ])->setPaper('a4', 'landscape');

                return $pdf->download('planning-annual-report.pdf');
            }

            return Excel::download(
                new AnnualActivityCalendarExport(
                    $annualRows,
                    $timelineQuarters,
                    $selectedYear,
                    $reportOrgName,
                    $orgHeaderLines
                ),
                'planning-annual-report.xlsx'
            );
        }
    }

    private function buildDailyActivityReport($visiblePlanIds, int $selectedMonth)
    {
        if ($visiblePlanIds->isEmpty()) {
            return collect();
        }

        return DB::table('plan_items')
            ->join('plans', 'plans.id', '=', 'plan_items.plan_id')
            ->leftJoin('plan_item_indicators', 'plan_item_indicators.plan_item_id', '=', 'plan_items.id')
            ->leftJoin('indicators', 'indicators.id', '=', 'plan_item_indicators.indicator_id')
            ->leftJoin('plan_item_schedules', function ($join) use ($selectedMonth) {
                $join->on('plan_item_schedules.plan_item_id', '=', 'plan_items.id')
                    ->where('plan_item_schedules.period_type', '=', 'daily')
                    ->whereMonth('plan_item_schedules.start_date', '=', $selectedMonth);
            })
            ->whereIn('plan_items.plan_id', $visiblePlanIds)
            ->where('plan_items.item_type', 'activity')
            ->select(
                'indicators.code as indicator_code',
                'indicators.name as indicator_name',
                'plan_items.title as activity_title',
                DB::raw("COALESCE(MAX(NULLIF(plan_item_schedules.goal_text, '')), '-') as goal_text"),
                DB::raw("GROUP_CONCAT(DISTINCT DAY(plan_item_schedules.start_date) ORDER BY plan_item_schedules.start_date SEPARATOR ',') as activity_days")
            )
            ->groupBy(
                'indicators.code',
                'indicators.name',
                'plan_items.title'
            )
            ->orderBy('indicators.code')
            ->orderBy('plan_items.title')
            ->get()
            ->map(function ($row) {
                $row->activity_day_numbers = collect(explode(',', (string) $row->activity_days))
                    ->filter()
                    ->map(fn ($day) => (int) $day)
                    ->values()
                    ->all();

                return $row;
            });
    }

    private function buildMonthlyActivityReport($visiblePlanIds)
    {
        if ($visiblePlanIds->isEmpty()) {
            return collect();
        }

        return DB::table('plan_items')
            ->join('plans', 'plans.id', '=', 'plan_items.plan_id')
            ->join('org_units', 'org_units.id', '=', 'plans.org_unit_id')
            ->leftJoin('plan_item_indicators', 'plan_item_indicators.plan_item_id', '=', 'plan_items.id')
            ->leftJoin('indicators', 'indicators.id', '=', 'plan_item_indicators.indicator_id')
            ->leftJoin('plan_item_schedules', function ($join) {
                $join->on('plan_item_schedules.plan_item_id', '=', 'plan_items.id')
                    ->where('plan_item_schedules.period_type', '=', 'monthly');
            })
            ->whereIn('plan_items.plan_id', $visiblePlanIds)
            ->where('plan_items.item_type', 'activity')
            ->select(
                'plans.year',
                'org_units.name as org_unit_name',
                'indicators.code as indicator_code',
                'indicators.name as indicator_name',
                'plan_items.title as activity_title',
                'plan_items.total_cost',
                DB::raw("GROUP_CONCAT(DISTINCT plan_item_schedules.month ORDER BY plan_item_schedules.month SEPARATOR ',') as activity_months"),
                DB::raw("GROUP_CONCAT(DISTINCT NULLIF(plan_item_schedules.note, '') ORDER BY plan_item_schedules.month SEPARATOR '; ') as notes")
            )
            ->groupBy(
                'plans.year',
                'org_units.name',
                'indicators.code',
                'indicators.name',
                'plan_items.title',
                'plan_items.total_cost'
            )
            ->orderBy('org_units.name')
            ->orderBy('indicators.code')
            ->orderBy('plan_items.title')
            ->get()
            ->map(function ($row) {
                $row->activity_month_numbers = collect(explode(',', (string) $row->activity_months))
                    ->filter()
                    ->map(fn ($month) => (int) $month)
                    ->values()
                    ->all();

                return $row;
            });
    }

    private function buildDailyTimelineDays(int $year, int $month)
    {
        $monthStart = Carbon::createFromDate($year, $month, 1);
        $daysInMonth = $monthStart->daysInMonth;

        return collect(range(1, 31))->map(function (int $day) use ($monthStart, $daysInMonth) {
            if ($day <= $daysInMonth) {
                $date = $monthStart->copy()->day($day);

                return (object) [
                    'day' => $day,
                    'dayOfWeekIso' => $date->dayOfWeekIso,
                    'is_actual_day' => true,
                ];
            }

            return (object) [
                'day' => $day,
                'dayOfWeekIso' => null,
                'is_actual_day' => false,
            ];
        });
    }

    private function buildMonthlyTimeline()
    {
        return collect([
            (object) ['month' => 1, 'label' => 'មករា'],
            (object) ['month' => 2, 'label' => 'កុម្ភៈ'],
            (object) ['month' => 3, 'label' => 'មីនា'],
            (object) ['month' => 4, 'label' => 'មេសា'],
            (object) ['month' => 5, 'label' => 'ឧសភា'],
            (object) ['month' => 6, 'label' => 'មិថុនា'],
            (object) ['month' => 7, 'label' => 'កក្កដា'],
            (object) ['month' => 8, 'label' => 'សីហា'],
            (object) ['month' => 9, 'label' => 'កញ្ញា'],
            (object) ['month' => 10, 'label' => 'តុលា'],
            (object) ['month' => 11, 'label' => 'វិច្ឆិកា'],
            (object) ['month' => 12, 'label' => 'ធ្នូ'],
        ]);
    }

    private function buildQuarterlyTimeline()
    {
        return collect([
            (object) ['quarter' => 1, 'label' => 'Q1'],
            (object) ['quarter' => 2, 'label' => 'Q2'],
            (object) ['quarter' => 3, 'label' => 'Q3'],
            (object) ['quarter' => 4, 'label' => 'Q4'],
        ]);
    }

    private function buildQuarterlyActivityReport($visiblePlanIds)
    {
        if ($visiblePlanIds->isEmpty()) {
            return collect();
        }

        $quarterlySchedules = DB::table('plan_item_schedules')
            ->where('plan_item_schedules.period_type', 'quarterly')
            ->select(
                'plan_item_schedules.plan_item_id',
                DB::raw("GROUP_CONCAT(DISTINCT plan_item_schedules.quarter ORDER BY plan_item_schedules.quarter SEPARATOR ',') as activity_quarters_raw"),
                DB::raw("GROUP_CONCAT(DISTINCT NULLIF(plan_item_schedules.period_label, '') ORDER BY plan_item_schedules.quarter SEPARATOR '; ') as expected_results"),
                DB::raw("GROUP_CONCAT(DISTINCT NULLIF(plan_item_schedules.note, '') ORDER BY plan_item_schedules.quarter SEPARATOR '; ') as notes")
            )
            ->groupBy('plan_item_schedules.plan_item_id');

        $costSummaries = DB::table('plan_item_costs')
            ->leftJoin('funding_sources', 'funding_sources.id', '=', 'plan_item_costs.funding_source_id')
            ->select(
                'plan_item_costs.plan_item_id',
                DB::raw('SUM(plan_item_costs.total_cost) as total_cost'),
                DB::raw("GROUP_CONCAT(DISTINCT NULLIF(funding_sources.name, '') ORDER BY funding_sources.name SEPARATOR '; ') as funding_source_names")
            )
            ->groupBy('plan_item_costs.plan_item_id');

        return DB::table('plan_items')
            ->join('plans', 'plans.id', '=', 'plan_items.plan_id')
            ->join('org_units', 'org_units.id', '=', 'plans.org_unit_id')
            ->leftJoin('plan_item_indicators', 'plan_item_indicators.plan_item_id', '=', 'plan_items.id')
            ->leftJoin('indicators', 'indicators.id', '=', 'plan_item_indicators.indicator_id')
            ->leftJoin('org_units as responsible_units', 'responsible_units.id', '=', 'plan_items.responsible_org_unit_id')
            ->leftJoinSub($quarterlySchedules, 'quarterly_schedules', function ($join) {
                $join->on('quarterly_schedules.plan_item_id', '=', 'plan_items.id');
            })
            ->leftJoinSub($costSummaries, 'cost_summaries', function ($join) {
                $join->on('cost_summaries.plan_item_id', '=', 'plan_items.id');
            })
            ->whereIn('plan_items.plan_id', $visiblePlanIds)
            ->where('plan_items.item_type', 'activity')
            ->select(
                'plans.year',
                'org_units.name as org_unit_name',
                'indicators.code as indicator_code',
                'indicators.name as indicator_name',
                'plan_items.title as activity_title',
                'responsible_units.name as responsible_org_unit_name',
                DB::raw('COALESCE(cost_summaries.total_cost, plan_items.total_cost, 0) as total_cost'),
                DB::raw("COALESCE(cost_summaries.funding_source_names, '-') as funding_source_names"),
                DB::raw("COALESCE(quarterly_schedules.activity_quarters_raw, '') as activity_quarters_raw"),
                DB::raw("COALESCE(quarterly_schedules.expected_results, '-') as expected_results"),
                DB::raw("COALESCE(quarterly_schedules.notes, '-') as notes")
            )
            ->orderBy('org_units.name')
            ->orderBy('indicators.code')
            ->orderBy('plan_items.title')
            ->get()
            ->map(function ($row) {
                $row->activity_quarters = collect(explode(',', (string) $row->activity_quarters_raw))
                    ->filter()
                    ->map(fn ($quarter) => (int) $quarter)
                    ->values()
                    ->all();
                return $row;
            });
    }

    private function buildAnnualActivityReport($visiblePlanIds)
    {
        if ($visiblePlanIds->isEmpty()) {
            return collect();
        }

        $scheduleSummaries = DB::table('plan_item_schedules')
            ->select(
                'plan_item_schedules.plan_item_id',
                DB::raw("GROUP_CONCAT(DISTINCT CASE WHEN plan_item_schedules.period_type = 'quarterly' THEN plan_item_schedules.quarter END ORDER BY plan_item_schedules.quarter SEPARATOR ',') as activity_quarters_raw"),
                DB::raw("COUNT(DISTINCT CASE WHEN plan_item_schedules.period_type = 'monthly' THEN plan_item_schedules.month END) as months_count")
            )
            ->groupBy('plan_item_schedules.plan_item_id');

        $costSummaries = DB::table('plan_item_costs')
            ->leftJoin('funding_sources', 'funding_sources.id', '=', 'plan_item_costs.funding_source_id')
            ->select(
                'plan_item_costs.plan_item_id',
                DB::raw('SUM(plan_item_costs.total_cost) as total_cost'),
                DB::raw("GROUP_CONCAT(DISTINCT NULLIF(funding_sources.name, '') ORDER BY funding_sources.name SEPARATOR '; ') as funding_source_names")
            )
            ->groupBy('plan_item_costs.plan_item_id');

        return DB::table('plan_items')
            ->join('plans', 'plans.id', '=', 'plan_items.plan_id')
            ->join('org_units', 'org_units.id', '=', 'plans.org_unit_id')
            ->leftJoin('plan_item_indicators', 'plan_item_indicators.plan_item_id', '=', 'plan_items.id')
            ->leftJoin('indicators', 'indicators.id', '=', 'plan_item_indicators.indicator_id')
            ->leftJoin('org_units as responsible_units', 'responsible_units.id', '=', 'plan_items.responsible_org_unit_id')
            ->leftJoinSub($scheduleSummaries, 'schedule_summaries', function ($join) {
                $join->on('schedule_summaries.plan_item_id', '=', 'plan_items.id');
            })
            ->leftJoinSub($costSummaries, 'cost_summaries', function ($join) {
                $join->on('cost_summaries.plan_item_id', '=', 'plan_items.id');
            })
            ->whereIn('plan_items.plan_id', $visiblePlanIds)
            ->where('plan_items.item_type', 'activity')
            ->select(
                'plans.year',
                'org_units.name as org_unit_name',
                'indicators.code as indicator_code',
                'indicators.name as indicator_name',
                'plan_items.title as activity_title',
                'responsible_units.name as responsible_org_unit_name',
                DB::raw('COALESCE(cost_summaries.total_cost, plan_items.total_cost, 0) as total_cost'),
                DB::raw("COALESCE(cost_summaries.funding_source_names, '-') as funding_source_names"),
                DB::raw("COALESCE(schedule_summaries.activity_quarters_raw, '') as activity_quarters_raw"),
                DB::raw("COALESCE(schedule_summaries.months_count, 0) as months_count")
            )
            ->orderBy('org_units.name')
            ->orderBy('indicators.code')
            ->orderBy('plan_items.title')
            ->get()
            ->map(function ($row) {
                $row->activity_quarters = collect(explode(',', (string) $row->activity_quarters_raw))
                    ->filter()
                    ->map(fn ($quarter) => (int) $quarter)
                    ->values()
                    ->all();
                return $row;
            });
    }

    private function resolveDailyReportMonth(Request $request, $visiblePlanIds): int
    {
        if ($request->filled('month')) {
            return (int) $request->input('month');
        }

        $firstMonthWithData = $visiblePlanIds->isEmpty()
            ? null
            : DB::table('plan_item_schedules')
                ->join('plan_items', 'plan_items.id', '=', 'plan_item_schedules.plan_item_id')
                ->whereIn('plan_items.plan_id', $visiblePlanIds)
                ->where('plan_items.item_type', 'activity')
                ->where('plan_item_schedules.period_type', 'daily')
                ->whereNotNull('plan_item_schedules.month')
                ->orderBy('plan_item_schedules.month')
                ->value('plan_item_schedules.month');

        return (int) ($firstMonthWithData ?: now()->month);
    }

    private function monthLabel(int $month): string
    {
        return [
            1 => 'មករា',
            2 => 'កុម្ភៈ',
            3 => 'មីនា',
            4 => 'មេសា',
            5 => 'ឧសភា',
            6 => 'មិថុនា',
            7 => 'កក្កដា',
            8 => 'សីហា',
            9 => 'កញ្ញា',
            10 => 'តុលា',
            11 => 'វិច្ឆិកា',
            12 => 'ធ្នូ',
        ][$month] ?? '';
    }

    private function renderPdfWithBrowser(string $view, array $data, string $downloadName)
    {
        $browserBinary = $this->browserPdfBinary();

        if ($browserBinary === null) {
            return null;
        }

        $tempDirectory = storage_path('app/planning-browser-pdf');
        File::ensureDirectoryExists($tempDirectory);

        $htmlPath = $tempDirectory . DIRECTORY_SEPARATOR . Str::uuid() . '.html';
        $pdfPath = $tempDirectory . DIRECTORY_SEPARATOR . Str::uuid() . '.pdf';

        File::put($htmlPath, view($view, $data)->render());

        $htmlUri = 'file:///' . ltrim(str_replace('\\', '/', $htmlPath), '/');
        $command = sprintf(
            '"%s" --headless=new --disable-gpu --allow-file-access-from-files --print-to-pdf="%s" --print-to-pdf-no-header "%s" 2>&1',
            $browserBinary,
            $pdfPath,
            $htmlUri
        );

        exec($command, $output, $exitCode);

        if (is_file($htmlPath)) {
            @unlink($htmlPath);
        }

        if ($exitCode !== 0 || !is_file($pdfPath)) {
            if (is_file($pdfPath)) {
                @unlink($pdfPath);
            }

            return null;
        }

        return Response::download($pdfPath, $downloadName)->deleteFileAfterSend(true);
    }

    private function browserPdfBinary(): ?string
    {
        $candidates = [
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function buildKhmerPrintDateLabels(): array
    {
        try {
            $khmerDate = new KhmerDate(now()->format('Y-m-d'));
            $khmerDay = KhmerDate::arabicToKhmerNumber(now()->format('d'));
            $khmerYear = KhmerDate::arabicToKhmerNumber(now()->format('Y'));
            $khmerMonth = $this->monthLabel((int) now()->month);

            return [
                $khmerDate->toLunarDate(),
                'ថ្ងៃទី ' . $khmerDay . ' ខែ' . $khmerMonth . ' ឆ្នាំ ' . $khmerYear,
            ];
        } catch (\Throwable) {
            return [
                'ថ្ងៃចន្ទគតិ',
                'ថ្ងៃទី .... ខែ.... ឆ្នាំ ....',
            ];
        }
    }

    private function buildOrgHeaderLines(?OrgUnit $orgUnit): array
    {
        if ($orgUnit === null) {
            return ['មន្ទីរសុខាភិបាលនៃរដ្ឋបាលខេត្ត'];
        }

        $lines = [];
        $current = $orgUnit;
        $guard = 0;

        while ($current !== null && $guard < 20) {
            $label = trim((string) ($current->name_km ?: $current->name ?: ''));
            if ($label !== '') {
                $lines[] = $label;
            }

            $current = $current->parent()->first();
            $guard++;
        }

        $lines = array_values(array_unique(array_reverse($lines)));

        if ($lines === []) {
            return ['មន្ទីរសុខាភិបាលនៃរដ្ឋបាលខេត្ត'];
        }

        return $lines;
    }
}
