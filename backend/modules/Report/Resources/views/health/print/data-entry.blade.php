@php($n = fn ($v) => \Modules\Report\Services\PeriodCode::khNumber($v))
<!doctype html>
<html lang="km">
<head>
    @include('report::health.print._head')
    <title>របាយការណ៍ • {{ $label }}</title>
</head>
<body>
    <button class="noprint" onclick="window.print()">បោះពុម្ព</button>
    <h1>របាយការណ៍ • {{ $label }}</h1>
    <div class="sub">មន្ទីរសុខាភិបាលខេត្ត • {{ \Modules\Report\Services\PeriodCode::kh($rows->count()) }} សូចនាករ</div>
    <table>
        <thead>
            <tr><th style="width:70px">លេខសម្គាល់</th><th>សូចនាករ</th><th style="width:160px">ជំពូក</th><th style="width:150px">ផ្នែក</th><th class="num" style="width:90px">តម្លៃ</th><th class="num" style="width:90px">គោលដៅ</th><th style="width:110px">ស្ថានភាព</th></tr>
        </thead>
        <tbody>
            @foreach ($rows as $r)
                <tr>
                    <td>{{ $r['code'] }}</td>
                    <td>{{ $r['name'] }}</td>
                    <td>{{ $r['department'] }}</td>
                    <td>{{ $r['unit'] }}</td>
                    <td class="num">{{ $n($r['value']) }}</td>
                    <td class="num">{{ $n($r['target']) }}</td>
                    <td class="{{ $r['value'] === null ? 'warn' : 'ok' }}">{{ $r['value'] === null ? 'មិនទាន់បញ្ចូល' : 'បានបញ្ចូលរួច' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="foot"><span>PHD Report</span><span>{{ now()->format('Y-m-d H:i') }}</span></div>
    <script>window.onload = () => window.print();</script>
</body>
</html>
