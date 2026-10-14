@props(['facility'])

@php
    $color = \App\Models\Facility::typeColor($facility->facility_type);
    $thumbnail = $facility->cover_image
        ? (str_starts_with($facility->cover_image, 'http')
            ? $facility->cover_image
            : (str_starts_with($facility->cover_image, 'images/')
                ? asset($facility->cover_image)
                : \Illuminate\Support\Facades\Storage::disk('public')->url($facility->cover_image)))
        : asset('images/stock/stock-1.jpg');
@endphp

<div class="facility-row"
     data-facility-item="{{ $facility->id }}"
     data-id="{{ $facility->id }}"
     data-name="{{ $facility->name_km }}"
     data-type="{{ $facility->facility_type }}"
     data-district="{{ $facility->district }}"
     data-commune="{{ $facility->commune }}"
     data-published-at="{{ $facility->published_at?->timestamp ?? 0 }}"
     x-show="matches({ name: '{{ $facility->name_km }}', type: '{{ $facility->facility_type }}', district: '{{ $facility->district }}', commune: '{{ $facility->commune }}' })"
     :class="{ 'is-selected': selectedId === {{ $facility->id }} }"
     @click="focusFacility({{ $facility->id }})">
    <div class="facility-row-thumb">
        <img src="{{ $thumbnail }}" alt="{{ $facility->name_km }}">
    </div>

    <div class="facility-row-body">
        <a href="{{ route('facilities.show', $facility) }}" class="facility-row-title" @click.stop>{{ $facility->name_km }}</a>
        <span class="facility-row-badge" style="background: {{ $color }}1a; color: {{ $color }};">
            {{ \App\Models\Facility::typeLabel($facility->facility_type) }}
        </span>
        <div class="facility-row-meta">
            @if ($facility->district)
                <span><x-news-icon name="pin" class="size-3" />{{ $facility->district }}</span>
            @endif
            @if ($facility->phone)
                <span><x-news-icon name="phone" class="size-3" />{{ $facility->phone }}</span>
            @endif
            @if ($facility->opening_hours)
                <span><x-news-icon name="clock" class="size-3" />{{ $facility->opening_hours }}</span>
            @endif
        </div>
    </div>

    <div class="facility-row-actions">
        <a href="{{ route('facilities.show', $facility) }}" class="facility-row-detail-btn" @click.stop>មើលព័ត៌មាន →</a>
        <button type="button" class="facility-row-locate-btn" @click.stop="focusFacility({{ $facility->id }})">
            <x-news-icon name="pin" class="size-3" />ទីតាំង
        </button>
    </div>
</div>
