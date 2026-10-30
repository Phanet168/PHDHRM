@extends('report::health.layout')
@php($kh = fn ($v) => \Modules\Report\Services\PeriodCode::kh($v))
@php($pct = fn ($n) => $kpis['total'] ? $kh(number_format($n / $kpis['total'] * 100, 1)) . '%' : '—')

@section('rpt_title', 'ផ្ទាំងគ្រប់គ្រង')

@push('rpt_css')
    <style>
        .rpt-kpi { padding: 20px; height: 100%; }
        .rpt-kpi-label { color: var(--rpt-s500); }
        .rpt-kpi-dot { width: 10px; height: 10px; border-radius: 999px; }
        .rpt-kpi-value { font-size: 30px; font-weight: 700; line-height: 1; }
        .rpt-chart { position: relative; height: 256px; border-left: 1px solid var(--rpt-s200); border-bottom: 1px solid var(--rpt-s200); }
        .rpt-chart-grid { position: absolute; inset: 0; display: flex; flex-direction: column; justify-content: space-between; font-size: 12px; color: var(--rpt-s400); }
        .rpt-chart-grid span { border-top: 1px dashed var(--rpt-s100); padding-left: 8px; }
        .rpt-chart-bars { position: absolute; left: 16px; right: 16px; top: 12px; bottom: 0; display: flex; align-items: flex-end; justify-content: space-between; gap: 8px; }
        .rpt-chart-bar { flex: 1; height: 100%; display: flex; align-items: flex-end; }
        .rpt-chart-bar div { position: relative; width: 100%; min-height: 2px; border-radius: 6px 6px 0 0; background: rgba(16,185,129,.9); transition: background .15s, height .3s; }
        .rpt-chart-bar div:hover { background: var(--rpt-e600); }
        .rpt-chart-bar span { display: none; position: absolute; top: -28px; left: 50%; transform: translateX(-50%); background: var(--rpt-s900); color: #fff; font-size: 12px; padding: 2px 8px; border-radius: 4px; white-space: nowrap; }
        .rpt-chart-bar div:hover span { display: block; }
        .rpt-chart-labels { display: flex; justify-content: space-between; gap: 8px; margin: 12px 16px 0; font-size: 10px; color: var(--rpt-s400); text-align: center; }
        .rpt-chart-labels span { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .rpt-activity { display: flex; gap: 12px; }
        .rpt-activity + .rpt-activity { margin-top: 20px; }
        .rpt-activity-icon { display: grid; place-items: center; width: 40px; height: 40px; border-radius: 12px; flex-shrink: 0; }
        .rpt-cta { margin-top: 24px; border-radius: 16px; background: var(--rpt-s900); color: #fff; padding: 24px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px; box-shadow: 0 20px 25px -5px rgba(15,23,42,.1); }
        .rpt-cta .rpt-btn { background: var(--rpt-e500); color: #fff; font-weight: 600; padding: 12px 20px; }
        .rpt-cta .rpt-btn:hover { background: var(--rpt-e400); }
        .rpt-year-btn { position: relative; }
        .rpt-year-btn select { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
    </style>
@endpush

@section('rpt')
    <section class="rpt-title">
        <div>
            <div class="rpt-eyebrow">ទិដ្ឋភាពទូទៅ</div>
            <h1>ផ្ទាំងគ្រប់គ្រង</h1>
            <p>តាមដានស្ថានភាពការងារ និងសូចនាករសុខាភិបាលប្រចាំឆ្នាំ {{ $kh($year) }}</p>
        </div>
        <div class="rpt-actions">
        @if ($periods->isNotEmpty())
            <form method="GET">
                <input type="hidden" name="year" value="{{ $year }}">
                <select class="rpt-select" name="period" onchange="this.form.submit()" aria-label="រយៈពេលសម្រាប់តួលេខ">
                    @foreach ($periods as $p)
                        <option value="{{ $p->code }}" @selected($period && $p->code === $period->code)>{{ $p->label }} ({{ $kh($p->values_count) }})</option>
                    @endforeach
                </select>
            </form>
        @endif
        <form method="GET" class="rpt-year-btn">
            <span class="rpt-btn rpt-btn-outline" style="font-weight:500">
                @include('report::health._icon', ['name' => 'calendar', 'size' => 16])
                ០១ មករា — ៣១ ធ្នូ {{ $kh($year) }}
            </span>
            <select name="year" aria-label="ឆ្នាំ" onchange="this.form.submit()">
                @foreach ($years as $y)
                    <option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>
                @endforeach
            </select>
        </form>
        </div>
    </section>

    <section class="row g-3 mb-4">
        @foreach ([
            ['សូចនាករសរុប', $kpis['total'], 'សកម្ម', 'var(--rpt-e500)', 'rpt-badge-ok'],
            ['បានបញ្ចូល', $kpis['filled'], $pct($kpis['filled']), 'var(--rpt-b500)', 'rpt-badge-info'],
            ['សម្រេចគោលដៅ', $kpis['achieved'], $kpis['with_target'] ? $kh($kpis['achieved']) . ' / ' . $kh($kpis['with_target']) . ' មានគោលដៅ' : 'គ្មានគោលដៅ', 'var(--rpt-a500)', 'rpt-badge-warn'],
            ['បញ្ហាប្រឈម', $kpis['open_issues'], 'ត្រូវពិនិត្យ', 'var(--rpt-r500)', 'rpt-badge-danger'],
        ] as [$label, $value, $note, $dot, $badge])
            <div class="col-sm-6 col-xl-3">
                <article class="rpt-card rpt-kpi">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="rpt-kpi-label">{{ $label }}</div>
                        <span class="rpt-kpi-dot" style="background: {{ $dot }}"></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-end mt-3">
                        <div class="rpt-kpi-value">{{ $kh(str_pad((string) $value, 2, '0', STR_PAD_LEFT)) }}</div>
                        <span class="rpt-badge {{ $badge }}" style="font-weight:500">{{ $note }}</span>
                    </div>
                </article>
            </div>
        @endforeach
    </section>

    <section class="row g-4">
        <div class="col-xl-8">
            <article class="rpt-card rpt-card-pad h-100">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                    <div>
                        <h2>វឌ្ឍនភាពសូចនាករ</h2>
                        <div class="rpt-sub">ភាគរយសូចនាករដែលបានបញ្ចូលទិន្នន័យ តាមរយៈពេល</div>
                    </div>
                    <div class="rpt-seg" id="chartSeg">
                        <button type="button" data-series="month" class="active">ខែ</button>
                        <button type="button" data-series="quarter">ត្រីមាស</button>
                        <button type="button" data-series="year">ឆ្នាំ</button>
                    </div>
                </div>
                <div class="rpt-chart">
                    <div class="rpt-chart-grid"><span>១០០%</span><span>៧៥%</span><span>៥០%</span><span>២៥%</span></div>
                    <div class="rpt-chart-bars" id="chartBars"></div>
                </div>
                <div class="rpt-chart-labels" id="chartLabels"></div>
            </article>
        </div>

        <div class="col-xl-4">
            <article class="rpt-card rpt-card-pad h-100">
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div>
                        <h2>សកម្មភាពថ្មីៗ</h2>
                        <div class="rpt-sub">ការធ្វើបច្ចុប្បន្នភាពចុងក្រោយ</div>
                    </div>
                    <a class="rpt-link" href="{{ route('report.health.issues') }}">មើលទាំងអស់</a>
                </div>
                @forelse ($activity as $a)
                    @php($tone = ['entry' => 'background:var(--rpt-e100);color:var(--rpt-e700)', 'import' => 'background:var(--rpt-b100);color:var(--rpt-b700)', 'issue' => 'background:var(--rpt-a100);color:var(--rpt-a700)'][$a['kind']])
                    <div class="rpt-activity">
                        <div class="rpt-activity-icon" style="{{ $tone }}">@include('report::health._icon', ['name' => $a['kind'] === 'issue' ? 'alert' : 'report', 'size' => 16])</div>
                        <div class="flex-grow-1" style="min-width:0">
                            <div class="text-truncate" style="font-weight:500">{{ $a['title'] }}</div>
                            <div class="rpt-sub" style="margin-top:2px">{{ $a['text'] }}</div>
                            <div style="font-size:11px;color:var(--rpt-s400);margin-top:4px">{{ $a['at']?->locale('km')->diffForHumans() }}</div>
                        </div>
                    </div>
                @empty
                    <div class="rpt-empty">មិនទាន់មានសកម្មភាព</div>
                @endforelse
            </article>
        </div>
    </section>

    <section class="rpt-cta">
        <div>
            <div style="font-size:18px;font-weight:600">ត្រៀមបង្កើតរបាយការណ៍ប្រចាំខែ?</div>
            <div style="font-size:14px;color:var(--rpt-s400);margin-top:4px">
                @if ($period)
                    {{ $period->label }}៖ បានបញ្ចូល {{ $kh($kpis['filled']) }} / {{ $kh($kpis['total']) }} សូចនាករ។
                @else
                    មិនទាន់មានទិន្នន័យសម្រាប់ឆ្នាំ {{ $kh($year) }}។
                @endif
            </div>
        </div>
        <a class="rpt-btn" href="{{ route('report.health.reports', $period ? ['period' => $period->code] : []) }}">
            បង្កើតរបាយការណ៍ @include('report::health._icon', ['name' => 'arrow', 'size' => 16])
        </a>
    </section>
@endsection

@push('rpt_js')
    <script>
        (function () {
            const series = @json($series);
            const bars = document.getElementById('chartBars');
            const labels = document.getElementById('chartLabels');
            function draw(key) {
                bars.innerHTML = series[key].map((p) =>
                    `<div class="rpt-chart-bar"><div style="height:${p.percent}%"><span>${RPT.kh(p.percent)}%</span></div></div>`).join('');
                labels.innerHTML = series[key].map((p) => `<span title="${p.label}">${p.label}</span>`).join('');
            }
            document.getElementById('chartSeg').addEventListener('click', (e) => {
                const btn = e.target.closest('button[data-series]');
                if (!btn) return;
                document.querySelectorAll('#chartSeg button').forEach((b) => b.classList.toggle('active', b === btn));
                draw(btn.dataset.series);
            });
            draw('month');
        })();
    </script>
@endpush
