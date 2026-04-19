<?php

namespace Modules\HumanResource\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\HumanResource\Entities\ShiftRoster;

class ShiftRosterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ShiftRoster::query();

        if ($request->filled('employee_id')) {
            $query->where('employee_id', (int) $request->input('employee_id'));
        }
        if ($request->filled('date')) {
            $query->whereDate('roster_date', $request->input('date'));
        }

        return response()->json([
            'status' => 'ok',
            'data' => $query->orderBy('roster_date')->orderBy('employee_id')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'roster_date' => ['required', 'date'],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
            'is_day_off' => ['nullable', 'boolean'],
            'is_holiday' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $roster = ShiftRoster::query()->updateOrCreate(
            [
                'employee_id' => (int) $validated['employee_id'],
                'roster_date' => $validated['roster_date'],
            ],
            [
                'uuid' => (string) Str::uuid(),
                'shift_id' => $validated['shift_id'] ?? null,
                'is_day_off' => (bool) ($validated['is_day_off'] ?? false),
                'is_holiday' => (bool) ($validated['is_holiday'] ?? false),
                'created_by' => auth()->id(),
                'note' => $validated['note'] ?? null,
            ]
        );

        return response()->json([
            'status' => 'ok',
            'message' => 'Shift roster saved successfully.',
            'data' => $roster,
        ], 201);
    }
}
