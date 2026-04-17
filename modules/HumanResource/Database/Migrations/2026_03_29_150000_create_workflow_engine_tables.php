<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\HumanResource\Entities\UserOrgRole;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('workflow_definitions')) {
            Schema::create('workflow_definitions', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('module_key', 64);
                $table->string('request_type_key', 64);
                $table->string('name', 190);
                $table->text('description')->nullable();
                $table->json('condition_json')->nullable();
                $table->unsignedInteger('priority')->default(100);
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->unsignedBigInteger('deleted_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['module_key', 'request_type_key', 'is_active'], 'wf_def_module_type_active_idx');
                $table->index(['priority', 'is_active'], 'wf_def_priority_active_idx');
            });
        }

        if (!Schema::hasTable('workflow_definition_steps')) {
            Schema::create('workflow_definition_steps', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('workflow_definition_id');
                $table->unsignedSmallInteger('step_order');
                $table->string('step_key', 64)->nullable();
                $table->string('step_name', 190);
                $table->string('action_type', 32)->default('approve'); // review|recommend|approve
                $table->string('org_role', 32); // head|deputy_head|manager
                $table->string('scope_type', 32)->default(UserOrgRole::SCOPE_SELF_AND_CHILDREN);
                $table->boolean('is_final_approval')->default(false);
                $table->boolean('is_required')->default(true);
                $table->boolean('can_return')->default(true);
                $table->boolean('can_reject')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('workflow_definition_id', 'wf_steps_definition_fk')
                    ->references('id')
                    ->on('workflow_definitions')
                    ->onDelete('cascade');

                $table->unique(['workflow_definition_id', 'step_order'], 'wf_steps_definition_order_unique');
                $table->index(['org_role', 'action_type'], 'wf_steps_role_action_idx');
            });
        }

        if (!Schema::hasTable('workflow_instances')) {
            Schema::create('workflow_instances', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('module_key', 64);
                $table->string('request_type_key', 64);
                $table->string('source_type', 120)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->unsignedBigInteger('workflow_definition_id')->nullable();
                $table->string('status', 32)->default('draft'); // draft|pending|approved|rejected|returned|cancelled
                $table->unsignedSmallInteger('current_step_order')->nullable();
                $table->unsignedBigInteger('submitted_by')->nullable();
                $table->dateTime('submitted_at')->nullable();
                $table->dateTime('finalized_at')->nullable();
                $table->json('context_json')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('workflow_definition_id', 'wf_instances_definition_fk')
                    ->references('id')
                    ->on('workflow_definitions')
                    ->nullOnDelete();

                $table->index(['module_key', 'request_type_key', 'status'], 'wf_instances_module_type_status_idx');
                $table->index(['source_type', 'source_id'], 'wf_instances_source_idx');
                $table->index(['current_step_order', 'status'], 'wf_instances_step_status_idx');
            });
        }

        if (!Schema::hasTable('workflow_instance_actions')) {
            Schema::create('workflow_instance_actions', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('workflow_instance_id');
                $table->unsignedSmallInteger('step_order')->nullable();
                $table->string('action_type', 32); // submit|recommend|approve|reject|return|cancel|comment
                $table->string('action_status', 32)->nullable();
                $table->unsignedBigInteger('acted_by')->nullable();
                $table->dateTime('acted_at')->nullable();
                $table->string('decision_ref_no', 120)->nullable();
                $table->date('decision_ref_date')->nullable();
                $table->text('decision_note')->nullable();
                $table->json('payload_json')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('workflow_instance_id', 'wf_actions_instance_fk')
                    ->references('id')
                    ->on('workflow_instances')
                    ->onDelete('cascade');

                $table->index(['workflow_instance_id', 'action_type'], 'wf_actions_instance_type_idx');
                $table->index(['acted_by', 'acted_at'], 'wf_actions_actor_time_idx');
            });
        }

        $this->seedDefaultLeaveWorkflowDefinitions();
    }

    public function down(): void
    {
        if (Schema::hasTable('workflow_instance_actions')) {
            Schema::drop('workflow_instance_actions');
        }

        if (Schema::hasTable('workflow_instances')) {
            Schema::drop('workflow_instances');
        }

        if (Schema::hasTable('workflow_definition_steps')) {
            Schema::drop('workflow_definition_steps');
        }

        if (Schema::hasTable('workflow_definitions')) {
            Schema::drop('workflow_definitions');
        }
    }

    protected function seedDefaultLeaveWorkflowDefinitions(): void
    {
        if (!Schema::hasTable('workflow_definitions') || !Schema::hasTable('workflow_definition_steps')) {
            return;
        }

        $existing = DB::table('workflow_definitions')
            ->where('module_key', 'leave')
            ->where('request_type_key', 'leave_request')
            ->exists();

        if ($existing) {
            return;
        }

        $now = now();

        $definitions = [
            [
                'name' => 'Leave <= 1 day',
                'description' => 'Single-step approval for short leave requests up to 1 day.',
                'condition_json' => json_encode(['min_days' => 0, 'max_days' => 1]),
                'priority' => 10,
                'steps' => [
                    [
                        'step_order' => 1,
                        'step_key' => 'office_head_approve',
                        'step_name' => 'Office head approval',
                        'action_type' => 'approve',
                        'org_role' => UserOrgRole::ROLE_MANAGER,
                        'scope_type' => UserOrgRole::SCOPE_SELF_AND_CHILDREN,
                        'is_final_approval' => true,
                    ],
                ],
            ],
            [
                'name' => 'Leave 2-3 days',
                'description' => 'Office head review and department head approval for leave requests between 2 and 3 days.',
                'condition_json' => json_encode(['min_days' => 2, 'max_days' => 3]),
                'priority' => 20,
                'steps' => [
                    [
                        'step_order' => 1,
                        'step_key' => 'office_head_recommend',
                        'step_name' => 'Office head recommendation',
                        'action_type' => 'recommend',
                        'org_role' => UserOrgRole::ROLE_MANAGER,
                        'scope_type' => UserOrgRole::SCOPE_SELF_AND_CHILDREN,
                        'is_final_approval' => false,
                    ],
                    [
                        'step_order' => 2,
                        'step_key' => 'department_head_approve',
                        'step_name' => 'Department head approval',
                        'action_type' => 'approve',
                        'org_role' => UserOrgRole::ROLE_HEAD,
                        'scope_type' => UserOrgRole::SCOPE_SELF_AND_CHILDREN,
                        'is_final_approval' => true,
                    ],
                ],
            ],
            [
                'name' => 'Leave >= 4 days',
                'description' => 'Multi-step approval for long leave requests.',
                'condition_json' => json_encode(['min_days' => 4]),
                'priority' => 30,
                'steps' => [
                    [
                        'step_order' => 1,
                        'step_key' => 'office_head_recommend',
                        'step_name' => 'Office head recommendation',
                        'action_type' => 'recommend',
                        'org_role' => UserOrgRole::ROLE_MANAGER,
                        'scope_type' => UserOrgRole::SCOPE_SELF_AND_CHILDREN,
                        'is_final_approval' => false,
                    ],
                    [
                        'step_order' => 2,
                        'step_key' => 'deputy_head_recommend',
                        'step_name' => 'Deputy head recommendation',
                        'action_type' => 'recommend',
                        'org_role' => UserOrgRole::ROLE_DEPUTY_HEAD,
                        'scope_type' => UserOrgRole::SCOPE_SELF_AND_CHILDREN,
                        'is_final_approval' => false,
                    ],
                    [
                        'step_order' => 3,
                        'step_key' => 'head_approve',
                        'step_name' => 'Head final approval',
                        'action_type' => 'approve',
                        'org_role' => UserOrgRole::ROLE_HEAD,
                        'scope_type' => UserOrgRole::SCOPE_SELF_AND_CHILDREN,
                        'is_final_approval' => true,
                    ],
                ],
            ],
        ];

        foreach ($definitions as $def) {
            $definitionId = DB::table('workflow_definitions')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'module_key' => 'leave',
                'request_type_key' => 'leave_request',
                'name' => $def['name'],
                'description' => $def['description'],
                'condition_json' => $def['condition_json'],
                'priority' => $def['priority'],
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($def['steps'] as $step) {
                DB::table('workflow_definition_steps')->insert([
                    'uuid' => (string) Str::uuid(),
                    'workflow_definition_id' => $definitionId,
                    'step_order' => $step['step_order'],
                    'step_key' => $step['step_key'],
                    'step_name' => $step['step_name'],
                    'action_type' => $step['action_type'],
                    'org_role' => $step['org_role'],
                    'scope_type' => $step['scope_type'],
                    'is_final_approval' => $step['is_final_approval'] ? 1 : 0,
                    'is_required' => 1,
                    'can_return' => 1,
                    'can_reject' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
};

