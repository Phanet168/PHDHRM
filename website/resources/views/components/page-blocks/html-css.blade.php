@props(['block'])
@php
    $scopedCss = \App\Services\CssScoper::scope($block->get('css'), $block->id, 'page-block');
@endphp
@if ($scopedCss)
    <style>{!! $scopedCss !!}</style>
@endif
<div id="page-block-{{ $block->id }}" class="page-html-css-block">
    {!! $block->get('html') !!}
</div>
