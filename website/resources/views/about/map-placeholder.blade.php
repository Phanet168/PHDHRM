{{-- Stylised map card used until real coordinates are configured (or as the sidebar preview). No invented location. --}}
<a class="about-map-placeholder {{ ($small ?? false) ? 'is-small' : '' }}" href="{{ $mapUrl }}" target="_blank" rel="noopener noreferrer" aria-label="មើលទីតាំង {{ config('site.name_km') }} លើ Google Maps (បើកក្នុងផ្ទាំងថ្មី)">
    <span class="about-map-pin" aria-hidden="true"><x-news-icon name="pin" width="{{ ($small ?? false) ? 22 : 28 }}" height="{{ ($small ?? false) ? 22 : 28 }}" /></span>
    <span class="about-map-label" aria-hidden="true">{{ config('site.name_km') }}</span>
    <span class="about-map-cta"><x-news-icon name="globe" width="16" height="16" />មើលលើ Google Maps</span>
</a>
