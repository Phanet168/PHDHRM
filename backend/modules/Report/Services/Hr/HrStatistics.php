<?php

namespace Modules\Report\Services\Hr;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Report\Entities\ReportHrProfession;
use Modules\Report\Entities\ReportHrSkillMap;
use Modules\Report\Entities\ReportPeriod;
use Modules\Report\Services\PeriodCode;

/**
 * HR statistics from the HRM staff records: who is on staff at a month's end (with sex, profession, employment
 * type and workplace) and the movements dated inside the month. Taking a snapshot stores both, so a month's
 * figures stay put afterwards and each one can be traced back to names.
 */
class HrStatistics
{
    public const EMPLOYMENT = ['civil' => 'ក្របខ័ណ្ឌ', 'contract' => 'កិច្ចសន្យា', 'agreement' => 'កិច្ចព្រមព្រៀង', 'other' => 'ផ្សេងៗ'];

    /** Filters an indicator can use: the employment types plus "contract or agreement". */
    public const EMPLOYMENT_FILTERS = self::EMPLOYMENT + ['contract_agreement' => 'កិច្ចសន្យា + កិច្ចព្រមព្រៀង'];

    public const WORKPLACES = ['phd' => 'ទីចាត់ការមន្ទីរ', 'od' => 'ស្រុកប្រតិបត្តិ', 'hospital' => 'មន្ទីរពេទ្យ', 'hc' => 'មណ្ឌលសុខភាព'];

    public const EVENTS = [
        'enter_framework' => 'បញ្ចូលក្របខ័ណ្ឌ',
        'retire' => 'ចូលនិវត្តន៍',
        'remove' => 'លុបចេញពីក្របខ័ណ្ឌ',
        'death' => 'មរណភាព',
        'transfer_out_province' => 'ផ្ទេរចេញក្រៅខេត្ត',
        'transfer_out_ministry' => 'ផ្ទេរចេញក្រៅក្រសួង',
        'transfer_in' => 'ផ្ទេរចូល',
        'lwop' => 'ទំនេរគ្មានបៀវត្ស',
        'promotion' => 'តែងតាំងឡើងថ្នាក់',
    ];

    public const MEASURES = ['headcount' => 'ចំនួនបុគ្គលិក (ចុងខែ)'] + self::EVENTS;

    /** New work status (HRM status transitions) → event. Checked in order. */
    private const STATUS_EVENTS = [
        'transfer_out_ministry' => '/ទៅក្រសួងផ្សេង/u',
        'transfer_out_province' => '/ចេញពីខេត្តទៅ/u',
        'retire' => '/និវត្តន៍/u',
        'death' => '/មរណ/u',
        'lwop' => '/ទំនេរគ្មានបៀវត្/u',
        'remove' => '/លុប|បណ្តេញចេញ|បោះបង់|លាឈប់|បាត់បង់សម្បទា/u',
    ];

    /** @var array<string, Collection> period code → snapshot month ids, per request */
    private static array $snapshotMonths = [];

    private array $workplaceCache = [];
    private ?Collection $departments = null;
    private ?Collection $skillMaps = null;

    /** Month boundaries of a month period code; other period types are refused. */
    public static function month(string $code): array
    {
        $p = PeriodCode::parse($code);
        if ($p['type'] !== 'month') {
            throw new InvalidArgumentException('សូមជ្រើសរើសខែ (snapshot ធ្វើតែប្រចាំខែ)');
        }
        $start = Carbon::create($p['year'], (int) substr($p['tag'], 1), 1)->startOfDay();

        return [$p['code'], $start, $start->copy()->endOfMonth()];
    }

    /**
     * Staff and events of a month computed from the HRM records now (nothing is stored).
     *
     * @return array{staff: Collection, events: Collection}
     */
    public function compute(string $code): array
    {
        [, $start, $end] = self::month($code);
        if ($start->isFuture()) {
            throw new InvalidArgumentException('មិនអាចទាញទិន្នន័យសម្រាប់ខែអនាគតបានទេ');
        }
        $this->ensureSkillMaps();

        // on staff at the month's end: started by then, and still active or left after it
        $staff = $this->employees()
            ->whereNull('e.deleted_at')
            ->where(fn ($q) => $q->whereNull('e.service_start_date')->orWhere('e.service_start_date', '<=', $end->toDateString()))
            ->where(fn ($q) => $q->where('e.is_active', 1)->orWhere('e.termination_date', '>', $end->toDateString()))
            ->get()->map(fn ($e) => $this->attributes($e));

        return ['staff' => $staff, 'events' => $this->events($start, $end)];
    }

    /** Store the month's snapshot, replacing an earlier one. */
    public function save(string $code, ?User $user = null): array
    {
        [$code] = self::month($code);
        $period = ReportPeriod::forCode($code);
        if ($period->locked) {
            throw new InvalidArgumentException('រយៈពេលនេះត្រូវបានចាក់សោ — មិនអាចទាញម្តងទៀតបានទេ');
        }
        $data = $this->compute($code);
        $now = now();

        DB::transaction(function () use ($period, $data, $user, $now) {
            DB::table('report_hr_snapshots')->where('period_id', $period->id)->delete();
            DB::table('report_hr_events')->where('period_id', $period->id)->delete();
            foreach ($data['staff']->chunk(500) as $chunk) {
                DB::table('report_hr_snapshots')->insert($chunk->map(fn ($s) => [
                    'period_id' => $period->id, 'employee_id' => $s['employee_id'], 'sex' => $s['sex'],
                    'profession_id' => $s['profession_id'], 'skill_text' => $s['skill_text'], 'employment' => $s['employment'],
                    'workplace' => $s['workplace'], 'department_id' => $s['department_id'], 'service_state' => $s['service_state'],
                    'created_by' => $user?->id, 'created_at' => $now, 'updated_at' => $now,
                ])->values()->all());
            }
            foreach ($data['events']->chunk(500) as $chunk) {
                DB::table('report_hr_events')->insert($chunk->map(fn ($e) => [
                    'period_id' => $period->id, 'employee_id' => $e['employee_id'], 'event' => $e['event'], 'event_date' => $e['event_date'],
                    'sex' => $e['sex'], 'profession_id' => $e['profession_id'], 'employment' => $e['employment'], 'workplace' => $e['workplace'],
                    'source' => $e['source'], 'created_at' => $now, 'updated_at' => $now,
                ])->values()->all());
            }
        });
        self::$snapshotMonths = [];

        return ['staff' => $data['staff']->count(), 'events' => $data['events']->count()];
    }

    /** Remove a month's snapshot (staff and events). */
    public function delete(ReportPeriod $period): void
    {
        if ($period->locked) {
            throw new InvalidArgumentException('រយៈពេលនេះត្រូវបានចាក់សោ — មិនអាចលុបបានទេ');
        }
        DB::table('report_hr_snapshots')->where('period_id', $period->id)->delete();
        DB::table('report_hr_events')->where('period_id', $period->id)->delete();
        self::$snapshotMonths = [];
    }

    /**
     * Figures of a set of staff rows, for comparing the live HRM data with a saved snapshot.
     *
     * @param  Collection  $staff  rows with sex / employment / workplace / profession_id
     */
    public static function summary(Collection $staff): array
    {
        return [
            'total' => $staff->count(),
            'sex' => $staff->countBy(fn ($s) => $s['sex'] ?? '-')->all(),
            'employment' => $staff->countBy(fn ($s) => $s['employment'] ?? '-')->all(),
            'workplace' => $staff->countBy(fn ($s) => $s['workplace'] ?? '-')->all(),
            'profession' => $staff->groupBy(fn ($s) => $s['profession_id'] ?? 0)
                ->map(fn ($g) => ['M' => $g->where('sex', 'M')->count(), 'F' => $g->where('sex', 'F')->count(), 'T' => $g->count()])->all(),
        ];
    }

    /** Saved snapshot rows of a month in the same shape as compute()['staff']. */
    public function saved(ReportPeriod $period): array
    {
        $staff = DB::table('report_hr_snapshots')->where('period_id', $period->id)->get()->map(fn ($r) => (array) $r);
        $events = DB::table('report_hr_events as v')->leftJoin('employees as e', 'e.id', '=', 'v.employee_id')
            ->where('v.period_id', $period->id)
            ->get(['v.*', 'e.employee_id as staff_no', DB::raw("TRIM(CONCAT(COALESCE(e.last_name,''),' ',COALESCE(e.first_name,''))) as name")])
            ->map(fn ($r) => (array) $r);

        return ['staff' => $staff, 'events' => $events];
    }

    /** Month periods that have a snapshot, newest first. */
    public function snapshots(): Collection
    {
        return DB::table('report_hr_snapshots as s')->join('report_periods as p', 'p.id', '=', 's.period_id')
            ->leftJoin('users as u', 'u.id', '=', 's.created_by')
            ->groupBy('p.id', 'p.code', 'p.locked')
            ->selectRaw('p.id, p.code, p.locked, COUNT(*) as staff, MAX(s.created_at) as taken_at, MAX(u.full_name) as taken_by')
            ->orderByDesc('p.code')->get()
            ->map(function ($r) {
                $r->events = DB::table('report_hr_events')->where('period_id', $r->id)->count();

                return $r;
            });
    }

    /**
     * Value of one indicator slice for a period. Months read their own snapshot; longer periods take the
     * latest month's headcount, or add up the months' events.
     *
     * @return array{value: float, months: int, of: int, source: string}|null
     */
    public static function value(object $link, string $code): ?array
    {
        $p = PeriodCode::parse($code);
        $months = $p['type'] === 'month' ? [$p['code']] : PeriodCode::monthsOf($p['code']);
        // months of the period that have a snapshot (the same for every indicator of a page: looked up once)
        $periods = self::$snapshotMonths[$p['code']] ??=DB::table('report_periods as p')->whereIn('p.code', $months)
            ->whereExists(fn ($q) => $q->from('report_hr_snapshots as s')->whereColumn('s.period_id', 'p.id'))
            ->orderBy('p.tag')->pluck('p.id', 'p.code');
        if ($periods->isEmpty()) {
            return null;
        }

        $headcount = $link->hrm_measure === 'headcount';
        $q = DB::table($headcount ? 'report_hr_snapshots as x' : 'report_hr_events as x')
            ->when(!$headcount, fn ($b) => $b->where('x.event', $link->hrm_measure))
            ->when($link->hrm_sex, fn ($b) => $b->where('x.sex', $link->hrm_sex))
            ->when($link->hrm_profession_id, fn ($b) => $b->where('x.profession_id', $link->hrm_profession_id))
            ->when($link->hrm_workplace, fn ($b) => $b->where('x.workplace', $link->hrm_workplace))
            ->when($link->hrm_employment, fn ($b) => $link->hrm_employment === 'contract_agreement'
                ? $b->whereIn('x.employment', ['contract', 'agreement'])
                : $b->where('x.employment', $link->hrm_employment));

        $value = $headcount
            ? (clone $q)->where('x.period_id', $periods->last())->count()   // stock: the latest month
            : (clone $q)->whereIn('x.period_id', $periods->values())->count(); // flow: all months

        return ['value' => (float) $value, 'months' => $periods->count(), 'of' => count($months), 'source' => 'hrm'];
    }

    /** People behind an indicator slice for one month (drill-down). */
    public static function people(object $link, string $code): Collection
    {
        $period = ReportPeriod::where('code', PeriodCode::parse($code)['code'])->first();
        if (!$period) {
            return collect();
        }
        $headcount = $link->hrm_measure === 'headcount';

        return DB::table($headcount ? 'report_hr_snapshots as x' : 'report_hr_events as x')
            ->join('employees as e', 'e.id', '=', 'x.employee_id')
            ->leftJoin('report_hr_professions as pr', 'pr.id', '=', 'x.profession_id')
            ->leftJoin('departments as d', 'd.id', '=', DB::raw($headcount ? 'x.department_id' : 'COALESCE(e.sub_department_id, e.department_id)'))
            ->where('x.period_id', $period->id)
            ->when(!$headcount, fn ($b) => $b->where('x.event', $link->hrm_measure))
            ->when($link->hrm_sex, fn ($b) => $b->where('x.sex', $link->hrm_sex))
            ->when($link->hrm_profession_id, fn ($b) => $b->where('x.profession_id', $link->hrm_profession_id))
            ->when($link->hrm_workplace, fn ($b) => $b->where('x.workplace', $link->hrm_workplace))
            ->when($link->hrm_employment, fn ($b) => $link->hrm_employment === 'contract_agreement'
                ? $b->whereIn('x.employment', ['contract', 'agreement'])
                : $b->where('x.employment', $link->hrm_employment))
            ->orderBy('e.last_name')->orderBy('e.first_name')
            ->get(['e.id', 'e.employee_id as staff_no', 'e.official_id_10', DB::raw("TRIM(CONCAT(COALESCE(e.last_name,''),' ',COALESCE(e.first_name,''))) as name"),
                'x.sex', 'pr.name as profession', 'x.employment', 'x.workplace', 'd.department_name as unit',
                DB::raw($headcount ? 'NULL as event_date' : 'x.event_date')]);
    }

    /** Active staff whose records are missing something the statistics need. */
    public function quality(): Collection
    {
        $this->ensureSkillMaps();

        return $this->employees()->whereNull('e.deleted_at')->where('e.is_active', 1)->get()
            ->map(fn ($e) => $this->attributes($e))
            ->map(function ($s) {
                $s['problems'] = array_values(array_filter([
                    $s['sex'] ? null : 'ភេទ',
                    $s['employment'] ? null : 'ប្រភេទបុគ្គលិក',
                    $s['skill_text'] === null ? 'ជំនាញ (ទទេ)' : ($s['profession_id'] ? null : 'ជំនាញមិនទាន់ផ្គូផ្គង'),
                    $s['workplace'] ? null : 'អង្គភាព',
                ]));

                return $s;
            })
            ->filter(fn ($s) => $s['problems'])->values();
    }

    /** Make sure every skill text in use has a mapping row (a guess when nothing is set yet). */
    public function ensureSkillMaps(): void
    {
        $texts = DB::table('employees as e')->leftJoin('employee_profile_extras as x', 'x.employee_id', '=', 'e.id')
            ->selectRaw("DISTINCT TRIM(COALESCE(NULLIF(TRIM(e.skill_name), ''), NULLIF(TRIM(x.current_work_skill), ''))) as t")
            ->pluck('t')->filter()->unique();
        $known = ReportHrSkillMap::pluck('skill_text')->map(fn ($t) => mb_strtolower($t))->flip();
        $codes = ReportHrProfession::pluck('id', 'code');
        foreach ($texts as $text) {
            if (!isset($known[mb_strtolower($text)])) {
                $guess = self::guessProfession($text);
                ReportHrSkillMap::create(['skill_text' => $text, 'profession_id' => $guess ? ($codes[$guess] ?? null) : null, 'auto' => true]);
                $known[mb_strtolower($text)] = true;
            }
        }
        $this->skillMaps = null;
    }

    /** Best guess of the profession category from how a skill is usually written. */
    public static function guessProfession(string $text): ?string
    {
        $t = str_replace(['ណ្ខ', 'ប័ត្រ', '្ដ', 'ពត៌', 'បចេ្ចក'], ['ណ្ឌ', 'បត្រ', '្ត', 'ព័ត៌', 'បច្ចេក'], $text);
        $t = preg_replace('/[\s.\-()\x{200B}\x{200C}\x{200D}]+/u', '', $t);
        $bachelor = (bool) preg_match('/បរិ/u', $t);

        return match (true) {
            str_contains($t, 'ទន្តគិលានុបដ្ឋាក') => 'dental_nurse',
            str_contains($t, 'ទន្តបណ្ឌិត') => str_contains($t, 'ឯកទេស') ? 'dentist_specialist' : 'dentist',
            str_contains($t, 'ទន្តពេទ្យ') => 'dental_assistant',
            str_contains($t, 'វេជ្ជបណ្ឌិត') => str_contains($t, 'ឯកទេស') ? 'md_specialist' : 'md',
            str_contains($t, 'គ្រូពេទ្យ') => 'medical_assistant',
            str_contains($t, 'ឱសថបណ្ឌិត') => 'pharm_doctor',
            str_contains($t, 'ឱសថ') => match (true) {
                str_contains($t, 'មធ្យម') => 'pharm_secondary',
                str_contains($t, 'បឋម') => 'pharm_primary',
                default => 'pharmacist',
            },
            str_contains($t, 'ឆ្មប') => $bachelor ? 'midwife_bachelor' : (str_contains($t, 'មធ្យម') ? 'midwife_secondary' : (str_contains($t, 'បឋម') ? 'midwife_primary' : null)),
            str_starts_with($t, 'គិ') || str_contains($t, 'គិលានុបដ្ឋាក') => $bachelor ? 'nurse_bachelor' : (str_contains($t, 'មធ្យម') ? 'nurse_secondary' : (str_contains($t, 'បឋម') ? 'nurse_primary' : null)),
            str_contains($t, 'ព្យាបាល') && str_contains($t, 'ចលនា') => 'physio',
            str_starts_with($t, 'ពិ') || str_contains($t, 'ពិសោធន៍') => str_contains($t, 'មធ្យម') ? 'lab_secondary' : (str_contains($t, 'បឋម') ? 'lab_primary' : null),
            str_contains($t, 'វិទ្យុ') => 'radiology',
            str_contains($t, 'ព័ត៌មានវិទ្យា') => 'it',
            str_contains($t, 'គណនេយ្យ') => 'accounting',
            str_contains($t, 'វិស្វ') => 'engineer',
            str_contains($t, 'បើកបរ') => 'driver',
            (bool) preg_match('/រដ្ឋបាល|កម្មករ|ផ្សេង/u', $t) => 'other',
            default => null,
        };
    }

    /** Employment type from the HRM employee type name. */
    public static function employment(?string $typeName): ?string
    {
        if ($typeName === null || trim($typeName) === '') {
            return null;
        }

        return match (true) {
            str_contains($typeName, 'ក្របខ') => 'civil',
            str_contains($typeName, 'កិច្ចសន្យា') => 'contract',
            str_contains($typeName, 'កិច្ចព្រមព្រៀង') => 'agreement',
            default => 'other',
        };
    }

    /** Workplace of a department: the health centre / hospital it sits in, else its OD, else the PHD office. */
    public function workplace(?int $departmentId): ?string
    {
        if (!$departmentId) {
            return null;
        }
        if (array_key_exists($departmentId, $this->workplaceCache)) {
            return $this->workplaceCache[$departmentId];
        }
        $this->departments ??= DB::table('departments as d')->leftJoin('org_unit_types as t', 't.id', '=', 'd.unit_type_id')
            ->get(['d.id', 'd.parent_id', 't.code'])->keyBy('id');

        $chain = [];
        for ($id = $departmentId, $guard = 0; $id && isset($this->departments[$id]) && $guard < 20; $guard++) {
            $chain[] = $this->departments[$id]->code;
            $id = $this->departments[$id]->parent_id;
        }
        $result = null;
        foreach ($chain as $code) {
            if (in_array($code, ['health_center', 'health_center_with_bed', 'health_center_without_bed', 'health_post'], true)) {
                $result = 'hc';
                break;
            }
            if (in_array($code, ['district_hospital', 'provincial_hospital'], true)) {
                $result = 'hospital';
                break;
            }
        }
        if (!$result && $chain) {
            $result = array_intersect($chain, ['operational_district', 'od_section']) ? 'od' : 'phd';
        }

        return $this->workplaceCache[$departmentId] = $result;
    }

    private function employees()
    {
        return DB::table('employees as e')
            ->leftJoin('employee_profile_extras as x', 'x.employee_id', '=', 'e.id')
            ->leftJoin('employee_types as et', 'et.id', '=', 'e.employee_type_id')
            ->select([
                'e.id', 'e.employee_id as staff_no', 'e.gender_id', 'e.department_id', 'e.sub_department_id', 'e.service_state', 'e.full_right_date',
                'e.first_name', 'e.last_name', 'e.employee_grade', 'e.official_id_10', 'et.name as type_name',
                DB::raw("TRIM(COALESCE(NULLIF(TRIM(e.skill_name), ''), NULLIF(TRIM(x.current_work_skill), ''))) as skill_text"),
            ]);
    }

    private function attributes(object $e): array
    {
        $this->skillMaps ??= ReportHrSkillMap::get(['skill_text', 'profession_id'])->keyBy(fn ($m) => mb_strtolower($m->skill_text));
        $department = $e->sub_department_id ?: $e->department_id;
        $skill = $e->skill_text !== null && $e->skill_text !== '' ? $e->skill_text : null;

        return [
            'employee_id' => $e->id,
            'staff_no' => $e->staff_no,
            'name' => trim(($e->last_name ?? '') . ' ' . ($e->first_name ?? '')),
            'sex' => match ((int) $e->gender_id) { 1 => 'M', 2 => 'F', default => null },
            'skill_text' => $skill,
            'profession_id' => $skill ? ($this->skillMaps[mb_strtolower($skill)]->profession_id ?? null) : null,
            'employment' => self::employment($e->type_name),
            'workplace' => $this->workplace($department ? (int) $department : null),
            'department_id' => $department,
            'service_state' => $e->service_state,
            'grade' => $e->employee_grade,
            'official_id' => $e->official_id_10,
        ];
    }

    /** Movements dated inside the month, one per person and kind. */
    private function events(Carbon $start, Carbon $end): Collection
    {
        $range = [$start->toDateString(), $end->toDateString()];
        $found = [];
        $add = function (int $employeeId, string $event, ?string $date, string $source) use (&$found) {
            $found["{$employeeId}|{$event}"] ??= ['employee_id' => $employeeId, 'event' => $event, 'event_date' => $date, 'source' => $source];
        };

        $transitions = DB::table('employee_status_transitions')->whereBetween('effective_date', $range)
            ->orderBy('effective_date')->orderBy('id')
            ->get(['id', 'employee_id', 'transition_type', 'from_work_status_name', 'to_work_status_name', 'effective_date']);
        foreach ($transitions as $t) {
            if ($t->transition_type === 'transfer_in') {
                $add($t->employee_id, 'transfer_in', $t->effective_date, "status_transition#{$t->id}");
                continue;
            }
            if ($t->from_work_status_name === $t->to_work_status_name) {
                continue; // a re-save, not a change
            }
            foreach (self::STATUS_EVENTS as $event => $pattern) {
                if (preg_match($pattern, (string) $t->to_work_status_name)) {
                    $add($t->employee_id, $event, $t->effective_date, "status_transition#{$t->id}");
                    break;
                }
            }
        }
        foreach (DB::table('employee_service_histories')->where('event_type', 'retirement')->whereBetween('event_date', $range)->get(['id', 'employee_id', 'event_date']) as $h) {
            $add($h->employee_id, 'retire', $h->event_date, "service_history#{$h->id}");
        }
        foreach (DB::table('employee_pay_grade_histories')->whereBetween('start_date', $range)->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'rejected'))->get(['id', 'employee_id', 'start_date']) as $g) {
            $add($g->employee_id, 'promotion', $g->start_date, "pay_grade#{$g->id}");
        }
        foreach (DB::table('employees')->whereBetween('full_right_date', $range)->get(['id', 'full_right_date']) as $e) {
            $add($e->id, 'enter_framework', $e->full_right_date, "employee#{$e->id}");
        }
        if (!$found) {
            return collect();
        }

        $people = $this->employees()->whereIn('e.id', array_unique(array_column($found, 'employee_id')))->get()
            ->keyBy('id')->map(fn ($e) => $this->attributes($e));

        return collect($found)->filter(fn ($ev) => isset($people[$ev['employee_id']]))
            ->map(fn ($ev) => $ev + array_intersect_key($people[$ev['employee_id']], array_flip(['name', 'staff_no', 'sex', 'profession_id', 'employment', 'workplace'])))
            ->values();
    }
}
