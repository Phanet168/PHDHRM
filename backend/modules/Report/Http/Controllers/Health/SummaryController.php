<?php

namespace Modules\Report\Http\Controllers\Health;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Report\Entities\ReportPeriod;
use Modules\Report\Services\HealthReportData;
use Modules\Report\Services\PeriodCode;
use Modules\Report\Services\XlsxExport;

/** សង្ខេបរបាយការណ៍ — value, target, attainment, issues and actions for one exact period. */
class SummaryController extends HealthController
{
    public const AUDIENCES = [
        'province' => 'ជូនរដ្ឋបាលខេត្ត',
        'ministry' => 'ជូនក្រសួងសុខាភិបាល',
    ];

    public function index(Request $request, HealthReportData $data)
    {
        [$p, $period, $rows] = $this->load($request, $data);

        return view('report::health.reports', [
            'p' => $p,
            'period' => $period,
            'periodLabel' => $period?->label ?? PeriodCode::label($p['code']),
            'years' => $this->years(),
            'tagCounts' => $this->tagCounts($p['year']),
            'groups' => $data->grouped($rows),
            'rowCount' => $rows->count(),
            'filledCount' => $rows->whereNotNull('value')->count(),
            'computedCount' => $rows->whereNotNull('computed')->count(),
            'lastUpdate' => $rows->max('updated_at'),
        ]);
    }

    public function print(Request $request, HealthReportData $data)
    {
        [$p, $period, $rows] = $this->load($request, $data);
        $audience = array_key_exists($request->input('audience'), self::AUDIENCES) ? $request->input('audience') : 'province';

        return view('report::health.print.summary', [
            'periodLabel' => $period?->label ?? PeriodCode::label($p['code']),
            'groups' => $data->grouped($rows),
            'audience' => self::AUDIENCES[$audience],
        ]);
    }

    public function export(Request $request, HealthReportData $data)
    {
        [$p, $period, $rows] = $this->load($request, $data);
        $label = $period?->label ?? PeriodCode::label($p['code']);

        return XlsxExport::download(
            "summary_{$p['code']}.xlsx",
            'សង្ខេប',
            ['រយៈពេល', 'ជំពូក', 'ផ្នែក', 'ID', 'សូចនាករ', 'តម្លៃ', 'ប្រភព', 'គោលដៅ', 'សម្រេចបាន (%)', 'បញ្ហាប្រឈម', 'សកម្មភាព'],
            $rows->map(fn ($r) => [
                $label, $r['department'], $r['unit'], $r['code'], $r['name'], $r['display_value'],
                $r['computed'] ? 'គណនាពី ' . $r['computed']['months'] . '/' . $r['computed']['of'] . ' ខែ' : ($r['value'] !== null ? 'បញ្ចូល' : ''),
                $r['target'], $r['percent'],
                $r['actions']->pluck('issue_text')->filter()->unique()->implode("\n"),
                $r['actions']->pluck('action_text')->filter()->unique()->implode("\n"),
            ])->all(),
            [16, 26, 26, 10, 60, 14, 18, 14, 14, 40, 40]
        );
    }

    /** @return array{0: array, 1: ?ReportPeriod, 2: Collection} */
    private function load(Request $request, HealthReportData $data): array
    {
        $p = $this->periodFromRequest($request);
        $period = ReportPeriod::where('code', $p['code'])->first();
        $q = mb_strtolower(trim((string) $request->input('q')));

        $rows = $data->rows($period, null, $p['code'])->when($q !== '', fn ($c) => $c->filter(function ($r) use ($q) {
            $blob = $r['name'] . ' ' . $r['department'] . ' ' . $r['unit'] . ' '
                . $r['actions']->pluck('issue_text')->implode(' ') . ' ' . $r['actions']->pluck('action_text')->implode(' ');

            return str_contains(mb_strtolower($blob), $q);
        }))->values();

        return [$p, $period, $rows];
    }
}
