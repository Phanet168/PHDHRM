<?php

namespace Modules\HumanResource\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\HumanResource\Entities\AttendanceAdjustment;
use Modules\HumanResource\Services\AttendanceAdjustmentService;

class AttendanceAdjustmentController extends Controller
{
    public function __construct(private readonly AttendanceAdjustmentService $adjustmentService)
    {
    }

    public function index(): JsonResponse
    {
        $items = AttendanceAdjustment::query()->orderByDesc('id')->paginate(20);

        return response()->json([
            'status' => 'ok',
            'data' => $items,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'attendance_id' => ['nullable', 'integer', 'exists:attendances,id'],
            'new_time' => ['nullable', 'date'],
            'new_machine_state' => ['nullable', 'integer', 'in:1,2'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $result = $this->adjustmentService->request($validated);

        return response()->json([
            'result' => $result,
        ], $result['status'] === 'ok' ? 201 : 422);
    }
}
