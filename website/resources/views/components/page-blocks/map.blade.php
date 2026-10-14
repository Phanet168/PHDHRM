@props(['block'])
@if ($block->get('lat') && $block->get('lng'))
    <div class="page-block-map"
         data-page-map-block
         data-lat="{{ $block->get('lat') }}"
         data-lng="{{ $block->get('lng') }}"
         data-zoom="{{ $block->get('zoom', 14) }}"
         data-marker-title="{{ $block->get('marker_title') }}"></div>
@endif
