<?php

namespace Modules\HumanResource\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use Modules\Setting\Entities\Application;
use Intervention\Image\Facades\Image;
use PPhatDev\LunarDate\KhmerDate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\BaseDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeeListOfficialExport implements FromArray, WithStyles, WithEvents, WithColumnWidths, WithDrawings
{
    protected Collection $employees;
    protected array $meta;
    protected bool $includeEmployeePhotos;
    protected string $exportRunId;
    protected array $groupHeaderRows = [];
    protected array $employeePhotoRows = [];
    protected array $temporaryPhotoFiles = [];
    protected array $temporaryPhotoCache = [];
    protected int $tableLastRow = 11;
    protected int $lastRow = 11;
    protected int $footerDateRow = 0;
    protected int $footerDateDetailRow = 0;
    protected int $footerApprovalRow = 0;
    protected int $footerHeadTitleRow = 0;
    protected int $footerHeadSignRow = 0;
    protected int $footerHrSignRow = 0;

    public function __construct(Collection $employees, array $meta = [], bool $includeEmployeePhotos = true)
    {
        $this->employees = $employees;
        $this->includeEmployeePhotos = $includeEmployeePhotos;
        $this->exportRunId = (string) uniqid('emp_export_', true);
        $this->meta = array_merge([
            'admin_text' => "\u{179a}\u{178a}\u{17d2}\u{178b}\u{1794}\u{17b6}\u{179b}\u{1781}\u{17c1}\u{178f}\u{17d2}\u{178f}\u{179f}\u{17d2}\u{1791}\u{17b9}\u{1784}\u{178f}\u{17d2}\u{179a}\u{17c2}\u{1784}",
            'unit_text' => "\u{1798}\u{1793}\u{17d2}\u{1791}\u{17b8}\u{179a}\u{179f}\u{17bb}\u{1781}\u{17b6}\u{1797}\u{17b7}\u{1794}\u{17b6}\u{179b}\u{1793}\u{17c3}\u{179a}\u{178a}\u{17d2}\u{178b}\u{1794}\u{17b6}\u{179b}\u{1781}\u{17c1}\u{178f}\u{17d2}\u{178f}",
            'title_text' => "\u{178f}\u{17b6}\u{179a}\u{17b6}\u{1784}\u{1794}\u{1789}\u{17d2}\u{1787}\u{17b8}\u{179a}\u{17b6}\u{1799}\u{1793}\u{17b6}\u{1798}\u{1798}\u{1793}\u{17d2}\u{179a}\u{17d2}\u{178f}\u{17b8}\u{179a}\u{17b6}\u{1787}\u{1780}\u{17b6}\u{179a}\u{1780}\u{17c6}\u{1796}\u{17bb}\u{1784}\u{1794}\u{1798}\u{17d2}\u{179a}\u{17be}\u{1780}\u{17b6}\u{179a}\u{1784}\u{17b6}\u{179a}",
            'location_text' => "\u{179f}\u{17d2}\u{1791}\u{17b9}\u{1784}\u{178f}\u{17d2}\u{179a}\u{17c2}\u{1784}",
            'approval_text' => "\u{1794}\u{17d2}\u{179a}\u{1792}\u{17b6}\u{1793}\u{1798}\u{1793}\u{17d2}\u{1791}\u{17b8}\u{179a}\u{179f}\u{17bb}\u{1781}\u{17b6}\u{1797}\u{17b7}\u{1794}\u{17b6}\u{179b}",
            'hr_manager_text' => "\u{1798}\u{1793}\u{17d2}\u{179a}\u{17d2}\u{178f}\u{17b8}\u{1782}\u{17d2}\u{179a}\u{1794}\u{17cb}\u{1782}\u{17d2}\u{179a}\u{1784}\u{1794}\u{17bb}\u{1782}\u{17d2}\u{1782}\u{179b}\u{17b7}\u{1780}",
        ], $meta);
    }

    public function __destruct()
    {
        foreach ($this->temporaryPhotoFiles as $path) {
            if (is_string($path) && $path !== '' && is_file($path)) {
                @unlink($path);
            }
        }
    }

    public function array(): array
    {
        $rows = [];
        $this->groupHeaderRows = [];
        $this->employeePhotoRows = [];

        $rows[] = [$this->kh('kingdom'), '', '', '', '', '', '', '', '', '', '', ''];
        $rows[] = [$this->kh('nation_religion_king'), '', '', '', '', '', '', '', '', '', '', ''];
        $rows[] = ['6', '', '', '', '', '', '', '', '', '', '', ''];
        $rows[] = $this->blankRow();
        $rows[] = $this->blankRow();
        $rows[] = [$this->sanitizeText((string) $this->meta['admin_text']), '', '', '', '', '', '', '', '', '', '', ''];
        $rows[] = [$this->sanitizeText((string) $this->meta['unit_text']), '', '', '', '', '', '', '', '', '', '', ''];
        $rows[] = $this->blankRow();
        $rows[] = [$this->sanitizeText((string) $this->meta['title_text']), '', '', '', '', '', '', '', '', '', '', ''];
        $rows[] = $this->blankRow();

        $rows[] = [
            $this->kh('total_no'),
            $this->kh('no'),
            $this->kh('official_id'),
            $this->kh('full_name'),
            $this->kh('gender'),
            $this->kh('dob'),
            $this->kh('position'),
            $this->kh('pay_grade'),
            $this->kh('service_start'),
            $this->kh('retirement_date'),
            $this->kh('phone'),
            $this->kh('photo'),
        ];

        $i = 1;
        $tree = $this->buildUnitTree($this->employees);
        $this->appendTreeRows($rows, $tree, $i, 0, '');

        $this->tableLastRow = count($rows);

        $rows[] = $this->blankRow();

        $rows[] = ['', '', '', '', '', '', '', '', '', $this->khmerLunarDateText(), '', ''];
        $this->footerDateRow = count($rows);

        $rows[] = ['', '', '', '', '', '', '', '', '', $this->khmerSolarDateText(), '', ''];
        $this->footerDateDetailRow = count($rows);

        $rows[] = [$this->kh('seen'), '', '', '', '', '', '', '', '', (string) $this->meta['hr_manager_text'], '', ''];
        $this->footerApprovalRow = count($rows);

        $rows[] = [(string) $this->meta['approval_text'], '', '', '', '', '', '', '', '', '', '', ''];
        $this->footerHeadTitleRow = count($rows);

        $rows[] = $this->blankRow();
        $rows[] = $this->blankRow();

        $rows[] = $this->blankRow();
        $this->footerHeadSignRow = count($rows);

        $rows[] = $this->blankRow();
        $this->footerHrSignRow = count($rows);

        $this->lastRow = count($rows);

        return $rows;
    }

    public function drawings(): array
    {
        $drawings = [];

        $logoPath = $this->resolveLogoPath();
        if ($logoPath) {
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
            $drawings[] = $drawing;
        }

        if ($this->includeEmployeePhotos) {
            foreach ($this->employeePhotoRows as $row => $employee) {
                $photoPath = $this->resolveEmployeePhotoPath($employee);
                if (!$photoPath) {
                    continue;
                }

                $drawing = new Drawing();
                $drawing->setName('Employee Photo ' . $row);
                $drawing->setDescription('Employee Photo');
                $drawing->setPath($photoPath);
                $drawing->setCoordinates('L' . $row);
                $drawing->setCoordinates2('L' . $row);
                $drawing->setOffsetX(0);
                $drawing->setOffsetY(0);
                $drawing->setOffsetX2(0);
                $drawing->setOffsetY2(0);
                $drawing->setEditAs(BaseDrawing::EDIT_AS_TWOCELL);
                $drawing->setResizeProportional(true);
                $drawing->setWidth(104);
                $drawings[] = $drawing;
            }
        }

        return $drawings;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => false, 'size' => 12, 'name' => 'Khmer M1', 'color' => ['rgb' => '002060']]],
            2 => ['font' => ['bold' => false, 'size' => 12, 'name' => 'Khmer M1', 'color' => ['rgb' => '002060']]],
            3 => ['font' => ['bold' => false, 'size' => 48, 'name' => 'Tacteing', 'color' => ['rgb' => '002060']]],
            6 => ['font' => ['bold' => false, 'size' => 12, 'name' => 'Khmer M1', 'color' => ['rgb' => '002060']]],
            7 => ['font' => ['bold' => false, 'size' => 12, 'name' => 'Khmer M1', 'color' => ['rgb' => '002060']]],
            9 => ['font' => ['bold' => false, 'size' => 14, 'name' => 'Khmer M1', 'color' => ['rgb' => '002060']]],
            11 => ['font' => ['bold' => false, 'size' => 11, 'name' => 'Khmer M1', 'color' => ['rgb' => '002060']]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $sheet->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setPaperSize(PageSetup::PAPERSIZE_A4)
                    ->setFitToWidth(1)
                    ->setFitToHeight(0)
                    ->setHorizontalCentered(true);
                $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(11, 11);

                $sheet->getPageMargins()
                    ->setLeft(0.43)
                    ->setRight(0.21)
                    ->setTop(0.29)
                    ->setBottom(0.46)
                    ->setHeader(0.22)
                    ->setFooter(0.30);

                $sheet->mergeCells('A1:L1');
                $sheet->mergeCells('A2:L2');
                $sheet->mergeCells('A3:L3');
                $sheet->mergeCells('A6:E6');
                $sheet->mergeCells('A7:E7');
                $sheet->mergeCells('A9:L9');

                foreach ($this->groupHeaderRows as $groupRow) {
                    $sheet->mergeCells("A{$groupRow}:L{$groupRow}");
                }

                if ($this->footerDateRow > 0) {
                    $sheet->mergeCells('J' . $this->footerDateRow . ':L' . $this->footerDateRow);
                    $sheet->mergeCells('J' . $this->footerDateDetailRow . ':L' . $this->footerDateDetailRow);
                    $sheet->mergeCells('A' . $this->footerApprovalRow . ':D' . $this->footerApprovalRow);
                    $sheet->mergeCells('J' . $this->footerApprovalRow . ':L' . $this->footerApprovalRow);
                    $sheet->mergeCells('A' . $this->footerHeadTitleRow . ':D' . $this->footerHeadTitleRow);
                    $sheet->mergeCells('A' . $this->footerHeadSignRow . ':D' . $this->footerHeadSignRow);
                    $sheet->mergeCells('J' . $this->footerHrSignRow . ':L' . $this->footerHrSignRow);
                }

                foreach ([1, 2, 3, 9] as $row) {
                    $sheet->getStyle("A{$row}:L{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                }

                foreach ([6, 7] as $row) {
                    $sheet->getStyle("A{$row}:E{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                        ->setVertical(Alignment::VERTICAL_CENTER)
                        ->setWrapText(false);
                }

                $sheet->getStyle('A11:L11')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);

                if ($this->tableLastRow >= 11) {
                    $sheet->getStyle("A11:L{$this->tableLastRow}")->getBorders()->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN)
                        ->getColor()->setRGB('3B3B3B');

                    foreach (['A', 'B', 'C', 'E', 'F', 'H', 'I', 'J', 'K', 'L'] as $column) {
                        $sheet->getStyle("{$column}12:{$column}{$this->tableLastRow}")
                            ->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }
                }

                if ($this->footerDateRow > 0) {
                    foreach ([
                        "J{$this->footerDateRow}:L{$this->footerDateRow}",
                        "J{$this->footerDateDetailRow}:L{$this->footerDateDetailRow}",
                    ] as $range) {
                        $sheet->getStyle($range)->applyFromArray([
                            'font' => ['name' => 'Khmer OS Siemreap', 'size' => 12, 'bold' => false, 'color' => ['rgb' => '002060']],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                        ]);
                    }

                    $sheet->getStyle("A{$this->footerApprovalRow}:D{$this->footerApprovalRow}")->applyFromArray([
                        'font' => ['name' => 'Khmer OS Siemreap', 'size' => 12, 'bold' => false, 'color' => ['rgb' => '002060']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    $sheet->getStyle("J{$this->footerApprovalRow}:L{$this->footerApprovalRow}")->applyFromArray([
                        'font' => ['name' => 'Khmer M1', 'size' => 12, 'bold' => false, 'color' => ['rgb' => '002060']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    $sheet->getStyle("A{$this->footerHeadTitleRow}:D{$this->footerHeadTitleRow}")->applyFromArray([
                        'font' => ['name' => 'Khmer M1', 'size' => 12, 'bold' => false, 'color' => ['rgb' => '002060']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    $sheet->getStyle("A{$this->footerHeadSignRow}:D{$this->footerHeadSignRow}")->applyFromArray([
                        'font' => ['name' => 'Khmer OS Siemreap', 'size' => 11, 'bold' => false, 'color' => ['rgb' => '002060']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    $sheet->getStyle("J{$this->footerHrSignRow}:L{$this->footerHrSignRow}")->applyFromArray([
                        'font' => ['name' => 'Khmer OS Siemreap', 'size' => 11, 'bold' => false, 'color' => ['rgb' => '002060']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                }

                $sheet->getStyle("A10:L{$this->lastRow}")->getFont()->setName('Khmer OS Siemreap')->setSize(11);
                $sheet->getStyle("A1:L{$this->lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

                $sheet->getStyle('A1:L1')->getFont()->setName('Khmer M1')->setSize(12)->setBold(false)->getColor()->setRGB('002060');
                $sheet->getStyle('A2:L2')->getFont()->setName('Khmer M1')->setSize(12)->setBold(false)->getColor()->setRGB('002060');
                $sheet->getStyle('A3:L3')->getFont()->setName('Tacteing')->setSize(48)->setBold(false)->getColor()->setRGB('002060');
                $sheet->getStyle('A6:E6')->getFont()->setName('Khmer M1')->setSize(12)->setBold(false)->getColor()->setRGB('002060');
                $sheet->getStyle('A7:E7')->getFont()->setName('Khmer M1')->setSize(12)->setBold(false)->getColor()->setRGB('002060');
                $sheet->getStyle('A9:L9')->getFont()->setName('Khmer M1')->setSize(14)->setBold(false)->getColor()->setRGB('002060');
                $sheet->getStyle('A11:L11')->getFont()->setName('Khmer M1')->setBold(false);

                foreach ($this->groupHeaderRows as $groupRow) {
                    $sheet->getStyle("A{$groupRow}:L{$groupRow}")->getFont()
                        ->setName('Khmer M1')
                        ->setBold(false)
                        ->setSize(11);
                    $sheet->getStyle("A{$groupRow}:L{$groupRow}")->getFont()->getColor()->setRGB('1A4368');
                    $sheet->getStyle("A{$groupRow}:L{$groupRow}")->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('EAF3FC');
                    $sheet->getStyle("A{$groupRow}:L{$groupRow}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                }

                for ($r = 1; $r <= 10; $r++) {
                    $sheet->getRowDimension($r)->setRowHeight(27.6);
                }
                $sheet->getRowDimension(4)->setRowHeight(35);
                $sheet->getRowDimension(5)->setRowHeight(35);
                $sheet->getRowDimension(11)->setRowHeight(52.2);

                for ($row = 12; $row <= $this->tableLastRow; $row++) {
                    $hasPhoto = $this->includeEmployeePhotos && isset($this->employeePhotoRows[$row]);
                    $sheet->getRowDimension($row)->setRowHeight($hasPhoto ? 96 : 25);
                }

                if ($this->footerDateRow > 0) {
                    $sheet->getRowDimension($this->footerDateRow)->setRowHeight(24);
                    $sheet->getRowDimension($this->footerDateDetailRow)->setRowHeight(24);
                    $sheet->getRowDimension($this->footerApprovalRow)->setRowHeight(24);
                    $sheet->getRowDimension($this->footerHeadTitleRow)->setRowHeight(24);
                    $sheet->getRowDimension($this->footerHeadSignRow)->setRowHeight(26);
                    $sheet->getRowDimension($this->footerHrSignRow)->setRowHeight(26);
                }

                $sheet->setAutoFilter('A11:L11');
                if ($this->includeEmployeePhotos) {
                    $this->fitEmployeePhotosInColumn($sheet, 'L', 6, 6);
                }
                $this->centerHeaderLogoInAB($sheet, 4, 5, 0, 12);
            },
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 7,
            'B' => 6,
            'C' => 18,
            'D' => 20,
            'E' => 7,
            'F' => 15,
            'G' => 20,
            'H' => 12,
            'I' => 15,
            'J' => 15,
            'K' => 18,
            'L' => 18,
        ];
    }

    protected function blankRow(): array
    {
        return ['', '', '', '', '', '', '', '', '', '', '', ''];
    }

    protected function kh(string $key): string
    {
        $map = [
            'kingdom' => "\u{1796}\u{17d2}\u{179a}\u{17c7}\u{179a}\u{17b6}\u{1787}\u{17b6}\u{178e}\u{17b6}\u{1785}\u{1780}\u{17d2}\u{179a}\u{1780}\u{1798}\u{17d2}\u{1796}\u{17bb}\u{1787}\u{17b6}",
            'nation_religion_king' => "\u{1787}\u{17b6}\u{178f}\u{17b7} \u{179f}\u{17b6}\u{179f}\u{1793}\u{17b6} \u{1796}\u{17d2}\u{179a}\u{17c7}\u{1798}\u{17a0}\u{17b6}\u{1780}\u{17d2}\u{179f}\u{178f}\u{17d2}\u{179a}",
            'no' => "\u{179b}.\u{179a}",
            'total_no' => "\u{179b}.\u{179a}\n\u{179f}\u{179a}\u{17bb}\u{1794}",
            'official_id' => "\u{17a2}\u{178f}\u{17d2}\u{178f}\u{179b}\u{17c1}\u{1781}",
            'full_name' => "\u{1793}\u{17b6}\u{1798} \u{1782}\u{17c4}\u{178f}\u{17d2}\u{178f}\u{1793}\u{17b6}\u{1798}",
            'gender' => "\u{1797}\u{17c1}\u{1791}",
            'dob' => "\u{1790}\u{17d2}\u{1784}\u{17c3}\u{1781}\u{17c2}\u{1786}\u{17d2}\u{1793}\u{17b6}\u{17c6}\u{1780}\u{17c6}\u{178e}\u{17be}\u{178f}",
            'service_start' => "\u{1790}\u{17d2}\u{1784}\u{17c3} \u{1781}\u{17c2} \u{1786}\u{17d2}\u{1793}\u{17b6}\u{17c6}\n\u{1794}\u{1798}\u{17d2}\u{179a}\u{17be}\u{1780}\u{17b6}\u{179a}\u{1784}\u{17b6}\u{179a}",
            'retirement_date' => "\u{1790}\u{17d2}\u{1784}\u{17c3} \u{1781}\u{17c2} \u{1786}\u{17d2}\u{1793}\u{17b6}\u{17c6}\n\u{1785}\u{17bc}\u{179b}\u{1793}\u{17b7}\u{179c}\u{178f}\u{17d2}\u{178f}\u{1793}\u{17cd}",
            'tenure' => "\u{17a2}\u{178f}\u{17b8}\u{178f}\u{1797}\u{17b6}\u{1796}\u{1780}\u{17b6}\u{179a}\u{1784}\u{17b6}\u{179a}",
            'qualification' => "\u{1782}\u{17bb}\u{178e}\u{179c}\u{17bb}\u{178c}\u{17d2}\u{178d}\u{17b7}",
            'position' => "\u{1798}\u{17bb}\u{1781}\u{178a}\u{17c6}\u{178e}\u{17c2}\u{1784}",
            'pay_grade' => "\u{178b}\u{17b6}\u{1793}\u{1793}\u{17d2}\u{178f}\u{179a}\u{179f}\u{1780}\u{17d2}\u{178f}\u{17b7}\u{1793}\u{17b7}\u{1784}\u{1790}\u{17d2}\u{1793}\u{17b6}\u{1780}\u{17cb}",
            'phone' => "\u{179b}\u{17c1}\u{1781}\u{1791}\u{17bc}\u{179a}\u{179f}\u{1796}\u{17d2}\u{1791}",
            'photo' => "\u{179a}\u{17bc}\u{1794}\u{1790}\u{178f}",
            'seen' => "\u{1794}\u{17b6}\u{1793}\u{1783}\u{17be}\u{1789}",
            'male' => "\u{1794}\u{17d2}\u{179a}\u{17bb}\u{179f}",
            'female' => "\u{179f}\u{17d2}\u{179a}\u{17b8}",
            'total' => "\u{179f}\u{179a}\u{17bb}\u{1794}",
            'year' => "\u{1786}\u{17d2}\u{1793}\u{17b6}\u{17c6}",
            'month' => "\u{1781}\u{17c2}",
            'today' => "\u{1790}\u{17d2}\u{1784}\u{17c3}\u{1791}\u{17b8}",
        ];

        return $map[$key] ?? $key;
    }

    protected function khmerGender($employee): string
    {
        $g = mb_strtolower(trim((string) ($employee->gender?->gender_name ?? '')));
        if (in_array($g, ['male', 'm', 'ប្រុស'], true)) {
            return $this->kh('male');
        }

        if (in_array($g, ['female', 'f', 'ស្រី'], true)) {
            return $this->kh('female');
        }

        return $this->sanitizeText((string) ($employee->gender?->gender_name ?? '-'));
    }

    protected function payLevel($employee): string
    {
        $direct = trim((string) (
            $employee->employee_grade
            ?: ($employee->profileExtra->current_salary_type ?? null)
            ?: ''
        ));
        if ($direct !== '') {
            return $this->normalizePayCodeToKhmer($direct);
        }

        $current = $employee->currentPayGradeHistory?->payLevel;
        if ($current) {
            $name = trim((string) ($current->level_name_km ?? ''));
            if ($name !== '') {
                return $this->sanitizeText($name);
            }
            $code = trim((string) ($current->level_code ?? ''));
            if ($code !== '') {
                return $this->normalizePayCodeToKhmer($code);
            }
        }

        $latest = $employee->latestPayGradeHistory?->payLevel;
        if ($latest) {
            $name = trim((string) ($latest->level_name_km ?? ''));
            if ($name !== '') {
                return $this->sanitizeText($name);
            }
            $code = trim((string) ($latest->level_code ?? ''));
            if ($code !== '') {
                return $this->normalizePayCodeToKhmer($code);
            }
        }

        $legacy = trim((string) ($employee->employee_grade ?? ''));
        if ($legacy === '') {
            return '-';
        }

        return $this->normalizePayCodeToKhmer($legacy);
    }

    protected function normalizePayCodeToKhmer(string $value): string
    {
        $clean = trim($value);
        if ($clean === '') {
            return '-';
        }

        return strtr(strtoupper($clean), [
            'A' => "\u{1780}",
            'B' => "\u{1781}",
            'C' => "\u{1782}",
            'D' => "\u{1783}",
            'E' => "\u{1784}",
            'F' => "\u{1785}",
            'G' => "\u{1786}",
            'H' => "\u{1787}",
            '0' => "\u{17e0}",
            '1' => "\u{17e1}",
            '2' => "\u{17e2}",
            '3' => "\u{17e3}",
            '4' => "\u{17e4}",
            '5' => "\u{17e5}",
            '6' => "\u{17e6}",
            '7' => "\u{17e7}",
            '8' => "\u{17e8}",
            '9' => "\u{17e9}",
        ]);
    }

    protected function toKhmerDigits(string $value): string
    {
        return strtr($value, [
            '0' => "\u{17e0}",
            '1' => "\u{17e1}",
            '2' => "\u{17e2}",
            '3' => "\u{17e3}",
            '4' => "\u{17e4}",
            '5' => "\u{17e5}",
            '6' => "\u{17e6}",
            '7' => "\u{17e7}",
            '8' => "\u{17e8}",
            '9' => "\u{17e9}",
        ]);
    }

    protected function khmerLunarDateText(): string
    {
        $fallback = $this->kh('today') . '........ ' . $this->kh('month') . '........ ' . $this->kh('year') . '........ ' . "\u{1796}.\u{179f}. ........";

        try {
            $khmerDate = new KhmerDate(Carbon::today()->toDateString());
            $text = trim((string) $khmerDate->toLunarDate());

            return $text !== '' ? $this->sanitizeText($text) : $fallback;
        } catch (\Throwable $exception) {
            return $fallback;
        }
    }

    protected function khmerSolarDateText(): string
    {
        $months = [
            1 => "\u{1798}\u{1780}\u{179a}\u{17b6}",
            2 => "\u{1780}\u{17bb}\u{1798}\u{17d2}\u{1797}\u{17c8}",
            3 => "\u{1798}\u{17b8}\u{1793}\u{17b6}",
            4 => "\u{1798}\u{17c1}\u{179f}\u{17b6}",
            5 => "\u{17a7}\u{179f}\u{1797}\u{17b6}",
            6 => "\u{1798}\u{17b7}\u{1790}\u{17bb}\u{1793}\u{17b6}",
            7 => "\u{1780}\u{1780}\u{17d2}\u{1780}\u{178a}\u{17b6}",
            8 => "\u{179f}\u{17b8}\u{17a0}\u{17b6}",
            9 => "\u{1780}\u{1789}\u{17d2}\u{1789}\u{17b6}",
            10 => "\u{178f}\u{17bb}\u{179b}\u{17b6}",
            11 => "\u{179c}\u{17b7}\u{1785}\u{17d2}\u{1786}\u{17b7}\u{1780}\u{17b6}",
            12 => "\u{1792}\u{17d2}\u{1793}\u{17bc}",
        ];

        $today = Carbon::today();
        $location = trim((string) ($this->meta['location_text'] ?? $this->khmerText('stung_treng')));
        $monthKh = $months[(int) $today->month] ?? '';

        return sprintf(
            '%s, %s%s %s%s %s %s',
            $location !== '' ? $location : $this->khmerText('stung_treng'),
            $this->kh('today'),
            $this->toKhmerDigits($today->format('d')),
            $this->kh('month'),
            $monthKh,
            $this->kh('year'),
            $this->toKhmerDigits($today->format('Y'))
        );
    }

    protected function khmerText(string $key): string
    {
        $map = [
            'stung_treng' => "\u{179f}\u{17d2}\u{1791}\u{17b9}\u{1784}\u{178f}\u{17d2}\u{179a}\u{17c2}\u{1784}",
        ];

        return $map[$key] ?? $key;
    }

    protected function buildUnitTree(Collection $employees): array
    {
        $tree = [];

        foreach ($employees as $employee) {
            $path = trim((string) ($employee->display_unit_path ?? ($employee->sub_department?->department_name ?? $employee->department?->department_name ?? '-')));
            $segments = array_values(array_filter(array_map('trim', explode('|', $path)), static function ($part) {
                return $part !== '';
            }));

            if (empty($segments)) {
                $segments = ['-'];
            }

            $cursor = &$tree;
            $nodeRef = null;
            foreach ($segments as $segment) {
                if (!isset($cursor[$segment])) {
                    $cursor[$segment] = [
                        'name' => $segment,
                        'children' => [],
                        'employees' => [],
                    ];
                }
                $nodeRef = &$cursor[$segment];
                $cursor = &$cursor[$segment]['children'];
            }

            if (is_array($nodeRef)) {
                $nodeRef['employees'][] = $employee;
            }
            unset($cursor, $nodeRef);
        }

        return $tree;
    }

    protected function appendTreeRows(array &$rows, array $tree, int &$sequence, int $depth = 0, string $numberPrefix = ''): array
    {
        $aggregate = ['total' => 0, 'male' => 0, 'female' => 0];
        $siblingIndex = 0;

        foreach ($tree as $node) {
            $siblingIndex++;
            $numbering = $numberPrefix === '' ? (string) $siblingIndex : ($numberPrefix . '.' . $siblingIndex);
            $khNumbering = $this->toKhmerDigits($numbering);
            $stats = $this->calculateNodeStats($node);

            $indent = str_repeat('    ', max(0, $depth));
            $prefix = $depth > 0 ? '- ' : '';
            $rows[] = [
                sprintf(
                    '%s%s%s %s (%s %s | %s %s | %s %s)',
                    $indent,
                    $prefix,
                    $khNumbering,
                    $this->sanitizeText((string) ($node['name'] ?? '-')),
                    $this->kh('total'),
                    $this->toKhmerDigits((string) $stats['total']),
                    $this->kh('male'),
                    $this->toKhmerDigits((string) $stats['male']),
                    $this->kh('female'),
                    $this->toKhmerDigits((string) $stats['female'])
                ),
                '', '', '', '', '', '', '', '', '', '', '',
            ];
            $this->groupHeaderRows[] = count($rows);

            $groupSeq = 1;
            foreach ($node['employees'] as $employee) {
                $serviceStartDate = $this->resolveServiceStartDate($employee);
                $overallSeq = $sequence++;
                $rows[] = [
                    $this->toKhmerDigits((string) $overallSeq),
                    $this->toKhmerDigits((string) $groupSeq++),
                    $this->toKhmerDigits($this->sanitizeText((string) ($employee->official_id_10 ?: '-'))),
                    $this->sanitizeText((string) ($employee->full_name ?: trim((string) (($employee->last_name ?? '') . ' ' . ($employee->first_name ?? ''))))),
                    $this->khmerGender($employee),
                    $this->formatDate($employee->date_of_birth ?? null),
                    $this->sanitizeText((string) ($employee->position?->position_name_km ?? $employee->position?->position_name ?? '-')),
                    $this->payLevel($employee),
                    $this->formatDate($serviceStartDate),
                    $this->formatDate($this->resolveRetirementDate($employee)),
                    $this->toKhmerDigits($this->sanitizeText($this->resolvePhoneNumber($employee))),
                    '',
                ];
                if ($this->includeEmployeePhotos) {
                    $this->employeePhotoRows[count($rows)] = $employee;
                }
            }

            $this->appendTreeRows($rows, $node['children'], $sequence, $depth + 1, $numbering);

            $aggregate['total'] += $stats['total'];
            $aggregate['male'] += $stats['male'];
            $aggregate['female'] += $stats['female'];
        }

        return $aggregate;
    }

    protected function calculateNodeStats(array $node): array
    {
        $stats = ['total' => 0, 'male' => 0, 'female' => 0];

        foreach (($node['employees'] ?? []) as $employee) {
            $gender = $this->khmerGender($employee);
            $stats['total']++;
            if ($gender === $this->kh('male')) {
                $stats['male']++;
            } elseif ($gender === $this->kh('female')) {
                $stats['female']++;
            }
        }

        foreach (($node['children'] ?? []) as $child) {
            $childStats = $this->calculateNodeStats($child);
            $stats['total'] += $childStats['total'];
            $stats['male'] += $childStats['male'];
            $stats['female'] += $childStats['female'];
        }

        return $stats;
    }

    protected function formatDate($value): string
    {
        if (!$value) {
            return '-';
        }

        try {
            return $this->toKhmerDigits(Carbon::parse($value)->format('d.m.Y'));
        } catch (\Throwable $e) {
            return $this->toKhmerDigits((string) $value);
        }
    }

    protected function resolveServiceStartDate($employee)
    {
        return $employee->service_start_date
            ?? $employee->service_date
            ?? $employee->joining_date
            ?? $employee->date_of_joining
            ?? $employee->date_of_join
            ?? null;
    }

    protected function resolveRetirementDate($employee)
    {
        if (!$employee->date_of_birth) {
            return null;
        }

        try {
            return Carbon::parse($employee->date_of_birth)
                ->addYears(60)
                ->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function serviceTenure($startDate): string
    {
        if (!$startDate) {
            return '-';
        }

        try {
            $start = Carbon::parse($startDate);
            $end = Carbon::today();

            if ($start->greaterThan($end)) {
                return '-';
            }

            $years = $start->diffInYears($end);
            $months = $start->copy()->addYears($years)->diffInMonths($end);

            return $this->toKhmerDigits((string) $years) . $this->kh('year') . ' ' . $this->toKhmerDigits((string) $months) . $this->kh('month');
        } catch (\Throwable $e) {
            return '-';
        }
    }

    protected function qualification($employee): string
    {
        $value = trim((string) (
            $employee->skill_name
            ?? ($employee->profileExtra?->current_work_skill ?? null)
            ?? ''
        ));

        return $value !== '' ? $this->sanitizeText($value) : '-';
    }

    protected function sanitizeText(string $value): string
    {
        return (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
    }

    protected function resolvePhoneNumber($employee): string
    {
        foreach ([
            $employee->phone ?? null,
            $employee->cell_phone ?? null,
            $employee->business_phone ?? null,
            $employee->home_phone ?? null,
            $employee->alternate_phone ?? null,
        ] as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return '-';
    }

    protected function resolveEmployeePhotoPath($employee): ?string
    {
        $candidates = array_values(array_unique(array_filter([
            trim((string) ($employee->profile_img_location ?? '')),
            trim((string) ($employee->profile_image ?? '')),
        ])));

        foreach ($candidates as $candidate) {
            $clean = ltrim(str_replace('\\', '/', $candidate), '/');
            if ($clean === '') {
                continue;
            }

            $paths = [
                storage_path('app/public/' . $clean),
                public_path('storage/' . $clean),
                public_path($clean),
            ];

            if (!str_contains($clean, '/')) {
                $paths[] = storage_path('app/public/employee/' . $clean);
                $paths[] = public_path('storage/employee/' . $clean);
            }

            foreach ($paths as $path) {
                if (is_string($path) && $path !== '' && is_file($path)) {
                    return $this->createTemporaryPhotoForExport($path);
                }
            }
        }

        return null;
    }

    protected function createTemporaryPhotoForExport(string $sourcePath): string
    {
        if (isset($this->temporaryPhotoCache[$sourcePath]) && is_file($this->temporaryPhotoCache[$sourcePath])) {
            return $this->temporaryPhotoCache[$sourcePath];
        }

        $tempDir = storage_path('app/tmp/export-photos');
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }

        $targetPath = $tempDir . DIRECTORY_SEPARATOR . $this->exportRunId . '_' . count($this->temporaryPhotoFiles) . '.jpg';

        try {
            $image = Image::make($sourcePath)
                ->orientate()
                ->resize(96, 96, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                })
                ->encode('png');

            $canvas = Image::canvas(96, 96, '#ffffff');
            $canvas->insert($image, 'center');
            $canvas->encode('jpg', 58)->save($targetPath);

            if (is_file($targetPath)) {
                $this->temporaryPhotoFiles[] = $targetPath;
                $this->temporaryPhotoCache[$sourcePath] = $targetPath;

                return $targetPath;
            }
        } catch (\Throwable $e) {
            // Fall back to original image if thumbnail generation fails.
        }

        return $sourcePath;
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

    protected function fitEmployeePhotosInColumn(Worksheet $sheet, string $column, int $paddingX = 6, int $paddingY = 6): void
    {
        if (empty($sheet->getDrawingCollection())) {
            return;
        }

        foreach ($sheet->getDrawingCollection() as $drawing) {
            $name = (string) $drawing->getName();
            if (!str_starts_with($name, 'Employee Photo ')) {
                continue;
            }

            $row = (int) substr($name, strlen('Employee Photo '));
            if ($row <= 0) {
                continue;
            }

            $this->fitDrawingWithinCell($sheet, $drawing, $column, $row, $paddingX, $paddingY);
        }
    }

    protected function fitDrawingWithinCell(Worksheet $sheet, Drawing $drawing, string $column, int $row, int $paddingX = 6, int $paddingY = 6): void
    {
        $path = (string) $drawing->getPath();
        if ($path === '' || !is_file($path)) {
            return;
        }

        $imageSize = @getimagesize($path);
        if (!is_array($imageSize) || empty($imageSize[0]) || empty($imageSize[1])) {
            return;
        }

        $originalWidth = (int) $imageSize[0];
        $originalHeight = (int) $imageSize[1];
        if ($originalWidth <= 0 || $originalHeight <= 0) {
            return;
        }

        $columnWidth = (float) $sheet->getColumnDimension($column)->getWidth();
        $cellWidthPx = max(1, (int) round($columnWidth * 7));

        $rowHeightPt = (float) $sheet->getRowDimension($row)->getRowHeight();
        if ($rowHeightPt <= 0) {
            $rowHeightPt = 15;
        }
        $cellHeightPx = max(1, (int) round($rowHeightPt * 96 / 72));

        $usableWidth = max(1, $cellWidthPx - ($paddingX * 2));
        $usableHeight = max(1, $cellHeightPx - ($paddingY * 2));
        $scale = min($usableWidth / $originalWidth, $usableHeight / $originalHeight);

        $targetWidth = max(1, (int) floor($originalWidth * $scale));
        $targetHeight = max(1, (int) floor($originalHeight * $scale));
        $offsetX = max(0, (int) floor(($cellWidthPx - $targetWidth) / 2));
        $offsetY = max(0, (int) floor(($cellHeightPx - $targetHeight) / 2));
        $offsetX2 = min($cellWidthPx, $offsetX + $targetWidth);
        $offsetY2 = min($cellHeightPx, $offsetY + $targetHeight);

        $drawing->setCoordinates($column . $row);
        // Use a two-cell anchor bound to the same cell so Excel treats the
        // photo as cell-attached while we still preserve aspect ratio visually.
        $drawing->setCoordinates2($column . $row);
        $drawing->setOffsetX($offsetX);
        $drawing->setOffsetY($offsetY);
        $drawing->setOffsetX2($offsetX2);
        $drawing->setOffsetY2($offsetY2);
        $drawing->setEditAs(BaseDrawing::EDIT_AS_TWOCELL);
        $drawing->setResizeProportional(false);
        $drawing->setWidth($targetWidth);
        $drawing->setHeight($targetHeight);
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
        } catch (\Throwable $e) {
            // Ignore.
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
