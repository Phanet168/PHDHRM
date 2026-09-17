<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Blade;
use Modules\HumanResource\Support\OrgUnitRuleService;
use Modules\HumanResource\Http\Controllers\EmployeeReportTemplateController;
use Tests\TestCase;

class EmployeeReportGeneratorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);
        // A dedicated in-memory connection: never migrate or truncate the application's database.
        config(['database.default' => 'report_testing', 'database.connections.report_testing' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            foreach (['first_name', 'last_name', 'employee_id', 'official_id_10', 'employee_code', 'skill_name', 'employee_grade', 'work_status_name', 'date_of_birth', 'service_start_date', 'joining_date', 'email', 'phone', 'first_name_latin', 'last_name_latin'] as $column) $t->string($column)->nullable();
            foreach (['department_id', 'sub_department_id', 'position_id', 'gender_id'] as $column) $t->integer($column)->nullable();
            $t->boolean('is_active')->default(true);
            $t->softDeletes();
        });
        Schema::create('departments', function (Blueprint $t) {
            $t->id(); $t->integer('unit_type_id'); $t->integer('parent_id')->nullable();
            $t->integer('sort_order')->default(0); $t->string('department_name')->nullable(); $t->softDeletes();
        });
        Schema::create('genders', function (Blueprint $t) { $t->id(); $t->string('gender_name'); $t->softDeletes(); });
        DB::table('genders')->insert([['id' => 1, 'gender_name' => 'Male'], ['id' => 2, 'gender_name' => 'Female']]);
        Carbon::setTestNow('2026-09-11');
        DB::table('departments')->insert([
            ['id' => 1, 'unit_type_id' => 1, 'parent_id' => null, 'department_name' => 'Root'],
            ['id' => 2, 'unit_type_id' => 2, 'parent_id' => 1, 'department_name' => 'Clinic'],
        ]);
        DB::table('employees')->insert([
            ['id' => 1, 'first_name' => 'One', 'last_name' => 'Staff', 'department_id' => 1, 'sub_department_id' => null, 'skill_name' => 'Nurse', 'employee_grade' => 'A', 'gender_id' => 1, 'date_of_birth' => '1996-09-11', 'service_start_date' => null, 'joining_date' => '2021-09-11'],
            ['id' => 2, 'first_name' => 'Two', 'last_name' => 'Staff', 'department_id' => 1, 'sub_department_id' => 2, 'skill_name' => 'Doctor', 'employee_grade' => 'B', 'gender_id' => 2, 'date_of_birth' => '1996-09-12', 'service_start_date' => '2020-09-11', 'joining_date' => '2021-09-11'],
            ['id' => 3, 'first_name' => 'Three', 'last_name' => 'Staff', 'department_id' => 2, 'sub_department_id' => null, 'skill_name' => 'Doctor', 'employee_grade' => 'A', 'gender_id' => 2, 'date_of_birth' => '1995-09-11', 'service_start_date' => null, 'joining_date' => '2021-09-12'],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function ids(array $filters): array
    {
        return (new ReportGeneratorFixture)->queryFor(Request::create('/', 'GET', $filters))->pluck('id')->all();
    }

    public function test_combinations_intersect_while_values_within_a_group_are_alternatives(): void
    {
        $this->assertSame([3], $this->ids(['skill_name' => ['Doctor'], 'employee_grade' => ['A', 'C']]));
        $this->assertSame([1, 3], $this->ids(['skill_name' => ['Doctor', 'Nurse'], 'employee_grade' => ['A']]));
    }

    public function test_age_and_completed_service_years_respect_anniversary_boundaries(): void
    {
        $this->assertSame([1], $this->ids(['age' => 30]));
        $this->assertSame([1], $this->ids(['service_years' => 5]));
        $this->assertSame([2], $this->ids(['service_years' => 6]));
    }

    public function test_place_type_uses_sub_department_when_assigned(): void
    {
        $this->assertSame([1], $this->ids(['unit_type_id' => 1]));
        $this->assertSame([2, 3], $this->ids(['unit_type_id' => 2]));
    }

    public function test_personal_filters_combine_and_match_full_name(): void
    {
        $this->assertSame([2], $this->ids(['employee_name' => 'Staff Two', 'birth_year' => 1996, 'gender_id' => 2]));
    }

    public function test_custom_columns_are_validated_without_requiring_a_saved_template(): void
    {
        $rules = (new ReportGeneratorFixture)->rules();
        $this->assertTrue(Validator::make(['columns' => ['full_name', 'gender']], $rules)->passes());
        $this->assertTrue(Validator::make(['columns' => ['not_a_report_column']], $rules)->fails());
        $this->assertTrue(Validator::make(['columns' => ['full_name', 'full_name']], $rules)->fails());
        $this->assertTrue(Validator::make(['columns' => []], $rules)->fails());
    }

    public function test_generator_route_requires_report_permission(): void
    {
        $route = app('router')->getRoutes()->getByName('reports.employee-report-templates.generate');
        $this->assertNotNull($route);
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('permission:read_employee_report', $route->gatherMiddleware());
    }

    public function test_csv_uses_custom_columns_and_filters_without_a_template(): void
    {
        $request = Request::create('/', 'POST', ['mode' => 'detail', 'format' => 'csv', 'columns' => ['full_name'], 'skill_name' => ['Nurse']]);
        $response = (new ReportGeneratorFixture)->generate($request, new OrgUnitRuleService);
        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();
        $this->assertStringStartsWith("\xEF\xBB\xBFName", $csv);
        $this->assertStringContainsString('Staff One', $csv);
        $this->assertStringNotContainsString('Staff Two', $csv);
    }

    public function test_generator_renders_controls_without_an_employee_results_table(): void
    {
        $source = file_get_contents(module_path('HumanResource', 'Resources/views/reports/employee-report-template-manager.blade.php'));
        $source = preg_replace('/^@extends\([^\n]+\)|^@section\([^\n]+\)|^@endsection|^@push\([^\n]+\)|^@endpush/m', '', $source);
        $source = str_replace(["@include('humanresource::reports_header')", "@include('backend.layouts.common.validation')"], '', $source);
        $html = Blade::render($source, [
            'templates' => collect(), 'editingTemplate' => null, 'selectedTemplate' => null,
            'selectedColumns' => ['full_name'], 'columnOptions' => ['full_name' => 'Name', 'gender' => 'Gender'],
            'columnGroups' => ['identity' => ['label' => 'Identity', 'columns' => ['full_name', 'gender']]],
            'reportPresets' => ['custom' => ['full_name']], 'reportTypeOptions' => ['custom' => 'Custom'],
            'groupByOptions' => ['gender' => 'Gender', 'department' => 'Unit'],
            'departmentTreeOptions' => collect(), 'positions' => collect(), 'genders' => collect(), 'unitTypes' => collect(),
            'combinationOptions' => ['skill_name' => [], 'employee_grade' => [], 'work_status_name' => []],
        ]);
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//form[@id="report-generator-form"]')->length);
        $this->assertSame(0, $xpath->query('//form[@id="report-generator-form"]//table')->length);
        $this->assertSame(3, $xpath->query('//select[@id="report-mode"]/option')->length);
        $this->assertSame(2, $xpath->query('//select[@id="report-layout"]/option')->length);
        $this->assertSame(1, $xpath->query('//button[@value="view"]')->length);
        $this->assertSame(1, $xpath->query('//form[@id="report-generator-form"]//input[@name="columns[]" and @value="full_name" and @checked]')->length);
    }

    public function test_preview_renders_group_headers_and_print_control(): void
    {
        $html = (new ReportGeneratorFixture)->preview(Request::create('/', 'GET', [
            'mode' => 'detail', 'columns' => ['full_name'], 'layout' => 'structured', 'title' => 'Test report',
        ]), new OrgUnitRuleService)->render();
        $this->assertStringContainsString('window.print()', $html);
        $this->assertStringContainsString('Test report', $html);
        $this->assertStringContainsString('Staff One', $html);
        $this->assertStringContainsString('colspan="1"', $html);
    }

    public function test_excel_contains_custom_title_and_structured_group_rows(): void
    {
        $response = (new ReportGeneratorFixture)->generate(Request::create('/', 'POST', [
            'mode' => 'detail', 'format' => 'excel', 'columns' => ['full_name'], 'layout' => 'structured', 'title' => 'Selected staff',
        ]), new OrgUnitRuleService);
        $path = $response->getFile()->getPathname();
        try {
            $workbook = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            $sheet = $workbook->getActiveSheet();
            $this->assertSame('Selected staff', $sheet->getCell('A9')->getValue());
            $this->assertSame('Name', $sheet->getCell('A11')->getValue());
            $this->assertContains('A12:J12', $sheet->getMergeCells());
            $this->assertSame('Staff One', $sheet->getCell('A13')->getValue());
            $workbook->disconnectWorksheets();
        } finally {
            unlink($path);
        }
    }

    public function test_structure_summary_exports_the_same_matrix_to_csv_excel_and_preview(): void
    {
        $filters = ['mode' => 'summary', 'layout' => 'structured', 'group_by' => 'skill_name', 'split_gender' => 1, 'title' => 'Skills by unit'];
        $controller = new ReportGeneratorFixture;
        $service = new OrgUnitRuleService;
        $response = $controller->generate(Request::create('/', 'POST', $filters + ['format' => 'csv']), $service);
        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();
        $lines = array_map('str_getcsv', explode("\n", trim($csv)));
        $this->assertSame(['Doctor', 'Nurse'], array_slice($lines[0], 1, 2));
        $this->assertSame(['Root', '2', '1', '3'], $lines[1]);
        $this->assertSame(['2', '0', '2'], array_slice($lines[2], 1));
        $this->assertSame(['0', '1', '1'], array_slice($lines[3], 1));
        $this->assertSame(['2', '0', '2'], array_slice($lines[4], 1));
        $html = $controller->generate(Request::create('/', 'POST', $filters + ['format' => 'view']), $service)->render();
        $this->assertStringContainsString('unit-total', $html);
        $this->assertStringContainsString('Doctor', $html);
        $this->assertStringNotContainsString('Staff One', $html);
        $excel = $controller->generate(Request::create('/', 'POST', $filters + ['format' => 'excel']), $service);
        $path = $excel->getFile()->getPathname();
        try {
            $workbook = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            $sheet = $workbook->getActiveSheet();
            $this->assertSame(3, $sheet->getCell('D12')->getValue());
            $this->assertSame(2, $sheet->getCell('B13')->getValue());
            $this->assertTrue($sheet->getStyle('A12')->getFont()->getBold());
            $workbook->disconnectWorksheets();
        } finally {
            unlink($path);
        }
    }
}

class ReportGeneratorFixture extends EmployeeReportTemplateController
{
    protected function buildReportQuery(Request $request, ?array $departmentFilterIds = null)
    {
        return parent::buildReportQuery($request, $departmentFilterIds)->setEagerLoads([]);
    }
    public function queryFor(Request $request)
    {
        return $this->buildReportQuery($request)->setEagerLoads([]);
    }

    protected function columnOptions(): array
    {
        return ['full_name' => 'Name', 'gender' => 'Gender', 'department' => 'Unit', 'skill_name' => 'Skill'];
    }

    public function rules(): array
    {
        return $this->generationRules();
    }
}
