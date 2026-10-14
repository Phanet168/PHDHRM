@props(['block'])
@if ($block->is_active)
    @switch($block->block_type)
        @case('heading')
            <x-content.heading-block :block="$block" />
            @break
        @case('text')
            <x-content.text-block :block="$block" />
            @break
        @case('image')
            <x-content.image-block :block="$block" />
            @break
        @case('gallery')
            <x-content.gallery-block :block="$block" />
            @break
        @case('button')
            <x-content.button-block :block="$block" />
            @break
        @case('two_columns')
            <x-content.two-columns-block :block="$block" />
            @break
        @case('video')
            <x-content.video-block :block="$block" />
            @break
        @case('quote')
            <x-content.quote-block :block="$block" />
            @break
        @case('html')
            <x-content.html-block :block="$block" />
            @break
        @case('html_css')
            <x-content.html-css-block :block="$block" />
            @break
    @endswitch
@endif
