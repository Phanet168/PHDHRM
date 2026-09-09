<?php

namespace Tests\Unit;

use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;
use Modules\HumanResource\Exports\EmployeeStructuredReportExport;
use PHPUnit\Framework\TestCase;

class EmployeeStructuredReportExportTest extends TestCase
{
    public function test_position_precedes_grade_and_official_id(): void
    {
        $export = $this->report([
            $this->employee('001', 3, 'ក.១-១'),
            $this->employee('002', 2, 'ក.១-១'),
            $this->employee('999', 1, 'ខ.១-១'),
        ]);

        $this->assertSame(['999', '002', '001'], $export->staffIds());
    }

    public function test_equal_positions_use_configured_grade_order_for_khmer_and_latin_values(): void
    {
        $export = $this->report([
            $this->employee('001', 2, 'ខ.១-១'),
            $this->employee('002', 2, 'ក.១-៣'),
            $this->employee('003', 2, ' a.1-2 '),
            $this->employee('004', 2, ' ក. 1-1 '),
        ]);

        $this->assertSame(['004', '003', '002', '001'], $export->staffIds());
    }

    public function test_sort_uses_displayed_grade_and_history_fallbacks(): void
    {
        $current = $this->employee('002', 2, '');
        $current->currentPayGradeHistory = new Fluent([
            'payLevel' => new Fluent(['level_name_km' => 'ក.១-២']),
        ]);
        $latest = $this->employee('003', 2, '');
        $latest->latestPayGradeHistory = new Fluent([
            'payLevel' => new Fluent(['level_code' => 'A.1-1']),
        ]);
        $explicit = $this->employee('001', 2, 'ខ.១-១');
        $explicit->currentPayGradeHistory = $latest->latestPayGradeHistory;

        $export = $this->report([$explicit, $current, $latest]);

        $this->assertSame(['003', '002', '001'], $export->staffIds());
        $this->assertSame(['A.1-1', 'ក.១-២', 'ខ.១-១'], array_column($export->staffRows(), 11));
    }

    public function test_missing_ranks_and_unknown_grades_sort_last_with_stable_ties(): void
    {
        $export = $this->report([
            $this->employee('001', null, 'ក.១-១'),
            $this->employee('003', 2, ''),
            $this->employee('002', 2, 'Unknown'),
            $this->employee('005', 2, 'ក.១-១'),
            $this->employee('004', 2, 'ក.១-១'),
            $this->employee('006', 0, ''),
        ]);

        $this->assertSame(['006', '004', '005', '002', '003', '001'], $export->staffIds());
    }

    public function test_unit_sections_and_numbering_remain_together(): void
    {
        $secondUnitLeader = $this->employee('001', 1, 'ក.១-១');
        $secondUnitLeader->department_id = 2;
        $export = $this->report([
            $secondUnitLeader,
            $this->employee('002', 3, 'ក.១-១'),
            $this->employee('003', 2, 'ខ.១-១'),
        ]);

        $this->assertSame(['003', '002', '001'], $export->staffIds());
        $this->assertSame(['1', '2', '3'], array_column($export->staffRows(), 0));
        $this->assertSame(['1', '2', '1'], array_column($export->staffRows(), 1));
        $this->assertCount(2, $export->groupRows());
        $this->assertCount(2, $export->sheets());
    }

    private function employee(string $id, ?int $rank, string $grade): Fluent
    {
        return new Fluent([
            'official_id_10' => $id,
            'full_name' => 'Staff ' . $id,
            'department_id' => 1,
            'position' => new Fluent(['position_rank' => $rank, 'position_name' => 'Position ' . $rank]),
            'employee_grade' => $grade,
        ]);
    }

    private function report(array $employees): StaffReportFixture
    {
        return new StaffReportFixture(new Collection($employees));
    }
}

class StaffReportFixture extends EmployeeStructuredReportExport
{
    public function __construct(Collection $employees)
    {
        // Isolate workbook generation from the database with configured master-data fixtures.
        $this->payGradeSortOrderCache = [];
        foreach ([['A.1-1', 'ក.១-១', 1], ['A.1-2', 'ក.១-២', 2], ['A.1-3', 'ក.១-៣', 8], ['B.1-1', 'ខ.១-១', 36]] as [$code, $name, $order]) {
            $this->payGradeSortOrderCache[$this->normalizePayGradeKey($code)] = $order;
            $this->payGradeSortOrderCache[$this->normalizePayGradeKey($name)] = $order;
        }
        foreach ([1, 2] as $id) {
            $this->unitSegmentCache[$id] = [[
                'id' => $id,
                'name' => 'Unit ' . $id,
                'depth' => 0,
                'path_code' => (string) $id,
                'path_key' => 'unit-' . $id,
            ]];
        }

        parent::__construct($employees);
    }

    public function staffRows(): array
    {
        return array_map(
            fn (int $row): array => $this->prepared['list']['rows'][$row - 1],
            $this->prepared['list']['employee_rows']
        );
    }

    public function staffIds(): array
    {
        return array_column($this->staffRows(), 2);
    }

    public function groupRows(): array
    {
        return $this->prepared['list']['group_rows'];
    }
}
