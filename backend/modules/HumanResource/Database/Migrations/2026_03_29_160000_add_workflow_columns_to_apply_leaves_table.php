<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('apply_leaves')) {
            return;
        }

        Schema::table('apply_leaves', function (Blueprint $table): void {
            if (!Schema::hasColumn('apply_leaves', 'workflow_instance_id')) {
                $table->unsignedBigInteger('workflow_instance_id')->nullable()->after('is_approved');
            }

            if (!Schema::hasColumn('apply_leaves', 'workflow_status')) {
                $table->string('workflow_status', 32)->default('draft')->after('workflow_instance_id');
            }

            if (!Schema::hasColumn('apply_leaves', 'workflow_current_step_order')) {
                $table->unsignedInteger('workflow_current_step_order')->nullable()->after('workflow_status');
            }

            if (!Schema::hasColumn('apply_leaves', 'workflow_last_action_at')) {
                $table->dateTime('workflow_last_action_at')->nullable()->after('workflow_current_step_order');
            }

            if (!Schema::hasColumn('apply_leaves', 'workflow_snapshot_json')) {
                $table->json('workflow_snapshot_json')->nullable()->after('workflow_last_action_at');
            }
        });

        if (Schema::hasTable('workflow_instances')) {
            Schema::table('apply_leaves', function (Blueprint $table): void {
                try {
                    $table->foreign('workflow_instance_id', 'apply_leaves_workflow_instance_fk')
                        ->references('id')
                        ->on('workflow_instances')
                        ->nullOnDelete();
                } catch (\Throwable $e) {
                    // Ignore if the key already exists in some environments.
                }
            });
        }

        DB::table('apply_leaves')
            ->where('is_approved', 1)
            ->where(function ($query): void {
                $query->whereNull('workflow_status')->orWhere('workflow_status', 'draft');
            })
            ->update([
                'workflow_status' => 'approved',
            ]);

        DB::table('apply_leaves')
            ->where('is_approved', 0)
            ->where(function ($query): void {
                $query->whereNull('workflow_status')->orWhere('workflow_status', 'draft');
            })
            ->update([
                'workflow_status' => 'pending',
            ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('apply_leaves')) {
            return;
        }

        Schema::table('apply_leaves', function (Blueprint $table): void {
            try {
                $table->dropForeign('apply_leaves_workflow_instance_fk');
            } catch (\Throwable $e) {
                // Ignore missing key.
            }

            foreach ([
                'workflow_snapshot_json',
                'workflow_last_action_at',
                'workflow_current_step_order',
                'workflow_status',
                'workflow_instance_id',
            ] as $column) {
                if (Schema::hasColumn('apply_leaves', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

