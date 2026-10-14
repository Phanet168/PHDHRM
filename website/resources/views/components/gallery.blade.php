@props(['images', 'cap' => 8])

@php
    $images = $images->values();
    $total = $images->count();
    $overflow = $total > $cap;
    $normalCount = $overflow ? $cap - 1 : $total;
    $remaining = $overflow ? $total - $cap : 0;
@endphp

@if ($total > 0)
    <div class="article-gallery-grid">
        @foreach ($images as $index => $image)
            @if ($index < $normalCount)
                <a href="{{ $image->url() }}" class="article-gallery-item content-gallery-link" data-glightbox="title: {{ $image->caption }}">
                    <img src="{{ $image->url() }}" alt="{{ $image->alt_text }}" loading="lazy">
                    <span class="article-gallery-overlay"><x-news-icon name="zoom" /></span>
                </a>
            @elseif ($overflow && $index === $normalCount)
                <a href="{{ $image->url() }}" class="article-gallery-item content-gallery-link" data-glightbox="title: {{ $image->caption }}">
                    <img src="{{ $image->url() }}" alt="{{ $image->alt_text }}" loading="lazy">
                    <span class="article-gallery-more">+{{ \App\Support\KhmerDate::toKhmerDigits((string) $remaining) }} រូបទៀត</span>
                </a>
            @else
                {{-- Hidden but still registered with the lightbox so prev/next can reach every image. --}}
                <a href="{{ $image->url() }}" class="hidden content-gallery-link" data-glightbox="title: {{ $image->caption }}" aria-hidden="true"></a>
            @endif
        @endforeach
    </div>
@endif
