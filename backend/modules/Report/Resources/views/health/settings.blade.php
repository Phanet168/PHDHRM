@extends('report::health.layout')
@php
    use Modules\Report\Services\PeriodCode;
    $kh = fn ($v) => PeriodCode::kh($v);
    $canManage = auth()->user()->can('update_health_report_settings');
    $canDelete = auth()->user()->can('delete_health_report_settings');
    $tabs = ['indicators' => 'សូចនាករ', 'periods' => 'រយៈពេល', 'mappings' => 'Import Mapping', 'master' => 'នាំចូលទិន្នន័យមេ'];
@endphp

@section('rpt_title', 'ការកំណត់')

@push('rpt_css')
    <style>
        .rpt-map td { padding: 8px 10px; }
        .rpt-map input.rpt-input { padding: 6px 8px; font-size: 12px; border-radius: 8px; min-width: 90px; }
        .rpt-map input.f-formula { min-width: 170px; }
        .rpt-help code { background: var(--rpt-s100); padding: 1px 6px; border-radius: 6px; color: var(--rpt-s900); }
    </style>
@endpush

@section('rpt')
    <section class="rpt-title">
        <div>
            <div class="rpt-eyebrow">ការគ្រប់គ្រងប្រព័ន្ធ</div>
            <h1>ការកំណត់</h1>
            <p>កំណត់សូចនាករ រយៈពេល និងទិន្នន័យមេ</p>
        </div>
        <div class="rpt-seg">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('report.health.settings', $key) }}" class="{{ $tab === $key ? 'active' : '' }}">{{ $label }}</a>
            @endforeach
        </div>
    </section>

    {{-- ================= Indicators ================= --}}
    @if ($tab === 'indicators')
        <div class="rpt-card">
            <form method="GET" class="rpt-toolbar">
                <div class="rpt-search">
                    @include('report::health._icon', ['name' => 'search'])
                    <input class="rpt-input" type="search" name="q" value="{{ request('q') }}" placeholder="ស្វែងរកសូចនាករ ឬប្រភេទ...">
                </div>
                <select class="rpt-select" name="unit">
                    <option value="">ផ្នែកទាំងអស់</option>
                    @foreach ($departments as $d)
                        <optgroup label="{{ $d->name }}">
                            @foreach ($d->units as $u)<option value="{{ $u->id }}" @selected(request('unit') == $u->id)>{{ $u->name }}</option>@endforeach
                        </optgroup>
                    @endforeach
                </select>
                <select class="rpt-select" name="active">
                    <option value="">ទាំងអស់</option>
                    <option value="1" @selected(request('active') === '1')>សកម្ម</option>
                    <option value="0" @selected(request('active') === '0')>បិទ</option>
                </select>
                <button class="rpt-btn rpt-btn-primary" type="submit">អនុវត្ត</button>
                @if ($canManage)
                    <button type="button" class="rpt-btn rpt-btn-dark" data-indicator="{{ json_encode(['active' => true, 'unit_id' => request('unit'), 'direction' => 'up', 'aggregation' => 'none']) }}">បន្ថែមថ្មី</button>
                @endif
            </form>
            <div class="rpt-statbar">
                <span><b>{{ $kh($indicators->total()) }}</b> សូចនាករ</span>
                @if ($canManage)
                    <form method="POST" action="{{ route('report.health.settings.indicators.suggest') }}" onsubmit="return confirm('ណែនាំ «ទិសដៅ» និង «របៀបគណនាត្រីមាស/ឆ្នាំ» តាមឈ្មោះសូចនាករ សម្រាប់សូចនាករដែលមិនទាន់កំណត់? អ្នកអាចកែវិញបានគ្រប់ពេល។')">
                        @csrf
                        <button class="rpt-link" type="submit" title="កំណត់ ទិសដៅ (ខ្ពស់ល្អ/ទាបល្អ) និងរបៀបគណនាត្រីមាស/ឆ្នាំពីខែ តាមឈ្មោះសូចនាករ">✨ ណែនាំការកំណត់ស្វ័យប្រវត្តិ</button>
                    </form>
                @endif
            </div>
            <div class="rpt-table-wrap">
                <table class="rpt-table">
                    <thead><tr><th>ID</th><th>សូចនាករ</th><th>ប្រភេទ</th><th>ផ្នែក</th><th>ទិសដៅ / គណនា</th><th>អ្នកទទួលខុសត្រូវ</th><th>ស្ថានភាព</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($indicators as $i)
                            <tr>
                                <td class="rpt-id" title="{{ $i->code }}">{{ \Modules\Report\Entities\ReportIndicator::shortCode($i->code) }}</td>
                                <td class="rpt-name" style="max-width:420px">{{ $i->name }}
                                    @if ($i->his_element_id)
                                        <div><span class="rpt-badge rpt-badge-info" style="font-size:11px;padding:2px 8px;white-space:normal" title="តម្លៃយកពីទិន្នន័យ HIS">HIS · {{ $i->hisLabel() }}</span></div>
                                    @endif
                                    @if ($i->hrm_measure)
                                        <div><span class="rpt-badge rpt-badge-ok" style="font-size:11px;padding:2px 8px;white-space:normal" title="តម្លៃយកពីស្ថិតិ HRM ប្រចាំខែ">HRM · {{ $i->hrmLabel() }}</span></div>
                                    @endif
                                </td>
                                <td style="color:var(--rpt-s500);max-width:220px">{{ $i->indicator_type ?: '—' }}</td>
                                <td>{{ $i->unit?->name }}<div class="rpt-sub">{{ $i->department?->name }}</div></td>
                                <td style="white-space:nowrap">
                                    <span class="rpt-badge {{ $i->direction === 'down' ? 'rpt-badge-warn' : 'rpt-badge-muted' }}">{{ $i->direction === 'down' ? '↓' : '↑' }} {{ \Modules\Report\Entities\ReportIndicator::DIRECTIONS[$i->direction] ?? '' }}</span>
                                    <div class="rpt-sub">{{ \Modules\Report\Entities\ReportIndicator::AGGREGATIONS[$i->aggregation] ?? '' }}</div>
                                </td>
                                <td>{{ $i->owner?->full_name ?? '—' }}</td>
                                <td><span class="rpt-badge {{ $i->active ? 'rpt-badge-ok' : 'rpt-badge-muted' }}">{{ $i->active ? 'សកម្ម' : 'បិទ' }}</span></td>
                                <td class="text-end" style="white-space:nowrap">
                                    @if ($canManage)
                                        <button type="button" class="rpt-link" data-indicator="{{ json_encode($i->only(['id', 'code', 'name', 'indicator_type', 'direction', 'aggregation', 'unit_id', 'owner_user_id', 'sort_order', 'active', 'his_element_id', 'his_sex', 'his_age_group_id', 'his_diagnosis_id', 'his_option_id', 'hrm_measure', 'hrm_sex', 'hrm_profession_id', 'hrm_employment', 'hrm_workplace']) + ['his_element_name' => $i->hisElement ? $i->hisElement->code . ' · ' . $i->hisElement->name : null]) }}">កែ</button>
                                    @endif
                                    @if ($canDelete)
                                        <form method="POST" action="{{ route('report.health.settings.indicators.destroy', $i) }}" class="d-inline ms-2" onsubmit="return confirm('លុបសូចនាករ {{ $i->code }}?')">@csrf @method('DELETE')<button class="rpt-link rpt-link-danger">លុប</button></form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="rpt-empty">គ្មានសូចនាករ</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($indicators->hasPages())<div class="rpt-foot">{{ $indicators->links() }}</div>@endif
        </div>

        @if ($canManage)
            <datalist id="indicatorTypes">@foreach ($types as $t)<option value="{{ $t }}">@endforeach</datalist>
            <div class="modal fade phd-rpt-modal" id="mdlIndicator" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <form class="modal-content phd-rpt" method="POST" action="{{ route('report.health.settings.indicators.save') }}">
                        @csrf
                        <input type="hidden" name="id">
                        <div class="modal-header"><h5 class="modal-title" style="font-weight:600">សូចនាករ</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="បិទ"></button></div>
                        <div class="modal-body row g-3">
                            <div class="col-md-3"><label class="rpt-label">Indicator ID</label><input class="rpt-input" name="code" maxlength="64" placeholder="ស្វ័យប្រវត្តិ ({{ $nextCode }})"></div>
                            <div class="col-md-9"><label class="rpt-label">ឈ្មោះសូចនាករ</label><input class="rpt-input" name="name" required></div>
                            <div class="col-md-6"><label class="rpt-label">ប្រភេទ</label><input class="rpt-input" name="indicator_type" list="indicatorTypes"></div>
                            <div class="col-md-6"><label class="rpt-label">ផ្នែក</label>
                                <select class="rpt-input" name="unit_id" required>
                                    @foreach ($departments as $d)
                                        <optgroup label="{{ $d->name }}">@foreach ($d->units as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</optgroup>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6"><label class="rpt-label">អ្នកទទួលខុសត្រូវ (អាចបញ្ចូលទិន្នន័យ)</label>
                                <select class="rpt-input" name="owner_user_id">
                                    <option value="">—</option>
                                    @foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->full_name }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-md-6"><label class="rpt-label">ទិសដៅ</label>
                                <select class="rpt-input" name="direction">
                                    <option value="up">↑ ខ្ពស់ល្អ (ឧ. អត្រាគ្របដណ្តប់)</option>
                                    <option value="down">↓ ទាបល្អ (ឧ. ចំនួនស្លាប់ ដាច់ស្តុក)</option>
                                </select>
                            </div>
                            <div class="col-md-6"><label class="rpt-label">គណនាត្រីមាស / ឆមាស / ឆ្នាំ ពីខែ</label>
                                <select class="rpt-input" name="aggregation">
                                    <option value="none">មិនគណនា (បញ្ចូលដោយដៃ)</option>
                                    <option value="sum">បូកខែ — ចំនួនករណី សេវា</option>
                                    <option value="avg">មធ្យមខែ — អត្រា ភាគរយ</option>
                                    <option value="last">ខែចុងក្រោយ — ចំនួនគ្រែ បុគ្គលិក</option>
                                </select>
                            </div>
                            <div class="col-md-3"><label class="rpt-label">លំដាប់</label><input class="rpt-input" type="number" min="0" name="sort_order" value="0"></div>
                            <div class="col-md-3 d-flex align-items-end"><label class="d-flex gap-2 align-items-center"><input type="checkbox" name="active" value="1" checked> សកម្ម</label></div>

                            <div class="col-12"><hr class="my-1"><div style="font-weight:600">ភ្ជាប់ទិន្នន័យ HIS <span class="rpt-sub" style="display:inline;font-weight:400">— បើភ្ជាប់ តម្លៃនឹងយកពីឯកសារ HIS (HC1/HO2) ដោយស្វ័យប្រវត្តិ បើគ្មានការបញ្ចូលដោយដៃ</span></div></div>
                            <div class="col-12">
                                <input type="hidden" name="his_element_id">
                                <label class="rpt-label">ធាតុទិន្នន័យ HIS</label>
                                <div class="d-flex gap-2">
                                    <input class="rpt-input" id="hisSearch" placeholder="ស្វែងរកឈ្មោះ ឬ code ធាតុទិន្នន័យ…" autocomplete="off">
                                    <button type="button" class="rpt-btn" id="hisClear" title="ដកការភ្ជាប់">ដក</button>
                                </div>
                                <div class="rpt-sub mt-1" id="hisChosen"></div>
                                <div id="hisResults" class="mt-1" style="max-height:220px;overflow:auto"></div>
                            </div>
                            <div class="col-md-3"><label class="rpt-label">ភេទ</label>
                                <select class="rpt-input" name="his_sex"><option value="">ទាំងអស់</option>@foreach (\Modules\Report\Entities\ReportFact::SEXES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                            </div>
                            <div class="col-md-3"><label class="rpt-label">ក្រុមអាយុ</label>
                                <select class="rpt-input" name="his_age_group_id"><option value="">ទាំងអស់</option>@foreach ($his['ages'] as $a)<option value="{{ $a->id }}">{{ $a->label }}</option>@endforeach</select>
                            </div>
                            <div class="col-md-3"><label class="rpt-label">រោគវិនិច្ឆ័យ (ICD-10)</label>
                                <select class="rpt-input" name="his_diagnosis_id"><option value="">ទាំងអស់</option>@foreach ($his['diagnoses'] as $dg)<option value="{{ $dg->id }}">{{ $dg->icd10 }} {{ str($dg->name)->limit(40) }}</option>@endforeach</select>
                            </div>
                            <div class="col-md-3"><label class="rpt-label">ជម្រើស (តំបន់/ទីលំនៅ…)</label>
                                <select class="rpt-input" name="his_option_id"><option value="">ទាំងអស់</option>@foreach ($his['options'] as $o)<option value="{{ $o->id }}">{{ $o->label }}</option>@endforeach</select>
                            </div>

                            <div class="col-12"><hr class="my-1"><div style="font-weight:600">ភ្ជាប់ទិន្នន័យ HRM <span class="rpt-sub" style="display:inline;font-weight:400">— សម្រាប់សូចនាករធនធានមនុស្ស៖ តម្លៃយកពីស្ថិតិបុគ្គលិកប្រចាំខែ (ទិន្នន័យ HRM)</span></div></div>
                            <div class="col-md-4"><label class="rpt-label">រាប់អ្វី</label>
                                <select class="rpt-input" name="hrm_measure"><option value="">— មិនភ្ជាប់ —</option>@foreach (\Modules\Report\Services\Hr\HrStatistics::MEASURES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                            </div>
                            <div class="col-md-4"><label class="rpt-label">ជំនាញ</label>
                                <select class="rpt-input" name="hrm_profession_id"><option value="">ទាំងអស់</option>@foreach ($professions as $pr)<option value="{{ $pr->id }}">{{ $pr->name }}</option>@endforeach</select>
                            </div>
                            <div class="col-md-4"><label class="rpt-label">ភេទ</label>
                                <select class="rpt-input" name="hrm_sex"><option value="">ទាំងអស់</option>@foreach (\Modules\Report\Entities\ReportFact::SEXES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                            </div>
                            <div class="col-md-6"><label class="rpt-label">ប្រភេទបុគ្គលិក</label>
                                <select class="rpt-input" name="hrm_employment"><option value="">ទាំងអស់</option>@foreach (\Modules\Report\Services\Hr\HrStatistics::EMPLOYMENT_FILTERS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                            </div>
                            <div class="col-md-6"><label class="rpt-label">កន្លែងការងារ</label>
                                <select class="rpt-input" name="hrm_workplace"><option value="">ទាំងអស់</option>@foreach (\Modules\Report\Services\Hr\HrStatistics::WORKPLACES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                            </div>
                        </div>
                        <div class="modal-footer"><button class="rpt-btn rpt-btn-primary" type="submit">រក្សាទុក</button></div>
                    </form>
                </div>
            </div>
        @endif
    @endif

    {{-- ================= Periods ================= --}}
    @if ($tab === 'periods')
        <div class="rpt-card">
            <div class="rpt-toolbar">
                <form method="GET" class="d-flex gap-2 align-items-center me-auto">
                    <select class="rpt-select" name="year" onchange="this.form.submit()">
                        @foreach ($years as $y)<option value="{{ $y }}" @selected($y == $year)>ឆ្នាំ {{ $kh($y) }}</option>@endforeach
                    </select>
                </form>
                @if ($canManage)
                    <form method="POST" action="{{ route('report.health.settings.periods.generate') }}">
                        @csrf <input type="hidden" name="year" value="{{ $year }}">
                        <button class="rpt-btn rpt-btn-primary" type="submit">បង្កើតរយៈពេលទាំងអស់សម្រាប់ឆ្នាំ {{ $kh($year) }}</button>
                    </form>
                @endif
            </div>
            <div class="rpt-table-wrap">
                <table class="rpt-table">
                    <thead><tr><th>Period ID</th><th>រយៈពេល</th><th>ឈ្មោះបង្ហាញ</th><th class="text-end">តម្លៃបានបញ្ចូល</th><th>ចាក់សោ</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($periods as $period)
                            <tr>
                                <td class="rpt-id">{{ $period->code }}</td>
                                <td>{{ PeriodCode::label($period->code) }}</td>
                                <td colspan="{{ $canManage ? 1 : 1 }}">
                                    @if ($canManage)
                                        <form method="POST" action="{{ route('report.health.settings.periods.update', $period) }}" class="d-flex gap-2 align-items-center" id="per{{ $period->id }}">
                                            @csrf @method('PUT')
                                            <input class="rpt-input" name="name" value="{{ $period->name }}" placeholder="{{ PeriodCode::label($period->code) }}" style="max-width:260px">
                                        </form>
                                    @else
                                        {{ $period->name ?: '—' }}
                                    @endif
                                </td>
                                <td class="text-end">{{ $kh($period->values_count) }}</td>
                                <td>
                                    @if ($canManage)
                                        <label class="d-flex gap-2 align-items-center mb-0"><input type="checkbox" name="locked" value="1" form="per{{ $period->id }}" @checked($period->locked)> ចាក់សោ</label>
                                    @else
                                        {{ $period->locked ? 'ចាក់សោ' : '—' }}
                                    @endif
                                </td>
                                <td class="text-end">@if ($canManage)<button class="rpt-link" type="submit" form="per{{ $period->id }}">រក្សាទុក</button>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="rpt-empty">មិនទាន់មានរយៈពេលសម្រាប់ឆ្នាំនេះ — រយៈពេលនឹងត្រូវបង្កើតដោយស្វ័យប្រវត្តិពេលបញ្ចូលទិន្នន័យ ឬចុចប៊ូតុងខាងលើ</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ================= Import mappings ================= --}}
    @if ($tab === 'mappings')
        <div class="row g-4 mb-4">
            <div class="col-xl-7">
                <div class="rpt-card rpt-card-pad h-100 rpt-help">
                    <h3>របៀបកំណត់ Mapping</h3>
                    <div class="rpt-sub" style="font-size:13px;line-height:1.9">
                        សូចនាករនីមួយៗអានតម្លៃ <b>HC</b> ពីឯកសារមណ្ឌលសុខភាព និង <b>HOSP</b> ពីឯកសារមន្ទីរពេទ្យ (Sheet + Cell ឬ Range ដូចជា <code>O8</code>, <code>O8:O12</code>)។
                        បើមាន Formula នឹងប្រើ Formula ជំនួស Cell៖ <code>HC("Sheet!A1")</code>, <code>HOSP("Sheet!B2:B9")</code>, <code>NUM(..)</code>, <code>SUM/AVG/MIN/MAX(..)</code>, <code>ROUND(x,2)</code>, <code>+ - * / ( )</code>។
                        Result formula គណនាសរុបពី <code>HC</code> និង <code>HOSP</code> (ទទេ = <code>HC+HOSP</code>) ឧ. <code>ROUND(HC/HOSP*100,1)</code>។
                    </div>
                </div>
            </div>
            <div class="col-xl-5">
                <form class="rpt-card rpt-card-pad h-100" method="POST" action="{{ route('report.health.settings.mappings.test') }}" enctype="multipart/form-data">
                    @csrf
                    <h3>សាកល្បង Mapping</h3>
                    <div class="rpt-sub mb-3">ជ្រើសឯកសារគំរូ ដើម្បីមើលតម្លៃដែលទទួលបាន (មិននាំចូលទេ)</div>
                    <div class="row g-2">
                        <div class="col-6"><label class="rpt-label">HC</label><input class="rpt-input" type="file" name="file_hc" accept=".xlsx,.xls"></div>
                        <div class="col-6"><label class="rpt-label">HOSP</label><input class="rpt-input" type="file" name="file_hosp" accept=".xlsx,.xls"></div>
                    </div>
                    <button class="rpt-btn rpt-btn-dark mt-3" type="submit" @disabled(!$canManage)>Test</button>
                </form>
            </div>
        </div>

        @if ($test && $test['warnings'])
            <div class="rpt-alert rpt-alert-err">{!! collect($test['warnings'])->take(15)->map(fn ($w) => e($w))->implode('<br>') !!}</div>
        @endif

        <div class="rpt-card">
            <div class="rpt-toolbar">
                <form method="GET" class="rpt-search">
                    @include('report::health._icon', ['name' => 'search'])
                    <input class="rpt-input" type="search" name="q" value="{{ request('q') }}" placeholder="ស្វែងរក mapping...">
                </form>
                @if ($canManage)
                    <input class="rpt-input" style="max-width:160px" id="globalHc" placeholder="HC sheet (global)">
                    <input class="rpt-input" style="max-width:160px" id="globalHosp" placeholder="HOSP sheet (global)">
                    <button type="button" class="rpt-btn rpt-btn-outline" id="btnUseGlobal" title="បំពេញ Sheet ដែលទទេ">Use Global</button>
                    <button type="submit" form="frmMappings" class="rpt-btn rpt-btn-primary">រក្សាទុកទាំងអស់</button>
                @endif
            </div>
            <form method="POST" action="{{ route('report.health.settings.mappings.save') }}" id="frmMappings">
                @csrf
                <div class="rpt-table-wrap" style="max-height:65vh;overflow-y:auto">
                    <table class="rpt-table rpt-map" style="min-width:1400px">
                        <thead><tr><th>ID</th><th>សូចនាករ</th><th>HC sheet</th><th>HC cell</th><th>HC formula</th><th>HOSP sheet</th><th>HOSP cell</th><th>HOSP formula</th><th>Result formula</th><th>សកម្ម</th>@if ($test)<th>លទ្ធផល Test</th>@endif<th></th></tr></thead>
                        <tbody>
                            @forelse ($mappings as $m)
                                @php($k = "rows[{$m->indicator_id}]")
                                <tr>
                                    <td class="rpt-id" title="{{ $m->indicator?->code }}">{{ \Modules\Report\Entities\ReportIndicator::shortCode($m->indicator?->code) }}</td>
                                    <td style="max-width:260px;font-size:12px">{{ $m->indicator?->name }}</td>
                                    <td><input class="rpt-input f-hc-sheet" name="{{ $k }}[hc_sheet]" value="{{ $m->hc_sheet }}" @disabled(!$canManage)></td>
                                    <td><input class="rpt-input" name="{{ $k }}[hc_cell]" value="{{ $m->hc_cell }}" placeholder="O8" @disabled(!$canManage)></td>
                                    <td><input class="rpt-input f-formula" name="{{ $k }}[hc_formula]" value="{{ $m->hc_formula }}" placeholder='NUM(HC("S!A1"))' @disabled(!$canManage)></td>
                                    <td><input class="rpt-input f-hosp-sheet" name="{{ $k }}[hosp_sheet]" value="{{ $m->hosp_sheet }}" @disabled(!$canManage)></td>
                                    <td><input class="rpt-input" name="{{ $k }}[hosp_cell]" value="{{ $m->hosp_cell }}" placeholder="O9" @disabled(!$canManage)></td>
                                    <td><input class="rpt-input f-formula" name="{{ $k }}[hosp_formula]" value="{{ $m->hosp_formula }}" placeholder='NUM(HOSP("S!B1"))' @disabled(!$canManage)></td>
                                    <td><input class="rpt-input f-formula" name="{{ $k }}[result_formula]" value="{{ $m->result_formula }}" placeholder="HC+HOSP" @disabled(!$canManage)></td>
                                    <td class="text-center"><input type="checkbox" name="{{ $k }}[active]" value="1" @checked($m->active) @disabled(!$canManage)></td>
                                    @if ($test)
                                        @php($t = $test['rows'][$m->indicator_id] ?? null)
                                        <td style="font-size:12px;white-space:nowrap">
                                            @if ($t)
                                                HC {{ PeriodCode::khNumber($t['hc']) }} • HOSP {{ PeriodCode::khNumber($t['hosp']) }}<br><b>= {{ PeriodCode::khNumber($t['total']) }}</b>
                                                @if ($t['notes'])<div style="color:var(--rpt-a700)">{{ implode('; ', $t['notes']) }}</div>@endif
                                            @else
                                                <span style="color:var(--rpt-s400)">(បិទ)</span>
                                            @endif
                                        </td>
                                    @endif
                                    <td>
                                        @if ($canDelete)
                                            <button type="submit" form="delMap{{ $m->id }}" class="rpt-link rpt-link-danger" onclick="return confirm('លុប mapping {{ $m->indicator?->code }}?')">លុប</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="12" class="rpt-empty">មិនទាន់មាន Mapping</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </form>
            @foreach ($mappings as $m)
                <form method="POST" action="{{ route('report.health.settings.mappings.destroy', $m) }}" id="delMap{{ $m->id }}" hidden>@csrf @method('DELETE')</form>
            @endforeach
            @if ($canManage)
                <form method="POST" action="{{ route('report.health.settings.mappings.save') }}" class="rpt-foot" id="frmAddMap">
                    @csrf
                    <span>សូចនាករមិនទាន់មាន Mapping៖ {{ $kh($unmapped->count()) }}</span>
                    <div class="d-flex gap-2">
                        <select class="rpt-select" id="selUnmapped" style="max-width:420px">
                            @foreach ($unmapped as $i)<option value="{{ $i->id }}">{{ \Modules\Report\Entities\ReportIndicator::shortCode($i->code) }} —{{ \Illuminate\Support\Str::limit($i->name, 70) }}</option>@endforeach
                        </select>
                        <input type="hidden" id="addMapActive" value="1">
                        <button class="rpt-btn rpt-btn-outline" type="submit" @disabled($unmapped->isEmpty())>បន្ថែម</button>
                    </div>
                </form>
            @endif
        </div>
    @endif

    {{-- ================= Master data ================= --}}
    @if ($tab === 'master')
        <div class="row g-4">
            <div class="col-xl-7">
                <div class="rpt-card rpt-card-pad">
                    <h3>នាំចូលទិន្នន័យពី PHDReport (Firebase)</h3>
                    <div class="rpt-sub mb-4" style="font-size:13px;line-height:1.8">
                        Upload ឯកសារ JSON ដែល export ពី Firestore៖ បញ្ជីសូចនាករ (ដូច <code>indicator.json</code>) ឬ object ដែលមាន collections
                        <code>departments</code>, <code>units</code>, <code>indicators</code>, <code>periods</code>, <code>reports</code>, <code>actions</code>, <code>import_mappings</code>។
                        ទិន្នន័យត្រូវ upsert តាម ID ដើម ដូច្នេះអាចនាំចូលម្តងទៀតដោយសុវត្ថិភាព។ (Command: <code>php artisan report:import-phdreport file.json</code>)
                    </div>
                    @if ($canManage)
                        <form method="POST" action="{{ route('report.health.settings.master.import') }}" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-center mb-3">
                            @csrf <input type="hidden" name="source" value="upload">
                            <input class="rpt-input" style="max-width:360px" type="file" name="file" accept=".json,application/json" required>
                            <button class="rpt-btn rpt-btn-primary" type="submit">នាំចូល JSON</button>
                        </form>
                        @if ($bundled)
                            <form method="POST" action="{{ route('report.health.settings.master.import') }}" onsubmit="return confirm('នាំចូលបញ្ជីសូចនាករ PHDReport ដើម?')">
                                @csrf <input type="hidden" name="source" value="bundled">
                                <button class="rpt-btn rpt-btn-outline" type="submit">នាំចូលបញ្ជីសូចនាករ PHDReport ដើម (indicator.json)</button>
                            </form>
                        @endif
                    @endif
                </div>
            </div>
            <div class="col-xl-5">
                <div class="rpt-card rpt-card-pad">
                    <h3>ទិន្នន័យបច្ចុប្បន្ន</h3>
                    @foreach (['departments' => 'ជំពូក', 'units' => 'ផ្នែក', 'indicators' => 'សូចនាករ', 'mappings' => 'Import mapping'] as $key => $label)
                        <div class="d-flex justify-content-between mt-3"><span style="color:var(--rpt-s500)">{{ $label }}</span><b>{{ $kh($counts[$key]) }}</b></div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
@endsection

@push('rpt_js')
    <script>
        (function () {
            const mdl = document.getElementById('mdlIndicator');
            if (mdl) {
                const modal = new bootstrap.Modal(mdl);
                const form = mdl.querySelector('form');
                document.querySelectorAll('[data-indicator]').forEach((btn) => btn.addEventListener('click', () => {
                    const d = JSON.parse(btn.dataset.indicator);
                    form.reset();
                    ['id', 'code', 'name', 'indicator_type', 'owner_user_id', 'sort_order'].forEach((f) => { form[f].value = d[f] ?? (f === 'sort_order' ? 0 : ''); });
                    form.direction.value = d.direction || 'up';
                    form.aggregation.value = d.aggregation || 'none';
                    if (d.unit_id) form.unit_id.value = d.unit_id;
                    form.active.checked = d.active !== false;
                    ['his_element_id', 'his_sex', 'his_age_group_id', 'his_diagnosis_id', 'his_option_id', 'hrm_measure', 'hrm_sex', 'hrm_profession_id', 'hrm_employment', 'hrm_workplace'].forEach((f) => { form[f].value = d[f] ?? ''; });
                    his.show(d.his_element_name);
                    if (!d.his_element_id && d.name) his.suggest(d.name);
                    modal.show();
                }));

                // HIS element picker: search as you type, or suggestions from the indicator name
                const his = (() => {
                    const search = document.getElementById('hisSearch'), results = document.getElementById('hisResults'), chosen = document.getElementById('hisChosen');
                    const url = @json(route('report.health.settings.his-elements'));
                    const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
                    let timer;
                    const show = (label) => {
                        chosen.innerHTML = form.his_element_id.value ? `ភ្ជាប់ជាមួយ៖ <b>${esc(label)}</b>` : 'មិនទាន់ភ្ជាប់ — បញ្ចូលដោយដៃ ឬនាំចូល Excel';
                        results.innerHTML = '';
                        search.value = '';
                    };
                    const render = (items, title) => {
                        results.innerHTML = items.length
                            ? `<div class="rpt-sub">${title}</div>` + items.map((e) => `<button type="button" class="rpt-link d-block text-start w-100 py-1" data-id="${e.id}" data-label="${esc(e.code + ' · ' + e.name)}">${esc(e.code)} · ${esc(e.name)} <span class="rpt-sub" style="display:inline">${esc(e.section || '')} — ${e.facts_count} តម្លៃ${e.score ? ' • ' + e.score + '%' : ''}</span></button>`).join('')
                            : (title ? '<div class="rpt-sub">រកមិនឃើញ</div>' : '');
                    };
                    const fetchItems = (params, title, onData) => fetch(url + '?' + new URLSearchParams(params), { headers: { Accept: 'application/json' } })
                        .then((r) => r.json()).then((j) => { render(j.items, title); onData?.(j); }).catch(() => { results.innerHTML = ''; });
                    search.addEventListener('input', () => {
                        clearTimeout(timer);
                        const q = search.value.trim();
                        if (q.length < 2) { results.innerHTML = ''; return; }
                        timer = setTimeout(() => fetchItems({ q }, 'លទ្ធផលស្វែងរក'), 250);
                    });
                    results.addEventListener('click', (e) => {
                        const b = e.target.closest('[data-id]');
                        if (!b) return;
                        form.his_element_id.value = b.dataset.id;
                        show(b.dataset.label);
                    });
                    document.getElementById('hisClear').addEventListener('click', () => {
                        ['his_element_id', 'his_sex', 'his_age_group_id', 'his_diagnosis_id', 'his_option_id'].forEach((f) => { form[f].value = ''; });
                        show();
                    });
                    return {
                        show,
                        suggest: (name) => fetchItems({ name }, 'ណែនាំតាមឈ្មោះសូចនាករ (ចុចដើម្បីភ្ជាប់)', (j) => { if (j.sex && !form.his_sex.value) form.his_sex.value = j.sex; }),
                    };
                })();
            }
            document.getElementById('btnUseGlobal')?.addEventListener('click', () => {
                const hc = document.getElementById('globalHc').value.trim(), hosp = document.getElementById('globalHosp').value.trim();
                if (hc) document.querySelectorAll('.f-hc-sheet').forEach((i) => { if (!i.value) i.value = hc; });
                if (hosp) document.querySelectorAll('.f-hosp-sheet').forEach((i) => { if (!i.value) i.value = hosp; });
            });
            document.getElementById('frmAddMap')?.addEventListener('submit', (e) => {
                const id = document.getElementById('selUnmapped').value;
                document.getElementById('addMapActive').name = `rows[${id}][active]`;
            });
        })();
    </script>
@endpush
