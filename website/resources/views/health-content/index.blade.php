<x-layout title="មាតិកាអប់រំសុខភាព">
    <x-page-banner page="health-content" title="មាតិកាអប់រំសុខភាព" />
    <x-page-header title="មាតិកាអប់រំសុខភាព" />

    <main class="health-index-page public-container">
        <aside class="health-index-sidebar">
            <h2>ប្រភេទអប់រំសុខភាព</h2>
            <nav class="health-index-category-list">
                <a href="{{ route('health-content.index') }}" class="health-index-category-link {{ ! $activeCategory ? 'is-active' : '' }}">
                    <span>ទាំងអស់</span>
                    <span class="health-index-category-count">{{ $publishedCount }}</span>
                </a>
                @foreach ($categories as $category)
                    <a href="{{ route('health-content.index', ['category' => $category->slug]) }}" class="health-index-category-link {{ $activeCategory?->id === $category->id ? 'is-active' : '' }}">
                        <span>{{ $category->name_km }}</span>
                        <span class="health-index-category-count">{{ $category->contents_count }}</span>
                    </a>
                @endforeach
            </nav>
        </aside>

        <section class="health-index-results">
            @if ($articles->isEmpty())
                <p class="health-index-empty">មិនមានមាតិកាទេ។</p>
            @else
                <div class="health-index-grid">
                    @foreach ($articles as $article)
                        <a href="{{ route('health-content.show', $article) }}" class="health-index-card">
                            <div class="health-index-card-image">
                                <img src="{{ $article->thumbnailUrl() }}" alt="{{ $article->title_km }}" loading="lazy">
                                @if ($article->hasVideo())
                                    <span class="health-index-card-play" aria-hidden="true">▶</span>
                                @endif
                            </div>
                            <div class="health-index-card-body">
                                <p class="health-index-card-category">{{ $article->category?->name_km ?? 'អប់រំសុខភាព' }}</p>
                                <p class="health-index-card-title">{{ $article->title_km }}</p>
                                @if ($article->excerpt)
                                    <p class="health-index-card-excerpt">{{ \Illuminate\Support\Str::limit($article->excerpt, 90) }}</p>
                                @endif
                                <span class="health-index-card-date">{{ \App\Support\KhmerDate::format($article->published_at) }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
                <div class="mt-6">{{ $articles->links() }}</div>
            @endif
        </section>
    </main>
</x-layout>
