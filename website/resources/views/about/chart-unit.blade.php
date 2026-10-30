@php($people = collect($unit['leaders'] ?? []))
<li class="org-unit">
    @if ($unit['url'])
        <a class="org-unit-title" href="{{ $unit['url'] }}"><span>{{ $unit['name'] }}</span><x-news-icon name="arrow" width="15" height="15" /></a>
    @else
        <strong class="org-unit-title">{{ $unit['name'] }}</strong>
    @endif
    @if ($unit['description'])<p class="org-unit-desc">{{ $unit['description'] }}</p>@endif
    @if ($people->isNotEmpty())
        <div class="org-unit-people">
            @foreach ($people as $person)
                @include('about.chart-person', ['person' => $person, 'variant' => $loop->first && ! str_starts_with($person['title'], 'អនុ') ? 'head' : 'member'])
            @endforeach
        </div>
    @endif
    @if ($unit['children']->isNotEmpty())
        <details class="org-unit-children">
            <summary>អង្គភាពរង <span>{{ \App\Support\KhmerDate::number($unit['children']->count()) }}</span><x-news-icon name="chevron-right" width="14" height="14" /></summary>
            <ul>
                @foreach ($unit['children'] as $child)
                    @include('about.chart-unit', ['unit' => $child])
                @endforeach
            </ul>
        </details>
    @endif
</li>
