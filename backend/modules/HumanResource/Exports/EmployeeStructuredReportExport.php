<?php

namespace Modules\HumanResource\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\GovPayLevel;
use Modules\HumanResource\Entities\OrgUnitType;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PPhatDev\LunarDate\KhmerDate;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeeStructuredReportExport implements WithMultipleSheets
{
    protected Collection $employees;
    protected array $meta;
    protected array $prepared;
    protected array $unitSegmentCache = [];
    protected array $siblingOrdinalCache = [];
    protected array $orgUnitTypeMetaCache = [];
    protected ?array $payGradeSortOrderCache = null;

    public function __construct(Collection $employees, array $meta = [])
    {
        $this->employees = $employees->values();
        $this->meta = array_merge([
            'admin_text' => 'រដ្ឋបាលខេត្តស្ទឹងត្រែង',
            'unit_text' => 'មន្ទីរសុខាភិបាលនៃរដ្ឋបាលខេត្ត',
            'location_text' => 'ស្ទឹងត្រែង',
            'approval_text' => 'ប្រធានមន្ទីរសុខាភិបាល',
            'hr_manager_text' => 'ប្រធានការិយាល័យរដ្ឋបាល និង បុគ្គលិក',
        ], $meta);

        $this->prepared = $this->prepareWorkbookData();
    }

    public function sheets(): array
    {
        return [
            new EmployeeStructuredListSheet($this->prepared['list']),
            new EmployeeStructuredBySkillSheet($this->prepared['by_skill']),
        ];
    }

    protected function prepareWorkbookData(): array
    {
        $today = Carbon::today();
        $quarter = (int) ceil(((int) $today->month) / 3);
        $quarterKh = $this->toKhmerDigits((string) $quarter);
        $yearKh = $this->toKhmerDigits($today->format('Y'));
        $unitSuffix = trim((string) ($this->meta['location_text'] ?? ''));
        $unitText = trim((string) ($this->meta['unit_text'] ?? ''));

        $listTitle = sprintf(
            'តារាងបញ្ជីរាយនាមបច្ចុប្បន្នភាពមន្រ្តីរាជការតាមរចនាសម្ព័ន្ធ ត្រីមាសទី%s ឆ្នាំ%s របស់%s%s',
            $quarterKh,
            $yearKh,
            $unitText,
            $unitSuffix
        );
        $bySkillTitle = sprintf(
            'តារាងស្ថិតិមន្រ្តីរាជការសុខាភិបាល%s%s ត្រីមាសទី%s ឆ្នាំ %s',
            $unitText,
            $unitSuffix,
            $quarterKh,
            $yearKh
        );

        $listRows = [
            ['ព្រះរាជាណាចក្រកម្ពុជា', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['ជាតិ សាសនា ព្រះមហាក្សត្រ', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['6', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['   ' . trim((string) ($this->meta['admin_text'] ?? '')), '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['   ' . $unitText, '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            [$listTitle, '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            [
                "ល.រ\nសរុប",
                'ល.រ',
                'អត្តលេខ',
                'គោត្តនាម និងនាម',
                'ឈ្មោះជាអក្សឡាតាំង',
                'ភេទ',
                "ថ្ងៃខែឆ្នាំ\nកំណើត",
                "ថ្ងៃខែឆ្នាំ\nចូលបម្រើការងារ",
                'អតីតភាពការងារ',
                'ជំនាញ',
                'តួនាទី',
                "ឋានន្តរស័ក្ក\nនិងថ្នាក់",
                "ថ្ងៃខែឡើងកាំប្រាក់\nចុងក្រោយ",
                'ឈ្មោះអង្គភាព',
                'ជំនាញសម្រាប់ស្ថិតិ',
            ],
        ];

        $listRows[9] = [
            "ល.រ\nសរុប",
            'ល.រ',
            'អត្តលេខ',
            'គោត្តនាម និងនាម',
            'ឈ្មោះជាអក្សរឡាតាំង',
            'ភេទ',
            "ថ្ងៃខែឆ្នាំ\nកំណើត",
            "ថ្ងៃខែឆ្នាំ\nចូលបម្រើការងារ",
            'អតីតភាពការងារ',
            'ជំនាញ',
            'តួនាទី',
            "ឋានន្តរស័ក្តិ\nនិងថ្នាក់",
            "ថ្ងៃខែឡើងកាំប្រាក់\nចុងក្រោយ",
            'ឈ្មោះអង្គភាព',
            'ជំនាញសម្រាប់ស្ថិតិ',
        ];

        $listRows[9] = [
            "ល.រ\nសរុប",
            'ល.រ',
            'អត្តលេខ',
            'គោត្តនាម និងនាម',
            'ឈ្មោះជាអក្សរឡាតាំង',
            'ភេទ',
            "ថ្ងៃខែឆ្នាំ\nកំណើត",
            "ថ្ងៃខែឆ្នាំ\nចូលបម្រើការងារ",
            'អតីតភាពការងារ',
            'ជំនាញ',
            'តួនាទី',
            "ឋានន្តរស័ក្តិ\nនិងថ្នាក់",
            "ថ្ងៃខែឡើងកាំប្រាក់\nចុងក្រោយ",
            'ឈ្មោះអង្គភាព',
            'ជំនាញសម្រាប់ស្ថិតិ',
            "ស្ថានភាព\nការងារ",
        ];

        $groupRows = [];
        $employeeRows = [];
        $withoutPayRows = [];
        $offenderRows = [];
        $unitNames = [];
        $bySkillFixedUnitPresence = array_fill_keys($this->bySkillFixedUnitOrder(), false);
        $bySkillHealthCenterNames = [];
        $bySkillHealthPostNames = [];
        $unitSequence = [];
        $previousSegments = [];
        $topLevelDisplayOrdinals = [];
        $nextTopLevelDisplayOrdinal = 1;
        $overallIndex = 1;
        $femaleCount = 0;
        $employeeSegmentMap = [];
        $employeeGenderMap = [];
        $segmentCounts = [];
        $orderedEmployees = [];

        foreach ($this->employees as $employeeIndex => $employee) {
            $segments = $this->resolveDisplaySegments($employee);
            if (empty($segments)) {
                $segments = [$this->fallbackSegment()];
            }

            $gender = $this->canonicalGenderLabel($this->resolveGenderLabel($employee));
            $employeeSegmentMap[$employeeIndex] = $segments;
            $employeeGenderMap[$employeeIndex] = $gender;
            $orderedEmployees[] = [
                'index' => $employeeIndex,
                'employee' => $employee,
                'segments' => $segments,
                'sort_key' => $this->buildSegmentSortKey($segments),
                'unit_name' => $this->resolveStatisticUnitName($segments),
                'position_rank' => $employee->position?->position_rank ?? PHP_INT_MAX,
                'pay_grade_order' => $this->resolvePayGradeSortOrder($employee),
                'official_id' => trim((string) ($employee->official_id_10 ?: '')),
                'khmer_name' => $this->resolveKhmerName($employee),
            ];

            $seenSegmentKeys = [];
            foreach ($segments as $segment) {
                $segmentKey = (string) ($segment['path_key'] ?? '');
                if ($segmentKey === '' || isset($seenSegmentKeys[$segmentKey])) {
                    continue;
                }

                if (!isset($segmentCounts[$segmentKey])) {
                    $segmentCounts[$segmentKey] = [
                        'total' => 0,
                        'male' => 0,
                        'female' => 0,
                    ];
                }

                $segmentCounts[$segmentKey]['total']++;
                if ($gender === 'ប្រុស') {
                    $segmentCounts[$segmentKey]['male']++;
                } elseif ($gender === 'ស្រី') {
                    $segmentCounts[$segmentKey]['female']++;
                }

                $seenSegmentKeys[$segmentKey] = true;
            }
        }

        usort($orderedEmployees, function (array $left, array $right): int {
            $compare = strcmp((string) ($left['sort_key'] ?? ''), (string) ($right['sort_key'] ?? ''));
            if ($compare !== 0) {
                return $compare;
            }

            $compare = strcmp((string) ($left['unit_name'] ?? ''), (string) ($right['unit_name'] ?? ''));
            if ($compare !== 0) {
                return $compare;
            }

            // Keep unit sections together, then apply the configured staff hierarchy.
            $compare = (int) $left['position_rank'] <=> (int) $right['position_rank'];
            if ($compare !== 0) {
                return $compare;
            }

            $compare = $left['pay_grade_order'] <=> $right['pay_grade_order'];
            if ($compare !== 0) {
                return $compare;
            }

            $compare = strcmp((string) ($left['official_id'] ?? ''), (string) ($right['official_id'] ?? ''));
            if ($compare !== 0) {
                return $compare;
            }

            $compare = strcmp((string) ($left['khmer_name'] ?? ''), (string) ($right['khmer_name'] ?? ''));
            if ($compare !== 0) {
                return $compare;
            }

            return (int) ($left['index'] ?? 0) <=> (int) ($right['index'] ?? 0);
        });

        foreach ($orderedEmployees as $orderedEmployee) {
            $employeeIndex = (int) ($orderedEmployee['index'] ?? 0);
            $employee = $orderedEmployee['employee'];
            $segments = $orderedEmployee['segments'] ?? [$this->fallbackSegment()];
            foreach ($segments as $segmentIndex => $segment) {
                if ((int) ($segment['depth'] ?? 0) !== 1) {
                    continue;
                }

                $segmentKey = (string) ($segment['path_key'] ?? '');
                if ($segmentKey === '') {
                    continue;
                }

                if (!isset($topLevelDisplayOrdinals[$segmentKey])) {
                    $topLevelDisplayOrdinals[$segmentKey] = $nextTopLevelDisplayOrdinal++;
                }

                $segments[$segmentIndex]['display_ordinal'] = $topLevelDisplayOrdinals[$segmentKey];
            }

            $changedIndex = 0;
            while (
                $changedIndex < count($segments)
                && $changedIndex < count($previousSegments)
                && (($segments[$changedIndex]['path_key'] ?? null) === ($previousSegments[$changedIndex]['path_key'] ?? null))
            ) {
                $changedIndex++;
            }

            for ($i = $changedIndex; $i < count($segments); $i++) {
                $segmentKey = (string) ($segments[$i]['path_key'] ?? '');
                $counts = $segmentCounts[$segmentKey] ?? ['total' => 0, 'male' => 0, 'female' => 0];
                $listRows[] = [$this->formatDisplaySegmentLabel($segments[$i], $counts), '', '', '', '', '', '', '', '', '', '', '', '', '', ''];
                $groupRows[] = count($listRows);
            }

            $previousSegments = $segments;
            $unitName = $this->resolveStatisticUnitName($segments);
            $bySkillUnitName = $this->resolveBySkillUnitCategory($segments);
            if ($bySkillUnitName !== null) {
                $bySkillUnitKind = $this->resolveBySkillUnitKind($segments);
                if ($bySkillUnitKind === 'health_center') {
                    if (!in_array($bySkillUnitName, $bySkillHealthCenterNames, true)) {
                        $bySkillHealthCenterNames[] = $bySkillUnitName;
                    }
                } elseif ($bySkillUnitKind === 'health_post') {
                    if (!in_array($bySkillUnitName, $bySkillHealthPostNames, true)) {
                        $bySkillHealthPostNames[] = $bySkillUnitName;
                    }
                } elseif (array_key_exists($bySkillUnitName, $bySkillFixedUnitPresence)) {
                    $bySkillFixedUnitPresence[$bySkillUnitName] = true;
                }
            }

            if (!array_key_exists($unitName, $unitSequence)) {
                $unitSequence[$unitName] = 0;
            }
            $unitSequence[$unitName]++;

            $gender = $employeeGenderMap[$employeeIndex] ?? $this->canonicalGenderLabel($this->resolveGenderLabel($employee));
            if ($gender === 'ស្រី') {
                $femaleCount++;
            }

            $listRows[] = [
                (string) $overallIndex,
                (string) $unitSequence[$unitName],
                trim((string) ($employee->official_id_10 ?: '')),
                $this->resolveKhmerName($employee),
                $this->resolveLatinName($employee),
                $gender,
                $this->formatDateTime($employee->date_of_birth ?? null),
                $this->formatDateTime($this->resolveServiceStartDate($employee)),
                $this->resolveTenureYears($employee),
                $this->resolveSkillName($employee),
                $this->resolvePositionName($employee),
                $this->resolvePayGrade($employee),
                $this->formatDateTime($this->resolveLastPromotionDate($employee)),
                $unitName,
                $this->resolveSkillStatisticLabel($employee),
                $this->resolveWorkStatusName($employee),
                $bySkillUnitName ?? '',
            ];
            $employeeRows[] = count($listRows);
            if ($this->isWithoutPayEmployee($employee)) {
                $withoutPayRows[] = count($listRows);
            }
            if ($this->isOffenderEmployee($employee)) {
                $offenderRows[] = count($listRows);
            }
            $overallIndex++;
        }

        $listLastEmployeeRow = empty($employeeRows) ? 10 : max($employeeRows);
        $listRows[] = ['', '', '', '', '', '', '', '', '', '', '', '', '', '', ''];
        $noteRow = count($listRows) + 1;
        $listRows[] = [
            sprintf('ចំណាំ៖ សរុបចំនួន %sនាក់ ស្រី %s នាក់', $this->employees->count(), $femaleCount),
            '', '', '', '', '', '', '', '', '', '', '', '', '', '',
        ];

        $listRows[$noteRow - 1][0] = $this->buildSummaryNote($this->employees->count(), $femaleCount, count($withoutPayRows), count($offenderRows));
        $unitNames = array_merge(
            array_values(array_filter(
                $this->bySkillFixedUnitOrder(),
                fn (string $name): bool => (bool) ($bySkillFixedUnitPresence[$name] ?? false)
            )),
            $bySkillHealthCenterNames,
            $bySkillHealthPostNames
        );
        foreach ($listRows as &$row) {
            $row = array_pad($row, 17, '');
        }
        unset($row);

        $skillHeaders = [
            'ឱសថការី',
            'បច្ចេក.រដ្ឋបាល',
            'ពិសោធន៍មធ្យម',
            'បរិញ្ញាប័ត្រគិលានុបដ្ឋាក',
            'បរិញ្ញាប័ត្រឆ្មប',
            'ពត៌មានវិទ្យា',
            'ព្យាបាលដោយចលនា',
            'វេជ្ជបណ្ឌិត',
            'គិ.បឋម',
            'គិ.មធ្យម',
            'កម្មករ',
            'គណនេយ្យឧត្តម',
            'ឆ្មបបឋម',
            'ឆ្មបមធ្យម',
            'គ្រូពេទ្យមធ្យម',
            'ទន្តបណ្ឌិត',
            'ទន្តគិលានុបដ្ឋាក',
            'ផ្សេងៗ',
        ];

        $bySkillRows = [];
        $bySkillRows[] = array_merge([$bySkillTitle], array_fill(0, 22, ''));
        $bySkillRows[] = array_fill(0, 23, '');
        $bySkillRows[] = array_fill(0, 23, '');
        $bySkillHeaderLabels = ['អង្គភាពសុខាភិបាល', 'សរុប', 'ស្រី'];
        $bySkillRows[] = array_merge(['អង្គភាពសុខាភិបាល', 'សរុប', 'ស្រី'], $skillHeaders, ['', '']);

        $bySkillRows[3][0] = $bySkillHeaderLabels[0];
        $bySkillRows[3][1] = $bySkillHeaderLabels[1];
        $bySkillRows[3][2] = $bySkillHeaderLabels[2];

        $unitStartRow = 5;
        $listRangeEndRow = max($listLastEmployeeRow, 11);

        foreach ($unitNames as $index => $unitName) {
            $excelRow = $unitStartRow + $index;
            $row = array_fill(0, 23, '');
            $row[0] = $unitName;
            $row[1] = sprintf('=COUNTIFS(List_of_Staff!$Q$11:$Q$%d,A%d)', $listRangeEndRow, $excelRow);
            $row[2] = sprintf('=COUNTIFS(List_of_Staff!$Q$11:$Q$%d,A%d,List_of_Staff!$F$11:$F$%d,$C$4)', $listRangeEndRow, $excelRow, $listRangeEndRow);

            foreach ($skillHeaders as $skillIndex => $header) {
                $columnLetter = Coordinate::stringFromColumnIndex(4 + $skillIndex);
                $row[3 + $skillIndex] = sprintf(
                    '=COUNTIFS(List_of_Staff!$Q$11:$Q$%1$d,A%2$d,List_of_Staff!$O$11:$O$%1$d,%3$s$4)',
                    $listRangeEndRow,
                    $excelRow,
                    $columnLetter
                );
            }

            $bySkillRows[] = $row;
        }

        $totalRowNumber = $unitStartRow + count($unitNames);
        $totalRow = array_fill(0, 23, '');
        $totalRow[0] = 'សរុប';
        for ($col = 2; $col <= 21; $col++) {
            $letter = Coordinate::stringFromColumnIndex($col);
            $totalRow[$col - 1] = sprintf('=SUM(%1$s5:%1$s%2$d)', $letter, max($totalRowNumber - 1, 5));
        }
        $bySkillRows[] = $totalRow;

        $footerStartRow = $totalRowNumber + 1;
        $bySkillRows[] = array_merge(array_fill(0, 10, ''), [$this->khmerLunarDateText()], array_fill(0, 12, ''));
        $bySkillRows[] = array_merge(['បានឃើញ និង ឯកភាព'], array_fill(0, 9, ''), [$this->khmerSolarDateText()], array_fill(0, 12, ''));
        $bySkillRows[] = array_merge([$this->khmerLunarDateText()], array_fill(0, 10, ''), [trim((string) ($this->meta['hr_manager_text'] ?? ''))], array_fill(0, 11, ''));
        $bySkillRows[] = array_merge([$this->khmerSolarDateText()], array_fill(0, 22, ''));
        $bySkillRows[] = array_merge([trim((string) ($this->meta['approval_text'] ?? ''))], array_fill(0, 22, ''));
        $bySkillRows[] = array_fill(0, 23, '');
        $bySkillRows[] = array_fill(0, 23, '');
        $bySkillRows[] = array_fill(0, 23, '');

        $bySkillRows[$footerStartRow - 1] = array_fill(0, 23, '');
        $bySkillRows[$footerStartRow] = array_merge(['បានឃើញ និង ឯកភាព'], array_fill(0, 9, ''), [$this->khmerSolarDateText()], array_fill(0, 12, ''));
        $bySkillRows[$footerStartRow + 1] = array_merge([$this->khmerLunarDateText()], array_fill(0, 10, ''), [trim((string) ($this->meta['hr_manager_text'] ?? ''))], array_fill(0, 11, ''));
        $bySkillRows[$footerStartRow + 2] = array_merge([$this->khmerSolarDateText()], array_fill(0, 22, ''));
        $bySkillRows[$footerStartRow + 3] = array_merge([trim((string) ($this->meta['approval_text'] ?? ''))], array_fill(0, 22, ''));

        return [
            'list' => [
                'rows' => $listRows,
                'group_rows' => $groupRows,
                'employee_rows' => $employeeRows,
                'without_pay_rows' => $withoutPayRows,
                'offender_rows' => $offenderRows,
                'last_employee_row' => $listLastEmployeeRow,
                'note_row' => $noteRow,
            ],
            'by_skill' => [
                'rows' => $bySkillRows,
                'unit_row_start' => $unitStartRow,
                'total_row' => $totalRowNumber,
                'footer_start_row' => $footerStartRow,
            ],
        ];
    }

    protected function resolveDisplaySegments($employee): array
    {
        $unitId = (int) ($employee->sub_department_id ?: $employee->department_id ?: 0);
        if ($unitId > 0 && isset($this->unitSegmentCache[$unitId])) {
            return $this->unitSegmentCache[$unitId];
        }

        if ($unitId <= 0) {
            return [$this->fallbackSegment()];
        }

        $visited = [];
        $chain = [];
        $guard = 0;
        $currentId = $unitId;

        while ($currentId > 0 && $guard < 50) {
            if (isset($visited[$currentId])) {
                break;
            }

            $visited[$currentId] = true;

            $unit = Department::withoutGlobalScopes()
                ->select(['id', 'department_name', 'parent_id', 'sort_order', 'unit_type_id'])
                ->find($currentId);

            if (!$unit) {
                break;
            }

            $name = trim((string) ($unit->department_name ?? ''));
            if ($name !== '') {
                $chain[] = [
                    'id' => (int) $unit->id,
                    'name' => $name,
                    'sort_order' => $unit->sort_order !== null ? (int) $unit->sort_order : null,
                    'unit_type_id' => (int) ($unit->unit_type_id ?? 0),
                ];
            }

            $currentId = (int) ($unit->parent_id ?? 0);
            $guard++;
        }

        if (empty($chain)) {
            return [$this->fallbackSegment()];
        }

        $chain = array_reverse($chain);
        $normalized = [];
        $seenIds = [];

        foreach ($chain as $segment) {
            $segmentId = (int) ($segment['id'] ?? 0);
            if ($segmentId > 0 && isset($seenIds[$segmentId])) {
                continue;
            }

            if ($segmentId > 0) {
                $seenIds[$segmentId] = true;
            }

            $normalized[] = $segment;
        }

        $pathNumbers = [];
        $segments = [];

        foreach (array_values($normalized) as $depth => $segment) {
            $unitTypeMeta = $this->resolveOrgUnitTypeMeta((int) ($segment['unit_type_id'] ?? 0));
            $ordinal = $this->resolveSiblingOrdinal(
                (int) ($segment['id'] ?? 0),
                $depth > 0 ? (int) ($normalized[$depth - 1]['id'] ?? 0) : null
            );
            $pathNumbers[] = (string) max(1, $ordinal);

            $segments[] = [
                'id' => (int) ($segment['id'] ?? 0),
                'name' => trim((string) ($segment['name'] ?? '')),
                'depth' => $depth,
                'unit_type_id' => (int) ($segment['unit_type_id'] ?? 0),
                'unit_type_code' => (string) ($unitTypeMeta['code'] ?? ''),
                'unit_type_name_km' => (string) ($unitTypeMeta['name_km'] ?? ''),
                'path_code' => implode('.', $pathNumbers),
                'path_key' => (string) ((int) ($segment['id'] ?? 0)),
            ];
        }

        $this->unitSegmentCache[$unitId] = $segments;

        return $segments;
    }

    protected function resolveStatisticUnitName(array $segments): string
    {
        if (empty($segments)) {
            return '-';
        }

        $candidates = count($segments) > 1 ? array_slice($segments, 1) : $segments;
        for ($i = count($candidates) - 1; $i >= 0; $i--) {
            $name = trim((string) ($candidates[$i]['name'] ?? ''));
            if ($name !== '') {
                return $name;
            }
        }

        return trim((string) ($segments[count($segments) - 1]['name'] ?? '-'));
    }

    protected function buildSegmentSortKey(array $segments): string
    {
        if (empty($segments)) {
            return '99999';
        }

        $parts = [];
        foreach ($segments as $segment) {
            $pathCode = (string) ($segment['path_code'] ?? '');
            if ($pathCode !== '') {
                $normalizedPath = implode('.', array_map(
                    static fn (string $part): string => str_pad((string) ((int) $part), 5, '0', STR_PAD_LEFT),
                    array_values(array_filter(explode('.', $pathCode), 'strlen'))
                ));
                $parts[] = $normalizedPath;
                continue;
            }

            $parts[] = '99999';
        }

        return implode('|', $parts);
    }

    protected function bySkillFixedUnitOrder(): array
    {
        return [
            'ទីចាត់ការមន្ទីរសុខាភិបាល',
            'មន្ទីរពេទ្យខេត្ត',
            'ការិយាល័យស្រុកប្រតិបត្តិ',
        ];
    }

    protected function resolveBySkillUnitCategory(array $segments): ?string
    {
        if (empty($segments)) {
            return null;
        }

        $unitKind = $this->resolveBySkillUnitKind($segments);
        if ($unitKind === 'health_center' || $unitKind === 'health_post') {
            return $this->resolveStatisticUnitName($segments);
        }
        if ($unitKind === 'provincial_hospital') {
            return 'មន្ទីរពេទ្យខេត្ត';
        }
        if ($unitKind === 'operational_district_office') {
            return 'ការិយាល័យស្រុកប្រតិបត្តិ';
        }
        if ($unitKind === 'phd_office') {
            return 'ទីចាត់ការមន្ទីរសុខាភិបាល';
        }

        $reversedSegments = array_reverse($segments);

        foreach ($reversedSegments as $segment) {
            $name = trim((string) ($segment['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            if (str_contains($name, 'ប៉ុស្តិ៍សុខភាព')) {
                return $name;
            }
            if (str_contains($name, 'មណ្ឌលសុខភាព')) {
                return $name;
            }
            if (str_contains($name, 'មន្ទីរពេទ្យខេត្ត')) {
                return 'មន្ទីរពេទ្យខេត្ត';
            }
            if (str_contains($name, 'ការិយាល័យស្រុកប្រតិបត្តិ') || str_contains($name, 'ស្រុកប្រតិបត្តិ')) {
                return 'ការិយាល័យស្រុកប្រតិបត្តិ';
            }
            if (str_contains($name, 'ទីចាត់ការមន្ទីរសុខាភិបាល')) {
                return 'ទីចាត់ការមន្ទីរសុខាភិបាល';
            }
        }

        return null;
    }

    protected function resolveBySkillUnitKind(array $segments): ?string
    {
        if (empty($segments)) {
            return null;
        }

        $ancestorTypeCodes = array_values(array_filter(array_map(
            static fn (array $item): string => trim((string) ($item['unit_type_code'] ?? '')),
            $segments
        )));

        foreach (array_reverse($segments) as $segment) {
            $typeCode = trim((string) ($segment['unit_type_code'] ?? ''));

            if ($typeCode === 'health_post') {
                return 'health_post';
            }

            if (str_starts_with($typeCode, 'health_center')) {
                return 'health_center';
            }

            if ($typeCode === 'provincial_hospital') {
                return 'provincial_hospital';
            }

            if ($typeCode === 'operational_district') {
                return 'operational_district_office';
            }

            if ($typeCode === 'office') {
                if (in_array('operational_district', $ancestorTypeCodes, true)) {
                    return 'operational_district_office';
                }

                if (in_array('phd', $ancestorTypeCodes, true)) {
                    return 'phd_office';
                }
            }
        }

        return null;
    }

    protected function resolveOrgUnitTypeMeta(int $unitTypeId): array
    {
        if ($unitTypeId <= 0) {
            return ['code' => '', 'name_km' => ''];
        }

        if (!isset($this->orgUnitTypeMetaCache[$unitTypeId])) {
            $type = OrgUnitType::query()
                ->select(['id', 'code', 'name_km'])
                ->find($unitTypeId);

            $this->orgUnitTypeMetaCache[$unitTypeId] = [
                'code' => (string) ($type->code ?? ''),
                'name_km' => (string) ($type->name_km ?? ''),
            ];
        }

        return $this->orgUnitTypeMetaCache[$unitTypeId];
    }

    protected function fallbackSegment(): array
    {
        return [
            'id' => 0,
            'name' => '-',
            'depth' => 0,
            'path_code' => '',
            'path_key' => 'fallback',
        ];
    }

    protected function formatDisplaySegmentLabel(array $segment, array $counts): string
    {
        $depth = max(0, (int) ($segment['depth'] ?? 0));
        $name = trim((string) ($segment['name'] ?? '-'));
        if ($depth === 0) {
            return $name;
        }

        if ($depth === 1) {
            $ordinal = (int) ($segment['display_ordinal'] ?? 0);
            if ($ordinal <= 0) {
                $parts = array_values(array_filter(explode('.', (string) ($segment['path_code'] ?? '')), 'strlen'));
                $ordinal = (int) ($parts[count($parts) - 1] ?? 1);
            }

            return $this->toRomanNumeral(max(1, $ordinal)) . '.' . $name;
        }

        return '...............' . $name;
    }

    protected function resolveSiblingOrdinal(int $unitId, ?int $parentId): int
    {
        if ($unitId <= 0) {
            return 1;
        }

        $cacheKey = $parentId ? (string) $parentId : 'root';
        if (!isset($this->siblingOrdinalCache[$cacheKey])) {
            $query = Department::withoutGlobalScopes()
                ->select(['id', 'department_name', 'sort_order'])
                ->whereNull('deleted_at')
                ->where('is_active', true);

            if ($parentId) {
                $query->where('parent_id', $parentId);
            } else {
                $query->whereNull('parent_id');
            }

            $siblings = $query
                ->orderByRaw('COALESCE(sort_order, 999999) asc')
                ->orderBy('department_name')
                ->get();

            $this->siblingOrdinalCache[$cacheKey] = [];
            foreach ($siblings as $index => $sibling) {
                $this->siblingOrdinalCache[$cacheKey][(int) $sibling->id] = $index + 1;
            }
        }

        return (int) ($this->siblingOrdinalCache[$cacheKey][$unitId] ?? 1);
    }

    protected function formatSegmentSummary(array $counts): string
    {
        return sprintf(
            '(សរុប %d | ប្រុស %d | ស្រី %d)',
            (int) ($counts['total'] ?? 0),
            (int) ($counts['male'] ?? 0),
            (int) ($counts['female'] ?? 0)
        );
    }

    protected function toRomanNumeral(int $number): string
    {
        $map = [
            1000 => 'M',
            900 => 'CM',
            500 => 'D',
            400 => 'CD',
            100 => 'C',
            90 => 'XC',
            50 => 'L',
            40 => 'XL',
            10 => 'X',
            9 => 'IX',
            5 => 'V',
            4 => 'IV',
            1 => 'I',
        ];

        $result = '';
        foreach ($map as $value => $glyph) {
            while ($number >= $value) {
                $result .= $glyph;
                $number -= $value;
            }
        }

        return $result;
    }

    protected function resolveKhmerName($employee): string
    {
        return trim((string) ($employee->full_name ?: trim((string) (($employee->last_name ?? '') . ' ' . ($employee->first_name ?? '')))));
    }

    protected function canonicalGenderLabel(string $gender): string
    {
        $value = mb_strtolower(trim($gender), 'UTF-8');
        if (in_array($value, ['male', 'm', 'ប្រុស', 'áž”áŸ’ážšáž»ážŸ'], true)) {
            return 'ប្រុស';
        }
        if (in_array($value, ['female', 'f', 'ស្រី', 'ážŸáŸ’ážšáž¸'], true)) {
            return 'ស្រី';
        }

        return trim($gender);
    }

    protected function resolveLatinName($employee): string
    {
        $latin = trim(preg_replace('/\s+/u', ' ', (string) (($employee->last_name_latin ?? '') . ' ' . ($employee->first_name_latin ?? ''))));
        if ($latin !== '') {
            return $latin;
        }

        return trim((string) ($employee->full_name_latin ?? ''));
    }

    protected function resolveWorkStatusName($employee): string
    {
        $status = trim((string) ($employee->work_status_name ?? ''));
        if ($status !== '') {
            return $status;
        }

        return match ((string) ($employee->service_state ?? 'active')) {
            'suspended' => 'ផ្អាកបណ្តោះអាសន្ន',
            'inactive' => 'អសកម្ម',
            default => 'កំពុងបម្រើការងារ',
        };
    }

    protected function isWithoutPayEmployee($employee): bool
    {
        $status = trim((string) ($employee->work_status_name ?? ''));
        if ($status === '') {
            return false;
        }

        $normalized = mb_strtolower($status, 'UTF-8');

        return str_contains($normalized, 'without pay')
            || str_contains($normalized, 'leave without pay')
            || str_contains($status, 'ទំនេរគ្មានបៀវត្ស')
            || str_contains($status, 'គ្មានបៀវត្ស');
    }

    protected function isOffenderEmployee($employee): bool
    {
        $status = trim((string) ($employee->work_status_name ?? ''));
        if ($status === '') {
            return false;
        }

        $normalized = mb_strtolower($status, 'UTF-8');

        return str_contains($status, 'ពិរុទ្ធជន')
            || str_contains($status, 'ពិរុទ្ធ')
            || str_contains($normalized, 'offender')
            || str_contains($normalized, 'accused')
            || str_contains($normalized, 'defendant');
    }

    protected function buildSummaryNote(int $total, int $female, int $withoutPay, int $offender): string
    {
        return sprintf(
            'ចំណាំ៖ សរុបចំនួន %dនាក់ ស្រី %d នាក់, ទំនេរគ្មានបៀវត្ស %d នាក់, ពិរុទ្ធជន %d នាក់',
            $total,
            $female,
            $withoutPay,
            $offender
        );
    }

    protected function resolveGenderLabel($employee): string
    {
        $value = mb_strtolower(trim((string) ($employee->gender?->gender_name ?? '')), 'UTF-8');
        if (in_array($value, ['male', 'm', 'ប្រុស'], true)) {
            return 'ប្រុស';
        }
        if (in_array($value, ['female', 'f', 'ស្រី'], true)) {
            return 'ស្រី';
        }

        return trim((string) ($employee->gender?->gender_name ?? ''));
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

    protected function resolveTenureYears($employee): string
    {
        $startDate = $this->resolveServiceStartDate($employee);
        if (blank($startDate)) {
            return '';
        }

        try {
            return (string) Carbon::parse($startDate)->diffInYears(Carbon::today());
        } catch (\Throwable $e) {
            return '';
        }
    }

    protected function resolveSkillName($employee): string
    {
        return trim((string) ($employee->skill_name ?: ($employee->profileExtra?->current_work_skill ?? '')));
    }

    protected function resolvePositionName($employee): string
    {
        return trim((string) ($employee->position?->position_name_km ?: $employee->position?->position_name ?: ''));
    }

    protected function resolvePayGrade($employee): string
    {
        foreach ([
            trim((string) ($employee->employee_grade ?? '')),
            trim((string) ($employee->currentPayGradeHistory?->payLevel?->level_name_km ?? '')),
            trim((string) ($employee->currentPayGradeHistory?->payLevel?->level_code ?? '')),
            trim((string) ($employee->latestPayGradeHistory?->payLevel?->level_name_km ?? '')),
            trim((string) ($employee->latestPayGradeHistory?->payLevel?->level_code ?? '')),
        ] as $candidate) {
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    protected function resolvePayGradeSortOrder($employee): int
    {
        // Sort by the grade printed in the report, including legacy employee_grade values.
        $key = $this->normalizePayGradeKey($this->resolvePayGrade($employee));
        if ($key === '') {
            return PHP_INT_MAX;
        }

        if ($this->payGradeSortOrderCache === null) {
            $this->payGradeSortOrderCache = [];
            foreach (GovPayLevel::withTrashed()->get(['level_code', 'level_name_km', 'sort_order']) as $level) {
                foreach ([$level->level_code, $level->level_name_km] as $value) {
                    $levelKey = $this->normalizePayGradeKey((string) $value);
                    if ($levelKey !== '') {
                        $this->payGradeSortOrderCache[$levelKey] = $level->sort_order ?? PHP_INT_MAX;
                    }
                }
            }
        }

        return $this->payGradeSortOrderCache[$key] ?? PHP_INT_MAX;
    }

    protected function normalizePayGradeKey(string $value): string
    {
        $value = strtr(mb_strtoupper(trim($value), 'UTF-8'), [
            '០' => '0', '១' => '1', '២' => '2', '៣' => '3', '៤' => '4',
            '៥' => '5', '៦' => '6', '៧' => '7', '៨' => '8', '៩' => '9',
        ]);

        return preg_replace('/[\s.\-]+/u', '', $value) ?? $value;
    }

    protected function resolveLastPromotionDate($employee)
    {
        return $employee->currentPayGradeHistory?->start_date
            ?? $employee->latestPayGradeHistory?->start_date
            ?? $employee->promotion_date
            ?? null;
    }

    protected function resolveSkillStatisticLabel($employee): string
    {
        $skill = $this->normalizeSkillLabel($this->resolveSkillName($employee));
        if ($skill === '') {
            return 'ផ្សេងៗ';
        }

        $patterns = [
            'បរិញ្ញាប័ត្រគិលានុបដ្ឋាក' => ['បរិញ្ញាប័ត្រគិលានុបដ្ឋាក', 'គិលានុបដ្ឋាកបរិញ្ញាប័ត្រ'],
            'បរិញ្ញាប័ត្រឆ្មប' => ['បរិញ្ញាប័ត្រឆ្មប', 'ឆ្មបបរិញ្ញាប័ត្រ'],
            'ទន្តគិលានុបដ្ឋាក' => ['ទន្តគិលានុបដ្ឋាក'],
            'ទន្តបណ្ឌិត' => ['ទន្តបណ្ឌិត', 'dentist'],
            'គ្រូពេទ្យមធ្យម' => ['គ្រូពេទ្យមធ្យម'],
            'ឆ្មបបឋម' => ['ឆ្មបបឋម'],
            'ឆ្មបមធ្យម' => ['ឆ្មបមធ្យម'],
            'គិ.បឋម' => ['គិបឋម', 'គិលានុបដ្ឋាកបឋម'],
            'គិ.មធ្យម' => ['គិមធ្យម', 'គិលានុបដ្ឋាកមធ្យម'],
            'វេជ្ជបណ្ឌិត' => ['វេជ្ជបណ្ឌិត', 'medicaldoctor', 'doctor'],
            'ពត៌មានវិទ្យា' => ['ពត៌មានវិទ្យា', 'ព័ត៌មានវិទ្យា', 'ict', 'informationtechnology'],
            'ព្យាបាលដោយចលនា' => ['ព្យាបាលដោយចលនា', 'physio'],
            'គណនេយ្យឧត្តម' => ['គណនេយ្យឧត្តម', 'accounting'],
            'ពិសោធន៍មធ្យម' => ['ពិសោធន៍មធ្យម'],
            'បច្ចេក.រដ្ឋបាល' => ['បច្ចេករដ្ឋបាល', 'បច្ចេក.រដ្ឋបាល', 'administrativetechnician', 'រដ្ឋបាល'],
            'ឱសថការី' => ['ឱសថការី', 'pharmacist', 'pharmacy'],
            'កម្មករ' => ['កម្មករ', 'worker'],
        ];

        foreach ($patterns as $label => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($skill, $this->normalizeSkillLabel($keyword))) {
                    return $label;
                }
            }
        }

        return 'ផ្សេងៗ';
    }

    protected function normalizeSkillLabel(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = preg_replace('/[\s\.\-–_,()]+/u', '', $value);

        return (string) $value;
    }

    protected function formatDateTime($value): string
    {
        if (blank($value)) {
            return '';
        }

        try {
            return Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable $e) {
            return trim((string) $value);
        }
    }

    protected function khmerLunarDateText(): string
    {
        $fallback = 'ថ្ងៃទី........ ខែ........ ឆ្នាំ........ ព.ស........';

        try {
            $khmerDate = new KhmerDate(Carbon::today()->toDateString());

            return trim((string) $khmerDate->toLunarDate()) ?: $fallback;
        } catch (\Throwable $e) {
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

        return sprintf(
            '%s ថ្ងៃទី%s ខែ%s ឆ្នាំ%s',
            trim((string) ($this->meta['location_text'] ?? 'ស្ទឹងត្រែង')) ?: 'ស្ទឹងត្រែង',
            $this->toKhmerDigits($today->format('d')),
            $months[(int) $today->month] ?? '',
            $this->toKhmerDigits($today->format('Y'))
        );
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
}

class EmployeeStructuredListSheet implements FromArray, WithTitle, WithStyles, WithEvents, WithColumnWidths
{
    protected array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function title(): string
    {
        return 'List_of_Staff';
    }

    public function array(): array
    {
        return $this->payload['rows'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['name' => 'Khmer M1', 'size' => 16, 'bold' => false]],
            2 => ['font' => ['name' => 'Khmer M1', 'size' => 16, 'bold' => false]],
            3 => ['font' => ['name' => 'Tacteing', 'size' => 48, 'bold' => false]],
            5 => ['font' => ['name' => 'Khmer M1', 'size' => 12, 'bold' => false]],
            6 => ['font' => ['name' => 'Khmer M1', 'size' => 12, 'bold' => false]],
            8 => ['font' => ['name' => 'Khmer M1', 'size' => 14, 'bold' => false]],
            10 => ['font' => ['name' => 'Khmer OS Siemreap', 'size' => 12, 'bold' => true]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = count($this->payload['rows']);

                $sheet->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setPaperSize(PageSetup::PAPERSIZE_A4)
                    ->setFitToWidth(1)
                    ->setFitToHeight(0)
                    ->setHorizontalCentered(true);

                $sheet->mergeCells('A1:M1');
                $sheet->mergeCells('A2:M2');
                $sheet->mergeCells('A3:M3');
                $sheet->mergeCells('A8:M8');

                foreach ([1, 2, 3, 8] as $row) {
                    $sheet->getStyle("A{$row}:M{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                }

                $sheet->getStyle("A10:P10")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);

                $sheet->getStyle("A1:P{$lastRow}")->getFont()->setName('Khmer OS Siemreap')->setSize(12);

                foreach ($this->payload['group_rows'] as $row) {
                    $sheet->getStyle("A{$row}:P{$row}")->applyFromArray([
                        'font' => [
                            'name' => 'Khmer OS Siemreap',
                            'size' => 12,
                            'bold' => true,
                        ],
                    ]);
                }

                $sheet->getStyle('N10:O' . $lastRow)->getFont()->setName('Arial')->setSize(12);
                foreach ($this->payload['employee_rows'] as $row) {
                    $sheet->getStyle("N{$row}")->getFont()->setBold(true);
                    $sheet->getStyle("N{$row}:O{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                        ->setVertical(Alignment::VERTICAL_TOP);
                }

                foreach (($this->payload['without_pay_rows'] ?? []) as $row) {
                    $sheet->getStyle("A{$row}:P{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()
                        ->setRGB('DDEBF7');
                }

                foreach (($this->payload['offender_rows'] ?? []) as $row) {
                    $sheet->getStyle("A{$row}:P{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()
                        ->setRGB('F4CCCC');
                }

                $sheet->getStyle('A1:M1')->getFont()->setName('Khmer M1')->setSize(16);
                $sheet->getStyle('A2:M2')->getFont()->setName('Khmer M1')->setSize(16);
                $sheet->getStyle('A3:M3')->getFont()->setName('Tacteing')->setSize(48);
                $sheet->getStyle('A5:M5')->getFont()->setName('Khmer M1')->setSize(12);
                $sheet->getStyle('A6:M6')->getFont()->setName('Khmer M1')->setSize(12);
                $sheet->getStyle('A8:M8')->getFont()->setName('Khmer M1')->setSize(14);
                $sheet->getStyle('A10:M10')->getFont()->setName('Khmer OS Siemreap')->setSize(12)->setBold(true);
                $sheet->getStyle('N10:O10')->getFont()->setName('Arial')->setSize(12)->setBold(true);

                foreach (array_merge([10], $this->payload['employee_rows']) as $row) {
                    $sheet->getStyle("A{$row}:P{$row}")
                        ->getBorders()
                        ->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN)
                        ->getColor()
                        ->setRGB('3B3B3B');
                }

                foreach (['A', 'B', 'C', 'F', 'G', 'H', 'I', 'L', 'M'] as $column) {
                    foreach ($this->payload['employee_rows'] as $row) {
                        $sheet->getStyle("{$column}{$row}")->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                            ->setVertical(Alignment::VERTICAL_CENTER);
                    }
                }

                $sheet->getStyle('A' . $this->payload['note_row'])->getFont()->setBold(true);
                $sheet->setAutoFilter('A10:P10');
                $sheet->getColumnDimension('Q')->setVisible(false);

                $sheet->getRowDimension(1)->setRowHeight(36.75);
                $sheet->getRowDimension(2)->setRowHeight(33.00);
                $sheet->getRowDimension(3)->setRowHeight(25.95);
                $sheet->getRowDimension(4)->setRowHeight(62.40);
                $sheet->getRowDimension(5)->setRowHeight(43.20);
                $sheet->getRowDimension(6)->setRowHeight(21.60);
                $sheet->getRowDimension(7)->setRowHeight(21.60);
                $sheet->getRowDimension(8)->setRowHeight(37.20);
                $sheet->getRowDimension(9)->setRowHeight(24.60);
                $sheet->getRowDimension(10)->setRowHeight(92.40);

                for ($row = 11; $row <= $lastRow; $row++) {
                    $sheet->getRowDimension($row)->setRowHeight(24.60);
                }
            },
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6.33,
            'B' => 6.89,
            'C' => 16.66,
            'D' => 19.66,
            'E' => 28.78,
            'F' => 8.66,
            'G' => 11.78,
            'H' => 14.89,
            'I' => 11.00,
            'J' => 15.44,
            'K' => 32.22,
            'L' => 9.44,
            'M' => 12.00,
            'N' => 26.00,
            'O' => 20.00,
            'P' => 18.00,
        ];
    }
}

class EmployeeStructuredBySkillSheet implements FromArray, WithTitle, WithStyles, WithEvents, WithColumnWidths
{
    protected array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function title(): string
    {
        return 'BySkill';
    }

    public function array(): array
    {
        return $this->payload['rows'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['name' => 'Khmer M1', 'size' => 14, 'bold' => false]],
            4 => ['font' => ['name' => 'Khmer M1', 'size' => 10, 'bold' => false]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $footerStart = $this->payload['footer_start_row'];

                $sheet->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setPaperSize(PageSetup::PAPERSIZE_A4)
                    ->setFitToWidth(1)
                    ->setFitToHeight(0)
                    ->setHorizontalCentered(true);

                $sheet->mergeCells('A1:U3');
                $sheet->mergeCells('A' . ($footerStart + 1) . ':G' . ($footerStart + 1));
                $sheet->mergeCells('K' . ($footerStart + 1) . ':S' . ($footerStart + 1));
                $sheet->mergeCells('A' . ($footerStart + 2) . ':F' . ($footerStart + 2));
                $sheet->mergeCells('K' . ($footerStart + 2) . ':S' . ($footerStart + 2));
                $sheet->mergeCells('A' . ($footerStart + 3) . ':F' . ($footerStart + 3));
                $sheet->mergeCells('A' . ($footerStart + 4) . ':F' . ($footerStart + 4));

                $sheet->getStyle('A1:U3')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('A4:U4')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);

                $sheet->getStyle('A4:U' . $this->payload['total_row'])->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setRGB('3B3B3B');

                $sheet->getStyle('A' . $this->payload['total_row'] . ':U' . $this->payload['total_row'])->applyFromArray([
                    'font' => ['name' => 'Khmer OS Siemreap', 'size' => 11, 'bold' => true],
                ]);

                $sheet->getStyle('A1:W' . count($this->payload['rows']))->getFont()->setName('Khmer OS Siemreap')->setSize(10);
                $sheet->getStyle('A1:U3')->getFont()->setName('Khmer M1')->setSize(14);
                $sheet->getStyle('A4')->getFont()->setName('Khmer M1')->setSize(11);
                $sheet->getStyle('B4:U4')->getFont()->setName('Khmer M1')->setSize(10);
                $sheet->getStyle('A5:U' . $this->payload['total_row'])->getFont()->setName('Khmer OS Siemreap')->setSize(11)->setBold(true);
                $sheet->getStyle('A' . ($footerStart + 1) . ':U' . count($this->payload['rows']))->getFont()->setName('Khmer OS Siemreap')->setSize(10)->setBold(false);
                $sheet->getStyle('K' . ($footerStart + 2) . ':S' . ($footerStart + 2))->getFont()->setName('Khmer M1')->setSize(11);
                $sheet->getStyle('A' . ($footerStart + 4) . ':F' . ($footerStart + 4))->getFont()->setName('Khmer M1')->setSize(11);

                foreach (range($this->payload['unit_row_start'], $this->payload['total_row']) as $row) {
                    $sheet->getStyle("B{$row}:U{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                }
                $sheet->getStyle("A{$this->payload['unit_row_start']}:A{$this->payload['total_row']}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $sheet->setAutoFilter('A4:U4');

                $sheet->getRowDimension(1)->setRowHeight(4.20);
                $sheet->getRowDimension(2)->setRowHeight(13.20);
                $sheet->getRowDimension(3)->setRowHeight(34.95);
                $sheet->getRowDimension(4)->setRowHeight(123.60);
                for ($row = 5; $row <= $this->payload['total_row']; $row++) {
                    $sheet->getRowDimension($row)->setRowHeight(31.20);
                }
                $sheet->getRowDimension($this->payload['total_row'])->setRowHeight(24.00);
                $sheet->getRowDimension($footerStart)->setRowHeight(21.60);
                $sheet->getRowDimension($footerStart + 1)->setRowHeight(21.60);
                $sheet->getRowDimension($footerStart + 2)->setRowHeight(22.80);
                $sheet->getRowDimension($footerStart + 3)->setRowHeight(21.60);
                $sheet->getRowDimension($footerStart + 4)->setRowHeight(22.80);
            },
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 33.55,
            'B' => 5.11,
            'C' => 5.11,
            'D' => 5.11,
            'E' => 5.11,
            'F' => 5.11,
            'G' => 5.11,
            'H' => 5.11,
            'I' => 4.89,
            'J' => 5.11,
            'K' => 5.33,
            'L' => 5.11,
            'M' => 5.11,
            'N' => 5.33,
            'O' => 5.11,
            'P' => 5.11,
            'Q' => 5.33,
            'R' => 5.11,
            'S' => 5.11,
            'T' => 5.11,
            'U' => 5.11,
            'V' => 4.00,
            'W' => 4.00,
        ];
    }
}
