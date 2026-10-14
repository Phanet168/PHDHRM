@props(['articles', 'duration' => 30])

@if ($articles->isNotEmpty())
    <div class="news-ticker bg-brand-blue flex items-stretch overflow-hidden">
        <div class="bg-brand-blue-dark shrink-0 flex items-center px-5 md:px-7">
            <span class="font-bold text-white text-[14px] md:text-[15px] whitespace-nowrap">ព័ត៌មានថ្មី</span>
        </div>

        <div class="news-ticker-viewport relative flex-1 overflow-hidden"
             x-data="{ paused: false }"
             @mouseenter="paused = true" @mouseleave="paused = false"
             @touchstart="paused = true" @touchend="paused = false" @touchcancel="paused = false">
            <div class="news-ticker-track flex items-center gap-8 py-3 px-6" :class="paused && 'news-ticker-paused'" style="animation-duration: {{ $duration }}s;">
                @foreach ([0, 1] as $copy)
                    @foreach ($articles as $article)
                        <a href="{{ route('news.show', $article) }}" class="font-bold text-white text-[14px] md:text-[15px] whitespace-nowrap hover:underline">
                            {{ $article->title_km }}
                        </a>
                        <span class="text-white/50 text-[10px] shrink-0" aria-hidden="true">●</span>
                    @endforeach
                @endforeach
            </div>
        </div>
    </div>
@endif
