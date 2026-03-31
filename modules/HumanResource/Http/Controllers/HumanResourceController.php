<?php

namespace Modules\HumanResource\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\EmployeePayGradeHistory;
use Modules\HumanResource\Entities\EmployeeProfileExtra;
use Modules\HumanResource\Entities\EmployeeUnitPosting;
use Modules\HumanResource\Entities\ManualAttendance;
use Modules\HumanResource\Support\OrgUnitRuleService;

class HumanResourceController extends Controller
{
    public function index(Request $request, OrgUnitRuleService $orgUnitRuleService)
    {
        $selectedYear = (int) $request->integer('year', now()->year);
        if ($selectedYear < 1950 || $selectedYear > 2100) {
            $selectedYear = (int) now()->year;
        }

        $cutoffDate = Carbon::create($selectedYear, 4, 1)->endOfDay();
        $today = Carbon::today();
        $notifyUntil = $today->copy()->addMonths(3)->endOfDay();
        $managedBranchIds = $this->managedBranchIds($orgUnitRuleService);
        $selectedDepartmentId = $request->filled('department_id') ? (int) $request->department_id : null;

        $departments = Department::query()
            ->where('is_active', true)
            ->orderBy('department_name');
        $this->applyManagedBranchScope($departments, $managedBranchIds);
        $departments = $departments->get();

        $employeesQuery = Employee::query()
            ->where('is_active', true)
            ->with(['employee_type', 'profileExtra', 'department', 'sub_department', 'position']);

        $this->applyManagedBranchScope($employeesQuery, $managedBranchIds);
        if ($selectedDepartmentId) {
            $branchIds = $orgUnitRuleService->branchIdsIncludingSelf($selectedDepartmentId);
            if (empty($branchIds)) {
                $employeesQuery->whereRaw('1=0');
            } else {
                $employeesQuery->where(function ($q) use ($branchIds) {
                    $q->whereIn('department_id', $branchIds)
                        ->orWhereIn('sub_department_id', $branchIds);
                });
            }
        }

        if ($request->filled('branch_id')) {
            $employeesQuery->where('branch_id', (int) $request->branch_id);
        }

        $employees = $employeesQuery->get();
        $totalEmployee = $employees->count();

        $next60days = $today->copy()->addDays(60);
        $contractRenewEmployees = $employees->filter(function (Employee $employee) use ($today, $next60days) {
            if (empty($employee->contract_end_date)) {
                return false;
            }

            try {
                $contractEndDate = Carbon::parse($employee->contract_end_date);
            } catch (\Throwable $th) {
                return false;
            }

            return $contractEndDate->between($today, $next60days);
        })->count();

        $todayAttendanceQuery = ManualAttendance::query()
            ->whereDate('time', $today->toDateString());

        if (is_array($managedBranchIds)) {
            if (empty($managedBranchIds)) {
                $todayAttendanceQuery->whereRaw('1=0');
            } else {
                $todayAttendanceQuery->whereHas('employee', function ($q) use ($managedBranchIds) {
                    $q->whereIn('department_id', $managedBranchIds)
                        ->orWhereIn('sub_department_id', $managedBranchIds);
                });
            }
        }

        if ($selectedDepartmentId) {
            $branchIds = $orgUnitRuleService->branchIdsIncludingSelf($selectedDepartmentId);
            if (empty($branchIds)) {
                $todayAttendanceQuery->whereRaw('1=0');
            } else {
                $todayAttendanceQuery->whereHas('employee', function ($q) use ($branchIds) {
                    $q->whereIn('department_id', $branchIds)
                        ->orWhereIn('sub_department_id', $branchIds);
                });
            }
        }

        if ($request->filled('branch_id')) {
            $todayAttendanceQuery->whereHas('employee', function ($q) use ($request) {
                $q->where('branch_id', (int) $request->branch_id);
            });
        }

        $todayAttendance = $todayAttendanceQuery->distinct('employee_id')->count('employee_id');
        $todayAbsence = max(0, $totalEmployee - $todayAttendance);

        $employeeIds = $employees->pluck('id')->map(function ($id) {
            return (int) $id;
        })->filter(function ($id) {
            return $id > 0;
        })->values()->all();

        $payHistoryByEmployee = collect();
        $profileExtraByEmployee = collect();
        $positionPostingByEmployee = collect();

        if (!empty($employeeIds)) {
            $payHistoryByEmployee = EmployeePayGradeHistory::query()
                ->whereIn('employee_id', $employeeIds)
                ->whereNotIn('status', ['proposed', 'rejected', 'cancelled'])
                ->whereDate('start_date', '<=', $cutoffDate->toDateString())
                ->orderByDesc('start_date')
                ->orderByDesc('id')
                ->get(['id', 'employee_id', 'start_date', 'status'])
                ->groupBy('employee_id');

            $profileExtraByEmployee = EmployeeProfileExtra::query()
                ->whereIn('employee_id', $employeeIds)
                ->get(['employee_id', 'current_position_start_date'])
                ->keyBy('employee_id');

            $positionPostingByEmployee = EmployeeUnitPosting::query()
                ->whereIn('employee_id', $employeeIds)
                ->whereNotNull('position_id')
                ->whereDate('start_date', '<=', $cutoffDate->toDateString())
                ->orderByDesc('start_date')
                ->orderByDesc('id')
                ->get(['id', 'employee_id', 'start_date'])
                ->groupBy('employee_id');
        }

        $duePayPromotionCount = 0;
        $duePositionPromotionCount = 0;
        $dueNotifications = collect();

        foreach ($employees as $employee) {
            if (!$this->isStateCadreEmployee($employee)) {
                continue;
            }

            if ($this->normalizeServiceState((string) ($employee->service_state ?? 'active')) !== 'active') {
                continue;
            }

            $employeeId = (int) $employee->id;
            $unitLabel = $employee->sub_department?->department_name
                ?: ($employee->department?->department_name ?: '-');

            $payAnchor = $this->resolvePayAnchorDate(
                $employee,
                collect($payHistoryByEmployee->get($employeeId, [])),
                $cutoffDate
            );
            if ($payAnchor) {
                $payDueDate = $payAnchor->copy()->addYears(2)->endOfDay();
                if ($payDueDate->lte($cutoffDate)) {
                    $duePayPromotionCount++;
                }
                if ($payDueDate->between($today, $notifyUntil)) {
                    $dueNotifications->push([
                        'employee_id' => $employeeId,
                        'employee_code' => (string) ($employee->employee_id ?? ''),
                        'full_name' => (string) ($employee->full_name ?? ''),
                        'unit_name' => (string) $unitLabel,
                        'promotion_type' => 'pay',
                        'due_date' => $payDueDate->toDateString(),
                        'days_left' => $today->diffInDays($payDueDate, false),
                    ]);
                }
            }

            $positionAnchor = $this->resolvePositionAnchorDate(
                $employee,
                $profileExtraByEmployee,
                collect($positionPostingByEmployee->get($employeeId, [])),
                $cutoffDate
            );
            if ($positionAnchor) {
                $positionDueDate = $positionAnchor->copy()->addYears(2)->endOfDay();
                if ($positionDueDate->lte($cutoffDate)) {
                    $duePositionPromotionCount++;
                }
                if ($positionDueDate->between($today, $notifyUntil)) {
                    $dueNotifications->push([
                        'employee_id' => $employeeId,
                        'employee_code' => (string) ($employee->employee_id ?? ''),
                        'full_name' => (string) ($employee->full_name ?? ''),
                        'unit_name' => (string) $unitLabel,
                        'promotion_type' => 'position',
                        'due_date' => $positionDueDate->toDateString(),
                        'days_left' => $today->diffInDays($positionDueDate, false),
                    ]);
                }
            }
        }

        $promotionHistoryScope = function (Builder $query) use ($managedBranchIds, $selectedDepartmentId, $orgUnitRuleService, $request): void {
            if (is_array($managedBranchIds)) {
                if (empty($managedBranchIds)) {
                    $query->whereRaw('1=0');
                    return;
                }

                $query->whereHas('employee', function ($q) use ($managedBranchIds) {
                    $q->whereIn('department_id', $managedBranchIds)
                        ->orWhereIn('sub_department_id', $managedBranchIds);
                });
            }

            if ($selectedDepartmentId) {
                $branchIds = $orgUnitRuleService->branchIdsIncludingSelf($selectedDepartmentId);
                if (empty($branchIds)) {
                    $query->whereRaw('1=0');
                    return;
                }

                $query->whereHas('employee', function ($q) use ($branchIds) {
                    $q->whereIn('department_id', $branchIds)
                        ->orWhereIn('sub_department_id', $branchIds);
                });
            }

            if ($request->filled('branch_id')) {
                $query->whereHas('employee', function ($q) use ($request) {
                    $q->where('branch_id', (int) $request->branch_id);
                });
            }
        };

        $pendingRequestQuery = EmployeePayGradeHistory::query()->where('status', 'proposed');
        $promotionHistoryScope($pendingRequestQuery);

        $approvedRequestQuery = EmployeePayGradeHistory::query()
            ->where('status', 'approved')
            ->whereYear('start_date', $selectedYear);
        $promotionHistoryScope($approvedRequestQuery);

        $rejectedRequestQuery = EmployeePayGradeHistory::query()
            ->whereIn('status', ['rejected', 'cancelled'])
            ->whereYear('start_date', $selectedYear);
        $promotionHistoryScope($rejectedRequestQuery);

        $pendingRequestsPreview = (clone $pendingRequestQuery)
            ->with(['employee.position', 'employee.department', 'employee.sub_department', 'payLevel'])
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        $pendingRequestCount = (clone $pendingRequestQuery)->count();
        $approvedRequestCount = (clone $approvedRequestQuery)->count();
        $rejectedRequestCount = (clone $rejectedRequestQuery)->count();

        $dueNotifications = $dueNotifications
            ->sortBy(function (array $row) {
                return [$row['due_date'] ?? '9999-12-31', $row['employee_code'] ?? ''];
            })
            ->values();

        return view('humanresource::index', [
            'selected_year' => $selectedYear,
            'cutoff_date' => $cutoffDate->toDateString(),
            'total_employee' => $totalEmployee,
            'today_attenedence' => $todayAttendance,
            'today_absense' => $todayAbsence,
            'contract_renew_employees' => $contractRenewEmployees,
            'due_pay_promotion_count' => $duePayPromotionCount,
            'due_position_promotion_count' => $duePositionPromotionCount,
            'pending_request_count' => $pendingRequestCount,
            'approved_request_count' => $approvedRequestCount,
            'rejected_request_count' => $rejectedRequestCount,
            'notification_count' => $dueNotifications->count(),
            'due_notifications' => $dueNotifications->take(20),
            'pending_requests_preview' => $pendingRequestsPreview,
            'departments' => $departments,
            'request' => $request,
        ]);
    }

    protected function isStateCadreEmployee(Employee $employee): bool
    {
        if ((int) ($employee->is_full_right_officer ?? 0) === 1) {
            return true;
        }

        $employeeTypeText = trim(strtolower(implode(' ', array_filter([
            (string) data_get($employee, 'employee_type.name', ''),
            (string) data_get($employee, 'employee_type.name_km', ''),
            (string) data_get($employee, 'employee_type.employee_type_name', ''),
        ]))));

        if ($employeeTypeText === '') {
            return false;
        }

        foreach (['civil', 'state cadre', 'cadre', 'government'] as $keyword) {
            if (str_contains($employeeTypeText, $keyword)) {
                return true;
            }
        }

        return false;
    }

    protected function normalizeServiceState(?string $state): string
    {
        $value = strtolower(trim((string) $state));
        if (in_array($value, ['active', 'inactive', 'suspended'], true)) {
            return $value;
        }

        if (str_contains($value, 'suspend') || str_contains($value, 'without pay')) {
            return 'suspended';
        }

        if (str_contains($value, 'inactive') || str_contains($value, 'retire') || str_contains($value, 'death')) {
            return 'inactive';
        }

        return 'active';
    }

    protected function resolvePayAnchorDate(Employee $employee, $payHistoryRows, Carbon $cutoffDate): ?Carbon
    {
        $latestRow = $payHistoryRows->first();
        if ($latestRow && !empty($latestRow->start_date)) {
            try {
                return Carbon::parse($latestRow->start_date)->startOfDay();
            } catch (\Throwable $th) {
                // Continue with fallback dates.
            }
        }

        return $this->resolveDateFromCandidates([
            $employee->full_right_date ?? null,
            $employee->service_start_date ?? null,
            $employee->joining_date ?? null,
            $employee->hire_date ?? null,
            $employee->promotion_date ?? null,
        ], $cutoffDate);
    }

    protected function resolvePositionAnchorDate(Employee $employee, $profileExtraByEmployee, $positionPostingRows, Carbon $cutoffDate): ?Carbon
    {
        $profileExtra = $profileExtraByEmployee->get((int) $employee->id);
        if ($profileExtra && !empty($profileExtra->current_position_start_date)) {
            try {
                $parsed = Carbon::parse($profileExtra->current_position_start_date)->startOfDay();
                if ($parsed->lte($cutoffDate)) {
                    return $parsed;
                }
            } catch (\Throwable $th) {
                // Continue with fallback dates.
            }
        }

        $latestPosting = $positionPostingRows->first();
        if ($latestPosting && !empty($latestPosting->start_date)) {
            try {
                $parsed = Carbon::parse($latestPosting->start_date)->startOfDay();
                if ($parsed->lte($cutoffDate)) {
                    return $parsed;
                }
            } catch (\Throwable $th) {
                // Continue with fallback dates.
            }
        }

        return $this->resolveDateFromCandidates([
            $employee->service_start_date ?? null,
            $employee->joining_date ?? null,
            $employee->hire_date ?? null,
        ], $cutoffDate);
    }

    protected function resolveDateFromCandidates(array $candidates, Carbon $cutoffDate): ?Carbon
    {
        foreach ($candidates as $rawDate) {
            if (empty($rawDate)) {
                continue;
            }

            try {
                $parsed = Carbon::parse($rawDate)->startOfDay();
                if ($parsed->lte($cutoffDate)) {
                    return $parsed;
                }
            } catch (\Throwable $th) {
                // Skip invalid values and continue with next candidate.
            }
        }

        return null;
    }

    protected function applyManagedBranchScope($query, ?array $managedBranchIds): void
    {
        if (!is_array($managedBranchIds)) {
            return;
        }

        if (empty($managedBranchIds)) {
            $query->whereRaw('1=0');
            return;
        }

        $query->where(function ($q) use ($managedBranchIds) {
            $q->whereIn('department_id', $managedBranchIds)
                ->orWhereIn('sub_department_id', $managedBranchIds);
        });
    }

    protected function managedBranchIds(OrgUnitRuleService $orgUnitRuleService): ?array
    {
        if ($this->isSystemAdmin()) {
            return null;
        }

        $rootUnitId = $this->currentUserRootUnitId();
        if (!$rootUnitId) {
            return [];
        }

        return $orgUnitRuleService->branchIdsIncludingSelf($rootUnitId);
    }

    protected function isSystemAdmin(): bool
    {
        $user = auth()->user();
        return $user && (int) $user->user_type_id === 1;
    }

    protected function currentUserRootUnitId(): ?int
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }

        $employee = $user->employee()->with('primaryUnitPosting')->first();
        if (!$employee) {
            return null;
        }

        $rootUnitId = $this->employeeAssignedUnitId($employee);
        if ($rootUnitId) {
            return $rootUnitId;
        }

        $postedUnitId = (int) optional($employee->primaryUnitPosting)->department_id;
        return $postedUnitId > 0 ? $postedUnitId : null;
    }

    protected function employeeAssignedUnitId(Employee $employee): ?int
    {
        $unitId = (int) ($employee->sub_department_id ?: $employee->department_id);
        if ($unitId > 0) {
            return $unitId;
        }

        $postedUnitId = (int) optional($employee->primaryUnitPosting)->department_id;
        return $postedUnitId > 0 ? $postedUnitId : null;
    }
}
