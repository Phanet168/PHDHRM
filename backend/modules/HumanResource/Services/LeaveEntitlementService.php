<?php

namespace Modules\HumanResource\Services;

use Carbon\Carbon;
use Modules\HumanResource\Entities\ApplyLeave;
use Modules\HumanResource\Entities\LeaveEntitlement;
use Modules\HumanResource\Entities\LeaveType;

class LeaveEntitlementService
{
    public function recalculateForEmployee(int $employeeId): void
    {
        $academicYearId = (int) date('Y');

        $leaveTypes = LeaveType::query()->get([
            'id',
            'policy_key',
            'entitlement_scope',
            'entitlement_value',
            'max_per_request',
        ]);

        foreach ($leaveTypes as $leaveType) {
            $entitledDays = $this->resolveEntitledDays($leaveType);

            $usedDays = (float) ApplyLeave::query()
                ->where('employee_id', $employeeId)
                ->where('leave_type_id', $leaveType->id)
                ->where('is_approved', true)
                ->whereYear('leave_approved_start_date', Carbon::now()->year)
                ->sum('total_approved_day');

            $remainingDays = max(0, $entitledDays - $usedDays);

            LeaveEntitlement::query()->updateOrCreate(
                [
                    'employee_id' => $employeeId,
                    'leave_type_id' => $leaveType->id,
                    'academic_year_id' => $academicYearId,
                ],
                [
                    'entitled_days' => $entitledDays,
                    'used_days' => $usedDays,
                    'remaining_days' => $remainingDays,
                    'last_calculated_at' => now(),
                ]
            );
        }
    }

    private function resolveEntitledDays(LeaveType $leaveType): float
    {
        $scope = strtolower((string) ($leaveType->entitlement_scope ?? ''));
        $value = (float) ($leaveType->entitlement_value ?? 0);

        if ($scope === 'per_request') {
            return (float) ($leaveType->max_per_request ?? $value);
        }

        if ($scope === 'manual') {
            return $value;
        }

        if ($scope === 'per_service_lifetime') {
            return $value;
        }

        return $value;
    }
}
