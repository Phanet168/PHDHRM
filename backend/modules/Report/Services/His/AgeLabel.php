<?php

namespace Modules\Report\Services\His;

/**
 * Parses HIS age headers: "0-28 ថ្ងៃ", "29ថ្ងៃ-11ខែ", "5 - 14 ឆ្នាំ", "≥ 65 ឆ្នាំ", "< 20ឆ្នាំ",
 * "កុមារអាយុពី ១២ខែ - ៥៩ខែ" … into a normalised code and a day range.
 */
class AgeLabel
{
    private const UNIT_DAYS = ['d' => 1, 'm' => 30, 'y' => 365];

    /** @return array{code:string,label:string,min_days:?int,max_days:?int}|null */
    public static function parse(string $label): ?array
    {
        $label = preg_replace('/[\x{200B}\x{200C}\x{200D}\x{FEFF}]/u', '', $label);
        $s = strtr($label, ['០' => '0', '១' => '1', '២' => '2', '៣' => '3', '៤' => '4', '៥' => '5', '៦' => '6', '៧' => '7', '៨' => '8', '៩' => '9']);
        $s = str_replace(['ថ្ងៃ', 'ខែ', 'ឆ្នាំ', '>=', '=>', '<=', '–', '—'], ['d', 'm', 'y', '≥', '≥', '≤', '-', '-'], $s);
        // Accept an optional Khmer lead-in such as "កុមារអាយុពី" / "អាយុ" before the numbers.
        $s = preg_replace('/^[^\d<>≥≤]*(អាយុ|ពី)\s*/u', '', $s); // greedy: drop up to the last lead-in word
        $s = preg_replace('/\s+/u', '', $s);

        if (preg_match('/^([<>≥≤])(\d+)([dmy])$/u', $s, $m)) {
            $n = (int) $m[2] * self::UNIT_DAYS[$m[3]];
            [$min, $max] = match ($m[1]) {
                '<' => [0, $n - 1],
                '≤' => [0, $n],
                '>' => [$n + 1, null],
                default => [$n, null],
            };
            $code = ($m[1] === '≥' || $m[1] === '>' ? '' : $m[1]) . $m[2] . $m[3] . ($m[1] === '≥' || $m[1] === '>' ? '+' : '');

            return ['code' => $code, 'label' => trim(preg_replace('/\s+/u', ' ', $label)), 'min_days' => $min, 'max_days' => $max];
        }
        // "1 - < 2 ឆ្នាំ": from 1 year up to (not including) 2 years
        if (preg_match('/^(\d+)([dmy])?-<(\d+)([dmy])$/u', $s, $m)) {
            $fromUnit = $m[2] !== '' ? $m[2] : $m[4];
            $min = (int) $m[1] * self::UNIT_DAYS[$fromUnit];
            $max = (int) $m[3] * self::UNIT_DAYS[$m[4]] - 1;

            return ['code' => $m[1] . $fromUnit . '-<' . $m[3] . $m[4], 'label' => trim(preg_replace('/\s+/u', ' ', $label)), 'min_days' => $min, 'max_days' => $max];
        }
        if (preg_match('/^(\d+)([dmy])?-(\d+)([dmy])$/u', $s, $m)) {
            $fromUnit = $m[2] !== '' ? $m[2] : $m[4];
            $min = (int) $m[1] * self::UNIT_DAYS[$fromUnit];
            // the upper bound covers the whole last unit: "1-4 years" ends the day before the 5th birthday
            $max = ((int) $m[3] + 1) * self::UNIT_DAYS[$m[4]] - 1;

            return ['code' => $m[1] . $fromUnit . '-' . $m[3] . $m[4], 'label' => trim(preg_replace('/\s+/u', ' ', $label)), 'min_days' => $min, 'max_days' => $max];
        }

        return null;
    }
}
