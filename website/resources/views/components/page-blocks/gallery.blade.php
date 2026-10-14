@props(['block'])
@php
    $mediaIds = $block->get('media_ids', []);
    $images = empty($mediaIds) ? collect() : \App\Models\Media::whereIn('id', $mediaIds)->get()->sortBy(fn ($m) => array_search($m->id, $mediaIds))->values();
    $cap = 8;
    $total = $images->count();
    $overflow = $total > $cap;
    $normalCount = $overflow ? $cap - 1 : $total;
    $remaining = $overflow ? $total - $cap : 0;
@endphp
@if ($total > 0)
    <div class="article-gallery-grid">
        @foreach ($images as $index => $image)
            @if ($index < $normalCount)
                <a href="{{ $image->url }}" class="article-gallery-item content-gallery-link" data-glightbox="title: {{ $image->alt_text }}">
                    <img src="{{ $image->url }}" alt="{{ $image->alt_text }}" loading="lazy">
                    <span class="article-gallery-overlay"><x-news-icon name="zoom" /></span>
                </a>
            @elseif ($overflow && $index === $normalCount)
                <a href="{{ $image->url }}" class="article-gallery-item content-gallery-link" data-glightbox="title: {{ $image->alt_text }}">
                    <img src="{{ $image->url }}" alt="{{ $image->alt_text }}" loading="lazy">
                    <span class="article-gallery-more">+{{ $remaining }} រូបទៀត</span>
                </a>
            @else
                <a href="{{ $image->url }}" class="hidden content-gallery-link" data-glightbox="title: {{ $image->alt_text }}" aria-hidden="true"></a>
            @endif
        @endforeach
    </div>
@endif
