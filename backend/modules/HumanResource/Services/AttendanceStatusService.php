<?php

namespace Modules\HumanResource\Services;

use Carbon\CarbonInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Modules\HumanResource\Entities\ApplyLeave;
use Modules\HumanResource\Entities\Attendance;
use Modules\HumanResource\Entities\AttendanceStatusRule;
use Modules\HumanResource\Entities\Holiday;
use Modules\HumanResource\Entities\WeekHoliday;

class AttendanceStatusService
{
    public function __construct(
        private readonly ShiftResolverService $shiftResolverService,
        private readonly MissionResolverService $missionResolverService,
    ) {
    }

    public function determineDailyStatus(int $employeeId, CarbonInterface $date): array
    {
        $day = $date->toDateString();

        $isHoliday = $this->isPublicHoliday($day);
        $isDayOff = $this->isWeeklyDayOff($date);

        $leave = $this->resolveApprovedLeave($employeeId, $day);
        $mission = $this->missionResolverService->resolveForDate($employeeId, $date);

        $punches = Attendance::query()
            ->where('employee_id', $employeeId)
            ->whereDate('time', $day)
            ->orderBy('time')
            ->get(['id', 'time', 'machine_state']);

        $inTime = $punches->isNotEmpty() ? Carbon::parse((string) $punches->first()->time) : null;
        $outTime = $punches->count() > 1 ? Carbon::parse((string) $punches->last()->time) : null;

        if ($isHoliday) {
            return $this->basePayload($employeeId, $day, 'Holiday', $inTime, $outTime, null, null, null, $leave?->id, $mission['mission_id'] ?? null, true, $isDayOff, [
                'rule' => 'public_holiday',
            ]);
        }

        if ($isDayOff) {
            return $this->basePayload($employeeId, $day, 'Day Off', $inTime, $outTime, null, null, null, $leave?->id, $mission['mission_id'] ?? null, $isHoliday, true, [
                'rule' => 'weekly_day_off',
            ]);
        }

        if ($mission) {
            return $this->basePayload($employeeId, $day, 'On Mission', $inTime, $outTime, null, null, null, null, $mission['mission_id'], $isHoliday, $isDayOff, [
                'rule' => 'approved_mission',
                'mission' => $mission,
            ]);
        }

        if ($leave) {
            return $this->basePayload($employeeId, $day, 'On Leave', $inTime, $outTime, null, null, null, (int) $leave->id, null, $isHoliday, $isDayOff, [
                'rule' => 'approved_leave',
            ]);
        }

        if ($punches->isEmpty()) {
            return $this->basePayload($employeeId, $day, 'Absent', null, null, null, null, null, null, null, $isHoliday, $isDayOff, [
                'rule' => 'no_punch',
            ]);
        }

        if (($punches->count() % 2) !== 0) {
            return $this->basePayload($employeeId, $day, 'Incomplete', $inTime, $outTime, null, null, null, null, null, $isHoliday, $isDayOff, [
                'rule' => 'odd_punch_count',
                'punch_count' => $punches->count(),
            ]);
        }

        $workedMinutes = null;
        if ($inTime && $outTime && $outTime->greaterThan($inTime)) {
            $workedMinutes = $inTime->diffInMinutes($outTime);
        }

        $resolved = $this->shiftResolverService->resolveForDate($employeeId, $date);
        $shift = $resolved['shift'] ?? null;

        if (!$shift) {
            return $this->basePayload($employeeId, $day, 'Present', $inTime, $outTime, $workedMinutes, 0, 0, null, null, $isHoliday, $isDayOff, [
                'rule' => 'present_without_shift',
                'shift_source' => $resolved['source'] ?? null,
            ]);
        }

        $shiftStart = Carbon::parse($day . ' ' . $shift->start_time);
        $shiftEnd = Carbon::parse($day . ' ' . $shift->end_time);
        if ($shift->is_cross_day || $shiftEnd->lessThanOrEqualTo($shiftStart)) {
            $shiftEnd = $shiftEnd->addDay();
        }

        $lateGraceMinutes = (int) ($shift->grace_late_minutes ?? $this->ruleValue('late_grace_minutes', (int) config('humanresource.attendance.late_grace_minutes', 10)));
        $earlyGraceMinutes = (int) ($shift->grace_early_leave_minutes ?? $this->ruleValue('early_leave_grace_minutes', (int) config('humanresource.attendance.early_leave_grace_minutes', 10)));

        $lateMinutes = 0;
        if ($inTime && $inTime->greaterThan($shiftStart->copy()->addMinutes($lateGraceMinutes))) {
            $lateMinutes = $shiftStart->diffInMinutes($inTime);
        }

        $earlyLeaveMinutes = 0;
        if ($outTime && $outTime->lessThan($shiftEnd->copy()->subMinutes($earlyGraceMinutes))) {
            $earlyLeaveMinutes = $outTime->diffInMinutes($shiftEnd);
        }

        $status = 'Present';
        if ($lateMinutes > 0) {
            $status = 'Late';
        } elseif ($earlyLeaveMinutes > 0) {
            $status = 'Early Leave';
        }

        return [
            ...$this->basePayload(
                $employeeId,
                $day,
                $status,
                $inTime,
                $outTime,
                $workedMinutes,
                $lateMinutes,
                $earlyLeaveMinutes,
                null,
                null,
                $isHoliday,
                $isDayOff,
                [
                    'rule' => 'shift_policy',
                    'shift_id' => (int) $shift->id,
                    'shift_source' => $resolved['source'] ?? null,
                    'late_grace_minutes' => $lateGraceMinutes,
                    'early_leave_grace_minutes' => $earlyGraceMinutes,
                ]
            ),
            'shift_id' => (int) $shift->id,
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

    private function isPublicHoliday(string $day): bool
    {
        return Holiday::query()
            ->whereDate('start_date', '<=', $day)
            ->whereDate('end_date', '>=', $day)
            ->exists();
    }

    private function isWeeklyDayOff(CarbonInterface $date): bool
    {
        $weeklyHoliday = WeekHoliday::query()->first();
        if (!$weeklyHoliday || !$weeklyHoliday->dayname) {
            return false;
        }

        $rawDays = explode(',', (string) $weeklyHoliday->dayname);
        $days = array_map(static fn (string $day): string => strtoupper(trim($day)), $rawDays);

        return in_array(strtoupper($date->format('l')), $days, true);
    }

    private function ruleValue(string $ruleKey, int $fallback): int
    {
        if (!Schema::hasTable('attendance_status_rules')) {
            return $fallback;
        }

        $value = AttendanceStatusRule::query()
            ->where('rule_key', $ruleKey)
            ->where('is_active', true)
            ->value('rule_value');

        if ($value === null || $value === '') {
            return $fallback;
        }

        return (int) $value;
    }
}
