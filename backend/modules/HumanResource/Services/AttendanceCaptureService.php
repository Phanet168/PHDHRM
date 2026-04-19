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
        $employeeId = (int) ($payload['employee_id'] ?? 0);
        $time = self::normalizeTime($payload['time'] ?? null);
        $source = (string) ($payload['attendance_source'] ?? 'manual');

        $employee = Employee::query()->select('id', 'department_id', 'sub_department_id')->find($employeeId);
        $resolvedWorkplaceId = (int) ($payload['workplace_id'] ?? 0);
        if ($resolvedWorkplaceId <= 0) {
            $resolvedWorkplaceId = (int) ($employee?->sub_department_id ?: $employee?->department_id ?: 0);
        }
        $resolvedWorkplaceId = $resolvedWorkplaceId > 0 ? $resolvedWorkplaceId : null;

        // 1) Exact duplicate guard.
        $exact = Attendance::query()
            ->where('employee_id', $employeeId)
            ->where('time', $time)
            ->first();
        if ($exact) {
            self::syncDailyExceptionStatus($employeeId, Carbon::parse($time)->toDateString());
            return $exact;
        }

        // 2) Near duplicate guard (same source & same state within 60 sec).
        $windowStart = Carbon::parse($time)->subMinute()->format('Y-m-d H:i:s');
        $windowEnd = Carbon::parse($time)->addMinute()->format('Y-m-d H:i:s');

        // For QR scan flow, block accidental double-scan even if state would toggle.
        if ($source === 'api_qr') {
            $recentQr = Attendance::query()
                ->where('employee_id', $employeeId)
                ->whereBetween('time', [$windowStart, $windowEnd])
                ->where('attendance_source', $source)
                ->first();
            if ($recentQr) {
                self::syncDailyExceptionStatus($employeeId, Carbon::parse($time)->toDateString());
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
            self::syncDailyExceptionStatus($employeeId, Carbon::parse($time)->toDateString());
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

        self::syncDailyExceptionStatus($employeeId, Carbon::parse($time)->toDateString());

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

        $date = Carbon::parse($time)->toDateString();
        $latestPunch = Attendance::query()
            ->where('employee_id', $employeeId)
            ->whereDate('time', $date)
            ->where('time', '<=', $time)
            ->orderByDesc('time')
            ->orderByDesc('id')
            ->first(['id', 'machine_state']);

        if (!$latestPunch) {
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
            ->whereDate('time', $date)
            ->where('time', '<=', $time)
            ->count();

        return ($recordsBeforeCount % 2 === 0) ? self::MACHINE_STATE_IN : self::MACHINE_STATE_OUT;
    }

    /**
     * A simple and reliable exception rule:
     * odd punch count in a day = unpaired IN/OUT.
     */
    public static function syncDailyExceptionStatus(int $employeeId, string $date): void
    {
        $records = Attendance::query()
            ->where('employee_id', $employeeId)
            ->whereDate('time', $date)
            ->get(['id']);

        if ($records->isEmpty()) {
            return;
        }

        $hasUnpairedPunch = ($records->count() % 2) !== 0;

        Attendance::query()
            ->whereIn('id', $records->pluck('id')->all())
            ->update([
                'exception_flag' => $hasUnpairedPunch,
                'exception_reason' => $hasUnpairedPunch ? 'UNPAIRED_PUNCH' : null,
            ]);
    }
}

