@props(['link'])

@php
    // Fallback order: this card's selected Media Library image, then a
    // card-specific default (never one single image reused for every card).
    // Soft-deleted or missing media already resolves to null via the
    // belongsTo relation.
    $imageUrl = $link->imageUrl() ?? asset($link->defaultImagePath());
@endphp

<a href="{{ $link->resolveUrl() }}" @if ($link->link_type === 'url') target="_blank" rel="noopener" @endif class="home-quick-card">
    <div class="home-quick-card-image">
        <img src="{{ $imageUrl }}" alt="{{ $link->media?->alt_text ?: $link->title_km }}" loading="lazy">
    </div>
    <div class="home-quick-card-body">
        <span class="home-quick-icon" style="background: {{ $link->iconColor() }};">
            <x-news-icon :name="$link->icon" width="22" height="22" />
        </span>
        <span class="home-quick-arrow">
            <x-news-icon name="arrow" width="16" height="16" />
        </span>
        <div class="home-quick-card-text">
            <p class="home-quick-label">{{ $link->title_km }}</p>
            @if ($link->description_km)
                <p class="home-quick-description">{{ $link->description_km }}</p>
            @endif
        </div>
    </div>
</a>
