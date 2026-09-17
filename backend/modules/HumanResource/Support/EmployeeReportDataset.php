<?php

namespace Modules\HumanResource\Support;

use Illuminate\Support\Collection;

class EmployeeReportDataset
{
    public function build(Collection $employees, array $columns, callable $cell, string $mode = 'detail', string $groupBy = 'department', bool $splitGender = false, string $layout = 'plain'): array
    {
        $groups = $employees->groupBy(fn ($employee) => $cell($employee, $groupBy) ?: '-');

        if ($mode === 'summary') {
            $labels = ['group_label' => 'ក្រុម', 'total' => 'ចំនួនសរុប'];
            if ($splitGender) {
                $labels += ['male' => 'ប្រុស', 'female' => 'ស្រី', 'unspecified' => 'មិនបានបញ្ជាក់'];
            }
            $rows = $groups->map(function ($items, $label) use ($cell, $splitGender) {
                $row = ['group_label' => (string) $label, 'total' => $items->count()];
                if ($splitGender) {
                    $male = $items->filter(fn ($employee) => in_array(mb_strtolower(trim($cell($employee, 'gender'))), ['male', 'm', 'ប្រុស'], true))->count();
                    $female = $items->filter(fn ($employee) => in_array(mb_strtolower(trim($cell($employee, 'gender'))), ['female', 'f', 'ស្រី'], true))->count();
                    $row += ['male' => $male, 'female' => $female, 'unspecified' => $items->count() - $male - $female];
                }
                return $row;
            })->values();

            return ['columns' => array_keys($labels), 'labels' => $labels, 'rows' => $rows];
        }

        $mapRow = function ($employee) use ($columns, $cell) {
            $row = [];
            foreach ($columns as $column) {
                $row[$column] = $cell($employee, $column);
            }
            return $row;
        };

        if ($layout === 'structured') {
            // Group by unit identity so unrelated units with the same name remain separate.
            $units = $employees->groupBy(fn ($employee) => $employee->sub_department_id ?: $employee->department_id ?: 0);
            $rows = collect();
            foreach ($units as $items) {
                $employee = $items->first();
                $rows->push(['__group' => $cell($employee, 'sub_department') ?: $cell($employee, 'department') ?: '-']);
                foreach ($items as $employee) {
                    $rows->push($mapRow($employee));
                }
            }
        } else {
            $rows = $employees->map($mapRow)->values();
        }

        return ['columns' => $columns, 'labels' => [], 'rows' => $rows];
    }
}
