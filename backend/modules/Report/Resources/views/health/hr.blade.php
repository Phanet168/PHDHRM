@extends('report::health.layout')
@php
    use Modules\Report\Services\Hr\HrStatistics;
    use Modules\Report\Services\PeriodCode;
    $kh = fn ($v) => PeriodCode::kh($v);
    $sexes = \Modules\Report\Entities\ReportFact::SEXES;
    $tabs = ['snapshot' => 'ស្ថិតិប្រចាំខែ', 'skills' => 'ផ្គូផ្គងជំនាញ', 'quality' => 'គុណភាពទិន្នន័យ'];
@endphp

@section('rpt_title', 'ទិន្នន័យ HRM')

@push('rpt_css')
    <style>
        .hr-tiles { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; }
        .hr-tile { background: var(--rpt-s50); border-radius: 14px; padding: 14px 16px; }
        .hr-tile b { display: block; font-size: 24px; font-weight: 600; color: var(--rpt-s900); line-height: 1.2; }
        .hr-tile span { font-size: 12px; color: var(--rpt-s500); }
        .hr-diff { color: var(--rpt-a600); font-weight: 600; }
        .hr-table td, .hr-table th { padding: 10px 16px; }
        .hr-chip { cursor: pointer; border: 0; }
        .hr-chip.active { outline: 2px solid var(--rpt-e600); }
    </style>
@endpush

@section('rpt')
    <section class="rpt-title">
        <div>
            <div class="rpt-eyebrow">ស្ថិតិមន្ត្រីរាជការ</div>
            <h1>ទិន្នន័យ HRM</h1>
            <p>ទាញស្ថិតិបុគ្គលិកពីប្រព័ន្ធគ្រប់គ្រងមន្ត្រី — ចំនួនតាមជំនាញ ភេទ ប្រភេទ កន្លែងការងារ និងចលនាបុគ្គលិកក្នុងខែ — ហើយរក្សាទុកជាស្ថិតិប្រចាំខែ</p>
        </div>
        <div class="rpt-seg">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('report.health.hr', $key) }}" class="{{ $tab === $key ? 'active' : '' }}">{{ $label }}</a>
            @endforeach
        </div>
    </section>

    {{-- ================= Monthly snapshot ================= --}}
    @if ($tab === 'snapshot')
        @php
            $p = PeriodCode::parse($code);
            $ls = $liveSummary;
            $ss = $savedSummary;
            $cmp = function ($live, $saved) use ($ss, $kh) {
                if (!$ss) {
                    return '';
                }
                return (int) $live === (int) $saved
                    ? '<div class="rpt-sub">បានរក្សាទុក ' . $kh($saved) . '</div>'
                    : '<div class="rpt-sub hr-diff">បានរក្សាទុក ' . $kh($saved) . '</div>';
            };
            $unknownType = $ls['employment']['-'] ?? 0;
        @endphp
        <div class="rpt-card rpt-card-pad">
            <div class="d-flex flex-wrap gap-3 align-items-end">
                <form method="GET" class="d-flex gap-2 align-items-end" id="frmMonth">
                    <div><label class="rpt-label">ឆ្នាំ</label>
                        <select class="rpt-input" id="selYear">@foreach ($years as $y)<option value="{{ $y }}" @selected($y == $p['year'])>{{ $kh($y) }}</option>@endforeach</select>
                    </div>
                    <div><label class="rpt-label">ខែ</label>
                        <select class="rpt-input" id="selMonth">@foreach (PeriodCode::tagsFor('month') as $tag)<option value="{{ $tag }}" @selected($tag === $p['tag'])>{{ PeriodCode::tagLabel($tag) }}</option>@endforeach</select>
                    </div>
                    <input type="hidden" name="period" id="inpPeriod" value="{{ $code }}">
                    <button class="rpt-btn" type="submit">បង្ហាញ</button>
                </form>
                <div class="me-auto ms-md-3">
                    @if ($saved)
                        <span class="rpt-badge rpt-badge-ok">✓ បានរក្សាទុក {{ PeriodCode::label($code) }}</span>
                        <span class="rpt-sub" style="display:inline">{{ \Illuminate\Support\Carbon::parse($saved['staff']->max('created_at'))->format('d/m/Y H:i') }}</span>
                    @else
                        <span class="rpt-badge rpt-badge-muted">មិនទាន់រក្សាទុក {{ PeriodCode::label($code) }}</span>
                    @endif
                    @if ($period?->locked)<span class="rpt-badge rpt-badge-warn">🔒 ចាក់សោ</span>@endif
                </div>
                <form method="POST" action="{{ route('report.health.hr.save') }}"
                    onsubmit="{{ $saved ? "if (!confirm('ជំនួសស្ថិតិដែលបានរក្សាទុករួចសម្រាប់ " . PeriodCode::label($code) . " ដោយទិន្នន័យ HRM បច្ចុប្បន្ន?')) return false;" : '' }} this.querySelector('button').disabled = true; this.querySelector('button').textContent = 'កំពុងរក្សាទុក…'">
                    @csrf
                    <input type="hidden" name="period" value="{{ $code }}">
                    <button class="rpt-btn rpt-btn-primary" type="submit" @disabled($period?->locked)>{{ $saved ? 'ទាញម្តងទៀត និងជំនួស' : 'ទាញ និងរក្សាទុក' }} {{ PeriodCode::label($code) }}</button>
                </form>
            </div>
            @if ($old)
                <div class="rpt-alert rpt-alert-err mt-3">ខែនេះកន្លងយូរហើយ៖ ចំនួនបុគ្គលិកត្រូវបានគណនាឡើងវិញ (ចូលបម្រើការមុនចុងខែ និងមិនទាន់ចាកចេញនៅចុងខែ) ប៉ុន្តែ ជំនាញ ប្រភេទ និងអង្គភាព គឺជាព័ត៌មាន <b>បច្ចុប្បន្ន</b>។ ល្អបំផុត៖ ទាញរៀងរាល់ដើមខែ (ប្រព័ន្ធទាញស្វ័យប្រវត្តិនៅថ្ងៃទី ១)។</div>
            @endif
            @if ($unknownType)
                <div class="rpt-alert rpt-alert-err mt-3">បុគ្គលិក <b>{{ $kh($unknownType) }}</b> នាក់មិនទាន់កំណត់ «ប្រភេទបុគ្គលិក» (ក្របខ័ណ្ឌ / កិច្ចសន្យា / កិច្ចព្រមព្រៀង) — សូចនាករតាមប្រភេទនឹងខ្វះ។ <a href="{{ route('report.health.hr', 'quality') }}">កែនៅ គុណភាពទិន្នន័យ →</a></div>
            @endif

            <h2 class="mt-4 mb-3" style="font-size:16px">ចំនួនបុគ្គលិកនៅចុង{{ PeriodCode::label($code) }} <span class="rpt-sub" style="display:inline">— លេខធំ = ទិន្នន័យ HRM ឥឡូវ{{ $ss ? '; លេខតូច = បានរក្សាទុក (ពណ៌ទឹកក្រូច = ខុសគ្នា)' : '' }}</span></h2>
            <div class="hr-tiles">
                <div class="hr-tile"><span>សរុប</span><b>{{ $kh($ls['total']) }}</b>{!! $cmp($ls['total'], $ss['total'] ?? 0) !!}</div>
                @foreach ($sexes as $k => $label)
                    <div class="hr-tile"><span>{{ $label }}</span><b>{{ $kh($ls['sex'][$k] ?? 0) }}</b>{!! $cmp($ls['sex'][$k] ?? 0, $ss['sex'][$k] ?? 0) !!}</div>
                @endforeach
                @foreach (HrStatistics::EMPLOYMENT + ['-' => 'មិនទាន់កំណត់ប្រភេទ'] as $k => $label)
                    @if (($ls['employment'][$k] ?? 0) || ($ss['employment'][$k] ?? 0) || $k !== 'other')
                        <div class="hr-tile"><span>{{ $label }}</span><b>{{ $kh($ls['employment'][$k] ?? 0) }}</b>{!! $cmp($ls['employment'][$k] ?? 0, $ss['employment'][$k] ?? 0) !!}</div>
                    @endif
                @endforeach
                @foreach (HrStatistics::WORKPLACES as $k => $label)
                    <div class="hr-tile"><span>{{ $label }}</span><b>{{ $kh($ls['workplace'][$k] ?? 0) }}</b>{!! $cmp($ls['workplace'][$k] ?? 0, $ss['workplace'][$k] ?? 0) !!}</div>
                @endforeach
            </div>
        </div>

        <div class="row g-4 mt-0">
            <div class="col-xl-7">
                <div class="rpt-card h-100">
                    <div class="rpt-toolbar"><h2 class="me-auto">តាមជំនាញ</h2><a class="rpt-link" href="{{ route('report.health.hr', 'skills') }}">ផ្គូផ្គងជំនាញ →</a></div>
                    <div class="rpt-table-wrap" style="max-height:560px;overflow-y:auto">
                        <table class="rpt-table hr-table" style="min-width:520px">
                            <thead><tr><th>ជំនាញ</th><th class="text-end">ប្រុស</th><th class="text-end">ស្រី</th><th class="text-end">សរុប</th>@if ($ss)<th class="text-end">បានរក្សាទុក</th>@endif</tr></thead>
                            <tbody>
                                @foreach ($professions as $pr)
                                    @php($l = $ls['profession'][$pr->id] ?? ['M' => 0, 'F' => 0, 'T' => 0])
                                    @php($s = $ss['profession'][$pr->id]['T'] ?? 0)
                                    @continue(!$l['T'] && !$s)
                                    <tr>
                                        <td>{{ $pr->name }}</td>
                                        <td class="text-end">{{ $kh($l['M']) }}</td>
                                        <td class="text-end">{{ $kh($l['F']) }}</td>
                                        <td class="text-end"><b>{{ $kh($l['T']) }}</b></td>
                                        @if ($ss)<td class="text-end {{ $s != $l['T'] ? 'hr-diff' : '' }}">{{ $kh($s) }}</td>@endif
                                    </tr>
                                @endforeach
                                @php($u = $ls['profession'][0] ?? null)
                                @if ($u)
                                    <tr>
                                        <td><span class="rpt-badge rpt-badge-warn">មិនទាន់ផ្គូផ្គង</span></td>
                                        <td class="text-end">{{ $kh($u['M']) }}</td><td class="text-end">{{ $kh($u['F']) }}</td><td class="text-end"><b>{{ $kh($u['T']) }}</b></td>
                                        @if ($ss)<td class="text-end">{{ $kh($ss['profession'][0]['T'] ?? 0) }}</td>@endif
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="rpt-card h-100">
                    <div class="rpt-toolbar"><h2 class="me-auto">ចលនាបុគ្គលិកក្នុងខែ</h2><span class="rpt-badge rpt-badge-info">{{ $kh($live['events']->count()) }}</span></div>
                    <div class="rpt-table-wrap" style="max-height:560px;overflow-y:auto">
                        <table class="rpt-table hr-table" style="min-width:420px">
                            <thead><tr><th>ព្រឹត្តិការណ៍</th><th>ឈ្មោះ</th><th>កាលបរិច្ឆេទ</th></tr></thead>
                            <tbody>
                                @forelse ($live['events']->sortBy('event') as $e)
                                    <tr>
                                        <td><span class="rpt-badge rpt-badge-muted">{{ HrStatistics::EVENTS[$e['event']] ?? $e['event'] }}</span></td>
                                        <td>{{ $e['name'] }}<div class="rpt-sub">{{ $e['staff_no'] }} {{ $e['sex'] ? '• ' . $sexes[$e['sex']] : '' }}</div></td>
                                        <td style="white-space:nowrap">{{ $e['event_date'] ? \Illuminate\Support\Carbon::parse($e['event_date'])->format('d/m/Y') : '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="rpt-empty">គ្មានចលនាបុគ្គលិកដែលបានកត់ត្រាក្នុងខែនេះ</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($saved && $saved['events']->count() !== $live['events']->count())
                        <div class="rpt-foot rpt-sub hr-diff">បានរក្សាទុក {{ $kh($saved['events']->count()) }} ព្រឹត្តិការណ៍</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="row g-4 mt-0">
            <div class="col-xl-8">
                <div class="rpt-card">
                    <div class="rpt-toolbar"><h2 class="me-auto">ស្ថិតិដែលបានរក្សាទុក</h2></div>
                    <div class="rpt-table-wrap">
                        <table class="rpt-table hr-table" style="min-width:640px">
                            <thead><tr><th>ខែ</th><th class="text-end">បុគ្គលិក</th><th class="text-end">ចលនា</th><th>រក្សាទុកនៅ</th><th>ដោយ</th><th></th></tr></thead>
                            <tbody>
                                @forelse ($snapshots as $s)
                                    <tr>
                                        <td><a href="{{ route('report.health.hr', ['period' => $s->code]) }}">{{ PeriodCode::label($s->code) }}</a> @if ($s->locked)🔒@endif</td>
                                        <td class="text-end">{{ $kh($s->staff) }}</td>
                                        <td class="text-end">{{ $kh($s->events) }}</td>
                                        <td style="white-space:nowrap">{{ \Illuminate\Support\Carbon::parse($s->taken_at)->format('d/m/Y H:i') }}</td>
                                        <td>{{ $s->taken_by ?? 'ស្វ័យប្រវត្តិ' }}</td>
                                        <td class="text-end">
                                            @canany(['delete_health_activity_report', 'delete_health_report_settings'])
                                                @unless ($s->locked)
                                                    <form method="POST" action="{{ route('report.health.hr.destroy', $s->id) }}" onsubmit="return confirm('លុបស្ថិតិ HRM {{ PeriodCode::label($s->code) }}? សូចនាករដែលភ្ជាប់នឹងគ្មានតម្លៃសម្រាប់ខែនេះ។')">
                                                        @csrf @method('DELETE')<button class="rpt-link rpt-link-danger" type="submit">លុប</button>
                                                    </form>
                                                @endunless
                                            @endcanany
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="rpt-empty">មិនទាន់មានស្ថិតិដែលបានរក្សាទុក — ជ្រើសខែ ហើយចុច «ទាញ និងរក្សាទុក»</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <aside class="col-xl-4">
                <div class="rpt-note">
                    <span style="color:var(--rpt-e600)">@include('report::health._icon', ['name' => 'report'])</span>
                    <div>
                        <h3>សូចនាករធនធានមនុស្ស</h3>
                        <p>បានភ្ជាប់ទៅ HRM៖ <b>{{ $kh($linked) }}</b> សូចនាករ។ ពេលរក្សាទុកស្ថិតិខែ តម្លៃបង្ហាញក្នុង «បញ្ចូលទិន្នន័យ» និង «របាយការណ៍» ដោយស្វ័យប្រវត្តិ (ស្លាក HRM)។ ការបញ្ចូលដោយដៃនៅតែជំនួសបាន។<br>
                            ត្រីមាស/ឆ្នាំ៖ ចំនួនបុគ្គលិក = ខែចុងក្រោយ • ចលនា = បូកខែ</p>
                        @if ($linkable)
                            @can('update_health_report_settings')
                                <form method="POST" action="{{ route('report.health.hr.link') }}" class="mt-2" onsubmit="return confirm('ភ្ជាប់សូចនាករ HR {{ $kh($linkable) }} ទៅ HRM ដោយអានពីឈ្មោះសូចនាករ?')">
                                    @csrf<button class="rpt-btn rpt-btn-dark" type="submit">✨ ភ្ជាប់ស្វ័យប្រវត្តិ ({{ $kh($linkable) }})</button>
                                </form>
                            @endcan
                        @endif
                    </div>
                </div>
            </aside>
        </div>
    @endif

    {{-- ================= Skill mapping ================= --}}
    @if ($tab === 'skills')
        <form class="rpt-card" method="POST" action="{{ route('report.health.hr.skills') }}">
            @csrf
            <div class="rpt-toolbar">
                <div class="me-auto">
                    <h2>ផ្គូផ្គងជំនាញ</h2>
                    <div class="rpt-sub">ជំនាញដែលសរសេរក្នុងប្រវត្តិរូបមន្ត្រី (អក្សរសរសេរខុសៗគ្នា) → ជំនាញស្តង់ដាររបស់សូចនាករ។ ប្រព័ន្ធបានទាយជូន — សូមពិនិត្យ ហើយរក្សាទុក</div>
                </div>
                @can('update_health_report_settings')<button class="rpt-btn rpt-btn-primary" type="submit">រក្សាទុក</button>@endcan
            </div>
            <div class="rpt-table-wrap">
                <table class="rpt-table hr-table" style="min-width:640px">
                    <thead><tr><th>ជំនាញក្នុងប្រវត្តិរូប</th><th class="text-end">បុគ្គលិកសកម្ម</th><th>ជំនាញស្តង់ដារ</th><th>ស្ថានភាព</th></tr></thead>
                    <tbody>
                        @foreach ($maps as $m)
                            <tr>
                                <td class="rpt-name">{{ $m->skill_text }}</td>
                                <td class="text-end">{{ $kh($m->staff) }}</td>
                                <td>
                                    <select class="rpt-input" name="maps[{{ $m->id }}]" style="min-width:220px">
                                        <option value="">— មិនទាន់ផ្គូផ្គង —</option>
                                        @foreach ($professions as $pr)<option value="{{ $pr->id }}" @selected($m->profession_id == $pr->id)>{{ $pr->name }}</option>@endforeach
                                    </select>
                                </td>
                                <td>
                                    @if (!$m->profession_id)<span class="rpt-badge rpt-badge-warn">មិនទាន់ផ្គូផ្គង</span>
                                    @elseif ($m->auto)<span class="rpt-badge rpt-badge-info">ប្រព័ន្ធទាយ</span>
                                    @else<span class="rpt-badge rpt-badge-ok">បានបញ្ជាក់</span>@endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </form>
    @endif

    {{-- ================= Data quality ================= --}}
    @if ($tab === 'quality')
        @php($counts = $issues->flatMap(fn ($s) => $s['problems'])->countBy())
        <div class="rpt-card">
            <div class="rpt-toolbar">
                <div class="me-auto">
                    <h2>គុណភាពទិន្នន័យ HR</h2>
                    <div class="rpt-sub">បុគ្គលិកសកម្ម {{ $kh($issues->count()) }} នាក់ខ្វះព័ត៌មានដែលស្ថិតិត្រូវការ — ចុចលើស្លាកដើម្បីត្រង</div>
                </div>
                <button type="button" class="rpt-badge rpt-badge-muted hr-chip active" data-filter="">ទាំងអស់ {{ $kh($issues->count()) }}</button>
                @foreach ($counts as $problem => $n)
                    <button type="button" class="rpt-badge rpt-badge-warn hr-chip" data-filter="{{ $problem }}">{{ $problem }} {{ $kh($n) }}</button>
                @endforeach
            </div>

            @if ($canEditStaff && ($counts['ប្រភេទបុគ្គលិក'] ?? 0))
                <form method="POST" action="{{ route('report.health.hr.employee-types') }}" id="frmTypes" class="d-flex flex-wrap gap-2 align-items-center" style="padding:0 20px 16px"
                    onsubmit="const n = Array.from(this.elements).filter((el) => el.name === 'employees[]' && el.checked).length; return n > 0 ? confirm('កំណត់ប្រភេទបុគ្គលិកឲ្យ ' + n + ' នាក់ដែលបានជ្រើស?') : (alert('សូមជ្រើសបុគ្គលិក'), false)">
                    @csrf
                    <b style="font-size:13px">កំណត់ប្រភេទបុគ្គលិកជាក្រុម៖</b>
                    <select class="rpt-input" name="employee_type_id" style="width:auto">
                        @foreach ($types as $t)<option value="{{ $t->id }}" @selected(str_contains($t->name, 'ក្របខ'))>{{ $t->name }}</option>@endforeach
                    </select>
                    <button type="button" class="rpt-btn" id="btnPickGrade" title="ជ្រើសអ្នកគ្មានប្រភេទ ដែលមានឋានន្តរស័ក្តិ (ក/ខ/គ) — ធម្មតាជាមន្ត្រីក្របខ័ណ្ឌ">ជ្រើសអ្នកមានឋានន្តរស័ក្តិ</button>
                    <button type="button" class="rpt-btn" id="btnPickNone">ដកការជ្រើស</button>
                    <button class="rpt-btn rpt-btn-primary" type="submit">កំណត់ឲ្យអ្នកដែលបានជ្រើស (<span id="pickCount">០</span>)</button>
                </form>
            @endif

            <div class="rpt-table-wrap" style="max-height:65vh;overflow-y:auto">
                <table class="rpt-table hr-table">
                    <thead><tr>@if ($canEditStaff)<th style="width:36px"><input type="checkbox" id="chkAll" title="ជ្រើសទាំងអស់ (ដែលកំពុងបង្ហាញ)"></th>@endif<th>ឈ្មោះ</th><th>អង្គភាព</th><th>ជំនាញ</th><th>ឋានន្តរស័ក្តិ</th><th>ខ្វះ</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($issues as $s)
                            @php($noType = in_array('ប្រភេទបុគ្គលិក', $s['problems'], true))
                            <tr data-problems="{{ implode('|', $s['problems']) }}" data-grade="{{ $s['grade'] ? 1 : 0 }}">
                                @if ($canEditStaff)<td>@if ($noType)<input type="checkbox" name="employees[]" value="{{ $s['employee_id'] }}" form="frmTypes">@endif</td>@endif
                                <td class="rpt-name">{{ $s['name'] }}<div class="rpt-sub">{{ $s['staff_no'] }} {{ $s['sex'] ? '• ' . $sexes[$s['sex']] : '' }}</div></td>
                                <td>{{ $units[$s['department_id']] ?? '—' }}<div class="rpt-sub">{{ HrStatistics::WORKPLACES[$s['workplace']] ?? '' }}</div></td>
                                <td>{{ $s['skill_text'] ?? '—' }}</td>
                                <td>{{ $s['grade'] ?: '—' }}</td>
                                <td>@foreach ($s['problems'] as $pb)<span class="rpt-badge rpt-badge-warn me-1">{{ $pb }}</span>@endforeach</td>
                                <td class="text-end">@if ($canEditStaff)<a class="rpt-link" href="{{ route('employees.edit', $s['employee_id']) }}" target="_blank">កែប្រវត្តិរូប ↗</a>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="rpt-empty">✓ ទិន្នន័យបុគ្គលិកគ្រប់គ្រាន់សម្រាប់ស្ថិតិ</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection

@push('rpt_js')
    <script>
        (function () {
            // month picker → one period code
            const y = document.getElementById('selYear'), m = document.getElementById('selMonth'), inp = document.getElementById('inpPeriod');
            const sync = () => { if (inp) inp.value = `${y.value}-${m.value}`; };
            y?.addEventListener('change', sync);
            m?.addEventListener('change', sync);

            // data quality: filter chips, selection helpers
            const rows = Array.from(document.querySelectorAll('tr[data-problems]'));
            const boxes = () => rows.filter((r) => r.style.display !== 'none').map((r) => r.querySelector('input[type=checkbox]')).filter(Boolean);
            const count = () => { const c = document.getElementById('pickCount'); if (c) c.textContent = rows.filter((r) => r.querySelector('input:checked')).length; };
            document.querySelectorAll('.hr-chip').forEach((chip) => chip.addEventListener('click', () => {
                document.querySelectorAll('.hr-chip').forEach((c) => c.classList.toggle('active', c === chip));
                const f = chip.dataset.filter;
                rows.forEach((r) => { r.style.display = !f || r.dataset.problems.split('|').includes(f) ? '' : 'none'; });
            }));
            document.getElementById('chkAll')?.addEventListener('change', (e) => { boxes().forEach((b) => { b.checked = e.target.checked; }); count(); });
            document.getElementById('btnPickGrade')?.addEventListener('click', () => {
                rows.forEach((r) => { const b = r.querySelector('input[type=checkbox]'); if (b) b.checked = r.dataset.grade === '1'; });
                count();
            });
            document.getElementById('btnPickNone')?.addEventListener('click', () => { rows.forEach((r) => { const b = r.querySelector('input[type=checkbox]'); if (b) b.checked = false; }); count(); });
            rows.forEach((r) => r.querySelector('input[type=checkbox]')?.addEventListener('change', count));
        })();
    </script>
@endpush
