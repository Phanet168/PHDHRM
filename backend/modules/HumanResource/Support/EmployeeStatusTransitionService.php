<?php

namespace Modules\HumanResource\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\EmployeeStatus;
use Modules\HumanResource\Entities\EmployeeStatusTransition;
use Modules\HumanResource\Entities\EmployeeWorkHistory;

class EmployeeStatusTransitionService
{
    protected const SERVICE_ACTIVE_STATUS_IDS = [
        1, 5, 8, 23,
        26, 27, 29, 30, 31, 32, 33, 34, 35,
        37, 39, 40, 41,
        43, 44, 45, 46, 47, 48, 49, 50,
    ];

    protected const SERVICE_SUSPENDED_STATUS_IDS = [
        2, 4, 7, 18, 19, 24, 25, 36, 38,
    ];

    protected const SERVICE_INACTIVE_STATUS_IDS = [
        0, 3, 6, 9, 10, 11, 12, 13, 14, 15, 16, 17, 20, 21, 22, 28, 42,
    ];

    public function apply(Employee $employee, array $payload): ?EmployeeStatusTransition
    {
        $toStatusName = trim((string) ($payload['to_work_status_name'] ?? $payload['work_status_name'] ?? ''));
        $toStatusName = $this->normalizeKhmerText($toStatusName);

        $transitionType = trim((string) ($payload['transition_type'] ?? 'status_change'));
        if (in_array($transitionType, ['transfer', 'transfer_in', 'transfer_out'], true)) {
            $toStatusName = $this->defaultActiveStatusName();
        }

        if ($toStatusName === '') {
            return null;
        }

        $classification = $this->classifyEmploymentStatusFromName($toStatusName);

        $from = [
            'work_status_name' => trim((string) ($employee->work_status_name ?? '')),
            'service_state' => trim((string) ($employee->service_state ?? 'active')),
            'is_active' => (int) ($employee->is_active ?? 1),
            'is_left' => (int) ($employee->is_left ?? 0),
        ];

        $to = [
            'work_status_name' => $toStatusName,
            'service_state' => $classification['service_state'],
            'is_active' => $classification['is_active'] ? 1 : 0,
            'is_left' => $classification['is_left'] ? 1 : 0,
            'work_status_id' => $classification['work_status_id'],
        ];

        $effectiveDate = $this->normalizeDate($payload['effective_date'] ?? null);
        $documentDate = $this->normalizeDate($payload['document_date'] ?? null);
        $documentReference = $this->strOrNull($payload['document_reference'] ?? null);
        $note = $this->strOrNull($payload['note'] ?? null);
        $transitionSource = $this->strOrNull($payload['transition_source'] ?? 'status_management');
        $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
        $skipWorkHistory = (bool) ($payload['skip_work_history'] ?? false);

        $changed = (
            $from['work_status_name'] !== $to['work_status_name'] ||
            $from['service_state'] !== $to['service_state'] ||
            $from['is_active'] !== $to['is_active'] ||
            $from['is_left'] !== $to['is_left'] ||
            (int) ($employee->work_status_id ?? 0) !== (int) ($to['work_status_id'] ?? 0)
        );

        return DB::transaction(function () use (
            $employee,
            $from,
            $to,
            $effectiveDate,
            $documentDate,
            $documentReference,
            $note,
            $transitionType,
            $transitionSource,
            $metadata,
            $skipWorkHistory,
            $changed
        ) {
            if (!$skipWorkHistory) {
                EmployeeWorkHistory::create([
                    'employee_id' => $employee->id,
                    'work_status_name' => $to['work_status_name'],
                    'start_date' => $effectiveDate,
                    'document_reference' => $documentReference,
                    'document_date' => $documentDate,
                    'note' => $note,
                ]);
            }

            if (!$changed) {
                return null;
            }

            $employee->work_status_name = $to['work_status_name'];
            $employee->work_status_id = $to['work_status_id'] ?: $employee->work_status_id;
            $employee->service_state = $to['service_state'];
            $employee->is_active = $to['is_active'];
            $employee->is_left = $to['is_left'];
            $employee->save();

            $transition = EmployeeStatusTransition::create([
                'employee_id' => $employee->id,
                'transition_type' => $transitionType !== '' ? $transitionType : 'status_change',
                'transition_source' => $transitionSource,
                'from_work_status_name' => $this->strOrNull($from['work_status_name']),
                'to_work_status_name' => $to['work_status_name'],
                'from_service_state' => $this->strOrNull($from['service_state']),
                'to_service_state' => $to['service_state'],
                'from_is_active' => (bool) $from['is_active'],
                'to_is_active' => (bool) $to['is_active'],
                'from_is_left' => (bool) $from['is_left'],
                'to_is_left' => (bool) $to['is_left'],
                'effective_date' => $effectiveDate,
                'document_date' => $documentDate,
                'document_reference' => $documentReference,
                'note' => $note,
                'metadata' => $metadata,
            ]);

            app(EmployeeServiceHistoryService::class)->log(
                (int) $employee->id,
                'status_change',
                'Employee status changed',
                'Updated via employee status management model',
                $effectiveDate,
                $from['work_status_name'] ?: $from['service_state'],
                $to['work_status_name'] ?: $to['service_state'],
                'employee_status_transition',
                (int) $transition->id,
                [
                    'transition_type' => $transitionType,
                    'transition_source' => $transitionSource,
                    'from' => $from,
                    'to' => $to,
                    'metadata' => $metadata,
                ]
            );

            return $transition;
        });
    }

    public function syncFromLatestWorkHistory(Employee $employee, ?string $source = null): ?EmployeeStatusTransition
    {
        $latest = $this->latestWorkHistoryRecord($employee);
        if (!$latest) {
            return null;
        }

        $statusName = trim((string) $this->normalizeKhmerText((string) $latest->work_status_name));
        if ($statusName === '') {
            return null;
        }

        return $this->apply($employee, [
            'to_work_status_name' => $statusName,
            'effective_date' => $latest->start_date ?: $latest->document_date ?: now()->toDateString(),
            'document_date' => $latest->document_date,
            'document_reference' => $latest->document_reference,
            'note' => $latest->note,
            'transition_type' => 'status_sync',
            'transition_source' => $source ?: 'latest_work_history',
            'metadata' => [
                'work_history_id' => (int) $latest->id,
            ],
            'skip_work_history' => true,
        ]);
    }

    protected function latestWorkHistoryRecord(Employee $employee): ?EmployeeWorkHistory
    {
        return EmployeeWorkHistory::query()
            ->where('employee_id', $employee->id)
            ->whereNotNull('work_status_name')
            ->where('work_status_name', '<>', '')
            ->orderByRaw('CASE WHEN start_date IS NULL THEN 1 ELSE 0 END ASC')
            ->orderByDesc('start_date')
            ->orderByRaw('CASE WHEN document_date IS NULL THEN 1 ELSE 0 END ASC')
            ->orderByDesc('document_date')
            ->orderByDesc('id')
            ->first();
    }

    protected function classifyEmploymentStatusFromName(string $status): array
    {
        $statusName = trim((string) $status);

        $masterGroup = $this->masterTransitionGroupByStatusName($statusName);
        if ($masterGroup !== null) {
            return [
                'work_status_id' => null,
                'service_state' => $masterGroup,
                'is_active' => $masterGroup !== 'inactive',
                'is_left' => $masterGroup === 'inactive',
            ];
        }

        $statusKey = $this->normalizeStatusKey($statusName);
        $legacyMeta = $this->legacyWorkStatusMeta();
        $statusId = $statusKey !== '' ? ($legacyMeta['by_key'][$statusKey] ?? null) : null;

        $serviceState = null;

        if ($statusId !== null) {
            $serviceState = $this->serviceStateByLegacyStatusId((int) $statusId);
            if ($serviceState === null) {
                $activeFlag = $legacyMeta['by_id'][(int) $statusId]['active'] ?? null;
                if ($activeFlag === false) {
                    $serviceState = 'inactive';
                }
            }
        }

        if ($serviceState === null) {
            $serviceState = $this->inferServiceStateFromStatusKeywords($statusName);
        }

        return [
            'work_status_id' => $statusId !== null ? (int) $statusId : null,
            'service_state' => $serviceState,
            'is_active' => $serviceState !== 'inactive',
            'is_left' => $serviceState === 'inactive',
        ];
    }

    protected function masterTransitionGroupByStatusName(string $statusName): ?string
    {
        $normalized = $this->normalizeStatusKey($statusName);
        if ($normalized === '') {
            return null;
        }

        if (!Schema::hasTable('employee_statuses')) {
            return null;
        }

        $selectColumns = [];
        foreach (['name_en', 'name_km', 'transition_group'] as $column) {
            if (Schema::hasColumn('employee_statuses', $column)) {
                $selectColumns[] = $column;
            }
        }

        if (empty($selectColumns)) {
            return null;
        }

        $rows = EmployeeStatus::query()->get($selectColumns);

        foreach ($rows as $row) {
            $nameEn = $this->normalizeStatusKey((string) ($row->name_en ?? ''));
            $nameKm = $this->normalizeStatusKey((string) ($row->name_km ?? ''));

            if ($normalized !== $nameEn && $normalized !== $nameKm) {
                continue;
            }

            $group = trim((string) ($row->transition_group ?? ''));
            if (in_array($group, ['active', 'suspended', 'inactive'], true)) {
                return $group;
            }
        }

        return null;
    }

    protected function serviceStateByLegacyStatusId(int $statusId): ?string
    {
        if (in_array($statusId, self::SERVICE_ACTIVE_STATUS_IDS, true)) {
            return 'active';
        }

        if (in_array($statusId, self::SERVICE_SUSPENDED_STATUS_IDS, true)) {
            return 'suspended';
        }

        if (in_array($statusId, self::SERVICE_INACTIVE_STATUS_IDS, true)) {
            return 'inactive';
        }

        return null;
    }

    protected function inferServiceStateFromStatusKeywords(string $status): string
    {
        $value = mb_strtolower(trim((string) $status));

        if ($this->statusContainsAny($value, [
            'returned',
            'reinstated',
            'return to work',
            'ចូលបម្រើការងារវិញ',
            'ចូលវិញ',
            'បំពេញការងារវិញ',
            'បន្តបម្រើការងារ',
        ])) {
            return 'active';
        }

        if ($this->statusContainsAny($value, [
            'retired',
            'retire',
            'deceased',
            'dead',
            'death',
            'dismissed',
            'terminated',
            'removed',
            'transfer out',
            'resigned',
            'inactive',
            'អសកម្ម',
            'ចូលនិវត្តន៍',
            'និវត្តន៍',
            'មរណភាព',
            'ស្លាប់',
            'លាឈប់',
            'ផ្លាស់ចេញ',
            'បញ្ឈប់ពីការងារ',
            'បណ្តេញចេញ',
        ])) {
            return 'inactive';
        }

        if ($this->statusContainsAny($value, [
            'without pay',
            'absent without pay',
            'suspend',
            'suspended',
            'study leave',
            'no salary',
            'ទំនេរគ្មានបៀវត្ស',
            'ទំនេរបៀវត្ស',
            'ផ្អាកការងារ',
            'ផ្អាក',
            'ដាក់ឱ្យនៅក្រៅក្របខ័ណ្ឌ',
        ])) {
            return 'suspended';
        }

        return 'active';
    }

    protected function statusContainsAny(string $value, array $needles): bool
    {
        foreach ($needles as $needle) {
            $needle = mb_strtolower(trim((string) $needle));
            if ($needle === '') {
                continue;
            }

            if (mb_strpos($value, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    protected function normalizeStatusKey(?string $value): string
    {
        $status = mb_strtolower(trim((string) $value));
        if ($status === '') {
            return '';
        }

        $status = preg_replace('/\s+/u', ' ', $status) ?? $status;
        $status = preg_replace('/[\s\p{P}\p{S}_-]+/u', '', $status) ?? $status;
        return trim($status);
    }

    protected function legacyWorkStatusMeta(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        $cache = [
            'by_id' => [],
            'by_key' => [],
        ];

        $path = storage_path('app/legacy_import/unicode/work_status.json');
        if (!is_file($path)) {
            return $cache;
        }

        $json = @file_get_contents($path);
        if (!is_string($json) || trim($json) === '') {
            return $cache;
        }

        $rows = json_decode($json, true);
        if (!is_array($rows)) {
            return $cache;
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $statusId = (int) ($row['WorkStatusID'] ?? 0);
            if ($statusId <= 0) {
                continue;
            }

            $nameEn = trim((string) ($row['WorkStatusE'] ?? ''));
            $nameKm = trim((string) $this->normalizeKhmerText((string) ($row['WorkStatusK'] ?? '')));
            $active = $this->legacyBoolValue($row['Active'] ?? null);

            $cache['by_id'][$statusId] = [
                'name_en' => $nameEn,
                'name_km' => $nameKm,
                'active' => $active,
            ];

            foreach ([$nameEn, $nameKm] as $candidateName) {
                $key = $this->normalizeStatusKey($candidateName);
                if ($key !== '') {
                    $cache['by_key'][$key] = $statusId;
                }
            }
        }

        return $cache;
    }

    protected function legacyBoolValue($value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return ((int) $value) !== 0;
        }

        $text = strtolower(trim((string) $value));
        if (in_array($text, ['1', 'true', 'yes', 'y'], true)) {
            return true;
        }

        if (in_array($text, ['0', 'false', 'no', 'n'], true)) {
            return false;
        }

        return null;
    }

    protected function normalizeKhmerText(?string $text): string
    {
        $value = trim((string) $text);
        if ($value === '') {
            return '';
        }

        if (preg_match('/\p{Khmer}/u', $value)) {
            return $value;
        }

        if (!str_contains($value, 'Ãƒ')) {
            return $value;
        }

        $decoded = @utf8_encode($value);
        if (is_string($decoded) && $decoded !== '' && preg_match('/\p{Khmer}/u', $decoded)) {
            return trim($decoded);
        }

        $iconv = @iconv('Windows-1252', 'UTF-8//IGNORE', $value);
        if (is_string($iconv) && $iconv !== '' && preg_match('/\p{Khmer}/u', $iconv)) {
            return trim($iconv);
        }

        return $value;
    }

    protected function normalizeDate($value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return now()->toDateString();
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable $th) {
            return now()->toDateString();
        }
    }

    protected function strOrNull($value): ?string
    {
        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $trimmed;
    }

    protected function defaultActiveStatusName(): string
    {
        if (!Schema::hasTable('employee_statuses')) {
            return 'កំពុងបម្រើការងារ';
        }

        $query = EmployeeStatus::query();

        if (Schema::hasColumn('employee_statuses', 'is_active')) {
            $query->where('is_active', true);
        }

        if (Schema::hasColumn('employee_statuses', 'transition_group')) {
            $query->where('transition_group', 'active');
        }

        if (Schema::hasColumn('employee_statuses', 'code')) {
            $query->orderByRaw("CASE WHEN LOWER(COALESCE(code, '')) IN ('active','in_service','working') THEN 0 ELSE 1 END ASC");
        }

        if (Schema::hasColumn('employee_statuses', 'name_km')) {
            $query->orderByRaw("CASE WHEN COALESCE(name_km, '') LIKE '%កំពុងបម្រើការងារ%' THEN 0 ELSE 1 END ASC");
        }

        if (Schema::hasColumn('employee_statuses', 'sort_order')) {
            $query->orderBy('sort_order');
        }

        $status = $query->orderBy('id')->first();

        if (!$status) {
            return 'កំពុងបម្រើការងារ';
        }

        $nameKm = trim((string) ($status->name_km ?? ''));
        $nameEn = trim((string) ($status->name_en ?? ''));

        return $nameKm !== '' ? $nameKm : ($nameEn !== '' ? $nameEn : 'កំពុងបម្រើការងារ');
    }
}
