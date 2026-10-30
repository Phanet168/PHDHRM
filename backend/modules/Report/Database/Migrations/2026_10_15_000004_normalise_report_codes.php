<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps each record's PHDReport/Firebase id in source_id (so re-imports still match), then replaces
 * non-numeric codes (Firebase UUIDs, "none-…") with the next sequential number.
 */
return new class extends Migration
{
    private array $tables = ['report_departments', 'report_units', 'report_indicators'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('source_id', 64)->nullable()->after('code')->index();
            });
            DB::table($table)->update(['source_id' => DB::raw('code')]);
        }

        DB::transaction(function () {
            // Departments and units: in their display order. Indicators: grouped by department and unit.
            $this->renumber('report_departments', DB::table('report_departments')->orderBy('sort_order')->orderBy('id'));
            $this->renumber('report_units', DB::table('report_units')->orderBy('department_id')->orderBy('sort_order')->orderBy('id'));
            $this->renumber('report_indicators', DB::table('report_indicators')->orderBy('department_id')->orderBy('unit_id')->orderBy('id'));
        });
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            DB::table($table)->whereNotNull('source_id')->update(['code' => DB::raw('source_id')]);
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('source_id'));
        }
    }

    private function renumber(string $table, $query): void
    {
        $next = (int) DB::table($table)->whereRaw("code REGEXP '^[0-9]+$'")->max(DB::raw('CAST(code AS UNSIGNED)'));
        foreach ($query->whereRaw("code NOT REGEXP '^[0-9]+$'")->get(['id']) as $row) {
            DB::table($table)->where('id', $row->id)->update(['code' => (string) ++$next]);
        }
    }
};
