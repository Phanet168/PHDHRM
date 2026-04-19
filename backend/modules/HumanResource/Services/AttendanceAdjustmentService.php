<?php

namespace Modules\HumanResource\Services;

use Illuminate\Support\Facades\Auth;
use Modules\HumanResource\Entities\Attendance;
use Modules\HumanResource\Entities\AttendanceAdjustment;

class AttendanceAdjustmentService
{
    public function request(array $payload): array
    {
        $employeeId = (int) ($payload['employee_id'] ?? 0);
        $attendanceId = !empty($payload['attendance_id']) ? (int) $payload['attendance_id'] : null;
        $reason = trim((string) ($payload['reason'] ?? ''));

        if ($employeeId <= 0 || $reason === '') {
            return [
                'status' => 'error',
                'message' => 'employee_id and reason are required.',
            ];
        }

        $attendance = null;
        if ($attendanceId) {
            $attendance = Attendance::query()->find($attendanceId);
        }

        $requestedBy = Auth::id() ?: null;

        $adjustment = AttendanceAdjustment::query()->create([
            'employee_id' => $employeeId,
            'attendance_id' => $attendance?->id,
            'old_time' => $attendance?->time,
            'new_time' => $payload['new_time'] ?? null,
            'old_machine_state' => $attendance?->machine_state,
            'new_machine_state' => $payload['new_machine_state'] ?? null,
            'reason' => $reason,
            'status' => 'pending',
            'requested_by' => $requestedBy,
            'audit_meta' => [
                'requested_at' => now()->toDateTimeString(),
                'source' => 'attendance_adjustment_service',
            ],
        ]);

        return [
            'status' => 'ok',
            'message' => 'Adjustment request submitted successfully.',
            'adjustment_id' => (int) $adjustment->id,
            'adjustment_uuid' => (string) $adjustment->uuid,
        ];
    }
}
