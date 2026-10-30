<?php

namespace Modules\Report\Services\His;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Report\Entities\ReportAgeGroup;
use Modules\Report\Entities\ReportCategoryOption;
use Modules\Report\Entities\ReportDataElement;
use Modules\Report\Entities\ReportDiagnosis;
use Modules\Report\Entities\ReportFact;
use Modules\Report\Entities\ReportImportBatch;
use Modules\Report\Entities\ReportImportTemplate;
use Modules\Report\Entities\ReportPeriod;
use Modules\Report\Services\CodeGenerator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Imports a whole HIS workbook (every active block of its template) into report_facts.
 * Re-importing the same template / facility / period replaces the previous batch.
 */
class HisImportService
{
    private array $elements = [];
    private array $diagnoses = [];
    private array $ages = [];
    private array $options = [];

    public function __construct(private HisExtractor $extractor)
    {
    }

    /** @return array{blocks: array<int, array>, values: int, checks: int} */
    public function preview(string $path, ReportImportTemplate $template): array
    {
        $book = $this->load($path);
        $blocks = [];
        $values = 0;
        $checks = 0;
        foreach ($this->extractAll($book, $template) as $code => $result) {
            $elements = [];
            $diagnoses = [];
            foreach ($result['values'] as $v) {
                $elements[implode(' › ', $v['element'])] = true;
                if ($v['diagnosis']) {
                    $diagnoses[$v['diagnosis']['icd10']] = true;
                }
            }
            $blocks[] = [
                'code' => $code,
                'title' => $result['block']->title,
                'sheet' => $result['block']->sheet,
                'values' => count($result['values']),
                'elements' => count($elements),
                'diagnoses' => count($diagnoses),
                'sum' => array_sum(array_column($result['values'], 'value')),
                'checks' => array_slice($result['checks'], 0, 20),
                'checks_count' => count($result['checks']),
                'error' => $result['error'],
            ];
            $values += count($result['values']);
            $checks += count($result['checks']);
        }

        return ['blocks' => $blocks, 'values' => $values, 'checks' => $checks];
    }

    /** @return ReportImportBatch the new batch (previous batch for the same facility / period is replaced) */
    public function commit(string $path, string $fileName, ReportImportTemplate $template, int $facilityId, ReportPeriod $period, ?User $user): ReportImportBatch
    {
        $book = $this->load($path);
        $results = $this->extractAll($book, $template);
        $this->warmCaches();

        return DB::transaction(function () use ($results, $template, $facilityId, $period, $user, $path, $fileName) {
            ReportImportBatch::where('template_id', $template->id)->where('facility_id', $facilityId)
                ->where('period_id', $period->id)->get()->each->delete(); // facts cascade

            $batch = ReportImportBatch::create([
                'template_id' => $template->id,
                'facility_id' => $facilityId,
                'period_id' => $period->id,
                'file_name' => $fileName,
                'file_hash' => hash_file('sha256', $path),
                'created_by' => $user?->id,
            ]);

            $now = now();
            $rows = [];
            $summary = [];
            foreach ($results as $code => $result) {
                foreach ($result['values'] as $v) {
                    $elementId = $this->element($v['element'], $result['block']->title);
                    $diagnosisId = $v['diagnosis'] ? $this->diagnosis($v['diagnosis']) : null;
                    $ageId = $v['age'] ? $this->age($v['age']) : null;
                    $optionId = $v['option'] ? $this->option($v['option']) : null;
                    $dimKey = ReportFact::dimKey($diagnosisId, $ageId, $v['sex'], $optionId);
                    // the same meaning twice in one form (an ICD-10 code listed in two sections): add them up
                    $key = "{$elementId}|{$dimKey}";
                    if (isset($rows[$key])) {
                        $rows[$key]['value'] += $v['value'];
                        $rows[$key]['source_cell'] = mb_substr($rows[$key]['source_cell'] . '+' . $v['cell'], 0, 64);
                        continue;
                    }
                    $rows[$key] = [
                        'period_id' => $period->id,
                        'facility_id' => $facilityId,
                        'data_element_id' => $elementId,
                        'diagnosis_id' => $diagnosisId,
                        'age_group_id' => $ageId,
                        'sex' => $v['sex'],
                        'option_id' => $optionId,
                        'dim_key' => $dimKey,
                        'value' => $v['value'],
                        'batch_id' => $batch->id,
                        'source_cell' => mb_substr($v['cell'], 0, 64),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                $summary[$code] = ['values' => count($result['values']), 'checks' => count($result['checks'])];
            }
            // Facts imported from another batch for the same keys (e.g. a different template) are replaced.
            foreach (array_chunk($rows, 1000) as $chunk) {
                DB::table('report_facts')->upsert(
                    array_values($chunk),
                    ['period_id', 'facility_id', 'data_element_id', 'dim_key'],
                    ['value', 'batch_id', 'source_cell', 'diagnosis_id', 'age_group_id', 'sex', 'option_id', 'updated_at']
                );
            }

            $batch->update(['facts_count' => count($rows), 'summary' => $summary]);

            return $batch;
        });
    }

    /** @return array<string, array{block: \Modules\Report\Entities\ReportImportBlock, values: array, checks: array, error: ?string}> */
    private function extractAll(Spreadsheet $book, ReportImportTemplate $template): array
    {
        $out = [];
        foreach ($template->blocks()->where('active', true)->get() as $block) {
            $ws = $book->getSheetByName($block->sheet);
            if (!$ws) {
                $out[$block->code] = ['block' => $block, 'values' => [], 'checks' => [], 'error' => "រកមិនឃើញ sheet «{$block->sheet}»"];
                continue;
            }
            $result = $this->extractor->extract($ws, $block->only([
                'code', 'title', 'header_rows', 'row_start', 'row_end', 'label_cols', 'code_col', 'kind', 'measure', 'first_col',
            ]));
            $out[$block->code] = ['block' => $block] + $result + ['error' => null];
        }

        return $out;
    }

    private function load(string $path): Spreadsheet
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(false); // merge ranges are needed to read headers

        return $reader->load($path);
    }

    private function warmCaches(): void
    {
        $this->elements = ReportDataElement::pluck('id', 'uid')->all();
        $this->diagnoses = ReportDiagnosis::pluck('id', 'icd10')->mapWithKeys(fn ($id, $code) => [self::icd10($code) => $id])->all();
        $this->ages = ReportAgeGroup::pluck('id', 'code')->all();
        $this->options = ReportCategoryOption::get()->mapWithKeys(fn ($o) => ["{$o->category}|{$o->code}" => $o->id])->all();
    }

    /** Data elements are shared across templates: identity is the normalised name path. */
    private function element(array $parts, string $section): int
    {
        $uid = sha1(implode('|', array_map(fn ($p) => str_replace([' ', "\u{200B}"], '', HisExtractor::canonical($p)), $parts)));
        if (!isset($this->elements[$uid])) {
            $name = implode(' › ', $parts);
            $this->elements[$uid] = ReportDataElement::create([
                'uid' => $uid,
                'code' => CodeGenerator::next('report_data_elements'),
                'name' => $name,
                'section' => $section,
                'value_type' => preg_match('/អត្រា|ភាគរយ|%/u', $name) ? 'percent' : 'count',
                'aggregation' => preg_match('/អត្រា|ភាគរយ|%|មធ្យម/u', $name) ? 'avg' : 'sum',
            ])->id;
        }

        return $this->elements[$uid];
    }

    private function diagnosis(array $d): int
    {
        $code = self::icd10($d['icd10']);
        if (!isset($this->diagnoses[$code])) {
            $this->diagnoses[$code] = ReportDiagnosis::create([
                'icd10' => $code,
                'name' => mb_substr($d['name'] ?: $code, 0, 255),
                'group_name' => $d['group'] ? mb_substr($d['group'], 0, 255) : null,
            ])->id;
        }

        return $this->diagnoses[$code];
    }

    /** "k74.6 ", "K74.6\u{00A0}" → "K74.6" (MySQL compares case- and trailing-space-insensitively). */
    public static function icd10(string $raw): string
    {
        return mb_substr(strtoupper(preg_replace('/[\s\x{00A0}\x{200B}]+/u', '', $raw)), 0, 16);
    }

    private function age(array $a): int
    {
        if (!isset($this->ages[$a['code']])) {
            $this->ages[$a['code']] = ReportAgeGroup::create($a + ['sort_order' => $a['min_days'] ?? 0])->id;
        }

        return $this->ages[$a['code']];
    }

    private function option(array $o): int
    {
        $key = "{$o['category']}|{$o['code']}";
        if (!isset($this->options[$key])) {
            $this->options[$key] = ReportCategoryOption::create($o)->id;
        }

        return $this->options[$key];
    }
}
