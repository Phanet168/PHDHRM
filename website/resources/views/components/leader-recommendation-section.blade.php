@props(['recommendation'])

@php
    $items = $recommendation->activeItems;
    $groups = $items->chunk(4)->values();
    $icons = ['users', 'heart-pulse', 'hospital', 'shield', 'stethoscope', 'trending-up', 'monitor', 'globe'];
@endphp

<section class="home-section leader-recommendation-section">
    <div class="leader-recommendation-panel">
        <div class="leader-recommendation-main">
            <div class="leader-recommendation-portrait">
                @if ($recommendation->leaderImageUrl())
                    <img src="{{ $recommendation->leaderImageUrl() }}" alt="{{ $recommendation->leader_name_km }}" class="leader-recommendation-portrait-img" loading="lazy">
                @else
                    <div class="leader-recommendation-portrait-fallback">
                        <x-news-icon name="user" width="40" height="40" />
                    </div>
                @endif
                <div class="leader-recommendation-name-overlay">
                    <p class="leader-recommendation-name">{{ $recommendation->leader_name_km }}</p>
                    @if ($recommendation->leader_position_km)
                        <p class="leader-recommendation-position">{{ $recommendation->leader_position_km }}</p>
                    @endif
                </div>
            </div>

            <div class="leader-recommendation-content">
                <header class="leader-recommendation-heading">
                    <p class="leader-recommendation-eyebrow">អនុសាសន៍ថ្នាក់ដឹកនាំ</p>
                    <div class="leader-recommendation-divider" aria-hidden="true"></div>
                    <p class="leader-recommendation-heading-sub">
                        {{ $recommendation->title_km }}
                        @if ($recommendation->subtitle_km)
                            {{ $recommendation->subtitle_km }}
                        @endif
                    </p>
                </header>

                @if ($groups->isNotEmpty())
                    <div class="leader-recommendation-swiper swiper" aria-label="អនុសាសន៍ថ្នាក់ដឹកនាំ" aria-roledescription="carousel">
                        <div class="swiper-wrapper">
                            @foreach ($groups as $group)
                                <div class="swiper-slide">
                                    <div class="leader-recommendation-grid">
                                        @foreach ($group as $item)
                                            @php $globalIndex = $loop->parent->index * 4 + $loop->index; @endphp
                                            <div class="leader-recommendation-item">
                                                <span class="leader-recommendation-badge">{{ \App\Support\KhmerDate::toKhmerDigits((string) ($globalIndex + 1)) }}</span>
                                                <span class="leader-recommendation-item-text">{{ $item->content_km }}</span>
                                                <span class="leader-recommendation-item-icon">
                                                    <x-news-icon :name="$icons[$globalIndex % count($icons)]" width="20" height="20" />
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="leader-recommendation-controls">
                    @if ($groups->count() > 1)
                        <div class="leader-slider-pagination"></div>
                    @endif

                    <a href="{{ route('leader-recommendations.show', $recommendation) }}" class="leader-recommendation-more">
                        <span>មើលអនុសាសន៍ទាំងអស់</span>
                        <span aria-hidden="true">→</span>
                    </a>

                    @if ($groups->count() > 1)
                        <div class="leader-slider-nav-group">
                            <button type="button" class="leader-slider-prev leader-slider-arrow" aria-label="មុន">
                                <x-news-icon name="chevron-left" width="18" height="18" />
                            </button>
                            <button type="button" class="leader-slider-next leader-slider-arrow" aria-label="បន្ទាប់">
                                <x-news-icon name="chevron-right" width="18" height="18" />
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
