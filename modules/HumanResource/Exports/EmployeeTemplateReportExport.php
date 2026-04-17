<?php

namespace Modules\HumanResource\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use Modules\Setting\Entities\Application;
use PPhatDev\LunarDate\KhmerDate;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeeTemplateReportExport implements FromArray, WithStyles, WithEvents, WithDrawings
{
    protected array $selectedColumns;
    protected array $columnOptions;
    protected Collection $rows;
    protected Collection $groupedSummary;
    protected ?string $groupLabel;
    protected array $meta;

    protected int $headerRow = 11;
    protected int $tableLastRow = 11;
    protected int $lastRow = 11;
    protected int $footerDateRow = 0;
    protected int $footerDateDetailRow = 0;
    protected int $footerApprovalRow = 0;
    protected int $footerHeadTitleRow = 0;
    protected int $footerHeadSignRow = 0;
    protected int $footerHrSignRow = 0;

    public function __construct(
        array $selectedColumns,
        array $columnOptions,
        Collection $rows,
        Collection $groupedSummary,
        ?string $groupLabel,
        array $meta = []
    ) {
        $this->selectedColumns = array_values($selectedColumns);
        $this->columnOptions = $columnOptions;
        $this->rows = $rows;
        $this->groupedSummary = $groupedSummary;
        $this->groupLabel = $groupLabel;
        $this->meta = array_merge([
            'admin_text' => 'រដ្ឋបាលខេត្តស្ទឹងត្រែង',
            'unit_text' => 'មន្ទីរសុខាភិបាលនៃរដ្ឋបាលខេត្ត',
            'title_text' => 'តារាងរបាយការណ៍បុគ្គលិក',
            'location_text' => 'ស្ទឹងត្រែង',
            'approval_text' => 'ប្រធានមន្ទីរសុខាភិបាល',
            'hr_manager_text' => 'មន្ត្រីគ្រប់គ្រងបុគ្គលិក',
        ], $meta);
    }

    public function array(): array
    {
        $rows = [];
        $columnCount = max(10, count($this->selectedColumns));

        $rows[] = $this->fillRow(['ព្រះរាជាណាចក្រកម្ពុជា'], $columnCount);
        $rows[] = $this->fillRow(['ជាតិ សាសនា ព្រះមហាក្សត្រ'], $columnCount);
        $rows[] = $this->fillRow(['6'], $columnCount);
        $rows[] = $this->blankRow($columnCount);
        $rows[] = $this->blankRow($columnCount);
        $rows[] = $this->fillRow([(string) $this->meta['admin_text']], $columnCount);
        $rows[] = $this->fillRow([(string) $this->meta['unit_text']], $columnCount);
        $rows[] = $this->blankRow($columnCount);
        $rows[] = $this->fillRow([(string) $this->meta['title_text']], $columnCount);

        if ($this->groupLabel) {
            $rows[] = $this->fillRow(['សង្ខេបតាមក្រុម: ' . $this->groupLabel], $columnCount);
        } else {
            $rows[] = $this->blankRow($columnCount);
        }

        $header = [];
        foreach ($this->selectedColumns as $column) {
            $header[] = (string) ($this->columnOptions[$column] ?? $column);
        }
        $rows[] = $this->fillRow($header, $columnCount);

        foreach ($this->rows as $row) {
            $line = [];
            foreach ($this->selectedColumns as $column) {
                $line[] = $this->sanitizeText((string) ($row[$column] ?? ''));
            }
            $rows[] = $this->fillRow($line, $columnCount);
        }

        $this->tableLastRow = count($rows);

        if ($this->groupLabel && $this->groupedSummary->isNotEmpty()) {
            $rows[] = $this->blankRow($columnCount);
            $rows[] = $this->fillRow(['សង្ខេបចំនួនតាមក្រុម: ' . $this->groupLabel], $columnCount);
            foreach ($this->groupedSummary as $summary) {
                $label = $this->sanitizeText((string) ($summary['group_label'] ?? '-'));
                $total = $this->toKhmerDigits((string) ($summary['total'] ?? 0));
                $rows[] = $this->fillRow([$label . ' : ' . $total], $columnCount);
            }
        }

        $rows[] = $this->blankRow($columnCount);

        $rightTextColIndex = $this->rightFooterTextIndex($columnCount);

        $rows[] = $this->textAtColumn($this->khmerLunarDateText(), $columnCount, $rightTextColIndex);
        $this->footerDateRow = count($rows);

        $rows[] = $this->textAtColumn($this->khmerSolarDateText(), $columnCount, $rightTextColIndex);
        $this->footerDateDetailRow = count($rows);

        $approvalRow = $this->blankRow($columnCount);
        $approvalRow[1] = 'ឯកភាព';
        $approvalRow[$rightTextColIndex] = (string) $this->meta['hr_manager_text'];
        $rows[] = $approvalRow;
        $this->footerApprovalRow = count($rows);

        $rows[] = $this->textAtColumn((string) $this->meta['approval_text'], $columnCount, 1);
        $this->footerHeadTitleRow = count($rows);

        $rows[] = $this->fillRow(['', '', ''], $columnCount);
        $this->footerHeadSignRow = count($rows);

        $rows[] = $this->fillRow(['', '', ''], $columnCount);
        $this->footerHrSignRow = count($rows);

        $this->lastRow = count($rows);

        return $rows;
    }

    public function drawings(): array
    {
        $logoPath = $this->resolveLogoPath();
        if (!$logoPath) {
            return [];
        }

        $drawing = new Drawing();
        $drawing->setName('Organization Logo');
        $drawing->setDescription('Organization Logo');
        $drawing->setPath($logoPath);
        $drawing->setCoordinates('A4');
        $drawing->setOffsetX(0);
        $drawing->setOffsetY(0);
        $drawing->setResizeProportional(false);
        $drawing->setWidth(96);
        $drawing->setHeight(96);

        return [$drawing];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => false, 'size' => 12, 'name' => 'Khmer M1', 'color' => ['rgb' => '002060']]],
            2 => ['font' => ['bold' => false, 'size' => 12, 'name' => 'Khmer M1', 'color' => ['rgb' => '002060']]],
            3 => ['font' => ['bold' => false, 'size' => 48, 'name' => 'Tacteing', 'color' => ['rgb' => '002060']]],
            6 => ['font' => ['bold' => false, 'size' => 12, 'name' => 'Khmer M1', 'color' => ['rgb' => '002060']]],
            7 => ['font' => ['bold' => false, 'size' => 12, 'name' => 'Khmer M1', 'color' => ['rgb' => '002060']]],
            9 => ['font' => ['bold' => false, 'size' => 13, 'name' => 'Khmer M1', 'color' => ['rgb' => '002060']]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $columnCount = max(10, count($this->selectedColumns));
                $lastCol = Coordinate::stringFromColumnIndex($columnCount);
                $rightStartCol = null;
                $rightEndCol = null;

                $sheet->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setPaperSize(PageSetup::PAPERSIZE_A4)
                    ->setFitToWidth(1)
                    ->setFitToHeight(0)
                    ->setHorizontalCentered(true);

                // Repeat the report table header row on each printed page.
                $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($this->headerRow, $this->headerRow);

                $sheet->getPageMargins()
                    ->setLeft(0.43)
                    ->setRight(0.21)
                    ->setTop(0.29)
                    ->setBottom(0.46)
                    ->setHeader(0.22)
                    ->setFooter(0.30);

                foreach ([1, 2, 3, 6, 7, 9, 10] as $row) {
                    $sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
                }

                if ($this->footerDateRow > 0) {
                    [$rightStartCol, $rightEndCol] = $this->footerRightMergeColumns($lastCol);
                    $sheet->mergeCells($rightStartCol . $this->footerDateRow . ':' . $rightEndCol . $this->footerDateRow);
                    $sheet->mergeCells($rightStartCol . $this->footerDateDetailRow . ':' . $rightEndCol . $this->footerDateDetailRow);
                    $sheet->mergeCells($rightStartCol . $this->footerApprovalRow . ':' . $rightEndCol . $this->footerApprovalRow);
                    $sheet->mergeCells('B' . $this->footerApprovalRow . ':D' . $this->footerApprovalRow);
                    $sheet->mergeCells('B' . $this->footerHeadTitleRow . ':D' . $this->footerHeadTitleRow);

                    $sheet->getStyle($rightStartCol . $this->footerDateRow . ':' . $rightEndCol . $this->footerDateDetailRow)
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);

                    $sheet->getStyle($rightStartCol . $this->footerApprovalRow . ':' . $rightEndCol . $this->footerApprovalRow)
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                    $sheet->getStyle($rightStartCol . $this->footerApprovalRow . ':' . $rightEndCol . $this->footerApprovalRow)
                        ->getFont()
                        ->setName('Khmer M1')
                        ->setSize(12)
                        ->setBold(false)
                        ->getColor()
                        ->setRGB('002060');

                    $sheet->getStyle('B' . $this->footerApprovalRow . ':D' . $this->footerHeadTitleRow)
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                    $sheet->getStyle('B' . $this->footerHeadTitleRow . ':D' . $this->footerHeadTitleRow)
                        ->getFont()
                        ->setName('Khmer M1')
                        ->setSize(12)
                        ->setBold(false)
                        ->getColor()
                        ->setRGB('002060');
                }

                foreach ([1, 2, 3, 9] as $row) {
                    $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                }

                $sheet->getRowDimension(6)->setRowHeight(25.8);
                $sheet->getRowDimension(7)->setRowHeight(25.8);
                $sheet->getRowDimension(3)->setRowHeight(35.5);
                $sheet->getRowDimension(4)->setRowHeight(35.5);
                $sheet->getRowDimension(5)->setRowHeight(35.5);

                if ($this->tableLastRow >= $this->headerRow) {
                    $sheet->getStyle('A' . $this->headerRow . ':' . $lastCol . $this->headerRow)->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER)
                        ->setWrapText(true);

                    $sheet->getStyle('A' . $this->headerRow . ':' . $lastCol . $this->tableLastRow)->getBorders()->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN)
                        ->getColor()->setRGB('3B3B3B');

                    $sheet->setAutoFilter('A' . $this->headerRow . ':' . $lastCol . $this->headerRow);
                }

                $sheet->getStyle('A1:' . $lastCol . $this->lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('A10:' . $lastCol . $this->lastRow)->getFont()->setName('Khmer OS Siemreap')->setSize(10);
                $sheet->getStyle('A' . $this->headerRow . ':' . $lastCol . $this->headerRow)->getFont()->setName('Khmer M1')->setSize(10);

                if ($this->footerApprovalRow > 0 && $rightStartCol && $rightEndCol) {
                    $sheet->getStyle($rightStartCol . $this->footerApprovalRow . ':' . $rightEndCol . $this->footerApprovalRow)
                        ->getFont()
                        ->setName('Khmer M1')
                        ->setSize(12)
                        ->setBold(false)
                        ->getColor()
                        ->setRGB('002060');
                }

                if ($this->footerHeadTitleRow > 0) {
                    $sheet->getStyle('B' . $this->footerHeadTitleRow . ':D' . $this->footerHeadTitleRow)
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                    $sheet->getStyle('B' . $this->footerHeadTitleRow . ':D' . $this->footerHeadTitleRow)
                        ->getFont()
                        ->setName('Khmer M1')
                        ->setSize(12)
                        ->setBold(false)
                        ->getColor()
                        ->setRGB('002060');
                }

                for ($i = 1; $i <= $columnCount; $i++) {
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth(20);
                }

                $this->centerHeaderLogoInAB($sheet, 4, 5, 0, -$this->cmToPixels(2.22));
            },
        ];
    }

    protected function cmToPixels(float $centimeters): int
    {
        return (int) round(max(0, $centimeters) * 37.7952755906);
    }

    protected function rightFooterTextIndex(int $columnCount): int
    {
        if ($columnCount >= 8) {
            return 7;
        }

        return max(0, $columnCount - 1);
    }

    protected function textAtColumn(string $text, int $columnCount, int $columnIndex): array
    {
        $row = $this->blankRow($columnCount);
        $row[min(max(0, $columnIndex), max(0, $columnCount - 1))] = $text;

        return $row;
    }

    protected function footerRightMergeColumns(string $lastCol): array
    {
        $maxColIndex = Coordinate::columnIndexFromString($lastCol);
        if ($maxColIndex >= 10) {
            return ['H', 'J'];
        }

        if ($maxColIndex >= 3) {
            return [
                Coordinate::stringFromColumnIndex($maxColIndex - 2),
                Coordinate::stringFromColumnIndex($maxColIndex),
            ];
        }

        return ['A', Coordinate::stringFromColumnIndex($maxColIndex)];
    }

    protected function fillRow(array $values, int $count): array
    {
        $row = array_values($values);
        while (count($row) < $count) {
            $row[] = '';
        }

        return array_slice($row, 0, $count);
    }

    protected function blankRow(int $count): array
    {
        return array_fill(0, $count, '');
    }

    protected function sanitizeText(string $value): string
    {
        return (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
    }

    protected function toKhmerDigits(string $value): string
    {
        return strtr($value, [
            '0' => '០',
            '1' => '១',
            '2' => '២',
            '3' => '៣',
            '4' => '៤',
            '5' => '៥',
            '6' => '៦',
            '7' => '៧',
            '8' => '៨',
            '9' => '៩',
        ]);
    }

    protected function khmerLunarDateText(): string
    {
        $fallback = 'ថ្ងៃ........ ខែ........ ឆ្នាំ........ ពស........';

        try {
            $khmerDate = new KhmerDate(Carbon::today()->toDateString());
            $text = trim((string) $khmerDate->toLunarDate());
            $text = preg_replace('/^\s*ត្រូវនឹង\s*/u', '', $text) ?: $text;
            $text = str_replace(['ពុទ្ធសករាជ', 'ព.ស.', 'ព.ស'], 'ពស', $text);

            return $text !== '' ? $text : $fallback;
        } catch (\Throwable $exception) {
            return $fallback;
        }
    }

    protected function khmerSolarDateText(): string
    {
        $months = [
            1 => 'មករា',
            2 => 'កុម្ភៈ',
            3 => 'មីនា',
            4 => 'មេសា',
            5 => 'ឧសភា',
            6 => 'មិថុនា',
            7 => 'កក្កដា',
            8 => 'សីហា',
            9 => 'កញ្ញា',
            10 => 'តុលា',
            11 => 'វិច្ឆិកា',
            12 => 'ធ្នូ',
        ];

        $today = Carbon::today();
        $location = trim((string) ($this->meta['location_text'] ?? 'ស្ទឹងត្រែង'));
        $monthKh = $months[(int) $today->month] ?? '';

        return sprintf(
            '%s ថ្ងៃទី%s ខែ%s ឆ្នាំ %s',
            $location !== '' ? $location : 'ស្ទឹងត្រែង',
            $this->toKhmerDigits($today->format('d')),
            $monthKh,
            $this->toKhmerDigits($today->format('Y'))
        );
    }

    protected function centerHeaderLogoInAB(Worksheet $sheet, int $startRow, int $endRow, int $extraOffsetY = 0, int $extraOffsetX = 0): void
    {
        if (empty($sheet->getDrawingCollection())) {
            return;
        }

        $target = null;
        foreach ($sheet->getDrawingCollection() as $drawing) {
            if ((string) $drawing->getName() === 'Organization Logo') {
                $target = $drawing;
                break;
            }
        }

        if (!$target) {
            return;
        }

        $colA = (int) round(((float) $sheet->getColumnDimension('A')->getWidth()) * 7);
        $colB = (int) round(((float) $sheet->getColumnDimension('B')->getWidth()) * 7);
        $areaWidth = (int) round($colA + $colB);

        $logoWidth = (int) round((float) $target->getWidth());
        if ($logoWidth <= 0) {
            return;
        }

        $offsetX = (int) floor(max(0, ($areaWidth - $logoWidth) / 2)) + $extraOffsetX;
        $target->setCoordinates('A' . $startRow);
        $target->setOffsetX($offsetX);

        $rowHeightPx = 0;
        for ($r = $startRow; $r <= $endRow; $r++) {
            $heightPt = (float) $sheet->getRowDimension($r)->getRowHeight();
            if ($heightPt <= 0) {
                $heightPt = 15;
            }
            $rowHeightPx += (int) round($heightPt * 96 / 72);
        }

        $logoHeight = (int) round((float) $target->getHeight());
        $offsetY = (int) floor(max(0, ($rowHeightPx - $logoHeight) / 2)) + max(0, $extraOffsetY);
        $target->setOffsetY($offsetY);
    }

    protected function resolveLogoPath(): ?string
    {
        $dbCandidates = [];
        try {
            $app = Application::find(1);
            $logo = trim((string) ($app->logo ?? ''));
            if ($logo !== '') {
                $clean = ltrim(str_replace('\\', '/', $logo), '/');
                $dbCandidates[] = storage_path('app/public/' . $clean);
                $dbCandidates[] = public_path('storage/' . $clean);
                $dbCandidates[] = public_path($clean);
            }
        } catch (\Throwable $exception) {
            // Ignore and use fallbacks.
        }

        $candidates = array_merge($dbCandidates, [
            public_path('backend/assets/dist/img/sidebar-logo.png'),
            public_path('backend/assets/dist/img/new-logo.png'),
            public_path('backend/assets/dist/img/logo-preview.png'),
            public_path('assets/logo.png'),
            public_path('assets/logo2.png'),
            public_path('assets/hrm-nrw-logo.png'),
            public_path('assets/img/logo.png'),
            public_path('logo.png'),
        ]);

        foreach ($candidates as $path) {
            if (is_string($path) && $path !== '' && is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
