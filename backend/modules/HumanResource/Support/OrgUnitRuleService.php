<?php

namespace Modules\HumanResource\Support;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\OrgUnitType;
use Modules\HumanResource\Entities\OrgUnitTypePosition;
use Modules\HumanResource\Entities\Position;

class OrgUnitRuleService
{
    public function allowedPositionsForDepartment(?int $departmentId): Collection
    {
        $departmentId = (int) $departmentId;
        if ($departmentId <= 0) {
            return $this->fallbackActivePositions();
        }

        $unitTypeId = (int) (Department::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->whereKey($departmentId)
            ->value('unit_type_id') ?? 0);

        return $this->allowedPositionsForUnitType($unitTypeId);
    }

    public function allowedPositionsForUnitType(?int $unitTypeId): Collection
    {
        $unitTypeId = (int) $unitTypeId;

        if ($unitTypeId <= 0) {
            return $this->fallbackActivePositions();
        }

        $mappings = OrgUnitTypePosition::query()
            ->where('unit_type_id', $unitTypeId)
            ->where('is_active', true)
            ->with(['position' => function ($query) {
                $query->withoutGlobalScope('sortByLatest')
                    ->where('is_active', true)
                    ->whereNull('deleted_at');
            }])
            ->orderByRaw('CASE WHEN hierarchy_rank IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('hierarchy_rank')
            ->orderByDesc('is_leadership')
            ->orderBy('id')
            ->get();

        $positions = $mappings
            ->map(function (OrgUnitTypePosition $mapping) {
                return $mapping->position;
            })
            ->filter()
            ->unique('id')
            ->values();

        if ($positions->isEmpty()) {
            return $this->fallbackActivePositions();
        }

        return $positions;
    }

    public function activeTypes()
    {
        return OrgUnitType::active()->orderBy('sort_order')->orderBy('name')->get();
    }

    public function rootTypes()
    {
        return OrgUnitType::active()
            ->whereDoesntHave('childRules')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function allowedParentTypeIdsByChildType(): array
    {
        $activeTypes = $this->activeTypes();
        $typeIds = $activeTypes->pluck('id')->map(function ($id) {
            return (int) $id;
        })->values()->all();
        $map = [];

        // Legacy DMT style: allow free hierarchy chaining (sub -> sub -> sub)
        // regardless of strict parent-child type rule definitions.
        foreach ($typeIds as $childTypeId) {
            $map[$childTypeId] = $typeIds;
        }

        return $map;
    }

    protected function compatibilityParentRulesByCode(): array
    {
        return [
            // Legacy structures sometimes nest office and OD-section blocks recursively.
            'office' => ['office'],
            'od_section' => ['od_section'],

            // Leadership under OD section was stored as bureau in legacy data.
            'bureau' => ['od_section', 'bureau'],

            // Some legacy datasets keep program directly under office.
            'program' => ['office'],

            // Legacy "all HC" containers may appear as health_center -> health_center.
            'health_center' => ['health_center'],
        ];
    }

    public function parentOptionsForType(?int $childTypeId, ?int $excludeDepartmentId = null)
    {
        return $this->hierarchyOptions($excludeDepartmentId);
    }

    public function validateParentRule(int $childTypeId, ?int $parentDepartmentId, ?Department $currentDepartment = null): void
    {
        $childType = OrgUnitType::active()->find($childTypeId);

        if (!$childType) {
            throw ValidationException::withMessages([
                'unit_type_id' => localize('invalid_unit_type_selected'),
            ]);
        }

        if (!$parentDepartmentId) {
            // Root unit is valid in legacy tree-management mode.
            return;
        }

        $parentDepartment = Department::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->find($parentDepartmentId);

        if (!$parentDepartment) {
            throw ValidationException::withMessages([
                'parent_id' => localize('selected_parent_unit_not_found'),
            ]);
        }

        if (!$parentDepartment->unit_type_id) {
            throw ValidationException::withMessages([
                'parent_id' => localize('selected_parent_unit_has_no_type'),
            ]);
        }

        if ($currentDepartment && (int) $parentDepartment->id === (int) $currentDepartment->id) {
            throw ValidationException::withMessages([
                'parent_id' => localize('unit_can_not_be_parent_of_itself'),
            ]);
        }

        if ($currentDepartment && $parentDepartment->hasAncestor($currentDepartment->id)) {
            throw ValidationException::withMessages([
                'parent_id' => localize('parent_can_not_be_selected_from_child_branch'),
            ]);
        }
    }

    public function hierarchyOptions(?int $excludeBranchRootId = null): Collection
    {
        $units = $this->activeUnits();

        if ($units->isEmpty()) {
            return collect();
        }

        $unitsById = $units->keyBy('id');
        $childrenByParent = $units->groupBy(function ($unit) {
            return $unit->parent_id ?? 0;
        });
        $roots = $this->rootUnits($units, $unitsById);

        $excludedIds = $this->excludedBranchIds($excludeBranchRootId, $childrenByParent);
        $result = collect();
        $visited = [];

        foreach ($roots as $root) {
            $this->appendHierarchyNode($root, $childrenByParent, $result, $visited, 0, [], $excludedIds, (int) $root->id, (string) $root->department_name);
        }

        foreach ($units as $unit) {
            if (!isset($visited[$unit->id])) {
                $this->appendHierarchyNode($unit, $childrenByParent, $result, $visited, 0, [], $excludedIds, (int) $unit->id, (string) $unit->department_name);
            }
        }

        return $result->values();
    }

    public function hierarchyTree(): array
    {
        $units = $this->activeUnits();

        if ($units->isEmpty()) {
            return [];
        }

        $unitsById = $units->keyBy('id');
        $childrenByParent = $units->groupBy(function ($unit) {
            return $unit->parent_id ?? 0;
        });
        $roots = $this->rootUnits($units, $unitsById);

        $tree = [];
        $visited = [];

        foreach ($roots as $root) {
            $node = $this->buildHierarchyTreeNode($root, $childrenByParent, [], $visited);
            if ($node) {
                $tree[] = $node;
            }
        }

        foreach ($units as $unit) {
            if (!isset($visited[$unit->id])) {
                $node = $this->buildHierarchyTreeNode($unit, $childrenByParent, [], $visited);
                if ($node) {
                    $tree[] = $node;
                }
            }
        }

        return $tree;
    }

    public function branchIdsIncludingSelf(int $rootDepartmentId): array
    {
        if ($rootDepartmentId <= 0) {
            return [];
        }

        $units = $this->activeUnits();

        if ($units->isEmpty()) {
            return [$rootDepartmentId];
        }

        $childrenByParent = $units->groupBy(function ($unit) {
            return (int) ($unit->parent_id ?? 0);
        });

        $result = [];
        $stack = [$rootDepartmentId];

        while (!empty($stack)) {
            $currentId = (int) array_pop($stack);

            if (isset($result[$currentId])) {
                continue;
            }

            $result[$currentId] = true;

            $children = collect($childrenByParent->get($currentId, []));
            foreach ($children as $child) {
                $stack[] = (int) $child->id;
            }
        }

        return array_keys($result);
    }

    protected function activeUnits(): Collection
    {
        return Department::withoutGlobalScopes()
            ->with('unitType')
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->whereNotNull('unit_type_id')
            ->orderByRaw('COALESCE(sort_order, 999999) asc')
            ->orderBy('department_name')
            ->get();
    }

    protected function rootUnits(Collection $units, Collection $unitsById): Collection
    {
        return $this->sortUnits($units->filter(function ($unit) use ($unitsById) {
            return !$unit->parent_id || !$unitsById->has($unit->parent_id);
        }));
    }

    protected function excludedBranchIds(?int $excludeBranchRootId, Collection $childrenByParent): array
    {
        if (!$excludeBranchRootId) {
            return [];
        }

        $excluded = [];
        $stack = [$excludeBranchRootId];

        while (!empty($stack)) {
            $currentId = array_pop($stack);
            if (isset($excluded[$currentId])) {
                continue;
            }

            $excluded[$currentId] = true;

            $children = collect($childrenByParent->get($currentId, []));
            foreach ($children as $child) {
                $stack[] = (int) $child->id;
            }
        }

        return $excluded;
    }

    protected function buildHierarchyTreeNode(
        Department $unit,
        Collection $childrenByParent,
        array $ancestorNames,
        array &$visited
    ): ?array {
        if (isset($visited[$unit->id])) {
            return null;
        }

        $visited[$unit->id] = true;

        $pathParts = array_merge($ancestorNames, [$unit->department_name]);
        $children = $this->sortUnits(collect($childrenByParent->get($unit->id, [])));

        $childNodes = [];
        foreach ($children as $child) {
            $childNode = $this->buildHierarchyTreeNode($child, $childrenByParent, $pathParts, $visited);
            if ($childNode) {
                $childNodes[] = $childNode;
            }
        }

        return [
            'id' => $unit->id,
            'uuid' => $unit->uuid,
            'sort_order' => $unit->sort_order !== null ? (int) $unit->sort_order : null,
            'name' => $unit->department_name,
            'type' => $unit->unitType?->display_name ?? localize('unit_type_not_set'),
            'path' => implode(' > ', $pathParts),
            'children' => $childNodes,
        ];
    }

    protected function appendHierarchyNode(
        Department $unit,
        Collection $childrenByParent,
        Collection $result,
        array &$visited,
        int $depth,
        array $ancestorNames,
        array $excludedIds = [],
        ?int $rootId = null,
        ?string $rootName = null
    ): void {
        if (isset($visited[$unit->id])) {
            return;
        }

        if (isset($excludedIds[$unit->id])) {
            return;
        }

        $visited[$unit->id] = true;

        $pathParts = array_merge($ancestorNames, [$unit->department_name]);
        $pathLabel = implode(' > ', $pathParts);
        $indent = str_repeat('-- ', $depth);
        $typeLabel = $unit->unitType?->display_name ?? localize('unit_type_not_set');
        $sortOrder = $unit->sort_order !== null ? (int) $unit->sort_order : null;
        $orderLabel = $sortOrder !== null ? str_pad((string) $sortOrder, 2, '0', STR_PAD_LEFT) . ' ' : '';
        $resolvedRootId = $rootId ?: (int) $unit->id;
        $resolvedRootName = $rootName ?: (string) $unit->department_name;

        $result->push((object) [
            'id' => $unit->id,
            'parent_id' => $unit->parent_id ? (int) $unit->parent_id : null,
            'root_id' => $resolvedRootId,
            'root_name' => $resolvedRootName,
            'unit_type_id' => $unit->unit_type_id,
            'sort_order' => $sortOrder,
            'label' => trim($indent . $orderLabel . $unit->department_name . ' (' . $typeLabel . ')'),
            'path' => $pathLabel,
            'depth' => $depth,
        ]);

        $children = $this->sortUnits(collect($childrenByParent->get($unit->id, [])));

        foreach ($children as $child) {
            $this->appendHierarchyNode(
                $child,
                $childrenByParent,
                $result,
                $visited,
                $depth + 1,
                $pathParts,
                $excludedIds,
                $resolvedRootId,
                $resolvedRootName
            );
        }
    }

    protected function sortUnits(Collection $units): Collection
    {
        return $units->sort(function ($left, $right) {
            $leftOrder = $left->sort_order === null ? PHP_INT_MAX : (int) $left->sort_order;
            $rightOrder = $right->sort_order === null ? PHP_INT_MAX : (int) $right->sort_order;

            if ($leftOrder === $rightOrder) {
                return strcmp(
                    strtolower((string) $left->department_name),
                    strtolower((string) $right->department_name)
                );
            }

            return $leftOrder <=> $rightOrder;
        })->values();
    }

    protected function fallbackActivePositions(): Collection
    {
        return Position::query()
            ->withoutGlobalScope('sortByLatest')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderByRaw('CASE WHEN position_rank IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('position_rank')
            ->orderBy('position_name')
            ->get();
    }
}
