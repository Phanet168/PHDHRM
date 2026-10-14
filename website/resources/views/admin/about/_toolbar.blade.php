{{-- List header shared by the About lists: heading, trash switch and "+ បន្ថែម". --}}
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <div>
        <p class="font-bold text-brand-navy text-[16px]">{{ $heading }}@if ($trashed) · ធុងសំរាម @endif</p>
        @if (! empty($hint))<p class="text-muted text-[12px] mt-1">{{ $hint }}</p>@endif
    </div>
    <div class="flex flex-wrap items-center gap-2">
        @if ($trashed)
            <a href="{{ $indexUrl }}" class="text-brand-blue text-[13px] font-bold px-3 h-10 inline-flex items-center">← ត្រឡប់ទៅបញ្ជី</a>
        @else
            @if ($trashedCount > 0)
                <a href="{{ $indexUrl }}?trashed=1" class="text-muted text-[13px] font-bold px-3 h-10 inline-flex items-center">ធុងសំរាម ({{ $trashedCount }})</a>
            @endif
            {{ $extra ?? '' }}
            <a href="{{ $createUrl }}" class="bg-brand-green text-white font-bold text-[13px] rounded-[8px] h-10 px-5 inline-flex items-center whitespace-nowrap">+ បន្ថែម</a>
        @endif
    </div>
</div>
