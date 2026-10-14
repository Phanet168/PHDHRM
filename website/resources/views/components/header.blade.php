@php
    $headerMenu = \App\Models\Menu::tree('header');
    $mobileMenu = \App\Models\Menu::tree('mobile');
    $healthMessages = \Illuminate\Support\Facades\Cache::remember(
        'health-messages.active', now()->addMinutes(15),
        fn () => \App\Models\HealthMessage::active()->orderBy('sort_order')->orderBy('id')->get()
    );
@endphp

{{-- Top bar and main bar are pinned together while the page scrolls (see header.css / sticky-header.js). --}}
<div class="public-sticky-header" data-sticky-header>
<div class="public-header-topbar bg-brand-navy text-[15px] text-white">
    <div class="public-container public-header-topbar-inner">
        @if ($healthMessages->isEmpty())
            <div class="public-header-ticker">
                <p class="public-header-ticker-item is-static font-bold">
                    <span class="public-header-ticker-icon">{!! \App\Support\HealthMessageIcon::svg('HeartPulse') !!}</span>
                    <span>សុខភាពល្អ</span>
                </p>
            </div>
        @elseif ($healthMessages->count() === 1)
            @php $only = $healthMessages->first(); @endphp
            <div class="public-header-ticker">
                <p class="public-header-ticker-item is-static font-bold">
                    <span class="public-header-ticker-icon" @if ($only->accent_color) style="color: {{ $only->accent_color }}" @endif>{!! \App\Support\HealthMessageIcon::svg($only->icon) !!}</span>
                    <span>{{ $only->message_km }}</span>
                </p>
            </div>
        @else
            <div class="public-header-ticker" x-data="{ index: 0 }" x-init="setInterval(() => { index = (index + 1) % {{ $healthMessages->count() }} }, 6000)">
                @foreach ($healthMessages as $i => $msg)
                    <p class="public-header-ticker-item font-bold" x-show="index === {{ $i }}" x-cloak
                       x-transition:enter="public-header-ticker-enter" x-transition:enter-start="public-header-ticker-enter-start" x-transition:enter-end="public-header-ticker-enter-end"
                       x-transition:leave="public-header-ticker-leave" x-transition:leave-start="public-header-ticker-leave-start" x-transition:leave-end="public-header-ticker-leave-end">
                        <span class="public-header-ticker-icon" @if ($msg->accent_color) style="color: {{ $msg->accent_color }}" @endif>{!! \App\Support\HealthMessageIcon::svg($msg->icon) !!}</span>
                        <span>{{ $msg->message_km }}</span>
                    </p>
                @endforeach
            </div>
        @endif
        <div class="public-header-contact flex items-center gap-[10px] md:gap-[18px] min-w-0">
            <p class="show-from-md">☎ {{ config('site.phone') }}</p>
            <p class="show-from-md">✉ {{ config('site.email') }}</p>
            <p class="shrink-0">ខ្មែរ&nbsp;&nbsp;|&nbsp;&nbsp;EN</p>
            @if (config('site.hrm_url'))
                <a href="{{ config('site.hrm_url') }}" target="_blank" rel="noopener" class="show-from-md bg-brand-blue rounded-full px-3 py-[3px] font-bold whitespace-nowrap">ចូលប្រព័ន្ធ HRM</a>
            @endif
        </div>
    </div>
</div>

<header x-data="{ mobileOpen: false }" @keydown.escape.window="mobileOpen = false" class="public-header">
    <div class="public-container public-header-inner">
        <a href="{{ route('home') }}" class="public-header-brand">
            <x-organization-logo class="public-header-logo" />
            <div class="public-header-brand-text">
                <span class="public-header-name-km" title="{{ config('site.name_km') }}">{{ config('site.name_km') }}</span>
                <span class="public-header-name-en" lang="en" title="{{ config('site.name_en') }}">{{ config('site.name_en') }}</span>
            </div>
        </a>

        <nav class="public-header-nav" aria-label="ម៉ឺនុយមេ">
            @foreach ($headerMenu as $item)
                <x-nav-desktop-item :item="$item" />
            @endforeach
            <a href="{{ route('facilities.index') }}" class="bg-brand-blue flex items-center justify-center rounded-full size-9 shrink-0" aria-label="ស្វែងរក">
                <span class="font-bold text-[18px] text-white">⌕</span>
            </a>
        </nav>

        <button type="button" @click="mobileOpen = !mobileOpen" class="public-header-toggle" aria-label="ម៉ឺនុយ" aria-controls="public-header-mobile-menu" :aria-expanded="mobileOpen">
            <svg x-show="!mobileOpen" xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
            <svg x-show="mobileOpen" x-cloak xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <line x1="5" y1="5" x2="19" y2="19"></line>
                <line x1="19" y1="5" x2="5" y2="19"></line>
            </svg>
        </button>
    </div>

    <div id="public-header-mobile-menu" x-show="mobileOpen" x-cloak @click.outside="mobileOpen = false"
         @click="if ($event.target.closest('a')) mobileOpen = false"
         class="public-header-mobile-menu">
        @forelse ($mobileMenu as $item)
            <x-nav-mobile-item :item="$item" />
        @empty
            @foreach ($headerMenu as $item)
                <x-nav-mobile-item :item="$item" />
            @endforeach
        @endforelse
        <div class="public-header-mobile-actions">
            <a href="{{ route('facilities.index') }}">ស្វែងរក</a>
            @if (config('site.hrm_url'))
                <a href="{{ config('site.hrm_url') }}" target="_blank" rel="noopener" class="public-header-mobile-hrm">ចូលប្រព័ន្ធ HRM</a>
            @endif
        </div>
    </div>
</header>
</div>
