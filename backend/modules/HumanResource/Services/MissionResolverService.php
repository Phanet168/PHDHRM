<?php

namespace Modules\HumanResource\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;
use Modules\HumanResource\Entities\Mission;
use Modules\HumanResource\Entities\MissionAssignment;

class MissionResolverService
{
    public function resolveForDate(int $employeeId, CarbonInterface $date): ?array
    {
        if (!Schema::hasTable('missions') || !Schema::hasTable('mission_assignments')) {
            return null;
        }

        $day = $date->toDateString();

        $assignment = MissionAssignment::query()
            ->where('employee_id', $employeeId)
            ->where('status', 'active')
            ->whereHas('mission', function ($q) use ($day) {
                $q->where('status', 'approved')
                    ->whereDate('start_date', '<=', $day)
                    ->whereDate('end_date', '>=', $day);
            })
            ->with('mission:id,title,start_date,end_date,status')
            ->orderByDesc('id')
            ->first();

        if (!$assignment || !$assignment->mission) {
            return null;
        }

        return [
            'assignment_id' => (int) $assignment->id,
            'mission_id' => (int) $assignment->mission_id,
            'title' => (string) $assignment->mission->title,
            'start_date' => (string) $assignment->mission->start_date,
            'end_date' => (string) $assignment->mission->end_date,
        ];
    }
}
