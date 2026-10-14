@props(['article'])

<div class="article-meta">
    <span class="article-meta-item">
        <x-news-icon name="calendar" />
        {{ \App\Support\KhmerDate::format($article->published_at ?? $article->created_at) }}
    </span>
    @if ($article->author)
        <span class="article-meta-item">
            <x-news-icon name="user" />
            {{ $article->author->name }}
        </span>
    @endif
    <span class="article-meta-item">
        <x-news-icon name="eye" />
        {{ \App\Support\KhmerDate::toKhmerDigits(number_format($article->views_count)) }} ដង
    </span>
</div>
