<?php

namespace Modules\Report\Http\Controllers\Health;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Report\Entities\ReportHrProfession;
use Modules\Report\Entities\ReportHrSkillMap;
use Modules\Report\Entities\ReportIndicator;
use Modules\Report\Entities\ReportPeriod;
use Modules\Report\Services\Hr\HrIndicatorLinker;
use Modules\Report\Services\Hr\HrStatistics;
use Modules\Report\Services\PeriodCode;

/** ទិន្នន័យ HRM — monthly HR statistics taken from the staff records: snapshot, skill mapping, data quality. */
class HrController extends HealthController
{
    public function index(Request $request, HrStatistics $hr, string $tab = 'snapshot')
    {
        $view = ['tab' => $tab, 'professions' => ReportHrProfession::orderBy('sort_order')->get()];

        if ($tab === 'snapshot') {
            // default: the month that just ended
            $code = $request->input('period') ?: PeriodCode::make(now()->subMonthNoOverflow()->year, 'M' . now()->subMonthNoOverflow()->month);
            try {
                [$code, $start] = HrStatistics::month($code);
                $live = $hr->compute($code);
            } catch (InvalidArgumentException $e) {
                return redirect()->route('report.health.hr')->withErrors(['period' => $e->getMessage()]);
            }
            $period = ReportPeriod::where('code', $code)->first();
            $saved = $period ? $hr->saved($period) : null;
            $view += [
                'code' => $code,
                'period' => $period,
                'live' => $live,
                'liveSummary' => HrStatistics::summary($live['staff']),
                'saved' => $saved && $saved['staff']->isNotEmpty() ? $saved : null,
                'savedSummary' => $saved && $saved['staff']->isNotEmpty() ? HrStatistics::summary($saved['staff']) : null,
                // attributes (profession, unit …) are today's; warn when looking further back than last month
                'old' => $start->lt(now()->subMonthsNoOverflow(1)->startOfMonth()),
                'snapshots' => $hr->snapshots(),
                'years' => $this->years(),
                'linked' => ReportIndicator::whereNotNull('hrm_measure')->count(),
                'linkable' => app(HrIndicatorLinker::class)->run(false)->count(),
            ];
        } elseif ($tab === 'skills') {
            $hr->ensureSkillMaps();
            $counts = DB::table('employees as e')->leftJoin('employee_profile_extras as x', 'x.employee_id', '=', 'e.id')
                ->whereNull('e.deleted_at')->where('e.is_active', 1)
                ->groupByRaw("LOWER(TRIM(COALESCE(NULLIF(TRIM(e.skill_name), ''), NULLIF(TRIM(x.current_work_skill), ''))))")
                ->selectRaw("LOWER(TRIM(COALESCE(NULLIF(TRIM(e.skill_name), ''), NULLIF(TRIM(x.current_work_skill), '')))) as t, COUNT(*) as n")
                ->pluck('n', 't');
            $view['maps'] = ReportHrSkillMap::with('profession')->get()
                ->each(fn ($m) => $m->staff = (int) ($counts[mb_strtolower($m->skill_text)] ?? 0))
                ->sortBy([['profession_id', 'asc'], ['staff', 'desc']])->values();
        } else {
            $view['issues'] = $hr->quality();
            $view['types'] = DB::table('employee_types')->orderBy('id')->get(['id', 'name']);
            $view['units'] = DB::table('departments')->pluck('department_name', 'id');
            $view['canEditStaff'] = (bool) $request->user()?->can('update_employee');
        }

        return view('report::health.hr', $view);
    }

    public function save(Request $request, HrStatistics $hr)
    {
        $code = (string) $request->validate(['period' => ['required', 'string']], ['period.required' => 'សូមជ្រើសខែ'])['period'];
        try {
            $n = $hr->save($code, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['period' => $e->getMessage()]);
        }

        return redirect()->route('report.health.hr', ['period' => PeriodCode::parse($code)['code']])->with('success', sprintf(
            'បានរក្សាទុកស្ថិតិ %s៖ បុគ្គលិក %s នាក់ • ព្រឹត្តិការណ៍ %s',
            PeriodCode::label($code), PeriodCode::kh($n['staff']), PeriodCode::kh($n['events'])
        ));
    }

    public function destroy(ReportPeriod $period, HrStatistics $hr)
    {
        try {
            $hr->delete($period);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['period' => $e->getMessage()]);
        }

        return back()->with('success', 'បានលុបស្ថិតិ ' . $period->label);
    }

    public function saveSkills(Request $request)
    {
        $data = $request->validate([
            'maps' => ['array'],
            'maps.*' => ['nullable', 'integer', 'exists:report_hr_professions,id'],
        ]);
        $changed = 0;
        foreach (ReportHrSkillMap::whereIn('id', array_keys($data['maps'] ?? []))->get() as $map) {
            $profession = $data['maps'][$map->id] ?: null;
            if ($map->profession_id != $profession || $map->auto) {
                $changed += (int) ($map->profession_id != $profession);
                $map->update(['profession_id' => $profession, 'auto' => false]);
            }
        }

        return back()->with('success', 'បានរក្សាទុកការផ្គូផ្គងជំនាញ (' . PeriodCode::kh($changed) . ' ប្តូរ) — ទាញស្ថិតិខែឡើងវិញ ដើម្បីប្រើការផ្គូផ្គងថ្មី');
    }

    public function link(HrIndicatorLinker $linker)
    {
        $n = $linker->run()->count();

        return back()->with('success', 'បានភ្ជាប់សូចនាករ HR ' . PeriodCode::kh($n) . ' ទៅទិន្នន័យ HRM — អាចកែម្តងមួយៗនៅ ការកំណត់ › សូចនាករ');
    }

    /** Set the HRM employee type of several staff at once (from the data-quality list). */
    public function setEmployeeTypes(Request $request)
    {
        abort_unless($request->user()?->can('update_employee'), 403);
        $data = $request->validate([
            'employee_type_id' => ['required', 'integer', 'exists:employee_types,id'],
            'employees' => ['required', 'array', 'min:1'],
            'employees.*' => ['integer', 'exists:employees,id'],
        ], ['employees.required' => 'សូមជ្រើសបុគ្គលិកយ៉ាងហោចណាស់ម្នាក់', 'employee_type_id.required' => 'សូមជ្រើសប្រភេទបុគ្គលិក']);

        $n = DB::table('employees')->whereIn('id', $data['employees'])->whereNull('deleted_at')
            ->update(['employee_type_id' => $data['employee_type_id'], 'updated_by' => $request->user()->id, 'updated_at' => now()]);
        $type = DB::table('employee_types')->where('id', $data['employee_type_id'])->value('name');

        return back()->with('success', 'បានកំណត់ «' . $type . '» ឲ្យបុគ្គលិក ' . PeriodCode::kh($n) . ' នាក់');
    }

    /** Names behind an HRM-linked indicator for a month. */
    public function people(Request $request)
    {
        $data = $request->validate(['indicator' => ['required', 'integer', 'exists:report_indicators,id'], 'period' => ['required', 'string']]);
        $indicator = ReportIndicator::with('hrmProfession')->findOrFail($data['indicator']);
        abort_unless($indicator->hrm_measure, 404);
        $p = PeriodCode::parse($data['period']);
        // a quarter / year shows its latest month with a snapshot
        $month = $p['type'] === 'month' ? $p['code'] : DB::table('report_periods as rp')->whereIn('rp.code', PeriodCode::monthsOf($p['code']))
            ->whereExists(fn ($q) => $q->from('report_hr_snapshots as s')->whereColumn('s.period_id', 'rp.id'))
            ->orderByDesc('rp.tag')->value('rp.code');

        return view('report::health.hr-people', [
            'indicator' => $indicator,
            'code' => $month ?: $p['code'],
            'people' => $month ? HrStatistics::people($indicator, $month) : collect(),
        ]);
    }
}
