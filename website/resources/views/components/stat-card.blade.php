@props(['value', 'label'])

<div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] flex flex-col items-center justify-center gap-[10px] h-[110px] w-full">
    <p class="font-bold text-brand-green text-[28px]">{{ $value }}</p>
    <p class="text-muted text-[13px]">{{ $label }}</p>
</div>
