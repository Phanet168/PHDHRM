@props(['articles'])

<section class="news-sidebar-card" aria-labelledby="latest-news-heading">
    <h2 class="news-sidebar-title" id="latest-news-heading"><x-news-icon name="document" />ព័ត៌មានថ្មីៗ</h2>
    <div class="flex flex-col divide-y divide-line px-4">
        @foreach ($articles as $article)
            <a href="{{ route('news.show', $article) }}" class="flex items-center gap-3 py-3">
                <div class="shrink-0 w-[60px] h-[60px] rounded-[8px] overflow-hidden bg-brand-blue-tint">
                    <img src="{{ $article->featuredImageUrl() }}" alt="{{ $article->featuredImageAlt() }}" class="w-full h-full object-cover">
                </div>
                <div class="min-w-0">
                    <p class="font-bold text-brand-navy text-[14px] leading-snug line-clamp-2">{{ $article->title_km }}</p>
                    <p class="text-muted text-[12px] mt-1">{{ \App\Support\KhmerDate::format($article->published_at) }}</p>
                </div>
            </a>
        @endforeach
    </div>
    <a href="{{ route('news.index') }}" class="flex items-center justify-center gap-2 py-3 border-t border-line text-brand-blue font-bold text-[13px]">
        មើលទាំងអស់ <x-news-icon name="arrow" class="size-4" />
    </a>
</section>
