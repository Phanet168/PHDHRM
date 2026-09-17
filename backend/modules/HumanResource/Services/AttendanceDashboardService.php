<?php

namespace Modules\HumanResource\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceDashboardService
{
    public function __construct(private readonly AttendanceStatusService $status, private readonly ShiftResolverService $shifts)
    {
    }

    public function build(Collection $employees, Carbon $date): array
    {
        $rows = $employees->map(function ($employee) use ($date) {
            $daily = $this->status->determineDailyStatus($employee->id, $date);
            $shift = $this->shifts->resolveForDate($employee->id, $date)['shift'] ?? null;
            $sessions = collect($daily['policy_payload']['sessions'] ?? []);
            $punchCount = (int) $sessions->sum('punch_count');
            $exempt = in_array($daily['attendance_status'], ['On Leave', 'On Mission', 'Holiday', 'Day Off'], true);
            $ended = $date->lt(today());
            if ($shift) {
                [, $end] = $this->shifts->shiftBounds($shift, $date);
                $ended = now()->gte($end);
            }
            $missingFinishedSession = ! $exempt && $sessions->contains(fn ($s) => ! $s['complete'] && ! empty($s['scheduled_out']) && now()->gte(Carbon::parse($s['scheduled_out'])));
            $state = $daily['attendance_status'];
            if ($missingFinishedSession && ! $ended && $state === 'Absent') {
                $state = 'Incomplete';
            }
            if (! $exempt && ! $shift) {
                $state = 'Unscheduled';
            } elseif (! $exempt && ! $ended && in_array($state, ['Absent', 'Incomplete'], true) && ! $missingFinishedSession) {
                $state = $punchCount ? 'In Progress' : 'Waiting';
            }
            $attention = ! $exempt && ($daily['late_minutes'] > 0 || $daily['early_leave_minutes'] > 0 || $missingFinishedSession || ($ended && in_array($state, ['Absent', 'Incomplete'], true)));

            return [
                'employee_id' => (int) $employee->id, 'employee_number' => $employee->employee_id,
                'name' => $employee->full_name, 'status' => $state, 'needs_attention' => $attention,
                'recorded' => $punchCount > 0, 'expected' => ! $exempt && $shift !== null,
                'is_duty' => ! $exempt && (bool) $shift?->is_duty,
                'shift_name' => $shift?->name, 'is_cross_day' => (bool) $shift?->is_cross_day,
                'late_minutes' => (int) $daily['late_minutes'], 'early_leave_minutes' => (int) $daily['early_leave_minutes'],
                'early_arrival_minutes' => (int) ($daily['policy_payload']['early_arrival_minutes'] ?? 0),
                'worked_minutes' => (int) $daily['worked_minutes'], 'sessions' => $sessions->all(),
            ];
        });
        $summary = [
            'total' => $rows->count(), 'expected' => $rows->where('expected', true)->count(),
            'recorded' => $rows->where('recorded', true)->count(),
            'expected_recorded' => $rows->where('expected', true)->where('recorded', true)->count(),
            'attention' => $rows->where('needs_attention', true)->count(), 'duty' => $rows->where('is_duty', true)->count(),
            'late' => $rows->where('late_minutes', '>', 0)->count(), 'early_leave' => $rows->where('early_leave_minutes', '>', 0)->count(),
            'absent' => $rows->where('status', 'Absent')->count(), 'incomplete' => $rows->where('status', 'Incomplete')->count(),
            'waiting' => $rows->where('status', 'Waiting')->count(), 'in_progress' => $rows->where('status', 'In Progress')->count(),
            'leave' => $rows->where('status', 'On Leave')->count(), 'mission' => $rows->where('status', 'On Mission')->count(),
            'off' => $rows->whereIn('status', ['Day Off', 'Holiday'])->count(), 'unscheduled' => $rows->where('status', 'Unscheduled')->count(),
        ];
        $sessionSummary = [];
        foreach (['morning', 'afternoon', 'duty', 'work'] as $name) {
            $parts = $rows->where('expected', true)->flatMap(fn ($r) => $r['sessions'])->where('name', $name);
            $sessionSummary[$name] = ['expected' => $parts->count(), 'complete' => $parts->where('complete', true)->count(), 'late' => $parts->where('late_minutes', '>', 0)->count()];
        }

        return ['summary' => $summary, 'sessions' => $sessionSummary, 'rows' => $rows];
    }
}
