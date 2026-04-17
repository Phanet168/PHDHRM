<?php

namespace Modules\HumanResource\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\GovPayLevel;
use Modules\Setting\Entities\Application;
use PPhatDev\LunarDate\KhmerDate;
use PhpOffice\PhpSpreadsheet\Shared\Drawing as SharedDrawing;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RetirementListExport implements FromArray, WithStyles, WithEvents, WithColumnWidths, WithDrawings
{
    protected Collection $employees;
    protected int $startYear;
    protected int $endYear;
    protected array $meta;

    protected array $sectionRows = [];
    protected int $tableLastRow = 11;
    protected int $lastRow = 11;

    protected int $footerDateRow = 0;
    protected int $footerDateDetailRow = 0;
    protected int $footerApprovalRow = 0;
    protected int $footerHeadTitleRow = 0;
    protected int $footerHeadSignRow = 0;
    protected int $footerHrSignRow = 0;

    public function __construct(Collection $employees, int $startYear, int $endYear, array $meta = [])
    {
        $this->employees = $employees;
        $this->startYear = $startYear;
        $this->endYear = $endYear;
        $this->meta = array_merge([
            'admin_text' => 'រដ្ឋបាលខេត្តស្ទឹងត្រែង',
            'unit_text' => 'មន្ទីរសុខាភិបាលនៃរដ្ឋបាលខេត្ត',
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

        $rows[] = ['ព្រះរាជាណាចក្រកម្ពុជា', '', '', '', '', '', '', ''];
        $rows[] = ['ជាតិ សាសនា ព្រះមហាក្សត្រ', '', '', '', '', '', '', ''];
        $rows[] = ['6', '', '', '', '', '', '', ''];
        $rows[] = $this->blankRow();
        $rows[] = $this->blankRow();
        $rows[] = [(string) $this->meta['admin_text'], '', '', '', '', '', '', ''];
        $rows[] = [(string) $this->meta['unit_text'], '', '', '', '', '', '', ''];
        $rows[] = $this->blankRow();
        $rows[] = [
            sprintf(
                'តារាងបញ្ជីរាយនាមមន្ត្រីរាជការនៃ %s ដល់អាយុចូលនិវត្តន៍ពី ឆ្នាំ%s ដល់ %s',
                (string) $this->meta['unit_text'],
                $this->toKhmerDigits((string) $this->startYear),
                $this->toKhmerDigits((string) $this->endYear)
            ),
            '', '', '', '', '', '', '',
        ];
        $rows[] = $this->blankRow();
        $rows[] = [
            'ល.រ',
            'អត្តលេខមន្ត្រីរាជការ',
            'គោត្តនាម និងនាម',
            'ភេទ',
            'ថ្ងៃខែឆ្នាំកំណើត',
            'ថ្ងៃខែឆ្នាំចូលបម្រើការងារ',
            'ឋានន្តរស័ក្ក',
            'អង្គភាព',
        ];

        $grouped = $this->employees->groupBy(function (Employee $employee) {
            return (int) date('Y', strtotime((string) $employee->retirement_date));
        });

        $seq = 1;
        $sectionIndex = 1;

        for ($year = $this->startYear; $year <= $this->endYear; $year++) {
            $sectionLabel = sprintf(
                '%s. ចូលនិវត្តន៍ឆ្នាំ%s',
                $this->toRoman($sectionIndex++),
                $this->toKhmerDigits((string) $year)
            );

            $rows[] = [$sectionLabel, '', '', '', '', '', '', ''];
            $this->sectionRows[] = count($rows);

            /** @var Collection<int,Employee> $yearEmployees */
            $yearEmployees = $grouped->get($year, collect())->values();

            if ($yearEmployees->isEmpty()) {
                $rows[] = ['', '', 'មិនមានទិន្នន័យ', '', '', '', '', ''];
                continue;
            }

            foreach ($yearEmployees as $employee) {
                $rows[] = [
                    (string) $seq++,
                    (string) ($employee->official_id_10 ?: ($employee->employee_id ?: '-')),
                    (string) ($employee->full_name ?: trim(($employee->last_name ?: '') . ' ' . ($employee->first_name ?: ''))),
                    $this->khmerGender($employee),
                    $this->formatDate($employee->date_of_birth),
                    $this->formatDate(
                        $employee->service_date
                        ?? $employee->joining_date
                        ?? $employee->date_of_joining
                        ?? $employee->date_of_join
                        ?? null
                    ),
                    $this->payLevelKm($employee),
                    (string) (
                        $employee->display_unit_path
                        ?: 
                        $employee->display_unit_name
                        ?: ($employee->sub_department?->department_name ?: ($employee->department?->department_name ?: '-'))
                    ),
                ];
            }
        }

        $this->tableLastRow = count($rows);

        $rows[] = $this->blankRow();

        $rows[] = ['', '', '', '', '', $lunarDate, '', ''];
        $this->footerDateRow = count($rows);

        $rows[] = ['', '', '', '', '', $solarDate, '', ''];
        $this->footerDateDetailRow = count($rows);

        $rows[] = ['ឯកភាព', '', '', '', '', (string) $this->meta['hr_manager_text'], '', ''];
        $this->footerApprovalRow = count($rows);

        $rows[] = [(string) $this->meta['approval_text'], '', '', '', '', '', '', ''];
        $this->footerHeadTitleRow = count($rows);

        $rows[] = $this->blankRow();
        $rows[] = $this->blankRow();

        $rows[] = ['', '', '', '', '', '', '', ''];
        $this->footerHeadSignRow = count($rows);

        $rows[] = ['', '', '', '', '', '', '', ''];
        $this->footerHrSignRow = count($rows);

        $this->lastRow = count($rows);

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => false, 'size' => 12, 'name' => 'Khmer M1', 'color' => ['rgb' => '002060']]],
            2 => ['font' => ['bold' => false, 'size' => 12, 'name' => 'Khmer M1', 'color' => ['rgb' => '002060']]],
            3 => ['font' => ['bold' => false, 'size' => 48, 'name' => 'Tacteing', 'color' => ['rgb' => '002060']]],
            6 => ['font' => ['bold' => false, 'size' => 12, 'name' => 'Khmer M1', 'color' => ['rgb' => '002060']]],
            7 => ['font' => ['bold' => false, 'size' => 12, 'name' => 'Khmer M1', 'color' => ['rgb' => '002060']]],
            9 => ['font' => ['bold' => false, 'size' => 12, 'name' => 'Khmer M1', 'color' => ['rgb' => '002060']]],
            11 => ['font' => ['bold' => true, 'size' => 11, 'name' => 'Khmer OS Siemreap', 'color' => ['rgb' => '002060']]],
        ];
    }

    /**
     * @return array<int,Drawing>
     */
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
        // Initial placement; final horizontal centering is applied in AfterSheet.
        $drawing->setCoordinates('A4');
        $drawing->setOffsetX(0);
        $drawing->setOffsetY(0);
        $drawing->setResizeProportional(true);
        // Keep logo clear and compact: set width only to avoid over-scaling.
        $drawing->setWidth(114);

        return [$drawing];
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
                    ->setFitToHeight(1)
                    ->setHorizontalCentered(true);

                $sheet->getPageMargins()
                    ->setLeft(0.43)
                    ->setRight(0.21)
                    ->setTop(0.29)
                    ->setBottom(0.46)
                    ->setHeader(0.22)
                    ->setFooter(0.30);

                $sheet->getPageSetup()->setPrintArea("A1:H{$this->lastRow}");

                $sheet->mergeCells('A1:H1');
                $sheet->mergeCells('A2:H2');
                $sheet->mergeCells('A3:H3');
                $sheet->mergeCells('A6:C6');
                $sheet->mergeCells('A7:C7');
                $sheet->mergeCells('A9:H9');

                foreach ($this->sectionRows as $row) {
                    $sheet->mergeCells("A{$row}:H{$row}");
                }

                $sheet->mergeCells('F' . $this->footerDateRow . ':H' . $this->footerDateRow);
                $sheet->mergeCells('F' . $this->footerDateDetailRow . ':H' . $this->footerDateDetailRow);
                $sheet->mergeCells('A' . $this->footerApprovalRow . ':D' . $this->footerApprovalRow);
                $sheet->mergeCells('F' . $this->footerApprovalRow . ':H' . $this->footerApprovalRow);
                $sheet->mergeCells('A' . $this->footerHeadTitleRow . ':D' . $this->footerHeadTitleRow);
                $sheet->mergeCells('A' . $this->footerHeadSignRow . ':D' . $this->footerHeadSignRow);
                $sheet->mergeCells('F' . $this->footerHrSignRow . ':H' . $this->footerHrSignRow);

                foreach ([1, 2, 3, 9] as $row) {
                    $sheet->getStyle("A{$row}:H{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                }

                foreach ([6, 7] as $row) {
                    $sheet->getStyle("A{$row}:C{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                }

                $sheet->getStyle('A1:H2')->applyFromArray([
                    'font' => [
                        'name' => 'Khmer M1',
                        'bold' => false,
                        'color' => ['rgb' => '002060'],
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);
                $sheet->getStyle('A4:H10')->applyFromArray([
                    'font' => [
                        'name' => 'Khmer M1',
                        'bold' => false,
                        'color' => ['rgb' => '002060'],
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);
                $sheet->getStyle('A3:H3')->applyFromArray([
                    'font' => [
                        'name' => 'Tacteing',
                        'size' => 48,
                        'bold' => false,
                        'color' => ['rgb' => '002060'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getStyle("A11:H{$this->tableLastRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                $sheet->getStyle('A11:H11')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A11:H11')->getAlignment()->setWrapText(true);

                if ($this->tableLastRow >= 13) {
                    $sheet->getStyle("A13:H{$this->tableLastRow}")->applyFromArray([
                        'font' => [
                            'name' => 'Khmer OS Siemreap',
                            'size' => 11,
                            'bold' => false,
                            'color' => ['rgb' => '000000'],
                        ],
                        'alignment' => [
                            'vertical' => Alignment::VERTICAL_CENTER,
                            'wrapText' => true,
                        ],
                    ]);
                    $sheet->getStyle("A13:A{$this->tableLastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("B13:B{$this->tableLastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("D13:D{$this->tableLastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("E13:E{$this->tableLastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("F13:F{$this->tableLastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("C13:C{$this->tableLastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle("G13:G{$this->tableLastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle("H13:H{$this->tableLastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                }

                // Apply year-section style last so it is not overridden by data-row styling.
                foreach ($this->sectionRows as $row) {
                    $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
                        'font' => [
                            'name' => 'Khmer M1',
                            'size' => 11,
                            'bold' => false,
                            'color' => ['rgb' => '002060'],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_LEFT,
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                    ]);
                    $sheet->getRowDimension($row)->setRowHeight(29.4);
                }

                $sheet->getStyle("F{$this->footerDateRow}:H{$this->footerDateRow}")->applyFromArray([
                    'font' => ['name' => 'Khmer OS Siemreap', 'size' => 12, 'bold' => false, 'color' => ['rgb' => '002060']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getStyle("F{$this->footerDateDetailRow}:H{$this->footerDateDetailRow}")->applyFromArray([
                    'font' => ['name' => 'Khmer OS Siemreap', 'size' => 12, 'bold' => false, 'color' => ['rgb' => '002060']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                // Force the solar-date row layout exactly as requested.
                $sheet->mergeCells('F' . $this->footerDateDetailRow . ':H' . $this->footerDateDetailRow);
                $sheet->getStyle("F{$this->footerDateDetailRow}:H{$this->footerDateDetailRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(false);
                $sheet->getStyle("F{$this->footerDateDetailRow}:H{$this->footerDateDetailRow}")
                    ->getFont()
                    ->setName('Khmer OS Siemreap')
                    ->setSize(12)
                    ->setBold(false);
                $sheet->getStyle("A{$this->footerApprovalRow}:D{$this->footerApprovalRow}")->applyFromArray([
                    'font' => ['name' => 'Khmer OS Siemreap', 'size' => 12, 'bold' => false, 'color' => ['rgb' => '002060']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getStyle("F{$this->footerApprovalRow}:H{$this->footerApprovalRow}")->applyFromArray([
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
                $sheet->getStyle("F{$this->footerHrSignRow}:H{$this->footerHrSignRow}")->applyFromArray([
                    'font' => ['name' => 'Khmer OS Siemreap', 'size' => 11, 'bold' => false, 'color' => ['rgb' => '002060']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                $sheet->getRowDimension(1)->setRowHeight(28);
                $sheet->getRowDimension(2)->setRowHeight(28);
                $sheet->getRowDimension(3)->setRowHeight(52);
                $sheet->getRowDimension(4)->setRowHeight(44);
                $sheet->getRowDimension(5)->setRowHeight(44);
                $sheet->getRowDimension(6)->setRowHeight(24);
                $sheet->getRowDimension(7)->setRowHeight(24);
                $sheet->getRowDimension(8)->setRowHeight(16);
                $sheet->getRowDimension(9)->setRowHeight(30);
                $sheet->getRowDimension(11)->setRowHeight(-1);

                foreach (range('C', 'H') as $column) {
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }

                $this->centerHeaderLogoInAB($sheet);

                for ($row = 13; $row <= $this->tableLastRow; $row++) {
                    if (in_array($row, $this->sectionRows, true)) {
                        continue;
                    }
                    $sheet->getRowDimension($row)->setRowHeight(31.2);
                }

                $sheet->getRowDimension($this->footerDateRow)->setRowHeight(24);
                $sheet->getRowDimension($this->footerDateDetailRow)->setRowHeight(24);
                $sheet->getRowDimension($this->footerApprovalRow)->setRowHeight(24);
                $sheet->getRowDimension($this->footerHeadTitleRow)->setRowHeight(24);
                $sheet->getRowDimension($this->footerHeadSignRow)->setRowHeight(26);
                $sheet->getRowDimension($this->footerHrSignRow)->setRowHeight(26);
            },
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 7.55,
            'B' => 17.00,
            'C' => 18.00,
            'D' => 22.00,
            'E' => 9.00,
            'F' => 14.00,
            'G' => 22.00,
            'H' => 14.00,
        ];
    }

    protected function blankRow(): array
    {
        return ['', '', '', '', '', '', '', ''];
    }

    protected function centerHeaderLogoInAB(Worksheet $sheet): void
    {
        $logo = null;
        foreach ($sheet->getDrawingCollection() as $drawing) {
            if ($drawing instanceof Drawing && $drawing->getName() === 'Organization Logo') {
                $logo = $drawing;
                break;
            }
        }

        if (!$logo) {
            return;
        }

        $aWidth = $this->columnWidthPixels($sheet, 'A');
        $bWidth = $this->columnWidthPixels($sheet, 'B');
        $targetWidth = $aWidth + $bWidth;

        $logoWidth = max(1, (int) $logo->getWidth());
        $offsetX = max(0, (int) floor(($targetWidth - $logoWidth) / 2) - 8);

        $logo->setCoordinates('A4');
        $logo->setOffsetX($offsetX);
        $logo->setOffsetY(0);
    }

    protected function columnWidthPixels(Worksheet $sheet, string $column): int
    {
        $width = (float) $sheet->getColumnDimension($column)->getWidth();
        if ($width <= 0) {
            $width = (float) ($this->columnWidths()[$column] ?? 8.43);
        }

        $defaultFont = $sheet->getParent()->getDefaultStyle()->getFont();

        return (int) round(SharedDrawing::cellDimensionToPixels($width, $defaultFont));
    }

    protected function resolveLogoPath(): ?string
    {
        $folderCandidates = [];
        $logoDirectories = [
            public_path('assets/logo'),
            public_path('assets/logo/logo'),
        ];

        foreach ($logoDirectories as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $images = glob($dir . DIRECTORY_SEPARATOR . '*.{png,jpg,jpeg,webp,svg}', GLOB_BRACE) ?: [];
            foreach ($images as $imagePath) {
                $folderCandidates[] = $imagePath;
            }
        }

        $dbCandidates = [];
        $application = Application::query()->select(['logo', 'sidebar_logo', 'sidebar_collapsed_logo'])->first();

        if ($application) {
            foreach (['logo', 'sidebar_logo', 'sidebar_collapsed_logo'] as $column) {
                $value = trim((string) ($application->{$column} ?? ''));
                if ($value === '') {
                    continue;
                }

                $clean = ltrim(str_replace('\\', '/', $value), '/');
                $dbCandidates[] = storage_path('app/public/' . $clean);
                $dbCandidates[] = public_path('storage/' . $clean);
                $dbCandidates[] = public_path($clean);
            }
        }

        $candidates = array_filter(array_merge($folderCandidates, $dbCandidates, [
            // Prefer the current system logo used in sidebar/header branding.
            public_path('backend/assets/dist/img/sidebar-logo.png'),
            public_path('backend/assets/dist/img/new-logo.png'),
            public_path('backend/assets/dist/img/logo-preview.png'),
            public_path('assets/logo.png'),
            public_path('assets/logo2.png'),
            public_path('assets/hrm-nrw-logo.png'),
            public_path('assets/img/logo.png'),
            public_path('logo.png'),
        ]));

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    protected function formatDate($value): string
    {
        if (!$value) {
            return '-';
        }

        $timestamp = strtotime((string) $value);
        if ($timestamp === false) {
            return (string) $value;
        }

        return date('d/m/Y', $timestamp);
    }

    protected function khmerGender(Employee $employee): string
    {
        $source = strtolower(trim((string) (
            $employee->gender?->gender_name
            ?: $employee->gender_name
            ?: ''
        )));

        if (in_array($source, ['male', 'm', 'ប្រុស'], true) || (int) ($employee->gender_id ?? 0) === 1) {
            return 'ប្រុស';
        }

        if (in_array($source, ['female', 'f', 'ស្រី'], true) || (int) ($employee->gender_id ?? 0) === 2) {
            return 'ស្រី';
        }

        return '-';
    }

    protected function payLevelKm(Employee $employee): string
    {
        $activePayLevel = optional($employee->currentPayGradeHistory)->payLevel;
        $activeName = trim((string) (
            optional($activePayLevel)->level_name_mk
            ?: optional($activePayLevel)->level_name_km
            ?: ''
        ));
        if ($activeName !== '') {
            $mapped = $this->findPayLevelLabelByCode($this->payLevelNameByCodeMap(), $activeName);
            if ($mapped !== '') {
                return $mapped;
            }
            if ($this->isLikelyPayLevelCode($activeName)) {
                return $this->normalizePayLevelCodeToKhmer($activeName);
            }
            return $activeName;
        }

        $latestPayLevel = optional($employee->latestPayGradeHistory)->payLevel;
        $latestName = trim((string) (
            optional($latestPayLevel)->level_name_mk
            ?: optional($latestPayLevel)->level_name_km
            ?: ''
        ));
        if ($latestName !== '') {
            $mapped = $this->findPayLevelLabelByCode($this->payLevelNameByCodeMap(), $latestName);
            if ($mapped !== '') {
                return $mapped;
            }
            if ($this->isLikelyPayLevelCode($latestName)) {
                return $this->normalizePayLevelCodeToKhmer($latestName);
            }
            return $latestName;
        }

        $legacyCode = trim((string) ($employee->employee_grade ?: $employee->class_code ?: ''));
        if ($legacyCode !== '') {
            $mapped = $this->findPayLevelLabelByCode($this->payLevelNameByCodeMap(), $legacyCode);
            if ($mapped !== '') {
                return $mapped;
            }
            return $this->normalizePayLevelCodeToKhmer($legacyCode);
        }

        return '-';
    }

    protected function payLevelNameByCodeMap(): array
    {
        static $cache = null;

        if (is_array($cache)) {
            return $cache;
        }

        $cache = [];
        $hasMk = Schema::hasColumn('gov_pay_levels', 'level_name_mk');
        $selects = $hasMk
            ? ['level_code', 'level_name_mk', 'level_name_km']
            : ['level_code', 'level_name_km'];

        GovPayLevel::query()
            ->select($selects)
            ->get()
            ->each(function ($row) use (&$cache, $hasMk) {
                $code = $this->normalizePayLevelCodeKey((string) ($row->level_code ?? ''));
                $compactCode = $this->normalizePayLevelCodeCompactKey((string) ($row->level_code ?? ''));
                $label = trim((string) (
                    $hasMk
                        ? (($row->level_name_mk ?? '') ?: ($row->level_name_km ?? ''))
                        : ($row->level_name_km ?? '')
                ));

                if ($code !== '' && $label !== '') {
                    $cache[$code] = $label;
                }
                if ($compactCode !== '' && $label !== '') {
                    $cache[$compactCode] = $label;
                }
            });

        return $cache;
    }

    protected function normalizePayLevelCodeKey(string $value): string
    {
        $clean = strtoupper(trim($value));
        $clean = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $clean) ?? $clean;
        $clean = preg_replace('/\s+/u', '', $clean) ?? $clean;

        return $clean;
    }

    protected function normalizePayLevelCodeCompactKey(string $value): string
    {
        $clean = $this->normalizePayLevelCodeKey($value);
        return preg_replace('/[^A-Z0-9]/', '', $clean) ?? '';
    }

    protected function findPayLevelLabelByCode(array $payLevelByCode, string $raw): string
    {
        $key = $this->normalizePayLevelCodeKey($raw);
        if ($key !== '' && isset($payLevelByCode[$key]) && trim((string) $payLevelByCode[$key]) !== '') {
            return trim((string) $payLevelByCode[$key]);
        }

        $compact = $this->normalizePayLevelCodeCompactKey($raw);
        if ($compact !== '' && isset($payLevelByCode[$compact]) && trim((string) $payLevelByCode[$compact]) !== '') {
            return trim((string) $payLevelByCode[$compact]);
        }

        return '';
    }

    protected function isLikelyPayLevelCode(string $value): bool
    {
        $clean = $this->normalizePayLevelCodeKey($value);
        return $clean !== '' && (bool) preg_match('/^[A-Z](?:[.\-]?\d+){1,3}$/', $clean);
    }

    protected function normalizePayLevelCodeToKhmer(string $value): string
    {
        $clean = trim($value);
        if ($clean === '') {
            return '';
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

    protected function toRoman(int $number): string
    {
        $map = [
            1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD',
            100 => 'C', 90 => 'XC', 50 => 'L', 40 => 'XL',
            10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I',
        ];

        $result = '';
        foreach ($map as $value => $roman) {
            while ($number >= $value) {
                $result .= $roman;
                $number -= $value;
            }
        }

        return $result;
    }
}
