<?php

namespace Modules\HumanResource\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Modules\HumanResource\Entities\Shift;

class AttendanceSessionService
{
    public function evaluate(Collection $punches, ?Shift $shift, CarbonInterface $date): array
    {
        $punches = $punches->sortBy('time')->values();
        $sessions = [];
        if ($shift) {
            [$start, $end] = (new ShiftResolverService)->shiftBounds($shift, $date);
            if ($shift->morning_end_time && $shift->afternoon_start_time) {
                $morningEnd = Carbon::parse($date->toDateString().' '.$shift->morning_end_time);
                $afternoonStart = Carbon::parse($date->toDateString().' '.$shift->afternoon_start_time);
                $boundary = $morningEnd->copy()->addSeconds((int) ($morningEnd->diffInSeconds($afternoonStart) / 2));
                $sessions[] = $this->session('morning', $punches->filter(fn ($p) => Carbon::parse($p->time)->lt($boundary)), $start, $morningEnd, $shift);
                $sessions[] = $this->session('afternoon', $punches->filter(fn ($p) => Carbon::parse($p->time)->gte($boundary)), $afternoonStart, $end, $shift);
            } else {
                $sessions[] = $this->session($shift->is_duty ? 'duty' : 'work', $punches, $start, $end, $shift);
            }
        } else {
            $sessions[] = $this->session('work', $punches, null, null, null);
        }
        $rows = collect($sessions);
        $late = (int) $rows->sum('late_minutes');
        $early = (int) $rows->sum('early_leave_minutes');
        $incomplete = $rows->contains(fn ($s) => ! $s['complete']);
        $status = $punches->isEmpty() ? 'Absent' : ($incomplete ? 'Incomplete' : ($late > 0 ? 'Late' : ($early > 0 ? 'Early Leave' : 'Present')));

        return [
            'attendance_status' => $status,
            'in_time' => $rows->pluck('in_time')->filter()->min(),
            'out_time' => $rows->pluck('out_time')->filter()->max(),
            'worked_minutes' => (int) $rows->sum('worked_minutes'),
            // Minutes clocked out past the shift's scheduled end — worked_minutes stays
            // clamped to the shift window (payroll depends on that), this is additive.
            'overtime_minutes' => (int) $rows->sum('overtime_minutes'),
            'late_minutes' => $late,
            'early_leave_minutes' => $early,
            'early_arrival_minutes' => (int) $rows->sum('early_arrival_minutes'),
            'sessions' => $sessions,
        ];
    }

    private function session(string $name, Collection $punches, ?Carbon $start, ?Carbon $end, ?Shift $shift): array
    {
        $pending = null;
        $firstIn = null;
        $lastOut = null;
        $worked = 0;
        $overtime = 0;
        $invalid = false;
        foreach ($punches as $punch) {
            $time = Carbon::parse($punch->time);
            $state = (int) ($punch->machine_state ?? 0);
            // Old imported rows have no direction; explicit directions always take precedence.
            if (! in_array($state, [1, 2], true)) {
                $state = $pending ? 2 : 1;
            }
            if ($state === 1) {
                if ($pending) {
                    $invalid = true;

                    continue;
                }
                $pending = $time;
                $firstIn ??= $time;
            } else {
                if (! $pending || $time->lte($pending)) {
                    $invalid = true;

                    continue;
                }
                $lastOut = $time;
                $from = $start && $pending->lt($start) ? $start : $pending;
                $to = $end && $time->gt($end) ? $end : $time;
                if ($to->gt($from)) {
                    $worked += (int) $from->diffInMinutes($to);
                }
                if ($end && $time->gt($end)) {
                    $overtime += (int) $end->diffInMinutes($time);
                }
                $pending = null;
            }
        }
        $late = $start && $firstIn && $firstIn->gt($start->copy()->addMinutes((int) $shift?->grace_late_minutes))
            ? (int) $start->diffInMinutes($firstIn) : 0;
        $early = $end && $lastOut && $lastOut->lt($end->copy()->subMinutes((int) $shift?->grace_early_leave_minutes))
            ? (int) $lastOut->diffInMinutes($end) : 0;

        return [
            'name' => $name,
            'scheduled_in' => $start?->format('Y-m-d H:i:s'),
            'scheduled_out' => $end?->format('Y-m-d H:i:s'),
            'in_time' => $firstIn?->format('Y-m-d H:i:s'),
            'out_time' => $lastOut?->format('Y-m-d H:i:s'),
            'worked_minutes' => $worked,
            'overtime_minutes' => $overtime,
            'late_minutes' => $late,
            'early_leave_minutes' => $early,
            'early_arrival_minutes' => $start && $firstIn && $firstIn->lt($start) ? (int) $firstIn->diffInMinutes($start) : 0,
            'complete' => ! $invalid && ! $pending && $firstIn !== null && $lastOut !== null,
            'punch_count' => $punches->count(),
        ];
    }
}
