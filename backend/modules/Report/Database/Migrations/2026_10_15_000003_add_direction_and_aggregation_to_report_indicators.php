<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_indicators', function (Blueprint $t) {
            // up: higher is better (coverage); down: lower is better (deaths, stock-outs).
            $t->string('direction', 8)->default('up')->after('indicator_type');
            // How quarter / semester / nine-month / year values are derived from months when not entered.
            $t->string('aggregation', 8)->default('none')->after('direction'); // none | sum | avg | last
        });

        // PHDReport stored 0 where no target was set; treat it as "no target".
        DB::table('report_values')->where('target', 0)->update(['target' => null]);
    }

    public function down(): void
    {
        Schema::table('report_indicators', fn (Blueprint $t) => $t->dropColumn(['direction', 'aggregation']));
    }
};
