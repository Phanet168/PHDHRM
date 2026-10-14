@props(['block'])
@php
    $src = $block->get('image') ? \Illuminate\Support\Facades\Storage::disk('public')->url($block->get('image')) : null;
    $imageFirst = $block->get('image_position', 'left') !== 'right';
@endphp
<div class="page-block-image-text {{ $imageFirst ? '' : 'is-reversed' }}">
    @if ($src)
        <div class="page-block-image-text-media">
            <img src="{{ $src }}" alt="{{ $block->get('alt_text') }}" loading="lazy">
        </div>
    @endif
    <div class="page-block-image-text-body article-prose">
        @if ($block->get('heading'))
            <h3>{{ $block->get('heading') }}</h3>
        @endif
        {!! $block->get('html') !!}
    </div>
</div>
