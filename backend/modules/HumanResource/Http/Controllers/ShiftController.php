<?php

namespace Modules\HumanResource\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\HumanResource\Entities\Shift;

class ShiftController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'data' => Shift::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:255'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'is_cross_day' => ['nullable', 'boolean'],
            'grace_late_minutes' => ['nullable', 'integer', 'min:0', 'max:720'],
            'grace_early_leave_minutes' => ['nullable', 'integer', 'min:0', 'max:720'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $shift = Shift::query()->create([
            'uuid' => (string) Str::uuid(),
            'code' => $validated['code'] ?? null,
            'name' => $validated['name'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'is_cross_day' => (bool) ($validated['is_cross_day'] ?? false),
            'grace_late_minutes' => (int) ($validated['grace_late_minutes'] ?? 0),
            'grace_early_leave_minutes' => (int) ($validated['grace_early_leave_minutes'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'status' => 'ok',
            'message' => 'Shift created successfully.',
            'data' => $shift,
        ], 201);
    }
}
