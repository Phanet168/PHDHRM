@php
    $isPreview = $isPreview ?? false;
    $isWide = in_array($page->template, ['full_width', 'landing'], true);
@endphp
<x-layout
    :title="$page->title_km"
    :meta-description="$page->metaDescriptionOrFallback()"
    :og-image="$page->featuredMedia ? $page->featuredImageUrl() : null"
    :canonical="$page->canonical_url ?: null"
>
    @if ($isPreview)
        <div class="page-preview-banner">
            មើលសាកល្បង — ស្ថានភាព៖ {{ \App\Models\Page::statusLabel($page->status) }} (មិនមែនជាទំព័រសាធារណៈទេ)
        </div>
    @endif

    <x-page-banner :image="$page->featuredImageUrl()" :title="$page->title_km" />

    @unless ($page->template === 'landing')
        <x-page-header :title="$page->title_km" />
    @endunless

    <main class="page-show {{ $isWide ? 'is-wide' : '' }}">
        @if ($page->excerpt)
            <p class="page-show-excerpt">{{ $page->excerpt }}</p>
        @endif

        @if ($page->blocks->isNotEmpty())
            <div class="page-show-blocks">
                @foreach ($page->blocks as $block)
                    <x-page-blocks.render-block :block="$block" />
                @endforeach
            </div>
        @elseif ($page->content)
            {{-- Legacy pages created before the block builder existed. --}}
            <div class="page-show-legacy-content">{{ $page->content }}</div>
        @endif
    </main>
</x-layout>
