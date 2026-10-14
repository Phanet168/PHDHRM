@php
    $leaderImage = $recommendation->leaderImageUrl();
    $itemCount = $recommendation->activeItems->count();
@endphp

<x-layout :title="$recommendation->title_km" :meta-description="$recommendation->subtitle_km" :og-image="$leaderImage">
    <main class="leader-show-page public-container">
        <nav class="leader-show-breadcrumb" aria-label="ទីតាំងទំព័រ">
            <a href="{{ route('home') }}">ទំព័រដើម</a>
            <x-news-icon name="chevron-right" width="16" height="16" />
            <span aria-current="page">អនុសាសន៍ថ្នាក់ដឹកនាំ</span>
        </nav>

        <header class="leader-show-hero" aria-labelledby="leader-show-title">
            <div class="leader-show-intro">
                <span class="leader-show-eyebrow"><span aria-hidden="true"></span>អនុសាសន៍ថ្នាក់ដឹកនាំ</span>
                <h1 id="leader-show-title">{{ $recommendation->title_km }}</h1>
                @if ($recommendation->subtitle_km)
                    <p class="leader-show-subtitle">{{ $recommendation->subtitle_km }}</p>
                @endif
                <div class="leader-show-meta">
                    <span class="leader-show-count"><x-news-icon name="list" />{{ \App\Support\KhmerDate::toKhmerDigits((string) $itemCount) }} អនុសាសន៍</span>
                    @if ($recommendation->published_at)
                        <time datetime="{{ $recommendation->published_at->toDateString() }}"><x-news-icon name="calendar" />{{ \App\Support\KhmerDate::format($recommendation->published_at) }}</time>
                    @endif
                </div>
                @if ($itemCount)
                    <a href="#leader-recommendation-list" class="leader-show-read">អានអនុសាសន៍ទាំងអស់ <x-news-icon name="arrow" /></a>
                @endif
            </div>

            <figure class="leader-show-profile">
                <div class="leader-show-portrait">
                    <span class="leader-show-photo-fallback" aria-hidden="true"><x-news-icon name="user" width="64" height="64" /></span>
                    @if ($leaderImage)
                        <img src="{{ $leaderImage }}" alt="{{ $recommendation->leader_name_km }}" class="leader-show-photo" width="1280" height="800" fetchpriority="high" decoding="async" onerror="this.hidden = true;">
                    @endif
                </div>
                <figcaption>
                    <p class="leader-show-name">{{ $recommendation->leader_name_km }}</p>
                    @if ($recommendation->leader_position_km)
                        <p class="leader-show-position">{{ $recommendation->leader_position_km }}</p>
                    @endif
                </figcaption>
            </figure>
        </header>

        <section id="leader-recommendation-list" class="leader-show-content" aria-labelledby="leader-show-list-title">
            <div class="leader-show-toolbar">
                <div class="leader-show-section-heading">
                    <span class="leader-show-section-icon" aria-hidden="true"><x-news-icon name="list" width="24" height="24" /></span>
                    <h2 id="leader-show-list-title">អនុសាសន៍សំខាន់ៗ</h2>
                    <span class="leader-show-total">{{ \App\Support\KhmerDate::toKhmerDigits((string) $itemCount) }}</span>
                </div>
                <div class="leader-show-sharing">
                    <x-share-buttons :url="route('leader-recommendations.show', $recommendation)" :title="$recommendation->title_km" />
                </div>
            </div>

            @if ($itemCount)
                <ol class="leader-show-list" role="list">
                    @foreach ($recommendation->activeItems as $item)
                        <li>
                            <span class="leader-show-number" aria-hidden="true">{{ \App\Support\KhmerDate::toKhmerDigits(str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT)) }}</span>
                            <p>{{ $item->content_km }}</p>
                        </li>
                    @endforeach
                </ol>
            @else
                <div class="leader-show-empty"><x-news-icon name="list" width="36" height="36" /><p>មិនទាន់មានអនុសាសន៍ទេ។</p></div>
            @endif
        </section>

        <a href="{{ route('home') }}" class="leader-show-back"><x-news-icon name="chevron-left" width="18" height="18" />ត្រឡប់ទៅទំព័រដើម</a>
    </main>
</x-layout>
