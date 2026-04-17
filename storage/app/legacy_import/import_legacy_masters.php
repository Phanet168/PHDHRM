<?php
require __DIR__ . '/../../../vendor/autoload.php';
$app = require __DIR__ . '/../../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Modules\HumanResource\Entities\GovPayLevel;
use Modules\HumanResource\Entities\GovSalaryScale;
use Modules\HumanResource\Entities\GovSalaryScaleValue;
use Modules\HumanResource\Entities\Position;
use Modules\HumanResource\Entities\ProfessionalSkill;

function loadRows(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $json = file_get_contents($path);
    if ($json === false || $json === '') {
        return [];
    }

    // Support UTF-8 BOM exported by legacy tools.
    $json = preg_replace('/^\xEF\xBB\xBF/', '', $json) ?? $json;
    $data = json_decode($json, true);
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        return [];
    }

    if (array_keys($data) !== range(0, count($data) - 1)) {
        return [$data];
    }

    return $data;
}

function loadRowsFromCandidates(array $paths): array
{
    foreach ($paths as $path) {
        $rows = loadRows($path);
        if (!empty($rows)) {
            return $rows;
        }
    }

    return [];
}

function boolFromLegacy($value): bool
{
    if (is_bool($value)) {
        return $value;
    }

    if (is_numeric($value)) {
        return (float) $value != 0.0;
    }

    $text = strtolower(trim((string) $value));
    return in_array($text, ['true', 'yes', 'y', '1', '-1'], true);
}

$base = __DIR__;
$unicodeBase = $base . '/unicode';
$skillRows = loadRowsFromCandidates([$unicodeBase . '/skill.json', $base . '/skill.json']);
$positionRows = loadRowsFromCandidates([$unicodeBase . '/position.json', $base . '/position.json']);
$payLevelRows = loadRowsFromCandidates([$unicodeBase . '/pay_level.json', $base . '/pay_level.json']);
$scaleRows = loadRowsFromCandidates([$unicodeBase . '/ssl_type.json', $base . '/ssl_type.json']);
$valueRows = loadRowsFromCandidates([$unicodeBase . '/ssl_values.json', $base . '/ssl_values.json']);

$skillMap = [];
$scaleMap = [];

DB::beginTransaction();

try {
    foreach ($skillRows as $row) {
        $legacyId = (int) ($row['SkillID'] ?? 0);
        $nameEn = trim((string) ($row['SkillE'] ?? ''));

        if ($legacyId <= 0 || $nameEn === '') {
            continue;
        }

        $code = 'SK-' . $legacyId;

        $skill = ProfessionalSkill::withTrashed()->firstOrNew(['code' => $code]);
        $skill->name_en = $nameEn;
        $skill->name_km = trim((string) ($row['SkillK'] ?? '')) ?: null;
        $skill->shortcut_en = trim((string) ($row['ShortCutE'] ?? '')) ?: null;
        $skill->shortcut_km = trim((string) ($row['ShortCutK'] ?? '')) ?: null;
        $skill->retire_age = is_numeric($row['RetireAge'] ?? null) ? (int) $row['RetireAge'] : null;
        $skill->is_active = true;
        $skill->save();

        if (method_exists($skill, 'trashed') && $skill->trashed()) {
            $skill->restore();
        }

        $skillMap[$legacyId] = $skill->id;
    }

    foreach ($positionRows as $row) {
        $nameEn = trim((string) ($row['PositionE'] ?? ''));
        if ($nameEn === '') {
            continue;
        }

        $position = Position::withTrashed()->firstOrNew(['position_name' => $nameEn]);
        $legacyRange = $row['PositionRange'] ?? ($row['Range'] ?? null);
        $position->position_name_km = trim((string) ($row['PositionK'] ?? '')) ?: null;
        $position->position_details = $position->position_details ?: 'Legacy import';
        $position->position_rank = is_numeric($legacyRange) ? (int) $legacyRange : null;
        $position->is_prov_level = boolFromLegacy($row['isProvLevel'] ?? true);
        $position->is_active = true;
        $position->save();

        if (method_exists($position, 'trashed') && $position->trashed()) {
            $position->restore();
        }
    }

    foreach ($payLevelRows as $row) {
        $levelCode = trim((string) ($row['PayLevelE'] ?? ''));
        if ($levelCode === '') {
            continue;
        }

        $levelNameKm = trim((string) ($row['PayLevelK'] ?? '')) ?: null;
        if ($levelNameKm !== null) {
            $levelNameKm = preg_replace('/\.\.+/u', '.', $levelNameKm) ?: $levelNameKm;
        }

        $payLevel = GovPayLevel::withTrashed()->firstOrNew(['level_code' => $levelCode]);
        $payLevel->level_name_km = $levelNameKm;
        $payLevel->sort_order = is_numeric($row['PayLevelID'] ?? null) ? (int) $row['PayLevelID'] : 0;
        $payLevel->is_active = true;
        $payLevel->save();

        if (method_exists($payLevel, 'trashed') && $payLevel->trashed()) {
            $payLevel->restore();
        }
    }

    foreach ($scaleRows as $row) {
        $legacyId = (int) ($row['sslID'] ?? 0);
        $nameEn = trim((string) ($row['sslNameE'] ?? ''));

        if ($legacyId < 0 || $nameEn === '') {
            continue;
        }

        $scale = GovSalaryScale::withTrashed()->firstOrNew(['name_en' => $nameEn]);
        $scale->name_km = trim((string) ($row['sslNameK'] ?? '')) ?: null;
        $scale->is_active = true;
        $scale->save();

        if (method_exists($scale, 'trashed') && $scale->trashed()) {
            $scale->restore();
        }

        $scaleMap[$legacyId] = $scale->id;
    }

    foreach ($valueRows as $row) {
        $legacyScaleId = (int) ($row['sslID'] ?? -1);
        $legacySkillId = (int) ($row['skillID'] ?? -1);

        if (!isset($scaleMap[$legacyScaleId], $skillMap[$legacySkillId])) {
            continue;
        }

        if (!is_numeric($row['value'] ?? null)) {
            continue;
        }

        GovSalaryScaleValue::updateOrCreate(
            [
                'gov_salary_scale_id' => $scaleMap[$legacyScaleId],
                'professional_skill_id' => $skillMap[$legacySkillId],
            ],
            [
                'value' => (float) $row['value'],
            ]
        );
    }

    DB::commit();

    echo 'Legacy master data import completed.' . PHP_EOL;
    echo 'Skills: ' . ProfessionalSkill::count() . PHP_EOL;
    echo 'Positions: ' . Position::count() . PHP_EOL;
    echo 'Pay Levels: ' . GovPayLevel::count() . PHP_EOL;
    echo 'Salary Scales: ' . GovSalaryScale::count() . PHP_EOL;
    echo 'Scale Values: ' . GovSalaryScaleValue::count() . PHP_EOL;
} catch (Throwable $e) {
    DB::rollBack();
    fwrite(STDERR, 'Import failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

