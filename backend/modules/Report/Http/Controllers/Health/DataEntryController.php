<?php

namespace Modules\Report\Http\Controllers\Health;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Report\Entities\ReportDepartment;
use Modules\Report\Entities\ReportIndicator;
use Modules\Report\Entities\ReportPeriod;
use Modules\Report\Entities\ReportValue;
use Modules\Report\Services\HealthReportData;
use Modules\Report\Services\MappingFormula;
use Modules\Report\Services\PeriodCode;
use Modules\Report\Services\XlsxExport;

class DataEntryController extends HealthController
{
    public function index(Request $request, HealthReportData $data)
    {
        $p = $this->periodFromRequest($request);
        $period = ReportPeriod::where('code', $p['code'])->first();
        $all = $this->scopedRows($request, $data, $period, $p['code']);
        $units = $this->unitSummaries($all);

        // Entry is organised by unit: the start page lists the units (cards), each unit is entered on its own page.
        // "department" keeps the older all-in-one view (by chapter) for managers who check across units.
        $unitId = $request->integer('unit') ?: null;
        if (!$unitId && !$request->has('department')) {
            $isManager = $this->access->isManager($request->user());
            // someone who fills a single unit goes straight to it; everyone else picks from the unit menu
            if ($units->count() === 1 && !$isManager) {
                return redirect()->route('report.health.entry', ['unit' => $units->first()['id'], 'period' => $p['code']]);
            }

            return view('report::health.entry-units', [
                'p' => $p,
                'period' => $period,
                'periodLabel' => $period?->label ?? PeriodCode::label($p['code']),
                'locked' => (bool) $period?->locked,
                'years' => $this->years(),
                'tagCounts' => $this->tagCounts($p['year']),
                'units' => $units,
                'isManager' => $isManager,
            ]);
        }

        if ($unitId) {
            $current = $units->firstWhere('id', $unitId);
            abort_unless($current, 404, 'រកមិនឃើញផ្នែកនេះ ឬអ្នកគ្មានសិទ្ធិមើល');
            $at = $units->search(fn ($u) => $u['id'] === $unitId);
            $rows = $all->where('unit_id', $unitId)->values();

            return view('report::health.data-entry', [
                'p' => $p,
                'period' => $period,
                'periodLabel' => $period?->label ?? PeriodCode::label($p['code']),
                'locked' => (bool) $period?->locked,
                'years' => $this->years(),
                'tagCounts' => $this->tagCounts($p['year']),
                'rows' => $rows,
                'department' => 'all',
                'deptCounts' => collect(),
                'totalAll' => $rows->count(),
                'departments' => collect(),
                'canSave' => $rows->contains('can_edit', true),
                'unit' => $current + ['position' => $at + 1, 'of' => $units->count(), 'prev' => $units->get($at - 1), 'next' => $units->get($at + 1)],
            ]);
        }

        // Large lists open one department at a time (the page stays light); "all" is still selectable.
        $department = $request->input('department');
        if ($department === null && $all->count() > 150) {
            $department = (string) $all->first()['department_id'];
        }
        $rows = $department && $department !== 'all'
            ? $all->where('department_id', (int) $department)->values()
            : $all;

        return view('report::health.data-entry', [
            'p' => $p,
            'period' => $period,
            'periodLabel' => $period?->label ?? PeriodCode::label($p['code']),
            'locked' => (bool) $period?->locked,
            'years' => $this->years(),
            'tagCounts' => $this->tagCounts($p['year']),
            'rows' => $rows,
            'department' => $department ?: 'all',
            'deptCounts' => $all->countBy('department_id'),
            'totalAll' => $all->count(),
            'departments' => ReportDepartment::with('units')->orderBy('sort_order')->orderBy('id')->get(),
            'canSave' => $rows->contains('can_edit', true),
        ]);
    }

    /** Batch upsert of the dirty rows sent by the page's save dock. */
    public function save(Request $request)
    {
        $payload = $request->validate([
            'period' => ['required', 'string', 'max:16'],
            'rows' => ['required', 'array', 'max:2000'],
            'rows.*.indicator_id' => ['required', 'integer'],
            'rows.*.value' => ['nullable'],
            'rows.*.target' => ['nullable'],
        ]);

        $period = $this->period($payload['period']);
        abort_if($period->locked, 423, 'រយៈពេលនេះត្រូវបានចាក់សោ');

        $user = $request->user();
        $indicators = ReportIndicator::whereIn('id', collect($payload['rows'])->pluck('indicator_id'))->get()->keyBy('id');
        $saved = [];
        $denied = 0;

        DB::transaction(function () use ($payload, $period, $user, $indicators, &$saved, &$denied) {
            foreach ($payload['rows'] as $row) {
                $indicator = $indicators->get((int) $row['indicator_id']);
                if (!$indicator || !$this->access->canEditIndicator($user, $indicator)) {
                    $denied++;
                    continue;
                }
                $value = ReportValue::firstOrNew(['period_id' => $period->id, 'indicator_id' => $indicator->id]);
                $value->fill([
                    'unit_id' => $indicator->unit_id,
                    'value' => MappingFormula::toNumber($row['value'] ?? null),
                    'target' => MappingFormula::toNumber($row['target'] ?? null),
                    'source' => 'manual',
                    'updated_by' => $user->id,
                ])->save();

                $saved[] = [
                    'indicator_id' => $indicator->id,
                    'value' => $value->value,
                    'target' => $value->target,
                    'percent' => HealthReportData::percent($value->value, $value->target, $indicator->direction),
                ];
            }
        });

        return response()->json([
            'saved' => $saved,
            'denied' => $denied,
            'message' => 'បានរក្សាទុក ' . PeriodCode::kh(count($saved)) . ' ជួរ' . ($denied ? ' • គ្មានសិទ្ធិ ' . PeriodCode::kh($denied) : ''),
        ]);
    }

    /** Values of the previous period, for "copy previous" (only fills empty cells on the page). */
    public function previous(Request $request)
    {
        $code = PeriodCode::previous($this->periodFromRequest($request)['code']);
        $period = ReportPeriod::where('code', $code)->first();
        $values = $period
            ? ReportValue::where('period_id', $period->id)->get(['indicator_id', 'value', 'target'])
            : collect();

        return response()->json(['period' => PeriodCode::label($code), 'values' => $values]);
    }

    public function export(Request $request, HealthReportData $data)
    {
        $p = $this->periodFromRequest($request);
        $period = ReportPeriod::where('code', $p['code'])->first();
        $label = $period?->label ?? PeriodCode::label($p['code']);
        $rows = $this->filtered($request, $this->scopedRows($request, $data, $period, $p['code']));

        return XlsxExport::download(
            "របាយការណ៍_{$p['code']}.xlsx",
            'របាយការណ៍',
            ['រយៈពេល', 'លេខសម្គាល់', 'សូចនាករ', 'ជំពូក', 'ផ្នែក', 'តម្លៃ', 'គោលដៅ', 'ស្ថានភាព'],
            $rows->map(fn ($r) => [
                $label, $r['code'], $r['name'], $r['department'], $r['unit'], $r['value'], $r['target'],
                $r['value'] === null ? 'មិនទាន់បញ្ចូល' : 'បានបញ្ចូលរួច',
            ])->all(),
            [18, 12, 60, 28, 28, 14, 14, 16]
        );
    }

    public function print(Request $request, HealthReportData $data)
    {
        $p = $this->periodFromRequest($request);
        $period = ReportPeriod::where('code', $p['code'])->first();

        return view('report::health.print.data-entry', [
            'label' => $period?->label ?? PeriodCode::label($p['code']),
            'rows' => $this->filtered($request, $this->scopedRows($request, $data, $period, $p['code'])),
        ]);
    }

    /**
     * Managers see every indicator; other users see the units assigned to them plus indicators they own
     * (everything read-only when nothing is assigned).
     */
    private function scopedRows(Request $request, HealthReportData $data, ?ReportPeriod $period, ?string $code = null): Collection
    {
        $user = $request->user();
        $editable = $this->access->editableUnitIds($user);
        $assigned = $editable === null ? null : $this->access->assignedUnitIds($user);
        $canEnter = $this->access->canEnter($user) || $editable === null;
        $locked = (bool) $period?->locked;

        return $data->rows($period, null, $code ?? $period?->code)
            ->filter(fn ($r) => !$assigned || in_array($r['unit_id'], $assigned, true) || (int) $r['owner_user_id'] === (int) $user->id)
            ->map(function ($r) use ($editable, $canEnter, $locked, $user) {
                $r['can_edit'] = !$locked && $canEnter && ($editable === null
                    || in_array($r['unit_id'], $editable, true)
                    || (int) $r['owner_user_id'] === (int) $user->id);

                return $r;
            })->values();
    }

    /**
     * One entry per unit the user sees, in display order, with its progress for the period.
     * Done = has a value to report (typed, or from HIS / HRM / months).
     */
    private function unitSummaries(Collection $rows): Collection
    {
        return $rows->groupBy('unit_id')->map(function (Collection $g) {
            $first = $g->first();
            $total = $g->count();
            $done = $g->where('status', '!=', 'missing')->count();

            return [
                'id' => (int) $first['unit_id'],
                'name' => $first['unit'],
                'department_id' => (int) $first['department_id'],
                'department' => $first['department'],
                'total' => $total,
                'done' => $done,
                'typed' => $g->whereNotNull('value')->count(),
                'auto' => $g->where('status', 'computed')->count(),
                'percent' => $total ? (int) floor($done / $total * 100) : 0,
                'editable' => $g->contains('can_edit', true),
                'issues' => $g->sum(fn ($r) => $r['actions']->count()),
                'updated_at' => $g->pluck('updated_at')->filter()->max(),
            ];
        })->values();
    }

    /** Same filters as the page (department, unit, search, only-empty) for exports. */
    private function filtered(Request $request, Collection $rows): Collection
    {
        $q = mb_strtolower(trim((string) $request->input('q')));

        return $rows
            ->when($request->filled('department') && $request->input('department') !== 'all', fn ($c) => $c->where('department_id', (int) $request->input('department')))
            ->when($request->filled('unit'), fn ($c) => $c->where('unit_id', (int) $request->input('unit')))
            ->when($request->boolean('only_empty'), fn ($c) => $c->whereNull('value'))
            ->when($q !== '', fn ($c) => $c->filter(fn ($r) => str_contains(mb_strtolower($r['code'] . ' ' . $r['name'] . ' ' . $r['unit'] . ' ' . $r['department']), $q)))
            ->values();
    }
}
