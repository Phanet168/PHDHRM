<?php

namespace Modules\HumanResource\Support;

use Closure;
use Illuminate\Support\Facades\Schema;

/**
 * Memoizes lookups for the lifetime of the current request (stored on the
 * request, so the next request — and each test HTTP call — starts clean).
 *
 * Attendance endpoints evaluate a day at a time; without this, a 30-day
 * history re-ran the same schema probes and unit-hierarchy walks for every
 * day (~1,200 queries, most of them information_schema lookups).
 */
final class RequestCache
{
    private const KEY = '_hr_request_cache';

    public static function remember(string $key, Closure $resolve): mixed
    {
        $attributes = request()->attributes;
        $cache = $attributes->get(self::KEY, []);
        if (! array_key_exists($key, $cache)) {
            $cache[$key] = $resolve();
            $attributes->set(self::KEY, $cache);
        }

        return $cache[$key];
    }

    public static function hasTable(string $table): bool
    {
        return self::remember('schema:table:'.$table, fn () => Schema::hasTable($table));
    }

    public static function hasColumn(string $table, string $column): bool
    {
        return self::remember('schema:column:'.$table.'.'.$column, fn () => Schema::hasColumn($table, $column));
    }
}
