<?php

namespace Modules\Report\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Report\Entities\ReportAction;
use Modules\Report\Entities\ReportDepartment;
use Modules\Report\Entities\ReportImportMapping;
use Modules\Report\Entities\ReportIndicator;
use Modules\Report\Entities\ReportPeriod;
use Modules\Report\Entities\ReportUnit;
use Modules\Report\Entities\ReportValue;

/**
 * Imports PHDReport (Firestore) data. Accepts either the plain indicator list (indicator.json) or an
 * object of collections: departments, units, indicators, periods, reports, actions, import_mappings —
 * using the Firestore field names (department_id, unit_name, indicator_id, period_id, …).
 * Everything is upserted by its PHDReport id, so re-running is safe.
 */
class MasterDataImporter
{
    private array $counts = [];
    private array $departments = [];
    private array $units = [];
    private array $indicators = [];

    public static function decode(string $json): array
    {
        $data = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', $json), true);
        if (!is_array($data)) {
            throw new InvalidArgumentException('JSON មិនត្រឹមត្រូវ');
        }

        return array_is_list($data) ? ['indicators' => $data] : $data;
    }

    /** @return array<string,int> number of upserted rows per collection */
    public function import(array $data): array
    {
        $this->counts = [];
        DB::transaction(function () use ($data) {
            foreach ($this->rows($data, 'departments') as $r) {
                $this->department((string) ($r['department_id'] ?? $r['id'] ?? ''), $r['department_name'] ?? null, $r);
            }
            foreach ($this->rows($data, 'units') as $r) {
                $this->unit((string) ($r['unit_id'] ?? $r['id'] ?? ''), (string) ($r['department_id'] ?? ''), $r['unit_name'] ?? null, $r);
            }
            foreach ($this->rows($data, 'indicators') as $r) {
                $this->indicator($r);
            }
            foreach ($this->rows($data, 'periods') as $r) {
                $this->period($r);
            }
            foreach ($this->rows($data, 'import_mappings') as $r) {
                $this->mapping($r);
            }
            // The same month can exist as "2025-01" and "2025-M01": oldest first so the newest value wins.
            $reports = $this->rows($data, 'reports');
            usort($reports, fn ($a, $b) => strcmp((string) ($a['updated_at'] ?? ''), (string) ($b['updated_at'] ?? '')));
            foreach ($reports as $r) {
                $this->value($r);
            }
            foreach ($this->rows($data, 'actions') as $r) {
                $this->action($r);
            }
        });

        return $this->counts;
    }

    private function rows(array $data, string $key): array
    {
        $rows = $data[$key] ?? [];

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    private function bump(string $key): void
    {
        $this->counts[$key] = ($this->counts[$key] ?? 0) + 1;
    }

    private function department(string $code, ?string $name, ?array $row = null): ?ReportDepartment
    {
        if ($code === '') {
            return null;
        }
        if (isset($this->departments[$code]) && $row === null) {
            return $this->departments[$code];
        }
        $dept = $this->locate(ReportDepartment::class, $code);
        if ($name || !$dept->exists) {
            $dept->name = $name ?: ($dept->name ?: "ជំពូក {$code}");
        }
        // Numbered departments first, then ones with generated ids, the "unassigned" bucket (0) last.
        $dept->sort_order = $code === '0' ? 9999 : (is_numeric($code) ? (int) $code : 1000);
        $dept->save();
        if ($row !== null) {
            $this->bump('departments');
        }

        return $this->departments[$code] = $dept;
    }

    private function unit(string $code, string $deptCode, ?string $name, ?array $row = null): ?ReportUnit
    {
        if ($code === '') {
            return null;
        }
        if (isset($this->units[$code]) && $row === null) {
            return $this->units[$code];
        }
        $unit = $this->locate(ReportUnit::class, $code);
        $dept = $this->department($deptCode, null) ?? ($unit->department_id ? null : $this->department('0', 'មិនបានកំណត់ជំពូក'));
        if ($dept) {
            $unit->department_id = $dept->id;
        }
        if ($name || !$unit->exists) {
            $unit->name = $name ?: ($unit->name ?: "ផ្នែក {$code}");
        }
        if ($row && array_key_exists('unit_type', $row)) {
            $unit->unit_type = $row['unit_type'];
        }
        $unit->sort_order = is_numeric($code) ? (int) $code : ($unit->sort_order ?? 0);
        $unit->save();
        if ($row !== null) {
            $this->bump('units');
        }

        return $this->units[$code] = $unit;
    }

    private function indicator(array $r): void
    {
        $code = trim((string) ($r['indicator_id'] ?? ''));
        if ($code === '') {
            return;
        }
        $deptCode = (string) ($r['department_id'] ?? '');
        if ($deptCode === '' && empty($r['unit_id'])) {
            $this->department('0', 'មិនបានកំណត់ជំពូក');
        }
        // Indicators without a unit go to an "unassigned" unit inside their own department.
        $unit = $this->unit((string) ($r['unit_id'] ?? ''), $deptCode, null)
            ?? $this->unit('none-' . ($deptCode !== '' ? $deptCode : '0'), $deptCode !== '' ? $deptCode : '0', 'មិនបានកំណត់ផ្នែក');
        $indicator = $this->locate(ReportIndicator::class, $code);
        $indicator->fill([
            'name' => trim((string) ($r['indicator_name'] ?? $indicator->name ?? $code)),
            'indicator_type' => isset($r['indicator_type']) ? trim((string) $r['indicator_type']) : $indicator->indicator_type,
            'department_id' => $unit->department_id,
            'unit_id' => $unit->id,
            'active' => !isset($r['active']) || (bool) $r['active'],
        ]);
        $indicator->save(); // order comes from the natural order of the code (sort_order stays 0)
        $this->indicators[$code] = $indicator;
        $this->bump('indicators');
    }

    /** Values and actions reference the PHDReport id, which lives in source_id (or code for older rows). */
    private function findIndicator(mixed $sourceId): ?ReportIndicator
    {
        $sourceId = trim((string) $sourceId);

        return $this->indicators[$sourceId] ??= ReportIndicator::where('source_id', $sourceId)->first()
            ?? ReportIndicator::whereNull('source_id')->where('code', $sourceId)->first();
    }

    /**
     * The record for a PHDReport id: matched on source_id (or a legacy code), otherwise a new record whose
     * code is the PHDReport id when it is a free number, else the next sequential code.
     *
     * @template T of \Illuminate\Database\Eloquent\Model
     * @param  class-string<T>  $class
     * @return T
     */
    private function locate(string $class, string $sourceId)
    {
        $found = $class::where('source_id', $sourceId)->first()
            ?? $class::whereNull('source_id')->where('code', $sourceId)->first();
        if ($found) {
            $found->source_id ??= $sourceId;

            return $found;
        }

        $model = new $class(['source_id' => $sourceId]);
        $taken = $class::where('code', $sourceId)->exists();
        $model->code = CodeGenerator::isGenerated($sourceId) && !$taken ? $sourceId : CodeGenerator::next($model->getTable());

        return $model;
    }

    private function period(array $r): ?ReportPeriod
    {
        $code = (string) ($r['period_id'] ?? '');
        if ($code === '') {
            return null;
        }
        try {
            $period = ReportPeriod::forCode($code);
        } catch (InvalidArgumentException) {
            return null;
        }
        // PHDReport often used the raw id as the name ("2024-01"); keep the Khmer label in that case.
        if (!empty($r['period_name']) && !isset($r['indicator_id']) && trim($r['period_name']) !== trim($code)) {
            $period->update(['name' => $r['period_name']]);
            $this->bump('periods');
        }

        return $period;
    }

    private function mapping(array $r): void
    {
        $indicator = $this->findIndicator($r['indicator_id'] ?? '');
        if (!$indicator) {
            return;
        }
        $fields = ['hc_sheet', 'hc_cell', 'hc_formula', 'hosp_sheet', 'hosp_cell', 'hosp_formula', 'result_formula'];
        $payload = array_map(fn ($f) => ($v = trim((string) ($r[$f] ?? ''))) === '' ? null : $v, array_combine($fields, $fields));
        $payload['active'] = !isset($r['active']) || (bool) $r['active'];
        ReportImportMapping::updateOrCreate(['indicator_id' => $indicator->id], $payload);
        $this->bump('import_mappings');
    }

    private function value(array $r): void
    {
        $indicator = $this->findIndicator($r['indicator_id'] ?? '');
        $period = $this->period(['period_id' => $r['period_id'] ?? '', 'indicator_id' => true]);
        if (!$indicator || !$period) {
            $this->bump('reports_skipped');

            return;
        }
        // Rows arrive oldest first; a newer duplicate overrides each field it actually has.
        $value = ReportValue::firstOrNew(['period_id' => $period->id, 'indicator_id' => $indicator->id]);
        $value->unit_id = $indicator->unit_id;
        $value->source = 'import';
        foreach (['value', 'target', 'value_hc', 'value_hosp'] as $field) {
            $n = MappingFormula::toNumber($r[$field] ?? null);
            if ($field === 'target' && $n == 0) {
                $n = null; // PHDReport stored 0 for "no target"
            }
            if ($n !== null || !$value->exists) {
                $value->{$field} = $n;
            }
        }
        $value->save();
        $this->bump('reports');
    }

    private function action(array $r): void
    {
        $indicator = $this->findIndicator($r['indicator_id'] ?? '');
        $period = $this->period(['period_id' => $r['period_id'] ?? '', 'indicator_id' => true]);
        if (!$indicator || !$period) {
            return;
        }
        $owner = $r['action_owner'] ?? null;
        $status = strtolower((string) ($r['action_status'] ?? 'planned'));
        $status = ['completed' => 'done', 'in-progress' => 'ongoing'][$status] ?? $status;
        ReportAction::updateOrCreate([
            'period_id' => $period->id,
            'indicator_id' => $indicator->id,
            'issue_text' => $r['issue_text'] ?? null,
            'action_text' => $r['action_text'] ?? null,
        ], [
            'action_owner' => is_array($owner) ? implode(', ', $owner) : $owner,
            'action_due' => $this->date($r['action_due'] ?? null),
            'action_status' => array_key_exists($status, ReportAction::STATUSES) ? $status : 'planned',
            'resolution_text' => $r['resolution_text'] ?? null,
            'resolution_date' => $this->date($r['resolution_date'] ?? null),
        ]);
        $this->bump('actions');
    }

    private function date(mixed $raw): ?string
    {
        return is_string($raw) && preg_match('/^\d{4}-\d{2}-\d{2}/', $raw) ? substr($raw, 0, 10) : null;
    }
}
