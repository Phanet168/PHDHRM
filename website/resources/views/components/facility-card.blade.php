@props(['facility'])

<div class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] flex flex-col overflow-hidden">
    <div class="h-[170px] w-full">
        <img src="{{ asset($facility->cover_image ?? 'images/stock/stock-1.jpg') }}" alt="{{ $facility->name_km }}" class="size-full object-cover">
    </div>
    <div class="flex flex-col gap-[7px] px-4 py-4">
        <p class="font-bold text-brand-navy text-[18px]">{{ $facility->name_km }}</p>
        <p class="text-muted text-[13px]">{{ $facility->district }} · {{ config('site.name_en') }}</p>
        @if ($facility->phone)
            <p class="text-ink text-[13px]">☎ {{ $facility->phone }}</p>
        @endif
        <div class="flex gap-2 pt-1">
            <a href="{{ route('facilities.show', $facility) }}" class="bg-brand-blue text-white text-[14px] font-bold rounded-[8px] h-10 px-4 flex items-center justify-center">
                មើលព័ត៌មាន
            </a>
            <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($facility->name_km . ' ' . $facility->district) }}" target="_blank" rel="noopener" class="bg-brand-green text-white text-[14px] font-bold rounded-[8px] h-10 px-4 flex items-center justify-center">
                ទីតាំង
            </a>
        </div>
    </div>
</div>
