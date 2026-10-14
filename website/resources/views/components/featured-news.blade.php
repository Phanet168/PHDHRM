@props(['article'])

<a href="{{ route('news.show', $article) }}" class="home-featured-news">
    <div class="home-featured-news-image">
        <img src="{{ $article->featuredImageUrl() }}" alt="{{ $article->featuredImageAlt() }}" loading="lazy">
        <span class="home-featured-news-badge">{{ $article->category?->name_km ?? 'ព័ត៌មាន' }}</span>
    </div>
    <div class="home-featured-news-body">
        <span class="home-featured-news-date">{{ \App\Support\KhmerDate::format($article->published_at) }}</span>
        <p class="home-featured-news-title">{{ $article->title_km }}</p>
        @if ($article->excerpt)
            <p class="home-featured-news-excerpt">{{ \Illuminate\Support\Str::limit($article->excerpt, 140) }}</p>
        @endif
        <span class="home-featured-news-more">អានបន្ថែម →</span>
    </div>
</a>
