<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\EmployeeUnitPosting;
use Modules\HumanResource\Entities\Position;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class SyncStaffExcel extends Command
{
    protected $signature = 'hr:sync-staff-excel
        {file : Path to the source Excel file}
        {--sheet=Staff_Data : Worksheet name to import}
        {--admin-user-id=1 : Existing user id to assign to newly created employees}
        {--unit-prefix=LEGACY-WP-1-2-19 : Department location_code prefix that identifies the target org tree}
        {--dry-run : Analyze without writing database changes}
        {--keep-missing : Do not soft-delete current employees that are missing from the Excel file}';

    protected $description = 'Sync authoritative staff data from an Excel worksheet into the employee table';

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $runtimeUnitTargets = [];

    private const POSITION_NAME_TO_ID = [
        'ប្រធានមន្ទីរសុខាភិបាលខេត្តក្រុង' => 14,
        'អនុប្រធានមន្ទីរសុខាភិបាលខេត្តក្រុង' => 15,
        'ប្រធានការិយាល័យសុខាភិបាលស្រុកប្រតិបត្តិ' => 16,
        'អនុប្រធានការិយាល័យសុខាភិបាលស្រុកប្រតិបត្តិ' => 17,
        'ប្រធានមន្ទីរពេទ្យបង្អែក' => 18,
        'អនុប្រធានមន្ទីរពេទ្យបង្អែក' => 19,
        'ប្រធានមណ្ឌលសុខភាព' => 20,
        'អនុប្រធានមណ្ឌលសុខភាព' => 21,
        'បុគ្គលិក' => 22,
        'ប្រធានការិយាល័យខេត្តក្រុង' => 38,
        'អនុប្រធានការិយាល័យខេត្តក្រុង' => 39,
    ];

    private const UNIT_TARGETS = [
        'ទីចាត់ការមន្ទីរសុខាភិបាល' => ['department_id' => 16, 'expected_prefix' => 'LEGACY-WP-1-2-19-2'],
        'មន្ទីរពេទ្យខេត្ត' => ['department_id' => 17, 'expected_prefix' => 'LEGACY-WP-1-2-19-20'],
        'ការិយាល័យសុខាភិបាលស្រុកប្រតិបត្តិស្ទឹងត្រែង' => ['department_id' => 51, 'expected_prefix' => 'LEGACY-WP-1-2-19-21-1'],
        '២មណ្ឌលសុខភាពស្ទឹងត្រែង' => ['department_id' => 81, 'expected_prefix' => 'LEGACY-WP-1-2-19-21-2-6'],
        '៣មណ្ឌលសុខភាពសាមគ្គី' => ['department_id' => 82, 'expected_prefix' => 'LEGACY-WP-1-2-19-21-2-7'],
        '៤មណ្ឌសុខភាពកំភុន' => ['department_id' => 83, 'expected_prefix' => 'LEGACY-WP-1-2-19-21-2-8'],
        '៥មណ្ឌលសុខភាពក្បាលរមាស' => ['department_id' => 67, 'expected_prefix' => 'LEGACY-WP-1-2-19-21-2-12'],
        '៦មណ្ឌលសុខភាពស្រែគរ' => ['department_id' => 65, 'expected_prefix' => 'LEGACY-WP-1-2-19-21-2-10'],
        '៧មណ្ឌលសុខភាពស្រែពក' => ['department_id' => 68, 'expected_prefix' => 'LEGACY-WP-1-2-19-21-2-13'],
        '៨មណ្ឌលសុខភាពព្រះរំកិល' => ['department_id' => 78, 'expected_prefix' => 'LEGACY-WP-1-2-19-21-2-3'],
        '៩មណ្ឌលសុខភាពចំការលើ' => ['department_id' => 64, 'expected_prefix' => 'LEGACY-WP-1-2-19-21-2-1'],
        '១០មណ្ឌលសុខភាពថាឡាបរិវ៉ាត់' => ['department_id' => 75, 'expected_prefix' => 'LEGACY-WP-1-2-19-21-2-2'],
        '១១មណ្ឌលសុខភាពស្រែក្រសាំង' => ['department_id' => 80, 'expected_prefix' => 'LEGACY-WP-1-2-19-21-2-5'],
        '១២មណ្ឌលសុខភាពកោះស្រឡាយ' => ['department_id' => 84, 'expected_prefix' => 'LEGACY-WP-1-2-19-21-2-9'],
        '១៣មណ្ឌលសុខភាពកោះព្រះ' => ['department_id' => 66, 'expected_prefix' => 'LEGACY-WP-1-2-19-21-2-11'],
        '១៤មណ្ឌលសុខភាពសៀមប៉ាង' => ['department_id' => 79, 'expected_prefix' => 'LEGACY-WP-1-2-19-21-2-4'],
        '១៥មណ្ឌលសុខភាពស្រែសំបូរ' => ['department_id' => 69, 'expected_prefix' => 'LEGACY-WP-1-2-19-21-2-14'],
        '១៦មណ្ឌលសុខភាពវាលដេញ' => ['department_id' => 70, 'expected_prefix' => 'LEGACY-WP-1-2-19-21-2-15'],
        '១៧មណ្ឌលសុខភាពតាឡាត' => ['department_id' => 71, 'expected_prefix' => 'LEGACY-WP-1-2-19-21-2-16'],
        '១៨មណ្ឌលសុខភាពត្បូងខ្លា' => ['department_id' => 72, 'expected_prefix' => 'LEGACY-WP-1-2-19-21-2-17'],
        '១៩មណ្ឌលសុខភាពព្រះនរោត្តមសីហនុរាជានិងសម្តេចព្រះវររាជមាតានរោត្តមមុនិនាថសីហនុអូរស្វាយ' => ['department_id' => 73, 'expected_prefix' => 'LEGACY-WP-1-2-19-21-2-18'],
        '២០មណ្ឌសុខភាពព្រែកមាស' => ['department_id' => 76, 'expected_prefix' => 'LEGACY-WP-1-2-19-21-2-20'],
        '២១មណ្ឌលសុខភាពសៀមបូក' => ['department_id' => 77, 'expected_prefix' => 'LEGACY-WP-1-2-19-21-2-21'],
        '២២មណ្ឌលសុខភាពសន្តិភាព' => ['department_id' => 74, 'expected_prefix' => 'LEGACY-WP-1-2-19-21-2-19'],
    ];

    private const STUNG_TRENG_HEALTH_UNIT_SPECS = [
        [
            'unit_name' => '៩-មណ្ឌលសុខភាពចំការលើ',
            'location_code' => 'LEGACY-WP-1-2-19-21-2-1',
            'parent_location_code' => 'LEGACY-WP-1-2-19-21-2',
            'unit_type_code' => 'health_center',
            'sort_order' => 1,
        ],
        [
            'unit_name' => '១០-មណ្ឌលសុខភាពថាឡាបរិវ៉ាត់',
            'location_code' => 'LEGACY-WP-1-2-19-21-2-2',
            'parent_location_code' => 'LEGACY-WP-1-2-19-21-2',
            'unit_type_code' => 'health_center',
            'sort_order' => 2,
        ],
        [
            'unit_name' => '៨-មណ្ឌលសុខភាពព្រះរំកិល',
            'location_code' => 'LEGACY-WP-1-2-19-21-2-3',
            'parent_location_code' => 'LEGACY-WP-1-2-19-21-2',
            'unit_type_code' => 'health_center',
            'sort_order' => 3,
        ],
        [
            'unit_name' => '១៤-មណ្ឌលសុខភាពសៀមប៉ាង',
            'location_code' => 'LEGACY-WP-1-2-19-21-2-4',
            'parent_location_code' => 'LEGACY-WP-1-2-19-21-2',
            'unit_type_code' => 'health_center',
            'sort_order' => 4,
        ],
        [
            'unit_name' => '១១-មណ្ឌលសុខភាពស្រែក្រសាំង',
            'location_code' => 'LEGACY-WP-1-2-19-21-2-5',
            'parent_location_code' => 'LEGACY-WP-1-2-19-21-2',
            'unit_type_code' => 'health_center',
            'sort_order' => 5,
        ],
        [
            'unit_name' => '២-មណ្ឌលសុខភាពស្ទឹងត្រែង',
            'location_code' => 'LEGACY-WP-1-2-19-21-2-6',
            'parent_location_code' => 'LEGACY-WP-1-2-19-21-2',
            'unit_type_code' => 'health_center',
            'sort_order' => 6,
        ],
        [
            'unit_name' => '៣-មណ្ឌលសុខភាពសាមគ្គី',
            'location_code' => 'LEGACY-WP-1-2-19-21-2-7',
            'parent_location_code' => 'LEGACY-WP-1-2-19-21-2',
            'unit_type_code' => 'health_center',
            'sort_order' => 7,
        ],
        [
            'unit_name' => '៤-មណ្ឌសុខភាពកំភុន',
            'location_code' => 'LEGACY-WP-1-2-19-21-2-8',
            'parent_location_code' => 'LEGACY-WP-1-2-19-21-2',
            'unit_type_code' => 'health_center',
            'sort_order' => 8,
        ],
        [
            'unit_name' => '១២-មណ្ឌលសុខភាពកោះស្រឡាយ',
            'location_code' => 'LEGACY-WP-1-2-19-21-2-9',
            'parent_location_code' => 'LEGACY-WP-1-2-19-21-2',
            'unit_type_code' => 'health_center',
            'sort_order' => 9,
        ],
        [
            'unit_name' => '៦-មណ្ឌលសុខភាពស្រែគរ',
            'location_code' => 'LEGACY-WP-1-2-19-21-2-10',
            'parent_location_code' => 'LEGACY-WP-1-2-19-21-2',
            'unit_type_code' => 'health_center',
            'sort_order' => 10,
        ],
        [
            'unit_name' => '១៣-មណ្ឌលសុខភាពកោះព្រះ',
            'location_code' => 'LEGACY-WP-1-2-19-21-2-11',
            'parent_location_code' => 'LEGACY-WP-1-2-19-21-2',
            'unit_type_code' => 'health_center',
            'sort_order' => 11,
        ],
        [
            'unit_name' => '៥-មណ្ឌលសុខភាពក្បាលរមាស',
            'location_code' => 'LEGACY-WP-1-2-19-21-2-12',
            'parent_location_code' => 'LEGACY-WP-1-2-19-21-2',
            'unit_type_code' => 'health_center',
            'sort_order' => 12,
        ],
        [
            'unit_name' => '៧-មណ្ឌលសុខភាពស្រែពក',
            'location_code' => 'LEGACY-WP-1-2-19-21-2-13',
            'parent_location_code' => 'LEGACY-WP-1-2-19-21-2',
            'unit_type_code' => 'health_center',
            'sort_order' => 13,
        ],
        [
            'unit_name' => '១៥-មណ្ឌលសុខភាពស្រែសំបូរ',
            'location_code' => 'LEGACY-WP-1-2-19-21-2-14',
            'parent_location_code' => 'LEGACY-WP-1-2-19-21-2',
            'unit_type_code' => 'health_center',
            'sort_order' => 14,
        ],
        [
            'unit_name' => '១៦-មណ្ឌលសុខភាពវាលដេញ',
            'location_code' => 'LEGACY-WP-1-2-19-21-2-15',
            'parent_location_code' => 'LEGACY-WP-1-2-19-21-2',
            'unit_type_code' => 'health_center',
            'sort_order' => 15,
        ],
        [
            'unit_name' => '១៧-មណ្ឌលសុខភាពតាឡាត',
            'location_code' => 'LEGACY-WP-1-2-19-21-2-16',
            'parent_location_code' => 'LEGACY-WP-1-2-19-21-2',
            'unit_type_code' => 'health_center',
            'sort_order' => 16,
        ],
        [
            'unit_name' => '១៨-មណ្ឌលសុខភាពត្បូងខ្លា',
            'location_code' => 'LEGACY-WP-1-2-19-21-2-17',
            'parent_location_code' => 'LEGACY-WP-1-2-19-21-2',
            'unit_type_code' => 'health_center',
            'sort_order' => 17,
        ],
        [
            'unit_name' => '១៩-មណ្ឌលសុខភាពព្រះនរោត្តម សីហនុរាជា និងសម្តេចព្រះវររាជមាតា នរោត្តម មុនិនាថ សីហនុ អូរស្វាយ',
            'location_code' => 'LEGACY-WP-1-2-19-21-2-18',
            'parent_location_code' => 'LEGACY-WP-1-2-19-21-2',
            'unit_type_code' => 'health_center',
            'sort_order' => 18,
        ],
        [
            'unit_name' => '២២-មណ្ឌលសុខភាពសន្តិភាព',
            'location_code' => 'LEGACY-WP-1-2-19-21-2-19',
            'parent_location_code' => 'LEGACY-WP-1-2-19-21-2',
            'unit_type_code' => 'health_center',
            'sort_order' => 19,
        ],
        [
            'unit_name' => '២០-មណ្ឌសុខភាពព្រែកមាស',
            'location_code' => 'LEGACY-WP-1-2-19-21-2-20',
            'parent_location_code' => 'LEGACY-WP-1-2-19-21-2',
            'unit_type_code' => 'health_center',
            'sort_order' => 20,
        ],
        [
            'unit_name' => '២១-មណ្ឌលសុខភាពសៀមបូក',
            'location_code' => 'LEGACY-WP-1-2-19-21-2-21',
            'parent_location_code' => 'LEGACY-WP-1-2-19-21-2',
            'unit_type_code' => 'health_center',
            'sort_order' => 21,
        ],
        [
            'unit_name' => '២៣-មណ្ឌសុខភាពស្ដៅ',
            'location_code' => 'LEGACY-WP-1-2-19-21-2-22',
            'parent_location_code' => 'LEGACY-WP-1-2-19-21-2',
            'unit_type_code' => 'health_center',
            'sort_order' => 22,
        ],
        [
            'unit_name' => '២៤-មណ្ឌសុខភាពអូរពងមាន់',
            'location_code' => 'LEGACY-WP-1-2-19-21-2-23',
            'parent_location_code' => 'LEGACY-WP-1-2-19-21-2',
            'unit_type_code' => 'health_center',
            'sort_order' => 23,
        ],
        [
            'unit_name' => '២៥-ប៉ុស្ដិ៍សុខភាពសំអាង',
            'location_code' => 'LEGACY-WP-1-2-19-21-2-24',
            'parent_location_code' => 'LEGACY-WP-1-2-19-21-2-2',
            'unit_type_code' => 'health_post',
            'sort_order' => 24,
        ],
        [
            'unit_name' => '២៦-ប៉ុស្ដិ៍សុខភាពកាំងចាម',
            'location_code' => 'LEGACY-WP-1-2-19-21-2-25',
            'parent_location_code' => 'LEGACY-WP-1-2-19-21-2-2',
            'unit_type_code' => 'health_post',
            'sort_order' => 25,
        ],
        [
            'unit_name' => '២៧-ប៉ុស្ដិ៍សុខភាពកោះស្នែង',
            'location_code' => 'LEGACY-WP-1-2-19-21-2-26',
            'parent_location_code' => 'LEGACY-WP-1-2-19-21-2-3',
            'unit_type_code' => 'health_post',
            'sort_order' => 26,
        ],
        [
            'unit_name' => '២៨-ប៉ុស្ដិ៍សុខភាពអន្លង់ភេ',
            'location_code' => 'LEGACY-WP-1-2-19-21-2-27',
            'parent_location_code' => 'LEGACY-WP-1-2-19-21-2-1',
            'unit_type_code' => 'health_post',
            'sort_order' => 27,
        ],
        [
            'unit_name' => '២៩-ប៉ុស្ដិ៍សុខភាពច្រប់',
            'location_code' => 'LEGACY-WP-1-2-19-21-2-28',
            'parent_location_code' => 'LEGACY-WP-1-2-19-21-2-12',
            'unit_type_code' => 'health_post',
            'sort_order' => 28,
        ],
        [
            'unit_name' => '៣០-ប៉ុស្ដិ៍សុខភាពសែនជ័យ',
            'location_code' => 'LEGACY-WP-1-2-19-21-2-29',
            'parent_location_code' => 'LEGACY-WP-1-2-19-21-2-12',
            'unit_type_code' => 'health_post',
            'sort_order' => 29,
        ],
        [
            'unit_name' => '៣១-ប៉ុស្ដិ៍សុខភាពសហគមន៍កាតូត',
            'location_code' => 'LEGACY-WP-1-2-19-21-2-30',
            'parent_location_code' => 'LEGACY-WP-1-2-19-21-2-8',
            'unit_type_code' => 'health_post',
            'sort_order' => 30,
        ],
    ];

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        $sheetName = (string) $this->option('sheet');
        $adminUserId = (int) $this->option('admin-user-id');
        $unitPrefix = trim((string) $this->option('unit-prefix'));
        $dryRun = (bool) $this->option('dry-run');
        $keepMissing = (bool) $this->option('keep-missing');

        if ($file === '' || !is_file($file)) {
            $this->error("Excel file not found: {$file}");
            return self::FAILURE;
        }

        if ($adminUserId <= 0 || !DB::table('users')->where('id', $adminUserId)->exists()) {
            $this->error("Invalid --admin-user-id: {$adminUserId}");
            return self::FAILURE;
        }

        $this->syncStungTrengHealthUnits($dryRun);
        $this->hydrateRuntimeUnitTargets();

        $targetDepartments = Department::withoutGlobalScopes()
            ->select('id', 'department_name', 'location_code')
            ->where('location_code', 'like', $unitPrefix . '%')
            ->orderBy('id')
            ->get();

        if ($targetDepartments->isEmpty()) {
            $this->error("No departments found for unit prefix {$unitPrefix}");
            return self::FAILURE;
        }

        $targetDepartmentIds = $targetDepartments->pluck('id')->map(fn ($id) => (int) $id)->all();
        $departmentLocationById = $targetDepartments
            ->pluck('location_code', 'id')
            ->map(fn ($value) => (string) $value)
            ->all();

        try {
            $sourceRows = $this->parseWorksheet($file, $sheetName);
        } catch (Throwable $e) {
            $this->error('Failed to read Excel file: ' . $e->getMessage());
            return self::FAILURE;
        }

        if ($sourceRows === []) {
            $this->error("No staff rows found in sheet {$sheetName}");
            return self::FAILURE;
        }

        $currentEmployees = Employee::withTrashed()
            ->whereIn('department_id', $targetDepartmentIds)
            ->orderBy('id')
            ->get();

        $state = $this->buildMatchingState($currentEmployees);
        $backupPath = $this->writeBackup($file, $sheetName, $dryRun, $sourceRows, $currentEmployees);

        $summary = [
            'source_rows' => count($sourceRows),
            'current_rows' => $currentEmployees->count(),
            'exact_matches' => 0,
            'khmer_name_matches' => 0,
            'latin_name_matches' => 0,
            'fuzzy_latin_matches' => 0,
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'restored' => 0,
            'soft_deleted' => 0,
            'warnings' => 0,
        ];

        $warnings = [];
        $matchedEmployeeIds = [];
        $nextEmployeeCode = $this->nextEmployeeCode();
        $sourceOfficialCounts = collect($sourceRows)
            ->countBy('official_id_10')
            ->map(fn ($count) => (int) $count)
            ->all();

        DB::beginTransaction();

        try {
            foreach ($sourceRows as $row) {
                [$employee, $matchType] = $this->matchEmployee($row, $state, $matchedEmployeeIds);
                $payload = $this->buildPayload(
                    $row,
                    $employee,
                    $adminUserId,
                    $nextEmployeeCode,
                    $departmentLocationById,
                    (int) ($sourceOfficialCounts[$row['official_id_10']] ?? 0)
                );

                foreach ($payload['warnings'] as $warning) {
                    $warnings[] = $warning;
                }

                $postingNeedsSync = $this->employeePrimaryPostingNeedsSync($employee, $payload['attributes']);

                if ($employee) {
                    $matchedEmployeeIds[$employee->id] = true;
                    $summary[$this->matchSummaryKey($matchType)]++;

                    $changes = $this->detectChanges($employee, $payload['attributes']);
                    if ($changes === [] && !$postingNeedsSync) {
                        $summary['unchanged']++;
                        continue;
                    }

                    if (!$dryRun) {
                        if ($employee->trashed()) {
                            $employee->restore();
                            $summary['restored']++;
                        }

                        if ($changes !== []) {
                            $employee->fill($payload['attributes']);
                            $employee->save();
                        }

                        $this->syncPrimaryPosting($employee, $payload['attributes']);
                    } elseif ($employee->trashed()) {
                        $summary['restored']++;
                    }

                    $summary['updated']++;
                    continue;
                }

                $matchedEmployeeIds['new:' . $row['official_id_10']] = true;
                $summary['created']++;

                if (!$dryRun) {
                    $employee = new Employee();
                    $employee->fill($payload['attributes']);
                    $employee->uuid = (string) Str::uuid();
                    $employee->save();
                    $this->syncPrimaryPosting($employee, $payload['attributes']);
                }

                $nextEmployeeCode++;
            }

            if (!$keepMissing) {
                $staleEmployees = $currentEmployees
                    ->filter(fn (Employee $employee) => !$employee->trashed() && !isset($matchedEmployeeIds[$employee->id]));

                foreach ($staleEmployees as $staleEmployee) {
                    if (!$dryRun) {
                        $staleEmployee->delete();
                    }
                    $summary['soft_deleted']++;
                }
            }

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (Throwable $e) {
            DB::rollBack();
            $this->error('Sync failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $summary['warnings'] = count($warnings);

        $this->line('Backup: ' . $backupPath);
        foreach ($summary as $key => $value) {
            $this->line(str_pad($key, 22) . ': ' . $value);
        }

        if ($warnings !== []) {
            $this->newLine();
            $this->warn('Warnings');
            foreach (array_slice($warnings, 0, 20) as $warning) {
                $this->line('- ' . $warning);
            }

            if (count($warnings) > 20) {
                $this->line('- ... and ' . (count($warnings) - 20) . ' more');
            }
        }

        $this->info($dryRun ? 'Dry run completed.' : 'Sync completed successfully.');
        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseWorksheet(string $file, string $sheetName): array
    {
        $reader = IOFactory::createReaderForFile($file);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file);
        $sheet = $spreadsheet->getSheetByName($sheetName);

        if (!$sheet) {
            throw new \RuntimeException("Worksheet not found: {$sheetName}");
        }

        $rows = [];
        foreach ($sheet->toArray(null, false, false, false) as $rowNumber => $row) {
            if ($rowNumber === 0) {
                continue;
            }

            $officialId = $this->normalizeOfficialId((string) ($row[2] ?? ''));
            if ($officialId === '') {
                continue;
            }

            $rows[] = [
                'excel_row' => $rowNumber,
                'official_id_10' => $officialId,
                'full_name_km' => $this->cleanText((string) ($row[3] ?? '')),
                'full_name_latin' => $this->cleanText((string) ($row[4] ?? '')),
                'gender' => $this->cleanText((string) ($row[5] ?? '')),
                'date_of_birth' => $this->parseExcelDate($row[6] ?? null),
                'joining_date' => $this->parseExcelDate($row[7] ?? null),
                'skill_name' => $this->cleanText((string) ($row[9] ?? '')),
                'position_name' => $this->cleanText((string) ($row[10] ?? '')),
                'employee_grade' => $this->cleanText((string) ($row[11] ?? '')),
                'promotion_date' => $this->parseExcelDate($row[12] ?? null),
                'unit_name' => $this->cleanText((string) ($row[13] ?? '')),
                'skill_stat' => $this->cleanText((string) ($row[14] ?? '')),
                'program_code' => $this->cleanText((string) ($row[15] ?? '')),
                'program_name' => $this->cleanText((string) ($row[16] ?? '')),
                'next_promotion_date' => $this->parseExcelDate($row[17] ?? null),
                'promotion_status' => $this->cleanText((string) ($row[18] ?? '')),
                'retirement_date' => $this->parseExcelDate($row[20] ?? null),
                'retirement_status' => $this->cleanText((string) ($row[22] ?? '')),
                'data_check' => $this->cleanText((string) ($row[23] ?? '')),
                'action_note' => $this->cleanText((string) ($row[24] ?? '')),
            ];
        }

        return $rows;
    }

    /**
     * @param Collection<int, Employee> $employees
     * @return array<string, mixed>
     */
    private function buildMatchingState(Collection $employees): array
    {
        $byOfficialId = [];
        $byKhmerName = [];
        $byLatinName = [];

        foreach ($employees as $employee) {
            $officialId = $this->normalizeOfficialId((string) $employee->official_id_10);
            if ($officialId !== '') {
                $byOfficialId[$officialId][] = $employee;
            }

            $khmerName = $this->normalizeName($employee->full_name);
            if ($khmerName !== '') {
                $byKhmerName[$khmerName][] = $employee;
            }

            $latinName = $this->normalizeLatin($employee->full_name_latin);
            if ($latinName !== '') {
                $byLatinName[$latinName][] = $employee;
            }
        }

        return [
            'employees' => $employees,
            'by_official_id' => $byOfficialId,
            'by_khmer_name' => $byKhmerName,
            'by_latin_name' => $byLatinName,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $state
     * @param array<int|string, bool> $matchedEmployeeIds
     * @return array{0: Employee|null, 1: string}
     */
    private function matchEmployee(array $row, array $state, array $matchedEmployeeIds): array
    {
        $officialId = $row['official_id_10'];
        $khmerName = $this->normalizeName((string) $row['full_name_km']);
        $latinName = $this->normalizeLatin((string) $row['full_name_latin']);

        $exactCandidates = $state['by_official_id'][$officialId] ?? [];
        $employee = $this->pickSingleAvailableEmployee($exactCandidates, $matchedEmployeeIds);
        if ($employee) {
            return [$employee, 'exact_matches'];
        }

        $khmerCandidates = $state['by_khmer_name'][$khmerName] ?? [];
        $employee = $this->pickSingleAvailableEmployee($khmerCandidates, $matchedEmployeeIds);
        if ($employee) {
            return [$employee, 'khmer_name_matches'];
        }

        $latinCandidates = $state['by_latin_name'][$latinName] ?? [];
        $employee = $this->pickSingleAvailableEmployee($latinCandidates, $matchedEmployeeIds);
        if ($employee) {
            return [$employee, 'latin_name_matches'];
        }

        $employee = $this->pickFuzzyLatinCandidate($row, $state['employees'], $matchedEmployeeIds);
        if ($employee) {
            return [$employee, 'fuzzy_latin_matches'];
        }

        return [null, 'created'];
    }

    /**
     * @param array<Employee> $candidates
     * @param array<int|string, bool> $matchedEmployeeIds
     */
    private function pickSingleAvailableEmployee(array $candidates, array $matchedEmployeeIds): ?Employee
    {
        $available = array_values(array_filter($candidates, function (Employee $employee) use ($matchedEmployeeIds) {
            return !isset($matchedEmployeeIds[$employee->id]);
        }));

        if (count($available) !== 1) {
            return null;
        }

        return $available[0];
    }

    /**
     * @param array<string, mixed> $row
     * @param Collection<int, Employee> $employees
     * @param array<int|string, bool> $matchedEmployeeIds
     */
    private function pickFuzzyLatinCandidate(array $row, Collection $employees, array $matchedEmployeeIds): ?Employee
    {
        $latin = $this->normalizeLatin((string) $row['full_name_latin']);
        if ($latin === '') {
            return null;
        }

        $genderId = $this->resolveGenderId((string) $row['gender']);
        $sourceDob = $row['date_of_birth'] ? Carbon::parse($row['date_of_birth']) : null;
        $ranked = [];

        foreach ($employees as $employee) {
            if (isset($matchedEmployeeIds[$employee->id])) {
                continue;
            }

            $employeeLatin = $this->normalizeLatin($employee->full_name_latin);
            if ($employeeLatin === '') {
                continue;
            }

            $distance = levenshtein($latin, $employeeLatin);
            similar_text($latin, $employeeLatin, $similarity);
            if ($distance > 3 && $similarity < 85.0) {
                continue;
            }

            if ($genderId && (int) $employee->gender_id !== $genderId) {
                continue;
            }

            if ($sourceDob && $employee->date_of_birth) {
                $employeeDob = Carbon::parse((string) $employee->date_of_birth);
                if ($sourceDob->diffInDays($employeeDob) > 2) {
                    continue;
                }
            }

            $ranked[] = [
                'employee' => $employee,
                'distance' => $distance,
                'similarity' => $similarity,
            ];
        }

        if ($ranked === []) {
            return null;
        }

        usort($ranked, function (array $left, array $right) {
            if ($left['distance'] === $right['distance']) {
                return $right['similarity'] <=> $left['similarity'];
            }

            return $left['distance'] <=> $right['distance'];
        });

        $best = $ranked[0];
        if (count($ranked) > 1) {
            $second = $ranked[1];
            if ($best['distance'] === $second['distance'] && abs($best['similarity'] - $second['similarity']) < 0.01) {
                return null;
            }
        }

        return $best['employee'];
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $departmentLocationById
     * @return array{attributes: array<string, mixed>, warnings: array<int, string>}
     */
    private function buildPayload(
        array $row,
        ?Employee $employee,
        int $adminUserId,
        int $nextEmployeeCode,
        array $departmentLocationById,
        int $sourceOfficialCount
    ): array {
        $warnings = [];
        [$lastName, $firstName] = $this->splitName((string) $row['full_name_km']);
        [$lastNameLatin, $firstNameLatin] = $this->splitName((string) $row['full_name_latin']);
        $genderId = $this->resolveGenderId((string) $row['gender']);
        $targetPositionId = $this->resolvePositionId((string) $row['position_name']);
        $targetDepartment = $this->resolveDepartmentTarget((string) $row['unit_name'], $targetPositionId);

        if (!$targetDepartment) {
            $warnings[] = "Unmapped unit for {$row['official_id_10']}: {$row['unit_name']}";
        }

        if (!$targetPositionId && $row['position_name'] !== '' && $row['position_name'] !== 'ទំនេរគ្មានបៀវត្ស') {
            $warnings[] = "Unmapped position for {$row['official_id_10']}: {$row['position_name']}";
        }

        $departmentId = $employee ? (int) ($employee->department_id ?? 0) : 0;
        if ($targetDepartment && !$this->employeeDepartmentFitsTarget($employee, $targetDepartment, $departmentLocationById)) {
            $departmentId = (int) $targetDepartment['department_id'];
        } elseif ($departmentId <= 0 && $targetDepartment) {
            $departmentId = (int) $targetDepartment['department_id'];
        }

        $attributes = [
            'user_id' => (int) ($employee?->user_id ?: $adminUserId),
            'employee_id' => $employee?->employee_id ?: str_pad((string) $nextEmployeeCode, 6, '0', STR_PAD_LEFT),
            'employee_code' => (int) ($employee?->employee_code ?: $nextEmployeeCode),
            'official_id_10' => $row['official_id_10'],
            'last_name' => $lastName,
            'first_name' => $firstName,
            'last_name_latin' => $lastNameLatin,
            'first_name_latin' => $firstNameLatin,
            'gender_id' => $genderId ?: $employee?->gender_id,
            'date_of_birth' => $row['date_of_birth'] ?: $employee?->date_of_birth,
            'joining_date' => $row['joining_date'] ?: $employee?->joining_date,
            'service_start_date' => $row['joining_date'] ?: $employee?->service_start_date,
            'hire_date' => $row['joining_date'] ?: $employee?->hire_date,
            'skill_name' => $row['skill_name'] !== '' ? $row['skill_name'] : $employee?->skill_name,
            'skill_type' => $row['skill_stat'] !== '' ? $row['skill_stat'] : $employee?->skill_type,
            'employee_grade' => $row['employee_grade'] !== '' ? $row['employee_grade'] : $employee?->employee_grade,
            'promotion_date' => $row['promotion_date'] ?: $employee?->promotion_date,
            'department_id' => $departmentId ?: $employee?->department_id,
            'sub_department_id' => $departmentId ?: $employee?->sub_department_id,
            'department_text' => $row['unit_name'] !== '' ? $row['unit_name'] : $employee?->department_text,
            'home_department' => $row['unit_name'] !== '' ? $row['unit_name'] : $employee?->home_department,
            'position_id' => $targetPositionId ?: $employee?->position_id,
            'is_active' => 1,
            'is_left' => 0,
            'termination_date' => null,
            'termination_reason' => null,
            'work_status_name' => 'កំពុងបំរើការងារ',
            'service_state' => 'active',
        ];

        if ($sourceOfficialCount > 1) {
            $currentOfficialId = $employee ? $this->normalizeOfficialId((string) $employee->official_id_10) : '';
            if ($currentOfficialId !== '' && $currentOfficialId !== $row['official_id_10']) {
                $attributes['official_id_10'] = $currentOfficialId;
                $warnings[] = "Duplicate source official ID {$row['official_id_10']} for {$row['full_name_km']}; kept existing {$currentOfficialId}.";
            } elseif ($currentOfficialId === '' && !$employee) {
                $attributes['official_id_10'] = null;
                $warnings[] = "Duplicate source official ID {$row['official_id_10']} for {$row['full_name_km']}; new record will be created without official ID.";
            } else {
                $warnings[] = "Duplicate source official ID {$row['official_id_10']} appears multiple times in Excel.";
            }
        }

        return [
            'attributes' => $attributes,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param array<string, mixed> $targetDepartment
     * @param array<int, string> $departmentLocationById
     */
    private function employeeDepartmentFitsTarget(?Employee $employee, array $targetDepartment, array $departmentLocationById): bool
    {
        if (!$employee || !$employee->department_id) {
            return false;
        }

        $locationCode = $departmentLocationById[(int) $employee->department_id] ?? '';
        if ($locationCode === '') {
            return false;
        }

        return Str::startsWith($locationCode, (string) $targetDepartment['expected_prefix']);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function employeePrimaryPostingNeedsSync(?Employee $employee, array $attributes): bool
    {
        if (!$employee) {
            return true;
        }

        $departmentId = (int) ($attributes['department_id'] ?? 0);
        $positionId = (int) ($attributes['position_id'] ?? 0);

        if ($departmentId <= 0) {
            return false;
        }

        $posting = EmployeeUnitPosting::query()
            ->where('employee_id', $employee->id)
            ->where('is_primary', true)
            ->whereNull('end_date')
            ->latest('id')
            ->first();

        if (!$posting) {
            return true;
        }

        if ((int) $posting->department_id !== $departmentId) {
            return true;
        }

        if ($positionId > 0 && (int) $posting->position_id !== $positionId) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function syncPrimaryPosting(Employee $employee, array $attributes): void
    {
        $departmentId = (int) ($attributes['department_id'] ?? 0);
        $positionId = (int) ($attributes['position_id'] ?? 0);

        if ($departmentId <= 0) {
            return;
        }

        $posting = EmployeeUnitPosting::query()
            ->where('employee_id', $employee->id)
            ->where('is_primary', true)
            ->whereNull('end_date')
            ->latest('id')
            ->first();

        if (!$posting) {
            $posting = new EmployeeUnitPosting();
            $posting->employee_id = (int) $employee->id;
            $posting->is_primary = true;
            $posting->start_date = $attributes['joining_date'] ?: $employee->joining_date ?: now()->toDateString();
            $posting->note = 'Synced from hr:sync-staff-excel';
        }

        $posting->department_id = $departmentId;
        if ($positionId > 0) {
            $posting->position_id = $positionId;
        }

        $posting->save();
    }

    private function syncStungTrengHealthUnits(bool $dryRun): void
    {
        $unitTypeIds = DB::table('org_unit_types')
            ->whereIn('code', ['health_center', 'health_post'])
            ->pluck('id', 'code')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach (self::STUNG_TRENG_HEALTH_UNIT_SPECS as $spec) {
            $department = Department::withoutGlobalScopes()
                ->where('location_code', $spec['location_code'])
                ->first();

            $unitTypeId = (int) ($unitTypeIds[$spec['unit_type_code']] ?? 0);
            if ($unitTypeId <= 0) {
                continue;
            }

            $parentLocationCode = (string) ($spec['parent_location_code'] ?? 'LEGACY-WP-1-2-19-21-2');
            $parent = Department::withoutGlobalScopes()
                ->where('location_code', $parentLocationCode)
                ->first(['id']);

            if (!$parent) {
                continue;
            }

            if (!$department) {
                if ($dryRun) {
                    continue;
                }

                $department = new Department();
                $department->uuid = (string) Str::uuid();
                $department->location_code = $spec['location_code'];
            }

            $department->department_name = $spec['unit_name'];
            $department->parent_id = (int) $parent->id;
            $department->unit_type_id = $unitTypeId;
            $department->sort_order = (int) $spec['sort_order'];
            $department->is_active = true;

            if (!$dryRun) {
                $department->save();
            }
        }
    }

    private function hydrateRuntimeUnitTargets(): void
    {
        $this->runtimeUnitTargets = self::UNIT_TARGETS;

        $departmentsByLocation = Department::withoutGlobalScopes()
            ->whereIn('location_code', collect(self::STUNG_TRENG_HEALTH_UNIT_SPECS)->pluck('location_code')->all())
            ->get(['id', 'location_code'])
            ->keyBy('location_code');

        foreach (self::STUNG_TRENG_HEALTH_UNIT_SPECS as $spec) {
            $department = $departmentsByLocation->get($spec['location_code']);
            if (!$department) {
                continue;
            }

            $this->runtimeUnitTargets[$spec['unit_name']] = [
                'department_id' => (int) $department->id,
                'expected_prefix' => (string) $spec['location_code'],
            ];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveUnitTarget(string $unitName): ?array
    {
        $normalized = $this->normalizeKey($unitName);
        foreach ($this->runtimeUnitTargets as $key => $target) {
            if ($this->normalizeKey($key) === $normalized) {
                return $target;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveDepartmentTarget(string $unitName, ?int $positionId): ?array
    {
        $target = $this->resolveUnitTarget($unitName);
        $normalizedUnit = $this->normalizeKey($unitName);

        if ($normalizedUnit === $this->normalizeKey('ទីចាត់ការមន្ទីរសុខាភិបាល') && in_array($positionId, [14, 15], true)) {
            return [
                'department_id' => 53,
                'expected_prefix' => 'LEGACY-WP-1-2-19-2-1',
            ];
        }

        return $target;
    }

    private function resolvePositionId(string $positionName): ?int
    {
        $normalized = $this->normalizeKey($positionName);
        foreach (self::POSITION_NAME_TO_ID as $key => $id) {
            if ($this->normalizeKey($key) === $normalized) {
                return $id;
            }
        }

        if ($normalized === '') {
            return null;
        }

        if (Str::contains($normalized, 'អនុប្រធានមន្ទីរសុខាភិបាលខេត្តក្រុង')) {
            return 15;
        }

        if (Str::contains($normalized, 'ប្រធានមន្ទីរសុខាភិបាលខេត្តក្រុង')) {
            return 14;
        }

        if (Str::contains($normalized, 'អនុប្រធានមន្ទីរពេទ្យ')) {
            return 19;
        }

        if (Str::contains($normalized, 'ប្រធានមន្ទីរពេទ្យ')) {
            return 18;
        }

        if (Str::contains($normalized, 'អនុប្រធានមណ្ឌលសុខភាព')) {
            return 21;
        }

        if (Str::contains($normalized, 'ប្រធានមណ្ឌលសុខភាព')) {
            return 20;
        }

        if (Str::contains($normalized, 'អនុប្រធានការិយាល័យសុខាភិបាលស្រុកប្រតិបត្តិ')) {
            return 17;
        }

        if (Str::contains($normalized, 'ប្រធានការិយាល័យសុខាភិបាលស្រុកប្រតិបត្តិ')) {
            return 16;
        }

        if (Str::contains($normalized, 'អនុប្រធានការិយាល័យ')) {
            return 39;
        }

        if (Str::contains($normalized, 'ប្រធានការិយាល័យ')) {
            return 38;
        }

        if (Str::contains($normalized, 'អនុប្រធានផ្នែក')) {
            return 28;
        }

        if (Str::contains($normalized, 'ប្រធានផ្នែក')) {
            return 27;
        }

        if (Str::contains($normalized, 'អនុប្រធានសាល')) {
            return 26;
        }

        if (Str::contains($normalized, 'ប្រធានសាល')) {
            return 25;
        }

        if (Str::contains($normalized, ['មន្ត្រី', 'មន្រ្តី', 'បុគ្គលិក', 'ទំនេរគ្មានបៀវត្ស'])) {
            return 22;
        }

        $position = Position::query()
            ->where(function ($query) use ($positionName) {
                $query->where('position_name_km', $positionName)
                    ->orWhere('position_name', $positionName);
            })
            ->first();

        return $position ? (int) $position->id : null;
    }

    private function resolveGenderId(string $gender): ?int
    {
        $normalized = $this->normalizeKey($gender);
        return match ($normalized) {
            'ប្រុស', 'male', 'm' => 1,
            'ស្រី', 'female', 'f' => 2,
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function detectChanges(Employee $employee, array $attributes): array
    {
        $changes = [];
        foreach ($attributes as $key => $value) {
            $old = $employee->getAttribute($key);
            $oldComparable = $old instanceof Carbon ? $old->toDateString() : ($old ?? null);
            $newComparable = $value instanceof Carbon ? $value->toDateString() : ($value ?? null);

            if ((string) $oldComparable !== (string) $newComparable) {
                $changes[$key] = [
                    'old' => $oldComparable,
                    'new' => $newComparable,
                ];
            }
        }

        return $changes;
    }

    private function matchSummaryKey(string $matchType): string
    {
        return match ($matchType) {
            'exact_matches' => 'exact_matches',
            'khmer_name_matches' => 'khmer_name_matches',
            'latin_name_matches' => 'latin_name_matches',
            'fuzzy_latin_matches' => 'fuzzy_latin_matches',
            default => 'exact_matches',
        };
    }

    private function nextEmployeeCode(): int
    {
        $maxCode = (int) Employee::withTrashed()
            ->selectRaw('MAX(CAST(employee_id AS UNSIGNED)) as max_employee_id')
            ->value('max_employee_id');

        return $maxCode > 0 ? $maxCode + 1 : 1;
    }

    /**
     * @param array<int, array<string, mixed>> $sourceRows
     * @param Collection<int, Employee> $currentEmployees
     */
    private function writeBackup(
        string $file,
        string $sheetName,
        bool $dryRun,
        array $sourceRows,
        Collection $currentEmployees
    ): string {
        $backupDir = storage_path('app/staff_sync_backups');
        if (!File::isDirectory($backupDir)) {
            File::makeDirectory($backupDir, 0777, true);
        }

        $timestamp = now()->format('Ymd_His');
        $backupPath = $backupDir . DIRECTORY_SEPARATOR . 'staff_sync_' . $timestamp . ($dryRun ? '_dry_run' : '') . '.json';

        $payload = [
            'source_file' => $file,
            'sheet' => $sheetName,
            'dry_run' => $dryRun,
            'exported_at' => now()->toDateTimeString(),
            'source_rows' => $sourceRows,
            'current_rows' => $currentEmployees->map(function (Employee $employee) {
                return [
                    'id' => $employee->id,
                    'employee_id' => $employee->employee_id,
                    'official_id_10' => $employee->official_id_10,
                    'full_name' => $employee->full_name,
                    'full_name_latin' => $employee->full_name_latin,
                    'department_id' => $employee->department_id,
                    'position_id' => $employee->position_id,
                    'deleted_at' => optional($employee->deleted_at)->toDateTimeString(),
                ];
            })->values()->all(),
        ];

        file_put_contents(
            $backupPath,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        return $backupPath;
    }

    private function parseExcelDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            if ((float) $value <= 0) {
                return null;
            }
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        $text = $this->cleanText((string) $value);
        if ($text === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $text) === 1) {
            return substr($text, 0, 10);
        }

        foreach (['d/m/Y', 'j/n/Y', 'm/d/Y', 'n/j/Y'] as $format) {
            try {
                $date = \DateTime::createFromFormat($format, $text);
                if ($date instanceof \DateTimeInterface) {
                    return $date->format('Y-m-d');
                }
            } catch (Throwable) {
                // Keep trying the next format.
            }
        }

        try {
            return Carbon::parse($text)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $name): array
    {
        $clean = trim(preg_replace('/\s+/u', ' ', $this->cleanText($name)) ?? '');
        if ($clean === '') {
            return ['', ''];
        }

        $parts = preg_split('/\s+/u', $clean) ?: [];
        $lastName = array_shift($parts) ?: '';
        $firstName = implode(' ', $parts);

        return [$lastName, $firstName];
    }

    private function normalizeOfficialId(string $value): string
    {
        $clean = $this->cleanText($value);
        return preg_replace('/[^\d]/u', '', $clean) ?? '';
    }

    private function normalizeName(string $value): string
    {
        return $this->normalizeKey($value);
    }

    private function normalizeLatin(string $value): string
    {
        $normalized = $this->normalizeKey($value);
        return mb_strtoupper($normalized, 'UTF-8');
    }

    private function normalizeKey(string $value): string
    {
        $clean = $this->cleanText($value);
        $clean = str_replace(['-', '.', ',', '/', '(', ')'], '', $clean);
        $clean = preg_replace('/\s+/u', '', $clean) ?? '';
        $clean = strtr($clean, [
            '០' => '0',
            '១' => '1',
            '២' => '2',
            '៣' => '3',
            '៤' => '4',
            '៥' => '5',
            '៦' => '6',
            '៧' => '7',
            '៨' => '8',
            '៩' => '9',
        ]);

        return mb_strtolower($clean, 'UTF-8');
    }

    private function cleanText(string $value): string
    {
        $value = str_replace(["\u{200B}", "\u{200C}", "\u{200D}", "\u{FEFF}"], '', $value);
        $value = trim($value);
        return preg_replace('/\s+/u', ' ', $value) ?? '';
    }
}
