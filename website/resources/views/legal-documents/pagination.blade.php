@if ($paginator->hasPages())
    <nav class="legal-pagination-links" aria-label="ទំព័រឯកសារ">
        @if ($paginator->onFirstPage())
            <span class="is-disabled" aria-disabled="true"><x-news-icon name="chevron-left" width="18" height="18" /></span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="ទំព័រមុន"><x-news-icon name="chevron-left" width="18" height="18" /></a>
        @endif
        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="legal-pagination-ellipsis">{{ $element }}</span>
            @else
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span aria-current="page" class="is-current">{{ \App\Support\KhmerDate::toKhmerDigits((string) $page) }}</span>
                    @else
                        <a href="{{ $url }}" aria-label="ទំព័រ {{ \App\Support\KhmerDate::toKhmerDigits((string) $page) }}">{{ \App\Support\KhmerDate::toKhmerDigits((string) $page) }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="ទំព័របន្ទាប់"><x-news-icon name="chevron-right" width="18" height="18" /></a>
        @else
            <span class="is-disabled" aria-disabled="true"><x-news-icon name="chevron-right" width="18" height="18" /></span>
        @endif
    </nav>
@endif
