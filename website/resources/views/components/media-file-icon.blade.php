@props(['type' => 'file', 'size' => 36])
<svg {{ $attributes->class('media-file-icon-svg') }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" aria-hidden="true">
    @switch($type)
        @case('pdf')
            <path fill="#EF4444" d="M6 2h8l5 5v14a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1Z"/>
            <path fill="#fca5a5" d="M14 2v5h5"/>
            <text x="12" y="17.5" font-size="6.5" font-weight="700" fill="#fff" text-anchor="middle" font-family="Arial, sans-serif">PDF</text>
            @break
        @case('word')
            <path fill="#2563EB" d="M6 2h8l5 5v14a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1Z"/>
            <path fill="#93c5fd" d="M14 2v5h5"/>
            <text x="12" y="17.5" font-size="6" font-weight="700" fill="#fff" text-anchor="middle" font-family="Arial, sans-serif">DOC</text>
            @break
        @case('excel')
            <path fill="#0EA962" d="M6 2h8l5 5v14a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1Z"/>
            <path fill="#86efac" d="M14 2v5h5"/>
            <text x="12" y="17.5" font-size="6" font-weight="700" fill="#fff" text-anchor="middle" font-family="Arial, sans-serif">XLS</text>
            @break
        @case('video')
            <rect x="2" y="4" width="20" height="16" rx="3" fill="#7C3AED"/>
            <path fill="#fff" d="M10 9.2v5.6l5-2.8Z"/>
            @break
        @default
            <path fill="#94A3B8" d="M6 2h8l5 5v14a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1Z"/>
            <path fill="#cbd5e1" d="M14 2v5h5"/>
    @endswitch
</svg>
