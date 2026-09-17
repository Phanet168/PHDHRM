<?php

namespace Tests\Unit;

use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;
use Modules\HumanResource\Support\EmployeeReportDataset;
use PHPUnit\Framework\TestCase;

class EmployeeReportDatasetTest extends TestCase
{
    private function employees(): Collection
    {
        return collect([
            new Fluent(['name' => 'A', 'department_id' => 1, 'department' => 'Shared name', 'gender' => 'ប្រុស']),
            new Fluent(['name' => 'B', 'department_id' => 1, 'department' => 'Shared name', 'gender' => 'ស្រី']),
            new Fluent(['name' => 'C', 'department_id' => 2, 'department' => 'Shared name', 'gender' => '']),
            new Fluent(['name' => 'D', 'department_id' => 2, 'sub_department_id' => 3, 'department' => 'Shared name', 'sub_department' => 'Clinic', 'gender' => 'Female']),
        ]);
    }

    public function test_summary_counts_every_employee_including_unspecified_gender(): void
    {
        $result = (new EmployeeReportDataset)->build($this->employees(), ['name'], fn ($e, $c) => (string) $e->$c, 'summary', 'department', true);
        $row = $result['rows']->first();
        $this->assertSame(4, $row['total']);
        $this->assertSame(1, $row['male']);
        $this->assertSame(2, $row['female']);
        $this->assertSame(1, $row['unspecified']);
        $this->assertSame($row['total'], $row['male'] + $row['female'] + $row['unspecified']);
        $this->assertNotContains('name', $result['columns']);
    }

    public function test_structured_list_keeps_distinct_unit_identities_and_all_staff(): void
    {
        $result = (new EmployeeReportDataset)->build($this->employees(), ['name'], fn ($e, $c) => (string) $e->$c, 'detail', 'department', false, 'structured');
        $this->assertSame(['Shared name', 'Shared name', 'Clinic'], $result['rows']->filter(fn ($r) => isset($r['__group']))->pluck('__group')->all());
        $this->assertSame(['A', 'B', 'C', 'D'], $result['rows']->reject(fn ($r) => isset($r['__group']))->pluck('name')->all());
    }

    public function test_plain_list_exports_only_selected_columns_in_requested_order(): void
    {
        $result = (new EmployeeReportDataset)->build($this->employees(), ['gender', 'name'], fn ($e, $c) => (string) $e->$c);
        $this->assertSame(['gender', 'name'], array_keys($result['rows']->first()));
        $this->assertCount(4, $result['rows']);
    }

    public function test_empty_summary_still_has_headers_and_no_invented_records(): void
    {
        $result = (new EmployeeReportDataset)->build(collect(), [], fn () => '', 'summary');
        $this->assertSame(['group_label', 'total'], $result['columns']);
        $this->assertCount(0, $result['rows']);
    }
}
