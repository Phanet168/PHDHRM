<?php

namespace Modules\HumanResource\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\Shift;
use Modules\HumanResource\Entities\ShiftAssignment;
use Modules\HumanResource\Entities\ShiftRoster;

class ShiftResolverService
{
    public function resolveForDate(int $employeeId, CarbonInterface $date): ?array
    {
        if (!Schema::hasTable('shifts')) {
            return null;
        }

        $day = $date->toDateString();

        if (Schema::hasTable('shift_rosters')) {
            $roster = ShiftRoster::query()
                ->where('employee_id', $employeeId)
                ->whereDate('roster_date', $day)
                ->first();

            if ($roster) {
                if ($roster->is_day_off || $roster->is_holiday || !$roster->shift_id) {
                    return [
                        'source' => 'roster',
                        'shift' => null,
                        'is_day_off' => (bool) $roster->is_day_off,
                        'is_holiday' => (bool) $roster->is_holiday,
                    ];
                }

                $shift = Shift::query()->find($roster->shift_id);
                if ($shift) {
                    return [
                        'source' => 'roster',
                        'shift' => $shift,
                        'is_day_off' => false,
                        'is_holiday' => false,
                    ];
                }
            }
        }

        if (Schema::hasTable('shift_assignments')) {
            $assignment = ShiftAssignment::query()
                ->where('employee_id', $employeeId)
                ->whereDate('effective_date', '<=', $day)
                ->where(function ($q) use ($day) {
                    $q->whereNull('end_date')->orWhereDate('end_date', '>=', $day);
                })
                ->orderByDesc('effective_date')
                ->orderByDesc('id')
                ->first();

            if ($assignment) {
                $shift = Shift::query()->find($assignment->shift_id);
                if ($shift) {
                    return [
                        'source' => 'assignment',
                        'shift' => $shift,
                        'is_day_off' => false,
                        'is_holiday' => false,
                    ];
                }
            }
        }

        if (Schema::hasColumn('employees', 'default_shift_id')) {
            $employee = Employee::query()->select('id', 'default_shift_id')->find($employeeId);
            if ($employee && $employee->default_shift_id) {
                $shift = Shift::query()->find($employee->default_shift_id);
                if ($shift) {
                    return [
                        'source' => 'default',
                        'shift' => $shift,
                        'is_day_off' => false,
                        'is_holiday' => false,
                    ];
                }
            }
        }

        return null;
    }
}
