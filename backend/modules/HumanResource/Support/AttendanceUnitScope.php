<?php

namespace Modules\HumanResource\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\Employee;

class AttendanceUnitScope
{
    public const UNIT_TYPES = ['phd', 'provincial_hospital', 'operational_district', 'health_center', 'health_center_with_bed', 'health_center_without_bed', 'health_post'];

    private const INTERNAL_TYPES = ['office', 'bureau', 'program', 'od_section'];

    public function __construct(private readonly OrgHierarchyAccessService $access)
    {
    }

    public function authorize(string $permission): void
    {
        $user = auth()->user();
        abort_unless($user && ($this->access->isSystemAdmin($user) || $user->can($permission)), 403);
    }

    public function departments(): Builder
    {
        $ids = $this->access->managedBranchIds(auth()->user());

        return Department::query()
            ->whereHas('unitType', fn ($q) => $q->whereIn('code', self::UNIT_TYPES))
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->reorder()->orderByRaw("(SELECT CASE org_unit_types.code
                WHEN 'phd' THEN 1
                WHEN 'provincial_hospital' THEN 2
                WHEN 'operational_district' THEN 3
                WHEN 'health_center' THEN 4
                WHEN 'health_center_with_bed' THEN 4
                WHEN 'health_center_without_bed' THEN 4
                WHEN 'health_post' THEN 5
                ELSE 6 END FROM org_unit_types WHERE org_unit_types.id = departments.unit_type_id)")
            ->orderBy('department_name')->orderBy('id');
    }

    public function selected(Request $request): ?int
    {
        $request->validate(['department_id' => ['nullable', 'integer', 'min:1']]);
        if ($request->filled('department_id')) {
            return (int) $this->departments()->findOrFail($request->integer('department_id'))->id;
        }

        return $request->expectsJson() ? null : (int) ($this->departments()->value('id') ?? 0);
    }

    public static function employeeUnit(Employee $employee): int
    {
        $id = (int) ($employee->sub_department_id ?: $employee->department_id);
        $visited = [];
        while ($id > 0 && ! isset($visited[$id])) {
            $visited[$id] = true;
            $node = Department::with('unitType')->find($id);
            if (! $node) {
                return 0;
            }
            $code = $node->unitType?->code;
            if (in_array($code, self::UNIT_TYPES, true)) {
                return $id;
            }
            if (! in_array($code, self::INTERNAL_TYPES, true)) {
                return 0;
            }
            $id = (int) $node->parent_id;
        }

        return 0;
    }

    public function employees(?int $unitId = null): Builder
    {
        $unitIds = $this->departments()->when($unitId !== null, fn ($q) => $q->whereKey($unitId))->pluck('id')->all();
        $allowed = $this->access->managedBranchIds(auth()->user());
        $nodes = Department::with('unitType')->get()->keyBy('id');
        $ids = [];
        foreach ($nodes as $node) {
            // Retain the user's branch permissions when collecting internal offices.
            if ($allowed !== null && ! in_array((int) $node->id, $allowed, true)) {
                continue;
            }
            $current = $node;
            $visited = [];
            while ($current && ! isset($visited[$current->id])) {
                $visited[$current->id] = true;
                $code = $current->unitType?->code;
                if (in_array($code, self::UNIT_TYPES, true)) {
                    if (in_array((int) $current->id, $unitIds, true)) {
                        $ids[] = (int) $node->id;
                    }
                    break;
                }
                if (! in_array($code, self::INTERNAL_TYPES, true)) {
                    break;
                }
                $current = $nodes->get($current->parent_id);
            }
        }

        return Employee::query()->whereIn(\Illuminate\Support\Facades\DB::raw('COALESCE(NULLIF(employees.sub_department_id, 0), employees.department_id)'), $ids);
    }
}
