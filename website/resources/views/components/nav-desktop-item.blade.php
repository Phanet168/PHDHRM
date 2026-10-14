@props(['item'])

@if ($item->children->isEmpty())
    <a href="{{ $item->resolveUrl() }}" target="{{ $item->target }}"
       class="{{ $item->isActiveLink() ? 'font-bold text-brand-blue' : 'text-ink hover:text-brand-blue' }}">
        @if ($item->icon)<span>{{ $item->icon }}</span>@endif
        {{ $item->label_km }}
    </a>
@else
    <div class="relative" x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false">
        <button @click="open = !open" type="button"
                class="flex items-center gap-1 {{ $item->isActiveLink() ? 'font-bold text-brand-blue' : 'text-ink hover:text-brand-blue' }}">
            @if ($item->icon)<span>{{ $item->icon }}</span>@endif
            {{ $item->label_km }}
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" :class="open ? 'rotate-180' : ''" class="transition-transform">
                <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
        </button>

        <div x-show="open" x-cloak x-transition
             class="absolute top-full left-0 mt-2 bg-white border border-line rounded-[8px] shadow-[0px_10px_20px_0px_rgba(0,0,0,0.1)] py-2 min-w-[220px] z-40">
            @foreach ($item->children as $child)
                <a href="{{ $child->resolveUrl() }}" target="{{ $child->target }}"
                   class="block px-4 py-2 text-[14px] whitespace-nowrap {{ $child->isActiveLink() ? 'font-bold text-brand-blue' : 'text-ink hover:bg-surface hover:text-brand-blue' }}">
                    @if ($child->icon)<span>{{ $child->icon }}</span>@endif
                    {{ $child->label_km }}
                </a>
            @endforeach
        </div>
    </div>
@endif
