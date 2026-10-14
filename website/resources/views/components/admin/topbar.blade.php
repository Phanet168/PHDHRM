@props(['title' => null])

<header class="bg-white border-b border-line flex items-center justify-between h-[76px] px-6 md:px-8">
    <div class="flex items-center gap-3">
        <p class="font-bold text-brand-navy text-[19px]">{{ $title ?? 'ផ្ទាំងគ្រប់គ្រង' }}</p>
    </div>

    <div x-data="{ open: false }" class="relative">
        <button @click="open = !open" type="button" class="flex items-center gap-2">
            <div class="bg-brand-blue-tint flex items-center justify-center rounded-full size-9">
                <span class="text-brand-blue text-[15px] font-bold">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
            </div>
            <div class="text-left hidden md:block">
                <p class="font-bold text-ink text-[13px] leading-tight">{{ auth()->user()->name }}</p>
                <p class="text-muted text-[11px] leading-tight">{{ auth()->user()->getRoleNames()->first() ?? '—' }}</p>
            </div>
        </button>

        <div x-show="open" @click.outside="open = false" x-cloak
             class="absolute right-0 mt-2 w-[200px] bg-white border border-line rounded-[8px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.1)] py-2 z-20">
            <a href="{{ route('home') }}" class="block px-4 py-2 text-[13px] text-ink hover:bg-surface">មើលគេហទំព័រ</a>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" class="w-full text-left px-4 py-2 text-[13px] text-brand-red hover:bg-surface">ចាកចេញ</button>
            </form>
        </div>
    </div>
</header>
