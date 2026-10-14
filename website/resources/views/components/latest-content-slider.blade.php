@props(['items'])

<div class="home-latest-slider-wrap">
    <div class="home-latest-swiper-shell">
        <div class="home-latest-swiper swiper" aria-label="មាតិកាថ្មីៗ" aria-roledescription="carousel">
            <div class="swiper-wrapper">
                @foreach ($items as $item)
                    <div class="swiper-slide">
                        <a href="{{ $item->url }}" class="home-latest-card">
                            <div class="home-latest-card-image">
                                @if ($item->thumbnail)
                                    <img src="{{ asset($item->thumbnail) }}" alt="{{ $item->title }}" loading="lazy">
                                @else
                                    <div class="home-latest-card-fallback home-latest-type-{{ $item->type }}">
                                        <x-news-icon name="document" width="30" height="30" />
                                    </div>
                                @endif
                                <span class="home-latest-badge home-latest-type-{{ $item->type }}">{{ $item->categoryLabel }}</span>
                            </div>
                            <div class="home-latest-card-body">
                                <span class="home-latest-card-date">{{ \App\Support\KhmerDate::format($item->publishedAt) }}</span>
                                <p class="home-latest-card-title">{{ $item->title }}</p>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>

        @if ($items->count() > 1)
            <button type="button" class="home-latest-prev home-latest-arrow" aria-label="មាតិកាមុន">
                <x-news-icon name="chevron-left" width="18" height="18" />
            </button>
            <button type="button" class="home-latest-next home-latest-arrow" aria-label="មាតិកាបន្ទាប់">
                <x-news-icon name="chevron-right" width="18" height="18" />
            </button>
        @endif
    </div>

    @if ($items->count() > 1)
        <div class="home-latest-pagination"></div>
    @endif
</div>
