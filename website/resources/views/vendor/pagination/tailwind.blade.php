@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center justify-between gap-4 flex-wrap">
        <p class="text-muted text-[14px]">
            {{ __('Showing') }}
            <span class="font-bold text-ink">{{ \App\Support\KhmerDate::toKhmerDigits($paginator->firstItem() ?? 0) }}</span>
            {{ __('to') }}
            <span class="font-bold text-ink">{{ \App\Support\KhmerDate::toKhmerDigits($paginator->lastItem() ?? 0) }}</span>
            {{ __('of') }}
            <span class="font-bold text-ink">{{ \App\Support\KhmerDate::toKhmerDigits($paginator->total()) }}</span>
            {{ __('results') }}
        </p>

        <span class="inline-flex items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="flex items-center justify-center size-9 rounded-[8px] border border-line text-muted">‹</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="flex items-center justify-center size-9 rounded-[8px] border border-line text-ink hover:bg-brand-blue-tint">‹</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="flex items-center justify-center size-9 text-muted">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="flex items-center justify-center size-9 rounded-[8px] bg-brand-blue font-bold text-white text-[15px]">{{ \App\Support\KhmerDate::toKhmerDigits($page) }}</span>
                        @else
                            <a href="{{ $url }}" class="flex items-center justify-center size-9 rounded-[8px] border border-line text-ink text-[15px] hover:bg-brand-blue-tint">{{ \App\Support\KhmerDate::toKhmerDigits($page) }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="flex items-center justify-center size-9 rounded-[8px] border border-line text-ink hover:bg-brand-blue-tint">›</a>
            @else
                <span class="flex items-center justify-center size-9 rounded-[8px] border border-line text-muted">›</span>
            @endif
        </span>
    </nav>
@endif
