<x-layout :title="$article->title_km">
    <x-page-banner :image="$article->featuredImageUrl()" :title="$article->title_km" />
    <x-page-header :title="$article->title_km" :breadcrumbs="[
        ['label' => 'ព័ត៌មាន និងសកម្មភាព', 'url' => route('news.index')],
        ['label' => $article->title_km],
    ]" />

    <div class="article-page public-container">
        <div class="news-layout">
            <div class="news-main">
                <article class="article-card flex flex-col gap-8">
                    <div>
                        <span class="article-badge">{{ $article->category?->name_km ?? 'ព័ត៌មាន' }}</span>
                        <h1 class="article-title">{{ $article->title_km }}</h1>
                        <x-article-meta :article="$article" />
                    </div>

                    <a href="{{ $article->featuredImageUrl() }}" class="article-featured-image">
                        <img src="{{ $article->featuredImageUrl() }}" alt="{{ $article->featuredImageAlt() }}">
                    </a>

                    <div class="article-prose">
                        @if ($article->content)
                            {{-- renderedContentHtml() re-sanitizes with HtmlSanitizer::clean() on every
                                 render (defence in depth) and falls back to escaped nl2br() text for
                                 legacy plain-text articles saved before the rich editor existed. --}}
                            {!! $article->renderedContentHtml() !!}
                        @else
                            <p>{{ $article->excerpt }}</p>
                        @endif
                    </div>

                    @if ($article->images->isNotEmpty())
                        <div>
                            <p class="article-gallery-heading">រូបថតសកម្មភាព</p>
                        <x-gallery :images="$article->images" :cap="10" />
                        </div>
                    @endif

                    <div class="border-t border-line">
                        <p class="article-share-heading pt-6">ចែករំលែកអត្ថបទនេះ</p>
                        <x-share-buttons :url="url()->current()" :title="$article->title_km" />
                    </div>

                    @if ($previous || $next)
                        <div class="article-nav pt-6 border-t border-line">
                            @if ($previous)
                                <a href="{{ route('news.show', $previous) }}" class="article-nav-card">
                                    <div class="article-nav-thumb"><img src="{{ $previous->featuredImageUrl() }}" alt=""></div>
                                    <div class="article-nav-body">
                                        <span class="article-nav-label"><x-news-icon name="chevron-left" class="size-4" />អត្ថបទមុន</span>
                                        <p class="article-nav-title">{{ $previous->title_km }}</p>
                                        <p class="article-nav-date">{{ \App\Support\KhmerDate::format($previous->published_at) }}</p>
                                    </div>
                                </a>
                            @else
                                <div></div>
                            @endif
                            @if ($next)
                                <a href="{{ route('news.show', $next) }}" class="article-nav-card article-nav-next">
                                    <div class="article-nav-thumb"><img src="{{ $next->featuredImageUrl() }}" alt=""></div>
                                    <div class="article-nav-body">
                                        <span class="article-nav-label">អត្ថបទបន្ទាប់<x-news-icon name="chevron-right" class="size-4" /></span>
                                        <p class="article-nav-title">{{ $next->title_km }}</p>
                                        <p class="article-nav-date">{{ \App\Support\KhmerDate::format($next->published_at) }}</p>
                                    </div>
                                </a>
                            @endif
                        </div>
                    @endif
                </article>
            </div>

            <aside class="news-sidebar" aria-label="ប្រភេទព័ត៌មាន ព័ត៌មានថ្មីៗ និងស្ថិតិអ្នកចូលទស្សនា">
                <div class="news-sidebar-stack is-sticky">
                    <x-news-category-sidebar :categories="$categories" :publishedCount="$publishedCount" :current="$article->category?->slug ?? ''" />
                    <x-latest-news-sidebar :articles="$latestNews" />
                    <x-visitor-stats :stats="$visitorStats" :statisticsUrl="route('visitor-statistics')" :countries="$visitorCountries" />
                </div>
            </aside>
        </div>

        @if ($related->isNotEmpty())
            <section class="article-related">
                <h2 class="article-related-heading">ព័ត៌មានពាក់ព័ន្ធ</h2>
                <div class="article-related-grid">
                    @foreach ($related as $item)
                        <x-related-article-card :article="$item" route="news.show" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layout>
