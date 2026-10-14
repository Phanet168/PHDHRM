@props(['embed'])

@if ($embed)
    <div class="health-show-video">
        @if ($embed['type'] === 'iframe')
            <iframe src="{{ $embed['src'] }}" loading="lazy"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
        @else
            <video src="{{ $embed['src'] }}" controls></video>
        @endif
    </div>
@endif
