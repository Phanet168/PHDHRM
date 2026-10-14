@props(['video'])

<button type="button" class="home-health-small" aria-label="ចាក់វីដេអូ៖ {{ $video->title_km }}"
    @click="const e = @js($video->videoEmbed()); if (e) { videoOpen = true; videoEmbed = e }">
    <span class="home-health-small-image">
        <img src="{{ $video->thumbnailUrl() }}" alt="{{ $video->title_km }}" loading="lazy">
        <span class="home-health-play-icon home-health-play-icon-small" aria-hidden="true">▶</span>
        @if ($video->durationLabel())
            <span class="home-health-duration">{{ $video->durationLabel() }}</span>
        @endif
    </span>
    <span class="home-health-small-body">
        <span class="home-health-small-category">{{ $video->category?->name_km ?? 'អប់រំសុខភាព' }}</span>
        <span class="home-health-small-title">{{ $video->title_km }}</span>
        <span class="home-health-small-date">{{ \App\Support\KhmerDate::format($video->published_at) }}</span>
    </span>
</button>
