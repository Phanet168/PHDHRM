<?php

namespace Modules\HumanResource\Support;

use Illuminate\Support\Collection;

class EmployeeStructureSummary
{
    public function build(Collection $employees, Collection $units, callable $cell, string $groupBy = 'skill_name', bool $splitGender = true): array
    {
        $unitsById = $units->keyBy('id');
        $categories = $employees->map(fn ($employee) => trim($cell($employee, $groupBy)) ?: 'មិនបានបញ្ជាក់')
            ->unique()->sort(SORT_NATURAL)->values();
        $labels = ['structure' => 'រចនាសម្ព័ន្ធអង្គភាព'];
        $categoryKeys = [];
        foreach ($categories as $index => $category) {
            $key = 'category_' . $index;
            $categoryKeys[$category] = $key;
            $labels[$key] = $category;
        }
        $labels['total'] = 'សរុប';
        $counts = [];
        $unknown = [];

        foreach ($employees as $employee) {
            $category = $categoryKeys[trim($cell($employee, $groupBy)) ?: 'មិនបានបញ្ជាក់'];
            $gender = match (mb_strtolower(trim($cell($employee, 'gender')))) {
                'female', 'f', 'ស្រី' => 'female',
                'male', 'm', 'ប្រុស' => 'male',
                default => 'unspecified',
            };
            $unitId = (int) ($employee->sub_department_id ?: $employee->department_id ?: 0);
            if (!$unitsById->has($unitId)) {
                $unknown[$gender][$category] = ($unknown[$gender][$category] ?? 0) + 1;
                continue;
            }
            // Count once at the effective workplace, then once at each ancestor.
            // The visited set also terminates malformed cycles without double counting.
            $visited = [];
            while ($unitId && $unitsById->has($unitId) && !isset($visited[$unitId])) {
                $visited[$unitId] = true;
                $counts[$unitId][$gender][$category] = ($counts[$unitId][$gender][$category] ?? 0) + 1;
                $unitId = (int) $unitsById->get($unitId)->parent_id;
            }
        }

        $children = $units->groupBy(fn ($unit) => (int) $unit->parent_id);
        $rows = collect();
        $append = function (int $id, string $name, int $depth, array $buckets) use ($rows, $categoryKeys, $splitGender) {
            $total = ['structure' => str_repeat('    ', $depth) . $name, '__depth' => $depth, '__row_kind' => 'unit', '__unit_id' => $id];
            foreach ($categoryKeys as $key) {
                $total[$key] = array_sum(array_column($buckets, $key));
            }
            $total['total'] = array_sum(array_intersect_key($total, array_flip(array_values($categoryKeys))));
            $rows->push($total);
            if (!$splitGender) {
                return;
            }
            foreach (['female' => 'ស្រី', 'male' => 'ប្រុស', 'unspecified' => 'មិនបានបញ្ជាក់'] as $gender => $label) {
                if ($gender === 'unspecified' && empty($buckets[$gender])) {
                    continue;
                }
                $row = ['structure' => str_repeat('    ', $depth + 1) . $label, '__depth' => $depth + 1, '__row_kind' => $gender, '__unit_id' => $id];
                foreach ($categoryKeys as $key) {
                    $row[$key] = $buckets[$gender][$key] ?? 0;
                }
                $row['total'] = array_sum($buckets[$gender] ?? []);
                $rows->push($row);
            }
        };

        $visited = [];
        $walk = function ($unit, int $depth) use (&$walk, &$visited, $children, $counts, $append) {
            $id = (int) $unit->id;
            if (isset($visited[$id])) {
                return;
            }
            $visited[$id] = true;
            if (isset($counts[$id])) {
                $append($id, (string) $unit->department_name, $depth, $counts[$id]);
            }
            foreach ($children->get($id, collect()) as $child) {
                $walk($child, $depth + 1);
            }
        };
        foreach ($units as $unit) {
            if (!$unit->parent_id || !$unitsById->has($unit->parent_id)) {
                $walk($unit, 0);
            }
        }
        // Include orphaned cycles deterministically instead of dropping their staff.
        foreach ($units as $unit) {
            if (!isset($visited[$unit->id])) {
                $walk($unit, 0);
            }
        }
        if ($unknown) {
            $append(0, 'មិនបានបញ្ជាក់អង្គភាព', 0, $unknown);
        }

        return ['columns' => array_keys($labels), 'labels' => $labels, 'rows' => $rows];
    }
}
