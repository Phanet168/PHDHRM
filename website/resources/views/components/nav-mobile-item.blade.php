@props(['item'])

@if ($item->children->isEmpty())
    <a href="{{ $item->resolveUrl() }}" target="{{ $item->target }}"
       class="px-6 py-3 text-[15px] {{ $item->isActiveLink() ? 'font-bold text-brand-blue' : 'text-ink' }}">
        @if ($item->icon)<span>{{ $item->icon }}</span>@endif
        {{ $item->label_km }}
    </a>
@else
    <div x-data="{ open: false }">
        <button @click="open = !open" type="button"
                class="w-full flex items-center justify-between px-6 py-3 text-[15px] {{ $item->isActiveLink() ? 'font-bold text-brand-blue' : 'text-ink' }}">
            <span>
                @if ($item->icon)<span>{{ $item->icon }}</span>@endif
                {{ $item->label_km }}
            </span>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" :class="open ? 'rotate-180' : ''" class="transition-transform">
                <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
        </button>
        <div x-show="open" x-cloak class="flex flex-col bg-surface">
            @foreach ($item->children as $child)
                <a href="{{ $child->resolveUrl() }}" target="{{ $child->target }}"
                   class="px-10 py-2 text-[14px] {{ $child->isActiveLink() ? 'font-bold text-brand-blue' : 'text-muted' }}">
                    @if ($child->icon)<span>{{ $child->icon }}</span>@endif
                    {{ $child->label_km }}
                </a>
            @endforeach
        </div>
    </div>
@endif
