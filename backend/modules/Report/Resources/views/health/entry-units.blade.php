@extends('report::health.layout')
@php
    use Modules\Report\Services\PeriodCode;
    $kh = fn ($v) => PeriodCode::kh($v);
    $units ??= collect();
    $state = fn ($u) => $u['percent'] === 100 ? 'done' : ($u['done'] > 0 ? 'part' : 'none');
    $states = [
        'done' => ['icon' => '✓', 'label' => 'រួចរាល់', 'badge' => 'rpt-badge-ok'],
        'part' => ['icon' => '◐', 'label' => 'កំពុងបញ្ចូល', 'badge' => 'rpt-badge-warn'],
        'none' => ['icon' => '○', 'label' => 'មិនទាន់ចាប់ផ្តើម', 'badge' => 'rpt-badge-muted'],
    ];
    $count = $units->countBy($state);
    $total = $units->sum('total');
    $done = $units->sum('done');
    $pct = $total ? (int) floor($done / $total * 100) : 0;
@endphp

@section('rpt_title', 'បញ្ចូលទិន្នន័យ')

@push('rpt_css')
    <style>
        .mt-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 20px; }
        .mt-stat { display: flex; align-items: center; gap: 14px; padding: 16px 18px; background: #fff; border: 1px solid var(--rpt-s200); border-radius: 16px; cursor: pointer; text-align: left; transition: border-color .15s, box-shadow .15s; }
        .mt-stat:hover { border-color: var(--rpt-s400); }
        .mt-stat.active { border-color: var(--rpt-e600); box-shadow: 0 0 0 3px var(--rpt-e100); }
        .mt-stat-icon { display: grid; place-items: center; width: 42px; height: 42px; border-radius: 12px; font-size: 18px; flex-shrink: 0; }
        .mt-stat b { display: block; font-size: 22px; line-height: 1.2; color: var(--rpt-s950); }
        .mt-stat span { font-size: 12px; color: var(--rpt-s500); }
        .mt-i-all { background: var(--rpt-s100); color: var(--rpt-s700); }
        .mt-i-done { background: var(--rpt-e50); color: var(--rpt-e600); }
        .mt-i-part { background: var(--rpt-a50); color: var(--rpt-a600); }
        .mt-i-none { background: var(--rpt-s50); color: var(--rpt-s400); }

        .mt-tools { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 16px 20px; border-bottom: 1px solid var(--rpt-s100); }
        .mt-overall { display: flex; align-items: center; gap: 10px; margin-left: auto; font-size: 13px; color: var(--rpt-s500); }
        .mt-bar { height: 8px; border-radius: 99px; background: var(--rpt-s100); overflow: hidden; }
        .mt-bar > span { display: block; height: 100%; border-radius: 99px; background: #059669; }

        .mt-tree { padding: 8px 12px 16px; }
        .mt-dept { border-radius: 14px; }
        .mt-dept + .mt-dept { margin-top: 4px; }
        .mt-dept > summary { list-style: none; display: grid; grid-template-columns: 20px 34px minmax(0, 1fr) 120px 200px; align-items: center; gap: 12px; padding: 12px; border-radius: 12px; cursor: pointer; }
        .mt-dept > summary::-webkit-details-marker { display: none; }
        .mt-dept > summary:hover { background: var(--rpt-s50); }
        .mt-chev { color: var(--rpt-s400); transition: transform .15s; text-align: center; }
        .mt-dept[open] > summary .mt-chev { transform: rotate(90deg); }
        .mt-num { display: grid; place-items: center; width: 34px; height: 34px; border-radius: 10px; background: var(--rpt-s950); color: #fff; font-weight: 600; font-size: 13px; }
        .mt-dept-name { font-weight: 600; font-size: 15px; color: var(--rpt-s950); line-height: 1.5; }
        .mt-dept-sub { font-size: 12px; color: var(--rpt-s500); }
        .mt-progress { display: flex; align-items: center; gap: 10px; }
        .mt-progress .mt-bar { flex: 1; }
        .mt-progress small { min-width: 70px; text-align: right; color: var(--rpt-s500); font-size: 12px; white-space: nowrap; }

        .mt-children { position: relative; margin: 0 0 8px 54px; padding-left: 18px; border-left: 2px solid var(--rpt-s200); }
        .mt-unit { position: relative; display: grid; grid-template-columns: 30px minmax(0, 1fr) 120px 200px 110px; align-items: center; gap: 12px; padding: 12px 14px; border-radius: 12px; color: inherit; text-decoration: none; }
        .mt-unit::before { content: ""; position: absolute; left: -18px; top: 50%; width: 14px; border-top: 2px solid var(--rpt-s200); }
        .mt-unit:hover { background: var(--rpt-e50); color: inherit; }
        .mt-unit:hover .mt-go { background: var(--rpt-e600); color: #fff; border-color: var(--rpt-e600); }
        .mt-unit[hidden], .mt-dept[hidden] { display: none; }
        .mt-state { display: grid; place-items: center; width: 30px; height: 30px; border-radius: 50%; font-size: 14px; }
        .mt-state.done { background: var(--rpt-e50); color: var(--rpt-e600); }
        .mt-state.part { background: var(--rpt-a50); color: var(--rpt-a600); }
        .mt-state.none { background: var(--rpt-s100); color: var(--rpt-s400); }
        .mt-unit-name { font-weight: 500; font-size: 14px; color: var(--rpt-s900); line-height: 1.5; }
        .mt-unit-meta { font-size: 12px; color: var(--rpt-s500); }
        .mt-go { justify-self: end; padding: 7px 14px; border: 1px solid var(--rpt-s200); border-radius: 10px; font-size: 13px; font-weight: 500; color: var(--rpt-s700); white-space: nowrap; transition: background .15s, color .15s; }

        @media (max-width: 991.98px) {
            .mt-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .mt-dept > summary { grid-template-columns: 20px 34px minmax(0, 1fr) 90px; }
            .mt-dept > summary .mt-progress { display: none; }
            .mt-unit { grid-template-columns: 30px minmax(0, 1fr) auto; }
            .mt-unit .mt-progress, .mt-unit .mt-badge { display: none; }
            .mt-children { margin-left: 22px; padding-left: 12px; }
            .mt-unit::before { left: -12px; width: 9px; }
            .mt-overall { margin-left: 0; width: 100%; }
        }
    </style>
@endpush

@section('rpt')
    <section class="rpt-title">
        <div>
            <div class="rpt-eyebrow">គ្រប់គ្រងទិន្នន័យ</div>
            <h1>បញ្ចូលទិន្នន័យរបាយការណ៍</h1>
            <p>ជ្រើសផ្នែក ដើម្បីបើកទំព័របញ្ចូលទិន្នន័យ • {{ $periodLabel }}</p>
        </div>
        @if (($isManager ?? false) && $units->isNotEmpty())
            <div class="rpt-actions">
                <a class="rpt-btn rpt-btn-outline" href="{{ route('report.health.entry', ['period' => $p['code'], 'department' => $units->first()['department_id']]) }}" title="សូចនាករគ្រប់ផ្នែកក្នុងជំពូក ក្នុងតារាងតែមួយ">ទិដ្ឋភាពរួមតាមជំពូក</a>
            </div>
        @endif
    </section>

    @if ($units->isEmpty())
        <div class="rpt-card rpt-card-pad rpt-empty">អ្នកមិនទាន់ត្រូវបានកំណត់ឲ្យទទួលខុសត្រូវផ្នែកណាមួយទេ — សូមស្នើអ្នកគ្រប់គ្រងកំណត់ផ្នែកឲ្យអ្នកនៅ «អ្នកប្រើប្រាស់»។</div>
    @else
        @if (!$isManager && !$units->contains('editable', true))
            <div class="rpt-alert rpt-alert-err">អ្នកមិនទាន់ត្រូវបានកំណត់ឲ្យទទួលខុសត្រូវផ្នែកណាមួយទេ — អាចមើលបាន ប៉ុន្តែមិនអាចបញ្ចូល។ សូមស្នើអ្នកគ្រប់គ្រងកំណត់ផ្នែកឲ្យអ្នកនៅ «អ្នកប្រើប្រាស់»។</div>
        @endif
        @if ($locked)
            <div class="rpt-alert rpt-alert-err">{{ $periodLabel }} ត្រូវបានចាក់សោ — មើលបាន ប៉ុន្តែមិនអាចកែប្រែទិន្នន័យទេ។</div>
        @endif

        {{-- the four figures double as filters --}}
        <div class="mt-stats">
            <button type="button" class="mt-stat active" data-filter=""><span class="mt-stat-icon mt-i-all">☰</span><div><b>{{ $kh($units->count()) }}</b><span>ផ្នែកទាំងអស់</span></div></button>
            @foreach ($states as $key => $s)
                <button type="button" class="mt-stat" data-filter="{{ $key }}"><span class="mt-stat-icon mt-i-{{ $key }}">{{ $s['icon'] }}</span><div><b>{{ $kh($count[$key] ?? 0) }}</b><span>{{ $s['label'] }}</span></div></button>
            @endforeach
        </div>

        <div class="rpt-card">
            <form method="GET" class="mt-tools">
                <select class="rpt-select" name="year" aria-label="ឆ្នាំ" onchange="this.form.requestSubmit()">
                    @foreach ($years as $y)<option value="{{ $y }}" @selected($y == $p['year'])>ឆ្នាំ {{ $kh($y) }}</option>@endforeach
                </select>
                <select class="rpt-select" name="tag" id="selTag" aria-label="រយៈពេល">
                    @foreach (PeriodCode::TYPES as $type => $typeLabel)
                        <optgroup label="{{ $typeLabel }}">
                            @foreach (PeriodCode::tagsFor($type) as $tag)
                                <option value="{{ $tag }}" data-type="{{ $type }}" @selected($tag === $p['tag'])>{{ $type === 'month' ? 'ខែ' : '' }}{{ PeriodCode::tagLabel($tag) }} {{ isset($tagCounts[$tag]) ? '(' . $kh($tagCounts[$tag]) . ')' : '' }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                <input type="hidden" name="type" id="inpType" value="{{ $p['type'] }}">
                <div class="rpt-search" style="min-width:240px">
                    @include('report::health._icon', ['name' => 'search'])
                    <input class="rpt-input" id="mtSearch" type="search" placeholder="ស្វែងរកផ្នែក ឬជំពូក..." autocomplete="off">
                </div>
                <button type="button" class="rpt-link" id="mtToggleAll">បិទទាំងអស់</button>
                <div class="mt-overall">
                    <span>សូចនាករមានតម្លៃ <b style="color:var(--rpt-s900)">{{ $kh($done) }}</b>/{{ $kh($total) }}</span>
                    <div class="mt-bar" style="width:140px" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"><span style="width:{{ $pct }}%"></span></div>
                    <b style="color:var(--rpt-s900)">{{ $kh($pct) }}%</b>
                </div>
            </form>

            <div class="mt-tree">
                @foreach ($units->groupBy('department_id')->values() as $i => $deptUnits)
                    @php($dDone = $deptUnits->sum('done'))
                    @php($dTotal = $deptUnits->sum('total'))
                    @php($dPct = $dTotal ? (int) floor($dDone / $dTotal * 100) : 0)
                    <details class="mt-dept" open>
                        <summary>
                            <span class="mt-chev" aria-hidden="true">▸</span>
                            <span class="mt-num">{{ $kh($i + 1) }}</span>
                            <span>
                                <div class="mt-dept-name">{{ $deptUnits->first()['department'] }}</div>
                                <div class="mt-dept-sub">{{ $kh($deptUnits->count()) }} ផ្នែក • {{ $kh($deptUnits->filter(fn ($u) => $u['percent'] === 100)->count()) }} រួចរាល់</div>
                            </span>
                            <span></span>
                            <span class="mt-progress"><span class="mt-bar"><span style="width:{{ $dPct }}%"></span></span><small>{{ $kh($dDone) }}/{{ $kh($dTotal) }}</small></span>
                        </summary>
                        <div class="mt-children">
                            @foreach ($deptUnits as $u)
                                @php($st = $state($u))
                                <a class="mt-unit" href="{{ route('report.health.entry', ['unit' => $u['id'], 'period' => $p['code']]) }}"
                                    data-state="{{ $st }}" data-search="{{ mb_strtolower($u['name'] . ' ' . $u['department']) }}">
                                    <span class="mt-state {{ $st }}" title="{{ $states[$st]['label'] }}">{{ $states[$st]['icon'] }}</span>
                                    <span>
                                        <div class="mt-unit-name">{{ $u['name'] }}</div>
                                        <div class="mt-unit-meta">
                                            {{ $kh($u['total']) }} សូចនាករ
                                            @if ($u['auto']) • ស្វ័យប្រវត្តិ (HIS/HRM) {{ $kh($u['auto']) }}@endif
                                            @if ($u['updated_at']) • កែចុងក្រោយ {{ \Illuminate\Support\Carbon::parse($u['updated_at'])->format('d/m/Y') }}@endif
                                            @if ($u['issues']) • <span style="color:var(--rpt-a700)">⚑ បញ្ហា {{ $kh($u['issues']) }}</span>@endif
                                        </div>
                                    </span>
                                    <span class="mt-badge"><span class="rpt-badge {{ $states[$st]['badge'] }}">{{ $states[$st]['label'] }}</span></span>
                                    <span class="mt-progress"><span class="mt-bar"><span style="width:{{ $u['percent'] }}%"></span></span><small>{{ $kh($u['done']) }}/{{ $kh($u['total']) }}</small></span>
                                    <span class="mt-go">{{ $u['editable'] && !$locked ? 'បញ្ចូល →' : 'មើល →' }}</span>
                                </a>
                            @endforeach
                        </div>
                    </details>
                @endforeach
                <div class="rpt-empty" id="mtNone" hidden>រកមិនឃើញផ្នែក</div>
            </div>
        </div>
    @endif
@endsection

@push('rpt_js')
    <script>
        (function () {
            const tag = document.getElementById('selTag');
            if (!tag) return;
            tag.addEventListener('change', () => {
                document.getElementById('inpType').value = tag.selectedOptions[0].dataset.type;
                tag.form.requestSubmit();
            });

            const search = document.getElementById('mtSearch');
            const depts = Array.from(document.querySelectorAll('.mt-dept'));
            let filter = '';
            const apply = () => {
                const q = search.value.trim().toLowerCase();
                let shown = 0;
                depts.forEach((d) => {
                    const deptName = d.querySelector('.mt-dept-name').textContent.toLowerCase();
                    let any = 0;
                    d.querySelectorAll('.mt-unit').forEach((u) => {
                        const ok = (!q || u.dataset.search.includes(q) || deptName.includes(q)) && (!filter || u.dataset.state === filter);
                        u.hidden = !ok;
                        any += ok ? 1 : 0;
                    });
                    d.hidden = !any;
                    if (any && (q || filter)) d.open = true;
                    shown += any;
                });
                document.getElementById('mtNone').hidden = shown > 0;
            };
            search.addEventListener('input', apply);
            document.querySelectorAll('.mt-stat').forEach((b) => b.addEventListener('click', () => {
                filter = b.dataset.filter;
                document.querySelectorAll('.mt-stat').forEach((x) => x.classList.toggle('active', x === b));
                apply();
            }));

            const toggle = document.getElementById('mtToggleAll');
            toggle.addEventListener('click', () => {
                const open = toggle.textContent.trim() !== 'បិទទាំងអស់';
                depts.forEach((d) => { d.open = open; });
                toggle.textContent = open ? 'បិទទាំងអស់' : 'បើកទាំងអស់';
            });
        })();
    </script>
@endpush
