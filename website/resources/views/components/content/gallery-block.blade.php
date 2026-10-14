@props(['block'])
@php
    $imageIds = $block->get('image_ids', []);
    $images = empty($imageIds) ? $block->content->images : $block->content->images->whereIn('id', $imageIds);
@endphp
<x-gallery :images="$images" />
