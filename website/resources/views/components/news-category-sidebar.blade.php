@props(['categories', 'publishedCount', 'current' => '', 'enhanced' => false])
@php
    // The redesigned index gives every category a stable icon + soft tint (by position); other pages keep the plain list.
    $categoryIcons = ['heart-pulse', 'hospital', 'users', 'stethoscope', 'shield', 'trending-up', 'globe', 'document'];
    $activeName = $categories->firstWhere('slug', $current)?->name_km ?? 'ព័ត៌មានទាំងអស់';
@endphp

<section class="news-sidebar-card {{ $enhanced ? 'is-enhanced' : '' }}" aria-labelledby="categories-heading" @if ($enhanced) x-data="{ open: false }" :class="{ 'is-open': open }" @endif>
    @if ($enhanced)
        <div class="news-sidebar-title">
            <h2 id="categories-heading"><x-news-icon name="categories" />ប្រភេទព័ត៌មាន</h2>
            <button type="button" class="news-category-toggle" aria-controls="news-category-menu" :aria-expanded="open.toString()" aria-expanded="false" @click="open = ! open">
                <span>{{ $activeName }}</span><x-news-icon name="chevron-right" />
            </button>
        </div>
    @else
        <h2 class="news-sidebar-title" id="categories-heading"><x-news-icon name="categories" />ប្រភេទព័ត៌មាន</h2>
    @endif
    <nav class="news-category-menu" id="news-category-menu" aria-label="ប្រភេទព័ត៌មាន">
        <a href="{{ route('news.index') }}#articles" class="news-category-link {{ $current === '' ? 'is-active' : '' }}" @if ($current === '') aria-current="page" @endif>
            @if ($enhanced)<span class="news-category-icon is-tone-all"><x-news-icon name="categories" /></span>@endif
            <span>ព័ត៌មានទាំងអស់</span><span class="news-category-count">{{ \App\Support\KhmerDate::toKhmerDigits((string) $publishedCount) }}</span><x-news-icon name="chevron-right" />
        </a>
        @foreach ($categories as $category)
            <a href="{{ route('news.index', ['category' => $category->slug]) }}#articles" class="news-category-link {{ $current === $category->slug ? 'is-active' : '' }}" @if ($current === $category->slug) aria-current="page" @endif>
                @if ($enhanced)<span class="news-category-icon is-tone-{{ $loop->index % 6 }}"><x-news-icon :name="$categoryIcons[$loop->index % count($categoryIcons)]" /></span>@endif
                <span>{{ $category->name_km }}</span><span class="news-category-count">{{ \App\Support\KhmerDate::toKhmerDigits((string) $category->news_count) }}</span><x-news-icon name="chevron-right" />
            </a>
        @endforeach
    </nav>
</section>
