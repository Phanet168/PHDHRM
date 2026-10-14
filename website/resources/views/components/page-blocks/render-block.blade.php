@props(['block'])
@if ($block->is_active)
    @switch($block->block_type)
        @case('heading')
            <x-page-blocks.heading :block="$block" />
            @break
        @case('text')
            <x-page-blocks.text :block="$block" />
            @break
        @case('image')
            <x-page-blocks.image :block="$block" />
            @break
        @case('image_text')
            <x-page-blocks.image-text :block="$block" />
            @break
        @case('gallery')
            <x-page-blocks.gallery :block="$block" />
            @break
        @case('button')
            <x-page-blocks.button :block="$block" />
            @break
        @case('two_columns')
            <x-page-blocks.two-columns :block="$block" />
            @break
        @case('cards')
            <x-page-blocks.cards :block="$block" />
            @break
        @case('video')
            <x-page-blocks.video :block="$block" />
            @break
        @case('quote')
            <x-page-blocks.quote :block="$block" />
            @break
        @case('statistics')
            <x-page-blocks.statistics :block="$block" />
            @break
        @case('contact_info')
            <x-page-blocks.contact-info :block="$block" />
            @break
        @case('map')
            <x-page-blocks.map :block="$block" />
            @break
        @case('html_css')
            <x-page-blocks.html-css :block="$block" />
            @break
    @endswitch
@endif
