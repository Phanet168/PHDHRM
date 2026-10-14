@props(['block'])
@php
    $src = $block->get('image') ? \Illuminate\Support\Facades\Storage::disk('public')->url($block->get('image')) : null;
@endphp
@if ($src)
    <figure class="flex flex-col gap-2">
        @if ($block->get('link_url'))
            <a href="{{ $block->get('link_url') }}">
                <img src="{{ $src }}" alt="{{ $block->get('alt_text') }}" class="w-full rounded-[10px] object-cover">
            </a>
        @else
            <img src="{{ $src }}" alt="{{ $block->get('alt_text') }}" class="w-full rounded-[10px] object-cover">
        @endif
        @if ($block->get('caption'))
            <figcaption class="text-muted text-[13px] text-center">{{ $block->get('caption') }}</figcaption>
        @endif
    </figure>
@endif
