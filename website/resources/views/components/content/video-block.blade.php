@props(['block'])
@php $embed = \App\Models\ContentBlock::videoEmbed($block->get('url')); @endphp
@if ($embed)
    <figure class="flex flex-col gap-2">
        <div class="relative w-full rounded-[10px] overflow-hidden" style="padding-top: 56.25%;">
            @if ($embed['type'] === 'iframe')
                <iframe src="{{ $embed['src'] }}" class="absolute inset-0 size-full" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
            @else
                <video src="{{ $embed['src'] }}" controls class="absolute inset-0 size-full object-cover"></video>
            @endif
        </div>
        @if ($block->get('caption'))
            <figcaption class="text-muted text-[13px] text-center">{{ $block->get('caption') }}</figcaption>
        @endif
    </figure>
@endif
