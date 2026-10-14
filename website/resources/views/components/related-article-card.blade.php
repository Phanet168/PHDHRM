@props(['article', 'route' => 'content.show'])

@php
    $imageUrl = method_exists($article, 'featuredImageUrl')
        ? $article->featuredImageUrl()
        : asset($article->featured_image ?: 'images/stock/stock-2.jpg');
@endphp

<a href="{{ route($route, $article) }}" class="news-article-card">
    <div class="news-card-image">
        <img src="{{ $imageUrl }}" alt="{{ $article->title_km }}" loading="lazy">
        @if ($article->category)
            <span class="news-card-badge">{{ $article->category->name_km }}</span>
        @endif
    </div>
    <div class="news-card-body">
        <h3>{{ $article->title_km }}</h3>
        <div class="news-card-footer">
            <time datetime="{{ ($article->published_at ?? $article->created_at)->toDateString() }}">
                <x-news-icon name="calendar" />{{ \App\Support\KhmerDate::format($article->published_at ?? $article->created_at) }}
            </time>
        </div>
    </div>
</a>
