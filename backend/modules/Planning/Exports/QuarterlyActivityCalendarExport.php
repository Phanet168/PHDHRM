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

class QuarterlyActivityCalendarExport implements FromView, WithEvents
{
    public function __construct(
        private readonly mixed $rows,
        private readonly mixed $timelineQuarters,
        private readonly int $reportYear,
        private readonly string $reportOrgName,
        private readonly array $orgHeaderLines,
    ) {
    }

    public function view(): View
    {
        return view('planning::reports.exports.quarterly-excel', [
            'rows' => $this->rows,
            'timelineQuarters' => $this->timelineQuarters,
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

                $timelineCount = $this->timelineQuarters->count();
                $totalColumns = 5 + $timelineCount;
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

                $sheet->setTitle('ផែនការប្រចាំត្រីមាស');

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

                $sheet->getColumnDimension('A')->setWidth(38);
                $sheet->getColumnDimension('B')->setWidth(24);
                foreach (range(3, 6) as $columnIndex) {
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setWidth(9.5);
                }
                $sheet->getColumnDimension('G')->setWidth(14);
                $sheet->getColumnDimension('H')->setWidth(24);
                $sheet->getColumnDimension('I')->setWidth(22);

                $sheet->getRowDimension(1)->setRowHeight(22);
                $sheet->getRowDimension(2)->setRowHeight(46);
                foreach (range(3, 5 + $extraOrgLineCount) as $row) {
                    $sheet->getRowDimension($row)->setRowHeight(24);
                }
                $sheet->getRowDimension($titleRow)->setRowHeight(34);
                $sheet->getRowDimension($headerRow)->setRowHeight(30);

                $sheet->getStyle('A1:' . $lastColumn . $printLastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('A1:' . $lastColumn . $printLastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_NONE);
                $sheet->getStyle('A1:' . $lastColumn . $printLastRow)->getFill()->setFillType(Fill::FILL_NONE);

                $sheet->getStyle('A1:B' . (2 + $extraOrgLineCount))->getFont()->setName('Khmer M1')->setSize(11)->setBold(false);
                $sheet->getStyle('A1:B' . (2 + $extraOrgLineCount))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_BOTTOM);
                $sheet->getStyle('C1:' . $lastColumn . '2')->getFont()->setName('Khmer M1')->setSize(14)->setBold(false);
                $sheet->getStyle('C1:' . $lastColumn . '2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A' . $titleRow . ':' . $lastColumn . $titleRow)->getFont()->setName('Khmer M1')->setSize(18)->setBold(false);
                $sheet->getStyle('A' . $titleRow . ':' . $lastColumn . $titleRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->getStyle('A' . $headerRow . ':' . $lastColumn . $headerRow)->getFont()->setName('Khmer OS Siemreap')->setSize(10)->setBold(true);
                $sheet->getStyle('A' . $headerRow . ':' . $lastColumn . $headerRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A' . $headerRow . ':' . $lastColumn . $headerRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('F6FBF7');
                $sheet->getStyle('A' . $headerRow . ':' . $lastColumn . $lastTableRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('111111');

                $sheet->getStyle('A' . $firstDataRow . ':B' . $lastTableRow)->getFont()->setName('Khmer OS Siemreap')->setSize(10);
                $sheet->getStyle('A' . $firstDataRow . ':B' . $lastTableRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
                $sheet->getStyle('C' . $firstDataRow . ':F' . $lastTableRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('G' . $firstDataRow . ':G' . $lastTableRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle('H' . $firstDataRow . ':I' . $lastTableRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setWrapText(true);

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
                        $sheet->getStyle('A' . $row . ':' . $lastColumn . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    }
                }

                $footerArea = 'A' . $footerTitleRow . ':' . $lastColumn . ($footerRoleRow + 2);
                $sheet->getStyle($footerArea)->applyFromArray([
                    'font' => ['name' => 'Khmer OS Siemreap', 'size' => 11, 'bold' => false],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_NONE]],
                    'fill' => ['fillType' => Fill::FILL_NONE],
                ]);

                foreach ([['A', 'B'], ['C', 'E'], ['F', $lastColumn]] as [$start, $end]) {
                    $sheet->mergeCells($start . $footerTitleRow . ':' . $end . $footerTitleRow);
                    $sheet->mergeCells($start . $footerDateRow . ':' . $end . $footerDateRow);
                    $sheet->mergeCells($start . $footerRoleRow . ':' . $end . $footerRoleRow);
                }

                $sheet->setCellValue('A' . $footerTitleRow, 'បានឃើញ និង..............');
                $sheet->setCellValue('C' . $footerTitleRow, 'បានឃើញ និង..............');
                $sheet->setCellValue('F' . $footerTitleRow, 'ថ្ងៃ.......................ខែ.................ឆ្នាំមមី អដ្ឋស័ក ព.ស.២៥៧០');
                $sheet->setCellValue('A' . $footerDateRow, 'ថ្ងៃទី......... ខែ............ ឆ្នាំ.........');
                $sheet->setCellValue('C' . $footerDateRow, 'ថ្ងៃទី......... ខែ............ ឆ្នាំ.........');
                $sheet->setCellValue('F' . $footerDateRow, 'ស្ទឹងត្រែង ថ្ងៃទី......... ខែ............ ឆ្នាំ' . $khmerYear);
                $sheet->setCellValue('A' . $footerRoleRow, 'ប្រធានមន្ទីរ');
                $sheet->setCellValue('C' . $footerRoleRow, 'ប្រធាន' . $currentUnitName);
                $sheet->setCellValue('F' . $footerRoleRow, 'អ្នករៀបចំផែនការ');

                $sheet->getStyle('A' . $footerTitleRow . ':' . $lastColumn . $footerDateRow)->getFont()->setName('Khmer OS Siemreap')->setSize(11)->setBold(false);
                $sheet->getStyle('A' . $footerRoleRow . ':' . $lastColumn . $footerRoleRow)->getFont()->setName('Khmer M1')->setSize(11)->setBold(false);
            },
        ];
    }
}
