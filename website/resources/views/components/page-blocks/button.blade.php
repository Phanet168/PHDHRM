@props(['block'])
@php
    $styles = [
        'primary' => 'bg-brand-blue text-white hover:bg-brand-blue-dark',
        'secondary' => 'bg-brand-green text-white hover:opacity-90',
        'outline' => 'border-2 border-brand-blue text-brand-blue hover:bg-brand-blue-tint',
    ];
    $style = $styles[$block->get('style', 'primary')] ?? $styles['primary'];
@endphp
<a href="{{ $block->get('url', '#') }}" target="{{ $block->get('target', '_self') }}" rel="{{ $block->get('target') === '_blank' ? 'noopener' : '' }}"
   class="{{ $style }} inline-flex items-center justify-center font-bold text-[15px] rounded-[8px] h-11 px-6">
    {{ $block->get('text') }}
</a>
