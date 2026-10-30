<?php

namespace Modules\Report\Http\Controllers\Health;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use InvalidArgumentException;
use Modules\Report\Entities\ReportPeriod;
use Modules\Report\Services\PeriodCode;
use Modules\Report\Services\ReportAccess;

abstract class HealthController extends Controller
{
    public function __construct(protected ReportAccess $access)
    {
    }

    /**
     * Resolve year / period-type / tag from the request (or a full "period" code), defaulting to the
     * current month. Returns the period code pieces; the period row is created on demand.
     *
     * @return array{year:int, type:string, tag:string, code:string}
     */
    protected function periodFromRequest(Request $request, string $defaultType = 'month'): array
    {
        try {
            if ($request->filled('period')) {
                $p = PeriodCode::parse((string) $request->input('period'));

                return ['year' => $p['year'], 'type' => $p['type'], 'tag' => $p['tag'], 'code' => $p['code']];
            }
        } catch (InvalidArgumentException) {
            // fall through to the defaults
        }

        $year = (int) $request->input('year', now()->year);
        $year = $year >= 2000 && $year <= 2100 ? $year : now()->year;
        $type = array_key_exists($request->input('type'), PeriodCode::TYPES) ? $request->input('type') : $defaultType;
        $tags = PeriodCode::tagsFor($type);
        $default = $type === 'month' ? 'M' . now()->format('m') : $tags[0];
        $tag = in_array($request->input('tag'), $tags, true) ? $request->input('tag') : $default;

        return ['year' => $year, 'type' => $type, 'tag' => $tag, 'code' => PeriodCode::make($year, $tag)];
    }

    /**
     * Upload rule for Excel workbooks. Checked by extension because old HIS .xls exports are detected
     * as "application/CDFV2", which the mimes rule rejects; PhpSpreadsheet validates the content.
     */
    protected function excelRule(bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'file', 'max:10240', function (string $attribute, $file, $fail) {
            if (!in_array(strtolower($file->getClientOriginalExtension()), ['xls', 'xlsx'], true)) {
                $fail('ឯកសារត្រូវតែជា .xlsx ឬ .xls');
            }
        }];
    }

    protected function period(string $code): ReportPeriod
    {
        return ReportPeriod::forCode($code);
    }

    /** Number of entered values per period tag of a year, so period pickers can show where data exists. */
    protected function tagCounts(int $year): array
    {
        return \Modules\Report\Entities\ReportValue::query()
            ->join('report_periods as p', 'p.id', '=', 'report_values.period_id')
            ->where('p.year', $year)->whereNotNull('report_values.value')
            ->groupBy('p.tag')->selectRaw('p.tag, COUNT(*) as n')
            ->pluck('n', 'tag')->map(fn ($n) => (int) $n)->all();
    }

    /** Year options: a few years back and one ahead, plus any year that already has periods. */
    protected function years(): array
    {
        $years = ReportPeriod::query()->distinct()->pluck('year')->all();
        $years = array_unique(array_merge($years, range(now()->year - 4, now()->year + 1)));
        rsort($years);

        return $years;
    }
}
