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
use PPhatDev\LunarDate\KhmerDate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeeListOfficialExport implements FromArray, WithStyles, WithEvents, WithColumnWidths, WithDrawings
{
    protected Collection $employees;
    protected array $meta;
    protected array $groupHeaderRows = [];
    protected int $tableLastRow = 11;
    protected int $lastRow = 11;
    protected int $footerDateRow = 0;
    protected int $footerDateDetailRow = 0;
    protected int $footerApprovalRow = 0;
    protected int $footerHeadTitleRow = 0;
    protected int $footerHeadSignRow = 0;
    protected int $footerHrSignRow = 0;

    public function __construct(Collection $employees, array $meta = [])
    {
        $this->employees = $employees;
        $this->meta = array_merge([
            'admin_text' => 'រដ្ឋបាលខេត្តស្ទឹងត្រែង',
            'unit_text' => 'មន្ទីរសុខាភិបាលនៃរដ្ឋបាលខេត្ត',
            'title_text' => 'តារាងបញ្ជីរាយនាមមន្ត្រីរាជការកំពុងបម្រើការងារ',
            'location_text' => 'ស្ទឹងត្រែង',
            'approval_text' => 'ប្រធានមន្ទីរសុខាភិបាល',
            'hr_manager_text' => 'មន្ត្រីគ្រប់គ្រងបុគ្គលិក',
        ], $meta);
    }

    public function array(): array
    {
        $rows = [];
        $lunarDate = $this->khmerLunarDateText();
        $solarDate = $this->khmerSolarDateText();

        $rows[] = ['ព្រះរាជាណាចក្រកម្ពុជា', '', '', '', '', '', '', '', '', '', '', ''];
        $rows[] = ['ជាតិ សាសនា ព្រះមហាក្សត្រ', '', '', '', '', '', '', '', '', '', '', ''];
        $rows[] = ['6', '', '', '', '', '', '', '', '', '', '', ''];
        $rows[] = $this->blankRow();
        $rows[] = $this->blankRow();
        $rows[] = [$this->sanitizeText((string) $this->meta['admin_text']), '', '', '', '', '', '', '', '', '', '', ''];
        $rows[] = [$this->sanitizeText((string) $this->meta['unit_text']), '', '', '', '', '', '', '', '', '', '', ''];
        $rows[] = $this->blankRow();
        $rows[] = [$this->sanitizeText((string) $this->meta['title_text']), '', '', '', '', '', '', '', '', '', '', ''];
        $rows[] = $this->blankRow();

        $rows[] = [
            'ល.រ សរុប',
            'ល.រ',
            'គោត្តនាម និងនាម',
            'ឈ្មោះជាអក្សរឡាតាំង',
            'ភេទ',
            'ថ្ងៃខែឆ្នាំកំណើត',
            'ថ្ងៃខែឆ្នាំចូលបម្រើការងារ',
            'អតីតភាពការងារ',
            'គុណវុឌ្ឍនិ',
            'តួនាទី',
            'ឋានន្តរស័ក្កនិងថ្នាក់',
            'លេខទូរសព្ទ',
        ];

        $i = 1;
        $tree = $this->buildUnitTree($this->employees);
        $this->appendTreeRows($rows, $tree, 0, $i, '');

        $this->tableLastRow = count($rows);

        $rows[] = $this->blankRow();

        $rows[] = ['', '', '', '', '', '', '', '', '', $lunarDate, '', ''];
        $this->footerDateRow = count($rows);

        $rows[] = ['', '', '', '', '', '', '', '', '', $solarDate, '', ''];
        $this->footerDateDetailRow = count($rows);

        $rows[] = ['ឯកភាព', '', '', '', '', '', '', '', '', (string) $this->meta['hr_manager_text'], '', ''];
        $this->footerApprovalRow = count($rows);

        $rows[] = [(string) $this->meta['approval_text'], '', '', '', '', '', '', '', '', '', '', ''];
        $this->footerHeadTitleRow = count($rows);

        $rows[] = $this->blankRow();
        $rows[] = $this->blankRow();

        $rows[] = ['', '', '', '', '', '', '', '', '', '', '', ''];
        $this->footerHeadSignRow = count($rows);

        $rows[] = ['', '', '', '', '', '', '', '', '', '', '', ''];
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

                    $sheet->getStyle("A12:A{$this->tableLastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("B12:B{$this->tableLastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("E12:E{$this->tableLastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("F12:F{$this->tableLastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("G12:G{$this->tableLastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("H12:H{$this->tableLastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                foreach ($this->groupHeaderRows as $groupRow) {
                    $sheet->getStyle("A{$groupRow}:L{$groupRow}")->getFont()
                        ->setName('Khmer M1')
                        ->setBold(false)
                        ->setSize(11);
                    $sheet->getStyle("A{$groupRow}:L{$groupRow}")->getFont()->getColor()->setRGB('1A4368');
                    $sheet->getStyle("A{$groupRow}:L{$groupRow}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('EAF3FC');
                    $sheet->getStyle("A{$groupRow}:L{$groupRow}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                }

                if ($this->footerDateRow > 0) {
                    $sheet->getStyle("J{$this->footerDateRow}:L{$this->footerDateRow}")->applyFromArray([
                        'font' => ['name' => 'Khmer OS Siemreap', 'size' => 12, 'bold' => false, 'color' => ['rgb' => '002060']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    $sheet->getStyle("J{$this->footerDateDetailRow}:L{$this->footerDateDetailRow}")->applyFromArray([
                        'font' => ['name' => 'Khmer OS Siemreap', 'size' => 12, 'bold' => false, 'color' => ['rgb' => '002060']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
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

                // Keep report-header fonts (Khmer M1 / Tacteing) and apply base font only to body/table area.
                $sheet->getStyle("A10:L{$this->lastRow}")->getFont()->setName('Khmer OS Siemreap')->setSize(11);
                $sheet->getStyle("A1:L{$this->lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

                $sheet->getStyle('A1:L1')->getFont()->setName('Khmer M1')->setSize(12)->setBold(false)->getColor()->setRGB('002060');
                $sheet->getStyle('A2:L2')->getFont()->setName('Khmer M1')->setSize(12)->setBold(false)->getColor()->setRGB('002060');
                $sheet->getStyle('A3:L3')->getFont()->setName('Tacteing')->setSize(48)->setBold(false)->getColor()->setRGB('002060');
                $sheet->getStyle('A6:E6')->getFont()->setName('Khmer M1')->setSize(12)->setBold(false)->getColor()->setRGB('002060');
                $sheet->getStyle('A7:E7')->getFont()->setName('Khmer M1')->setSize(12)->setBold(false)->getColor()->setRGB('002060');
                $sheet->getStyle('A9:L9')->getFont()->setName('Khmer M1')->setSize(12)->setBold(false)->getColor()->setRGB('002060');
                $sheet->getStyle('A9:L9')->getFont()->setSize(14);
                $sheet->getStyle('A11:L11')->getFont()->setName('Khmer M1')->setBold(false);

                foreach ($this->groupHeaderRows as $groupRow) {
                    $sheet->getStyle("A{$groupRow}:L{$groupRow}")->getFont()
                        ->setName('Khmer M1')
                        ->setBold(false)
                        ->setSize(11);
                }

                if ($this->footerApprovalRow > 0) {
                    $sheet->getStyle("J{$this->footerApprovalRow}:L{$this->footerApprovalRow}")->getFont()
                        ->setName('Khmer M1')
                        ->setBold(false)
                        ->setSize(12);
                    $sheet->getStyle("A{$this->footerHeadTitleRow}:D{$this->footerHeadTitleRow}")->getFont()
                        ->setName('Khmer M1')
                        ->setBold(false)
                        ->setSize(12);
                }

                for ($r = 1; $r <= 10; $r++) {
                    $sheet->getRowDimension($r)->setRowHeight(27.6);
                }
                $sheet->getRowDimension(4)->setRowHeight(35);
                $sheet->getRowDimension(5)->setRowHeight(35);
                $sheet->getRowDimension(11)->setRowHeight(52.2);

                for ($row = 12; $row <= $this->tableLastRow; $row++) {
                    $sheet->getRowDimension($row)->setRowHeight(25);
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

                $this->centerHeaderLogoInAB($sheet, 4, 5, 0, 12);
            },
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 7.11,
            'B' => 6,
            'C' => 19,
            'D' => 24,
            'E' => 8,
            'F' => 15,
            'G' => 15,
            'H' => 12,
            'I' => 18,
            'J' => 22,
            'K' => 18,
            'L' => 15,
        ];
    }

    protected function blankRow(): array
    {
        return ['', '', '', '', '', '', '', '', '', '', '', ''];
    }

    protected function khmerGender($employee): string
    {
        $g = mb_strtolower(trim((string) ($employee->gender?->gender_name ?? '')));
        if (in_array($g, ['male', 'm', 'ប្រុស'], true)) {
            return 'ប្រុស';
        }

        if (in_array($g, ['female', 'f', 'ស្រី'], true)) {
            return 'ស្រី';
        }

        return (string) ($employee->gender?->gender_name ?? '-');
    }

    protected function payLevel($employee): string
    {
        $current = $employee->currentPayGradeHistory?->payLevel;
        if ($current) {
            $name = trim((string) ($current->level_name_km ?? ''));
            if ($name !== '') {
                return $name;
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
                return $name;
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

        $letterMap = [
            'A' => 'ក',
            'B' => 'ខ',
            'C' => 'គ',
            'D' => 'ឃ',
            'E' => 'ង',
            'F' => 'ច',
            'G' => 'ឆ',
            'H' => 'ជ',
        ];

        $digitMap = [
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
        ];

        return strtr(strtoupper($clean), $letterMap + $digitMap);
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
            '%s, ថ្ងៃទី%s ខែ%s ឆ្នាំ %s',
            $location !== '' ? $location : 'ស្ទឹងត្រែង',
            $this->toKhmerDigits($today->format('d')),
            $monthKh,
            $this->toKhmerDigits($today->format('Y'))
        );
    }

    protected function buildUnitTree(Collection $employees): array
    {
        $tree = [];

        foreach ($employees as $employee) {
            $path = trim((string) ($employee->display_unit_path ?? ($employee->sub_department?->department_name ?? $employee->department?->department_name ?? '-')));
            $segments = array_values(array_filter(array_map('trim', explode('|', $path)), function ($part) {
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

    protected function appendTreeRows(array &$rows, array $tree, int $depth, int &$sequence, string $numberPrefix = ''): array
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
                    '%s%s%s %s (សរុប %s | ប្រុស %s | ស្រី %s)',
                    $indent,
                    $prefix,
                    $khNumbering,
                    $this->sanitizeText((string) ($node['name'] ?? '-')),
                    $this->toKhmerDigits((string) $stats['total']),
                    $this->toKhmerDigits((string) $stats['male']),
                    $this->toKhmerDigits((string) $stats['female'])
                ),
                '', '', '', '', '', '', '', '', '', '', '',
            ];
            $this->groupHeaderRows[] = count($rows);

            $groupSeq = 1;
            foreach ($node['employees'] as $employee) {
                $serviceStartDate = $this->resolveServiceStartDate($employee);
                $rows[] = [
                    (string) $sequence++,
                    (string) $groupSeq++,
                    $this->sanitizeText((string) ($employee->full_name ?: trim((string) (($employee->last_name ?? '') . ' ' . ($employee->first_name ?? ''))))),
                    $this->sanitizeText((string) ($employee->full_name_latin ?: trim((string) (($employee->last_name_latin ?? '') . ' ' . ($employee->first_name_latin ?? ''))))),
                    $this->khmerGender($employee),
                    $this->formatDate($employee->date_of_birth ?? null),
                    $this->formatDate($serviceStartDate),
                    $this->serviceTenure($serviceStartDate),
                    $this->qualification($employee),
                    $this->sanitizeText((string) ($employee->position?->position_name_km ?? $employee->position?->position_name ?? '-')),
                    $this->payLevel($employee),
                    $this->sanitizeText((string) ($employee->phone ?? '-')),
                ];
            }

            $this->appendTreeRows($rows, $node['children'], $depth + 1, $sequence, $numbering);

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
            if ($gender === 'ប្រុស') {
                $stats['male']++;
            } elseif ($gender === 'ស្រី') {
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
            return Carbon::parse($value)->format('d-m-Y');
        } catch (\Throwable $e) {
            return (string) $value;
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

            return $this->toKhmerDigits((string) $years) . 'ឆ្នាំ ' . $this->toKhmerDigits((string) $months) . 'ខែ';
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
        // Remove control characters that can produce invalid XML in XLSX shared strings.
        return (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
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
        } catch (\Throwable $e) {
            // ignore
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
