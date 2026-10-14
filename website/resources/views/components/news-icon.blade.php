@props(['name'])
<svg {{ $attributes->merge(['width' => 20, 'height' => 20])->class('news-icon') }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('target')
            <circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>
            @break
        @case('briefcase')
            <rect x="3" y="7" width="18" height="14" rx="2"/><path d="M8 7V4h8v3M3 12a22 22 0 0 0 18 0M10 13h4"/>
            @break
        @case('calendar')
            <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18M8 15h2M14 15h2"/>
            @break
        @case('arrow')
            <path d="M4 12h16m-6-6 6 6-6 6"/>
            @break
        @case('chevron-left')
            <path d="m14 6-6 6 6 6"/>
            @break
        @case('chevron-right')
            <path d="m9 6 6 6-6 6"/>
            @break
        @case('categories')
            <rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>
            @break
        @case('chart')
            <path d="M4 4v16h17M9 15v-4M14 15V7M19 15V4"/>
            @break
        @case('zoom')
            <circle cx="10" cy="10" r="7"/><path d="m21 21-4.3-4.3M10 7v6M7 10h6"/>
            @break
        @case('user')
            <circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.5-7 8-7s8 3 8 7"/>
            @break
        @case('eye')
            <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>
            @break
        @case('pin')
            <path d="M12 22s7-7.5 7-13a7 7 0 1 0-14 0c0 5.5 7 13 7 13Z"/><circle cx="12" cy="9" r="2.5"/>
            @break
        @case('phone')
            <path d="M6.6 10.8a15.7 15.7 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.24 11 11 0 0 0 3.4.55 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1 11 11 0 0 0 .55 3.4 1 1 0 0 1-.25 1L6.6 10.8Z"/>
            @break
        @case('clock')
            <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>
            @break
        @case('arrow-up')
            <path d="M12 20V4m-6 6 6-6 6 6"/>
            @break
        @case('refresh')
            <path d="M20 11A8 8 0 1 0 18.5 16M20 4v7h-7"/>
            @break
        @case('cut')
            <circle cx="6" cy="6" r="2.5"/><circle cx="6" cy="18" r="2.5"/><path d="m8.2 7.5 11.8 9M20 5 8.2 16.5"/>
            @break
        @case('copy')
            <rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"/>
            @break
        @case('paste')
            <rect x="6" y="4" width="12" height="17" rx="2"/><path d="M9 4V3a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v1"/><path d="M9 11h6M9 15h6"/>
            @break
        @case('trash')
            <path d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m-9 0 1 13a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1l1-13"/>
            @break
        @case('move')
            <path d="M12 3v18M3 12h18M6 6l-3 3 3 3M18 6l3 3-3 3M6 18l-3-3 3-3M18 18l3-3-3-3"/>
            @break
        @case('grid')
            <rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="8" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/><rect x="13" y="13" width="8" height="8" rx="1.5"/>
            @break
        @case('list')
            <path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>
            @break
        @case('folder-plus')
            <path d="M3 6a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6Z"/><path d="M12 11v4M10 13h4"/>
            @break
        @case('upload')
            <path d="M12 16V4m0 0-4 4m4-4 4 4M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/>
            @break
        @case('mail')
            <rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 7 8.5 6.5L20.5 7"/>
            @break
        @case('send')
            <path d="M21 3 10 14"/><path d="m21 3-7 18-4-7-7-4Z"/>
            @break
        @case('filter')
            <path d="M4 5h16l-6 7.5V19l-4 2v-8.5L4 5Z"/>
            @break
        @case('pencil')
            <path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>
            @break
        @case('download')
            <path d="M12 4v12m0 0-4-4m4 4 4-4M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/>
            @break
        @case('external')
            <path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>
            @break
        @case('search')
            <circle cx="10" cy="10" r="6.5"/><path d="m20 20-4.3-4.3"/>
            @break
        @case('close')
            <path d="M5 5l14 14M19 5 5 19"/>
            @break
        @case('check')
            <path d="m4 12 6 6 10-12"/>
            @break
        @case('play')
            <path d="M7 5.5v13l11-6.5Z" fill="currentColor" stroke="none"/>
            @break
        @case('link')
            <path d="M9.5 14.5 14.5 9.5M8 17H6a4 4 0 0 1 0-8h2m8 8h2a4 4 0 0 0 0-8h-2"/>
            @break
        @case('heart')
            <path d="M12 20.5s-7.5-4.6-10-9.3C.6 8 2 4.5 5.4 3.8c2-.4 3.8.5 4.9 2.1.8-1.6 2.6-2.5 4.6-2.1 3.4.7 4.8 4.2 3.4 7.4-2.5 4.7-10 9.3-10 9.3Z" style="stroke-linejoin:round"/>
            @break
        @case('document')
            <rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>
            @break
        @case('users')
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            @break
        @case('heart-pulse')
            <path d="M19 14c1.5-1.5 3-3.2 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.8 0-3 .5-4.5 2-1.5-1.5-2.7-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4 3 5.5l7 7Z"/><path d="M3.2 8.5h2.6l1.5-2 3 4.5 1.5-2h5.5"/>
            @break
        @case('hospital')
            <rect x="4" y="3" width="16" height="18" rx="1.5"/><path d="M12 8v6M9 11h6"/><path d="M8 21v-3h2v3M14 21v-3h2v3"/>
            @break
        @case('shield')
            <path d="M12 2 4 5v6c0 5 3.5 9 8 11 4.5-2 8-6 8-11V5Z"/>
            @break
        @case('stethoscope')
            <path d="M5 3v5a4 4 0 0 0 4 4h0a4 4 0 0 0 4-4V3"/><path d="M9 12v3a6 6 0 0 0 12 0v-2"/><circle cx="19" cy="11" r="2"/><path d="M5 3H3M9 3H7"/>
            @break
        @case('trending-up')
            <path d="M3 17 9 11l4 4 8-8"/><path d="M14 6h7v7"/>
            @break
        @case('monitor')
            <rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8M12 17v4"/>
            @break
        @case('globe')
            <circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.7 4 6 4 9s-1.5 6.3-4 9c-2.5-2.7-4-6-4-9s1.5-6.3 4-9Z"/>
            @break
        @default
            <rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>
    @endswitch
</svg>
