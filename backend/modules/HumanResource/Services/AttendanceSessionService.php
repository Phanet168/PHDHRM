<?php

namespace Modules\HumanResource\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Modules\HumanResource\Entities\Shift;

/**
 * Evaluates one work date as an ordered list of scheduled slots:
 *
 *   regular hours  -> morning + afternoon (split day) or one "work" slot
 *   duty roster    -> one "duty" slot ADDED after the regular hours; regular
 *                     slots the duty overlaps (e.g. a 24h duty) are dropped
 *
 * Punches are bucketed into segments split at the midpoint of each gap, so
 * every segment needs its own IN/OUT (lunch must be scanned). The exception
 * is a duty that starts within DUTY_JOIN_GAP_MINUTES of the regular day's
 * end: it shares the afternoon's segment, so staying on through the evening
 * needs no extra OUT/IN at the hand-over (scanning one is still fine).
 */
class AttendanceSessionService
{
    public const DUTY_JOIN_GAP_MINUTES = 60;

    /**
     * @return array<int, array{name: string, start: Carbon, end: Carbon, grace_late: int, grace_early: int, is_duty: bool, shift_id: ?int}>
     */
    public static function slots(?Shift $shift, CarbonInterface $date, ?Shift $duty = null): array
    {
        $slots = [];
        if ($shift) {
            [$start, $end] = self::bounds($shift, $date);
            if ($shift->morning_end_time && $shift->afternoon_start_time) {
                $slots[] = self::slot('morning', $shift, $start, Carbon::parse($date->toDateString().' '.$shift->morning_end_time));
                $slots[] = self::slot('afternoon', $shift, Carbon::parse($date->toDateString().' '.$shift->afternoon_start_time), $end);
            } else {
                $slots[] = self::slot($shift->is_duty ? 'duty' : 'work', $shift, $start, $end);
            }
        }
        if ($duty) {
            [$dutyStart, $dutyEnd] = self::bounds($duty, $date);
            // The duty covers any regular hours it overlaps.
            $slots = array_values(array_filter($slots, fn ($s) => ! ($s['start']->lt($dutyEnd) && $s['end']->gt($dutyStart))));
            $slots[] = self::slot('duty', $duty, $dutyStart, $dutyEnd);
            usort($slots, fn ($a, $b) => $a['start'] <=> $b['start']);
        }

        return $slots;
    }

    /**
     * Groups slots into punch segments. `from`/`until` are the bucket edges
     * (null = open-ended, the day's punch window applies).
     *
     * @return array<int, array{from: ?Carbon, until: ?Carbon, slots: array}>
     */
    public static function segments(array $slots): array
    {
        $segments = [];
        foreach ($slots as $slot) {
            $last = $segments ? $segments[count($segments) - 1] : null;
            $previous = $last ? $last['slots'][count($last['slots']) - 1] : null;
            if ($previous && $slot['is_duty'] && ! $previous['is_duty']
                && $previous['end']->diffInMinutes($slot['start'], false) <= self::DUTY_JOIN_GAP_MINUTES) {
                $segments[count($segments) - 1]['slots'][] = $slot;

                continue;
            }
            if ($previous) {
                $gap = max(0, (int) $previous['end']->diffInSeconds($slot['start'], false));
                $boundary = $previous['end']->copy()->addSeconds(intdiv($gap, 2));
                $segments[count($segments) - 1]['until'] = $boundary;
                $segments[] = ['from' => $boundary, 'until' => null, 'slots' => [$slot]];
            } else {
                $segments[] = ['from' => null, 'until' => null, 'slots' => [$slot]];
            }
        }

        return $segments;
    }

    public function evaluate(Collection $punches, ?Shift $shift, CarbonInterface $date, ?Shift $duty = null): array
    {
        $punches = $punches->sortBy('time')->values();
        $sessions = [];
        $segments = self::segments(self::slots($shift, $date, $duty));
        foreach ($segments as $segment) {
            $inSegment = $punches->filter(function ($p) use ($segment) {
                $time = Carbon::parse($p->time);

                return (! $segment['from'] || $time->gte($segment['from'])) && (! $segment['until'] || $time->lt($segment['until']));
            })->values();
            $sessions = [...$sessions, ...(count($segment['slots']) === 1
                ? [$this->session($segment['slots'][0], $inSegment)]
                : $this->joinedSessions($segment['slots'], $inSegment))];
        }
        if (! $segments) {
            $sessions[] = $this->session(null, $punches);
        }
        // Overtime is time after the day's last scheduled end, not a late lunch checkout.
        foreach (array_keys(array_slice($sessions, 0, -1, true)) as $i) {
            $sessions[$i]['overtime_minutes'] = 0;
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
            'duty_minutes' => (int) $rows->where('is_duty', true)->sum('worked_minutes'),
            // Minutes clocked out past the shift's scheduled end — worked_minutes stays
            // clamped to the shift window (payroll depends on that), this is additive.
            'overtime_minutes' => (int) $rows->sum('overtime_minutes'),
            'late_minutes' => $late,
            'early_leave_minutes' => $early,
            'early_arrival_minutes' => (int) $rows->sum('early_arrival_minutes'),
            'sessions' => $sessions,
        ];
    }

    private static function bounds(Shift $shift, CarbonInterface $date): array
    {
        $start = Carbon::parse($date->toDateString().' '.$shift->start_time);
        $end = Carbon::parse($date->toDateString().' '.$shift->end_time);
        if ($shift->is_cross_day || $end->lte($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }

    private static function slot(string $name, Shift $shift, Carbon $start, Carbon $end): array
    {
        return ['name' => $name, 'start' => $start, 'end' => $end, 'grace_late' => (int) $shift->grace_late_minutes,
            'grace_early' => (int) $shift->grace_early_leave_minutes, 'is_duty' => $name === 'duty', 'shift_id' => $shift->id];
    }

    /**
     * Pairs IN/OUT punches in order. Old imported rows have no direction;
     * explicit directions always take precedence.
     *
     * @return array{pairs: array<int, array{0: Carbon, 1: Carbon}>, open: ?Carbon, first_in: ?Carbon, last_out: ?Carbon, invalid: bool}
     */
    private function pair(Collection $punches): array
    {
        $pairs = [];
        $pending = null;
        $firstIn = null;
        $lastOut = null;
        $invalid = false;
        foreach ($punches as $punch) {
            // Minute precision, as times are shown (HH:MM): 07:45:40 with 15 min grace is on time.
            $time = Carbon::parse($punch->time)->startOfMinute();
            $state = (int) ($punch->machine_state ?? 0);
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
                $pairs[] = [$pending, $time];
                $lastOut = $time;
                $pending = null;
            }
        }

        return ['pairs' => $pairs, 'open' => $pending, 'first_in' => $firstIn, 'last_out' => $lastOut, 'invalid' => $invalid];
    }

    private function session(?array $slot, Collection $punches): array
    {
        $start = $slot['start'] ?? null;
        $end = $slot['end'] ?? null;
        $paired = $this->pair($punches);
        $worked = 0;
        $overtime = 0;
        foreach ($paired['pairs'] as [$in, $out]) {
            $from = $start && $in->lt($start) ? $start : $in;
            $to = $end && $out->gt($end) ? $end : $out;
            if ($to->gt($from)) {
                $worked += (int) $from->diffInMinutes($to);
            }
            if ($end && $out->gt($end)) {
                $overtime += (int) $end->diffInMinutes($out);
            }
        }
        $firstIn = $paired['first_in'];
        $lastOut = $paired['last_out'];
        $late = $start && $firstIn && $firstIn->gt($start->copy()->addMinutes($slot['grace_late']))
            ? (int) $start->diffInMinutes($firstIn) : 0;
        $early = $end && $lastOut && $lastOut->lt($end->copy()->subMinutes($slot['grace_early']))
            ? (int) $lastOut->diffInMinutes($end) : 0;

        return $this->row($slot['name'] ?? 'work', $slot, $firstIn, $lastOut, $worked, $overtime, $late, $early,
            $start && $firstIn && $firstIn->lt($start) ? (int) $firstIn->diffInMinutes($start) : 0,
            ! $paired['invalid'] && ! $paired['open'] && $firstIn !== null && $lastOut !== null, $punches->count());
    }

    /**
     * Afternoon + adjacent duty sharing one segment: one set of pairs, scored
     * per slot. The hand-over point between slots is the earlier slot's end.
     */
    private function joinedSessions(array $slots, Collection $punches): array
    {
        $paired = $this->pair($punches);
        $intervals = $paired['pairs'];
        if ($paired['open']) {
            $intervals[] = [$paired['open'], null];
        }
        $segmentComplete = ! $paired['invalid'] && ! $paired['open'] && $paired['first_in'] && $paired['last_out'];
        $last = count($slots) - 1;
        $rows = [];
        foreach ($slots as $i => $slot) {
            $regionFrom = $i === 0 ? null : $slots[$i - 1]['end'];
            $regionUntil = $i === $last ? null : $slot['end'];
            $region = $punches->filter(function ($p) use ($regionFrom, $regionUntil) {
                $time = Carbon::parse($p->time);

                return (! $regionFrom || $time->gte($regionFrom)) && (! $regionUntil || $time->lt($regionUntil));
            });
            $worked = 0;
            $overtime = 0;
            $arrive = null;
            $leave = null;
            $coversStart = false;
            $coversEnd = false;
            foreach ($intervals as [$in, $out]) {
                if (($out && $out->lte($slot['start'])) || $in->gte($slot['end'])) {
                    continue;
                }
                $coversStart = $coversStart || $in->lte($slot['start']);
                $coversEnd = $coversEnd || ! $out || $out->gte($slot['end']);
                $arrive = $arrive && $arrive->lte($in) ? $arrive : $in;
                $leave = ! $out ? $leave : ($leave && $leave->gte($out) ? $leave : $out);
                if ($out) {
                    $from = $in->lt($slot['start']) ? $slot['start'] : $in;
                    $to = $out->gt($slot['end']) ? $slot['end'] : $out;
                    $worked += max(0, (int) $from->diffInMinutes($to, false));
                    if ($i === $last && $out->gt($slot['end'])) {
                        $overtime += (int) $slot['end']->diffInMinutes($out);
                    }
                }
            }
            $firstIn = $i === 0 ? $paired['first_in'] : $arrive;
            $lastOut = $i === $last ? $paired['last_out'] : $leave;
            $late = ! $coversStart && $firstIn && $firstIn->gt($slot['start']->copy()->addMinutes($slot['grace_late']))
                ? (int) $slot['start']->diffInMinutes($firstIn) : 0;
            $early = ! $coversEnd && $lastOut && $lastOut->lt($slot['end']->copy()->subMinutes($slot['grace_early']))
                ? (int) $lastOut->diffInMinutes($slot['end']) : 0;
            // Each punch shows on the slot it falls in (an OUT exactly at the hand-over
            // closes the earlier slot); presence carried across the hand-over without
            // a scan is flagged as continued instead of looking like a missing punch.
            $ownIn = collect($intervals)->map(fn ($iv) => $iv[0])
                ->filter(fn ($t) => (! $regionFrom || $t->gte($regionFrom)) && (! $regionUntil || $t->lt($regionUntil)))->min();
            $ownOut = collect($intervals)->map(fn ($iv) => $iv[1])->filter()
                ->filter(fn ($t) => (! $regionFrom || $t->gt($regionFrom)) && (! $regionUntil || $t->lte($regionUntil)))->max();
            $spans = fn (?Carbon $point) => $point !== null
                && collect($intervals)->contains(fn ($iv) => $iv[0]->lt($point) && (! $iv[1] || $iv[1]->gt($point)));
            $rows[] = $this->row($slot['name'], $slot, $ownIn, $ownOut,
                $worked, $overtime, $late, $early,
                $i === 0 && $firstIn && $firstIn->lt($slot['start']) ? (int) $firstIn->diffInMinutes($slot['start']) : 0,
                $segmentComplete && $arrive !== null, $region->count(), $spans($regionFrom), $spans($regionUntil));
        }

        return $rows;
    }

    private function row(string $name, ?array $slot, ?Carbon $in, ?Carbon $out, int $worked, int $overtime, int $late, int $early, int $earlyArrival, bool $complete, int $count, bool $inContinued = false, bool $outContinued = false): array
    {
        return [
            'name' => $name,
            'is_duty' => (bool) ($slot['is_duty'] ?? false),
            'scheduled_in' => $slot ? $slot['start']->format('Y-m-d H:i:s') : null,
            'scheduled_out' => $slot ? $slot['end']->format('Y-m-d H:i:s') : null,
            'in_time' => $in?->format('Y-m-d H:i:s'),
            'out_time' => $out?->format('Y-m-d H:i:s'),
            'worked_minutes' => $worked,
            'overtime_minutes' => $overtime,
            'late_minutes' => $late,
            'early_leave_minutes' => $early,
            'early_arrival_minutes' => $earlyArrival,
            'complete' => $complete,
            'punch_count' => $count,
            // Afternoon -> adjacent duty without hand-over scans: no own OUT/IN, but present.
            'in_continued' => $inContinued,
            'out_continued' => $outContinued,
        ];
    }
}
