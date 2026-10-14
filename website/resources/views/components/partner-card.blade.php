@props(['partner'])

@if ($partner->website)
    <a href="{{ $partner->website }}" target="_blank" rel="noopener" class="home-partner-card" title="{{ $partner->name }}">
        @if ($partner->logo)
            <img src="{{ asset('storage/' . $partner->logo) }}" alt="{{ $partner->name }}" loading="lazy">
        @else
            <span class="home-partner-card-name">{{ $partner->name }}</span>
        @endif
    </a>
@else
    <div class="home-partner-card" title="{{ $partner->name }}">
        @if ($partner->logo)
            <img src="{{ asset('storage/' . $partner->logo) }}" alt="{{ $partner->name }}" loading="lazy">
        @else
            <span class="home-partner-card-name">{{ $partner->name }}</span>
        @endif
    </div>
@endif
