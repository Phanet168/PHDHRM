<?php

namespace Modules\HumanResource\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Support\AttendanceUnitScope;

/**
 * Mobile "staff directory": the caller's own facility, grouped by office
 * and ordered by hierarchical rank -- the same structure
 * AttendanceUnitScope::hierarchyRows() already produces for the web admin
 * side. Strictly self-scoped like MobileAttendanceController's own
 * self-service endpoints: no client-supplied department_id, so this works
 * for every employee (not just managers/admins) and never leaks another
 * facility's roster.
 */
class StaffStructureApiController extends Controller
{
    public function __construct(private readonly AttendanceUnitScope $scope)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate(['employee_id' => ['prohibited'], 'department_id' => ['prohibited']]);

        $employee = $request->user()?->employee()->where('is_active', 1)->first();
        if (! $employee) {
            throw ValidationException::withMessages(['employee' => 'This account has no active employee profile.']);
        }

        $unitId = AttendanceUnitScope::employeeUnit($employee);
        if (! $unitId) {
            return $this->ok([], null, null);
        }

        $department = Department::find($unitId);
        $employees = $this->scope->employeesInUnit($unitId)
            ->where('is_active', 1)
            ->with(['department', 'sub_department', 'gender', 'position'])
            ->get(['id', 'first_name', 'middle_name', 'last_name', 'employee_id', 'department_id', 'sub_department_id', 'gender_id', 'position_id']);

        $rows = $this->scope->hierarchyRows($employees, $unitId);

        return $this->ok($this->present($rows), $unitId, $department?->department_name);
    }

    private function ok(array $rows, ?int $departmentId, ?string $departmentName): JsonResponse
    {
        return response()->json(['response' => [
            'status' => 'ok',
            'data' => $rows,
            'meta' => ['department_id' => $departmentId, 'department_name' => $departmentName],
        ]]);
    }

    /** @return array<int, array<string, mixed>> */
    private function present(array $rows): array
    {
        return array_map(function (array $row) {
            if ($row['type'] === 'header') {
                return [
                    'type' => 'header',
                    'depth' => $row['depth'],
                    'label' => $row['label'],
                    'total' => $row['total'],
                    'male' => $row['male'],
                    'female' => $row['female'],
                ];
            }

            /** @var Employee $employee */
            $employee = $row['employee'];

            return [
                'type' => 'employee',
                'id' => $employee->id,
                'employee_id' => $employee->employee_id,
                'full_name' => $employee->full_name,
                'position' => $employee->position?->position_name_km ?: $employee->position?->position_name,
                'gender' => $employee->gender?->gender_name,
            ];
        }, $rows);
    }
}
