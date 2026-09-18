<?php

namespace Modules\HumanResource\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\HumanResource\Entities\AttendanceDailySnapshot;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\Shift;

/**
 * Attendance Management Phase E: one shared summarizer backing the week/
 * quarter/semester/year attendance reports. Built entirely on the
 * ALREADY-COMPUTED attendance_daily_snapshots table (see
 * AttendanceStatusService::determineDailyStatus()) rather than re-deriving
 * from raw punches -- only the period's date boundary differs per report;
 * this method is period-agnostic.
 */
class PeriodAttendanceSummaryService
{
    private const PRESENT_LIKE_STATUSES = ['Present', 'Late', 'Early Leave', 'Incomplete'];

    /**
     * @param int[] $departmentIds empty array means "no restriction" (caller already resolved this via OrgHierarchyAccessService::effectiveReportDepartmentIds())
     * @return Collection<int, array{employee_id:int, employee_name:string, present_days:int, absent_days:int, leave_days:int, holiday_days:int, duty_minutes:int, regular_minutes:int, duty_hours:float, regular_hours:float}>
     */
    public function summarize(array $departmentIds, Carbon $from, Carbon $to): Collection
    {
        $employeeQuery = Employee::query()->where('is_active', true);
        if ($departmentIds !== []) {
            $employeeQuery->where(function ($q) use ($departmentIds) {
                $q->whereIn('department_id', $departmentIds)->orWhereIn('sub_department_id', $departmentIds);
            });
        }
        $employees = $employeeQuery->get(['id', 'first_name', 'last_name', 'department_id', 'sub_department_id']);

        if ($employees->isEmpty()) {
            return collect();
        }

        $snapshotsByEmployee = AttendanceDailySnapshot::query()
            ->select(['employee_id', 'attendance_status', 'worked_minutes', 'shift_id'])
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('snapshot_date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->groupBy('employee_id');

        $dutyShiftIds = Shift::where('is_duty', true)->pluck('id')->flip();

        return $employees->map(function (Employee $employee) use ($snapshotsByEmployee, $dutyShiftIds) {
            $rows = $snapshotsByEmployee->get($employee->id, collect());
            $dutyMinutes = (int) $rows->filter(fn ($r) => $r->shift_id && $dutyShiftIds->has($r->shift_id))->sum('worked_minutes');
            $totalMinutes = (int) $rows->sum('worked_minutes');

            return [
                'employee_id' => $employee->id,
                'employee_name' => $employee->full_name,
                'present_days' => $rows->whereIn('attendance_status', self::PRESENT_LIKE_STATUSES)->count(),
                'absent_days' => $rows->where('attendance_status', 'Absent')->count(),
                'leave_days' => $rows->where('attendance_status', 'On Leave')->count(),
                'holiday_days' => $rows->whereIn('attendance_status', ['Holiday', 'Day Off'])->count(),
                'duty_minutes' => $dutyMinutes,
                'regular_minutes' => $totalMinutes - $dutyMinutes,
                'duty_hours' => round($dutyMinutes / 60, 1),
                'regular_hours' => round(($totalMinutes - $dutyMinutes) / 60, 1),
            ];
        })->values();
    }
}
