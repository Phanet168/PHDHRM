<?php

namespace Modules\HumanResource\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Modules\HumanResource\Entities\Attendance;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\Shift;
use Modules\HumanResource\Entities\ShiftAssignment;
use Modules\HumanResource\Entities\ShiftRoster;
use Modules\HumanResource\Support\AttendanceUnitScope;
use Modules\HumanResource\Support\RequestCache;

/**
 * What an employee is expected to work on a date:
 *
 *   shift - the regular hours (unit default, or a non-duty roster shift that
 *           replaces them for that day)
 *   duty  - a rostered duty shift (វេនយាម), ADDED to the regular hours on a
 *           working day; on a weekend/holiday it is the only obligation
 *
 * A roster OFF/holiday row clears both.
 */
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
     * Saving a shift or roster row bumps $generation (see the models'
     * booted()), which drops every instance's memo on its next lookup.
     *
     * @var array<string, array|null>
     */
    private array $resolveCache = [];

    private static int $generation = 0;

    private int $cacheGeneration = 0;

    /** @var array<int, Employee|null> */
    private array $employeeCache = [];

    private readonly AttendanceCalendarService $calendar;

    public function __construct(?AttendanceCalendarService $calendar = null)
    {
        $this->calendar = $calendar ?? new AttendanceCalendarService;
    }

    public function resolveForDate(int $employeeId, CarbonInterface $date): ?array
    {
        if ($this->cacheGeneration !== self::$generation) {
            $this->resolveCache = [];
            $this->cacheGeneration = self::$generation;
        }
        $key = $employeeId.'|'.$date->toDateString();
        if (array_key_exists($key, $this->resolveCache)) {
            return $this->resolveCache[$key];
        }

        return $this->resolveCache[$key] = $this->resolveForDateUncached($employeeId, $date);
    }

    public static function invalidate(): void
    {
        self::$generation++;
    }

    private function resolveForDateUncached(int $employeeId, CarbonInterface $date): ?array
    {
        if (! RequestCache::hasTable('shifts')) {
            return null;
        }
        $employee = $this->employeeCache[$employeeId] ??= Employee::find($employeeId);
        if (! $employee) {
            return null;
        }
        $unitId = AttendanceUnitScope::employeeUnit($employee);
        $day = $date->toDateString();
        $scoped = RequestCache::hasColumn('shifts', 'department_id');
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
        if (RequestCache::hasTable('shift_rosters')) {
            $roster = ShiftRoster::where('employee_id', $employeeId)->whereDate('roster_date', $day)->first();
            if ($roster) {
                $off = ['source' => 'roster', 'shift' => null, 'duty' => null,
                    'is_day_off' => (bool) $roster->is_day_off, 'is_holiday' => (bool) $roster->is_holiday];
                if ($roster->is_day_off || $roster->is_holiday) {
                    return $off;
                }
                $rostered = $findShift($roster->shift_id, true);
                if (! $rostered || ! $rostered->is_duty) {
                    return ['shift' => $rostered] + $off;
                }
                $regular = $this->calendar->isWorkingDay($date)
                    ? ($this->regularShift($employee, $unitId, $day, $findShift, $scoped)['shift'] ?? null) : null;
                // A duty that covers the whole regular day (e.g. 24h) replaces it.
                if ($regular && collect(AttendanceSessionService::slots($regular, $date, $rostered))->every('is_duty', true)) {
                    $regular = null;
                }

                return ['shift' => $regular, 'duty' => $rostered] + $off;
            }
        }

        return $this->regularShift($employee, $unitId, $day, $findShift, $scoped);
    }

    private function regularShift(Employee $employee, int $unitId, string $day, \Closure $findShift, bool $scoped): ?array
    {
        if (RequestCache::hasTable('shift_assignments')) {
            $assignment = ShiftAssignment::where('employee_id', $employee->id)->whereDate('effective_date', '<=', $day)
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
        return ['source' => $source, 'shift' => $shift, 'duty' => null, 'is_day_off' => false, 'is_holiday' => false];
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

    /** The scheduled slots (morning/afternoon/work/duty) for the date, in order. */
    public function slotsForDate(int $employeeId, CarbonInterface $date): array
    {
        $resolved = $this->resolveForDate($employeeId, $date);

        return AttendanceSessionService::slots($resolved['shift'] ?? null, $date, $resolved['duty'] ?? null);
    }

    /** [first scheduled start, last scheduled end] of the date, or null when nothing is scheduled. */
    public function dayBounds(int $employeeId, CarbonInterface $date): ?array
    {
        $slots = $this->slotsForDate($employeeId, $date);

        return $slots ? [$slots[0]['start'], max(array_column($slots, 'end'))] : null;
    }

    /**
     * Like dayBounds() but ignores the unit's regular hours on a weekend or
     * public holiday that has no roster row — nobody is expected then.
     */
    public function expectedBounds(int $employeeId, CarbonInterface $date): ?array
    {
        $resolved = $this->resolveForDate($employeeId, $date);
        if (($resolved['source'] ?? null) !== 'roster' && ! $this->calendar->isWorkingDay($date)) {
            return null;
        }

        return $this->dayBounds($employeeId, $date);
    }

    // A shared half-open window keeps overnight punches on one work date only.
    private function overnightBoundary(int $employeeId, CarbonInterface $day, Carbon $end): Carbon
    {
        $next = $this->dayBounds($employeeId, $day->copy()->addDay());
        if ($next && $next[0]->gte($end)) {
            return $end->copy()->addSeconds((int) ($end->diffInSeconds($next[0]) / 2));
        }

        return $end->copy()->addHours(4);
    }

    public function punchWindow(int $employeeId, CarbonInterface $date): array
    {
        $start = Carbon::parse($date->toDateString())->startOfDay();
        $end = $start->copy()->addDay();
        $previousDay = $date->copy()->subDay();
        $previous = $this->dayBounds($employeeId, $previousDay);
        if ($previous && $previous[1]->gte($start)) {
            $start = $this->overnightBoundary($employeeId, $previousDay, $previous[1]);
        }
        $bounds = $this->dayBounds($employeeId, $date);
        if ($bounds && $bounds[1]->gte($end)) {
            $end = $this->overnightBoundary($employeeId, $date, $bounds[1]);
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
