<?php

namespace App\Support;

/**
 * Whitelisted icon keys and accents for About-page content. Admins pick a key;
 * the public page renders the matching built-in <x-news-icon>, so no stored
 * SVG or HTML ever reaches the page.
 */
class AboutIcon
{
    public const ICONS = [
        'eye' => 'ភ្នែក (ចក្ខុវិស័យ)',
        'target' => 'គោលដៅ (បេសកកម្ម)',
        'briefcase' => 'កាបូប (តួនាទី)',
        'shield' => 'ខែល (គោលតម្លៃ)',
        'heart' => 'បេះដូង',
        'heart-pulse' => 'ជីពចរ',
        'hospital' => 'មន្ទីរពេទ្យ',
        'stethoscope' => 'ឧបករណ៍ស្ដាប់',
        'users' => 'មនុស្ស',
        'user' => 'បុគ្គល',
        'document' => 'ឯកសារ',
        'calendar' => 'ប្រតិទិន',
        'clock' => 'នាឡិកា',
        'trending-up' => 'ការអភិវឌ្ឍ',
        'globe' => 'សកល',
        'check' => 'សញ្ញាធីក',
        'pin' => 'ទីតាំង',
    ];

    public const ACCENTS = [
        'blue' => 'ខៀវ',
        'green' => 'បៃតង',
        'gold' => 'មាស',
        'purple' => 'ស្វាយ',
        'teal' => 'ផ្ទៃមេឃ',
    ];

    public static function key(?string $key, string $fallback = 'document'): string
    {
        return array_key_exists((string) $key, self::ICONS) ? $key : $fallback;
    }

    public static function accent(?string $accent, string $fallback = 'blue'): string
    {
        return array_key_exists((string) $accent, self::ACCENTS) ? $accent : $fallback;
    }
}
