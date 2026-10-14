<?php

namespace App\Support;

/**
 * Whitelist-only icon resolver for HealthMessage.icon. The database only
 * ever supplies a lookup KEY (validated against WHITELIST at save time via
 * HealthMessageRequest) — never raw markup — so the SVG returned here is
 * always one of the fixed strings below, safe to echo unescaped.
 */
class HealthMessageIcon
{
    public const WHITELIST = [
        'Salad', 'Dumbbell', 'CigaretteOff', 'WineOff',
        'CupSoda', 'Apple', 'HeartPulse', 'Hospital',
    ];

    private const SVG = [
        'Salad' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h18"/><path d="M4 12a8 8 0 0 0 16 0"/><path d="M12 12V6a3 3 0 0 1 3-3c1 2-1 3-3 3"/><path d="M12 6c-2 0-3.5 1-4 2"/></svg>',
        'Dumbbell' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 5v14"/><path d="M18 5v14"/><path d="M2 9v6"/><path d="M22 9v6"/><path d="M6 12h12"/></svg>',
        'CigaretteOff' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12h13v4H2z"/><path d="M15 12h4a2 2 0 0 1 0 4h-4"/><path d="M18 8c.5 1 .5 2 0 3"/><line x1="3" y1="4" x2="21" y2="20"/></svg>',
        'WineOff' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3h8l-1 7a3 3 0 0 1-6 0z"/><path d="M12 14v6"/><path d="M9 20h6"/><line x1="3" y1="3" x2="21" y2="21"/></svg>',
        'CupSoda' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8h12l-1 12H7z"/><path d="M6 8l-1-3h14l-1 3"/><line x1="15" y1="2" x2="13" y2="8"/></svg>',
        'Apple' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8c-3 0-5 2.5-5 6a6 6 0 0 0 6 6c1 0 1.5-.5 2-.5s1 .5 2 .5a6 6 0 0 0 5-9c-1-1.5-2.5-2-4-2-1 0-1.5.5-2 .5"/><path d="M12 8c0-2 1-3 2.5-3.5"/></svg>',
        'HeartPulse' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-4.5-9.5-9A5.5 5.5 0 0 1 12 6a5.5 5.5 0 0 1 9.5 6c-.6 1-1.3 2-2.2 3H16l-1.5-3-2 5-1.5-3H8"/></svg>',
        'Hospital' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="7" width="16" height="14" rx="1"/><path d="M9 21v-5h6v5"/><line x1="12" y1="10" x2="12" y2="14"/><line x1="10" y1="12" x2="14" y2="12"/><path d="M9 7V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3"/></svg>',
    ];

    public static function svg(?string $name): string
    {
        return self::SVG[$name] ?? self::SVG['HeartPulse'];
    }

    /** [key => key] shape, ready for an admin <select> options list. */
    public static function options(): array
    {
        return array_combine(self::WHITELIST, self::WHITELIST);
    }
}
