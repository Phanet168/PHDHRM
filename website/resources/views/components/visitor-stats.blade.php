@props(['stats', 'statisticsUrl', 'icons' => false, 'countries' => null])
@php
    // [stat key, label, icon, tint] — the icon/tint only render when $icons is on (redesigned news index).
    $rows = [
        ['online', 'កំពុងទស្សនាឥឡូវនេះ', 'users', 'green'],
        ['today', 'អ្នកទស្សនាថ្ងៃនេះ', 'calendar', 'red'],
        ['yesterday', 'អ្នកទស្សនាម្សិលមិញ', 'clock', 'amber'],
        ['month', 'អ្នកទស្សនាខែនេះ', 'chart', 'purple'],
        ['total', 'អ្នកទស្សនាសរុប', 'globe', 'blue'],
        ['page_views', 'ចំនួនមើលទំព័រសរុប', 'eye', 'blue'],
    ];
@endphp

<section class="news-sidebar-card news-statistics {{ $icons ? 'is-enhanced' : '' }}" aria-labelledby="statistics-heading" data-statistics-url="{{ $statisticsUrl }}">
    <h2 class="news-sidebar-title" id="statistics-heading"><x-news-icon name="chart" />ស្ថិតិអ្នកចូលទស្សនា</h2>
    <dl class="news-stat-list">
        @foreach ($rows as [$key, $label, $icon, $tint])
            <div class="{{ $key === 'online' ? 'news-online' : '' }} {{ $key === 'page_views' ? 'news-page-views' : '' }}">
                <dt>
                    @if ($icons)
                        <span class="news-stat-icon is-{{ $tint }}"><x-news-icon :name="$icon" /></span>
                    @elseif ($key === 'online')
                        <span class="news-online-dot"></span>
                    @endif
                    {{ $label }}
                </dt>
                <dd data-stat="{{ $key }}">{{ \App\Support\KhmerDate::number($stats[$key]) }}</dd>
            </div>
        @endforeach
    </dl>
    @if (! empty($countries['countries']))
        <div class="news-country-block">
            <h3 class="news-country-title"><x-news-icon name="globe" />អ្នកទស្សនាតាមប្រទេស</h3>
            <ul class="news-country-list">
                @foreach ($countries['countries'] as $row)
                    <li>
                        <span class="news-country-name"><b class="news-country-code" aria-hidden="true">{{ $row['code'] }}</b>{{ $row['name'] }}</span>
                        <span class="news-country-count">{{ \App\Support\KhmerDate::number($row['visitors']) }}</span>
                        <span class="news-country-bar" role="img" aria-label="{{ \App\Support\KhmerDate::toKhmerDigits((string) $row['share']) }}%"><i style="width: {{ min(100, max(2, $row['share'])) }}%"></i></span>
                    </li>
                @endforeach
                @if ($countries['other'] > 0)
                    <li>
                        <span class="news-country-name"><b class="news-country-code" aria-hidden="true">…</b>ប្រទេសផ្សេងៗ</span>
                        <span class="news-country-count">{{ \App\Support\KhmerDate::number($countries['other']) }}</span>
                    </li>
                @endif
            </ul>
        </div>
    @endif
    <p class="news-stat-note">សកម្មក្នុងរយៈពេល ៥ នាទីចុងក្រោយ។<br>រាប់ដោយអនាមិក និងមិនរក្សាទុកអាសយដ្ឋាន IP។@if (! empty($countries['countries']))<br>ប្រទេសកំណត់ដោយ <a href="https://db-ip.com" target="_blank" rel="noopener noreferrer">IP Geolocation by DB-IP</a>@endif</p>
</section>
