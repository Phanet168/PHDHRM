@props(['sortKey', 'label', 'currentSort', 'currentDirection' => 'desc', 'filterActive' => false])
@php
    // Route-agnostic: reusable on any admin listing table, not just News. Resets `page` to 1 on
    // every sort click (a re-sorted list starting mid-page would be confusing), keeps every
    // other current filter/search param exactly as-is.
    $isActive = $currentSort === $sortKey;
    $nextDirection = $isActive && $currentDirection === 'asc' ? 'desc' : 'asc';
    $query = array_merge(request()->except(['sort', 'direction', 'page']), ['sort' => $sortKey, 'direction' => $nextDirection]);
    $href = request()->url().'?'.http_build_query($query);
    $hasFilter = $slot->isNotEmpty();
@endphp
<th class="text-left font-bold px-4 py-3 relative" @if ($hasFilter) x-data="{ filterOpen: false }" @click.outside="filterOpen = false" @endif>
    <div class="flex items-center gap-1.5 whitespace-nowrap">
        <a href="{{ $href }}" class="flex items-center gap-1 {{ $isActive ? 'text-brand-blue' : 'text-muted hover:text-ink' }}">
            {{ $label }}
            <span class="text-[10px] leading-none">
                @if ($isActive)
                    {{ $currentDirection === 'asc' ? '▲' : '▼' }}
                @else
                    ⇅
                @endif
            </span>
        </a>

        @if ($hasFilter)
            {{-- Filter stays tucked behind this icon (click to reveal) instead of sitting bare in
                 the header — the icon itself turns blue while a filter is applied, so it stays
                 obvious at a glance even when the popover is closed. --}}
            <button type="button" @click="filterOpen = !filterOpen"
                    class="shrink-0 p-0.5 rounded {{ $filterActive ? 'text-brand-blue' : 'text-muted hover:text-ink' }}"
                    :class="filterOpen ? 'bg-brand-blue-tint text-brand-blue' : ''"
                    title="តម្រងតាម{{ $label }}" aria-label="តម្រងតាម{{ $label }}">
                <x-news-icon name="filter" width="12" height="12" />
            </button>
        @endif
    </div>

    @if ($hasFilter)
        <div x-show="filterOpen" x-cloak @click.stop
             class="absolute z-20 top-full left-0 mt-1 min-w-[160px] bg-white border border-line rounded-[8px] shadow-[0px_6px_16px_0px_rgba(0,0,0,0.12)] p-2 font-normal">
            {{ $slot }}
        </div>
    @endif
</th>
