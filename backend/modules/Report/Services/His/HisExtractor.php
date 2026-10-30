<?php

namespace Modules\Report\Services\His;

use Modules\Report\Services\MappingFormula;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Reads one table of an HIS sheet into dimension-tagged values, driven by the table's own header rows:
 *
 *   header tokens → age group ("0-28 ថ្ងៃ", "≥ 65 ឆ្នាំ", "< 20ឆ្នាំ"), sex (ប្រ/ស្រ/ប្រុស/ស្រី),
 *                   option (តំបន់ក/ខ/គ, ក្នុង/ក្រៅស្រុក, ម្តាយ/កូន), total (សរុប) or a measure word
 *   rows          → an ICD-10 diagnosis (kind "diagnosis") or an item that becomes a data element
 *
 * Total rows / columns are not returned as values; they are used to check that the parts add up.
 * Pure: no database access, so it can be unit-tested with an in-memory sheet.
 */
class HisExtractor
{
    private const TOLERANCE = 0.5;

    /**
     * @param  array{code:string,title:string,header_rows:int[],row_start:int,row_end:int,label_cols:string[],code_col:?string,kind:string,measure:?string,first_col:?string}  $block
     * @return array{values: array<int, array>, checks: array<int, array>, sections: string[]}
     */
    public function extract(Worksheet $ws, array $block): array
    {
        $headerRows = $block['header_rows'];
        $labelCols = array_map(fn ($c) => Coordinate::columnIndexFromString($c), $block['label_cols']);
        $codeCol = $block['code_col'] ? Coordinate::columnIndexFromString($block['code_col']) : null;
        $firstCol = $block['first_col'] ? Coordinate::columnIndexFromString($block['first_col']) : 1;
        $lastCol = Coordinate::columnIndexFromString($ws->getHighestDataColumn());
        $skipCols = array_merge($labelCols, $codeCol ? [$codeCol] : []);

        $grid = $this->grid($ws, min($headerRows ?: [$block['row_start']]), $block['row_end'], $lastCol, $headerRows, $labelCols);
        $columns = $this->columns($grid, $headerRows, $firstCol, $lastCol, $skipCols, $block);

        $values = [];
        $checks = [];
        $sections = [];
        $skipped = [];
        $section = null;
        $columnSums = [];   // whole table so far
        $sectionSums = [];  // since the last total / subtotal row
        $firstDataCol = $columns ? min(array_keys($columns)) : $lastCol + 1;

        for ($r = $block['row_start']; $r <= $block['row_end']; $r++) {
            $cells = $grid[$r] ?? [];
            $numbers = [];
            foreach ($columns as $c => $col) {
                if (isset($cells[$c]) && ($n = $this->number($cells[$c])) !== null) {
                    $numbers[$c] = $n;
                }
            }
            $label = trim(implode(' ', array_unique(array_filter(array_map(fn ($c) => $cells[$c] ?? '', $labelCols)))));
            $code = $codeCol ? trim((string) ($cells[$codeCol] ?? '')) : '';
            // every text left of the data columns (the "សរុប" of a total row often sits in column A)
            $leading = array_filter(array_intersect_key($cells, array_flip(range(1, max(1, $firstDataCol - 1)))), fn ($t) => $this->number($t) === null);

            if (!$numbers) {
                // section headings ("1. ផ្នែកជំងឺទូទៅ") usually sit in column A, left of the label column
                $heading = $label !== '' ? $label : trim(implode(' ', $leading));
                if ($heading !== '' && !$this->isNumberingOnly($heading)) {
                    $section = $heading;
                    $sections[] = $heading;
                }
                continue;
            }
            if ($code === '' && ($this->isTotal($label) || array_filter($leading, fn ($t) => $this->isTotal($t)))) {
                // a (sub)total: must equal either the rows since the previous total or the whole table
                foreach ($numbers as $c => $expected) {
                    $sinceLast = $sectionSums[$c] ?? 0.0;
                    $overall = $columnSums[$c] ?? 0.0;
                    if (abs($sinceLast - $expected) > self::TOLERANCE && abs($overall - $expected) > self::TOLERANCE) {
                        $checks[] = ['cell' => Coordinate::stringFromColumnIndex($c) . $r, 'expected' => $expected, 'actual' => $overall, 'what' => 'ជួរសរុប'];
                    }
                }
                $sectionSums = [];
                continue;
            }
            if ($label === '' && $code === '' && $block['kind'] !== 'wide') {
                // label written elsewhere in the row (e.g. a summary line "សរុបកញ្ចក់ឈាម…" in column R)
                $inline = array_values(array_filter($cells, fn ($t) => $this->number($t) === null && !preg_match('/^\(.*\)$/u', $t)));
                $label = $inline[0] ?? '';
            }
            if ($label === '' && $code === '' && $block['kind'] !== 'wide') {
                $skipped[] = $r; // numbers without any label cannot be named
                continue;
            }

            $isDiagnosis = $block['kind'] === 'diagnosis' && $code !== '';
            foreach ($numbers as $c => $n) {
                $col = $columns[$c];
                // every column (total columns too) is summed for the total-row check
                $columnSums[$c] = ($columnSums[$c] ?? 0) + $n;
                $sectionSums[$c] = ($sectionSums[$c] ?? 0) + $n;
                if ($col['total'] && $col['has_parts']) {
                    continue; // checked, not stored
                }
                $values[] = [
                    // "wide" tables: one unlabeled row, every column is its own data element
                    'element' => $this->elementName($block, $section, $isDiagnosis || $label === '' ? null : $label, $col['others']),
                    'diagnosis' => $isDiagnosis ? ['icd10' => $code, 'name' => $label, 'group' => $section] : null,
                    'age' => $col['age'],
                    'sex' => $col['sex'],
                    'option' => $col['option'],
                    'value' => $n,
                    'cell' => $ws->getTitle() . '!' . Coordinate::stringFromColumnIndex($c) . $r,
                ];
            }
            foreach ($this->rowChecks($columns, $numbers, $r) as $check) {
                $checks[] = $check;
            }
        }

        foreach ($skipped as $r) {
            $checks[] = ['cell' => "A{$r}", 'expected' => null, 'actual' => null, 'what' => 'ជួរមានលេខ តែគ្មានឈ្មោះ (មិននាំចូល)'];
        }

        return ['values' => $values, 'checks' => $checks, 'sections' => array_values(array_unique($sections))];
    }

    /** @return array<int, array<int, string>> row → column index → text (merged cells resolved for header rows) */
    private function grid(Worksheet $ws, int $fromRow, int $toRow, int $lastCol, array $headerRows, array $labelCols = []): array
    {
        $grid = [];
        foreach ($ws->getCellCollection()->getCoordinates() as $coordinate) {
            [$col, $row] = Coordinate::coordinateFromString($coordinate);
            $row = (int) $row;
            if ($row < $fromRow || $row > $toRow) {
                continue;
            }
            $cell = $ws->getCell($coordinate);
            $value = $cell->getDataType() === 'f' ? $cell->getOldCalculatedValue() : $cell->getValue();
            // zero-width characters (common in Khmer text, e.g. "≥​ 65") would hide age / sex words
            $text = trim(preg_replace(['/[\x{200B}\x{200C}\x{200D}\x{FEFF}]/u', '/\s+/u'], ['', ' '], (string) $value));
            if ($text !== '') {
                $grid[$row][Coordinate::columnIndexFromString($col)] = $text;
            }
        }
        // A merged header spreads its text over every column / row it covers. Only header rows:
        // a merged data cell holds one value and must not be counted once per covered column.
        foreach ($ws->getMergeCells() as $range) {
            // rangeBoundaries() returns rows as strings; cast so the header-row test below is exact
            [[$c1, $r1], [$c2, $r2]] = array_map(fn ($p) => array_map('intval', $p), Coordinate::rangeBoundaries($range));
            $text = $grid[$r1][$c1] ?? null;
            if ($text === null || $r2 < $fromRow || $r1 > $toRow) {
                continue;
            }
            for ($r = max($r1, $fromRow); $r <= min($r2, $toRow); $r++) {
                // label cells merged over several rows (e.g. "ចំនួនរំលូត" over two methods) name every row
                $isLabel = in_array($c1, $labelCols, true);
                if (!in_array($r, $headerRows, true) && !$isLabel) {
                    continue;
                }
                for ($c = $c1; $c <= min($c2, $lastCol); $c++) {
                    if (!$isLabel || in_array($c, $labelCols, true)) {
                        $grid[$r][$c] ??= $text;
                    }
                }
            }
        }

        // First header row: a group title spans the empty cells to its right until the next title
        // (HIS forms often draw the group without merging, e.g. "៥. ស្ត្រីមានផ្ទៃពោះ" over five tetanus columns).
        if ($headerRows) {
            $top = min($headerRows);
            $carry = null;
            for ($c = 1; $c <= $lastCol; $c++) {
                if (isset($grid[$top][$c])) {
                    $carry = $grid[$top][$c];
                } elseif ($carry !== null) {
                    $grid[$top][$c] = $carry;
                }
            }
        }

        return $grid;
    }

    /** Classifies every data column from its header path. */
    private function columns(array $grid, array $headerRows, int $firstCol, int $lastCol, array $skipCols, array $block): array
    {
        $columns = [];
        for ($c = $firstCol; $c <= $lastCol; $c++) {
            if (in_array($c, $skipCols, true)) {
                continue;
            }
            $hasData = false;
            for ($r = $block['row_start']; $r <= $block['row_end'] && !$hasData; $r++) {
                $hasData = isset($grid[$r][$c]) && $this->number($grid[$r][$c]) !== null;
            }
            if (!$hasData) {
                continue;
            }
            $path = [];
            foreach ($headerRows as $hr) {
                $t = $grid[$hr][$c] ?? '';
                if ($t !== '' && end($path) !== $t) {
                    $path[] = $t;
                }
            }
            $col = ['path' => $path, 'age' => null, 'sex' => null, 'option' => null, 'total' => false, 'others' => []];
            foreach ($path as $token) {
                if (($sex = self::sex($token)) !== null) {
                    $col['sex'] = $sex;
                } elseif (self::isTotal($token)) {
                    $col['total'] = true;
                } elseif (($age = AgeLabel::parse($token)) !== null) {
                    $col['age'] = $age;
                } elseif (($option = self::option($token)) !== null) {
                    $col['option'] = $option;
                } else {
                    $col['others'][] = self::canonical($token);
                }
            }
            $columns[$c] = $col;
        }

        // A total column is redundant when other columns break the same thing down (same measure words).
        foreach ($columns as $c => &$col) {
            $col['has_parts'] = false;
            if (!$col['total']) {
                continue;
            }
            foreach ($columns as $d => $other) {
                if ($d !== $c && !$other['total'] && $this->isPartOf($other, $col)) {
                    $col['has_parts'] = true;
                    break;
                }
            }
        }

        return $columns;
    }

    /** Total columns must equal the sum of each breakdown (by age, by zone, by sex …) in the same row. */
    private function rowChecks(array $columns, array $numbers, int $row): array
    {
        $checks = [];
        foreach ($columns as $c => $total) {
            if (!$total['total'] || !$total['has_parts'] || !isset($numbers[$c])) {
                continue;
            }
            $partitions = [];
            foreach ($columns as $d => $part) {
                if ($part['total'] || !$this->isPartOf($part, $total)) {
                    continue;
                }
                if (!isset($numbers[$d])) {
                    continue; // a breakdown left blank in this row is not checked
                }
                $key = ($part['age'] ? 'age' : '') . ($part['option'] ? 'opt:' . $part['option']['category'] : '') . ($total['sex'] === null && $part['sex'] ? 'sex' : '');
                $partitions[$key] = ($partitions[$key] ?? 0) + $numbers[$d];
            }
            foreach ($partitions as $sum) {
                if (abs($sum - $numbers[$c]) > self::TOLERANCE) {
                    $checks[] = ['cell' => Coordinate::stringFromColumnIndex($c) . $row, 'expected' => $numbers[$c], 'actual' => $sum, 'what' => 'ជួរឈរសរុប'];
                }
            }
        }

        return $checks;
    }

    /**
     * A column is a part of a total column when it measures the same thing and lies inside the total's
     * fixed dimensions: e.g. "១២-៥៩ខែ › សរុប" only adds up the males and females of that same age group.
     */
    private function isPartOf(array $part, array $total): bool
    {
        return $part['others'] === $total['others']
            && ($total['sex'] === null || $part['sex'] === $total['sex'])
            && ($total['age'] === null || $part['age'] === $total['age'])
            && ($total['option'] === null || $part['option'] === $total['option']);
    }

    private function elementName(array $block, ?string $section, ?string $rowLabel, array $others): array
    {
        $parts = [$block['measure'] ?: $block['title']];
        if ($rowLabel !== null) {
            if ($section && $section !== $block['title']) {
                $parts[] = $section;
            }
            $parts[] = $rowLabel;
        }

        return array_values(array_filter(array_merge($parts, $others), fn ($p) => $p !== null && $p !== ''));
    }

    private function number(string $text): ?float
    {
        // HIS cells are plain numbers; text such as "ICD10" or "(1)" is a header, not data.
        if (!preg_match('/^-?[\d០-៩][\d០-៩,. ]*%?$/u', $text)) {
            return null;
        }

        return MappingFormula::toNumber($text);
    }

    private function isNumberingOnly(string $label): bool
    {
        return (bool) preg_match('/^[\d០-៩.\s()]+$/u', $label);
    }

    /**
     * Header wording differs slightly between HC1 and HO2 (typos, footnote stars, spacing); measure words
     * are normalised so both forms feed the same data element.
     */
    public static function canonical(string $token): string
    {
        $t = trim(preg_replace('/\s+/u', ' ', str_replace(['*', "\u{200B}"], '', $token)));
        $squashed = str_replace(' ', '', $t);
        $known = [
            'បញ្ញួនទៅ' => 'បញ្ជូនទៅ',
            'បញ្ជូនទៅ' => 'បញ្ជូនទៅ',
            'ប្រើបា្រស់មូលនិធិសមធម៌' => 'ប្រើប្រាស់មូលនិធិសមធម៌',
            'ប្រើប្រាស់មូលនិធិសមធម៌' => 'ប្រើប្រាស់មូលនិធិសមធម៌',
        ];

        return $known[$squashed] ?? $t;
    }

    public static function isTotal(string $token): bool
    {
        return (bool) preg_match('/^(សរុប|total)$/iu', trim($token));
    }

    public static function sex(string $token): ?string
    {
        $t = trim($token, " .\t");

        return match (true) {
            (bool) preg_match('/^(ប្រ|ប្រុស|male|m)$/iu', $t) => 'M',
            (bool) preg_match('/^(ស្រ|ស្រី|female|f)$/iu', $t) => 'F',
            default => null,
        };
    }

    /** @return array{category:string,code:string,label:string}|null */
    public static function option(string $token): ?array
    {
        $t = trim(preg_replace('/\s+/u', ' ', $token));
        if (preg_match('/^តំបន់\s*(ក|ខ|គ)$/u', $t, $m)) {
            return ['category' => 'zone', 'code' => ['ក' => 'A', 'ខ' => 'B', 'គ' => 'C'][$m[1]], 'label' => "តំបន់{$m[1]}"];
        }
        if (preg_match('/^(ក្នុង|ក្រៅ)ស្រុកប្រតិបត្តិ/u', $t, $m)) {
            return ['category' => 'residence', 'code' => $m[1] === 'ក្នុង' ? 'IN' : 'OUT', 'label' => $m[1] . 'ស្រុកប្រតិបត្តិ/ខេត្ត'];
        }
        if (in_array($t, ['ម្តាយ', 'ម្ដាយ'], true)) {
            return ['category' => 'person', 'code' => 'MOTHER', 'label' => 'ម្តាយ'];
        }
        if ($t === 'កូន') {
            return ['category' => 'person', 'code' => 'CHILD', 'label' => 'កូន'];
        }

        return null;
    }
}
