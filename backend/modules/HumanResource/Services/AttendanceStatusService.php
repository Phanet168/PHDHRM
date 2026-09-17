<?php

namespace Modules\HumanResource\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Modules\HumanResource\Entities\ApplyLeave;
use Modules\HumanResource\Entities\Holiday;
use Modules\HumanResource\Entities\WeekHoliday;

class AttendanceStatusService
{
    // The weekly-day-off config is one global row, identical for every date
    // in a request — memoized so an N-day history/schedule request doesn't
    // re-query it N times.
    private ?WeekHoliday $weekHolidayCache = null;

    private bool $weekHolidayLoaded = false;

    public function __construct(
        private readonly ShiftResolverService $shiftResolverService,
        private readonly MissionResolverService $missionResolverService,
    ) {
    }

    public function determineDailyStatus(int $employeeId, CarbonInterface $date): array
    {
        $day = $date->toDateString();

        $resolved = $this->shiftResolverService->resolveForDate($employeeId, $date);
        $shift = $resolved['shift'] ?? null;
        $holidayName = $this->publicHolidayName($day);
        $isHoliday = $holidayName !== null;
        $isDayOff = $this->isWeeklyDayOff($date);
        // An explicit roster is authoritative, including duty on weekends/public holidays.
        if (($resolved['source'] ?? null) === 'roster') {
            $isHoliday = (bool) $resolved['is_holiday'];
            $isDayOff = (bool) $resolved['is_day_off'];
            // The roster doesn't carry a holiday name — only trust the
            // calendar's name when the calendar itself is the one saying
            // it's a holiday.
            if (! $isHoliday) {
                $holidayName = null;
            }
        }
        $leave = $this->resolveApprovedLeave($employeeId, $day);
        $mission = $this->missionResolverService->resolveForDate($employeeId, $date);
        [$windowStart, $windowEnd] = $this->shiftResolverService->punchWindow($employeeId, $date);
        $punches = $this->shiftResolverService->punchesForDate($employeeId, $date)
            ->orderBy('time')->orderBy('id')->get(['id', 'time', 'machine_state']);
        $result = (new AttendanceSessionService)->evaluate($punches, $shift, $date);
        $status = $result['attendance_status'];
        $rule = $shift ? 'shift_policy' : 'present_without_shift';
        if ($mission) {
            $status = 'On Mission';
            $rule = 'approved_mission';
        } elseif ($leave) {
            $status = 'On Leave';
            $rule = 'approved_leave';
        } elseif ($isHoliday) {
            $status = 'Holiday';
            $rule = 'public_holiday';
        } elseif ($isDayOff) {
            $status = 'Day Off';
            $rule = 'weekly_day_off';
        } elseif (! $shift && ($resolved['source'] ?? null) === 'roster') {
            $status = 'Incomplete';
            $rule = 'missing_roster_shift';
        }

        $exempt = in_array($status, ['On Mission', 'On Leave', 'Holiday', 'Day Off'], true);

        return [
            ...$this->basePayload($employeeId, $day, $status,
                $result['in_time'] ? Carbon::parse($result['in_time']) : null,
                $result['out_time'] ? Carbon::parse($result['out_time']) : null,
                $result['worked_minutes'], $exempt ? 0 : $result['late_minutes'], $exempt ? 0 : $result['early_leave_minutes'],
                $exempt ? 0 : $result['overtime_minutes'],
                $leave?->id, $mission['mission_id'] ?? null, $isHoliday, $isDayOff,
                ['rule' => $rule, 'shift_source' => $resolved['source'] ?? null,
                    'shift_id' => $shift?->id, 'department_id' => $shift?->department_id,
                    'sessions' => $result['sessions'], 'early_arrival_minutes' => $result['early_arrival_minutes'],
                    'window_start' => $windowStart->toDateTimeString(), 'window_end' => $windowEnd->toDateTimeString(),
                    'holiday_name' => $holidayName]
            ),
            'shift_id' => $shift?->id,
        ];
    }

    private function basePayload(
        int $employeeId,
        string $day,
        string $status,
        ?Carbon $inTime,
        ?Carbon $outTime,
        ?int $workedMinutes,
        ?int $lateMinutes,
        ?int $earlyLeaveMinutes,
        ?int $overtimeMinutes,
        ?int $leaveId,
        ?int $missionId,
        bool $isHoliday,
        bool $isDayOff,
        array $policyPayload,
    ): array {
        return [
            'employee_id' => $employeeId,
            'snapshot_date' => $day,
            'attendance_status' => $status,
            'in_time' => $inTime?->format('Y-m-d H:i:s'),
            'out_time' => $outTime?->format('Y-m-d H:i:s'),
            'worked_minutes' => $workedMinutes,
            'late_minutes' => $lateMinutes,
            'early_leave_minutes' => $earlyLeaveMinutes,
            'overtime_minutes' => $overtimeMinutes,
            'leave_id' => $leaveId,
            'mission_id' => $missionId,
            'is_holiday' => $isHoliday,
            'is_day_off' => $isDayOff,
            'policy_payload' => $policyPayload,
            'computed_at' => now()->format('Y-m-d H:i:s'),
        ];
    }

    private function resolveApprovedLeave(int $employeeId, string $day): ?ApplyLeave
    {
        return ApplyLeave::query()
            ->where('employee_id', $employeeId)
            ->where('is_approved', true)
            ->whereDate('leave_approved_start_date', '<=', $day)
            ->whereDate('leave_approved_end_date', '>=', $day)
            ->orderByDesc('id')
            ->first();
    }

    private function publicHolidayName(string $day): ?string
    {
        return Holiday::query()
            ->whereDate('start_date', '<=', $day)
            ->whereDate('end_date', '>=', $day)
            ->value('holiday_name');
    }

    private function isWeeklyDayOff(CarbonInterface $date): bool
    {
        if (! $this->weekHolidayLoaded) {
            $this->weekHolidayCache = WeekHoliday::query()->first();
            $this->weekHolidayLoaded = true;
        }
        $weeklyHoliday = $this->weekHolidayCache;
        if (! $weeklyHoliday || ! $weeklyHoliday->dayname) {
            return false;
        }

        $rawDays = explode(',', (string) $weeklyHoliday->dayname);
        $days = array_map(static fn (string $day): string => strtoupper(trim($day)), $rawDays);

        return in_array(strtoupper($date->format('l')), $days, true);
    }
}
