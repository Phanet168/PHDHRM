<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\EmployeeLegalRecord;
use Modules\HumanResource\Entities\EmployeeServiceHistory;
use Modules\HumanResource\Entities\EmployeeUnitPosting;
use Modules\HumanResource\Entities\GovPayLevel;
use Modules\HumanResource\Entities\Position;
use Modules\HumanResource\Entities\ProfessionalSkill;

class ImportLegacyStaff extends Command
{
    // Cambodia civil servant context:
    // Active service: serving/appointed/received into institution.
    protected const SERVICE_ACTIVE_STATUS_IDS = [
        1, 5, 8, 23,
        26, 27, 29, 30, 31, 32, 33, 34, 35,
        37, 39, 40, 41,
        43, 44, 45, 46, 47, 48, 49, 50,
    ];

    // Suspended service: still employed but temporarily not in normal service.
    protected const SERVICE_SUSPENDED_STATUS_IDS = [
        2, 4, 7, 18, 19, 24, 25, 36, 38,
    ];

    // Inactive service: separated/left from current institution.
    protected const SERVICE_INACTIVE_STATUS_IDS = [
        0, 3, 6, 9, 10, 11, 12, 13, 14, 15, 16, 17, 20, 21, 22, 28, 42,
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hr:import-legacy-staff
        {--staff=storage/app/legacy_import/unicode/staff.json : Legacy Staff JSON file}
        {--work-history=storage/app/legacy_import/unicode/work_history.json : Legacy WorkHistory JSON file}
        {--status-history=storage/app/legacy_import/unicode/status_history.json : Legacy StatusHistory JSON file}
        {--work-status=storage/app/legacy_import/unicode/work_status.json : Legacy WorkStatus JSON file}
        {--position=storage/app/legacy_import/unicode/position.json : Legacy Position JSON file}
        {--skill=storage/app/legacy_import/unicode/skill.json : Legacy Skill JSON file}
        {--pay-level=storage/app/legacy_import/unicode/pay_level.json : Legacy PayLevel JSON file}
        {--admin-user-id=1 : Existing user id to assign as owner of imported employees}
        {--dry-run : Analyze and simulate import without writing changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import legacy staff, work history, and status history into current employee module';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $adminUserId = (int) $this->option('admin-user-id');

        if ($adminUserId <= 0 || !DB::table('users')->where('id', $adminUserId)->exists()) {
            $this->error("Invalid --admin-user-id: {$adminUserId}");
            return self::FAILURE;
        }

        $staffRows = $this->loadRowsFromOption('staff');
        $workHistoryRows = $this->loadRowsFromOption('work-history');
        $statusHistoryRows = $this->loadRowsFromOption('status-history');
        $workStatusRows = $this->loadRowsFromOption('work-status');
        $positionRows = $this->loadRowsFromOption('position');
        $skillRows = $this->loadRowsFromOption('skill');
        $payLevelRows = $this->loadRowsFromOption('pay-level');

        if ($staffRows === null || $workHistoryRows === null || $statusHistoryRows === null || $workStatusRows === null || $positionRows === null || $skillRows === null || $payLevelRows === null) {
            return self::FAILURE;
        }

        $workHistoryByStaff = [];
        foreach ($workHistoryRows as $row) {
            $staffId = trim((string) ($row['StaffID'] ?? ''));
            if ($staffId === '') {
                continue;
            }

            $workHistoryByStaff[$staffId][] = $row;
        }

        $statusHistoryByStaff = [];
        foreach ($statusHistoryRows as $row) {
            $staffId = trim((string) ($row['StaffID'] ?? ''));
            if ($staffId === '') {
                continue;
            }

            $statusHistoryByStaff[$staffId][] = $row;
        }

        $workStatusById = [];
        foreach ($workStatusRows as $row) {
            $statusId = (int) ($row['WorkStatusID'] ?? 0);
            if ($statusId <= 0) {
                continue;
            }

            $workStatusById[$statusId] = [
                'active' => $this->legacyActiveToBool($row['Active'] ?? null),
                'name_en' => trim((string) ($row['WorkStatusE'] ?? '')),
                'name_km' => trim((string) ($row['WorkStatusK'] ?? '')),
            ];
        }

        $legacyPositionNameById = [];
        foreach ($positionRows as $row) {
            $legacyId = (int) ($row['PositionID'] ?? 0);
            $nameEn = trim((string) ($row['PositionE'] ?? ''));
            if ($legacyId <= 0 || $nameEn === '') {
                continue;
            }

            $legacyPositionNameById[$legacyId] = $nameEn;
        }

        $positionIdByLegacyId = [];
        foreach ($legacyPositionNameById as $legacyId => $nameEn) {
            $position = Position::withoutGlobalScopes()
                ->where('position_name', $nameEn)
                ->first();
            if ($position) {
                $positionIdByLegacyId[$legacyId] = (int) $position->id;
            }
        }

        $skillTextByLegacyId = [];
        foreach ($skillRows as $row) {
            $legacyId = (int) ($row['SkillID'] ?? 0);
            if ($legacyId <= 0) {
                continue;
            }

            $skill = ProfessionalSkill::query()
                ->where('code', 'SK-' . $legacyId)
                ->first();
            if ($skill) {
                $skillTextByLegacyId[$legacyId] = trim((string) ($skill->name_km ?: $skill->name_en));
                continue;
            }

            $nameKm = trim((string) ($row['SkillK'] ?? ''));
            $nameEn = trim((string) ($row['SkillE'] ?? ''));
            $skillTextByLegacyId[$legacyId] = $nameKm !== '' ? $nameKm : $nameEn;
        }

        $payLevelTextByLegacyId = [];
        foreach ($payLevelRows as $row) {
            $legacyId = (int) ($row['PayLevelID'] ?? 0);
            $code = trim((string) ($row['PayLevelE'] ?? ''));
            if ($legacyId <= 0 || $code === '') {
                continue;
            }

            $payLevel = GovPayLevel::query()
                ->where('sort_order', $legacyId)
                ->first();
            $payLevelTextByLegacyId[$legacyId] = $payLevel ? (string) $payLevel->level_code : $code;
        }

        $departmentIdByLocationCode = Department::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('location_code', 'like', 'LEGACY-WP-%')
            ->pluck('id', 'location_code')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        $stats = [
            'staff_total' => count($staffRows),
            'employee_created' => 0,
            'employee_updated' => 0,
            'employee_skipped' => 0,
            'posting_created' => 0,
            'posting_skipped' => 0,
            'inactive_marked' => 0,
            'legal_record_synced' => 0,
            'status_history_synced' => 0,
        ];
        $warnings = [];

        DB::beginTransaction();

        try {
            foreach ($staffRows as $row) {
                $staffId = trim((string) ($row['StaffID'] ?? ''));
                if ($staffId === '') {
                    $stats['employee_skipped']++;
                    continue;
                }

                [$staffNumberText, $staffNumberInt] = $this->extractStaffNumber($staffId);
                if ($staffNumberInt <= 0) {
                    $warnings[] = "Skipped {$staffId}: invalid staff number.";
                    $stats['employee_skipped']++;
                    continue;
                }

                $histories = $workHistoryByStaff[$staffId] ?? [];
                $statusRowsForStaff = $statusHistoryByStaff[$staffId] ?? [];

                $sortedHistories = $this->sortLegacyRowsByDate($histories, 'StartDatex');
                $currentHistory = $this->pickCurrentHistory($sortedHistories);
                $currentStatusRow = $this->pickCurrentStatusRow($statusRowsForStaff);

                $departmentId = null;
                if ($currentHistory) {
                    $departmentId = $this->resolveDepartmentId(
                        trim((string) ($currentHistory['WorkPlaceID'] ?? '')),
                        $departmentIdByLocationCode
                    );
                }

                if (!$departmentId) {
                    $rootWorkplace = $this->extractRootWorkplaceFromStaffId($staffId);
                    $departmentId = $this->resolveDepartmentId($rootWorkplace, $departmentIdByLocationCode);
                }

                $positionId = null;
                if ($currentHistory) {
                    $legacyPositionId = (int) ($currentHistory['PositionID'] ?? 0);
                    $positionId = $positionIdByLegacyId[$legacyPositionId] ?? null;
                }

                $legacySkillId = $currentHistory ? (int) ($currentHistory['SkillID'] ?? 0) : 0;
                $skillText = $skillTextByLegacyId[$legacySkillId] ?? null;

                $legacyPayLevelId = $currentHistory ? (int) ($currentHistory['PayLevelID'] ?? 0) : 0;
                $payLevelText = $payLevelTextByLegacyId[$legacyPayLevelId] ?? null;

                $statusId = $currentStatusRow ? (int) ($currentStatusRow['WorkStatusID'] ?? 0) : 0;
                $statusMeta = $workStatusById[$statusId] ?? null;
                $employmentStatus = $this->classifyLegacyEmploymentStatus($statusId, $statusMeta);
                $isActive = $employmentStatus['is_active'];
                $isLeft = $employmentStatus['is_left'];

                $terminationReason = null;
                if ($isLeft && $statusMeta) {
                    $terminationReason = $this->resolveLegacyStatusLabel($statusMeta);
                }

                $terminationDate = null;
                if ($isLeft && $currentStatusRow) {
                    $terminationDate = $this->legacyDateToSql($currentStatusRow['EndDate'] ?? null)
                        ?: $this->legacyDateToSql($currentStatusRow['StartDate'] ?? null);
                }

                $nameKm = trim((string) ($row['NameK'] ?? ''));
                $nameEn = trim((string) ($row['NameE'] ?? ''));
                [$surnameKm, $givenKm] = $this->splitNameParts($nameKm);
                [$surnameEn, $givenEn] = $this->splitNameParts($nameEn);
                $serviceDate = $this->legacyDateToSql($row['ServiceDate'] ?? null);
                $officialId10 = $this->normalizeOfficialId($row['Code'] ?? null);
                $legacyLegalMeta = $this->extractLegacyLegalMeta($statusRowsForStaff, $workStatusById, $officialId10, $serviceDate);

                if ($surnameKm === '' && $givenKm !== '') {
                    $surnameKm = $givenKm;
                    $givenKm = '';
                }
                if ($surnameKm === '' && $givenKm === '') {
                    $surnameKm = 'Unknown';
                }

                $employee = Employee::query()
                    ->where('employee_device_id', $staffId)
                    ->first();

                $isNew = false;
                if (!$employee) {
                    $employee = new Employee();
                    $employee->uuid = (string) Str::uuid();
                    $employee->user_id = $adminUserId;
                    $employee->employee_id = str_pad($staffNumberText, 6, '0', STR_PAD_LEFT);
                    $employee->employee_code = $staffNumberInt;
                    $employee->employee_device_id = $staffId;
                    $isNew = true;
                }

                $employee->card_no = trim((string) ($row['Code'] ?? '')) ?: null;
                $employee->official_id_10 = $officialId10;
                $employee->last_name = $surnameKm;
                $employee->first_name = $givenKm;
                $employee->middle_name = null;
                $employee->last_name_latin = $surnameEn !== '' ? $surnameEn : null;
                $employee->first_name_latin = $givenEn !== '' ? $givenEn : null;
                $employee->maiden_name = $nameEn !== '' ? $nameEn : null;
                $employee->date_of_birth = $this->legacyDateToSql($row['DOB'] ?? null);
                $employee->joining_date = $serviceDate;
                $employee->hire_date = $serviceDate;
                $employee->service_start_date = $serviceDate;
                $employee->is_full_right_officer = $legacyLegalMeta['is_full_right_officer'] ? 1 : 0;
                $employee->full_right_date = $legacyLegalMeta['full_right_date'];
                $employee->legal_document_type = $legacyLegalMeta['document_type'];
                $employee->legal_document_number = $legacyLegalMeta['document_number'];
                $employee->legal_document_date = $legacyLegalMeta['document_date'];
                $employee->legal_document_subject = $legacyLegalMeta['document_subject'];
                $employee->highest_educational_qualification = trim((string) ($row['EducationLevel'] ?? '')) ?: null;
                $employee->present_address = trim((string) ($row['PBDetail'] ?? '')) ?: null;
                $employee->permanent_address = trim((string) ($row['PADetail'] ?? '')) ?: null;
                $employee->legacy_pob_code = is_numeric($row['POB'] ?? null) ? (int) $row['POB'] : null;
                $employee->legacy_pa_code = is_numeric($row['PA'] ?? null) ? (int) $row['PA'] : null;
                $employee->legacy_other_info = trim((string) ($row['OtherInfo'] ?? '')) ?: null;
                $employee->department_id = $departmentId;
                $employee->sub_department_id = $departmentId;
                $employee->position_id = $positionId;
                $employee->skill_name = $skillText;
                $employee->employee_grade = $payLevelText;
                $employee->gender_id = $this->mapLegacyGenderToId((string) ($row['Sex'] ?? ''));
                $employee->marital_status_id = $this->mapLegacyMaritalToId($row['MaritalStatus'] ?? null);
                $employee->is_active = $isActive;
                $employee->is_left = $isLeft;
                $employee->work_status_id = $statusId > 0 ? $statusId : null;
                $employee->work_status_name = $this->resolveLegacyStatusLabel($statusMeta)
                    ?: ($statusMeta ? trim((string) ($statusMeta['name_en'] ?? '')) : null);
                $employee->service_state = $employmentStatus['service_state'];
                $employee->termination_reason = $terminationReason;
                $employee->termination_date = $terminationDate;
                $employee->updated_by = $adminUserId;

                if ($isNew) {
                    $employee->created_by = $adminUserId;
                }

                $employee->save();

                if ($isNew) {
                    $stats['employee_created']++;
                } else {
                    $stats['employee_updated']++;
                }

                if ($isLeft) {
                    $stats['inactive_marked']++;
                }

                [$createdPostingCount, $skippedPostingCount] = $this->syncLegacyPostings(
                    $employee,
                    $sortedHistories,
                    $departmentIdByLocationCode,
                    $positionIdByLegacyId,
                    $terminationDate,
                    $adminUserId
                );
                $stats['posting_created'] += $createdPostingCount;
                $stats['posting_skipped'] += $skippedPostingCount;
                $stats['legal_record_synced'] += $this->syncLegacyLegalRecord($employee, $legacyLegalMeta, $adminUserId);
                $stats['status_history_synced'] += $this->syncLegacyStatusHistory($employee, $statusRowsForStaff, $workStatusById);
            }

            if ($isDryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Import failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->line('Mode: ' . ($isDryRun ? 'DRY-RUN (rolled back)' : 'APPLY'));
        $this->line('Legacy staff rows: ' . $stats['staff_total']);
        $this->line('Employees created: ' . $stats['employee_created']);
        $this->line('Employees updated: ' . $stats['employee_updated']);
        $this->line('Employees skipped: ' . $stats['employee_skipped']);
        $this->line('Postings created: ' . $stats['posting_created']);
        $this->line('Postings skipped: ' . $stats['posting_skipped']);
        $this->line('Legal records synced: ' . $stats['legal_record_synced']);
        $this->line('Status histories synced: ' . $stats['status_history_synced']);
        $this->line('Marked inactive/left: ' . $stats['inactive_marked']);

        if (!empty($warnings)) {
            $this->warn('Warnings:');
            foreach (array_slice($warnings, 0, 20) as $warning) {
                $this->line(' - ' . $warning);
            }
            if (count($warnings) > 20) {
                $this->line(' - ...');
            }
        }

        return self::SUCCESS;
    }

    protected function extractLegacyLegalMeta(
        array $statusRowsForStaff,
        array $workStatusById,
        ?string $officialId10,
        ?string $serviceDate
    ): array {
        $sortedStatusRows = $this->sortLegacyRowsByDate($statusRowsForStaff, 'StartDate');
        $currentStatusRow = $this->pickCurrentStatusRow($statusRowsForStaff);

        $titularRow = null;
        foreach (array_reverse($sortedStatusRows) as $row) {
            $statusId = (int) ($row['WorkStatusID'] ?? 0);
            $statusMeta = $workStatusById[$statusId] ?? null;
            $statusEn = Str::lower(trim((string) ($statusMeta['name_en'] ?? '')));
            if ($statusEn !== '' && (str_contains($statusEn, 'titular') || str_contains($statusEn, 'full right') || str_contains($statusEn, 'full-right'))) {
                $titularRow = $row;
                break;
            }
        }

        $docRow = null;
        if ($titularRow && trim((string) ($titularRow['Prakah'] ?? '')) !== '') {
            $docRow = $titularRow;
        } elseif ($currentStatusRow && trim((string) ($currentStatusRow['Prakah'] ?? '')) !== '') {
            $docRow = $currentStatusRow;
        } else {
            foreach (array_reverse($sortedStatusRows) as $row) {
                if (trim((string) ($row['Prakah'] ?? '')) !== '') {
                    $docRow = $row;
                    break;
                }
            }
        }

        $documentNumber = $docRow ? trim((string) ($docRow['Prakah'] ?? '')) : null;
        if ($documentNumber === '') {
            $documentNumber = null;
        }

        $documentDate = $docRow
            ? ($this->legacyDateToSql($docRow['PrakahDate'] ?? null) ?: $this->legacyDateToSql($docRow['StartDate'] ?? null))
            : null;

        $statusMetaForDocument = $docRow ? ($workStatusById[(int) ($docRow['WorkStatusID'] ?? 0)] ?? null) : null;
        $documentType = $this->resolveLegacyLegalDocumentType($documentNumber, $statusMetaForDocument);
        $documentSubject = $this->resolveLegacyStatusLabel($statusMetaForDocument);

        if ($documentSubject === null && $documentNumber !== null) {
            $documentSubject = 'ស្តីពីការកំណត់ស្ថានភាពមន្ត្រី';
        }

        $isFullRightOfficer = $officialId10 !== null && $documentNumber !== null;
        $fullRightDate = null;
        if ($isFullRightOfficer) {
            $fullRightDate = $titularRow
                ? ($this->legacyDateToSql($titularRow['StartDate'] ?? null) ?: $this->legacyDateToSql($titularRow['PrakahDate'] ?? null))
                : ($documentDate ?: $serviceDate);
        }

        return [
            'is_full_right_officer' => $isFullRightOfficer,
            'full_right_date' => $fullRightDate,
            'document_type' => $isFullRightOfficer ? $documentType : null,
            'document_number' => $isFullRightOfficer ? $documentNumber : null,
            'document_date' => $isFullRightOfficer ? $documentDate : null,
            'document_subject' => $isFullRightOfficer ? $documentSubject : null,
        ];
    }

    protected function resolveLegacyLegalDocumentType(?string $documentNumber, ?array $statusMeta): ?string
    {
        $documentNumber = trim((string) $documentNumber);
        if ($documentNumber === '') {
            return null;
        }

        $statusEn = Str::lower(trim((string) ($statusMeta['name_en'] ?? '')));
        $statusKm = Str::lower(trim((string) ($statusMeta['name_km'] ?? '')));
        $haystack = Str::lower(trim($documentNumber . ' ' . $statusEn . ' ' . $statusKm));

        if (Str::contains($haystack, ['royal decree', 'ព្រះរាជ'])) {
            return 'royal_decree';
        }

        if (Str::contains($haystack, ['sub decree', 'sub-decree', 'អនុក្រឹត', 'anukret'])) {
            return 'sub_decree';
        }

        if (Str::contains($haystack, ['proclamation', 'prakas', 'ប្រកាស'])) {
            return 'proclamation';
        }

        return 'proclamation';
    }

    protected function resolveLegacyStatusLabel(?array $statusMeta): ?string
    {
        if (!$statusMeta) {
            return null;
        }

        $nameKm = trim((string) ($statusMeta['name_km'] ?? ''));
        if ($nameKm !== '' && $this->hasKhmerUnicode($nameKm)) {
            return $nameKm;
        }

        $nameEn = trim((string) ($statusMeta['name_en'] ?? ''));
        if ($nameEn === '') {
            return null;
        }

        $normalized = Str::lower($nameEn);
        $khmerMap = [
            'currently working' => 'កំពុងបម្រើការងារ',
            'titular' => 'តាំងស៊ប់ក្នុងក្របខណ្ឌ',
            'probation extended' => 'ពន្យារតាំងស៊ប់',
            'retired by age' => 'ចូលនិវត្តន៍តាមអាយុកំណត់',
            'retired by request' => 'ចូលនិវត្តន៍តាមសំណើ',
            'transfer into province from province' => 'ផ្ទេរចូលខេត្តពីខេត្តផ្សេង',
            'transfer into province from central' => 'ផ្ទេរចូលខេត្តពីថ្នាក់កណ្ដាល',
            'transfer into province from another ministry' => 'ផ្ទេរចូលខេត្តពីក្រសួងផ្សេង',
            'transfer into central from province' => 'ផ្ទេរចូលថ្នាក់កណ្ដាលពីខេត្ត',
        ];

        return $khmerMap[$normalized] ?? $nameEn;
    }

    protected function hasKhmerUnicode(string $text): bool
    {
        return preg_match('/[\x{1780}-\x{17FF}]/u', $text) === 1;
    }

    protected function syncLegacyLegalRecord(Employee $employee, array $legacyLegalMeta, int $adminUserId): int
    {
        EmployeeLegalRecord::query()
            ->where('employee_id', $employee->id)
            ->where('note', 'like', 'Legacy StatusHistory%')
            ->delete();

        EmployeeServiceHistory::query()
            ->where('employee_id', $employee->id)
            ->where('reference_type', 'legacy_status_legal')
            ->delete();

        $isFullRight = (bool) ($legacyLegalMeta['is_full_right_officer'] ?? false);
        $documentType = trim((string) ($legacyLegalMeta['document_type'] ?? ''));
        $documentNumber = trim((string) ($legacyLegalMeta['document_number'] ?? ''));
        $documentDate = $legacyLegalMeta['document_date'] ?? null;
        $documentSubject = trim((string) ($legacyLegalMeta['document_subject'] ?? ''));
        $effectiveDate = $legacyLegalMeta['full_right_date'] ?? null;

        if (!$isFullRight || $documentType === '' || $documentNumber === '') {
            return 0;
        }

        $hasManualCurrentRecord = EmployeeLegalRecord::query()
            ->where('employee_id', $employee->id)
            ->where('is_current', true)
            ->where(function ($query) {
                $query->whereNull('note')
                    ->orWhere('note', 'not like', 'Legacy StatusHistory%');
            })
            ->exists();

        if (!$hasManualCurrentRecord) {
            EmployeeLegalRecord::query()
                ->where('employee_id', $employee->id)
                ->update(['is_current' => false]);
        }

        $record = new EmployeeLegalRecord();
        $record->employee_id = $employee->id;
        $record->document_type = $documentType;
        $record->document_number = $documentNumber;
        $record->document_date = $documentDate;
        $record->document_subject = $documentSubject !== '' ? $documentSubject : null;
        $record->effective_date = $effectiveDate;
        $record->is_current = !$hasManualCurrentRecord;
        $record->note = 'Legacy StatusHistory import';
        $record->created_by = $adminUserId;
        $record->updated_by = $adminUserId;
        $record->save();

        $summary = sprintf(
            '%s | លេខ %s | ចុះថ្ងៃទី %s | ស្តីពី %s',
            $this->legacyLegalTypeLabel($documentType),
            $documentNumber,
            $documentDate ?: '-',
            $documentSubject !== '' ? $documentSubject : '-'
        );

        EmployeeServiceHistory::create([
            'employee_id' => $employee->id,
            'event_type' => 'legal_document_update',
            'event_date' => $documentDate ?: ($effectiveDate ?: now()->toDateString()),
            'title' => 'បញ្ចូលលិខិតច្បាប់ពី DB ចាស់',
            'details' => $summary,
            'from_value' => null,
            'to_value' => $summary,
            'reference_type' => 'legacy_status_legal',
            'reference_id' => $record->id,
            'metadata' => [
                'source' => 'legacy_status_history',
                'is_current' => (bool) $record->is_current,
            ],
        ]);

        return 1;
    }

    protected function syncLegacyStatusHistory(Employee $employee, array $statusRowsForStaff, array $workStatusById): int
    {
        EmployeeServiceHistory::query()
            ->where('employee_id', $employee->id)
            ->where('reference_type', 'legacy_status_history')
            ->delete();

        $sortedRows = $this->sortLegacyRowsByDate($statusRowsForStaff, 'StartDate');
        if (empty($sortedRows)) {
            return 0;
        }

        $count = 0;
        $previousLabel = null;
        $seen = [];

        foreach ($sortedRows as $row) {
            $statusId = (int) ($row['WorkStatusID'] ?? 0);
            if ($statusId <= 0) {
                continue;
            }

            $startDate = $this->legacyDateToSql($row['StartDate'] ?? null) ?: ($employee->joining_date ?: now()->toDateString());
            $endDate = $this->legacyDateToSql($row['EndDate'] ?? null);
            $prakah = trim((string) ($row['Prakah'] ?? ''));
            $prakahDate = $this->legacyDateToSql($row['PrakahDate'] ?? null);
            $currentStatus = (bool) ($row['CurrentStatus'] ?? false);

            $dedupeKey = implode('|', [
                $statusId,
                $startDate,
                $endDate ?: '-',
                $prakah !== '' ? $prakah : '-',
                $prakahDate ?: '-',
            ]);
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;

            $statusMeta = $workStatusById[$statusId] ?? null;
            $statusLabel = $this->resolveLegacyStatusLabel($statusMeta) ?: ('Status ' . $statusId);

            $details = "ស្ថានភាព៖ {$statusLabel}";
            if ($endDate) {
                $details .= " | ដល់ថ្ងៃទី {$endDate}";
            }
            if ($prakah !== '') {
                $details .= " | លេខលិខិត {$prakah}";
            }
            if ($prakahDate) {
                $details .= " | ចុះថ្ងៃទី {$prakahDate}";
            }

            EmployeeServiceHistory::create([
                'employee_id' => $employee->id,
                'event_type' => 'status_change',
                'event_date' => $startDate,
                'title' => 'ប្ដូរស្ថានភាពមន្ត្រី (ទិន្នន័យចាស់)',
                'details' => $details,
                'from_value' => $previousLabel,
                'to_value' => $statusLabel,
                'reference_type' => 'legacy_status_history',
                'reference_id' => $statusId,
                'metadata' => [
                    'legacy_status_id' => $statusId,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'current_status' => $currentStatus,
                    'prakah' => $prakah !== '' ? $prakah : null,
                    'prakah_date' => $prakahDate,
                ],
            ]);

            $previousLabel = $statusLabel;
            $count++;
        }

        return $count;
    }

    protected function legacyLegalTypeLabel(string $type): string
    {
        return match ($type) {
            'royal_decree' => 'ព្រះរាជក្រឹត្យ',
            'sub_decree' => 'អនុក្រឹត្យ',
            'proclamation' => 'ប្រកាស',
            default => 'ផ្សេងៗ',
        };
    }

    protected function syncLegacyPostings(
        Employee $employee,
        array $sortedHistories,
        array $departmentIdByLocationCode,
        array $positionIdByLegacyId,
        ?string $terminationDate,
        int $adminUserId
    ): array {
        $created = 0;
        $skipped = 0;

        EmployeeUnitPosting::query()
            ->where('employee_id', $employee->id)
            ->where('note', 'like', 'Legacy WH:%')
            ->delete();
        EmployeeServiceHistory::query()
            ->where('employee_id', $employee->id)
            ->where('reference_type', 'legacy_work_history')
            ->delete();

        $prepared = [];
        $seen = [];

        foreach ($sortedHistories as $historyRow) {
            $workPlaceId = trim((string) ($historyRow['WorkPlaceID'] ?? ''));
            $departmentId = $this->resolveDepartmentId($workPlaceId, $departmentIdByLocationCode);
            if (!$departmentId) {
                $skipped++;
                continue;
            }

            $legacyPositionId = (int) ($historyRow['PositionID'] ?? 0);
            $positionId = $positionIdByLegacyId[$legacyPositionId] ?? null;
            $startDate = $this->legacyDateToSql($historyRow['StartDatex'] ?? null);
            $endDate = $this->legacyDateToSql($historyRow['EndDatex'] ?? null);

            $dedupeKey = implode('|', [
                $departmentId,
                $positionId ?: 0,
                $startDate ?: 'null',
                $endDate ?: 'null',
            ]);
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;

            $prepared[] = [
                'department_id' => $departmentId,
                'position_id' => $positionId,
                'start_date' => $startDate ?: $employee->joining_date,
                'end_date' => $endDate,
                'legacy_workplace_id' => $workPlaceId,
                'legacy_position_id' => $legacyPositionId > 0 ? $legacyPositionId : null,
                'legacy_skill_id' => (int) ($historyRow['SkillID'] ?? 0),
                'legacy_pay_level_id' => (int) ($historyRow['PayLevelID'] ?? 0),
                'legacy_cadre_id' => (int) ($historyRow['CadreID'] ?? 0),
                'legacy_current_job' => $historyRow['CurrentJob'] ?? null,
            ];
        }

        if (empty($prepared) && $employee->department_id) {
            $prepared[] = [
                'department_id' => (int) $employee->department_id,
                'position_id' => $employee->position_id ? (int) $employee->position_id : null,
                'start_date' => $employee->joining_date,
                'end_date' => $terminationDate,
                'legacy_workplace_id' => null,
                'legacy_position_id' => null,
                'legacy_skill_id' => null,
                'legacy_pay_level_id' => null,
                'legacy_cadre_id' => null,
                'legacy_current_job' => null,
            ];
        }

        if (empty($prepared)) {
            return [$created, $skipped];
        }

        usort($prepared, function ($a, $b) {
            return strcmp((string) ($a['start_date'] ?? ''), (string) ($b['start_date'] ?? ''));
        });

        $lastIndex = count($prepared) - 1;
        $previousPostingData = null;
        foreach ($prepared as $index => $postingData) {
            $isPrimary = $index === $lastIndex;
            $endDate = $postingData['end_date'];

            if ($isPrimary && $terminationDate && !$endDate) {
                $endDate = $terminationDate;
            }

            $note = sprintf(
                'Legacy WH: WP=%s, Pos=%s, Skill=%s, PayLevel=%s, Cadre=%s, CurrentJob=%s',
                $postingData['legacy_workplace_id'] ?? '-',
                $postingData['legacy_position_id'] ?? '-',
                $postingData['legacy_skill_id'] ?? '-',
                $postingData['legacy_pay_level_id'] ?? '-',
                $postingData['legacy_cadre_id'] ?? '-',
                $postingData['legacy_current_job'] === null ? '-' : (string) $postingData['legacy_current_job']
            );

            $posting = new EmployeeUnitPosting();
            $posting->employee_id = $employee->id;
            $posting->department_id = $postingData['department_id'];
            $posting->position_id = $postingData['position_id'];
            $posting->start_date = $postingData['start_date'];
            $posting->end_date = $endDate;
            $posting->is_primary = $isPrimary;
            $posting->note = $note;
            $posting->created_by = $adminUserId;
            $posting->updated_by = $adminUserId;
            $posting->save();
            $this->insertLegacyHistoryFromPosting($employee, $postingData, $previousPostingData, (int) $posting->id);

            $created++;
            $previousPostingData = $postingData;
        }

        return [$created, $skipped];
    }

    protected function loadRowsFromOption(string $optionName): ?array
    {
        $fileOption = (string) $this->option($optionName);
        $filePath = $this->resolvePath($fileOption);

        if (!is_file($filePath)) {
            $fallback = $this->resolveLegacyFallbackPath($filePath);
            if ($fallback && is_file($fallback)) {
                $filePath = $fallback;
            }
        }

        if (!is_file($filePath)) {
            $this->error("File not found ({$optionName}): {$filePath}");
            return null;
        }

        $json = file_get_contents($filePath);
        if ($json === false) {
            $this->error("Failed to read file ({$optionName}): {$filePath}");
            return null;
        }

        $json = preg_replace('/^\xEF\xBB\xBF/', '', (string) $json);
        $rows = json_decode((string) $json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error("Invalid JSON ({$optionName}): {$filePath}");
            return null;
        }

        if (!is_array($rows)) {
            $this->error("JSON must be array/object ({$optionName}): {$filePath}");
            return null;
        }

        if (isset($rows['id']) || isset($rows['StaffID']) || isset($rows['WorkPlaceID'])) {
            $rows = [$rows];
        }

        return $rows;
    }

    protected function resolveLegacyFallbackPath(string $path): ?string
    {
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $unicodeSegment = DIRECTORY_SEPARATOR . 'legacy_import' . DIRECTORY_SEPARATOR . 'unicode' . DIRECTORY_SEPARATOR;
        $legacySegment = DIRECTORY_SEPARATOR . 'legacy_import' . DIRECTORY_SEPARATOR;

        if (str_contains($normalized, $unicodeSegment)) {
            return str_replace($unicodeSegment, $legacySegment, $normalized);
        }

        if (str_contains($normalized, $legacySegment)) {
            return str_replace($legacySegment, $legacySegment . 'unicode' . DIRECTORY_SEPARATOR, $normalized);
        }

        return null;
    }

    protected function resolvePath(string $path): string
    {
        if (Str::startsWith($path, [DIRECTORY_SEPARATOR, 'C:\\', 'D:\\'])) {
            return $path;
        }

        return base_path($path);
    }

    protected function mapLegacyGenderToId(string $sex): ?int
    {
        $normalized = strtoupper(trim($sex));
        if ($normalized === 'M') {
            return 1;
        }
        if ($normalized === 'F') {
            return 2;
        }

        return null;
    }

    protected function mapLegacyMaritalToId($legacyValue): ?int
    {
        if (!is_numeric($legacyValue)) {
            return null;
        }

        $legacy = (int) $legacyValue;
        return match ($legacy) {
            1 => 1, // Single
            2 => 2, // Married
            3, 4 => 4, // Widow/Widower => Widowed
            0 => 5, // Unknown => Other
            default => 5,
        };
    }

    protected function legacyActiveToBool($legacyValue): bool
    {
        if ($legacyValue === null || $legacyValue === '') {
            return true;
        }

        if (is_bool($legacyValue)) {
            return $legacyValue;
        }

        if (is_numeric($legacyValue)) {
            return ((int) $legacyValue) !== 0;
        }

        $text = strtolower(trim((string) $legacyValue));
        if (in_array($text, ['false', 'f', 'no', 'n'], true)) {
            return false;
        }

        return true;
    }

    protected function classifyLegacyEmploymentStatus(int $statusId, ?array $statusMeta): array
    {
        if (in_array($statusId, self::SERVICE_ACTIVE_STATUS_IDS, true)) {
            return [
                'is_active' => true,
                'is_left' => false,
                'service_state' => 'active',
                'source' => 'service_active_status_id',
            ];
        }

        if (in_array($statusId, self::SERVICE_SUSPENDED_STATUS_IDS, true)) {
            return [
                'is_active' => true,
                'is_left' => false,
                'service_state' => 'suspended',
                'source' => 'service_suspended_status_id',
            ];
        }

        if (in_array($statusId, self::SERVICE_INACTIVE_STATUS_IDS, true)) {
            return [
                'is_active' => false,
                'is_left' => true,
                'service_state' => 'inactive',
                'source' => 'service_inactive_status_id',
            ];
        }

        $nameEn = Str::lower(trim((string) ($statusMeta['name_en'] ?? '')));
        if ($nameEn !== '') {
            if (Str::contains($nameEn, ['currently working', 'on probation', 'returned to work', 'reinstated', 'new staff', 'contracted'])) {
                return [
                    'is_active' => true,
                    'is_left' => false,
                    'service_state' => 'active',
                    'source' => 'name_en_active_keyword',
                ];
            }

            if (Str::contains($nameEn, ['absent without pay', 'study', 'probation', 'on notice'])) {
                return [
                    'is_active' => true,
                    'is_left' => false,
                    'service_state' => 'suspended',
                    'source' => 'name_en_suspended_keyword',
                ];
            }

            if (Str::contains($nameEn, ['dead', 'retired', 'dismissed', 'removed', 'transfer out', 'disability'])) {
                return [
                    'is_active' => false,
                    'is_left' => true,
                    'service_state' => 'inactive',
                    'source' => 'name_en_inactive_keyword',
                ];
            }
        }

        $legacyActive = $statusMeta ? $this->legacyActiveToBool($statusMeta['active'] ?? null) : true;
        return [
            'is_active' => $legacyActive,
            'is_left' => !$legacyActive,
            'service_state' => $legacyActive ? 'active' : 'inactive',
            'source' => 'legacy_active_flag',
        ];
    }

    protected function legacyDateToSql($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value) && preg_match('/\/Date\((-?\d+)(?:[+-]\d+)?\)\//', $value, $matches)) {
            $milliseconds = (int) $matches[1];
            $seconds = (int) floor($milliseconds / 1000);
            return gmdate('Y-m-d', $seconds);
        }

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        return null;
    }

    protected function sortLegacyRowsByDate(array $rows, string $dateKey): array
    {
        usort($rows, function ($a, $b) use ($dateKey) {
            $dateA = $this->legacyDateToSql($a[$dateKey] ?? null);
            $dateB = $this->legacyDateToSql($b[$dateKey] ?? null);
            $cmp = strcmp((string) $dateA, (string) $dateB);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp(
                trim((string) ($a['WorkPlaceID'] ?? '')),
                trim((string) ($b['WorkPlaceID'] ?? ''))
            );
        });

        return $rows;
    }

    protected function pickCurrentHistory(array $sortedHistories): ?array
    {
        if (empty($sortedHistories)) {
            return null;
        }

        foreach (array_reverse($sortedHistories) as $row) {
            $legacyCurrentJob = $row['CurrentJob'] ?? null;
            if ($legacyCurrentJob === true || $legacyCurrentJob === -1 || $legacyCurrentJob === 1 || $legacyCurrentJob === '1') {
                return $row;
            }
        }

        return $sortedHistories[count($sortedHistories) - 1];
    }

    protected function pickCurrentStatusRow(array $statusRows): ?array
    {
        if (empty($statusRows)) {
            return null;
        }

        $sorted = $this->sortLegacyRowsByDate($statusRows, 'StartDate');
        foreach (array_reverse($sorted) as $row) {
            $legacyCurrent = $row['CurrentStatus'] ?? null;
            if ($legacyCurrent === true || $legacyCurrent === -1 || $legacyCurrent === 1 || $legacyCurrent === '1') {
                return $row;
            }
        }

        return $sorted[count($sorted) - 1];
    }

    protected function resolveDepartmentId(string $legacyWorkPlaceId, array $departmentIdByLocationCode): ?int
    {
        if ($legacyWorkPlaceId === '') {
            return null;
        }

        $locationCode = 'LEGACY-WP-' . str_replace('|', '-', $legacyWorkPlaceId);
        return $departmentIdByLocationCode[$locationCode] ?? null;
    }

    protected function extractRootWorkplaceFromStaffId(string $staffId): string
    {
        $parts = explode('|', $staffId);
        if (count($parts) <= 1) {
            return '';
        }

        array_pop($parts);
        return implode('|', $parts);
    }

    protected function extractStaffNumber(string $staffId): array
    {
        $parts = explode('|', $staffId);
        $lastPart = trim((string) end($parts));
        if ($lastPart === '' || !ctype_digit($lastPart)) {
            return ['', 0];
        }

        return [$lastPart, (int) $lastPart];
    }

    protected function normalizeOfficialId($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $code = trim((string) $value);
        if ($code === '') {
            return null;
        }

        return preg_match('/^\d{10}$/', $code) ? $code : null;
    }

    protected function splitNameParts(?string $fullName): array
    {
        $fullName = trim(preg_replace('/\s+/', ' ', (string) $fullName));
        if ($fullName === '') {
            return ['', ''];
        }

        $parts = explode(' ', $fullName);
        if (count($parts) === 1) {
            return [$parts[0], ''];
        }

        $surname = array_shift($parts);
        $givenName = implode(' ', $parts);

        return [trim($surname), trim($givenName)];
    }

    protected function insertLegacyHistoryFromPosting(
        Employee $employee,
        array $currentPosting,
        ?array $previousPosting,
        int $postingId
    ): void {
        $eventType = 'work_assignment';
        $title = 'Work assignment updated';

        if ($previousPosting === null) {
            $eventType = 'appointment';
            $title = 'Initial assignment (legacy)';
        } else {
            $deptChanged = (int) $previousPosting['department_id'] !== (int) $currentPosting['department_id'];
            $posChanged = (int) ($previousPosting['position_id'] ?? 0) !== (int) ($currentPosting['position_id'] ?? 0);

            if ($deptChanged && $posChanged) {
                $eventType = 'transfer_position_change';
                $title = 'Transferred and position changed (legacy)';
            } elseif ($deptChanged) {
                $eventType = 'transfer';
                $title = 'Transferred workplace (legacy)';
            } elseif ($posChanged) {
                $eventType = 'position_change';
                $title = 'Position changed (legacy)';
            }
        }

        $fromDept = $previousPosting ? $this->labelDepartmentById((int) $previousPosting['department_id']) : null;
        $toDept = $this->labelDepartmentById((int) $currentPosting['department_id']);
        $fromPos = $previousPosting ? $this->labelPositionById($previousPosting['position_id'] ? (int) $previousPosting['position_id'] : null) : null;
        $toPos = $this->labelPositionById($currentPosting['position_id'] ? (int) $currentPosting['position_id'] : null);

        $details = sprintf(
            'Unit: %s%s%s | Position: %s%s%s',
            $fromDept ? "{$fromDept} -> " : '',
            $toDept ?: '-',
            $currentPosting['legacy_workplace_id'] ? " [WP:{$currentPosting['legacy_workplace_id']}]" : '',
            $fromPos ? "{$fromPos} -> " : '',
            $toPos ?: '-',
            $currentPosting['legacy_position_id'] ? " [Pos:{$currentPosting['legacy_position_id']}]" : ''
        );

        EmployeeServiceHistory::create([
            'employee_id' => $employee->id,
            'event_type' => $eventType,
            'event_date' => $currentPosting['start_date'] ?: $employee->joining_date ?: now()->toDateString(),
            'title' => $title,
            'details' => $details,
            'from_value' => $previousPosting ? (($fromDept ?: '-') . ' | ' . ($fromPos ?: '-')) : null,
            'to_value' => ($toDept ?: '-') . ' | ' . ($toPos ?: '-'),
            'reference_type' => 'legacy_work_history',
            'reference_id' => $postingId,
            'metadata' => [
                'legacy_workplace_id' => $currentPosting['legacy_workplace_id'],
                'legacy_position_id' => $currentPosting['legacy_position_id'],
                'legacy_skill_id' => $currentPosting['legacy_skill_id'],
                'legacy_pay_level_id' => $currentPosting['legacy_pay_level_id'],
            ],
        ]);
    }

    protected function labelDepartmentById(?int $departmentId): ?string
    {
        if (!$departmentId) {
            return null;
        }

        static $cache = [];
        if (!array_key_exists($departmentId, $cache)) {
            $cache[$departmentId] = (string) (DB::table('departments')->where('id', $departmentId)->value('department_name') ?? '');
        }

        return $cache[$departmentId] !== '' ? $cache[$departmentId] : null;
    }

    protected function labelPositionById(?int $positionId): ?string
    {
        if (!$positionId) {
            return null;
        }

        static $cache = [];
        if (!array_key_exists($positionId, $cache)) {
            $row = DB::table('positions')
                ->where('id', $positionId)
                ->select(['position_name', 'position_name_km'])
                ->first();

            $cache[$positionId] = $row ? ((string) ($row->position_name_km ?: $row->position_name)) : '';
        }

        return $cache[$positionId] !== '' ? $cache[$positionId] : null;
    }
}
