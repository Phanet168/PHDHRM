<?php

namespace Tests\Unit;

use Illuminate\Support\Fluent;
use Modules\HumanResource\Support\EmployeeStructureSummary;
use PHPUnit\Framework\TestCase;

class EmployeeStructureSummaryTest extends TestCase
{
    private function units()
    {
        return collect([
            new Fluent(['id' => 1, 'department_name' => 'Root', 'parent_id' => null]),
            new Fluent(['id' => 2, 'department_name' => 'Clinic', 'parent_id' => 1]),
            new Fluent(['id' => 3, 'department_name' => 'Clinic', 'parent_id' => 1]),
        ]);
    }

    private function employees()
    {
        return collect([
            new Fluent(['department_id' => 1, 'skill_name' => 'Doctor', 'gender' => 'M']),
            new Fluent(['department_id' => 1, 'sub_department_id' => 2, 'skill_name' => 'Nurse', 'gender' => 'ស្រី']),
            new Fluent(['department_id' => 1, 'sub_department_id' => 2, 'skill_name' => 'Doctor', 'gender' => 'Female']),
            new Fluent(['department_id' => 3, 'skill_name' => '', 'gender' => '']),
        ]);
    }

    public function test_matrix_rolls_up_descendants_without_double_counting_and_splits_each_unit(): void
    {
        $result = (new EmployeeStructureSummary)->build($this->employees(), $this->units(), fn ($e, $c) => (string) $e->$c);
        $root = $result['rows']->where('__unit_id', 1);
        $total = $root->firstWhere('__row_kind', 'unit');
        $this->assertSame(4, $total['total']);
        $this->assertSame(2, $total[array_search('Doctor', $result['labels'], true)]);
        $this->assertSame(1, $total[array_search('Nurse', $result['labels'], true)]);
        $this->assertSame(1, $total[array_search('មិនបានបញ្ជាក់', $result['labels'], true)]);
        $this->assertSame(['unit', 'female', 'male', 'unspecified'], $root->pluck('__row_kind')->all());
        $this->assertSame(4, $root->where('__row_kind', '!=', 'unit')->sum('total'));
        $unitRows = $result['rows']->where('__row_kind', 'unit')->values();
        $this->assertSame([1, 2, 3], $unitRows->pluck('__unit_id')->all());
        $this->assertSame([4, 2, 1], $unitRows->pluck('total')->all());
        $this->assertSame([0, 1, 1], $unitRows->pluck('__depth')->all());
    }

    public function test_filtered_root_starts_at_depth_zero_and_only_counts_filtered_employees(): void
    {
        $result = (new EmployeeStructureSummary)->build($this->employees()->where('sub_department_id', 2), $this->units()->where('id', 2), fn ($e, $c) => (string) $e->$c, 'skill_name', false);
        $this->assertCount(1, $result['rows']);
        $this->assertSame(2, $result['rows']->first()['total']);
        $this->assertSame(0, $result['rows']->first()['__depth']);
    }

    public function test_unassigned_staff_are_preserved_and_empty_data_has_no_fake_counts(): void
    {
        $builder = new EmployeeStructureSummary;
        $empty = $builder->build(collect(), $this->units(), fn () => '');
        $this->assertCount(0, $empty['rows']);
        $this->assertSame(['structure', 'total'], $empty['columns']);
        $unknown = $builder->build(collect([new Fluent(['skill_name' => 'Nurse'])]), $this->units(), fn ($e, $c) => (string) $e->$c);
        $this->assertSame(1, $unknown['rows']->first()['total']);
        $this->assertSame(0, $unknown['rows']->first()['__unit_id']);
    }

    public function test_cycles_terminate_and_each_ancestor_counts_a_staff_member_once(): void
    {
        $units = collect([new Fluent(['id' => 1, 'parent_id' => 2, 'department_name' => 'A']), new Fluent(['id' => 2, 'parent_id' => 1, 'department_name' => 'B'])]);
        $result = (new EmployeeStructureSummary)->build($this->employees()->take(1), $units, fn ($e, $c) => (string) $e->$c, 'skill_name', false);
        $this->assertSame([1, 1], $result['rows']->pluck('total')->all());
    }
}
