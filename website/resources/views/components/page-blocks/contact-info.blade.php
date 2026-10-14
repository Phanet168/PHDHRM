@props(['block'])
<div class="page-block-contact-info">
    @if ($block->get('address'))
        <div class="page-block-contact-row"><x-news-icon name="pin" /><span>{{ $block->get('address') }}</span></div>
    @endif
    @if ($block->get('phone'))
        <div class="page-block-contact-row"><x-news-icon name="phone" /><a href="tel:{{ preg_replace('/[^0-9+]/', '', $block->get('phone')) }}">{{ $block->get('phone') }}</a></div>
    @endif
    @if ($block->get('email'))
        <div class="page-block-contact-row"><x-news-icon name="link" /><a href="mailto:{{ $block->get('email') }}">{{ $block->get('email') }}</a></div>
    @endif
    @if ($block->get('hours'))
        <div class="page-block-contact-row"><x-news-icon name="clock" /><span>{{ $block->get('hours') }}</span></div>
    @endif
</div>
