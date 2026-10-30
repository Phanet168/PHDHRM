<?php

namespace Modules\Report\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Report\Entities\ReportAction;
use Modules\Report\Entities\ReportIndicator;
use Modules\Report\Entities\ReportPeriod;
use Modules\Report\Entities\ReportValue;
use Modules\Report\Services\His\HisAnalysis;
use Modules\Report\Services\Hr\HrStatistics;

/** Read-side queries shared by data entry, summary report, exports and the dashboard. */
class HealthReportData
{
    /**
     * One row per active indicator for the period, ordered department → unit → indicator.
     *
     * For quarter / semester / nine-month / year periods, indicators with an aggregation method and no
     * entered value get a value calculated from their months ('computed' => true).
     *
     * @param  int[]|null  $unitIds  limit to these units (null = all)
     * @param  string|null  $code  period code, needed for calculated values when the period row doesn't exist yet
     */
    public function rows(?ReportPeriod $period, ?array $unitIds = null, ?string $code = null): Collection
    {
        $period ??= $code ? ReportPeriod::where('code', PeriodCode::parse($code)['code'])->first() : null;

        $indicators = ReportIndicator::query()
            ->select('report_indicators.*')
            ->join('report_departments as d', 'd.id', '=', 'report_indicators.department_id')
            ->join('report_units as u', 'u.id', '=', 'report_indicators.unit_id')
            ->where('report_indicators.active', true)
            ->when($unitIds !== null, fn ($q) => $q->whereIn('report_indicators.unit_id', $unitIds ?: [0]))
            ->with(['department', 'unit', 'hisElement', 'hisAgeGroup', 'hisDiagnosis', 'hisOption', 'hrmProfession'])
            ->orderBy('d.sort_order')->orderBy('d.id')
            ->orderBy('u.sort_order')->orderBy('u.id')
            ->ordered()
            ->get();

        $values = $period
            ? ReportValue::where('period_id', $period->id)->get()->keyBy('indicator_id')
            : collect();
        $actions = $period
            ? ReportAction::where('period_id', $period->id)->orderByDesc('updated_at')->get()->groupBy('indicator_id')
            : collect();

        $code ??= $period?->code;
        $computed = $code ? $this->aggregated($code, $indicators, $values) : [];
        $his = $code ? $this->hisValues($code, $indicators, $values) : [];
        $hrm = $code ? $this->hrmValues($code, $indicators, $values) : [];

        return $indicators->map(function (ReportIndicator $i) use ($values, $actions, $computed, $his, $hrm) {
            $v = $values->get($i->id);
            $value = $v?->value;
            $target = $v?->target;
            // entered value first, then the linked HRM / HIS data, then the months roll-up
            $calc = $value === null ? ($hrm[$i->id] ?? $his[$i->id] ?? $computed[$i->id] ?? null) : null;
            $shown = $value ?? $calc['value'] ?? null;
            $percent = self::percent($shown, $target, $i->direction);

            return [
                'id' => $i->id,
                'code' => (string) $i->code,
                'name' => $i->name,
                'type' => $i->indicator_type,
                'direction' => $i->direction,
                'aggregation' => $i->aggregation,
                'owner_user_id' => $i->owner_user_id,
                'department_id' => $i->department_id,
                'department' => $i->department?->name ?? '',
                'unit_id' => $i->unit_id,
                'unit' => $i->unit?->name ?? '',
                'value' => $value,
                // what reports show: the entered value, else the one calculated from months
                'display_value' => $shown,
                'computed' => $calc,
                'his' => $i->hisLabel(),
                'hrm' => $i->hrmLabel(),
                'target' => $target,
                'value_hc' => $v?->value_hc,
                'value_hosp' => $v?->value_hosp,
                'source' => $v?->source,
                'updated_at' => $v?->updated_at,
                'percent' => $percent,
                'status' => $value === null ? ($calc ? 'computed' : 'missing') : (($percent !== null && $percent < 100) ? 'partial' : 'filled'),
                'actions' => $actions->get($i->id, collect())->values(),
            ];
        });
    }

    /**
     * Values for a quarter / semester / nine-month / year period calculated from monthly values, for indicators
     * whose aggregation is sum / avg / last and that have no entered value for the period.
     *
     * @return array<int, array{value: float, months: int, of: int}>
     */
    private function aggregated(string $code, Collection $indicators, Collection $values): array
    {
        $p = PeriodCode::parse($code);
        $months = match ($p['type']) {
            'quarter' => range(((int) substr($p['tag'], 1) - 1) * 3 + 1, (int) substr($p['tag'], 1) * 3),
            'half' => $p['tag'] === 'H1' ? range(1, 6) : range(7, 12),
            'nine' => range(1, 9),
            'year' => range(1, 12),
            default => [],
        };
        $wanted = $indicators->filter(fn ($i) => $i->aggregation !== 'none' && $values->get($i->id)?->value === null)->keyBy('id');
        if (!$months || $wanted->isEmpty()) {
            return [];
        }

        $monthly = ReportValue::query()
            ->join('report_periods as p', 'p.id', '=', 'report_values.period_id')
            ->whereIn('p.code', array_map(fn ($m) => PeriodCode::make($p['year'], 'M' . $m), $months))
            ->whereIn('report_values.indicator_id', $wanted->keys())
            ->whereNotNull('report_values.value')
            ->orderBy('p.tag')
            ->get(['report_values.indicator_id', 'report_values.value', 'p.tag'])
            ->groupBy('indicator_id');

        $out = [];
        foreach ($monthly as $indicatorId => $rows) {
            $series = $rows->pluck('value')->map(fn ($v) => (float) $v);
            $out[$indicatorId] = [
                'value' => round(match ($wanted[$indicatorId]->aggregation) {
                    'sum' => $series->sum(),
                    'avg' => $series->avg(),
                    default => $series->last(), // 'last': latest month with data (rows are in month order)
                }, 4),
                'months' => $series->count(),
                'of' => count($months),
            ];
        }

        return $out;
    }

    /**
     * Values of indicators linked to a HIS data element: the sum of the matching facts over all facilities for
     * the period. A quarter / semester / year without its own facts rolls up the months (indicator aggregation).
     *
     * @return array<int, array{value: float, months: int, of: int, source: string}>
     */
    private function hisValues(string $code, Collection $indicators, Collection $values): array
    {
        $linked = $indicators->filter(fn ($i) => $i->his_element_id && $values->get($i->id)?->value === null);
        if ($linked->isEmpty()) {
            return [];
        }
        $p = PeriodCode::parse($code);
        $monthCodes = PeriodCode::monthsOf($code);

        $out = [];
        foreach ($linked as $i) {
            $query = fn () => DB::table('report_facts as f')->join('report_periods as p', 'p.id', '=', 'f.period_id')
                ->where('f.data_element_id', $i->his_element_id)
                ->when($i->his_sex, fn ($q) => $q->where('f.sex', $i->his_sex))
                ->when($i->his_age_group_id, fn ($q) => $q->where('f.age_group_id', $i->his_age_group_id))
                ->when($i->his_diagnosis_id, fn ($q) => $q->where('f.diagnosis_id', $i->his_diagnosis_id))
                ->when($i->his_option_id, fn ($q) => $q->where('f.option_id', $i->his_option_id));

            if ($query()->where('p.code', $p['code'])->exists()) {
                $out[$i->id] = ['value' => round(HisAnalysis::total($query()->where('p.code', $p['code'])), 4), 'months' => 1, 'of' => 1, 'source' => 'his'];
                continue;
            }
            if (!$monthCodes || $i->aggregation === 'none') {
                continue;
            }
            $months = $query()->whereIn('p.code', $monthCodes)->select('p.code', 'p.tag')->distinct()->orderBy('p.tag')->pluck('code');
            $monthly = $months->map(fn ($c) => HisAnalysis::total($query()->where('p.code', $c)));
            if ($monthly->isNotEmpty()) {
                $out[$i->id] = [
                    'value' => round(match ($i->aggregation) { 'avg' => $monthly->avg(), 'last' => $monthly->last(), default => $monthly->sum() }, 4),
                    'months' => $monthly->count(),
                    'of' => count($monthCodes),
                    'source' => 'his',
                ];
            }
        }

        return $out;
    }

    /** Values of indicators linked to the HRM staff snapshots (see HrStatistics::value). */
    private function hrmValues(string $code, Collection $indicators, Collection $values): array
    {
        $out = [];
        foreach ($indicators->filter(fn ($i) => $i->hrm_measure && $values->get($i->id)?->value === null) as $i) {
            if ($v = HrStatistics::value($i, $code)) {
                $out[$i->id] = $v;
            }
        }

        return $out;
    }

    /** Rows grouped as [department => [unit => rows]] keeping order. */
    public function grouped(Collection $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[$row['department_id']]['name'] = $row['department'];
            $out[$row['department_id']]['units'][$row['unit_id']]['name'] = $row['unit'];
            $out[$row['department_id']]['units'][$row['unit_id']]['rows'][] = $row;
        }

        return $out;
    }

    /**
     * Attainment in %, where 100 means the target is met. For "lower is better" indicators it is
     * target / value (a value at or under the target scores ≥ 100).
     */
    public static function percent(?float $value, ?float $target, string $direction = 'up'): ?float
    {
        if ($value === null || $target === null || $target == 0.0) {
            return null;
        }
        if ($direction === 'down') {
            return $value == 0.0 ? 100.0 : round($target / $value * 100, 1);
        }

        return round($value / $target * 100, 1);
    }

    /** Figures for the dashboard KPI cards for one period. */
    public function kpis(?ReportPeriod $period): array
    {
        $rows = $this->rows($period);
        $total = $rows->count();
        // Attainment only means something for indicators that have a target (> 0) and a value.
        $withTarget = $rows->filter(fn ($r) => $r['percent'] !== null);
        $achieved = $withTarget->filter(fn ($r) => $r['percent'] >= 100)->count();
        $filled = $rows->whereNotNull('display_value')->count();

        return [
            'total' => $total,
            'filled' => $filled,
            'achieved' => $achieved,
            'with_target' => $withTarget->count(),
            'missing' => $total - $filled,
            'open_issues' => ReportAction::open()->count(),
        ];
    }

    /**
     * Reporting completeness (% of active indicators with a value) per period of a year,
     * for the month / quarter / year chart toggle.
     */
    public function completeness(int $year): array
    {
        $total = max(1, ReportIndicator::where('active', true)->count());
        $filled = ReportValue::query()
            ->join('report_periods as p', 'p.id', '=', 'report_values.period_id')
            ->join('report_indicators as i', 'i.id', '=', 'report_values.indicator_id')
            ->where('p.year', $year)->where('i.active', true)->whereNotNull('report_values.value')
            ->groupBy('p.tag')->selectRaw('p.tag, COUNT(*) as n')->pluck('n', 'tag');

        $series = [];
        foreach (['month', 'quarter', 'year'] as $type) {
            $tags = $type === 'year' ? ['N9', 'Y'] : PeriodCode::tagsFor($type);
            $series[$type] = array_map(fn ($tag) => [
                'label' => PeriodCode::tagLabel($tag),
                'percent' => round(((int) ($filled[$tag] ?? 0)) / $total * 100),
            ], $tags);
        }

        return $series;
    }

    /** Latest data-entry / import / issue events for the activity feed. */
    public function recentActivity(int $limit = 5): Collection
    {
        $values = ReportValue::query()
            ->join('report_units as u', 'u.id', '=', 'report_values.unit_id')
            ->join('report_periods as p', 'p.id', '=', 'report_values.period_id')
            ->selectRaw('u.name as unit, p.code as period, report_values.source, MAX(report_values.updated_at) as at, COUNT(*) as n')
            ->groupBy('u.name', 'p.code', 'report_values.source')
            ->orderByDesc('at')->limit($limit)->get()
            ->map(fn ($r) => [
                'kind' => $r->source === 'import' ? 'import' : 'entry',
                'title' => $r->unit,
                'text' => ($r->source === 'import' ? 'បាននាំចូល Excel' : 'បានបញ្ចូលទិន្នន័យ') . ' • ' . PeriodCode::label($r->period) . ' (' . PeriodCode::kh($r->n) . ')',
                'at' => \Illuminate\Support\Carbon::parse($r->at),
            ]);

        $issues = ReportAction::with('indicator')->orderByDesc('updated_at')->limit($limit)->get()
            ->map(fn (ReportAction $a) => [
                'kind' => 'issue',
                'title' => 'បញ្ហាប្រឈម #' . PeriodCode::kh(str_pad((string) $a->id, 3, '0', STR_PAD_LEFT)),
                'text' => $a->status_label . ' • ' . str($a->indicator?->name)->limit(40),
                'at' => $a->updated_at,
            ]);

        return $values->concat($issues)->sortByDesc('at')->take($limit)->values();
    }
}
