<?php

namespace Modules\HumanResource\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;
use Modules\HumanResource\Entities\Attendance;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\Shift;
use Modules\HumanResource\Entities\ShiftAssignment;
use Modules\HumanResource\Entities\ShiftRoster;
use Modules\HumanResource\Support\AttendanceUnitScope;

class ShiftResolverService
{
    /**
     * Per-request memo of resolveForDate(), keyed by "employeeId|Y-m-d".
     *
     * A single history/schedule request resolves the same employee+date
     * several times over (determineDailyStatus() calls it directly, then
     * punchWindow() re-resolves the current AND previous day, then
     * punchesForDate() calls punchWindow() again) — for an N-day range that
     * turned one logical lookup per day into several times N queries.
     * resolveForDate() is a pure read within a single request (nothing here
     * writes shifts/rosters mid-request), so caching by input is safe.
     *
     * @var array<string, array|null>
     */
    private array $resolveCache = [];

    /** @var array<int, Employee|null> */
    private array $employeeCache = [];

    public function resolveForDate(int $employeeId, CarbonInterface $date): ?array
    {
        $key = $employeeId.'|'.$date->toDateString();
        if (array_key_exists($key, $this->resolveCache)) {
            return $this->resolveCache[$key];
        }

        return $this->resolveCache[$key] = $this->resolveForDateUncached($employeeId, $date);
    }

    private function resolveForDateUncached(int $employeeId, CarbonInterface $date): ?array
    {
        if (! Schema::hasTable('shifts')) {
            return null;
        }
        $employee = $this->employeeCache[$employeeId] ??= Employee::find($employeeId);
        if (! $employee) {
            return null;
        }
        $unitId = AttendanceUnitScope::employeeUnit($employee);
        $day = $date->toDateString();
        $scoped = Schema::hasColumn('shifts', 'department_id');
        $findShift = function ($id, bool $roster = false) use ($unitId, $scoped) {
            $query = Shift::query()->where('is_active', true);
            if ($scoped) {
                $query->where(function ($q) use ($unitId) {
                    // Preserve explicitly assigned legacy shifts until they are migrated.
                    $q->where('department_id', $unitId)->orWhereNull('department_id');
                });
                if (! $roster) {
                    $query->where('is_duty', false);
                }
            }

            return $query->find($id);
        };
        if (Schema::hasTable('shift_rosters')) {
            $roster = ShiftRoster::where('employee_id', $employeeId)->whereDate('roster_date', $day)->first();
            if ($roster) {
                return ['source' => 'roster',
                    'shift' => ($roster->is_day_off || $roster->is_holiday) ? null : $findShift($roster->shift_id, true),
                    'is_day_off' => (bool) $roster->is_day_off, 'is_holiday' => (bool) $roster->is_holiday];
            }
        }
        if (Schema::hasTable('shift_assignments')) {
            $assignment = ShiftAssignment::where('employee_id', $employeeId)->whereDate('effective_date', '<=', $day)
                ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $day))
                ->orderByDesc('effective_date')->orderByDesc('id')->first();
            if ($assignment && ($shift = $findShift($assignment->shift_id))) {
                return $this->resolved($shift, 'assignment');
            }
        }
        if ($employee->default_shift_id && ($shift = $findShift($employee->default_shift_id))) {
            return $this->resolved($shift, 'default');
        }
        if ($scoped && $unitId > 0) {
            $shift = Shift::where('department_id', $unitId)->where('is_default', true)
                ->where('is_active', true)->where('is_duty', false)->first();
            if ($shift) {
                return $this->resolved($shift, 'unit_default');
            }
        }

        return null;
    }

    private function resolved(Shift $shift, string $source): array
    {
        return ['source' => $source, 'shift' => $shift, 'is_day_off' => false, 'is_holiday' => false];
    }

    public function shiftBounds(Shift $shift, CarbonInterface $date): array
    {
        $start = Carbon::parse($date->toDateString().' '.$shift->start_time);
        $end = Carbon::parse($date->toDateString().' '.$shift->end_time);
        if ($shift->is_cross_day || $end->lte($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }

    // A shared half-open window keeps overnight punches on one work date only.
    private function overnightBoundary(int $employeeId, CarbonInterface $day, Shift $shift): Carbon
    {
        [, $end] = $this->shiftBounds($shift, $day);
        $next = $this->resolveForDate($employeeId, $day->copy()->addDay())['shift'] ?? null;
        if ($next) {
            [$nextStart] = $this->shiftBounds($next, $day->copy()->addDay());
            if ($nextStart->gte($end)) {
                return $end->copy()->addSeconds((int) ($end->diffInSeconds($nextStart) / 2));
            }
        }

        return $end->copy()->addHours(4);
    }

    public function punchWindow(int $employeeId, CarbonInterface $date): array
    {
        $start = Carbon::parse($date->toDateString())->startOfDay();
        $end = $start->copy()->addDay();
        $previousDay = $date->copy()->subDay();
        $previous = $this->resolveForDate($employeeId, $previousDay)['shift'] ?? null;
        if ($previous) {
            [, $previousEnd] = $this->shiftBounds($previous, $previousDay);
            if ($previousEnd->gte($start)) {
                $start = $this->overnightBoundary($employeeId, $previousDay, $previous);
            }
        }
        $shift = $this->resolveForDate($employeeId, $date)['shift'] ?? null;
        if ($shift) {
            [, $shiftEnd] = $this->shiftBounds($shift, $date);
            if ($shiftEnd->gte($end)) {
                $end = $this->overnightBoundary($employeeId, $date, $shift);
            }
        }

        return [$start, $end];
    }

    public function punchesForDate(int $employeeId, CarbonInterface $date): \Illuminate\Database\Eloquent\Builder
    {
        [$start, $end] = $this->punchWindow($employeeId, $date);
        $midnight = Carbon::parse($date->toDateString());
        $hasPreviousOvernight = $start->gt($midnight);
        $hasOvernight = $end->gt($midnight->copy()->addDay());

        return Attendance::where('employee_id', $employeeId)
            ->where(function ($q) use ($start, $hasPreviousOvernight) {
                $q->where('time', '>', $start)->orWhere(function ($q) use ($start, $hasPreviousOvernight) {
                    $q->where('time', $start);
                    if ($hasPreviousOvernight) {
                        $q->where('machine_state', '!=', 2);
                    }
                });
            })->where(function ($q) use ($end, $hasOvernight) {
                $q->where('time', '<', $end);
                if ($hasOvernight) {
                    $q->orWhere(fn ($q) => $q->where('time', $end)->where('machine_state', 2));
                }
            });
    }

    public function workDateForPunch(int $employeeId, CarbonInterface $time, ?int $state = null): Carbon
    {
        $date = Carbon::parse($time->toDateString());
        [$start] = $this->punchWindow($employeeId, $date);
        if ($start->gt($date) && $time->eq($start)) {
            $latestState = Attendance::where('employee_id', $employeeId)->where('time', '<', $time)
                ->orderByDesc('time')->orderByDesc('id')->value('machine_state');
            if ($state === 2 || ($state === null && (int) $latestState === 1)) {
                return $date->subDay();
            }
        }

        return $time->lt($start) ? $date->subDay() : $date;
    }
}
