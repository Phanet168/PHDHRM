<?php

namespace Modules\Report\Http\Controllers\Health;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Modules\Report\Entities\ReportAgeGroup;
use Modules\Report\Entities\ReportCategoryOption;
use Modules\Report\Entities\ReportDataElement;
use Modules\Report\Entities\ReportDepartment;
use Modules\Report\Entities\ReportDiagnosis;
use Modules\Report\Entities\ReportFact;
use Modules\Report\Entities\ReportImportMapping;
use Modules\Report\Entities\ReportIndicator;
use Modules\Report\Entities\ReportPeriod;
use Modules\Report\Entities\ReportUnit;
use Modules\Report\Services\CodeGenerator;
use Modules\Report\Services\ExcelImportService;
use Modules\Report\Services\Hr\HrStatistics;
use Modules\Report\Services\MasterDataImporter;
use Modules\Report\Services\PeriodCode;
use Throwable;

/** ការកំណត់ — indicators, periods, Excel import mappings and PHDReport master-data import. */
class SettingController extends HealthController
{
    public const BUNDLED_INDICATORS = 'Database/Seeders/data/phdreport-indicators.json';

    public function index(Request $request, string $tab = 'indicators', array $extra = [])
    {
        $view = ['tab' => $tab];

        if ($tab === 'indicators') {
            $q = trim((string) $request->input('q'));
            $view['indicators'] = ReportIndicator::with(['unit', 'department', 'owner:id,full_name', 'hisElement', 'hisAgeGroup', 'hisDiagnosis', 'hisOption', 'hrmProfession'])
                ->when($request->filled('department'), fn ($b) => $b->where('department_id', (int) $request->input('department')))
                ->when($request->filled('unit'), fn ($b) => $b->where('unit_id', (int) $request->input('unit')))
                ->when($request->input('active') !== null && $request->input('active') !== '', fn ($b) => $b->where('active', $request->boolean('active')))
                ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('code', $q)->orWhere('indicator_type', 'like', "%{$q}%")))
                ->ordered()->paginate(50)->withQueryString();
            $view['departments'] = ReportDepartment::with('units')->orderBy('sort_order')->orderBy('id')->get();
            $view['users'] = User::where('is_active', 1)->orderBy('full_name')->get(['id', 'full_name']);
            $view['types'] = ReportIndicator::whereNotNull('indicator_type')->distinct()->orderBy('indicator_type')->pluck('indicator_type');
            $view['nextCode'] = CodeGenerator::next('report_indicators');
            $view['his'] = [
                'ages' => ReportAgeGroup::orderBy('sort_order')->orderBy('id')->get(['id', 'label']),
                'diagnoses' => ReportDiagnosis::orderBy('icd10')->get(['id', 'icd10', 'name']),
                'options' => ReportCategoryOption::orderBy('category')->orderBy('id')->get(['id', 'category', 'label']),
            ];
            $view['professions'] = \Modules\Report\Entities\ReportHrProfession::orderBy('sort_order')->get(['id', 'name']);
        } elseif ($tab === 'periods') {
            $year = (int) $request->input('year', now()->year);
            $view['year'] = $year;
            $view['years'] = $this->years();
            $view['periods'] = ReportPeriod::where('year', $year)->withCount('values')->get()
                ->sortBy(fn ($p) => PeriodCode::order($p->tag))->values();
        } elseif ($tab === 'mappings') {
            $q = trim((string) $request->input('q'));
            $view['mappings'] = ReportImportMapping::with('indicator.unit')
                ->join('report_indicators as i', 'i.id', '=', 'report_import_mappings.indicator_id')
                ->select('report_import_mappings.*')
                ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('i.name', 'like', "%{$q}%")->orWhere('i.code', $q)))
                ->orderByRaw('LENGTH(i.code)')->orderBy('i.code')->get();
            $view['unmapped'] = ReportIndicator::where('active', true)->whereDoesntHave('mapping')->ordered()->get(['id', 'code', 'name']);
            $view['test'] = $extra['test'] ?? null;
        } else {
            $view['counts'] = [
                'departments' => ReportDepartment::count(),
                'units' => ReportUnit::count(),
                'indicators' => ReportIndicator::count(),
                'mappings' => ReportImportMapping::count(),
            ];
            $view['bundled'] = is_file(module_path('Report', self::BUNDLED_INDICATORS));
        }

        return view('report::health.settings', $view);
    }

    public function saveIndicator(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:report_indicators,id'],
            'code' => ['nullable', 'string', 'max:64', Rule::unique('report_indicators', 'code')->ignore($request->input('id'))],
            'name' => ['required', 'string', 'max:2000'],
            'indicator_type' => ['nullable', 'string', 'max:255'],
            'unit_id' => ['required', 'integer', 'exists:report_units,id'],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'direction' => ['required', Rule::in(array_keys(ReportIndicator::DIRECTIONS))],
            'aggregation' => ['required', Rule::in(array_keys(ReportIndicator::AGGREGATIONS))],
            'his_element_id' => ['nullable', 'integer', 'exists:report_data_elements,id'],
            'his_sex' => ['nullable', Rule::in(array_keys(ReportFact::SEXES))],
            'his_age_group_id' => ['nullable', 'integer', 'exists:report_age_groups,id'],
            'his_diagnosis_id' => ['nullable', 'integer', 'exists:report_diagnoses,id'],
            'his_option_id' => ['nullable', 'integer', 'exists:report_category_options,id'],
            'hrm_measure' => ['nullable', Rule::in(array_keys(HrStatistics::MEASURES))],
            'hrm_sex' => ['nullable', Rule::in(array_keys(ReportFact::SEXES))],
            'hrm_profession_id' => ['nullable', 'integer', 'exists:report_hr_professions,id'],
            'hrm_employment' => ['nullable', Rule::in(array_keys(HrStatistics::EMPLOYMENT_FILTERS))],
            'hrm_workplace' => ['nullable', Rule::in(array_keys(HrStatistics::WORKPLACES))],
        ], [
            'hrm_*.in' => 'ការកំណត់ HRM មិនត្រឹមត្រូវ',
            'hrm_profession_id.exists' => 'រកមិនឃើញជំនាញដែលបានជ្រើស',
            'code.unique' => 'Indicator ID នេះមានរួចហើយ — ទុកទទេដើម្បីបង្កើតដោយស្វ័យប្រវត្តិ',
            'his_element_id.exists' => 'រកមិនឃើញធាតុទិន្នន័យ HIS ដែលបានជ្រើស',
            'his_*.exists' => 'ការកំណត់ HIS (អាយុ / រោគវិនិច្ឆ័យ / ជម្រើស) មិនត្រឹមត្រូវ',
            'his_sex.in' => 'ភេទមិនត្រឹមត្រូវ',
        ]);
        $element = $data['his_element_id'] ?? null;
        $unit = ReportUnit::findOrFail($data['unit_id']);
        ReportIndicator::updateOrCreate(['id' => $data['id'] ?? null], [
            'code' => trim((string) ($data['code'] ?? '')) ?: CodeGenerator::next('report_indicators'),
            'name' => trim($data['name']),
            'indicator_type' => $data['indicator_type'] ?? null,
            'direction' => $data['direction'],
            'aggregation' => $data['aggregation'],
            'department_id' => $unit->department_id,
            'unit_id' => $unit->id,
            'owner_user_id' => $data['owner_user_id'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'active' => $request->boolean('active'),
            // the narrowing filters only mean something with an element
            'his_element_id' => $element,
            'his_sex' => $element ? ($data['his_sex'] ?? null) : null,
            'his_age_group_id' => $element ? ($data['his_age_group_id'] ?? null) : null,
            'his_diagnosis_id' => $element ? ($data['his_diagnosis_id'] ?? null) : null,
            'his_option_id' => $element ? ($data['his_option_id'] ?? null) : null,
            'hrm_measure' => $measure = $data['hrm_measure'] ?? null,
            'hrm_sex' => $measure ? ($data['hrm_sex'] ?? null) : null,
            'hrm_profession_id' => $measure ? ($data['hrm_profession_id'] ?? null) : null,
            'hrm_employment' => $measure ? ($data['hrm_employment'] ?? null) : null,
            'hrm_workplace' => $measure ? ($data['hrm_workplace'] ?? null) : null,
        ]);

        return back()->with('success', 'បានរក្សាទុកសូចនាករ');
    }

    /**
     * HIS data elements for the indicator form: a name / code search (q), or — with only "name" — the elements
     * whose names are closest to an indicator name, plus the sex the name implies.
     */
    public function hisElements(Request $request)
    {
        $q = trim((string) $request->input('q'));
        $base = ReportDataElement::query()->where('active', true)
            ->select(['id', 'code', 'name', 'section'])
            ->withCount('facts');

        if ($q !== '') {
            $items = $base->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('code', $q)->orWhere('section', 'like', "%{$q}%"))
                ->orderByDesc('facts_count')->limit(30)->get();

            return response()->json(['items' => $items]);
        }

        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return response()->json(['items' => []]);
        }
        $sex = str_contains($name, 'ស្រី') ? 'F' : (str_contains($name, 'ប្រុស') ? 'M' : null);
        $key = self::nameKey($name);
        $items = $base->get()
            ->map(function ($e) use ($key) {
                similar_text($key, self::nameKey($e->name . ' ' . $e->section), $pct);
                $e->score = round($pct);

                return $e;
            })
            ->filter(fn ($e) => $e->score >= 40)
            ->sortByDesc(fn ($e) => [$e->score, $e->facts_count])
            ->take(8)->values();

        return response()->json(['items' => $items, 'sex' => $sex]);
    }

    /** Indicator / element name reduced to its content words for similarity ranking. */
    private static function nameKey(string $name): string
    {
        $name = preg_replace('/ចំនួន|សរុប|ប្រុស|ស្រី|ករណី|អ្នកជំងឺ|[›\s\p{P}0-9០-៩]+/u', '', $name);

        return mb_strtolower((string) $name);
    }

    /**
     * Suggest direction / aggregation from indicator names, only for indicators not yet configured
     * (aggregation still "none"). Everything stays editable afterwards.
     */
    public function suggestIndicatorSettings()
    {
        $counts = ['avg' => 0, 'last' => 0, 'sum' => 0, 'down' => 0];
        foreach (ReportIndicator::where('aggregation', 'none')->get() as $indicator) {
            $name = $indicator->name;
            $aggregation = match (true) {
                (bool) preg_match('/អត្រា|ភាគរយ|%|ជាមធ្យម|ក្នុងមួយ|ដោយប្រជាជន|សមាមាត្រ/u', $name) => 'avg',
                // stock-type counts (beds, facilities, staff by category) keep the latest month
                (bool) preg_match('/គ្រែ|បុគ្គលិក|មន្ត្រី|គិលានុបដ្ឋាក|ឆ្មប|វេជ្ជបណ្ឌិត|ឱសថការី|មូលដ្ឋានសុខាភិបាល|មណ្ឌលសុខភាព$|មន្ទីរពេទ្យ$|ប៉ុស្តិ៍|រថយន្ត|ឧបករណ៍/u', $name) => 'last',
                (bool) preg_match('/^ចំនួន|សរុប/u', $name) => 'sum',
                default => 'none',
            };
            $direction = preg_match('/ស្លាប់|មរណភាព|ដាច់ស្តុក|កង្វះ|ខ្វះ|បោះបង់|ផុតកំណត់|ឆ្លងពីម្តាយ/u', $name) ? 'down' : $indicator->direction;
            if ($aggregation !== 'none') {
                $counts[$aggregation]++;
            }
            if ($direction === 'down' && $indicator->direction !== 'down') {
                $counts['down']++;
            }
            $indicator->update(['aggregation' => $aggregation, 'direction' => $direction]);
        }

        return back()->with('success', sprintf(
            'បានណែនាំ៖ បូកខែ %s • មធ្យមខែ %s • ខែចុងក្រោយ %s • ទាបល្អ %s — សូមពិនិត្យ និងកែតាមការពិត',
            PeriodCode::kh($counts['sum']), PeriodCode::kh($counts['avg']), PeriodCode::kh($counts['last']), PeriodCode::kh($counts['down'])
        ));
    }

    public function destroyIndicator(ReportIndicator $indicator)
    {
        if ($indicator->values()->exists()) {
            $indicator->update(['active' => false]);

            return back()->with('success', 'សូចនាករមានទិន្នន័យរួចហើយ ដូច្នេះត្រូវបានបិទ (inactive) ជំនួសការលុប');
        }
        $indicator->delete();

        return back()->with('success', 'បានលុបសូចនាករ');
    }

    /** Create every month / quarter / semester / nine-month / year period of a year. */
    public function generatePeriods(Request $request)
    {
        $year = (int) $request->validate(['year' => ['required', 'integer', 'between:2000,2100']])['year'];
        foreach (PeriodCode::allTags() as $tag) {
            ReportPeriod::forCode(PeriodCode::make($year, $tag));
        }

        return redirect()->route('report.health.settings', ['tab' => 'periods', 'year' => $year])
            ->with('success', 'បានបង្កើតរយៈពេលសម្រាប់ឆ្នាំ ' . PeriodCode::kh($year));
    }

    public function updatePeriod(Request $request, ReportPeriod $period)
    {
        $data = $request->validate(['name' => ['nullable', 'string', 'max:255']]);
        $period->update(['name' => $data['name'] ?: null, 'locked' => $request->boolean('locked')]);

        return back()->with('success', 'បានរក្សាទុក ' . $period->label);
    }

    /** Save the whole mapping table (rows keyed by indicator id); also used to add a new indicator row. */
    public function saveMappings(Request $request)
    {
        $data = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.hc_sheet' => ['nullable', 'string', 'max:255'],
            'rows.*.hc_cell' => ['nullable', 'string', 'max:32'],
            'rows.*.hc_formula' => ['nullable', 'string', 'max:2000'],
            'rows.*.hosp_sheet' => ['nullable', 'string', 'max:255'],
            'rows.*.hosp_cell' => ['nullable', 'string', 'max:32'],
            'rows.*.hosp_formula' => ['nullable', 'string', 'max:2000'],
            'rows.*.result_formula' => ['nullable', 'string', 'max:2000'],
        ]);
        $known = ReportIndicator::whereIn('id', array_keys($data['rows']))->pluck('id')->all();

        DB::transaction(function () use ($data, $known) {
            foreach ($data['rows'] as $indicatorId => $row) {
                if (!in_array((int) $indicatorId, $known, true)) {
                    continue;
                }
                $fields = ['hc_sheet', 'hc_cell', 'hc_formula', 'hosp_sheet', 'hosp_cell', 'hosp_formula', 'result_formula'];
                $payload = [];
                foreach ($fields as $f) {
                    $v = trim((string) ($row[$f] ?? ''));
                    $payload[$f] = $v === '' ? null : (str_ends_with($f, '_cell') ? strtoupper($v) : $v);
                }
                $payload['active'] = !empty($row['active']);
                ReportImportMapping::updateOrCreate(['indicator_id' => (int) $indicatorId], $payload);
            }
        });

        return back()->with('success', 'បានរក្សាទុក Mapping');
    }

    public function destroyMapping(ReportImportMapping $mapping)
    {
        $mapping->delete();

        return back()->with('success', 'បានលុប Mapping');
    }

    /** Run the saved mappings against sample HC / HOSP workbooks without importing anything. */
    public function testMappings(Request $request, ExcelImportService $import)
    {
        $request->validate([
            'file_hc' => array_merge($this->excelRule(false), ['required_without:file_hosp']),
            'file_hosp' => $this->excelRule(false),
        ], ['file_hc.required_without' => 'សូមជ្រើសឯកសារ HC ឬ HOSP យ៉ាងហោចណាស់មួយ']);
        try {
            $result = $import->previewMapping($request->file('file_hc')?->getRealPath(), $request->file('file_hosp')?->getRealPath());
        } catch (Throwable $e) {
            return back()->withErrors(['file' => 'អាន Excel បរាជ័យ: ' . $e->getMessage()]);
        }

        return $this->index($request, 'mappings', ['test' => [
            'rows' => collect($result['rows'])->keyBy('indicator_id'),
            'warnings' => $result['warnings'],
        ]]);
    }

    public function importMaster(Request $request, MasterDataImporter $importer)
    {
        $request->validate([
            'source' => ['required', 'in:upload,bundled'],
            'file' => ['required_if:source,upload', 'nullable', 'file', 'max:20480'],
        ]);
        $json = $request->input('source') === 'bundled'
            ? file_get_contents(module_path('Report', self::BUNDLED_INDICATORS))
            : file_get_contents($request->file('file')->getRealPath());

        try {
            $counts = $importer->import(MasterDataImporter::decode($json));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }
        $summary = collect($counts)->map(fn ($n, $k) => "{$k}: " . PeriodCode::kh($n))->implode(' • ');

        return back()->with('success', 'នាំចូលរួចរាល់ — ' . ($summary ?: 'គ្មានទិន្នន័យ'));
    }
}
