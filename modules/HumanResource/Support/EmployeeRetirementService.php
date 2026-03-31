<?php

namespace Modules\HumanResource\Support;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\HumanResource\Entities\Employee;

class EmployeeRetirementService
{
    public const DEFAULT_RETIREMENT_AGE = 60;
    public const DEFAULT_UPCOMING_MONTHS = 12;

    public function notificationSummary(?User $user = null, int $limit = 5): array
    {
        $asOf = Carbon::today();

        return [
            'due_count' => $this->dueCount($user, $asOf),
            'upcoming_count' => $this->upcomingCount($user, $asOf, self::DEFAULT_UPCOMING_MONTHS),
            'due_employees' => $this->dueEmployees($user, $asOf, $limit),
        ];
    }

    public function dueCount(?User $user = null, ?Carbon $asOf = null): int
    {
        $asOf = $asOf ? $asOf->copy()->startOfDay() : Carbon::today();
        $dueDobThreshold = $asOf->copy()->subYears(self::DEFAULT_RETIREMENT_AGE)->toDateString();

        $query = Employee::query()
            ->where('is_active', true)
            ->whereNotNull('date_of_birth')
            ->whereDate('date_of_birth', '<=', $dueDobThreshold);

        $this->applyManagedBranchScope($query, $this->managedBranchIds($user));

        return (int) $query->count();
    }

    public function upcomingCount(?User $user = null, ?Carbon $asOf = null, int $months = self::DEFAULT_UPCOMING_MONTHS): int
    {
        $asOf = $asOf ? $asOf->copy()->startOfDay() : Carbon::today();
        $months = max(1, $months);
        $dueDobThreshold = $asOf->copy()->subYears(self::DEFAULT_RETIREMENT_AGE)->toDateString();
        $upcomingDobThreshold = $asOf->copy()->addMonths($months)->subYears(self::DEFAULT_RETIREMENT_AGE)->toDateString();

        $query = Employee::query()
            ->where('is_active', true)
            ->whereNotNull('date_of_birth')
            ->whereDate('date_of_birth', '>', $dueDobThreshold)
            ->whereDate('date_of_birth', '<=', $upcomingDobThreshold);

        $this->applyManagedBranchScope($query, $this->managedBranchIds($user));

        return (int) $query->count();
    }

    public function dueEmployees(?User $user = null, ?Carbon $asOf = null, int $limit = 10): Collection
    {
        $asOf = $asOf ? $asOf->copy()->startOfDay() : Carbon::today();
        $dueDobThreshold = $asOf->copy()->subYears(self::DEFAULT_RETIREMENT_AGE)->toDateString();

        $query = Employee::query()
            ->with(['department', 'sub_department', 'position'])
            ->where('is_active', true)
            ->whereNotNull('date_of_birth')
            ->whereDate('date_of_birth', '<=', $dueDobThreshold)
            ->orderBy('date_of_birth')
            ->orderBy('last_name')
            ->orderBy('first_name');

        $this->applyManagedBranchScope($query, $this->managedBranchIds($user));

        return $query
            ->limit(max(1, $limit))
            ->get()
            ->map(function (Employee $employee) {
                $employee->setAttribute('retirement_age', self::DEFAULT_RETIREMENT_AGE);
                $employee->setAttribute(
                    'retirement_date',
                    Carbon::parse($employee->date_of_birth)->addYears(self::DEFAULT_RETIREMENT_AGE)->toDateString()
                );
                $employee->setAttribute(
                    'display_unit_name',
                    $employee->sub_department?->department_name ?: ($employee->department?->department_name ?: '-')
                );

                return $employee;
            });
    }

    protected function managedBranchIds(?User $user = null): ?array
    {
        $user = $user ?: auth()->user();
        if (!$user) {
            return [];
        }

        if ((int) $user->user_type_id === 1) {
            return null;
        }

        $employee = $user->employee()->with('primaryUnitPosting')->first();
        if (!$employee) {
            return [];
        }

        $rootUnitId = (int) ($employee->sub_department_id ?: $employee->department_id);
        if ($rootUnitId <= 0) {
            $rootUnitId = (int) optional($employee->primaryUnitPosting)->department_id;
        }

        if ($rootUnitId <= 0) {
            return [];
        }

        return app(OrgUnitRuleService::class)->branchIdsIncludingSelf($rootUnitId);
    }

    protected function applyManagedBranchScope(Builder $query, ?array $managedBranchIds): void
    {
        if (!is_array($managedBranchIds)) {
            return;
        }

        if (empty($managedBranchIds)) {
            $query->whereRaw('1=0');
            return;
        }

        $query->where(function ($subQuery) use ($managedBranchIds) {
            $subQuery
                ->whereIn('department_id', $managedBranchIds)
                ->orWhereIn('sub_department_id', $managedBranchIds);
        });
    }
}

