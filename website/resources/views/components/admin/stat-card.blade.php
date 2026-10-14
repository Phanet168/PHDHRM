@props(['value', 'label', 'icon' => '●', 'color' => 'text-brand-blue', 'href' => null])

@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] flex items-center gap-4 p-5">
    <div class="bg-brand-blue-tint flex items-center justify-center rounded-[10px] size-[46px] shrink-0">
        <span class="{{ $color }} font-bold text-[19px]">{{ $icon }}</span>
    </div>
    <div>
        <p class="font-bold text-brand-navy text-[24px] leading-tight">{{ $value }}</p>
        <p class="text-muted text-[13px]">{{ $label }}</p>
    </div>
</{{ $tag }}>
