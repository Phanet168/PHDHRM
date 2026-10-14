@props(['types'])

<div class="facility-map-panel">
    <div id="facility-map" class="absolute inset-0"></div>

    <div class="facility-legend" role="group" aria-label="ចំណាំពណ៌លើផែនទី">
        @foreach ($types as $key => $meta)
            <label>
                <input type="checkbox" x-model="legend.{{ $key }}" @change="renderMarkers()">
                <span class="facility-legend-dot" style="background: {{ $meta['color'] }};"></span>
                {{ $meta['label'] }}
            </label>
        @endforeach
    </div>
</div>
