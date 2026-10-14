@props(['block'])
@php
    $scopedCss = \App\Services\CssScoper::scope($block->get('css'), $block->id);
@endphp
@if ($scopedCss)
    <style>{!! $scopedCss !!}</style>
@endif
<div id="content-block-{{ $block->id }}" class="content-html-css-block">
    {!! $block->get('html') !!}
</div>
