<?php

namespace Modules\Planning\Exports;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class DailyActivityCalendarExport implements FromView, WithEvents
{
    public function __construct(
        private readonly mixed $rows,
        private readonly mixed $timelineDays,
        private readonly string $monthLabel,
        private readonly int $reportYear,
        private readonly string $reportOrgName,
        private readonly array $orgHeaderLines,
        private readonly string $printDateLabel
    ) {
    }

    public function view(): View
    {
        return view('planning::reports.exports.daily-excel', [
            'rows' => $this->rows,
            'timelineDays' => $this->timelineDays,
            'monthLabel' => $this->monthLabel,
            'reportYear' => $this->reportYear,
            'reportOrgName' => $this->reportOrgName,
            'orgHeaderLines' => $this->orgHeaderLines,
            'printDateLabel' => $this->printDateLabel,
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $spreadsheet = $sheet->getParent();

                $timelineDaysCount = $this->timelineDays->count();
                $totalColumns = 5 + $timelineDaysCount;
                $lastColumn = Coordinate::stringFromColumnIndex($totalColumns);
                $extraOrgLineCount = max(count($this->orgHeaderLines) - 1, 0);
                $currentUnitName = collect($this->orgHeaderLines ?: [$this->reportOrgName])->filter()->last() ?: $this->reportOrgName;
                $khmerYear = strtr((string) $this->reportYear, ['0' => '០', '1' => '១', '2' => '២', '3' => '៣', '4' => '៤', '5' => '៥', '6' => '៦', '7' => '៧', '8' => '៨', '9' => '៩']);
                $titleRow = 4 + $extraOrgLineCount;
                $mainHeaderTopRow = 6 + $extraOrgLineCount;
                $mainHeaderBottomRow = $mainHeaderTopRow + 1;
                $firstDataRow = $mainHeaderBottomRow + 1;
                $rows = collect($this->rows);
                $groupedCount = $rows instanceof Collection
                    ? $rows->groupBy(function ($row) {
                        $label = trim(((string) ($row->indicator_code ?? '')) !== '' ? $row->indicator_code . ' - ' . ($row->indicator_name ?? '') : ($row->indicator_name ?? ''));

                        return $label !== '' ? $label : 'មិនមានសូចនាករ';
                    })->count()
                    : 0;
                $dataRowCount = max(1, $rows->count() + $groupedCount);
                $lastTableRow = $firstDataRow + $dataRowCount - 1;
                $footerTitleRow = $lastTableRow + 2;
                $footerDateRow = $lastTableRow + 3;
                $footerRoleRow = $lastTableRow + 4;
                $footerSignRow = $lastTableRow + 8;
                $printLastRow = $footerSignRow;
                $timelineStartColumnIndex = 4;
                $timelineEndColumnIndex = $totalColumns - 1;
                $leftBlockStartColumn = 'A';
                $leftBlockEndColumn = Coordinate::stringFromColumnIndex(10);
                $leftGapStartColumn = Coordinate::stringFromColumnIndex(11);
                $leftGapEndColumn = Coordinate::stringFromColumnIndex(12);
                $centerBlockStartColumn = Coordinate::stringFromColumnIndex(13);
                $centerBlockEndColumn = Coordinate::stringFromColumnIndex(22);
                $centerGapStartColumn = Coordinate::stringFromColumnIndex(23);
                $centerGapEndColumn = Coordinate::stringFromColumnIndex(24);
                $rightBlockStartColumn = Coordinate::stringFromColumnIndex(25);

                $sheet->setTitle('ផែនការប្រចាំថ្ងៃ');

                $spreadsheet->getDefaultStyle()->getFont()
                    ->setName('Khmer OS Siemreap')
                    ->setSize(10);
                $spreadsheet->getDefaultStyle()->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);

                $sheet->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setPaperSize(PageSetup::PAPERSIZE_A4)
                    ->setFitToWidth(1)
                    ->setFitToHeight(0)
                    ->setHorizontalCentered(true)
                    ->setPrintArea('A1:' . $lastColumn . $printLastRow)
                    ->setRowsToRepeatAtTopByStartAndEnd($mainHeaderTopRow, $mainHeaderBottomRow);
                $sheet->getPageMargins()
                    ->setTop(0.22)
                    ->setRight(0.12)
                    ->setBottom(0.22)
                    ->setLeft(0.12)
                    ->setHeader(0.15)
                    ->setFooter(0.15);
                $sheet->getSheetView()->setZoomScale(95);

                $sheet->getColumnDimension('A')->setWidth(6);
                $sheet->getColumnDimension('B')->setWidth(36);
                $sheet->getColumnDimension('C')->setWidth(20);
                foreach (range($timelineStartColumnIndex, $timelineEndColumnIndex) as $columnIndex) {
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setWidth(3.35);
                }
                $sheet->getColumnDimension($lastColumn)->setWidth(8);

                $sheet->getRowDimension(1)->setRowHeight(22);
                $sheet->getRowDimension(2)->setRowHeight(46);
                foreach (range(3, 5 + $extraOrgLineCount) as $row) {
                    $sheet->getRowDimension($row)->setRowHeight(24);
                }
                $sheet->getRowDimension($titleRow)->setRowHeight(34);
                $sheet->getRowDimension($mainHeaderTopRow)->setRowHeight(30);
                $sheet->getRowDimension($mainHeaderBottomRow)->setRowHeight(30);
                foreach (range($firstDataRow, $lastTableRow) as $row) {
                    $sheet->getRowDimension($row)->setRowHeight(-1);
                }
                $sheet->getRowDimension($footerTitleRow)->setRowHeight(26);
                $sheet->getRowDimension($footerDateRow)->setRowHeight(26);
                $sheet->getRowDimension($footerRoleRow)->setRowHeight(28);
                $sheet->getRowDimension($footerSignRow)->setRowHeight(34);

                $sheet->getStyle('A1:' . $lastColumn . $printLastRow)->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('A1:' . $lastColumn . $printLastRow)->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_NONE);
                $sheet->getStyle('A1:' . $lastColumn . $printLastRow)->getFill()->setFillType(Fill::FILL_NONE);

                $sheet->getStyle('A1:' . $leftBlockEndColumn . (2 + $extraOrgLineCount))->getFont()
                    ->setName('Khmer M1')
                    ->setSize(11)
                    ->setBold(false);
                $sheet->getStyle('A1:' . $leftBlockEndColumn . (2 + $extraOrgLineCount))->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                    ->setVertical(Alignment::VERTICAL_BOTTOM)
                    ->setIndent(1);

                $sheet->getStyle($rightBlockStartColumn . '1:' . $lastColumn . '2')->getFont()
                    ->setName('Khmer M1')
                    ->setSize(14)
                    ->setBold(false);
                $sheet->getStyle($rightBlockStartColumn . '1:' . $lastColumn . '2')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->getStyle($centerBlockStartColumn . '1:' . $centerBlockEndColumn . (2 + $extraOrgLineCount))->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->getStyle('A' . $titleRow . ':' . $lastColumn . $titleRow)->getFont()
                    ->setName('Khmer M1')
                    ->setSize(18)
                    ->setBold(false);
                $sheet->getStyle('A' . $titleRow . ':' . $lastColumn . $titleRow)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->getStyle('A' . $mainHeaderTopRow . ':' . $lastColumn . $mainHeaderBottomRow)->getFont()
                    ->setName('Khmer OS Siemreap')
                    ->setSize(10)
                    ->setBold(true);
                $sheet->getStyle('A' . $mainHeaderTopRow . ':' . $lastColumn . $mainHeaderBottomRow)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A' . $mainHeaderTopRow . ':' . $lastColumn . $mainHeaderBottomRow)
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('F6FBF7');
                $sheet->getStyle('A' . $mainHeaderTopRow . ':' . $lastColumn . $lastTableRow)
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setARGB('111111');
                $sheet->getStyle('A' . ($lastTableRow + 1) . ':' . $lastColumn . $lastRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_NONE,
                        ],
                        'outline' => [
                            'borderStyle' => Border::BORDER_NONE,
                        ],
                        'inside' => [
                            'borderStyle' => Border::BORDER_NONE,
                        ],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_NONE,
                    ],
                ]);

                foreach ($this->timelineDays as $offset => $day) {
                    $column = Coordinate::stringFromColumnIndex($timelineStartColumnIndex + $offset);

                    if (!($day->is_actual_day ?? true)) {
                        $sheet->getStyle($column . $mainHeaderBottomRow . ':' . $column . $lastTableRow)
                            ->getFill()->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setARGB('EEF2F5');
                        $sheet->getStyle($column . $mainHeaderBottomRow . ':' . $column . $lastTableRow)
                            ->getFont()->getColor()->setARGB('8F99A5');
                        continue;
                    }

                    if (in_array($day->dayOfWeekIso, [6, 7], true)) {
                        $sheet->getStyle($column . $mainHeaderBottomRow . ':' . $column . $lastTableRow)
                            ->getFill()->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setARGB('DCEAF8');
                    }
                }

                $sheet->getStyle('A' . $firstDataRow . ':A' . $lastTableRow)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('B' . $firstDataRow . ':C' . $lastTableRow)->getFont()
                    ->setName('Khmer OS Siemreap')
                    ->setSize(10);
                $sheet->getStyle('B' . $firstDataRow . ':B' . $lastTableRow)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                    ->setVertical(Alignment::VERTICAL_TOP)
                    ->setWrapText(true)
                    ->setShrinkToFit(false);
                $sheet->getStyle('C' . $firstDataRow . ':C' . $lastTableRow)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                    ->setVertical(Alignment::VERTICAL_TOP)
                    ->setWrapText(true)
                    ->setShrinkToFit(false);
                $sheet->getStyle('D' . $firstDataRow . ':' . $lastColumn . $lastTableRow)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                for ($row = $firstDataRow; $row <= $lastTableRow; $row++) {
                    $value = trim((string) $sheet->getCell('A' . $row)->getValue());
                    if (str_starts_with($value, 'សូចនាករ៖') || str_starts_with($value, 'ážŸáž¼áž…áž“áž¶áž€ážš')) {
                        $sheet->getStyle('A' . $row . ':' . $lastColumn . $row)->applyFromArray([
                            'font' => [
                                'name' => 'Khmer OS Siemreap',
                                'bold' => true,
                                'size' => 11,
                                'color' => ['argb' => '124F2C'],
                            ],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['argb' => 'EAF4EC'],
                            ],
                        ]);
                        $sheet->getStyle('A' . $row . ':' . $lastColumn . $row)->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_LEFT);
                        $sheet->getRowDimension($row)->setRowHeight(24);
                    }
                }

                $detectedFooterTitleRow = null;
                foreach (range($lastTableRow + 1, $lastRow) as $row) {
                    foreach (range(1, 36) as $column) {
                        $value = trim((string) $sheet->getCell(Coordinate::stringFromColumnIndex($column) . $row)->getValue());
                        if (str_contains($value, 'បានឃើញ')) {
                            $detectedFooterTitleRow = $row;
                            break 2;
                        }
                    }
                }

                $footerTitleRow = $detectedFooterTitleRow ?: $footerTitleRow;
                $footerDateRow = $footerTitleRow + 1;
                $footerRoleRow = $footerTitleRow + 2;
                $footerEndRow = max($lastRow, $footerRoleRow + 4);
                $footerArea = 'A' . $footerTitleRow . ':AJ' . $footerEndRow;

                foreach (array_keys($sheet->getMergeCells()) as $mergedRange) {
                    [$rangeStart, $rangeEnd] = explode(':', $mergedRange);
                    $startRow = (int) preg_replace('/[A-Z]+/', '', $rangeStart);
                    $endRow = (int) preg_replace('/[A-Z]+/', '', $rangeEnd);

                    if ($endRow >= $footerTitleRow && $startRow <= $footerEndRow) {
                        $sheet->unmergeCells($mergedRange);
                    }
                }

                foreach (range($footerTitleRow, $footerEndRow) as $row) {
                    foreach (range(1, 36) as $column) {
                        $sheet->setCellValue(Coordinate::stringFromColumnIndex($column) . $row, null);
                    }
                }

                $footerBlocks = [
                    ['start' => 'A', 'end' => 'C'],
                    ['start' => 'D', 'end' => 'S'],
                    ['start' => 'T', 'end' => 'AJ'],
                ];

                $footerValues = [
                    $footerTitleRow => [
                        'A' => 'បានឃើញ និង..............',
                        'D' => 'បានឃើញ និង..............',
                        'T' => 'ថ្ងៃ.......................ខែ.................ឆ្នាំមមី អដ្ឋស័ក ព.ស.២៥៧០',
                    ],
                    $footerDateRow => [
                        'A' => 'ថ្ងៃទី......... ខែ............ ឆ្នាំ.........',
                        'D' => 'ថ្ងៃទី......... ខែ............ ឆ្នាំ.........',
                        'T' => 'ស្ទឹងត្រែង ថ្ងៃទី......... ខែ............ ឆ្នាំ' . $khmerYear,
                    ],
                    $footerRoleRow => [
                        'A' => 'ប្រធានមន្ទីរ',
                        'D' => 'ប្រធាន' . $currentUnitName,
                        'T' => 'អ្នករៀបចំផែនការ',
                    ],
                ];

                foreach ($footerValues as $row => $values) {
                    foreach ($footerBlocks as $block) {
                        $start = $block['start'];
                        $end = $block['end'];
                        $sheet->setCellValue($start . $row, $values[$start] ?? '');
                        $sheet->mergeCells($start . $row . ':' . $end . $row);
                    }
                }

                $sheet->getStyle($footerArea)->applyFromArray([
                    'font' => [
                        'name' => 'Khmer OS Siemreap',
                        'size' => 11,
                        'bold' => false,
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_NONE,
                        ],
                        'outline' => [
                            'borderStyle' => Border::BORDER_NONE,
                        ],
                        'inside' => [
                            'borderStyle' => Border::BORDER_NONE,
                        ],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_NONE,
                    ],
                ]);

                $sheet->getStyle('A' . $footerTitleRow . ':AJ' . $footerDateRow)->getFont()
                    ->setName('Khmer OS Siemreap')
                    ->setSize(11)
                    ->setBold(false);
                $sheet->getStyle('A' . $footerRoleRow . ':AJ' . $footerRoleRow)->getFont()
                    ->setName('Khmer M1')
                    ->setSize(11)
                    ->setBold(false);
            },
        ];
    }
}
