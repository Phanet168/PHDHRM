@php
    $chiefs = $leaders->filter(fn ($person) => str_starts_with(trim($person->title), 'ប្រធាន'));
    $deputies = $leaders->filter(fn ($person) => str_starts_with(trim($person->title), 'អនុប្រធាន'));
    // A group with several units takes two tracks so its cards sit side by side; too many groups fall back to one track each.
    $spans = $organization->map(fn ($group) => $group['units']->count() > 1 ? 2 : 1);
    if ($spans->sum() > 4) $spans = $spans->map(fn () => 1);
@endphp
<div class="org-tree">
    <p class="org-tree-root"><x-news-icon name="hospital" width="18" height="18" />{{ config('site.name_km') }}</p>

    @if ($chiefs->isNotEmpty() || $deputies->isNotEmpty())
        <div class="org-leadership">
            <h3 class="org-leadership-label">ថ្នាក់ដឹកនាំមន្ទីរ</h3>
            @if ($chiefs->isNotEmpty())
                <div class="org-leadership-chief">
                    @foreach ($chiefs as $leader)
                        @include('about.chart-person', ['person' => ['name' => $leader->name, 'title' => $leader->title, 'photo' => $leader->portraitUrl()], 'variant' => 'chief'])
                    @endforeach
                </div>
            @endif
            @if ($deputies->isNotEmpty())
                <ul class="org-leadership-deputies org-bus" style="--org-count: {{ $deputies->count() }}">
                    @foreach ($deputies as $leader)
                        <li>@include('about.chart-person', ['person' => ['name' => $leader->name, 'title' => $leader->title, 'photo' => $leader->portraitUrl()], 'variant' => 'deputy'])</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    {{-- Connector lines only make sense while every group fits on one row. --}}
    <ul @class(['org-groups', 'org-bus', 'is-connected' => $organization->count() <= 4]) style="--org-tracks: {{ min($spans->sum(), 4) }}">
        @foreach ($organization as $group)
            <li id="{{ $group['type'] === $officeGroup ? 'offices' : 'org-'.$group['type'] }}" class="org-group tone-{{ $loop->index % 6 }}" style="--org-span: {{ $spans[$loop->index] }}">
                <h3 class="org-group-head">
                    <span class="org-group-icon"><x-news-icon :name="$group['icon']" width="18" height="18" /></span>
                    <span>{{ $group['label'] }}<small>{{ \App\Support\KhmerDate::number($group['units']->count()) }} អង្គភាព</small></span>
                </h3>
                <ul class="org-units">
                    @foreach ($group['units'] as $unit)
                        @include('about.chart-unit', ['unit' => $unit])
                    @endforeach
                </ul>
            </li>
        @endforeach
    </ul>
</div>
