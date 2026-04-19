<?php

namespace Modules\HumanResource\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\HumanResource\Entities\Mission;
use Modules\HumanResource\Entities\MissionAssignment;

class MissionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'data' => Mission::query()->orderByDesc('id')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'destination' => ['required', 'string', 'max:255'],
            'purpose' => ['nullable', 'string'],
            'status' => ['nullable', 'in:draft,pending,approved,rejected,cancelled'],
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
        ]);

        $mission = DB::transaction(function () use ($validated) {
            $mission = Mission::query()->create([
                'uuid' => (string) Str::uuid(),
                'title' => $validated['title'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'destination' => $validated['destination'],
                'purpose' => $validated['purpose'] ?? null,
                'status' => $validated['status'] ?? 'pending',
                'requested_by' => auth()->id(),
            ]);

            $employeeIds = array_values(array_unique(array_map('intval', $validated['employee_ids'] ?? [])));
            foreach ($employeeIds as $employeeId) {
                MissionAssignment::query()->updateOrCreate(
                    ['mission_id' => (int) $mission->id, 'employee_id' => $employeeId],
                    ['status' => 'active']
                );
            }

            return $mission;
        });

        return response()->json([
            'status' => 'ok',
            'message' => 'Mission saved successfully.',
            'data' => $mission,
        ], 201);
    }
}
