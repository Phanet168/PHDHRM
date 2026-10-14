@if ($timeline->isNotEmpty())
    <ol class="about-timeline" style="--about-timeline-count: {{ min($timeline->count(), 6) }}">
        @foreach ($timeline as $event)
            <li>
                <span class="about-timeline-dot" aria-hidden="true"><x-news-icon :name="$event['icon']" width="16" height="16" /></span>
                @if ($event['date'])<span class="about-timeline-year">{{ $event['date'] }}</span>@endif
                @if ($event['title'])<h3>{{ $event['title'] }}</h3>@endif
                @if ($event['text'])<p>{{ $event['text'] }}</p>@endif
            </li>
        @endforeach
    </ol>
@endif
