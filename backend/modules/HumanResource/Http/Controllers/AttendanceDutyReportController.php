<?php

namespace Modules\HumanResource\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\HumanResource\Services\PeriodAttendanceSummaryService;
use Modules\HumanResource\Support\AttendanceUnitScope;
use Modules\HumanResource\Support\OrgHierarchyAccessService;

/**
 * Attendance Management Phase E: week/quarter/semester/year attendance +
 * duty-hour reports. Each action only resolves its own period boundary,
 * then delegates to the shared PeriodAttendanceSummaryService -- no
 * duplicated summary logic across the four periods. Reuses
 * AttendanceUnitScope for department listing/authorization and
 * OrgHierarchyAccessService::effectiveReportDepartmentIds() for scoping,
 * exactly like the Phase A fix applied to the existing attendance reports
 * -- this new code never repeats that gap.
 */
class AttendanceDutyReportController extends Controller
{
    public function __construct(
        private readonly AttendanceUnitScope $scope,
        private readonly OrgHierarchyAccessService $access,
        private readonly PeriodAttendanceSummaryService $summaryService
    ) {
    }

    private function resolveScopedDepartmentIds(Request $request): array
    {
        $requestedId = (int) ($request->get('workplace_id') ?: $request->get('department_id') ?: 0);
        $requested = $requestedId > 0 ? [$requestedId] : null;
        $effective = $this->access->effectiveReportDepartmentIds(auth()->user(), $requested);

        return $effective ?? [];
    }

    private function render(string $view, string $title, array $extra, Carbon $from, Carbon $to, Request $request): mixed
    {
        $this->scope->authorize('read_attendance_report');
        $departmentIds = $this->resolveScopedDepartmentIds($request);
        $rows = $this->summaryService->summarize($departmentIds, $from, $to);

        if ($request->expectsJson()) {
            return response()->json(['status' => 'ok', 'data' => $rows, 'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()]]);
        }

        return view($view, $extra + [
            'title' => $title,
            'departments' => $this->scope->departments()->get(),
            'selectedDepartmentId' => $request->filled('workplace_id') || $request->filled('department_id')
                ? (int) ($request->get('workplace_id') ?: $request->get('department_id'))
                : null,
            'rows' => $rows,
            'periodFrom' => $from,
            'periodTo' => $to,
        ]);
    }

    public function weekly(Request $request): mixed
    {
        $request->validate(['year' => ['nullable', 'integer', 'between:2000,2100'], 'week' => ['nullable', 'integer', 'between:1,53']]);
        $year = (int) $request->input('year', now()->year);
        $week = (int) $request->input('week', now()->weekOfYear);
        $from = Carbon::now()->setISODate($year, $week)->startOfWeek();
        $to = $from->copy()->endOfWeek();

        return $this->render('humanresource::reports.attendance-weekly', 'របាយការណ៍វត្តមានប្រចាំសប្តាហ៍', ['selectedYear' => $year, 'selectedWeek' => $week], $from, $to, $request);
    }

    public function quarterly(Request $request): mixed
    {
        $request->validate(['year' => ['nullable', 'integer', 'between:2000,2100'], 'quarter' => ['nullable', 'integer', 'between:1,4']]);
        $year = (int) $request->input('year', now()->year);
        $quarter = (int) $request->input('quarter', ceil(now()->month / 3));
        $from = Carbon::create($year, 1, 1)->addMonths(($quarter - 1) * 3)->startOfMonth();
        $to = $from->copy()->addMonths(2)->endOfMonth();

        return $this->render('humanresource::reports.attendance-quarterly', 'របាយការណ៍វត្តមានប្រចាំត្រីមាស', ['selectedYear' => $year, 'selectedQuarter' => $quarter], $from, $to, $request);
    }

    public function semester(Request $request): mixed
    {
        $request->validate(['year' => ['nullable', 'integer', 'between:2000,2100'], 'half' => ['nullable', 'integer', 'between:1,2']]);
        $year = (int) $request->input('year', now()->year);
        $half = (int) $request->input('half', now()->month <= 6 ? 1 : 2);
        $from = Carbon::create($year, 1, 1)->addMonths(($half - 1) * 6)->startOfMonth();
        $to = $from->copy()->addMonths(5)->endOfMonth();

        return $this->render('humanresource::reports.attendance-semester', 'របាយការណ៍វត្តមានប្រចាំឆមាស', ['selectedYear' => $year, 'selectedHalf' => $half], $from, $to, $request);
    }

    public function yearly(Request $request): mixed
    {
        $request->validate(['year' => ['nullable', 'integer', 'between:2000,2100']]);
        $year = (int) $request->input('year', now()->year);
        $from = Carbon::create($year, 1, 1)->startOfYear();
        $to = $from->copy()->endOfYear();

        return $this->render('humanresource::reports.attendance-yearly', 'របាយការណ៍វត្តមានប្រចាំឆ្នាំ', ['selectedYear' => $year], $from, $to, $request);
    }
}
