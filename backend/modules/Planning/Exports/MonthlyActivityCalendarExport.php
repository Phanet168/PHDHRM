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

class MonthlyActivityCalendarExport implements FromView, WithEvents
{
    public function __construct(
        private readonly mixed $rows,
        private readonly mixed $timelineMonths,
        private readonly int $reportYear,
        private readonly string $reportOrgName,
        private readonly array $orgHeaderLines,
    ) {
    }

    public function view(): View
    {
        return view('planning::reports.exports.monthly-excel', [
            'rows' => $this->rows,
            'timelineMonths' => $this->timelineMonths,
            'reportYear' => $this->reportYear,
            'reportOrgName' => $this->reportOrgName,
            'orgHeaderLines' => $this->orgHeaderLines,
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $spreadsheet = $sheet->getParent();

                $timelineMonthsCount = $this->timelineMonths->count();
                $totalColumns = 3 + $timelineMonthsCount;
                $lastColumn = Coordinate::stringFromColumnIndex($totalColumns);
                $extraOrgLineCount = max(count($this->orgHeaderLines) - 1, 0);
                $currentUnitName = collect($this->orgHeaderLines ?: [$this->reportOrgName])->filter()->last() ?: $this->reportOrgName;
                $khmerYear = strtr((string) $this->reportYear, ['0' => '០', '1' => '១', '2' => '២', '3' => '៣', '4' => '៤', '5' => '៥', '6' => '៦', '7' => '៧', '8' => '៨', '9' => '៩']);
                $titleRow = 4 + $extraOrgLineCount;
                $headerRow = 6 + $extraOrgLineCount;
                $firstDataRow = $headerRow + 1;
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
                $printLastRow = $footerRoleRow + 2;
                $leftBlockEndColumn = 'B';
                $centerBlockStartColumn = 'C';
                $centerBlockEndColumn = 'I';
                $rightBlockStartColumn = 'J';

                $sheet->setTitle('ផែនការប្រចាំខែ');

                $spreadsheet->getDefaultStyle()->getFont()->setName('Khmer OS Siemreap')->setSize(10);
                $spreadsheet->getDefaultStyle()->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);

                $sheet->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setPaperSize(PageSetup::PAPERSIZE_A4)
                    ->setFitToWidth(1)
                    ->setFitToHeight(0)
                    ->setHorizontalCentered(true)
                    ->setPrintArea('A1:' . $lastColumn . $printLastRow)
                    ->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow);
                $sheet->getPageMargins()
                    ->setTop(0.22)
                    ->setRight(0.12)
                    ->setBottom(0.22)
                    ->setLeft(0.12)
                    ->setHeader(0.15)
                    ->setFooter(0.15);
                $sheet->getSheetView()->setZoomScale(95);

                $sheet->getColumnDimension('A')->setWidth(36);
                $sheet->getColumnDimension('B')->setWidth(26);
                foreach (range(3, $totalColumns) as $columnIndex) {
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setWidth(8.5);
                }

                $sheet->getRowDimension(1)->setRowHeight(22);
                $sheet->getRowDimension(2)->setRowHeight(46);
                foreach (range(3, 5 + $extraOrgLineCount) as $row) {
                    $sheet->getRowDimension($row)->setRowHeight(24);
                }
                $sheet->getRowDimension($titleRow)->setRowHeight(34);
                $sheet->getRowDimension($headerRow)->setRowHeight(30);
                foreach (range($firstDataRow, $lastTableRow) as $row) {
                    $sheet->getRowDimension($row)->setRowHeight(-1);
                }

                $sheet->getStyle('A1:' . $lastColumn . $printLastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('A1:' . $lastColumn . $printLastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_NONE);
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

                $sheet->getStyle('A' . $headerRow . ':' . $lastColumn . $headerRow)->getFont()
                    ->setName('Khmer OS Siemreap')
                    ->setSize(10)
                    ->setBold(true);
                $sheet->getStyle('A' . $headerRow . ':' . $lastColumn . $headerRow)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A' . $headerRow . ':' . $lastColumn . $headerRow)
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('F6FBF7');
                $sheet->getStyle('A' . $headerRow . ':' . $lastColumn . $lastTableRow)
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setARGB('111111');

                $sheet->getStyle('A' . $firstDataRow . ':B' . $lastTableRow)->getFont()
                    ->setName('Khmer OS Siemreap')
                    ->setSize(10);
                $sheet->getStyle('A' . $firstDataRow . ':B' . $lastTableRow)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                    ->setVertical(Alignment::VERTICAL_TOP)
                    ->setWrapText(true)
                    ->setShrinkToFit(false);
                $sheet->getStyle('C' . $firstDataRow . ':' . $lastColumn . $lastTableRow)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                for ($row = $firstDataRow; $row <= $lastTableRow; $row++) {
                    $value = trim((string) $sheet->getCell('A' . $row)->getValue());
                    if (str_starts_with($value, 'សូចនាករ៖')) {
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

                $footerArea = 'A' . $footerTitleRow . ':' . $lastColumn . ($footerRoleRow + 2);
                foreach ($sheet->getMergeCells() as $range) {
                    $bounds = Coordinate::rangeBoundaries($range);
                    if ($bounds[0][1] >= $footerTitleRow) {
                        $sheet->unmergeCells($range);
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
                        'allBorders' => ['borderStyle' => Border::BORDER_NONE],
                        'outline' => ['borderStyle' => Border::BORDER_NONE],
                        'inside' => ['borderStyle' => Border::BORDER_NONE],
                    ],
                    'fill' => ['fillType' => Fill::FILL_NONE],
                ]);

                foreach (range($footerTitleRow, $footerRoleRow + 2) as $row) {
                    $sheet->getRowDimension($row)->setRowHeight(24);
                }

                $footerBlocks = [
                    ['start' => 'A', 'end' => 'B'],
                    ['start' => 'C', 'end' => 'I'],
                    ['start' => 'J', 'end' => 'O'],
                ];

                foreach ($footerBlocks as $block) {
                    $sheet->mergeCells($block['start'] . $footerTitleRow . ':' . $block['end'] . $footerTitleRow);
                    $sheet->mergeCells($block['start'] . $footerDateRow . ':' . $block['end'] . $footerDateRow);
                    $sheet->mergeCells($block['start'] . $footerRoleRow . ':' . $block['end'] . $footerRoleRow);
                }

                $sheet->setCellValue('A' . $footerTitleRow, 'បានឃើញ និង..............');
                $sheet->setCellValue('C' . $footerTitleRow, 'បានឃើញ និង..............');
                $sheet->setCellValue('J' . $footerTitleRow, 'ថ្ងៃ.......................ខែ.................ឆ្នាំមមី អដ្ឋស័ក ព.ស.២៥៧០');

                $sheet->setCellValue('A' . $footerDateRow, 'ថ្ងៃទី......... ខែ............ ឆ្នាំ.........');
                $sheet->setCellValue('C' . $footerDateRow, 'ថ្ងៃទី......... ខែ............ ឆ្នាំ.........');
                $sheet->setCellValue('J' . $footerDateRow, 'ស្ទឹងត្រែង ថ្ងៃទី......... ខែ............ ឆ្នាំ' . $khmerYear);

                $sheet->setCellValue('A' . $footerRoleRow, 'ប្រធានមន្ទីរ');
                $sheet->setCellValue('C' . $footerRoleRow, 'ប្រធាន' . $currentUnitName);
                $sheet->setCellValue('J' . $footerRoleRow, 'អ្នករៀបចំផែនការ');

                $sheet->getStyle('A' . $footerTitleRow . ':O' . $footerDateRow)->getFont()
                    ->setName('Khmer OS Siemreap')
                    ->setSize(11)
                    ->setBold(false);
                $sheet->getStyle('A' . $footerRoleRow . ':O' . $footerRoleRow)->getFont()
                    ->setName('Khmer M1')
                    ->setSize(11)
                    ->setBold(false);

                $sheet->getStyle('A' . $footerTitleRow . ':O' . ($footerRoleRow + 2))->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);
            },
        ];
    }
}
