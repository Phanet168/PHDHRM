<?php

namespace Tests\Unit;

use Modules\Report\Services\His\AgeLabel;
use Modules\Report\Services\His\HisExtractor;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\TestCase;

class HisExtractorTest extends TestCase
{
    private const BLOCK = [
        'code' => 'OPD', 'title' => 'OPD', 'header_rows' => [1, 2], 'row_start' => 3, 'row_end' => 7,
        'label_cols' => ['B'], 'code_col' => 'C', 'kind' => 'diagnosis', 'measure' => 'ករណីថ្មី', 'first_col' => 'D',
    ];

    /** A miniature HIS morbidity table: two age groups × sex, a total pair, a referral pair and a total row. */
    private function sheet(int $badTotal = 5): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
    {
        $ws = (new Spreadsheet())->getActiveSheet()->setTitle('Page 1');
        $ws->fromArray([
            ['២. បញ្ហាសុខភាព', null, 'ICD10', '0-28 ថ្ងៃ', null, '≥ 65 ឆ្នាំ', null, 'សរុប', null, 'បញ្ញួនទៅ', null],
            [null, null, null, 'ប្រ', 'ស្រ', 'ប្រ ', 'ស្រ', 'ប្រ', 'ស្រ', 'ប្រ', 'ស្រ'],
            ['1. ផ្នែកជំងឺទូទៅ'],
            [1, 'អាសន្នរោគ', 'a00 ', 1, 2, 3, 4, 4, 6, 0, 1],
            [2, 'គ្រុនពោះវៀន', 'A01', 0, 1, 1, 0, 1, 1, 0, 0],
            [3, 'ជំងឺទូទៅផ្សេងៗ', null, 2, 0, 0, 0, 2, 0, 1, 0],
            ['សរុប', null, null, 3, 3, 4, 4, 7, $badTotal + 2, 1, 1],
        ], null, 'A1', true); // strict: keep the zeros, HIS sheets report them
        foreach (['D1:E1', 'F1:G1', 'H1:I1', 'J1:K1'] as $range) {
            $ws->mergeCells($range);
        }

        return $ws;
    }

    public function test_reads_diagnoses_by_age_and_sex_and_skips_totals(): void
    {
        $res = (new HisExtractor())->extract($this->sheet(), self::BLOCK);
        $values = collect($res['values']);

        // 3 rows × (4 age/sex + 2 referral) = 18; the total columns H/I and the total row are not stored
        $this->assertCount(18, $values);
        $this->assertFalse($values->contains(fn ($v) => str_starts_with(substr($v['cell'], 7), 'H') || str_ends_with($v['cell'], '7')));

        $first = $values->firstWhere('cell', 'Page 1!E4');
        $this->assertSame(['ករណីថ្មី'], $first['element']);
        $this->assertSame('a00', $first['diagnosis']['icd10']);
        $this->assertSame('1. ផ្នែកជំងឺទូទៅ', $first['diagnosis']['group']);
        $this->assertSame('0d-28d', $first['age']['code']);
        $this->assertSame('F', $first['sex']);

        $referral = $values->firstWhere('cell', 'Page 1!J4');
        $this->assertSame(['ករណីថ្មី', 'បញ្ជូនទៅ'], $referral['element'], 'typo "បញ្ញួនទៅ" is normalised');
        $this->assertNull($referral['age']);

        $other = $values->firstWhere('cell', 'Page 1!D6');
        $this->assertNull($other['diagnosis'], 'a row without ICD-10 becomes its own data element');
        $this->assertSame(['ករណីថ្មី', '1. ផ្នែកជំងឺទូទៅ', 'ជំងឺទូទៅផ្សេងៗ'], $other['element']);
    }

    public function test_totals_are_checked(): void
    {
        $this->assertSame([], (new HisExtractor())->extract($this->sheet(5), self::BLOCK)['checks']);

        $checks = (new HisExtractor())->extract($this->sheet(9), self::BLOCK)['checks'];
        $this->assertNotEmpty($checks);
        $this->assertSame('I7', $checks[0]['cell'], 'the total row in column A is recognised and compared');
    }

    public function test_age_labels(): void
    {
        $this->assertSame(['0d-28d', 0, 28], $this->age('0-28 ថ្ងៃ'));
        $this->assertSame(['29d-11m', 29, 359], $this->age('29ថ្ងៃ-11ខែ'));
        $this->assertSame(['1y-4y', 365, 1824], $this->age('1-4 ឆ្នាំ'));
        $this->assertSame(['65y+', 23725, null], $this->age('≥ 65 ឆ្នាំ'));
        $this->assertSame(['<20y', 0, 7299], $this->age('< 20ឆ្នាំ'));
        $this->assertSame(['12m-59m', 360, 1799], $this->age('កុមារអាយុពី ១២ខែ - ៥៩ខែ'));
        $this->assertSame(['1y-<2y', 365, 729], $this->age('១ - < ២ ឆ្នាំ'));
        $this->assertSame(['65y+', 23725, null], $this->age("≥\u{200B} 65 ឆ្នាំ"), 'zero-width space');
        $this->assertNull(AgeLabel::parse('ICD10'));
        $this->assertNull(AgeLabel::parse('ពិនិត្យលើកទី១'));
    }

    public function test_dimension_tokens(): void
    {
        $this->assertSame('M', HisExtractor::sex('ប្រ '));
        $this->assertSame('F', HisExtractor::sex('ស្រី'));
        $this->assertNull(HisExtractor::sex('ស្លាប់'));
        $this->assertSame('B', HisExtractor::option('តំបន់ខ')['code']);
        $this->assertSame('OUT', HisExtractor::option('ក្រៅស្រុកប្រតិបត្តិ/ខេត្ត')['code']);
        $this->assertSame('CHILD', HisExtractor::option('កូន')['code']);
        $this->assertTrue(HisExtractor::isTotal('សរុប'));
        $this->assertFalse(HisExtractor::isTotal('ចេញសរុប'), 'only an exact "សរុប" is a total; "ចេញសរុប" is a measure');
        $this->assertSame('ប្រើប្រាស់មូលនិធិសមធម៌', HisExtractor::canonical('ប្រើបា្រស់មូល និធិសមធម៌'));
    }

    private function age(string $label): array
    {
        $a = AgeLabel::parse($label);

        return [$a['code'], $a['min_days'], $a['max_days']];
    }
}
