<?php

namespace Modules\HumanResource\Services;

use Carbon\Carbon;
use Modules\HumanResource\Entities\Attendance;
use Modules\HumanResource\Entities\Employee;

class AttendanceCaptureService
{
    private const MACHINE_STATE_IN = 1;

    private const MACHINE_STATE_OUT = 2;

    /**
     * Capture attendance with dedupe + daily exception sync.
     *
     * @param array{
     *   employee_id:int,
     *   time:string|\DateTimeInterface,
     *   attendance_source?:string,
     *   machine_id?:int|null,
     *   machine_state?:int|null,
     *   workplace_id?:int|null,
     *   source_reference?:string|null,
     *   scan_latitude?:float|string|null,
     *   scan_longitude?:float|string|null
     * } $payload
     */
    public static function capture(array $payload): Attendance
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($payload) {
            Employee::query()->whereKey((int) ($payload['employee_id'] ?? 0))->lockForUpdate()->firstOrFail();

            return self::captureLocked($payload);
        });
    }

    private static function captureLocked(array $payload): Attendance
    {
        $employeeId = (int) ($payload['employee_id'] ?? 0);
        $time = self::normalizeTime($payload['time'] ?? null);
        $source = (string) ($payload['attendance_source'] ?? 'manual');

        $employee = Employee::query()->select('id', 'department_id', 'sub_department_id')->find($employeeId);
        $resolvedWorkplaceId = (int) ($payload['workplace_id'] ?? 0);
        if ($resolvedWorkplaceId <= 0) {
            $resolvedWorkplaceId = $employee ? \Modules\HumanResource\Support\AttendanceUnitScope::employeeUnit($employee) : 0;
        }
        $resolvedWorkplaceId = $resolvedWorkplaceId > 0 ? $resolvedWorkplaceId : null;

        // 1) Exact duplicate guard.
        $exact = Attendance::query()
            ->where('employee_id', $employeeId)
            ->where('time', $time)
            ->first();
        if ($exact) {
            self::syncDailyExceptionStatus($employeeId, app(ShiftResolverService::class)->workDateForPunch($employeeId, Carbon::parse($time), (int) $exact->machine_state)->toDateString());

            return $exact;
        }

        // 2) Near duplicate guard (same source & same state within 60 sec).
        $windowStart = Carbon::parse($time)->subMinute()->format('Y-m-d H:i:s');
        $windowEnd = Carbon::parse($time)->addMinute()->format('Y-m-d H:i:s');

        // Mobile retries must not toggle direction for either QR or GPS capture.
        if (in_array($source, ['api_qr', 'api_gps'], true)) {
            $recentQr = Attendance::query()
                ->where('employee_id', $employeeId)
                ->whereBetween('time', [$windowStart, $windowEnd])
                ->where('attendance_source', $source)
                ->first();
            if ($recentQr) {
                self::syncDailyExceptionStatus($employeeId, app(ShiftResolverService::class)->workDateForPunch($employeeId, Carbon::parse($time), (int) $recentQr->machine_state)->toDateString());

                return $recentQr;
            }
        }

        $state = self::resolveMachineState($employeeId, $time, $payload['machine_state'] ?? null);

        $near = Attendance::query()
            ->where('employee_id', $employeeId)
            ->whereBetween('time', [$windowStart, $windowEnd])
            ->where('attendance_source', $source)
            ->where('machine_state', $state)
            ->first();
        if ($near) {
            self::syncDailyExceptionStatus($employeeId, app(ShiftResolverService::class)->workDateForPunch($employeeId, Carbon::parse($time), (int) $near->machine_state)->toDateString());

            return $near;
        }

        $attendance = Attendance::create([
            'employee_id' => $employeeId,
            'workplace_id' => $resolvedWorkplaceId,
            'machine_id' => (int) ($payload['machine_id'] ?? 0),
            'machine_state' => $state,
            'attendance_source' => $source,
            'source_reference' => $payload['source_reference'] ?? null,
            'scan_latitude' => $payload['scan_latitude'] ?? null,
            'scan_longitude' => $payload['scan_longitude'] ?? null,
            'time' => $time,
        ]);

        self::syncDailyExceptionStatus($employeeId, app(ShiftResolverService::class)->workDateForPunch($employeeId, Carbon::parse($time), (int) $attendance->machine_state)->toDateString());

        return $attendance;
    }

    protected static function normalizeTime($value): string
    {
        try {
            if ($value !== null) {
                return Carbon::parse($value)->format('Y-m-d H:i:s');
            }
        } catch (\Throwable $e) {
            // Fall back to now when input time is invalid.
        }

        return now()->format('Y-m-d H:i:s');
    }

    protected static function resolveMachineState(int $employeeId, string $time, $requestedState): int
    {
        if (is_numeric($requestedState)) {
            $state = (int) $requestedState;
            if (in_array($state, [self::MACHINE_STATE_IN, self::MACHINE_STATE_OUT], true)) {
                return $state;
            }
        }

        $resolver = app(ShiftResolverService::class);
        $date = $resolver->workDateForPunch($employeeId, Carbon::parse($time));
        [$windowStart] = $resolver->punchWindow($employeeId, $date);
        $shift = $resolver->resolveForDate($employeeId, $date)['shift'] ?? null;
        if ($shift?->morning_end_time && $shift?->afternoon_start_time) {
            $morningEnd = Carbon::parse($date->toDateString().' '.$shift->morning_end_time);
            $afternoonStart = Carbon::parse($date->toDateString().' '.$shift->afternoon_start_time);
            $boundary = $morningEnd->copy()->addSeconds((int) ($morningEnd->diffInSeconds($afternoonStart) / 2));
            if (Carbon::parse($time)->gte($boundary)) {
                $windowStart = $boundary;
            }
        }
        $latestPunch = Attendance::query()
            ->where('employee_id', $employeeId)
            ->where('time', '>=', $windowStart)
            ->where('time', '<=', $time)
            ->orderByDesc('time')
            ->orderByDesc('id')
            ->first(['id', 'machine_state']);

        if (! $latestPunch) {
            return self::MACHINE_STATE_IN;
        }

        $latestState = (int) $latestPunch->machine_state;
        if ($latestState === self::MACHINE_STATE_IN) {
            return self::MACHINE_STATE_OUT;
        }

        if ($latestState === self::MACHINE_STATE_OUT) {
            return self::MACHINE_STATE_IN;
        }

        $recordsBeforeCount = Attendance::query()
            ->where('employee_id', $employeeId)
            ->where('time', '>=', $windowStart)
            ->where('time', '<=', $time)
            ->count();

        return ($recordsBeforeCount % 2 === 0) ? self::MACHINE_STATE_IN : self::MACHINE_STATE_OUT;
    }

    public static function nextPunchType(int $employeeId, Carbon $time): string
    {
        return self::resolveMachineState($employeeId, $time->toDateTimeString(), null) === self::MACHINE_STATE_IN ? 'in' : 'out';
    }

    /** Update exceptions and invalidate the derived daily summary after a capture. */
    public static function syncDailyExceptionStatus(int $employeeId, string $date): void
    {
        $resolver = app(ShiftResolverService::class);
        $day = Carbon::parse($date);
        [$start, $end] = $resolver->punchWindow($employeeId, $day);
        $records = $resolver->punchesForDate($employeeId, $day)->orderBy('time')->get(['id', 'time', 'machine_state']);
        $shift = $resolver->resolveForDate($employeeId, $day)['shift'] ?? null;
        $result = (new AttendanceSessionService)->evaluate($records, $shift, $day);
        $incomplete = $result['attendance_status'] === 'Incomplete';
        Attendance::whereIn('id', $records->pluck('id'))->update([
            'exception_flag' => $incomplete,
            'exception_reason' => $incomplete ? 'UNPAIRED_PUNCH' : null,
        ]);
        if (\Illuminate\Support\Facades\Schema::hasTable('attendance_daily_snapshots')) {
            \Modules\HumanResource\Entities\AttendanceDailySnapshot::where('employee_id', $employeeId)
                ->whereDate('snapshot_date', $date)->delete();
        }
    }
}
