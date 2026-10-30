<?php

namespace Modules\Report\Services;

use InvalidArgumentException;

/**
 * Period codes are "YYYY-TAG" where TAG is M01–M12 (month), Q1–Q4 (quarter), H1–H2 (semester),
 * N9 (nine months) or Y (full year). Legacy PHDReport spellings (2025-01, 2025M01, 2025Q1, 2025-Y12)
 * are accepted and normalised.
 */
class PeriodCode
{
    public const MONTHS = ['មករា', 'កុម្ភៈ', 'មីនា', 'មេសា', 'ឧសភា', 'មិថុនា', 'កក្កដា', 'សីហា', 'កញ្ញា', 'តុលា', 'វិច្ឆិកា', 'ធ្នូ'];

    public const TYPES = [
        'month' => 'ខែ',
        'quarter' => 'ត្រីមាស',
        'half' => 'ឆមាស',
        'nine' => '៩ខែ',
        'year' => '១២ខែ',
    ];

    /** @return array{code:string, year:int, tag:string, type:string} */
    public static function parse(string $code): array
    {
        $s = strtoupper(trim($code));
        if (!preg_match('/^(\d{4})-?(.*)$/', $s, $m)) {
            throw new InvalidArgumentException("Invalid period code: {$code}");
        }
        $year = (int) $m[1];
        $rest = $m[2];

        if (preg_match('/^M?(\d{1,2})$/', $rest, $x) && (int) $x[1] >= 1 && (int) $x[1] <= 12) {
            $tag = 'M' . str_pad((string) (int) $x[1], 2, '0', STR_PAD_LEFT);
        } elseif (preg_match('/^Q([1-4])$/', $rest, $x)) {
            $tag = 'Q' . $x[1];
        } elseif (preg_match('/^(?:H|S)([12])$/', $rest, $x)) {
            $tag = 'H' . $x[1];
        } elseif ($rest === 'N9' || $rest === '9M') { // PHDReport stored nine-month periods as both
            $tag = 'N9';
        } elseif ($rest === '' || $rest === 'Y' || $rest === 'Y12') {
            $tag = 'Y';
        } else {
            throw new InvalidArgumentException("Invalid period code: {$code}");
        }

        return ['code' => "{$year}-{$tag}", 'year' => $year, 'tag' => $tag, 'type' => self::typeOf($tag)];
    }

    public static function make(int $year, string $tag): string
    {
        return self::parse("{$year}-{$tag}")['code'];
    }

    public static function typeOf(string $tag): string
    {
        return match ($tag[0]) {
            'M' => 'month',
            'Q' => 'quarter',
            'H' => 'half',
            'N' => 'nine',
            default => 'year',
        };
    }

    /** Tags belonging to a period type, in calendar order. */
    public static function tagsFor(string $type): array
    {
        return match ($type) {
            'month' => array_map(fn ($m) => 'M' . str_pad((string) $m, 2, '0', STR_PAD_LEFT), range(1, 12)),
            'quarter' => ['Q1', 'Q2', 'Q3', 'Q4'],
            'half' => ['H1', 'H2'],
            'nine' => ['N9'],
            default => ['Y'],
        };
    }

    /** Every period tag of a year (used to generate the period list). */
    public static function allTags(): array
    {
        return array_merge(...array_map([self::class, 'tagsFor'], array_keys(self::TYPES)));
    }

    public static function tagLabel(string $tag): string
    {
        return match (self::typeOf($tag)) {
            'month' => self::MONTHS[(int) substr($tag, 1) - 1],
            'quarter' => 'ត្រីមាសទី' . self::kh(substr($tag, 1)),
            'half' => 'ឆមាសទី' . self::kh(substr($tag, 1)),
            'nine' => '៩ខែ',
            default => 'ឆ្នាំ',
        };
    }

    public static function label(string $code): string
    {
        $p = self::parse($code);

        return self::tagLabel($p['tag']) . ' ' . self::kh($p['year']);
    }

    /** Month codes a quarter / semester / nine-month / year period covers ([] for a month). */
    public static function monthsOf(string $code): array
    {
        $p = self::parse($code);
        $n = (int) preg_replace('/\D/', '', $p['tag']);
        $months = match ($p['type']) {
            'quarter' => range(($n - 1) * 3 + 1, $n * 3),
            'half' => $n === 1 ? range(1, 6) : range(7, 12),
            'nine' => range(1, 9),
            'year' => range(1, 12),
            default => [],
        };

        return array_map(fn ($m) => self::make($p['year'], 'M' . $m), $months);
    }

    /** The period before this one, used by "copy previous period". */
    public static function previous(string $code): string
    {
        $p = self::parse($code);
        $y = $p['year'];
        $n = (int) preg_replace('/\D/', '', $p['tag']);

        return match ($p['type']) {
            'month' => $n === 1 ? ($y - 1) . '-M12' : self::make($y, 'M' . ($n - 1)),
            'quarter' => $n === 1 ? ($y - 1) . '-Q4' : "{$y}-Q" . ($n - 1),
            'half' => $n === 1 ? ($y - 1) . '-H2' : "{$y}-H1",
            default => ($y - 1) . '-' . $p['tag'],
        };
    }

    /** Sort key so periods list as months, quarters, halves, nine-month, year. */
    public static function order(string $tag): int
    {
        $n = (int) preg_replace('/\D/', '', $tag);

        return match (self::typeOf($tag)) {
            'month' => 100 + $n,
            'quarter' => 200 + $n,
            'half' => 300 + $n,
            'nine' => 400,
            default => 500,
        };
    }

    public static function kh(int|string|float|null $value): string
    {
        return strtr((string) $value, ['0' => '០', '1' => '១', '2' => '២', '3' => '៣', '4' => '៤', '5' => '៥', '6' => '៦', '7' => '៧', '8' => '៨', '9' => '៩']);
    }

    /** Khmer-digit number formatting with thousands separators; "—" for null. */
    public static function khNumber(?float $value, int $decimals = 0): string
    {
        if ($value === null) {
            return '—';
        }
        if ($decimals === 0 && floor($value) != $value) {
            $decimals = 2;
        }

        return self::kh(number_format($value, $decimals));
    }
}
