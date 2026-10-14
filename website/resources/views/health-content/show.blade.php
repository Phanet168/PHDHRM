<x-layout :title="$article->title_km">
    <x-page-banner :image="$article->thumbnailUrl()" :title="$article->title_km" />
    <x-page-header :title="$article->title_km" :breadcrumbs="[
        ['label' => 'អប់រំសុខភាព', 'url' => route('health-content.index')],
        ['label' => $article->title_km],
    ]" />

    <main class="health-show-page public-container">
        <article class="health-show-article">
            <p class="health-show-category">{{ $article->category?->name_km ?? 'អប់រំសុខភាព' }}</p>
            <h1 class="health-show-title">{{ $article->title_km }}</h1>
            <p class="health-show-date">{{ \App\Support\KhmerDate::format($article->published_at) }}</p>

            @if ($article->hasVideo())
                <x-health-video-embed :embed="$article->videoEmbed()" />
            @endif

            @if ($article->excerpt)
                <p class="health-show-excerpt">{{ $article->excerpt }}</p>
            @endif

            <div class="health-show-content">{{ $article->content }}</div>

            @if ($article->educational_message)
                <div class="health-show-message">
                    <p class="health-show-message-title">សារអប់រំសុខភាព</p>
                    <p>{{ $article->educational_message }}</p>
                </div>
            @endif

            @if ($article->source_name)
                <p class="health-show-source">
                    ប្រភព៖ {{ $article->source_name }}
                    @if ($article->source_video_url)
                        · <a href="{{ $article->source_video_url }}" target="_blank" rel="noopener">មើលនៅ YouTube</a>
                    @endif
                </p>
            @endif
        </article>

        @if ($related->isNotEmpty())
            <section class="health-show-related">
                <h2>អត្ថបទពាក់ព័ន្ធ</h2>
                <div class="health-index-grid">
                    @foreach ($related as $item)
                        <a href="{{ route('health-content.show', $item) }}" class="health-index-card">
                            <div class="health-index-card-image">
                                <img src="{{ $item->thumbnailUrl() }}" alt="{{ $item->title_km }}" loading="lazy">
                                @if ($item->hasVideo())
                                    <span class="health-index-card-play" aria-hidden="true">▶</span>
                                @endif
                            </div>
                            <div class="health-index-card-body">
                                <p class="health-index-card-category">{{ $item->category?->name_km ?? 'អប់រំសុខភាព' }}</p>
                                <p class="health-index-card-title">{{ $item->title_km }}</p>
                                <span class="health-index-card-date">{{ \App\Support\KhmerDate::format($item->published_at) }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </main>
</x-layout>
