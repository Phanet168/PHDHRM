<?php

namespace Modules\Report\Http\Controllers\Health;

use Illuminate\Http\Request;
use Modules\Report\Entities\ReportDataElement;
use Modules\Report\Services\His\HisAnalysis;
use Modules\Report\Services\PeriodCode;

/** វិភាគទិន្នន័យ — one HIS data element broken down by age, sex, diagnosis, facility and month. */
class AnalysisController extends HealthController
{
    public function index(Request $request, HisAnalysis $analysis)
    {
        $elements = $analysis->elements();
        $facilities = $analysis->facilities();
        if ($elements->isEmpty()) {
            return view('report::health.analysis', ['empty' => true]);
        }

        // Default: OPD new cases by diagnosis, the richest table
        $element = $elements->firstWhere('id', (int) $request->input('element'))
            ?? $elements->firstWhere('name', 'ករណីថ្មីពិគ្រោះជំងឺក្រៅ')
            ?? $elements->sortByDesc('n')->first();
        $year = (int) $request->input('year', now()->year);
        $periods = $analysis->periodsWithData($element->id, $year);
        if ($periods->isEmpty() && !$request->filled('year')) {
            // jump to the latest year that has data for this element
            $year = (int) \Illuminate\Support\Facades\DB::table('report_facts as f')->join('report_periods as p', 'p.id', '=', 'f.period_id')
                ->where('f.data_element_id', $element->id)->max('p.year');
            $periods = $analysis->periodsWithData($element->id, $year);
        }
        $period = $periods->firstWhere('code', $request->input('period')) ?? $periods->last();
        $facilityId = $request->filled('facility') ? (int) $request->input('facility') : null;

        return view('report::health.analysis', [
            'empty' => false,
            'elements' => $elements->groupBy('section'),
            'element' => ReportDataElement::find($element->id),
            'facilities' => $facilities,
            'facilityId' => $facilityId,
            'years' => $this->years(),
            'year' => $year,
            'periods' => $periods,
            'period' => $period,
            'data' => $period ? $analysis->summary($element->id, $period->code, $facilityId) : null,
            'periodLabel' => $period ? PeriodCode::label($period->code) : null,
        ]);
    }
}
