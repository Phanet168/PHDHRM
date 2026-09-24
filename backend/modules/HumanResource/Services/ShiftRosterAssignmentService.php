<?php

namespace Modules\HumanResource\Services;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\HumanResource\Entities\AttendanceDailySnapshot;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\Shift;
use Modules\HumanResource\Entities\ShiftRoster;

/**
 * Assigns one employee's roster rows for a date range -- the shared
 * per-day overlap-check + upsert logic every roster-writing path needs
 * (individual assign, team assign, auto-generate commit, and the missing-
 * attendance quick-fix's day-off/holiday/shift options). Extracted from
 * ShiftRosterController so a second controller (ManualAttendanceController)
 * could reuse it without duplicating the overlap-check logic.
 *
 * Caller must wrap this in its own DB::transaction() -- kept out of here so
 * a team/bulk caller can wrap MULTIPLE employees in one all-or-nothing
 * transaction.
 */
class ShiftRosterAssignmentService
{
    public function __construct(private readonly ShiftResolverService $resolver)
    {
    }

    /** @return Collection<int, ShiftRoster> */
    public function assign(
        Employee $employee,
        ?Shift $shift,
        Carbon $start,
        Carbon $end,
        bool $off,
        bool $holiday,
        ?string $note
    ): Collection {
        $employee->newQuery()->whereKey($employee->id)->lockForUpdate()->first();
        $rows = collect();
        foreach (CarbonPeriod::create($start, $end) as $day) {
            if ($shift) {
                [$from, $to] = $this->resolver->shiftBounds($shift, $day);
                foreach ([$day->copy()->subDay(), $day->copy()->addDay()] as $adjacentDay) {
                    $adjacent = $adjacentDay->betweenIncluded($start, $end) ? $shift : ($this->resolver->resolveForDate($employee->id, $adjacentDay)['shift'] ?? null);
                    if (! $adjacent) {
                        continue;
                    }
                    [$otherFrom, $otherTo] = $this->resolver->shiftBounds($adjacent, $adjacentDay);
                    if ($from->lt($otherTo) && $to->gt($otherFrom)) {
                        throw ValidationException::withMessages(['roster_date' => 'វេននេះជាន់ម៉ោងជាមួយវេននៅថ្ងៃជាប់គ្នា។ (' . $employee->full_name . ')']);
                    }
                }
            }
            $row = ShiftRoster::firstOrNew(['employee_id' => $employee->id, 'roster_date' => $day->toDateString()]);
            if (! $row->exists) {
                $row->uuid = (string) Str::uuid();
                $row->created_by = auth()->id();
            }
            $row->fill(['shift_id' => $shift?->id, 'is_day_off' => $off, 'is_holiday' => $holiday, 'note' => $note])->save();
            $rows->push($row);
        }
        AttendanceDailySnapshot::where('employee_id', $employee->id)
            ->whereBetween('snapshot_date', [$start->copy()->subDay()->toDateString(), $end->copy()->addDay()->toDateString()])->delete();

        return $rows;
    }
}
