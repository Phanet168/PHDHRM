<?php

namespace App\Support;

use Carbon\Carbon;

class KhmerDate
{
    private const MONTHS = [
        1 => 'មករា', 2 => 'កុម្ភៈ', 3 => 'មីនា', 4 => 'មេសា', 5 => 'ឧសភា', 6 => 'មិថុនា',
        7 => 'កក្កដា', 8 => 'សីហា', 9 => 'កញ្ញា', 10 => 'តុលា', 11 => 'វិច្ឆិកា', 12 => 'ធ្នូ',
    ];

    private const DIGITS = ['0' => '០', '1' => '១', '2' => '២', '3' => '៣', '4' => '៤', '5' => '៥', '6' => '៦', '7' => '៧', '8' => '៨', '9' => '៩'];

    public static function format(Carbon $date): string
    {
        $day = self::toKhmerDigits((string) $date->day);
        $month = self::MONTHS[$date->month];
        $year = self::toKhmerDigits((string) $date->year);

        return "ថ្ងៃទី {$day} ខែ{$month} ឆ្នាំ{$year}";
    }

    /** Compact form for cards: "២៦ កញ្ញា ២០២៦". */
    public static function short(Carbon $date): string
    {
        return self::toKhmerDigits((string) $date->day).' '.self::MONTHS[$date->month].' '.self::toKhmerDigits((string) $date->year);
    }

    /** Thousands-separated number in Khmer digits: 1234 -> "១,២៣៤". */
    public static function number(int|float $value): string
    {
        return self::toKhmerDigits(number_format($value));
    }

    public static function toKhmerDigits(string $number): string
    {
        return strtr($number, self::DIGITS);
    }
}
