<?php

namespace Modules\Report\Services\His;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Report\Services\PeriodCode;

/** Aggregations of report_facts for the analysis page (one data element at a time). */
class HisAnalysis
{
    /** Data elements that have imported values, grouped by section, with how many values each has. */
    public function elements(): Collection
    {
        return DB::table('report_data_elements as e')
            ->join('report_facts as f', 'f.data_element_id', '=', 'e.id')
            ->groupBy('e.id', 'e.code', 'e.name', 'e.section')
            ->selectRaw('e.id, e.code, e.name, e.section, COUNT(*) as n')
            ->orderBy('e.section')->orderByRaw('LENGTH(e.code)')->orderBy('e.code')
            ->get();
    }

    /** Facilities that have imported values. */
    public function facilities(): Collection
    {
        return DB::table('departments as d')
            ->whereIn('d.id', DB::table('report_facts')->distinct()->select('facility_id'))
            ->orderBy('d.department_name')->get(['d.id', 'd.department_name as name']);
    }

    /** Periods of a year that have values for the element. */
    public function periodsWithData(int $elementId, int $year): Collection
    {
        return DB::table('report_facts as f')->join('report_periods as p', 'p.id', '=', 'f.period_id')
            ->where('f.data_element_id', $elementId)->where('p.year', $year)
            ->groupBy('p.code', 'p.tag')->selectRaw('p.code, p.tag, COUNT(*) as n')->get()
            ->sortBy(fn ($p) => PeriodCode::order($p->tag))->values();
    }

    /**
     * Everything the page shows for one element / period / (optional) facility.
     *
     * @return array{total: float, by_age_sex: array, by_sex: array, by_diagnosis: Collection, by_facility: Collection, by_option: Collection, trend: array}
     */
    public function summary(int $elementId, string $periodCode, ?int $facilityId): array
    {
        $base = fn () => $this->base($elementId, $facilityId)->where('p.code', $periodCode);

        $ageSex = (clone $base())->leftJoin('report_age_groups as a', 'a.id', '=', 'f.age_group_id')
            ->whereNotNull('f.age_group_id')
            ->groupBy('a.id', 'a.label', 'a.sort_order', 'f.sex')
            ->selectRaw('a.id, a.label, a.sort_order, f.sex, SUM(f.value) as v')
            ->orderBy('a.sort_order')->get();
        $byAge = [];
        foreach ($ageSex as $r) {
            $byAge[$r->id] ??= ['label' => $r->label, 'M' => 0.0, 'F' => 0.0, 'T' => 0.0];
            $byAge[$r->id][$r->sex ?: 'T'] += (float) $r->v;
        }

        $bySex = ['M' => self::total($base()->where('f.sex', 'M')), 'F' => self::total($base()->where('f.sex', 'F'))];

        $byDiagnosis = (clone $base())->join('report_diagnoses as d', 'd.id', '=', 'f.diagnosis_id')
            ->groupBy('d.id', 'd.icd10', 'd.name', 'd.group_name')
            ->selectRaw('d.icd10, d.name, d.group_name, SUM(f.value) as v, SUM(CASE WHEN f.sex = "M" THEN f.value ELSE 0 END) as m, SUM(CASE WHEN f.sex = "F" THEN f.value ELSE 0 END) as fe')
            ->havingRaw('SUM(f.value) > 0')->orderByDesc('v')->get();

        $byFacility = (clone $base())->join('departments as d', 'd.id', '=', 'f.facility_id')
            ->groupBy('d.id', 'd.department_name')->selectRaw('d.department_name as name, SUM(f.value) as v')->orderByDesc('v')->get();

        $byOption = (clone $base())->join('report_category_options as o', 'o.id', '=', 'f.option_id')
            ->groupBy('o.id', 'o.label', 'o.sort_order')->selectRaw('o.label, SUM(f.value) as v')->orderBy('o.sort_order')->orderBy('o.id')->get();

        // Monthly trend of the same element for the period's year
        $year = PeriodCode::parse($periodCode)['year'];
        $months = $this->base($elementId, $facilityId)->where('p.year', $year)->where('p.tag', 'like', 'M%')
            ->groupBy('p.tag')->selectRaw('p.tag, SUM(f.value) as v')->pluck('v', 'tag');
        $trend = array_map(fn ($tag) => ['tag' => $tag, 'label' => PeriodCode::tagLabel($tag), 'v' => isset($months[$tag]) ? (float) $months[$tag] : null], PeriodCode::tagsFor('month'));

        return [
            'total' => self::total($base()),
            'by_age_sex' => array_values($byAge),
            'by_sex' => $bySex,
            'by_diagnosis' => $byDiagnosis,
            'by_facility' => $byFacility,
            'by_option' => $byOption,
            'trend' => $trend,
        ];
    }

    /**
     * Total of a facts query without double counting. A table can break the same total down twice (by zone AND
     * by sex): facts sharing the same set of filled dimensions form one breakdown, so each facility counts the
     * largest of its breakdowns, and facilities are added up.
     */
    public static function total(Builder $facts): float
    {
        return (float) $facts
            ->groupBy('f.facility_id')
            ->groupByRaw('f.diagnosis_id IS NULL, f.age_group_id IS NULL, f.sex IS NULL, f.option_id IS NULL')
            ->selectRaw('f.facility_id, SUM(f.value) as v')->get()
            ->groupBy('facility_id')->sum(fn ($groups) => $groups->max(fn ($g) => (float) $g->v));
    }

    private function base(int $elementId, ?int $facilityId): Builder
    {
        return DB::table('report_facts as f')
            ->join('report_periods as p', 'p.id', '=', 'f.period_id')
            ->where('f.data_element_id', $elementId)
            ->when($facilityId, fn ($q) => $q->where('f.facility_id', $facilityId));
    }
}
