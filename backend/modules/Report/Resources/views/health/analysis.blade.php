@extends('report::health.layout')
@php
    use Modules\Report\Services\PeriodCode;
    $kh = fn ($v) => PeriodCode::kh($v);
    $num = fn ($v) => $v === null ? '—' : PeriodCode::kh(number_format((float) $v, floor((float) $v) == (float) $v ? 0 : 2));
@endphp

@section('rpt_title', 'វិភាគទិន្នន័យ')

@push('rpt_css')
    <style>
        .viz-root { --surface-1: #ffffff; --grid: #eef1f4; --text-primary: #0b0b0b; --text-secondary: #52514e;
            --series-1: #2a78d6; --series-2: #eb6834; --seq: #059669; }
        .viz-hero { font-size: 48px; font-weight: 700; line-height: 1; color: var(--text-primary); }
        .viz-legend { display: flex; gap: 16px; font-size: 12px; color: var(--text-secondary); }
        .viz-legend i { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 6px; vertical-align: -1px; }
        /* grouped columns (age × sex) and single-series columns (months) */
        .viz-cols { display: flex; align-items: flex-end; gap: 14px; height: 220px; border-bottom: 1px solid var(--text-secondary); padding: 0 4px;
            background: repeating-linear-gradient(to top, var(--grid) 0 1px, transparent 1px 55px); }
        .viz-col { flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%; justify-content: flex-end; min-width: 0; }
        .viz-pair { display: flex; align-items: flex-end; gap: 2px; height: 100%; }
        .viz-bar { width: 22px; max-width: 24px; border-radius: 4px 4px 0 0; min-height: 1px; cursor: default; transition: opacity .15s; }
        .viz-bar:hover { opacity: .8; }
        .viz-xlabels { display: flex; gap: 14px; padding: 6px 4px 0; }
        .viz-xlabels span { flex: 1; text-align: center; font-size: 11px; color: var(--text-secondary); min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        /* horizontal bars (ranked) */
        .viz-hrow { display: grid; grid-template-columns: minmax(140px, 34%) 1fr 70px; align-items: center; gap: 12px; padding: 4px 0; }
        .viz-hrow .lbl { font-size: 12.5px; color: var(--text-primary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .viz-hrow .lbl small { color: var(--text-secondary); }
        .viz-htrack { height: 18px; }
        .viz-hbar { height: 18px; border-radius: 0 4px 4px 0; background: var(--seq); min-width: 1px; }
        .viz-hrow .val { font-size: 12px; text-align: right; color: var(--text-secondary); }
        .viz-tip { position: fixed; z-index: 2000; pointer-events: none; background: #0f172a; color: #fff; font-size: 12px; padding: 6px 10px; border-radius: 8px; display: none; white-space: nowrap; }
        .viz-table summary { cursor: pointer; font-size: 12px; color: var(--rpt-e700); margin-top: 12px; }
        .viz-table table { width: 100%; font-size: 12px; margin-top: 8px; }
        .viz-table td, .viz-table th { padding: 4px 8px; border-bottom: 1px solid var(--rpt-s100); }
        .viz-table th { color: var(--rpt-s500); font-weight: 500; }
        #elementSearch { max-width: 220px; }
        @media (max-width: 767px) { .viz-cols, .viz-xlabels { gap: 6px; } .viz-bar { width: 12px; } .viz-hero { font-size: 40px; } }
    </style>
@endpush

@section('rpt')
    <section class="rpt-title">
        <div>
            <div class="rpt-eyebrow">ទិន្នន័យ HIS</div>
            <h1>វិភាគទិន្នន័យ</h1>
            <p>មើលធាតុទិន្នន័យមួយតាម អាយុ ភេទ ជំងឺ មូលដ្ឋានសុខាភិបាល និងខែ</p>
        </div>
    </section>

    @if ($empty)
        <div class="rpt-card rpt-empty">
            <div style="font-weight:600;color:var(--rpt-s950)">មិនទាន់មានទិន្នន័យ HIS</div>
            <div class="mt-1">សូមនាំចូលឯកសារ HC1 / HO2 នៅទំព័រ <a class="rpt-link" href="{{ route('report.health.his') }}">ទិន្នន័យ HIS</a> ជាមុនសិន។</div>
        </div>
    @else
        <div class="rpt-card">
            <form method="GET" class="rpt-toolbar">
                <input class="rpt-input" id="elementSearch" type="search" placeholder="ស្វែងរកធាតុទិន្នន័យ..." autocomplete="off">
                <select class="rpt-select" name="element" id="selElement" style="flex:1;min-width:260px" onchange="this.form.submit()">
                    @foreach ($elements as $section => $list)
                        <optgroup label="{{ $section }}">
                            @foreach ($list as $e)
                                <option value="{{ $e->id }}" @selected($e->id === $element->id)>{{ \Illuminate\Support\Str::limit($e->name, 110) }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                <select class="rpt-select" name="year" onchange="this.form.submit()">
                    @foreach ($years as $y)<option value="{{ $y }}" @selected($y == $year)>ឆ្នាំ {{ $kh($y) }}</option>@endforeach
                </select>
                <select class="rpt-select" name="period" onchange="this.form.submit()">
                    @forelse ($periods as $p)
                        <option value="{{ $p->code }}" @selected($period && $p->code === $period->code)>{{ PeriodCode::label($p->code) }}</option>
                    @empty
                        <option value="">គ្មានទិន្នន័យ</option>
                    @endforelse
                </select>
                <select class="rpt-select" name="facility" onchange="this.form.submit()">
                    <option value="">មូលដ្ឋានទាំងអស់</option>
                    @foreach ($facilities as $f)<option value="{{ $f->id }}" @selected($facilityId === $f->id)>{{ $f->name }}</option>@endforeach
                </select>
            </form>
        </div>

        @if (!$data)
            <div class="rpt-card rpt-empty mt-4">គ្មានទិន្នន័យសម្រាប់ឆ្នាំ {{ $kh($year) }} — សូមជ្រើសឆ្នាំផ្សេង</div>
        @else
            <div class="viz-root">
                <div class="row g-4 mt-1">
                    <div class="col-lg-6">
                        <div class="rpt-card rpt-card-pad h-100">
                            <div class="rpt-sub" style="margin:0 0 8px">{{ $element->name }}</div>
                            <div class="viz-hero">{{ $num($data['total']) }}</div>
                            <div class="rpt-sub mt-2">{{ $periodLabel }} • {{ $facilityId ? $facilities->firstWhere('id', $facilityId)?->name : 'មូលដ្ឋានទាំងអស់' }}</div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="rpt-card rpt-card-pad h-100 d-flex gap-4 align-items-center">
                            @php($sexTotal = $data['by_sex']['M'] + $data['by_sex']['F'])
                            @foreach (['M' => ['ប្រុស', 'var(--series-1)'], 'F' => ['ស្រី', 'var(--series-2)']] as $s => [$label, $color])
                                <div class="flex-grow-1">
                                    <div class="rpt-sub" style="margin:0"><i style="display:inline-block;width:10px;height:10px;border-radius:3px;background:{{ $color }};margin-right:6px"></i>{{ $label }}</div>
                                    <div style="font-size:28px;font-weight:700">{{ $num($data['by_sex'][$s]) }}</div>
                                    <div class="rpt-sub" style="margin:0">{{ $sexTotal ? $kh(number_format($data['by_sex'][$s] / $sexTotal * 100, 1)) . '%' : '—' }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                @if ($data['by_age_sex'])
                    @php($maxAge = max(array_map(fn ($a) => max($a['M'], $a['F'], $a['T']), $data['by_age_sex'])) ?: 1)
                    <div class="rpt-card rpt-card-pad mt-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div><h2>តាមក្រុមអាយុ និងភេទ</h2><div class="rpt-sub">{{ $periodLabel }}</div></div>
                            <div class="viz-legend"><span><i style="background:var(--series-1)"></i>ប្រុស</span><span><i style="background:var(--series-2)"></i>ស្រី</span></div>
                        </div>
                        <div class="viz-cols">
                            @foreach ($data['by_age_sex'] as $a)
                                <div class="viz-col"><div class="viz-pair">
                                    <div class="viz-bar" style="background:var(--series-1);height:{{ $a['M'] / $maxAge * 100 }}%" data-tip="{{ $a['label'] }} • ប្រុស: {{ $num($a['M']) }}"></div>
                                    <div class="viz-bar" style="background:var(--series-2);height:{{ $a['F'] / $maxAge * 100 }}%" data-tip="{{ $a['label'] }} • ស្រី: {{ $num($a['F']) }}"></div>
                                </div></div>
                            @endforeach
                        </div>
                        <div class="viz-xlabels">@foreach ($data['by_age_sex'] as $a)<span title="{{ $a['label'] }}">{{ $a['label'] }}</span>@endforeach</div>
                        <details class="viz-table"><summary>មើលជាតារាង</summary>
                            <table><thead><tr><th>ក្រុមអាយុ</th><th class="text-end">ប្រុស</th><th class="text-end">ស្រី</th><th class="text-end">សរុប</th></tr></thead><tbody>
                                @foreach ($data['by_age_sex'] as $a)<tr><td>{{ $a['label'] }}</td><td class="text-end">{{ $num($a['M']) }}</td><td class="text-end">{{ $num($a['F']) }}</td><td class="text-end">{{ $num($a['M'] + $a['F'] + $a['T']) }}</td></tr>@endforeach
                            </tbody></table>
                        </details>
                    </div>
                @endif

                <div class="row g-4 mt-1">
                    @if ($data['by_diagnosis']->isNotEmpty())
                        @php($top = $data['by_diagnosis']->take(10))
                        @php($maxDx = (float) $top->max('v') ?: 1)
                        <div class="col-xl-7">
                            <div class="rpt-card rpt-card-pad h-100">
                                <h2>ជំងឺ ១០ ញឹកញាប់បំផុត</h2>
                                <div class="rpt-sub mb-3">ក្នុងចំណោម {{ $kh($data['by_diagnosis']->count()) }} ជំងឺដែលមានករណី</div>
                                @foreach ($top as $d)
                                    <div class="viz-hrow">
                                        <div class="lbl" title="{{ $d->icd10 }} {{ $d->name }}"><small>{{ $d->icd10 }}</small> {{ $d->name }}</div>
                                        <div class="viz-htrack"><div class="viz-hbar" style="width:{{ $d->v / $maxDx * 100 }}%" data-tip="{{ $d->icd10 }} {{ $d->name }}: {{ $num($d->v) }} (ប្រុស {{ $num($d->m) }} • ស្រី {{ $num($d->fe) }})"></div></div>
                                        <div class="val">{{ $num($d->v) }}</div>
                                    </div>
                                @endforeach
                                <details class="viz-table"><summary>មើលជំងឺទាំងអស់ ({{ $kh($data['by_diagnosis']->count()) }})</summary>
                                    <table><thead><tr><th>ICD-10</th><th>ជំងឺ</th><th>ក្រុម</th><th class="text-end">ប្រុស</th><th class="text-end">ស្រី</th><th class="text-end">សរុប</th></tr></thead><tbody>
                                        @foreach ($data['by_diagnosis'] as $d)<tr><td>{{ $d->icd10 }}</td><td>{{ $d->name }}</td><td style="color:var(--rpt-s500)">{{ $d->group_name }}</td><td class="text-end">{{ $num($d->m) }}</td><td class="text-end">{{ $num($d->fe) }}</td><td class="text-end">{{ $num($d->v) }}</td></tr>@endforeach
                                    </tbody></table>
                                </details>
                            </div>
                        </div>
                    @endif
                    <div class="{{ $data['by_diagnosis']->isNotEmpty() ? 'col-xl-5' : 'col-12' }} d-flex flex-column gap-4">
                        @foreach (['by_facility' => 'តាមមូលដ្ឋានសុខាភិបាល', 'by_option' => 'តាមតំបន់ / ទីលំនៅ / ម្តាយ-កូន'] as $key => $title)
                            @if ($data[$key]->count() > 1 || ($key === 'by_option' && $data[$key]->isNotEmpty()))
                                @php($maxV = (float) $data[$key]->max('v') ?: 1)
                                <div class="rpt-card rpt-card-pad">
                                    <h2 class="mb-3">{{ $title }}</h2>
                                    @foreach ($data[$key] as $r)
                                        <div class="viz-hrow">
                                            <div class="lbl" title="{{ $r->name ?? $r->label }}">{{ $r->name ?? $r->label }}</div>
                                            <div class="viz-htrack"><div class="viz-hbar" style="width:{{ $r->v / $maxV * 100 }}%" data-tip="{{ $r->name ?? $r->label }}: {{ $num($r->v) }}"></div></div>
                                            <div class="val">{{ $num($r->v) }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        @endforeach

                        @php($trendMax = max(array_map(fn ($t) => (float) $t['v'], $data['trend'])) ?: 1)
                        <div class="rpt-card rpt-card-pad">
                            <h2>និន្នាការប្រចាំខែ {{ $kh($year) }}</h2>
                            <div class="rpt-sub mb-3">ខែដែលគ្មានទិន្នន័យ មិនបង្ហាញរបារ</div>
                            <div class="viz-cols" style="height:160px">
                                @foreach ($data['trend'] as $t)
                                    <div class="viz-col">@if ($t['v'] !== null)<div class="viz-bar" style="background:var(--seq);height:{{ $t['v'] / $trendMax * 100 }}%;{{ $period && $period->tag === $t['tag'] ? 'outline:2px solid var(--text-primary);outline-offset:2px' : '' }}" data-tip="{{ $t['label'] }}: {{ $num($t['v']) }}"></div>@endif</div>
                                @endforeach
                            </div>
                            <div class="viz-xlabels">@foreach ($data['trend'] as $t)<span>{{ mb_substr($t['label'], 0, 3) }}</span>@endforeach</div>
                            <details class="viz-table"><summary>មើលជាតារាង</summary>
                                <table><tbody>@foreach ($data['trend'] as $t)<tr><td>{{ $t['label'] }}</td><td class="text-end">{{ $num($t['v']) }}</td></tr>@endforeach</tbody></table>
                            </details>
                        </div>
                    </div>
                </div>
            </div>
            <div class="viz-tip" id="vizTip"></div>
        @endif
    @endif
@endsection

@push('rpt_js')
    <script>
        (function () {
            // per-mark hover tooltip
            const tip = document.getElementById('vizTip');
            if (tip) {
                document.addEventListener('mousemove', (e) => {
                    const mark = e.target.closest('[data-tip]');
                    if (!mark) { tip.style.display = 'none'; return; }
                    tip.textContent = mark.dataset.tip;
                    tip.style.display = 'block';
                    tip.style.left = Math.min(e.clientX + 14, window.innerWidth - tip.offsetWidth - 8) + 'px';
                    tip.style.top = (e.clientY - 36) + 'px';
                });
            }
            // filter the (long) data element list as you type
            const search = document.getElementById('elementSearch');
            const select = document.getElementById('selElement');
            search?.addEventListener('input', () => {
                const q = search.value.trim().toLowerCase();
                select.querySelectorAll('option').forEach((o) => { o.hidden = q !== '' && !o.textContent.toLowerCase().includes(q); });
                select.querySelectorAll('optgroup').forEach((g) => { g.hidden = q !== '' && !g.label.toLowerCase().includes(q) && !g.querySelector('option:not([hidden])'); });
            });
        })();
    </script>
@endpush
