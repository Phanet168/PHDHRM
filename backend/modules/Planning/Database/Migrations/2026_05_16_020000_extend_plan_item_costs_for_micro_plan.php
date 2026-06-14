<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('plan_item_costs')) {
            return;
        }

        Schema::table('plan_item_costs', function (Blueprint $table) {
            if (!Schema::hasColumn('plan_item_costs', 'implementer_count')) {
                $table->decimal('implementer_count', 14, 2)->default(1)->after('qty');
            }

            if (!Schema::hasColumn('plan_item_costs', 'occurrence_count')) {
                $table->decimal('occurrence_count', 14, 2)->default(1)->after('implementer_count');
            }

            if (!Schema::hasColumn('plan_item_costs', 'currency_code')) {
                $table->string('currency_code', 10)->default('KHR')->after('unit_price');
            }
        });

        DB::table('plan_item_costs')->update([
            'implementer_count' => DB::raw('COALESCE(implementer_count, 1)'),
            'occurrence_count' => DB::raw('COALESCE(occurrence_count, 1)'),
            'currency_code' => DB::raw("COALESCE(NULLIF(currency_code, ''), 'KHR')"),
        ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('plan_item_costs')) {
            return;
        }

        Schema::table('plan_item_costs', function (Blueprint $table) {
            foreach (['implementer_count', 'occurrence_count', 'currency_code'] as $column) {
                if (Schema::hasColumn('plan_item_costs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
