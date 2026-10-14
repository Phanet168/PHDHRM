@php
    $current = (string) request('category', '');
    $activeCategory = $categories->firstWhere('slug', $current);
@endphp
<x-layout title="ព័ត៌មាន និងសកម្មភាព">
    <x-page-banner page="news" title="ព័ត៌មាន និងសកម្មភាព" />
    <x-page-header title="ព័ត៌មាន និងសកម្មភាព" />

    <main class="news-page news-index public-container">
        <div class="news-index-layout">
            @if ($featuredArticles->isNotEmpty())
                <section class="news-featured" aria-labelledby="featured-heading">
                    <div class="news-index-heading">
                        <div class="news-index-heading-title">
                            <span class="news-index-heading-icon"><x-news-icon name="document" width="22" height="22" /></span>
                            <h2 id="featured-heading">ព័ត៌មានលេចធ្លោ</h2>
                        </div>
                        <a class="news-index-heading-link" href="{{ route('news.index') }}#articles">មើលទាំងអស់ <x-news-icon name="chevron-right" width="16" height="16" /></a>
                    </div>
                    <div class="swiper news-swiper" aria-label="ព័ត៌មានលេចធ្លោ" aria-roledescription="carousel">
                        <div class="swiper-wrapper">
                            @foreach ($featuredArticles as $article)
                                <article class="swiper-slide news-slide" aria-roledescription="slide" aria-label="{{ $loop->iteration }} / {{ $loop->count }}" @if (! $loop->first) inert @endif>
                                    <img class="news-slide-image" src="{{ $article->featuredImageUrl() }}" alt="" @if ($loop->first) fetchpriority="high" @else loading="lazy" @endif>
                                    <div class="news-slide-shade"></div>
                                    <div class="news-slide-content">
                                        <span class="news-featured-badge">{{ $article->category?->name_km ?? 'ព័ត៌មាន' }}</span>
                                        <h3><a href="{{ route('news.show', $article) }}">{{ $article->title_km }}</a></h3>
                                        <div class="news-slide-meta">
                                            <time datetime="{{ $article->published_at->toDateString() }}"><x-news-icon name="calendar" />{{ \App\Support\KhmerDate::format($article->published_at) }}</time>
                                            @if ($article->views_count > 0)
                                                <span><x-news-icon name="eye" />{{ \App\Support\KhmerDate::number($article->views_count) }}</span>
                                            @endif
                                        </div>
                                        <a class="news-read-button" href="{{ route('news.show', $article) }}" aria-label="អានបន្ថែម៖ {{ $article->title_km }}">អានបន្ថែម <x-news-icon name="arrow" /></a>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                        @if ($featuredArticles->count() > 1)
                            <div class="news-slider-controls">
                                <div class="news-slider-pagination"></div>
                                <div class="news-slider-buttons">
                                    <button type="button" class="news-slider-pause" aria-label="ផ្អាកការប្ដូរព័ត៌មានដោយស្វ័យប្រវត្តិ" aria-pressed="false"><span data-pause-icon aria-hidden="true">Ⅱ</span><span data-play-icon hidden aria-hidden="true">▶</span></button>
                                    <button type="button" class="news-slider-prev" aria-label="ព័ត៌មានមុន"><x-news-icon name="chevron-left" /></button>
                                    <button type="button" class="news-slider-next" aria-label="ព័ត៌មានបន្ទាប់"><x-news-icon name="chevron-right" /></button>
                                </div>
                            </div>
                        @endif
                    </div>
                </section>
            @endif

            <aside class="news-sidebar" aria-label="ប្រភេទព័ត៌មាន និងស្ថិតិអ្នកចូលទស្សនា">
                <div class="news-sidebar-stack">
                    <x-news-category-sidebar :categories="$categories" :publishedCount="$publishedCount" :current="$current" :enhanced="true" />
                    <x-visitor-stats :stats="$visitorStats" :statisticsUrl="route('visitor-statistics')" :icons="true" :countries="$visitorCountries" />
                </div>
            </aside>

            <section id="articles" class="news-listing" aria-labelledby="articles-heading">
                <div class="news-index-heading">
                    <div class="news-index-heading-title">
                        <span class="news-index-heading-icon"><x-news-icon name="document" width="22" height="22" /></span>
                        <h2 id="articles-heading">{{ $activeCategory?->name_km ?? 'ព័ត៌មាន និងសកម្មភាពថ្មីៗ' }}</h2>
                    </div>
                    <span class="news-results">{{ \App\Support\KhmerDate::toKhmerDigits((string) $articles->total()) }} អត្ថបទ</span>
                </div>
                @if ($articles->isEmpty())
                    <div class="news-empty">
                        <x-news-icon name="document" />
                        <h3>មិនមានព័ត៌មានក្នុងប្រភេទនេះទេ។</h3>
                        <p>សូមជ្រើសរើសប្រភេទផ្សេងទៀត ដើម្បីអានព័ត៌មាន និងសកម្មភាព។</p>
                        <a href="{{ route('news.index') }}#articles">មើលព័ត៌មានទាំងអស់ <span aria-hidden="true">→</span></a>
                    </div>
                @else
                    <div class="news-article-grid">
                        @foreach ($articles as $article)
                            <x-news-article-card :article="$article" />
                        @endforeach
                    </div>
                @endif
            </section>

            @if ($articles->isNotEmpty())
                <div class="news-index-pager">
                    <p class="news-index-pager-summary">បង្ហាញ {{ \App\Support\KhmerDate::toKhmerDigits((string) $articles->firstItem()) }}-{{ \App\Support\KhmerDate::toKhmerDigits((string) $articles->lastItem()) }} នៃ {{ \App\Support\KhmerDate::toKhmerDigits((string) $articles->total()) }} ព័ត៌មាន</p>
                    {{ $articles->onEachSide(1)->fragment('articles')->links('news.pagination') }}
                    <p class="news-index-pager-pages">ទំព័រ {{ \App\Support\KhmerDate::toKhmerDigits((string) $articles->currentPage()) }} នៃ {{ \App\Support\KhmerDate::toKhmerDigits((string) $articles->lastPage()) }}</p>
                </div>
            @endif
        </div>
    </main>
</x-layout>
