<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('missions')) {
            return;
        }

        Schema::table('missions', function (Blueprint $table): void {
            if (!Schema::hasColumn('missions', 'workflow_status')) {
                $table->string('workflow_status', 32)->nullable()->after('workflow_instance_id');
            }

            if (!Schema::hasColumn('missions', 'workflow_current_step_order')) {
                $table->unsignedInteger('workflow_current_step_order')->nullable()->after('workflow_status');
            }

            if (!Schema::hasColumn('missions', 'workflow_last_action_at')) {
                $table->dateTime('workflow_last_action_at')->nullable()->after('workflow_current_step_order');
            }

            if (!Schema::hasColumn('missions', 'workflow_snapshot_json')) {
                $table->json('workflow_snapshot_json')->nullable()->after('workflow_last_action_at');
            }
        });

        // Backfill a parallel workflow_status for pre-existing rows; lifecycle_status/creation_path
        // are intentionally left untouched for these legacy missions (see mission-phase2 doc).
        DB::table('missions')
            ->whereNull('workflow_status')
            ->whereIn('status', ['approved', 'rejected', 'cancelled'])
            ->update(['workflow_status' => DB::raw('status')]);

        DB::table('missions')
            ->whereNull('workflow_status')
            ->whereIn('status', ['draft', 'pending'])
            ->update(['workflow_status' => 'pending']);
    }

    public function down(): void
    {
        if (!Schema::hasTable('missions')) {
            return;
        }

        Schema::table('missions', function (Blueprint $table): void {
            foreach ([
                'workflow_snapshot_json',
                'workflow_last_action_at',
                'workflow_current_step_order',
                'workflow_status',
            ] as $column) {
                if (Schema::hasColumn('missions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
