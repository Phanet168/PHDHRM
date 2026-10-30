<?php

namespace Tests\Unit;

use Modules\Report\Services\Hr\HrIndicatorLinker;
use Modules\Report\Services\Hr\HrStatistics;
use PHPUnit\Framework\TestCase;

class HrStatisticsTest extends TestCase
{
    public function test_skill_spellings_map_to_one_profession(): void
    {
        $cases = [
            'គិ.មធ្យម' => 'nurse_secondary', 'គិបឋម' => 'nurse_primary', 'គិ.បឋម' => 'nurse_primary',
            'បរិ.បត្រឆ្មប' => 'midwife_bachelor', 'ឆ្មបបរិញ្ញាបត្រ' => 'midwife_bachelor', 'ឆ្មបមធ្យម' => 'midwife_secondary',
            'វេជ្ជបណ្ខិតឯកទេស' => 'md_specialist', 'វេជ្ជបណ្ឌិត' => 'md', 'ទន្តគិលានុបដ្ឋាក' => 'dental_nurse',
            'ឱសថការីមធ្យម' => 'pharm_secondary', 'ឱសថការី' => 'pharmacist', 'ពិ.មធ្យម' => 'lab_secondary',
            'ពត៌មានវិទ្យា' => 'it', 'បរិ.គណនេយ្យ' => 'accounting', 'បចេ្ចក.រដ្ឋបាល' => 'other', 'ព្យាបាលដោយចលនាមធ្យម' => 'physio',
            'គិលានុបដ្ឋាកឯកទេស' => null,
        ];
        foreach ($cases as $text => $expected) {
            $this->assertSame($expected, HrStatistics::guessProfession($text), $text);
        }
    }

    public function test_employment_from_type_name(): void
    {
        $this->assertSame('civil', HrStatistics::employment('បុគ្គលិកក្របខណ្ឌរដ្ឋ'));
        $this->assertSame('contract', HrStatistics::employment('បុគ្គលិកកិច្ចសន្យា'));
        $this->assertSame('agreement', HrStatistics::employment('កិច្ចព្រមព្រៀង'));
        $this->assertSame('other', HrStatistics::employment('បុគ្គលិកហាត់ការ'));
        $this->assertNull(HrStatistics::employment(null));
    }

    public function test_indicator_names_are_read_into_links(): void
    {
        $professions = collect([HrIndicatorLinker::norm('វេជ្ជបណ្ឌិត') => 5, HrIndicatorLinker::norm('សាស្រ្តាចារ្យមហាបរិញ្ញា') => 1]);
        $parse = fn ($name) => HrIndicatorLinker::parse($name, $professions);

        $this->assertSame(['hrm_measure' => 'headcount', 'hrm_profession_id' => 5, 'hrm_employment' => 'civil', 'hrm_sex' => 'F', 'hrm_workplace' => null], $parse('វេជ្ជបណ្ឌិត(ស្រី)'));
        $this->assertSame('contract_agreement', $parse('វេជ្ជបណ្ឌិត(កិច្ចសន្យាកិច្ចព្រមព្រៀង) ប្រុស')['hrm_employment']);
        $this->assertSame('M', $parse('វេជ្ជបណ្ឌិត(កិច្ចសន្យាកិច្ចព្រមព្រៀង) ប្រុស')['hrm_sex']);
        $this->assertSame(1, $parse('សាស្ត្រាចារ្យមហាបរិញ្ញា')['hrm_profession_id'], 'ស្រ្ត / ស្ត្រ spellings');

        $hc = $parse("ចំនួនបុគ្គលិក\u{200B}សរុបនៅមណ្ឌលសុខភាព ប្រុស");
        $this->assertSame(['headcount', 'hc', 'M', null], [$hc['hrm_measure'], $hc['hrm_workplace'], $hc['hrm_sex'], $hc['hrm_employment']]);
        $this->assertSame('agreement', $parse('ចំនួនបុគ្គ កិច្ចព្រមព្រៀង ស្រី')['hrm_employment']);
        $this->assertNull($parse('ចំនួនបុគ្គលិកសរុប')['hrm_sex']);

        $this->assertSame('death', $parse('មន្រ្តីទទួលមរណៈភាព ស្រី')['hrm_measure']);
        $this->assertSame('retire', $parse('ចូលនិវត្តន៍ ប្រុស')['hrm_measure']);
        $this->assertSame('transfer_out_ministry', $parse('ផ្ទេរចេញក្រៅក្រសួង')['hrm_measure']);
        $this->assertSame('promotion', $parse("តែងតាំងឡើងថ្នាក់\u{200B} ប្រុស")['hrm_measure']);
        $this->assertNull($parse('ផ្តល់គ្រឿងឥស្សរយស'));
    }
}
