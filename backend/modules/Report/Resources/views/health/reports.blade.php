@extends('report::health.layout')
@php
    use Modules\Report\Services\PeriodCode;
    $kh = fn ($v) => PeriodCode::kh($v);
    $tagMap = collect(PeriodCode::TYPES)->map(fn ($l, $type) => collect(PeriodCode::tagsFor($type))->map(fn ($t) => [
        'v' => $t,
        'l' => PeriodCode::tagLabel($t) . ' ' . (isset($tagCounts[$t]) ? '(' . PeriodCode::kh($tagCounts[$t]) . ')' : '—'),
    ])->all());
    $query = ['period' => $p['code'], 'q' => request('q')];
@endphp

@section('rpt_title', 'សង្ខេបរបាយការណ៍')

@section('rpt')
    <section class="rpt-title">
        <div>
            <div class="rpt-eyebrow">វិភាគ និងនាំចេញ</div>
            <h1>សង្ខេបរបាយការណ៍</h1>
            <p>ពិនិត្យលទ្ធផលធៀបផែនការ និងនាំចេញរបាយការណ៍សម្រាប់ខេត្ត ឬក្រសួង • {{ $periodLabel }}</p>
        </div>
        <div class="rpt-actions">
            <a class="rpt-btn rpt-btn-outline" href="{{ route('report.health.reports.export', $query) }}">Excel</a>
            @can('read_health_report_settings')
                <a class="rpt-btn rpt-btn-outline" target="_blank" href="{{ route('report.health.reports.print', $query + ['audience' => 'province']) }}">PDF ខេត្ត</a>
                <a class="rpt-btn rpt-btn-dark" target="_blank" href="{{ route('report.health.reports.print', $query + ['audience' => 'ministry']) }}">PDF ក្រសួង</a>
            @endcan
        </div>
    </section>

    <div class="rpt-card">
        <form method="GET" class="rpt-toolbar">
            <select class="rpt-select" name="type" id="selType" aria-label="ប្រភេទរយៈពេល">
                @foreach (PeriodCode::TYPES as $type => $label)
                    <option value="{{ $type }}" @selected($type === $p['type'])>{{ $label }}</option>
                @endforeach
            </select>
            <select class="rpt-select" name="year" aria-label="ឆ្នាំ">
                @foreach ($years as $y)
                    <option value="{{ $y }}" @selected($y == $p['year'])>ឆ្នាំ {{ $kh($y) }}</option>
                @endforeach
            </select>
            <select class="rpt-select" name="tag" id="selTag" aria-label="រយៈពេល">
                @foreach (PeriodCode::tagsFor($p['type']) as $tag)
                    <option value="{{ $tag }}" @selected($tag === $p['tag'])>{{ PeriodCode::tagLabel($tag) }} {{ isset($tagCounts[$tag]) ? '(' . $kh($tagCounts[$tag]) . ')' : '—' }}</option>
                @endforeach
            </select>
            <div class="rpt-search" style="min-width:220px">
                @include('report::health._icon', ['name' => 'search'])
                <input class="rpt-input" type="search" name="q" value="{{ request('q') }}" placeholder="ស្វែងរក...">
            </div>
            <button class="rpt-btn rpt-btn-primary" type="submit" style="padding-left:20px;padding-right:20px">អនុវត្ត</button>
        </form>

        <div class="rpt-table-wrap">
            <table class="rpt-table rpt-cards">
                <thead>
                    <tr><th>សូចនាករ</th><th>តម្លៃ</th><th>គោលដៅ</th><th>សម្រេចបាន</th><th style="min-width:170px">បញ្ហាប្រឈម</th><th style="min-width:170px">សកម្មភាព</th></tr>
                </thead>
                <tbody>
                    @php($dn = 0)
                    @forelse ($groups as $dept)
                        @php($dn++)
                        <tr class="rpt-group-dept"><td colspan="6">ជំពូកទី{{ $kh($dn) }}៖ {{ $dept['name'] }}</td></tr>
                        @php($un = 0)
                        @foreach ($dept['units'] as $unit)
                            @php($un++)
                            <tr class="rpt-group-unit"><td colspan="6">{{ $kh($dn) }}.{{ $kh($un) }} {{ $unit['name'] }}</td></tr>
                            @foreach ($unit['rows'] as $r)
                                @php($issues = $r['actions']->pluck('issue_text')->filter()->unique())
                                @php($acts = $r['actions']->pluck('action_text')->filter()->unique())
                                <tr>
                                    <td class="rpt-name">{{ $r['name'] }}<div class="rpt-sub" title="{{ $r['code'] }}">{{ \Modules\Report\Entities\ReportIndicator::shortCode($r['code']) }}{{ $r['direction'] === 'down' ? ' • ទាបល្អ' : '' }}</div></td>
                                    <td data-label="តម្លៃ">
                                        {{ PeriodCode::khNumber($r['display_value']) }}
                                        @if ($r['computed'] && in_array($src = $r['computed']['source'] ?? null, ['his', 'hrm'], true))
                                            <div><span class="rpt-badge rpt-badge-info" style="font-size:11px;padding:2px 8px" title="{{ $r[$src] }}">{{ strtoupper($src) }}{{ $r['computed']['of'] > 1 ? ' ' . $kh($r['computed']['months']) . '/' . $kh($r['computed']['of']) . ' ខែ' : '' }}</span></div>
                                        @elseif ($r['computed'])
                                            <div><span class="rpt-badge rpt-badge-info" style="font-size:11px;padding:2px 8px" title="{{ \Modules\Report\Entities\ReportIndicator::AGGREGATIONS[$r['aggregation']] ?? '' }}">គណនា {{ $kh($r['computed']['months']) }}/{{ $kh($r['computed']['of']) }} ខែ</span></div>
                                        @endif
                                    </td>
                                    <td data-label="គោលដៅ">{{ PeriodCode::khNumber($r['target']) }}</td>
                                    <td data-label="សម្រេចបាន">
                                        @if ($r['percent'] !== null)
                                            <div class="rpt-progress">
                                                <div class="rpt-progress-track"><div class="rpt-progress-bar {{ $r['percent'] < 100 ? 'below' : '' }}" style="width: {{ min(100, max(0, $r['percent'])) }}%"></div></div>
                                                <span style="font-size:12px">{{ $kh(rtrim(rtrim(number_format($r['percent'], 1), '0'), '.')) }}%</span>
                                            </div>
                                        @else
                                            <span style="color:var(--rpt-s400)">—</span>
                                        @endif
                                    </td>
                                    <td data-label="បញ្ហាប្រឈម" style="color:var(--rpt-s500)">{!! $issues->isNotEmpty() ? $issues->map(fn ($t) => '• ' . e($t))->implode('<br>') : '—' !!}</td>
                                    <td data-label="សកម្មភាព" style="color:var(--rpt-s500)">{!! $acts->isNotEmpty() ? $acts->map(fn ($t) => '• ' . e($t))->implode('<br>') : '—' !!}</td>
                                </tr>
                            @endforeach
                        @endforeach
                    @empty
                        <tr><td colspan="6" class="rpt-empty">គ្មានទិន្នន័យ</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="rpt-foot">
            <span>បានផ្ទុក {{ $kh($rowCount) }} សូចនាករ • បានបញ្ចូល {{ $kh($filledCount) }}@if ($computedCount) • គណនាពីខែ {{ $kh($computedCount) }}@endif</span>
            <span>ធ្វើបច្ចុប្បន្នភាព៖ {{ $lastUpdate ? $kh($lastUpdate->format('d/m/Y H:i')) : '—' }}</span>
        </div>
    </div>
@endsection

@push('rpt_js')
    <script>
        (function () {
            const TAGS = @json($tagMap);
            const selTag = document.getElementById('selTag');
            document.getElementById('selType').addEventListener('change', (e) => {
                selTag.innerHTML = TAGS[e.target.value].map((t) => `<option value="${t.v}">${t.l}</option>`).join('');
            });
        })();
    </script>
@endpush
