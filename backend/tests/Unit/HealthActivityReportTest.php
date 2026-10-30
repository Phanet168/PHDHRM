<?php

namespace Tests\Unit;

use InvalidArgumentException;
use Modules\Report\Entities\ReportIndicator;
use Modules\Report\Services\HealthReportData;
use Modules\Report\Services\MappingFormula;
use Modules\Report\Services\PeriodCode;
use Modules\Report\Services\WorkbookReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\TestCase;

class HealthActivityReportTest extends TestCase
{
    public function test_period_codes_normalise_legacy_phdreport_spellings(): void
    {
        $this->assertSame('2025-M03', PeriodCode::parse('2025-03')['code']);
        $this->assertSame('2025-M03', PeriodCode::parse('2025M3')['code']);
        $this->assertSame('2025-Q2', PeriodCode::parse('2025Q2')['code']);
        $this->assertSame('2025-H1', PeriodCode::parse('2025-H1')['code']);
        $this->assertSame('2025-N9', PeriodCode::parse('2025-N9')['code']);
        $this->assertSame('2025-N9', PeriodCode::parse('2025-9M')['code']);
        $this->assertSame('2025-Y', PeriodCode::parse('2025-Y12')['code']);
        $this->assertSame('year', PeriodCode::parse('2025-Y')['type']);

        $this->expectException(InvalidArgumentException::class);
        PeriodCode::parse('2025-M13');
    }

    public function test_previous_period_rolls_back_across_years(): void
    {
        $this->assertSame('2024-M12', PeriodCode::previous('2025-M01'));
        $this->assertSame('2025-M02', PeriodCode::previous('2025-M03'));
        $this->assertSame('2024-Q4', PeriodCode::previous('2025-Q1'));
        $this->assertSame('2025-H1', PeriodCode::previous('2025-H2'));
        $this->assertSame('2024-H2', PeriodCode::previous('2025-H1'));
        $this->assertSame('2024-Y', PeriodCode::previous('2025-Y'));
        $this->assertSame('2024-N9', PeriodCode::previous('2025-N9'));
    }

    public function test_period_labels_use_khmer(): void
    {
        $this->assertSame('មីនា ២០២៥', PeriodCode::label('2025-M03'));
        $this->assertSame('ត្រីមាសទី២ ២០២៥', PeriodCode::label('2025-Q2'));
        $this->assertSame('ឆ្នាំ ២០២៥', PeriodCode::label('2025-Y'));
        $this->assertCount(20, PeriodCode::allTags());
    }

    public function test_to_number_reads_khmer_digits_separators_percent_and_parentheses(): void
    {
        $this->assertSame(2486.0, MappingFormula::toNumber('២,៤៨៦'));
        $this->assertSame(1234567.89, MappingFormula::toNumber('1.234.567,89'));
        $this->assertSame(1234567.89, MappingFormula::toNumber('1,234,567.89'));
        $this->assertSame(0.92, MappingFormula::toNumber('92%'));
        $this->assertSame(-15.0, MappingFormula::toNumber('(15)'));
        $this->assertSame(12.0, MappingFormula::toNumber('12 នាក់'));
        $this->assertNull(MappingFormula::toNumber(''));
        $this->assertNull(MappingFormula::toNumber('—'));
    }

    public function test_result_formula_defaults_to_hc_plus_hosp(): void
    {
        $f = new MappingFormula();
        $this->assertSame(30.0, $f->evaluate('', 10, 20));
        $this->assertSame(10.0, $f->evaluate(null, 10, null));
        $this->assertNull($f->evaluate('', null, null));
    }

    public function test_formula_language_functions_and_precedence(): void
    {
        $f = new MappingFormula();
        $this->assertSame(25.0, $f->evaluate('ROUND(HC/HOSP*100, 1)', 1, 4));
        $this->assertSame(14.0, $f->evaluate('2 + 3 * 4'));
        $this->assertSame(20.0, $f->evaluate('(2 + 3) * 4'));
        $this->assertSame(6.0, $f->evaluate('SUM(1, 2, 3)'));
        $this->assertSame(2.0, $f->evaluate('AVG(1, 2, 3)'));
        $this->assertSame(-3.0, $f->evaluate('MIN(-3, 4) '));
        $this->assertSame(12.0, $f->evaluate('NUM("12 cases")'));
        $this->assertNull($f->evaluate('HC / HOSP', 5, 0), 'division by zero yields no value');
        $this->assertSame(5.0, $f->evaluate('HC + HOSP', 5, null), 'missing side counts as 0');
    }

    public function test_attainment_respects_indicator_direction(): void
    {
        $this->assertSame(80.0, HealthReportData::percent(80, 100));
        $this->assertSame(125.0, HealthReportData::percent(4, 5, 'down'), 'lower than target is better');
        $this->assertSame(50.0, HealthReportData::percent(10, 5, 'down'));
        $this->assertSame(100.0, HealthReportData::percent(0, 5, 'down'), 'zero deaths meets the target');
        $this->assertNull(HealthReportData::percent(10, 0), 'a zero target means no target');
        $this->assertNull(HealthReportData::percent(null, 5));
        $this->assertSame('0e360a8a…', ReportIndicator::shortCode('0e360a8a-fcd9-4944-9efe-c88106d7f058'));
        $this->assertSame('42', ReportIndicator::shortCode('42'));
    }

    public function test_formula_rejects_code_injection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new MappingFormula())->evaluate('system("dir")');
    }

    public function test_formula_reads_cells_through_the_workbook_reader(): void
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet()->setTitle('OPD');
        $sheet->fromArray([[10, 20], [30, null]], null, 'A1');
        $sheet->setCellValue('C1', '=A1+B1');
        $sheet->mergeCells('D1:E2');
        $sheet->setCellValue('D1', '៧');
        $reader = new WorkbookReader($book);

        $this->assertSame(10.0, $reader->value('opd', 'A1'), 'sheet names match case-insensitively');
        $this->assertSame(60.0, $reader->value('OPD', 'A1:B2'));
        $this->assertSame(30.0, $reader->value('OPD', 'C1'), 'formula cells are calculated');
        $this->assertSame(7.0, $reader->value('OPD', 'E2'), 'merged cells resolve to the top-left cell');
        $this->assertSame(7.0, $reader->value('OPD', 'D1:E2'), 'a merged block is summed once');
        $this->assertNull($reader->value('Missing', 'A1'));

        $f = new MappingFormula(fn ($source, $ref) => $reader->ref($ref));
        $this->assertSame(70.0, $f->evaluate('HC("OPD!A1:B2") + HOSP("OPD!E1") + 3'));
    }
}
