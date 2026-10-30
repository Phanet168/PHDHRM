<?php

namespace Modules\Report\Services;

use Illuminate\Support\Collection;

/**
 * Lays a unit's indicators out as a grid when their names are "<item>(<variant>)", e.g.
 *
 *   បន្ទប់ពិគ្រោះព្យាបាលជំងឺ | (បើកថ្មី) | (បន្តសុពលភាព) | (គ្មានច្បាប់) | (លុបចោល)
 *   វេជ្ជបណ្ឌិត            | (ប្រុស)   | (ស្រី)
 *
 * so one row holds every variant of the same item instead of the variants being scattered down a long list.
 * Indicators that don't fit (or would land on an occupied cell) are returned as "extra" and keep the list layout.
 */
class IndicatorMatrix
{
    /** Preferred column order; other variants follow in the order they first appear. */
    private const ORDER = ['', 'បើកថ្មី', 'បន្តសុពលភាព', 'គ្មានច្បាប់', 'លុបចោល', 'ប្រុស', 'ស្រី'];

    /** Spellings of the same variant. */
    private const SYNONYMS = ['សុពុលភាព' => 'សុពលភាព', 'បិត' => 'បិទ'];

    /** Variants shown in one column when no item has both. */
    private const MERGE = ['បិទ' => 'លុបចោល'];

    private const LABELS = ['' => 'ចំនួន / សរុប', 'លុបចោល' => 'លុបចោល / បិទ'];

    /**
     * @param  Collection  $rows  HealthReportData rows of one unit, in display order
     * @return array{columns: array<string,string>, rows: array<int, array{label: string, cells: array<string, array>}>, extra: Collection}|null
     */
    public static function build(Collection $rows): ?array
    {
        if ($rows->count() < 6) {
            return null;
        }

        $parsed = $rows->map(fn ($r) => ['row' => $r] + self::split($r['name']))->values();

        // a variant is a column only when at least two items have it
        $basesPerVariant = $parsed->groupBy('variant')->map(fn ($g) => $g->pluck('base_key')->unique()->count());
        $columns = $basesPerVariant->filter(fn ($n, $v) => $v !== '' && $n >= 2)->keys()->all();
        if (count($columns) < 2) {
            return null;
        }
        $parsed = $parsed->map(function ($p) use ($columns) {
            if ($p['variant'] !== '' && !in_array($p['variant'], $columns, true)) {
                // e.g. "ឱសថស្ថានរង (ខ)": the brackets are part of the item's name
                $p = ['row' => $p['row']] + self::split($p['row']['name'], false);
            }

            return $p;
        });

        // "X (ប្រភេទថ្នាំ A)" followed by "X (ប្រុស)", "X (ស្រី)": the split rows belong to the row just above
        $prev = null;
        $parsed = $parsed->map(function ($p) use (&$prev) {
            if ($p['variant'] !== '' && $prev && $prev['base_key'] !== $p['base_key'] && str_starts_with($prev['base_key'], $p['base_key'])) {
                $p = ['base' => $prev['base'], 'base_key' => $prev['base_key']] + $p;
            }

            return $prev = $p;
        });

        // merge synonym columns unless an item has both
        foreach (self::MERGE as $from => $to) {
            $clash = $parsed->where('variant', $from)->pluck('base_key')->intersect($parsed->where('variant', $to)->pluck('base_key'))->isNotEmpty();
            if (!$clash && in_array($from, $columns, true)) {
                $parsed = $parsed->map(fn ($p) => $p['variant'] === $from ? ['variant' => $to] + $p : $p);
            }
        }

        $grid = [];
        $labels = [];
        $extra = collect();
        $used = [];
        foreach ($parsed as $p) {
            if (isset($grid[$p['base_key']][$p['variant']])) {
                $extra->push($p['row']);
                continue;
            }
            $labels[$p['base_key']] ??= $p['base'];
            $grid[$p['base_key']][$p['variant']] = $p['row'];
            $used[$p['variant']] = true;
        }

        // only worth it when the grid is clearly shorter than the list
        if (count($grid) > $rows->count() * 0.6) {
            return null;
        }

        $variants = array_keys($used);
        usort($variants, function ($a, $b) use ($variants) {
            $ia = array_search($a, self::ORDER, true);
            $ib = array_search($b, self::ORDER, true);
            $ia = $ia === false ? 100 + array_search($a, $variants, true) : $ia;
            $ib = $ib === false ? 100 + array_search($b, $variants, true) : $ib;

            return $ia <=> $ib;
        });

        return [
            'columns' => collect($variants)->mapWithKeys(fn ($v) => [$v => self::LABELS[$v] ?? $v])->all(),
            'rows' => collect($grid)->map(fn ($cells, $key) => ['label' => $labels[$key], 'cells' => $cells])->values()->all(),
            'extra' => $extra,
        ];
    }

    /** "<item>(<variant>)", "<item>(<variant>) ប្រុស" or "<item> ស្រី" → item + variant. */
    private static function split(string $name, bool $withBrackets = true): array
    {
        $name = trim(preg_replace('/[\x{200B}\x{200C}\x{200D}\x{FEFF}]/u', '', $name));
        $base = $name;
        $variant = '';
        if ($withBrackets && preg_match('/^(.*?\S)\s*\(([^()]+)\)\s*(ប្រុស|ស្រី)?$/u', $name, $m)) {
            [$base, $variant] = [$m[1], trim($m[2] . ' ' . ($m[3] ?? ''))];
        } elseif (preg_match('/^(.*\S)\s+(ប្រុស|ស្រី)$/u', $name, $m)) {
            [$base, $variant] = [$m[1], $m[2]];
        }
        $variant = trim(preg_replace('/\s+/u', ' ', strtr($variant, self::SYNONYMS)));
        // "… សរុប" is the total of "… ប្រុស" / "… ស្រី": same row, first column
        if ($variant === '' && preg_match('/^(.+?)\s*សរុប$/u', $base, $m)) {
            $base = $m[1];
        }

        return ['base' => trim($base), 'base_key' => self::key($base), 'variant' => $variant];
    }

    /** Same item written slightly differently (spaces, zero-width marks, subscript order). */
    private static function key(string $s): string
    {
        $s = preg_replace('/[\s\x{200B}\x{200C}\x{200D}\x{FEFF}]+/u', '', $s);

        return mb_strtolower(str_replace(['្ដ', '្រ្ត'], ['្ត', '្ត្រ'], $s));
    }
}
