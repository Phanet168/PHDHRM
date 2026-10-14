@props(['article'])

<div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] flex flex-col overflow-hidden">
    <div class="h-[190px] w-full">
        <img src="{{ $article->featuredImageUrl() }}" alt="{{ $article->title_km }}" class="size-full object-cover">
    </div>
    <div class="flex flex-col gap-[6px] px-4 py-4">
        <p class="font-bold text-brand-blue text-[12px]">{{ $article->category?->name_km ?? 'មាតិកា' }}</p>
        <p class="font-bold text-brand-navy text-[17px] leading-snug">{{ $article->title_km }}</p>
        @if ($article->excerpt)
            <p class="text-muted text-[13px] leading-relaxed line-clamp-2">{{ $article->excerpt }}</p>
        @endif
        <p class="text-muted text-[12px]">{{ \App\Support\KhmerDate::format($article->published_at) }}</p>
        <a href="{{ route('content.show', $article) }}" class="font-bold text-brand-blue text-[13px]">អានបន្ថែម →</a>
    </div>
</div>
