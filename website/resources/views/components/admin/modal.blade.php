@props(['name', 'title' => null, 'maxWidth' => '520px'])

<div x-data="{ open: false }" @open-modal-{{ $name }}.window="open = true" @close-modal-{{ $name }}.window="open = false">
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center px-4">
        <div class="absolute inset-0 bg-black/40" @click="open = false"></div>
        <div class="relative bg-white rounded-[12px] shadow-[0px_10px_30px_0px_rgba(0,0,0,0.15)] w-full p-6" style="max-width: {{ $maxWidth }}">
            <div class="flex items-center justify-between mb-4">
                <p class="font-bold text-brand-navy text-[17px]">{{ $title }}</p>
                <button type="button" @click="open = false" class="text-muted hover:text-ink text-[18px]">✕</button>
            </div>
            {{ $slot }}
        </div>
    </div>
</div>
