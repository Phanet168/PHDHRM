<?php

namespace Modules\HumanResource\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Modules\HumanResource\Entities\AttendanceDailySnapshot;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Services\AttendanceStatusService;

class AttendanceSnapshotController extends Controller
{
    public function __construct(private readonly AttendanceStatusService $statusService)
    {
    }

    public function daily(Request $request): JsonResponse
    {
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

            if (!$snapshot) {
                $payload = $this->statusService->determineDailyStatus($employeeId, $date);
                $snapshot = AttendanceDailySnapshot::query()->create($payload);
            }
        }

        return response()->json([
            'status' => 'ok',
            'data' => $snapshot,
        ]);
    }

    public function regenerate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
        ]);

        $employeeIds = $validated['employee_ids'] ?? [];
        if (empty($employeeIds)) {
            $employeeIds = Employee::query()->pluck('id')->all();
        }

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
