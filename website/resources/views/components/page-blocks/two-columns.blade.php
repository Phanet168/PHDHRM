@props(['block'])
<div class="article-two-col">
    <div class="article-prose">{!! $block->get('left_html') !!}</div>
    <div class="article-prose">{!! $block->get('right_html') !!}</div>
</div>
