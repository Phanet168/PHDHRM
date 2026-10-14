{{-- Desktop: a simple one-level chart (department → groups). Phones hide it and show only the accordion list. --}}
<div class="about-org-chart" aria-hidden="false">
    <span class="about-org-root">{{ config('site.name_km') }}</span>
    <ul class="about-org-branches" style="--about-org-count: {{ $organization->count() }}">
        @foreach ($organization as $group)
            <li class="tone-{{ $loop->index % 6 }}">
                <a href="#{{ $group['type'] === $officeGroup ? 'offices' : 'org-'.$group['type'] }}" data-about-org-open>
                    <strong>{{ $group['label'] }}</strong>
                    <small>{{ $group['units']->count() }} អង្គភាព</small>
                </a>
            </li>
        @endforeach
    </ul>
</div>

<div id="about-org-detail" class="about-organization" data-about-org-detail>
    @foreach ($organization as $group)
        <details id="{{ $group['type'] === $officeGroup ? 'offices' : 'org-'.$group['type'] }}" class="about-unit-group" open>
            <summary><span class="about-unit-icon"><x-news-icon :name="$group['icon']" /></span><span>{{ $group['label'] }}<small>{{ $group['units']->count() }} អង្គភាព</small></span><x-news-icon name="chevron-right" /></summary>
            <ul>
                @foreach ($group['units'] as $unit)
                    <li>
                        @if ($unit['url'])
                            <a href="{{ $unit['url'] }}">{{ $unit['name'] }}<x-news-icon name="arrow" width="16" height="16" /></a>
                        @else
                            <span class="about-unit-name">{{ $unit['name'] }}</span>
                        @endif
                        @if ($unit['description'])<p>{{ $unit['description'] }}</p>@endif
                        @if ($unit['children']->isNotEmpty())
                            <ul class="about-unit-children">
                                @foreach ($unit['children'] as $child)
                                    <li>
                                        @if ($child['url'])<a href="{{ $child['url'] }}">{{ $child['name'] }}<x-news-icon name="arrow" width="14" height="14" /></a>@else<span class="about-unit-name">{{ $child['name'] }}</span>@endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
        </details>
    @endforeach
</div>
