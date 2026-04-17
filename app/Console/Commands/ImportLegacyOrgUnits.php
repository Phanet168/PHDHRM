<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\GovSalaryScale;
use Modules\HumanResource\Entities\OrgUnitType;
use Symfony\Component\Process\Process;

class ImportLegacyOrgUnits extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hr:import-legacy-org
                            {--file=storage/app/legacy_import/unicode/workplace_old.json : JSON exported from old Access WorkPlace table}
                            {--place-type-file=storage/app/legacy_import/unicode/place_type.json : JSON exported from old Access PlaceType table}
                            {--ssl-type-file=storage/app/legacy_import/unicode/ssl_type.json : JSON exported from old Access SSLType table}
                            {--convert-khmer : Convert imported Khmer Limon text to Unicode after import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import legacy organization units from old WorkPlace JSON and rebuild tree hierarchy';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $fileOption = (string) $this->option('file');
        $filePath = Str::startsWith($fileOption, [DIRECTORY_SEPARATOR, 'C:\\', 'D:\\'])
            ? $fileOption
            : base_path($fileOption);

        if (!is_file($filePath)) {
            $this->error("Legacy file not found: {$filePath}");
            return self::FAILURE;
        }

        $json = file_get_contents($filePath);
        $json = preg_replace('/^\xEF\xBB\xBF/', '', (string) $json);
        $rows = json_decode((string) $json, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($rows)) {
            $this->error('Invalid JSON format in legacy file.');
            return self::FAILURE;
        }

        if (isset($rows['WorkPlaceID'])) {
            $rows = [$rows];
        }

        if (empty($rows)) {
            $this->warn('No rows found in legacy JSON.');
            return self::SUCCESS;
        }

        $placeTypeById = $this->loadPlaceTypes();
        $sslTypeIdByLegacyId = $this->loadSslTypes();

        $unitTypeIdsByCode = OrgUnitType::query()
            ->where('is_active', true)
            ->pluck('id', 'code')
            ->toArray();

        $requiredCodes = [
            'phd',
            'office',
            'bureau',
            'operational_district',
            'district_hospital',
            'provincial_hospital',
            'health_center',
            'health_post',
            'od_section',
        ];

        foreach ($requiredCodes as $requiredCode) {
            if (!isset($unitTypeIdsByCode[$requiredCode])) {
                $this->error("Missing org unit type code: {$requiredCode}");
                return self::FAILURE;
            }
        }

        $childrenCountByParent = [];
        foreach ($rows as $row) {
            $workPlaceId = trim((string) ($row['WorkPlaceID'] ?? ''));
            if ($workPlaceId === '') {
                continue;
            }

            $parentLegacyId = $this->resolveParentLegacyId($workPlaceId);
            if ($parentLegacyId !== null) {
                $childrenCountByParent[$parentLegacyId] = ($childrenCountByParent[$parentLegacyId] ?? 0) + 1;
            }
        }

        $legacyIdSet = [];
        foreach ($rows as $row) {
            $workPlaceId = trim((string) ($row['WorkPlaceID'] ?? ''));
            if ($workPlaceId !== '') {
                $legacyIdSet[$workPlaceId] = true;
            }
        }

        usort($rows, function ($a, $b) {
            $idA = trim((string) ($a['WorkPlaceID'] ?? ''));
            $idB = trim((string) ($b['WorkPlaceID'] ?? ''));

            $depthA = substr_count($idA, '|') + 1;
            $depthB = substr_count($idB, '|') + 1;

            if ($depthA === $depthB) {
                return strcmp($idA, $idB);
            }

            return $depthA <=> $depthB;
        });

        $legacyLocationMap = Department::withoutGlobalScopes()
            ->where('location_code', 'like', 'LEGACY-WP-%')
            ->get()
            ->keyBy('location_code');

        $legacyIdToDepartmentId = [];
        $legacyIdToTypeCode = [];
        $createdCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $warnings = [];
        $mappedTypeStats = [];

        foreach ($rows as $row) {
            $legacyId = trim((string) ($row['WorkPlaceID'] ?? ''));
            if ($legacyId === '') {
                $skippedCount++;
                continue;
            }

            $locationCode = $this->locationCodeFromLegacyId($legacyId);
            $parentLegacyId = $this->resolveParentLegacyId($legacyId);
            if ($parentLegacyId && !isset($legacyIdSet[$parentLegacyId])) {
                $parentLegacyId = null;
            }
            $parentDepartmentId = $parentLegacyId ? ($legacyIdToDepartmentId[$parentLegacyId] ?? null) : null;
            $parentTypeCode = $parentLegacyId ? ($legacyIdToTypeCode[$parentLegacyId] ?? null) : null;
            $hasChildren = ($childrenCountByParent[$legacyId] ?? 0) > 0;

            if ($parentLegacyId && !$parentDepartmentId) {
                $warnings[] = "Skipped {$legacyId}: parent {$parentLegacyId} not found in import order.";
                $skippedCount++;
                continue;
            }

            $placeTypeId = (int) ($row['PlaceTypeID'] ?? 0);
            $nameEn = trim((string) ($row['WorkPlaceE'] ?? ''));
            $nameKmLegacy = trim((string) ($row['WorkPlaceK'] ?? ''));
            $name = $nameKmLegacy !== '' ? $nameKmLegacy : ($nameEn !== '' ? $nameEn : 'Legacy Unit ' . $legacyId);

            $typeCode = $this->resolveTypeCode(
                $placeTypeId,
                $parentTypeCode,
                $nameEn,
                $nameKmLegacy,
                $hasChildren,
                $legacyId
            );
            $mappedTypeStats[$placeTypeId . '->' . $typeCode] = ($mappedTypeStats[$placeTypeId . '->' . $typeCode] ?? 0) + 1;

            if (!isset($unitTypeIdsByCode[$typeCode])) {
                $warnings[] = "Skipped {$legacyId}: unresolved unit type '{$typeCode}'.";
                $skippedCount++;
                continue;
            }

            $department = $legacyLocationMap->get($locationCode);
            if (!$department) {
                $department = new Department();
                $department->uuid = (string) Str::uuid();
                $createdCount++;
            } else {
                $updatedCount++;
                if (preg_match('/[\x{1780}-\x{17FF}]/u', (string) $department->department_name)) {
                    $name = (string) $department->department_name;
                }
            }

            $department->department_name = $name;
            $department->unit_type_id = (int) $unitTypeIdsByCode[$typeCode];
            $department->ssl_type_id = $sslTypeIdByLegacyId[(int) ($row['sslID'] ?? -1)] ?? null;
            $department->parent_id = $parentDepartmentId;
            $department->sort_order = $this->extractSortOrderFromLegacyId($legacyId);
            $department->location_code = $locationCode;
            $department->is_active = true;
            $department->save();

            $legacyLocationMap->put($locationCode, $department);
            $legacyIdToDepartmentId[$legacyId] = (int) $department->id;
            $legacyIdToTypeCode[$legacyId] = $typeCode;
        }

        $this->info('Legacy org import finished.');
        $this->line("Created: {$createdCount}");
        $this->line("Updated: {$updatedCount}");
        $this->line("Skipped: {$skippedCount}");

        if (!empty($warnings)) {
            $this->warn('Warnings:');
            foreach (array_slice($warnings, 0, 20) as $warning) {
                $this->line(" - {$warning}");
            }
            if (count($warnings) > 20) {
                $this->line(' - ...');
            }
        }

        if (!empty($mappedTypeStats)) {
            ksort($mappedTypeStats);
            $this->line('PlaceType mapping summary (PlaceTypeID -> org_type):');
            foreach ($mappedTypeStats as $mapKey => $count) {
                [$placeTypeId, $typeCode] = explode('->', $mapKey, 2);
                $placeTypeLabel = $placeTypeById[(int) $placeTypeId] ?? null;
                if ($placeTypeLabel) {
                    $this->line(" - {$placeTypeId} ({$placeTypeLabel}) -> {$typeCode}: {$count}");
                } else {
                    $this->line(" - {$placeTypeId} -> {$typeCode}: {$count}");
                }
            }
        }

        if ($this->option('convert-khmer')) {
            $this->line('Running Khmer Limon -> Unicode conversion for imported org units...');
            $exitCode = $this->runLegacyKhmerConversion();
            if ($exitCode !== 0) {
                $this->warn('Import completed, but Khmer conversion script returned non-zero exit code.');
            }
        } else {
            $this->line('Tip: run with --convert-khmer to convert imported Khmer Limon text to Unicode.');
        }

        return self::SUCCESS;
    }

    protected function loadPlaceTypes(): array
    {
        $fileOption = (string) $this->option('place-type-file');
        $filePath = Str::startsWith($fileOption, [DIRECTORY_SEPARATOR, 'C:\\', 'D:\\'])
            ? $fileOption
            : base_path($fileOption);

        if (!is_file($filePath)) {
            $this->warn("PlaceType file not found: {$filePath}");
            return [];
        }

        $json = file_get_contents($filePath);
        $json = preg_replace('/^\xEF\xBB\xBF/', '', (string) $json);
        $rows = json_decode((string) $json, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($rows)) {
            $this->warn("Invalid PlaceType JSON: {$filePath}");
            return [];
        }

        if (isset($rows['PlaceTypeID'])) {
            $rows = [$rows];
        }

        $map = [];
        foreach ($rows as $row) {
            $id = (int) ($row['PlaceTypeID'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $nameKm = trim((string) ($row['PlaceTypeK'] ?? ''));
            $nameEn = trim((string) ($row['PlaceTypeE'] ?? ''));
            $map[$id] = $nameKm !== '' ? $nameKm : $nameEn;
        }

        return $map;
    }

    protected function loadSslTypes(): array
    {
        $fileOption = (string) $this->option('ssl-type-file');
        $filePath = Str::startsWith($fileOption, [DIRECTORY_SEPARATOR, 'C:\\', 'D:\\'])
            ? $fileOption
            : base_path($fileOption);

        if (!is_file($filePath)) {
            $this->warn("SSLType file not found: {$filePath}");
            return [];
        }

        $json = file_get_contents($filePath);
        $json = preg_replace('/^\xEF\xBB\xBF/', '', (string) $json);
        $rows = json_decode((string) $json, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($rows)) {
            $this->warn("Invalid SSLType JSON: {$filePath}");
            return [];
        }

        if (isset($rows['sslID'])) {
            $rows = [$rows];
        }

        $map = [];

        foreach ($rows as $row) {
            $legacyId = (int) ($row['sslID'] ?? -1);
            if ($legacyId < 0) {
                continue;
            }

            $nameEn = trim((string) ($row['sslNameE'] ?? ''));
            $nameKm = trim((string) ($row['sslNameK'] ?? ''));

            if ($nameEn === '' && $nameKm === '') {
                continue;
            }

            $scale = GovSalaryScale::withTrashed()
                ->where(function ($query) use ($nameEn, $nameKm) {
                    if ($nameEn !== '') {
                        $query->where('name_en', $nameEn);

                        if ($nameKm !== '') {
                            $query->orWhere('name_km', $nameKm);
                        }

                        return;
                    }

                    $query->where('name_km', $nameKm);
                })
                ->first();

            if (!$scale) {
                $scale = new GovSalaryScale();
            }

            if ($nameEn !== '') {
                $scale->name_en = $nameEn;
            } elseif (empty($scale->name_en)) {
                $scale->name_en = 'SSL-' . $legacyId;
            }

            if ($nameKm !== '') {
                $scale->name_km = $nameKm;
            }

            $scale->is_active = true;
            $scale->save();

            if (method_exists($scale, 'trashed') && $scale->trashed()) {
                $scale->restore();
            }

            $map[$legacyId] = (int) $scale->id;
        }

        return $map;
    }

    protected function locationCodeFromLegacyId(string $legacyId): string
    {
        return 'LEGACY-WP-' . str_replace('|', '-', $legacyId);
    }

    protected function resolveParentLegacyId(string $legacyId): ?string
    {
        $parts = explode('|', $legacyId);
        if (count($parts) <= 1) {
            return null;
        }

        array_pop($parts);
        return implode('|', $parts);
    }

    protected function extractSortOrderFromLegacyId(string $legacyId): ?int
    {
        $parts = explode('|', $legacyId);
        $last = trim((string) end($parts));
        if ($last === '' || !ctype_digit($last)) {
            return null;
        }

        return (int) $last;
    }
    protected function resolveTypeCode(
        int $placeTypeId,
        ?string $parentTypeCode,
        string $nameEn,
        string $nameKmLegacy,
        bool $hasChildren,
        string $legacyId = ''
    ): string {
        $depth = $legacyId !== '' ? (substr_count($legacyId, '|') + 1) : 0;
        $nameEnLower = Str::lower($nameEn);
        $nameKmLower = Str::lower($nameKmLegacy);
        $isLeadership = Str::contains($nameEnLower, ['leadership', 'leader'])
            || Str::contains($nameKmLower, ['ថ្នាក់ដឹកនាំ', 'ដឹកនាំ', 'fÃ±ak']);

        if ($isLeadership) {
            return 'bureau';
        }

        if ($placeTypeId === 21) {
            // Legacy PHD has root node + sub node (e.g., ...|2 = provincial office block).
            return $depth <= 3 ? 'phd' : 'office';
        }

        if ($placeTypeId === 18) {
            return 'office';
        }

        if ($placeTypeId === 22) {
            return 'bureau';
        }

        if ($placeTypeId === 17) {
            if ($depth <= 4 && $parentTypeCode === 'phd') {
                return 'operational_district';
            }

            if (in_array($parentTypeCode, ['operational_district', 'od_section'], true)) {
                return 'od_section';
            }

            return $hasChildren ? 'od_section' : 'operational_district';
        }

        if ($placeTypeId === 16) {
            return 'od_section';
        }

        if ($placeTypeId === 12) {
            if ($parentTypeCode === 'provincial_hospital') {
                return 'bureau';
            }

            return in_array($parentTypeCode, ['operational_district', 'od_section'], true)
                ? 'district_hospital'
                : 'provincial_hospital';
        }

        if (in_array($placeTypeId, [10, 23], true)) {
            // Some legacy rows are container nodes such as "All HC".
            if ($hasChildren || Str::contains($nameEnLower, 'all hc')) {
                return 'od_section';
            }

            return 'health_center';
        }

        if ($placeTypeId === 15) {
            return 'health_post';
        }

        if ($placeTypeId === 13) {
            return 'program';
        }

        if (in_array($placeTypeId, [14, 20], true)) {
            return 'bureau';
        }

        return 'bureau';
    }
    protected function runLegacyKhmerConversion(): int
    {
        $script = base_path('storage/app/legacy_import/convert_limon_mysql.py');
        if (!is_file($script)) {
            $this->warn("Khmer conversion script not found: {$script}");
            return 1;
        }

        $process = new Process([
            'py',
            '-2.7-32',
            $script,
            '--apply',
            '--include-legacy-departments',
        ], base_path());

        $process->setTimeout(600);
        $process->run();

        if ($process->getOutput()) {
            $this->line(trim($process->getOutput()));
        }

        if ($process->getErrorOutput()) {
            $this->warn(trim($process->getErrorOutput()));
        }

        return $process->getExitCode() ?? 1;
    }
}

