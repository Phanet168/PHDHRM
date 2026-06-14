<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_item_schedules', function (Blueprint $table) {
            if (!Schema::hasColumn('plan_item_schedules', 'activity_task_text')) {
                $table->text('activity_task_text')->nullable()->after('period_label');
            }

            if (!Schema::hasColumn('plan_item_schedules', 'goal_text')) {
                $table->text('goal_text')->nullable()->after('activity_task_text');
            }

            if (!Schema::hasColumn('plan_item_schedules', 'expected_result_text')) {
                $table->text('expected_result_text')->nullable()->after('goal_text');
            }

            if (!Schema::hasColumn('plan_item_schedules', 'verification_text')) {
                $table->text('verification_text')->nullable()->after('expected_result_text');
            }
        });
    }

    public function down(): void
    {
        Schema::table('plan_item_schedules', function (Blueprint $table) {
            foreach ([
                'verification_text',
                'expected_result_text',
                'goal_text',
                'activity_task_text',
            ] as $column) {
                if (Schema::hasColumn('plan_item_schedules', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
