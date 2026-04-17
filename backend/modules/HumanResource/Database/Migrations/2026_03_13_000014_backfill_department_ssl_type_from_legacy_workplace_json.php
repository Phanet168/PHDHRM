<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('departments') || !Schema::hasTable('gov_salary_scales')) {
            return;
        }

        if (!Schema::hasColumn('departments', 'ssl_type_id')) {
            return;
        }

        $sslTypeRows = $this->loadJsonRows([
            base_path('storage/app/legacy_import/unicode/ssl_type.json'),
            base_path('storage/app/legacy_import/ssl_type.json'),
        ], 'sslID');

        if (empty($sslTypeRows)) {
            return;
        }

        $scaleIdByLegacySslId = [];

        foreach ($sslTypeRows as $row) {
            $legacyId = (int) ($row['sslID'] ?? -1);
            $nameEn = trim((string) ($row['sslNameE'] ?? ''));
            $nameKm = trim((string) ($row['sslNameK'] ?? ''));

            if ($legacyId < 0 || ($nameEn === '' && $nameKm === '')) {
                continue;
            }

            $query = DB::table('gov_salary_scales');
            if ($nameEn !== '') {
                $query->where('name_en', $nameEn);
                if ($nameKm !== '') {
                    $query->orWhere('name_km', $nameKm);
                }
            } else {
                $query->where('name_km', $nameKm);
            }

            $scaleId = (int) ($query->value('id') ?? 0);
            if ($scaleId > 0) {
                $scaleIdByLegacySslId[$legacyId] = $scaleId;
            }
        }

        if (empty($scaleIdByLegacySslId)) {
            return;
        }

        $workplaceRows = $this->loadJsonRows([
            base_path('storage/app/legacy_import/unicode/workplace_old.json'),
            base_path('storage/app/legacy_import/workplace_old.json'),
        ], 'WorkPlaceID');

        if (empty($workplaceRows)) {
            return;
        }

        foreach ($workplaceRows as $row) {
            $legacyWorkPlaceId = trim((string) ($row['WorkPlaceID'] ?? ''));
            if ($legacyWorkPlaceId === '') {
                continue;
            }

            $legacySslId = (int) ($row['sslID'] ?? -1);
            if (!array_key_exists($legacySslId, $scaleIdByLegacySslId)) {
                continue;
            }

            $locationCode = 'LEGACY-WP-' . str_replace('|', '-', $legacyWorkPlaceId);

            DB::table('departments')
                ->where('location_code', $locationCode)
                ->update([
                    'ssl_type_id' => $scaleIdByLegacySslId[$legacySslId],
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: Keep imported SSL mappings.
    }

    protected function loadJsonRows(array $candidatePaths, string $keyForSingleRow): array
    {
        foreach ($candidatePaths as $path) {
            if (!File::exists($path)) {
                continue;
            }

            $json = File::get($path);
            $json = preg_replace('/^\xEF\xBB\xBF/', '', (string) $json);
            $rows = json_decode((string) $json, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($rows)) {
                continue;
            }

            if (isset($rows[$keyForSingleRow])) {
                return [$rows];
            }

            return $rows;
        }

        return [];
    }
};

