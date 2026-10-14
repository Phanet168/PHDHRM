@props(['block'])
<blockquote class="article-quote">
    "{{ $block->get('text') }}"
    @if ($block->get('author'))
        <cite>— {{ $block->get('author') }}</cite>
    @endif
</blockquote>
