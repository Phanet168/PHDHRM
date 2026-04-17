<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\HumanResource\Entities\UserOrgRole;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('notices')) {
            return;
        }

        Schema::table('notices', function (Blueprint $table): void {
            if (!Schema::hasColumn('notices', 'workflow_instance_id')) {
                $table->unsignedBigInteger('workflow_instance_id')->nullable()->after('delivery_last_error');
            }

            if (!Schema::hasColumn('notices', 'workflow_status')) {
                $table->string('workflow_status', 32)->default('draft')->after('workflow_instance_id');
            }

            if (!Schema::hasColumn('notices', 'workflow_current_step_order')) {
                $table->unsignedInteger('workflow_current_step_order')->nullable()->after('workflow_status');
            }

            if (!Schema::hasColumn('notices', 'workflow_last_action_at')) {
                $table->dateTime('workflow_last_action_at')->nullable()->after('workflow_current_step_order');
            }

            if (!Schema::hasColumn('notices', 'workflow_snapshot_json')) {
                $table->json('workflow_snapshot_json')->nullable()->after('workflow_last_action_at');
            }
        });

        if (Schema::hasTable('workflow_instances')) {
            Schema::table('notices', function (Blueprint $table): void {
                try {
                    $table->foreign('workflow_instance_id', 'notices_workflow_instance_fk')
                        ->references('id')
                        ->on('workflow_instances')
                        ->nullOnDelete();
                } catch (\Throwable $e) {
                    // Ignore if key exists.
                }
            });
        }

        DB::table('notices')
            ->whereIn('status', ['pending_approval'])
            ->update([
                'workflow_status' => 'pending',
            ]);

        DB::table('notices')
            ->whereIn('status', ['approved', 'scheduled', 'sent', 'partial_failed', 'archived'])
            ->update([
                'workflow_status' => 'approved',
            ]);

        DB::table('notices')
            ->whereIn('status', ['rejected'])
            ->update([
                'workflow_status' => 'rejected',
            ]);

        DB::table('notices')
            ->where(function ($query): void {
                $query->whereNull('workflow_status')->orWhere('workflow_status', '');
            })
            ->update([
                'workflow_status' => 'draft',
            ]);

        $this->seedNoticeDefaultWorkflowDefinition();
    }

    public function down(): void
    {
        if (!Schema::hasTable('notices')) {
            return;
        }

        Schema::table('notices', function (Blueprint $table): void {
            try {
                $table->dropForeign('notices_workflow_instance_fk');
            } catch (\Throwable $e) {
                // Ignore missing.
            }

            foreach ([
                'workflow_snapshot_json',
                'workflow_last_action_at',
                'workflow_current_step_order',
                'workflow_status',
                'workflow_instance_id',
            ] as $column) {
                if (Schema::hasColumn('notices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    protected function seedNoticeDefaultWorkflowDefinition(): void
    {
        if (!Schema::hasTable('workflow_definitions') || !Schema::hasTable('workflow_definition_steps')) {
            return;
        }

        $exists = DB::table('workflow_definitions')
            ->where('module_key', 'notice')
            ->where('request_type_key', 'notice_general')
            ->exists();

        if ($exists) {
            return;
        }

        $definitionId = DB::table('workflow_definitions')->insertGetId([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'module_key' => 'notice',
            'request_type_key' => 'notice_general',
            'name' => 'Notice general approval',
            'description' => 'Default approval for notice/announcement.',
            'condition_json' => json_encode([]),
            'priority' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('workflow_definition_steps')->insert([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'workflow_definition_id' => $definitionId,
            'step_order' => 1,
            'step_key' => 'head_approve',
            'step_name' => 'Head approval',
            'action_type' => 'approve',
            'org_role' => UserOrgRole::ROLE_HEAD,
            'scope_type' => UserOrgRole::SCOPE_SELF_AND_CHILDREN,
            'is_final_approval' => 1,
            'is_required' => 1,
            'can_return' => 1,
            'can_reject' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};

