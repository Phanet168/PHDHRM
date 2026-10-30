@php($kh = fn ($v) => \Modules\Report\Services\PeriodCode::kh($v))
@php($n = fn ($v) => \Modules\Report\Services\PeriodCode::khNumber($v))
<!doctype html>
<html lang="km">
<head>
    @include('report::health.print._head')
    <title>របាយការណ៍សង្ខេប • {{ $periodLabel }}</title>
</head>
<body>
    <button class="noprint" onclick="window.print()">បោះពុម្ព</button>
    <div class="head">
        <div class="kingdom">ព្រះរាជាណាចក្រកម្ពុជា</div>
        <div class="motto">ជាតិ សាសនា ព្រះមហាក្សត្រ</div>
    </div>
    <div class="org">មន្ទីរសុខាភិបាលខេត្ត<br>ផ្នែកផែនការ និងត្រួតពិនិត្យ</div>
    <h1>របាយការណ៍សង្ខេប {{ $audience }}</h1>
    <div class="sub">តម្លៃសូចនាករ • គោលដៅ • បញ្ហា • សកម្មភាព — {{ $periodLabel }}</div>
    <table>
        <thead>
            <tr><th>សូចនាករ</th><th class="num" style="width:90px">តម្លៃ</th><th class="num" style="width:90px">គោលដៅ</th><th class="num" style="width:80px">សម្រេច</th><th style="width:22%">បញ្ហាប្រឈម</th><th style="width:22%">សកម្មភាព</th></tr>
        </thead>
        <tbody>
            @php($dn = 0)
            @foreach ($groups as $dept)
                @php($dn++)
                <tr class="dept"><td colspan="6">ជំពូកទី{{ $kh($dn) }}៖ {{ $dept['name'] }}</td></tr>
                @php($un = 0)
                @foreach ($dept['units'] as $unit)
                    @php($un++)
                    <tr class="unit"><td colspan="6">{{ $kh($dn) }}.{{ $kh($un) }} {{ $unit['name'] }}</td></tr>
                    @foreach ($unit['rows'] as $r)
                        <tr>
                            <td>{{ $r['name'] }}</td>
                            <td class="num">{{ $n($r['display_value']) }}{{ $r['computed'] ? ' *' : '' }}</td>
                            <td class="num">{{ $n($r['target']) }}</td>
                            <td class="num">{{ $r['percent'] === null ? '—' : $kh(number_format($r['percent'], 1)) . '%' }}</td>
                            <td>{!! $r['actions']->pluck('issue_text')->filter()->unique()->map(fn ($t) => '• ' . e($t))->implode('<br>') ?: '—' !!}</td>
                            <td>{!! $r['actions']->pluck('action_text')->filter()->unique()->map(fn ($t) => '• ' . e($t))->implode('<br>') ?: '—' !!}</td>
                        </tr>
                    @endforeach
                @endforeach
            @endforeach
        </tbody>
    </table>
    <div class="foot"><span>PHD Report • * តម្លៃគណនាពីទិន្នន័យប្រចាំខែ</span><span>{{ now()->format('Y-m-d H:i') }}</span></div>
    <script>window.onload = () => window.print();</script>
</body>
</html>
