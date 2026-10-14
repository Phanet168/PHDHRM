@props(['video'])

<button type="button" class="home-featured-health-video" aria-label="ចាក់វីដេអូ៖ {{ $video->title_km }}"
    @click="const e = @js($video->videoEmbed()); if (e) { videoOpen = true; videoEmbed = e }">
    <span class="home-featured-health-image">
        <img src="{{ $video->thumbnailUrl() }}" alt="{{ $video->title_km }}" loading="eager">
        <span class="home-health-badge">{{ $video->category?->name_km ?? 'អប់រំសុខភាព' }}</span>
        <span class="home-health-play-icon" aria-hidden="true">▶</span>
        @if ($video->durationLabel())
            <span class="home-health-duration">{{ $video->durationLabel() }}</span>
        @endif
    </span>
    <span class="home-featured-health-body">
        <span class="home-featured-health-title">{{ $video->title_km }}</span>
        @if ($video->excerpt)
            <span class="home-featured-health-excerpt">{{ \Illuminate\Support\Str::limit($video->excerpt, 140) }}</span>
        @endif
        <span class="home-featured-health-date">{{ \App\Support\KhmerDate::format($video->published_at) }}</span>
    </span>
</button>
