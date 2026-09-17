<?php

namespace Modules\HumanResource\Http\Controllers;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\HumanResource\Entities\AttendanceDailySnapshot;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Services\AttendanceStatusService;

class AttendanceSnapshotController extends Controller
{
    public function __construct(private readonly AttendanceStatusService $statusService, private readonly \Modules\HumanResource\Support\AttendanceUnitScope $scope)
    {
    }

    public function daily(Request $request): mixed
    {
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'employee_id' => ['nullable', 'integer']]);
        $selectedDepartmentId = $this->scope->selected($request);
        $departments = $this->scope->departments()->get();
        $this->scope->authorize('read_attendance_snapshot');
        if ($request->filled('employee_id')) {
            $this->scope->employees($selectedDepartmentId)->findOrFail($request->integer('employee_id'));
        }
        // Web view: no required employee_id, loads grid
        if (! $request->expectsJson()) {
            $employees = $this->scope->employees($selectedDepartmentId)
                ->where('is_active', 1)
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'middle_name', 'last_name', 'employee_id']);
            $selectedDate = $request->input('date', now()->toDateString());
            $selectedEmployeeId = $request->input('employee_id');

            $snapshots = collect();
            if ($selectedDate) {
                $query = AttendanceDailySnapshot::query()
                    ->with('employee')->whereIn('employee_id', $this->scope->employees($selectedDepartmentId)->select('employees.id'))
                    ->whereDate('snapshot_date', $selectedDate);

                if ($selectedEmployeeId) {
                    $query->where('employee_id', (int) $selectedEmployeeId);
                }
                foreach ($employees as $employee) {
                    if ($selectedEmployeeId && (int) $selectedEmployeeId !== (int) $employee->id) {
                        continue;
                    }
                    $payload = $this->statusService->determineDailyStatus((int) $employee->id, Carbon::parse($selectedDate));
                    AttendanceDailySnapshot::updateOrCreate(['employee_id' => $employee->id, 'snapshot_date' => $selectedDate], $payload);
                }
                $snapshots = $query->orderBy('employee_id')->get();
            }

            return view('humanresource::attendance.daily-snapshot', compact(
                'employees', 'selectedDate', 'selectedEmployeeId', 'snapshots', 'departments', 'selectedDepartmentId'
            ));
        }

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'date' => ['nullable', 'date'],
            'recompute' => ['nullable', 'boolean'],
        ]);

        $date = Carbon::parse($validated['date'] ?? now())->startOfDay();
        $employeeId = (int) $validated['employee_id'];

        if ((bool) ($validated['recompute'] ?? false)) {
            $payload = $this->statusService->determineDailyStatus($employeeId, $date);
            $snapshot = AttendanceDailySnapshot::query()->updateOrCreate(
                [
                    'employee_id' => $employeeId,
                    'snapshot_date' => $date->toDateString(),
                ],
                $payload
            );
        } else {
            $snapshot = AttendanceDailySnapshot::query()
                ->where('employee_id', $employeeId)
                ->whereDate('snapshot_date', $date->toDateString())
                ->first();

            if (! $snapshot) {
                $payload = $this->statusService->determineDailyStatus($employeeId, $date);
                $snapshot = AttendanceDailySnapshot::query()->create($payload);
            }
        }

        return response()->json([
            'status' => 'ok',
            'data' => $snapshot,
        ]);
    }

    public function regenerate(Request $request): mixed
    {
        $this->scope->authorize('create_attendance_snapshot');
        $selectedDepartmentId = $this->scope->selected($request);
        $validated = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
        ]);

        if (Carbon::parse($validated['start_date'])->diffInDays(Carbon::parse($validated['end_date'])) > 30) {
            throw \Illuminate\Validation\ValidationException::withMessages(['end_date' => 'Select no more than 31 days.']);
        }
        $employeeIds = $validated['employee_ids'] ?? [];
        if (empty($employeeIds)) {
            $employeeIds = $this->scope->employees($selectedDepartmentId)->where('is_active', 1)->pluck('id')->all();
        }

        $employeeIds = array_values(array_unique(array_map('intval', $employeeIds)));
        abort_unless($this->scope->employees($selectedDepartmentId)->whereIn('id', $employeeIds)->count() === count($employeeIds), 403);

        $period = CarbonPeriod::create(
            Carbon::parse($validated['start_date'])->startOfDay(),
            Carbon::parse($validated['end_date'])->startOfDay(),
        );

        $processed = 0;
        foreach ($employeeIds as $employeeId) {
            foreach ($period as $day) {
                $payload = $this->statusService->determineDailyStatus((int) $employeeId, $day);
                AttendanceDailySnapshot::query()->updateOrCreate(
                    [
                        'employee_id' => (int) $employeeId,
                        'snapshot_date' => $day->toDateString(),
                    ],
                    $payload
                );
                $processed++;
            }
            $period = CarbonPeriod::create(
                Carbon::parse($validated['start_date'])->startOfDay(),
                Carbon::parse($validated['end_date'])->startOfDay(),
            );
        }

        if (! $request->expectsJson()) {
            return redirect()->route('attendance-snapshots.daily', ['date' => $validated['start_date'], 'department_id' => $selectedDepartmentId])
                ->with('success', localize('snapshots_regenerated', 'Snapshots បានបង្កើតឡើងវិញចំនួន '.$processed.' records'));
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'Snapshots regenerated successfully.',
            'processed_records' => $processed,
            'employees' => count($employeeIds),
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
        ]);
    }
}
