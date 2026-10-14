@props(['url', 'title' => ''])

@php
    $encodedUrl = urlencode($url);
    $encodedTitle = urlencode($title);
@endphp

<div class="flex items-center gap-3 flex-wrap" x-data="{ copied: false }">
    <span class="text-muted text-[13px] font-bold">ចែករំលែក៖</span>

    <a href="https://www.facebook.com/sharer/sharer.php?u={{ $encodedUrl }}" target="_blank" rel="noopener noreferrer"
       aria-label="ចែករំលែកទៅ Facebook"
       class="bg-[#1877F2] flex items-center justify-center rounded-full size-9 text-white hover:opacity-90 shrink-0">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M22 12.06C22 6.48 17.52 2 11.94 2S1.88 6.48 1.88 12.06c0 5.02 3.66 9.18 8.44 9.94v-7.03H7.9v-2.91h2.42V9.91c0-2.39 1.42-3.71 3.6-3.71 1.04 0 2.13.19 2.13.19v2.35h-1.2c-1.18 0-1.55.74-1.55 1.5v1.8h2.64l-.42 2.91h-2.22V22c4.78-.76 8.44-4.92 8.44-9.94Z"/>
        </svg>
    </a>

    <a href="https://t.me/share/url?url={{ $encodedUrl }}&text={{ $encodedTitle }}" target="_blank" rel="noopener noreferrer"
       aria-label="ចែករំលែកទៅ Telegram"
       class="bg-[#26A5E4] flex items-center justify-center rounded-full size-9 text-white hover:opacity-90 shrink-0">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M21.9 4.6c.3-1.2-.9-2.2-2-1.7L2.4 10.7c-1.2.5-1.1 2.2.1 2.6l4.4 1.4 1.7 5.3c.2.7 1.1.9 1.6.4l2.4-2.3 4.5 3.3c.9.7 2.2.2 2.4-.9l3.4-16Z"/>
        </svg>
    </a>

    <button type="button" @click="
            navigator.clipboard.writeText('{{ $url }}');
            copied = true;
            setTimeout(() => copied = false, 2000);
        "
        aria-label="ចម្លងតំណ"
        class="bg-surface border border-line flex items-center justify-center rounded-full size-9 text-ink hover:bg-brand-blue-tint hover:text-brand-blue shrink-0">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
        </svg>
    </button>

    <span x-show="copied" x-cloak x-transition class="text-brand-green text-[12px] font-bold">បានចម្លងតំណ!</span>
</div>
