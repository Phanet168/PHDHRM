<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\Employee;

class ExternalSyncController extends Controller
{
    public function health()
    {
        return response()->json([
            'ok' => true,
            'message' => 'External sync API is running.',
            'server_time' => now()->toIso8601String(),
            'timezone' => config('app.timezone'),
        ]);
    }

    public function employees(Request $request)
    {
        $query = Employee::query()
            ->with([
                'gender:id,gender_name',
                'department:id,department_name,parent_id,unit_type_id,is_active',
                'position:id,position_name,position_name_km,position_rank,is_active',
                'employee_type:id,name,name_km,is_active',
            ]);

        if ($request->filled('updated_since')) {
            try {
                $updatedSince = Carbon::parse((string) $request->input('updated_since'));
                $query->where('updated_at', '>=', $updatedSince);
            } catch (\Throwable $e) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Invalid updated_since format. Use ISO datetime or Y-m-d H:i:s.',
                ], 422);
            }
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', (int) $request->input('department_id'));
        }

        if ($request->has('is_active') && $request->input('is_active') !== '') {
            $query->where('is_active', (int) $request->input('is_active') ? 1 : 0);
        }

        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $query->where(function ($builder) use ($q) {
                $builder->where('employee_id', 'like', '%' . $q . '%')
                    ->orWhere('official_id_10', 'like', '%' . $q . '%')
                    ->orWhere('first_name', 'like', '%' . $q . '%')
                    ->orWhere('last_name', 'like', '%' . $q . '%')
                    ->orWhere('first_name_latin', 'like', '%' . $q . '%')
                    ->orWhere('last_name_latin', 'like', '%' . $q . '%')
                    ->orWhere('phone', 'like', '%' . $q . '%');
            });
        }

        $perPage = (int) $request->input('per_page', 50);
        $perPage = max(1, min($perPage, 200));

        $paginator = $query
            ->orderBy('id')
            ->paginate($perPage)
            ->appends($request->query());

        $items = collect($paginator->items())
            ->map(fn (Employee $employee) => $this->employeePayload($employee))
            ->values();

        return response()->json([
            'ok' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function employee(Request $request, int $id)
    {
        $employee = Employee::query()
            ->with([
                'gender:id,gender_name',
                'department:id,department_name,parent_id,unit_type_id,is_active',
                'position:id,position_name,position_name_km,position_rank,is_active',
                'employee_type:id,name,name_km,is_active',
            ])
            ->find($id);

        if (!$employee) {
            return response()->json([
                'ok' => false,
                'message' => 'Employee not found.',
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'data' => $this->employeePayload($employee),
        ]);
    }

    public function departments(Request $request)
    {
        $query = Department::query()
            ->withoutGlobalScopes()
            ->with(['unitType:id,code,name,name_km,is_active'])
            ->select([
                'id',
                'department_name',
                'parent_id',
                'unit_type_id',
                'sort_order',
                'location_code',
                'is_active',
                'updated_at',
            ]);

        if ($request->has('is_active') && $request->input('is_active') !== '') {
            $query->where('is_active', (int) $request->input('is_active') ? 1 : 0);
        }

        if ($request->filled('updated_since')) {
            try {
                $updatedSince = Carbon::parse((string) $request->input('updated_since'));
                $query->where('updated_at', '>=', $updatedSince);
            } catch (\Throwable $e) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Invalid updated_since format. Use ISO datetime or Y-m-d H:i:s.',
                ], 422);
            }
        }

        $items = $query
            ->orderByRaw('COALESCE(sort_order, 999999)')
            ->orderBy('id')
            ->get()
            ->map(function (Department $department) {
                return [
                    'id' => (int) $department->id,
                    'name' => (string) $department->department_name,
                    'parent_id' => $department->parent_id ? (int) $department->parent_id : null,
                    'unit_type_id' => $department->unit_type_id ? (int) $department->unit_type_id : null,
                    'unit_type' => $department->unitType ? [
                        'id' => (int) $department->unitType->id,
                        'code' => (string) ($department->unitType->code ?? ''),
                        'name' => (string) ($department->unitType->name ?? ''),
                        'name_km' => (string) ($department->unitType->name_km ?? ''),
                        'is_active' => (bool) ($department->unitType->is_active ?? false),
                    ] : null,
                    'sort_order' => $department->sort_order !== null ? (int) $department->sort_order : null,
                    'location_code' => (string) ($department->location_code ?? ''),
                    'is_active' => (bool) $department->is_active,
                    'updated_at' => optional($department->updated_at)->toIso8601String(),
                ];
            })
            ->values();

        return response()->json([
            'ok' => true,
            'data' => $items,
        ]);
    }

    private function employeePayload(Employee $employee): array
    {
        $fullNameKm = trim(implode(' ', array_filter([
            (string) ($employee->last_name ?? ''),
            (string) ($employee->first_name ?? ''),
        ])));

        $fullNameLatin = trim(implode(' ', array_filter([
            (string) ($employee->last_name_latin ?? ''),
            (string) ($employee->first_name_latin ?? ''),
        ])));

        return [
            'id' => (int) $employee->id,
            'employee_id' => (string) ($employee->employee_id ?? ''),
            'official_id_10' => (string) ($employee->official_id_10 ?? ''),
            'card_no' => (string) ($employee->card_no ?? ''),
            'name_km' => $fullNameKm,
            'name_latin' => $fullNameLatin,
            'first_name' => (string) ($employee->first_name ?? ''),
            'last_name' => (string) ($employee->last_name ?? ''),
            'first_name_latin' => (string) ($employee->first_name_latin ?? ''),
            'last_name_latin' => (string) ($employee->last_name_latin ?? ''),
            'gender' => $employee->gender ? [
                'id' => (int) $employee->gender->id,
                'name' => (string) ($employee->gender->gender_name ?? ''),
            ] : null,
            'date_of_birth' => optional($employee->date_of_birth)->format('Y-m-d'),
            'phone' => (string) ($employee->phone ?? ''),
            'email' => (string) ($employee->email ?? ''),
            'is_active' => (bool) $employee->is_active,
            'service_state' => (string) ($employee->service_state ?? ''),
            'work_status_name' => (string) ($employee->work_status_name ?? ''),
            'department' => $employee->department ? [
                'id' => (int) $employee->department->id,
                'name' => (string) ($employee->department->department_name ?? ''),
                'parent_id' => $employee->department->parent_id ? (int) $employee->department->parent_id : null,
                'unit_type_id' => $employee->department->unit_type_id ? (int) $employee->department->unit_type_id : null,
            ] : null,
            'position' => $employee->position ? [
                'id' => (int) $employee->position->id,
                'name' => (string) ($employee->position->position_name ?? ''),
                'name_km' => (string) ($employee->position->position_name_km ?? ''),
                'rank' => $employee->position->position_rank !== null ? (int) $employee->position->position_rank : null,
            ] : null,
            'employee_type' => $employee->employee_type ? [
                'id' => (int) $employee->employee_type->id,
                'name' => (string) ($employee->employee_type->name ?? ''),
                'name_km' => (string) ($employee->employee_type->name_km ?? ''),
                'is_active' => (bool) ($employee->employee_type->is_active ?? false),
            ] : null,
            'updated_at' => optional($employee->updated_at)->toIso8601String(),
            'created_at' => optional($employee->created_at)->toIso8601String(),
        ];
    }
}

