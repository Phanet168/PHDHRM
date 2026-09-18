<?php

namespace Modules\HumanResource\Services;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Modules\HumanResource\Entities\ApplyLeave;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\Shift;
use Modules\HumanResource\Entities\ShiftRoster;
use Modules\HumanResource\Entities\ShiftTeam;
use Modules\HumanResource\Support\AttendanceUnitScope;

/**
 * Attendance Management Phase D: automatic duty-roster generation with fair
 * rotation. For each day in the requested range, picks the eligible
 * employee who was LEAST RECENTLY assigned this exact duty shift --
 * self-correcting (accounts for real roster history, including manual
 * overrides) rather than a fixed/naive sequence. Returns a PREVIEW only;
 * ShiftRosterController::generateCommit() re-runs and persists it through
 * the same assignOneEmployee() every other roster-assignment path uses.
 */
class ShiftRosterGeneratorService
{
    public function __construct(private readonly AttendanceUnitScope $scope)
    {
    }

    /**
     * @return array<int, array{date: string, employee_id: ?int, employee_name: ?string, reason: ?string}>
     */
    public function generate(Department $department, Shift $shift, Carbon $start, Carbon $end, ?ShiftTeam $team = null): array
    {
        $pool = $team
            ? $team->activeEmployees()->get()
            : $this->scope->employees($department->id)->where('is_active', 1)->get();

        if ($pool->isEmpty()) {
            return [];
        }

        // Seed each candidate's "last assigned to THIS duty shift" date from
        // real history, so the rotation is fair from day one and
        // self-corrects after any manual roster edit -- never a blind fixed
        // sequence that ignores what already happened.
        $lastAssigned = ShiftRoster::whereIn('employee_id', $pool->pluck('id'))
            ->where('shift_id', $shift->id)
            ->groupBy('employee_id')
            ->selectRaw('employee_id, MAX(roster_date) as last_date')
            ->pluck('last_date', 'employee_id');

        $rotation = $pool->pluck('id')->mapWithKeys(fn ($id) => [$id => $lastAssigned->get($id)]);

        $preview = [];
        foreach (CarbonPeriod::create($start, $end) as $day) {
            $ordered = $rotation
                ->sortBy(fn ($date) => $date ? Carbon::parse($date)->timestamp : -1)
                ->keys();

            $picked = null;
            foreach ($ordered as $employeeId) {
                if (! $this->isUnavailable((int) $employeeId, $day)) {
                    $picked = (int) $employeeId;
                    break;
                }
            }

            $preview[] = [
                'date' => $day->toDateString(),
                'employee_id' => $picked,
                'employee_name' => $picked ? $pool->firstWhere('id', $picked)?->full_name : null,
                'reason' => $picked ? null : 'គ្មានបុគ្គលិកអាចប្រើបានសម្រាប់ថ្ងៃនេះ (ឈប់សម្រាក/មានវេនរួចហើយ)',
            ];

            if ($picked) {
                $rotation[$picked] = $day->toDateString();
            }
        }

        return $preview;
    }

    private function isUnavailable(int $employeeId, Carbon $day): bool
    {
        if (ShiftRoster::where('employee_id', $employeeId)->whereDate('roster_date', $day->toDateString())->exists()) {
            return true; // already has an explicit roster entry (any shift, day off, or holiday) for this day
        }

        return ApplyLeave::where('employee_id', $employeeId)
            ->where('is_approved', true)
            ->whereDate('leave_approved_start_date', '<=', $day->toDateString())
            ->whereDate('leave_approved_end_date', '>=', $day->toDateString())
            ->exists();
    }
}
