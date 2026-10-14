@props(['block'])
@php
    $level = in_array($block->get('level'), ['h1', 'h2', 'h3', 'h4'], true) ? $block->get('level') : 'h2';
@endphp
<{{ $level }} class="article-heading">{{ $block->get('text') }}</{{ $level }}>
