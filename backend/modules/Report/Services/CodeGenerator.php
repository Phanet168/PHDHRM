<?php

namespace Modules\Report\Services;

use Illuminate\Support\Facades\DB;

/**
 * Human-friendly sequential codes for departments, units and indicators.
 * The next code is the highest numeric code + 1; gaps are not reused because they belong to records
 * deleted in PHDReport, and reusing them would clash with older exports / Excel files.
 */
class CodeGenerator
{
    public static function next(string $table): string
    {
        $max = DB::table($table)->whereRaw("code REGEXP '^[0-9]+$'")->max(DB::raw('CAST(code AS UNSIGNED)'));

        return (string) (((int) $max) + 1);
    }

    public static function isGenerated(?string $code): bool
    {
        return $code !== null && ctype_digit($code);
    }
}
