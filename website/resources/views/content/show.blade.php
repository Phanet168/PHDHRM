<x-layout :title="$article->title_km">
    <x-page-banner :image="$article->featuredImageUrl()" :title="$article->title_km" />
    <x-page-header :title="$article->title_km" :breadcrumbs="array_filter([
        $article->category ? ['label' => $article->category->name_km, 'url' => route('category.show', $article->category)] : null,
        ['label' => $article->title_km],
    ])" />

    <div class="article-page public-container">
        <div class="news-layout">
            <div class="news-main">
                <article class="article-card flex flex-col gap-8">
                    <div>
                        @if ($article->category)
                            <span class="article-badge">{{ $article->category->name_km }}</span>
                        @endif
                        <h1 class="article-title">{{ $article->title_km }}</h1>
                        <x-article-meta :article="$article" />
                    </div>

                    <div>
                        <a href="{{ $article->featuredImageUrl() }}" class="article-featured-image">
                            <img src="{{ $article->featuredImageUrl() }}" alt="{{ $article->title_km }}">
                        </a>
                        @if ($article->excerpt)
                            <p class="article-featured-caption">{{ $article->excerpt }}</p>
                        @endif
                    </div>

                    <div class="flex flex-col gap-8">
                        @foreach ($article->blocks as $block)
                            <x-content.render-block :block="$block" />
                        @endforeach
                    </div>

                    @if ($article->images->isNotEmpty())
                        <div>
                            <p class="article-gallery-heading">រូបថតសកម្មភាព</p>
                            <x-gallery :images="$article->images" />
                        </div>
                    @endif

                    <div class="border-t border-line">
                        <p class="article-share-heading pt-6">ចែករំលែកអត្ថបទនេះ</p>
                        <x-share-buttons :url="url()->current()" :title="$article->title_km" />
                    </div>

                    @if (! empty($article->tags))
                        <div>
                            <p class="article-tags-heading">ស្លាកសញ្ញា</p>
                            <div class="article-tags">
                                @foreach ($article->tags as $tag)
                                    <span class="article-tag">#{{ $tag }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($previous || $next)
                        <div class="article-nav pt-6 border-t border-line">
                            @if ($previous)
                                <a href="{{ route('content.show', $previous) }}" class="article-nav-card">
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
                                <a href="{{ route('content.show', $next) }}" class="article-nav-card article-nav-next">
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
                    <x-category-sidebar :categories="$categories" :current="$article->category_id" />
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
                        <x-related-article-card :article="$item" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layout>
