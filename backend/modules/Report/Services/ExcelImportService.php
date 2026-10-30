<?php

namespace Modules\Report\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Report\Entities\ReportImportMapping;
use Modules\Report\Entities\ReportIndicator;
use Modules\Report\Entities\ReportPeriod;
use Modules\Report\Entities\ReportValue;
use Throwable;

/**
 * Import Excel (port of PHDReport import-excel):
 *  - mapping mode: an HC workbook and a HOSP workbook, read through each indicator's import mapping
 *  - summary mode: one sheet with columns indicator_id | name | hc | hosp | total [| target]
 * Preview first, then commit the previewed rows into report_values for the chosen period.
 */
class ExcelImportService
{
    public const SUMMARY_COLUMNS = ['indicator_id', 'name', 'unit', 'hc', 'hosp', 'total', 'target'];

    /** @return array{rows: array<int, array>, warnings: string[]} */
    public function previewMapping(?string $hcPath, ?string $hospPath): array
    {
        // Either workbook may be missing (HIS exports often arrive separately); that side then reads as empty.
        $hc = $hcPath ? WorkbookReader::open($hcPath) : null;
        $hosp = $hospPath ? WorkbookReader::open($hospPath) : null;
        $formula = new MappingFormula(fn (string $source, string $ref) => ($source === 'HC' ? $hc : $hosp)?->ref($ref));

        $warnings = [];
        $rows = [];
        $mappings = ReportImportMapping::with('indicator.unit')->where('active', true)->get();
        foreach ($mappings as $map) {
            $indicator = $map->indicator;
            if (!$indicator) {
                continue;
            }
            $notes = [];
            try {
                $hcVal = $this->side($map->hc_formula, $map->hc_sheet, $map->hc_cell, $hc, $formula, 'HC', $notes);
                $hospVal = $this->side($map->hosp_formula, $map->hosp_sheet, $map->hosp_cell, $hosp, $formula, 'HOSP', $notes);
                $total = $formula->evaluate($map->result_formula, $hcVal, $hospVal);
            } catch (InvalidArgumentException $e) {
                $hcVal = $hospVal = $total = null;
                $notes[] = 'រូបមន្តខុស: ' . $e->getMessage();
            }
            foreach ($notes as $note) {
                $warnings[] = "{$indicator->code}: {$note}";
            }
            $rows[] = $this->row($indicator, $hcVal, $hospVal, $total, null, $notes);
        }

        return ['rows' => $this->sorted($rows), 'warnings' => $warnings];
    }

    /** @return array{rows: array<int, array>, warnings: string[]} */
    public function previewSummary(string $path): array
    {
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $sheet = $book->getSheet(0);
        $grid = $sheet->toArray(null, true, false, false);
        if (!$grid) {
            throw new InvalidArgumentException('Sheet ទទេ');
        }

        $header = array_map(fn ($h) => mb_strtolower(trim((string) $h)), array_shift($grid));
        $col = array_flip($header);
        if (!isset($col['indicator_id'])) {
            throw new InvalidArgumentException('Header ត្រូវមានជួរឈរ "indicator_id"');
        }

        // Current codes first; old PHDReport ids (source_id, e.g. UUIDs) still resolve for older files.
        $indicators = ReportIndicator::with('unit')->get();
        // union() keeps keys (merge() would renumber numeric codes); current codes win over old ids
        $byCode = $indicators->keyBy(fn ($i) => (string) $i->code)
            ->union($indicators->filter(fn ($i) => $i->source_id)->keyBy(fn ($i) => (string) $i->source_id));
        $rows = [];
        $warnings = [];
        foreach ($grid as $line) {
            $code = trim((string) ($line[$col['indicator_id']] ?? ''));
            if ($code === '') {
                continue;
            }
            $cell = fn (string $key) => isset($col[$key]) ? MappingFormula::toNumber($line[$col[$key]] ?? null) : null;
            $indicator = $byCode->get($code);
            if (!$indicator) {
                $warnings[] = "{$code}: មិនមានសូចនាករនេះ";
                $rows[] = [
                    'indicator_id' => null, 'code' => $code, 'name' => (string) ($line[$col['name'] ?? -1] ?? ''),
                    'unit' => '', 'unit_id' => null, 'hc' => $cell('hc'), 'hosp' => $cell('hosp'),
                    'total' => $cell('total'), 'target' => $cell('target'), 'notes' => ['មិនមាន ID'],
                ];
                continue;
            }
            $hcVal = $cell('hc');
            $hospVal = $cell('hosp');
            $total = $cell('total') ?? (($hcVal === null && $hospVal === null) ? null : ($hcVal ?? 0) + ($hospVal ?? 0));
            $rows[] = $this->row($indicator, $hcVal, $hospVal, $total, $cell('target'), []);
        }

        return ['rows' => $this->sorted($rows), 'warnings' => $warnings];
    }

    /**
     * Upsert previewed rows. Rows outside the user's editable units are skipped.
     *
     * @return array{saved:int, skipped:int, errors:string[]}
     */
    public function commit(array $rows, ReportPeriod $period, User $user, ReportAccess $access): array
    {
        $editable = $access->editableUnitIds($user);
        $saved = 0;
        $skipped = 0;
        $errors = [];

        DB::transaction(function () use ($rows, $period, $user, $editable, &$saved, &$skipped, &$errors) {
            foreach ($rows as $row) {
                if (empty($row['indicator_id']) || empty($row['unit_id'])) {
                    $skipped++;
                    $errors[] = "{$row['code']}: មិនមានសូចនាករ ឬផ្នែក";
                    continue;
                }
                if ($editable !== null && !in_array((int) $row['unit_id'], $editable, true)) {
                    $skipped++;
                    continue;
                }
                // A mapping that found nothing in this file must not blank out a value already stored.
                if ($row['total'] === null && $row['hc'] === null && $row['hosp'] === null && $row['target'] === null) {
                    $skipped++;
                    continue;
                }
                try {
                    $value = ReportValue::firstOrNew(['period_id' => $period->id, 'indicator_id' => $row['indicator_id']]);
                    $value->fill([
                        'unit_id' => $row['unit_id'],
                        'value_hc' => $row['hc'],
                        'value_hosp' => $row['hosp'],
                        'value' => $row['total'],
                        'source' => 'import',
                        'updated_by' => $user->id,
                    ]);
                    if ($row['target'] !== null) {
                        $value->target = $row['target'];
                    }
                    $value->save();
                    $saved++;
                } catch (Throwable $e) {
                    $errors[] = "{$row['code']}: " . $e->getMessage();
                }
            }
        });

        return ['saved' => $saved, 'skipped' => $skipped, 'errors' => $errors];
    }

    private function side(?string $expr, ?string $sheet, ?string $cell, ?WorkbookReader $book, MappingFormula $formula, string $label, array &$notes): ?float
    {
        if (trim((string) $expr) !== '') {
            return $formula->evaluate($expr);
        }
        if (!$sheet || !$cell || !$book) {
            return null;
        }
        if (!$book->hasSheet($sheet)) {
            $notes[] = "{$label} sheet \"{$sheet}\" រកមិនឃើញ";

            return null;
        }

        return $book->value($sheet, $cell);
    }

    private function row(ReportIndicator $indicator, ?float $hc, ?float $hosp, ?float $total, ?float $target, array $notes): array
    {
        if ($total === null && ($hc !== null || $hosp !== null)) {
            $total = ($hc ?? 0) + ($hosp ?? 0);
        }

        return [
            'indicator_id' => $indicator->id,
            'code' => (string) $indicator->code,
            'name' => $indicator->name,
            'unit' => $indicator->unit?->name ?? '',
            'unit_id' => $indicator->unit_id,
            'hc' => $hc,
            'hosp' => $hosp,
            'total' => $total,
            'target' => $target,
            'notes' => $notes,
        ];
    }

    private function sorted(array $rows): array
    {
        usort($rows, fn ($a, $b) => strnatcmp($a['code'], $b['code']));

        return $rows;
    }
}
