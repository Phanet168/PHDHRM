@props(['block'])
@php $cards = $block->get('cards', []); @endphp
@if (! empty($cards))
    <div class="page-block-cards">
        @foreach ($cards as $card)
            @php $tag = ! empty($card['link_url']) ? 'a' : 'div'; @endphp
            @if ($tag === 'a')
                <a href="{{ $card['link_url'] }}" class="page-block-card">
                    @if (! empty($card['title']))<p class="page-block-card-title">{{ $card['title'] }}</p>@endif
                    @if (! empty($card['text']))<p class="page-block-card-text">{{ $card['text'] }}</p>@endif
                </a>
            @else
                <div class="page-block-card">
                    @if (! empty($card['title']))<p class="page-block-card-title">{{ $card['title'] }}</p>@endif
                    @if (! empty($card['text']))<p class="page-block-card-text">{{ $card['text'] }}</p>@endif
                </div>
            @endif
        @endforeach
    </div>
@endif
