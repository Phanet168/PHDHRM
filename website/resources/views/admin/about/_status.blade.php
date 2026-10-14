<div class="flex flex-wrap gap-1">
    <span class="{{ $active ? 'bg-brand-green-tint text-brand-green' : 'bg-gray-100 text-gray-500' }} inline-flex rounded-full px-3 py-1 text-[12px] font-bold whitespace-nowrap">{{ $active ? 'បើកប្រើ' : 'បិទប្រើ' }}</span>
    @if ($demo)<span class="bg-amber-100 text-amber-700 inline-flex rounded-full px-3 py-1 text-[12px] font-bold">DEMO</span>@endif
</div>
