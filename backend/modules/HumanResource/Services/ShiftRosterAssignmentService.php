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
            $row = ShiftRoster::firstOrNew(['employee_id' => $employee->id, 'roster_date' => $day->toDateString()]);
            if (! $row->exists) {
                $row->uuid = (string) Str::uuid();
                $row->created_by = auth()->id();
            }
            $row->fill(['shift_id' => $shift?->id, 'is_day_off' => $off, 'is_holiday' => $holiday, 'note' => $note])->save();
            $rows->push($row);
        }
        // Validate the saved result (the caller's transaction rolls it back):
        // a day's last session — usually a night duty — must end before the
        // next day's first expected session starts.
        foreach (CarbonPeriod::create($start->copy()->subDay(), $end) as $day) {
            $today = $this->resolver->expectedBounds($employee->id, $day);
            $next = $this->resolver->expectedBounds($employee->id, $day->copy()->addDay());
            if ($today && $next && $today[1]->gt($next[0])) {
                throw ValidationException::withMessages(['roster_date' => 'វេនថ្ងៃ '.$day->toDateString().' បញ្ចប់ម៉ោង '.$today[1]->format('H:i')
                    .' ជាន់ម៉ោងចូលធ្វើការថ្ងៃបន្ទាប់ ('.$next[0]->format('H:i').')។ សូមកែម៉ោងវេន ឬកំណត់ថ្ងៃបន្ទាប់ជាថ្ងៃឈប់សម្រាក។ ('.$employee->full_name.')']);
            }
        }
        AttendanceDailySnapshot::where('employee_id', $employee->id)
            ->whereBetween('snapshot_date', [$start->copy()->subDay()->toDateString(), $end->copy()->addDay()->toDateString()])->delete();

        return $rows;
    }
}
