@props(['article'])
@php $views = (int) $article->views_count; @endphp
<article class="news-article-card">
    <a class="news-card-image" href="{{ route('news.show', $article) }}" tabindex="-1" aria-hidden="true">
        <img src="{{ $article->featuredImageUrl() }}" alt="" loading="lazy" width="640" height="360">
        <span class="news-card-badge">{{ $article->category?->name_km ?? 'ព័ត៌មាន' }}</span>
    </a>
    <div class="news-card-body">
        <h3><a href="{{ route('news.show', $article) }}">{{ $article->title_km }}</a></h3>
        <p class="news-card-excerpt">{{ \Illuminate\Support\Str::limit(strip_tags($article->excerpt ?: ($article->content ?? '')), 180) }}</p>
        <div class="news-card-footer">
            <div class="news-card-meta">
                <time datetime="{{ $article->published_at->toDateString() }}"><x-news-icon name="calendar" />{{ \App\Support\KhmerDate::short($article->published_at) }}</time>
                @if ($views > 0)
                    <span class="news-card-views"><x-news-icon name="eye" />{{ \App\Support\KhmerDate::number($views) }}</span>
                @endif
            </div>
            <a href="{{ route('news.show', $article) }}" class="news-card-more" aria-label="អានបន្ត៖ {{ $article->title_km }}">អានបន្ត <x-news-icon name="arrow" /></a>
        </div>
    </div>
</article>
