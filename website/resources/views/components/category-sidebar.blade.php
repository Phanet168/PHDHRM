@props(['categories', 'current' => null])

<section class="news-sidebar-card" aria-labelledby="content-categories-heading">
    <h2 class="news-sidebar-title" id="content-categories-heading"><x-news-icon name="categories" />ប្រភេទព័ត៌មាន</h2>
    <nav class="news-category-menu" aria-label="ប្រភេទព័ត៌មាន">
        @foreach ($categories as $category)
            <a href="{{ route('category.show', $category) }}" class="news-category-link {{ $current === $category->id ? 'is-active' : '' }}" @if ($current === $category->id) aria-current="page" @endif>
                <span>{{ $category->name_km }}</span>
                <span class="news-category-count">{{ \App\Support\KhmerDate::toKhmerDigits((string) $category->contents_count) }}</span>
                <x-news-icon name="chevron-right" />
            </a>
        @endforeach
    </nav>
</section>
