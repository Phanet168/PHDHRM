<?php

namespace Modules\Report\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * Reads numeric values from an uploaded HC / HOSP workbook the way PHDReport did:
 * a cell inside a merged block resolves to the block's top-left cell, and a range returns the sum
 * of its numeric cells (each merged block counted once).
 */
class WorkbookReader
{
    private array $sheetsByLowerName = [];
    private array $mergeCache = [];

    public function __construct(private Spreadsheet $book)
    {
        foreach ($book->getSheetNames() as $name) {
            $this->sheetsByLowerName[mb_strtolower(trim($name))] = $name;
        }
    }

    public static function open(string $path): self
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(false); // keep merge ranges

        return new self($reader->load($path));
    }

    public function sheetNames(): array
    {
        return $this->book->getSheetNames();
    }

    public function hasSheet(string $sheet): bool
    {
        return isset($this->sheetsByLowerName[mb_strtolower(trim($sheet))]);
    }

    /** "Sheet!A1" or "Sheet!A1:B4" (sheet names may be quoted). */
    public function ref(string $ref): ?float
    {
        $at = strrpos($ref, '!');
        if ($at === false) {
            return null;
        }

        return $this->value(trim(substr($ref, 0, $at), " '\""), substr($ref, $at + 1));
    }

    public function value(string $sheet, string $cellOrRange): ?float
    {
        $name = $this->sheetsByLowerName[mb_strtolower(trim($sheet))] ?? null;
        $cellOrRange = strtoupper(str_replace('$', '', trim($cellOrRange)));
        if ($name === null || $cellOrRange === '') {
            return null;
        }
        $ws = $this->book->getSheetByName($name);

        try {
            if (str_contains($cellOrRange, ':')) {
                return $this->sumRange($ws, $cellOrRange);
            }
            [$col, $row] = Coordinate::coordinateFromString($cellOrRange);

            return $this->cellNumber($ws, ...$this->master($ws, Coordinate::columnIndexFromString($col), (int) $row));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Non-empty cells of a sheet for the import file viewer, limited to $maxRows × $maxCols.
     *
     * @return array{sheet:string, cols:string[], rows:array<int, array<string, string>>, more:bool}
     */
    public function grid(?string $sheet, int $maxRows = 300, int $maxCols = 60): array
    {
        $name = $sheet !== null ? ($this->sheetsByLowerName[mb_strtolower(trim($sheet))] ?? null) : null;
        $ws = $name ? $this->book->getSheetByName($name) : $this->book->getSheet(0);
        $lastRow = min($ws->getHighestDataRow(), $maxRows);
        $lastCol = min(Coordinate::columnIndexFromString($ws->getHighestDataColumn()), $maxCols);

        $rows = [];
        foreach ($ws->getCellCollection()->getCoordinates() as $coordinate) {
            [$colLetter, $row] = Coordinate::coordinateFromString($coordinate);
            $row = (int) $row;
            if ($row > $lastRow || Coordinate::columnIndexFromString($colLetter) > $lastCol) {
                continue;
            }
            $cell = $ws->getCell($coordinate);
            $value = $cell->getDataType() === DataType::TYPE_FORMULA ? $cell->getOldCalculatedValue() : $cell->getValue();
            if ($value === null && $cell->getDataType() === DataType::TYPE_FORMULA) {
                try {
                    $value = $cell->getCalculatedValue();
                } catch (Throwable) {
                    $value = null;
                }
            }
            $text = trim((string) $value);
            if ($text !== '') {
                $rows[$row][$colLetter] = $text;
            }
        }
        ksort($rows);

        return [
            'sheet' => $ws->getTitle(),
            'cols' => array_map(fn ($i) => Coordinate::stringFromColumnIndex($i), range(1, max(1, $lastCol))),
            'rows' => $rows,
            'more' => $ws->getHighestDataRow() > $maxRows || Coordinate::columnIndexFromString($ws->getHighestDataColumn()) > $maxCols,
        ];
    }

    private function sumRange(Worksheet $ws, string $range): ?float
    {
        [[$c1, $r1], [$c2, $r2]] = Coordinate::rangeBoundaries($range);
        $sum = 0.0;
        $any = false;
        $seen = [];
        for ($r = $r1; $r <= $r2; $r++) {
            for ($c = $c1; $c <= $c2; $c++) {
                [$mc, $mr] = $this->master($ws, $c, $r);
                if (isset($seen["{$mc}:{$mr}"])) {
                    continue;
                }
                $seen["{$mc}:{$mr}"] = true;
                $n = $this->cellNumber($ws, $mc, $mr);
                if ($n !== null) {
                    $sum += $n;
                    $any = true;
                }
            }
        }

        return $any ? $sum : null;
    }

    /** @return array{0:int,1:int} column index and row of the merged block's top-left cell */
    private function master(Worksheet $ws, int $col, int $row): array
    {
        $key = $ws->getTitle();
        if (!isset($this->mergeCache[$key])) {
            $this->mergeCache[$key] = array_map(
                fn ($range) => Coordinate::rangeBoundaries($range),
                array_values($ws->getMergeCells())
            );
        }
        foreach ($this->mergeCache[$key] as [[$c1, $r1], [$c2, $r2]]) {
            if ($col >= $c1 && $col <= $c2 && $row >= $r1 && $row <= $r2) {
                return [$c1, $r1];
            }
        }

        return [$col, $row];
    }

    private function cellNumber(Worksheet $ws, int $col, int $row): ?float
    {
        $coordinate = Coordinate::stringFromColumnIndex($col) . $row;
        if (!$ws->cellExists($coordinate)) {
            return null;
        }
        $cell = $ws->getCell($coordinate);
        if ($cell->getDataType() === DataType::TYPE_FORMULA) {
            $cached = $cell->getOldCalculatedValue();
            if ($cached !== null && $cached !== '') {
                return MappingFormula::toNumber($cached);
            }
            try {
                return MappingFormula::toNumber($cell->getCalculatedValue());
            } catch (Throwable) {
                return null;
            }
        }

        return MappingFormula::toNumber($cell->getValue());
    }
}
