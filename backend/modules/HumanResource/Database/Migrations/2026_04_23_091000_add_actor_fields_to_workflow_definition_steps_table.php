<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('workflow_definition_steps')) {
            return;
        }

        Schema::table('workflow_definition_steps', function (Blueprint $table) {
            if (!Schema::hasColumn('workflow_definition_steps', 'actor_type')) {
                $table->enum('actor_type', ['specific_user', 'position', 'responsibility', 'spatie_role'])
                    ->default('responsibility')
                    ->after('system_role_id');
            }

            if (!Schema::hasColumn('workflow_definition_steps', 'actor_user_id')) {
                $table->unsignedBigInteger('actor_user_id')->nullable()->after('actor_type');
            }

            if (!Schema::hasColumn('workflow_definition_steps', 'actor_position_id')) {
                $table->unsignedBigInteger('actor_position_id')->nullable()->after('actor_user_id');
            }

            if (!Schema::hasColumn('workflow_definition_steps', 'actor_responsibility_id')) {
                $table->unsignedBigInteger('actor_responsibility_id')->nullable()->after('actor_position_id');
            }

            if (!Schema::hasColumn('workflow_definition_steps', 'actor_role_id')) {
                $table->unsignedBigInteger('actor_role_id')->nullable()->after('actor_responsibility_id');
            }
        });

        Schema::table('workflow_definition_steps', function (Blueprint $table) {
            if (Schema::hasColumn('workflow_definition_steps', 'actor_type')) {
                $table->index(['actor_type', 'scope_type'], 'idx_wf_steps_actor_type_scope');
            }
            if (Schema::hasColumn('workflow_definition_steps', 'actor_user_id')) {
                $table->index(['actor_user_id'], 'idx_wf_steps_actor_user');
                $table->foreign('actor_user_id', 'fk_wf_steps_actor_user')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }
            if (Schema::hasColumn('workflow_definition_steps', 'actor_position_id')) {
                $table->index(['actor_position_id'], 'idx_wf_steps_actor_position');
                $table->foreign('actor_position_id', 'fk_wf_steps_actor_position')
                    ->references('id')
                    ->on('positions')
                    ->nullOnDelete();
            }
            if (Schema::hasColumn('workflow_definition_steps', 'actor_responsibility_id')) {
                $table->index(['actor_responsibility_id'], 'idx_wf_steps_actor_responsibility');
                $table->foreign('actor_responsibility_id', 'fk_wf_steps_actor_responsibility')
                    ->references('id')
                    ->on('system_roles')
                    ->nullOnDelete();
            }
            if (Schema::hasColumn('workflow_definition_steps', 'actor_role_id')) {
                $table->index(['actor_role_id'], 'idx_wf_steps_actor_role');
                // Fallback only: Spatie role, not primary business resolver.
                $table->foreign('actor_role_id', 'fk_wf_steps_actor_role')
                    ->references('id')
                    ->on('roles')
                    ->nullOnDelete();
            }
        });

        if (Schema::hasColumn('workflow_definition_steps', 'scope_type')) {
            DB::table('workflow_definition_steps')
                ->where('scope_type', 'self')
                ->update(['scope_type' => 'self_only']);
        }

        if (Schema::hasColumn('workflow_definition_steps', 'actor_responsibility_id')) {
            DB::table('workflow_definition_steps')
                ->whereNull('actor_responsibility_id')
                ->whereNotNull('system_role_id')
                ->update([
                    'actor_type' => 'responsibility',
                    'actor_responsibility_id' => DB::raw('system_role_id'),
                ]);

            if (Schema::hasTable('system_roles') && Schema::hasColumn('workflow_definition_steps', 'org_role')) {
                DB::statement("
                    UPDATE workflow_definition_steps wds
                    JOIN system_roles sr ON sr.code = wds.org_role
                    SET
                        wds.actor_type = 'responsibility',
                        wds.actor_responsibility_id = sr.id
                    WHERE wds.actor_responsibility_id IS NULL
                      AND wds.org_role IS NOT NULL
                      AND wds.org_role <> ''
                ");
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('workflow_definition_steps')) {
            return;
        }

        Schema::table('workflow_definition_steps', function (Blueprint $table) {
            if (Schema::hasColumn('workflow_definition_steps', 'actor_role_id')) {
                $table->dropForeign('fk_wf_steps_actor_role');
                $table->dropIndex('idx_wf_steps_actor_role');
            }
            if (Schema::hasColumn('workflow_definition_steps', 'actor_responsibility_id')) {
                $table->dropForeign('fk_wf_steps_actor_responsibility');
                $table->dropIndex('idx_wf_steps_actor_responsibility');
            }
            if (Schema::hasColumn('workflow_definition_steps', 'actor_position_id')) {
                $table->dropForeign('fk_wf_steps_actor_position');
                $table->dropIndex('idx_wf_steps_actor_position');
            }
            if (Schema::hasColumn('workflow_definition_steps', 'actor_user_id')) {
                $table->dropForeign('fk_wf_steps_actor_user');
                $table->dropIndex('idx_wf_steps_actor_user');
            }
            if (Schema::hasColumn('workflow_definition_steps', 'actor_type')) {
                $table->dropIndex('idx_wf_steps_actor_type_scope');
            }
        });

        Schema::table('workflow_definition_steps', function (Blueprint $table) {
            $dropColumns = [];
            foreach ([
                'actor_role_id',
                'actor_responsibility_id',
                'actor_position_id',
                'actor_user_id',
                'actor_type',
            ] as $column) {
                if (Schema::hasColumn('workflow_definition_steps', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
