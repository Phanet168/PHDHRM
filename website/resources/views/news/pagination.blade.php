@if ($paginator->hasPages())
    <nav class="news-pagination" aria-label="ទំព័រព័ត៌មាន">
        @if ($paginator->onFirstPage())
            <span class="is-disabled" aria-disabled="true"><x-news-icon name="chevron-left" /><span class="news-pagination-label">មុន</span></span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="ទំព័រមុន"><x-news-icon name="chevron-left" /><span class="news-pagination-label">មុន</span></a>
        @endif
        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="news-pagination-ellipsis">{{ $element }}</span>
            @else
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span aria-current="page" class="is-current">{{ \App\Support\KhmerDate::toKhmerDigits((string) $page) }}</span>
                    @else
                        <a href="{{ $url }}" aria-label="ទំព័រ {{ $page }}">{{ \App\Support\KhmerDate::toKhmerDigits((string) $page) }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="ទំព័របន្ទាប់"><span class="news-pagination-label">បន្ទាប់</span><x-news-icon name="chevron-right" /></a>
        @else
            <span class="is-disabled" aria-disabled="true"><span class="news-pagination-label">បន្ទាប់</span><x-news-icon name="chevron-right" /></span>
        @endif
    </nav>
@endif
