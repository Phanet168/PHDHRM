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

    /**
     * Attendance Phase B: the subset of UNIT_TYPES that directly serve
     * patients around the clock and therefore may have duty/on-call shifts
     * (វេនយាម) -- PHD and operational_district are administrative offices
     * with fixed hours even though they're attendance-tracked, so they're
     * deliberately excluded here.
     */
    public const DUTY_ELIGIBLE_UNIT_TYPES = ['provincial_hospital', 'health_center', 'health_center_with_bed', 'health_center_without_bed', 'health_post'];

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

        return Employee::query()
            ->whereIn(\Illuminate\Support\Facades\DB::raw('COALESCE(NULLIF(employees.sub_department_id, 0), employees.department_id)'), $ids)
            ->orderByRaw($this->orderByHierarchySql());
    }

    /**
     * Employees rolled up into exactly $unitId (including any internal
     * offices/sections beneath it) -- unlike employees(), this does NOT
     * gate by managedBranchIds(), so it also works for an ordinary staff
     * member with no management authority over their own facility (e.g.
     * the mobile "my facility's staff directory" API). Callers are
     * responsible for their own authorization -- typically by confirming
     * $unitId actually is the caller's own unit before calling this.
     */
    public function employeesInUnit(int $unitId): Builder
    {
        $nodes = Department::with('unitType')->get()->keyBy('id');
        $ids = [];
        foreach ($nodes as $node) {
            $current = $node;
            $visited = [];
            while ($current && ! isset($visited[$current->id])) {
                $visited[$current->id] = true;
                $code = $current->unitType?->code;
                if (in_array($code, self::UNIT_TYPES, true)) {
                    if ((int) $current->id === $unitId) {
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

        return Employee::query()
            ->whereIn(\Illuminate\Support\Facades\DB::raw('COALESCE(NULLIF(employees.sub_department_id, 0), employees.department_id)'), $ids)
            ->orderByRaw($this->orderByHierarchySql());
    }

    /**
     * Turns an employee list into a flat, ready-to-render sequence of rows
     * matching the reference staff table's structure (EmployeeStructuredReportExport):
     * a header row for every internal office/section/team level between
     * $rootUnitId and each employee's own department (ថ្នាក់ដឹកនាំ, ការិយាល័យ,
     * ផ្នែក, ក្រុម, ...), each carrying (total/male/female), followed by that
     * segment's employees -- a control-break over the org chain, so a
     * header is only repeated when the chain actually changes from the
     * previous employee. Employees directly in $rootUnitId with no internal
     * office get no header at all. Callers must eager-load `position` and
     * `gender` on $employees first (department chain is walked fresh here
     * since it can go deeper than the sub_department/department columns).
     *
     * @param \Illuminate\Support\Collection<int, Employee> $employees
     * @return array<int, array{type: 'header', depth: int, label: string, total: int, male: int, female: int}|array{type: 'employee', employee: Employee}>
     */
    public function hierarchyRows(\Illuminate\Support\Collection $employees, ?int $rootUnitId): array
    {
        if ($employees->isEmpty()) {
            return [];
        }

        $allDepartments = Department::withoutGlobalScopes()
            ->select(['id', 'department_name', 'parent_id', 'sort_order'])
            ->get()->keyBy('id');

        $prepared = $employees->map(function (Employee $employee) use ($allDepartments, $rootUnitId) {
            $chain = [];
            $currentId = (int) ($employee->sub_department_id ?: $employee->department_id);
            $visited = [];
            while ($currentId > 0 && ! isset($visited[$currentId])) {
                $visited[$currentId] = true;
                $dept = $allDepartments->get($currentId);
                if (! $dept) {
                    break;
                }
                $chain[] = $dept;
                if ($rootUnitId !== null && (int) $dept->id === $rootUnitId) {
                    break;
                }
                $currentId = (int) $dept->parent_id;
            }
            $chain = array_reverse($chain);
            if ($rootUnitId !== null && ! empty($chain) && (int) $chain[0]->id === $rootUnitId) {
                array_shift($chain);
            }

            $pathKey = implode('|', array_map(
                fn ($d) => str_pad((string) ($d->sort_order ?? 999999), 6, '0', STR_PAD_LEFT) . ':' . $d->id,
                $chain
            ));

            return [
                'employee' => $employee,
                'segments' => array_map(fn ($d) => trim((string) $d->department_name), $chain),
                'path_key' => $pathKey,
            ];
        })->sort(function (array $a, array $b) {
            $compare = strcmp($a['path_key'], $b['path_key']);
            if ($compare !== 0) {
                return $compare;
            }
            $rankA = $a['employee']->position?->position_rank ?? PHP_INT_MAX;
            $rankB = $b['employee']->position?->position_rank ?? PHP_INT_MAX;
            if ($rankA !== $rankB) {
                return $rankA <=> $rankB;
            }

            return strcmp((string) $a['employee']->full_name, (string) $b['employee']->full_name);
        })->values();

        $countsByPrefix = [];
        foreach ($prepared as $item) {
            $prefix = [];
            foreach ($item['segments'] as $segment) {
                $prefix[] = $segment;
                $key = implode('|', $prefix);
                $countsByPrefix[$key] ??= ['total' => 0, 'male' => 0];
                $countsByPrefix[$key]['total']++;
                if ($this->isMale($item['employee'])) {
                    $countsByPrefix[$key]['male']++;
                }
            }
        }

        $rows = [];
        $previousSegments = [];
        foreach ($prepared as $item) {
            $segments = $item['segments'];
            $changedIndex = 0;
            while (
                $changedIndex < count($segments)
                && $changedIndex < count($previousSegments)
                && $segments[$changedIndex] === $previousSegments[$changedIndex]
            ) {
                $changedIndex++;
            }
            for ($depth = $changedIndex; $depth < count($segments); $depth++) {
                $key = implode('|', array_slice($segments, 0, $depth + 1));
                $counts = $countsByPrefix[$key] ?? ['total' => 0, 'male' => 0];
                $rows[] = [
                    'type' => 'header',
                    'depth' => $depth,
                    'label' => $segments[$depth],
                    'total' => $counts['total'],
                    'male' => $counts['male'],
                    'female' => $counts['total'] - $counts['male'],
                ];
            }
            $previousSegments = $segments;
            $rows[] = ['type' => 'employee', 'employee' => $item['employee']];
        }

        return $rows;
    }

    private function isMale(Employee $employee): bool
    {
        $value = mb_strtolower(trim((string) ($employee->gender?->gender_name ?? '')), 'UTF-8');

        return in_array($value, ['male', 'm', 'ប្រុស'], true);
    }

    /**
     * Same ordering convention the main staff list (EmployeeDataTable /
     * EmployeeStructuredReportExport) uses: cluster employees by their own
     * immediate office (sub_department_id, falling back to department_id --
     * this may be an internal office/bureau a level below the selected
     * duty-eligible unit) in that office's structural sort_order, then by
     * hierarchical rank (position_rank, nulls last), then name. Clustering
     * by office first keeps each office's staff contiguous, which is what
     * lets callers render a group header per office (see
     * shift-teams/index.blade.php and shift-rosters/index.blade.php).
     */
    private function orderByHierarchySql(): string
    {
        return "
            (SELECT COALESCE(d.sort_order, 999999) FROM departments d
                WHERE d.id = COALESCE(NULLIF(employees.sub_department_id, 0), employees.department_id)) ASC,
            (SELECT d.department_name FROM departments d
                WHERE d.id = COALESCE(NULLIF(employees.sub_department_id, 0), employees.department_id)) ASC,
            (SELECT CASE WHEN positions.position_rank IS NULL THEN 1 ELSE 0 END
                FROM positions WHERE positions.id = employees.position_id) ASC,
            (SELECT positions.position_rank FROM positions WHERE positions.id = employees.position_id) ASC,
            COALESCE(NULLIF(employees.last_name, ''), '') ASC,
            COALESCE(NULLIF(employees.first_name, ''), '') ASC
        ";
    }
}
