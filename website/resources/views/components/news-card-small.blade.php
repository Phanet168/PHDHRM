@props(['article'])

<a href="{{ route('news.show', $article) }}" class="home-news-small">
    <div class="home-news-small-image">
        <img src="{{ $article->featuredImageUrl() }}" alt="{{ $article->featuredImageAlt() }}" loading="lazy">
    </div>
    <div class="home-news-small-body">
        <span class="home-news-small-category">{{ $article->category?->name_km ?? 'ព័ត៌មាន' }}</span>
        <p class="home-news-small-title">{{ $article->title_km }}</p>
        <span class="home-news-small-date">{{ \App\Support\KhmerDate::format($article->published_at) }}</span>
        @if ($article->excerpt)
            <p class="home-news-small-excerpt">{{ $article->excerpt }}</p>
        @endif
        <span class="home-news-small-more">អានបន្ថែម →</span>
    </div>
</a>
