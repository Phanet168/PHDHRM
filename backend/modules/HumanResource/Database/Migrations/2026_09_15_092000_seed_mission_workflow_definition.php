<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\HumanResource\Entities\UserOrgRole;

return new class extends Migration
{
    /**
     * Seeds the Mission request approval workflow: office head endorsement (not final)
     * then director approval (final). Mirrors the shape seeded for Leave in
     * 2026_03_29_150000_create_workflow_engine_tables.php, but sets actor_type/
     * actor_responsibility_id directly since the later backfill migration
     * (2026_04_23_091000) only runs once and will not pick up new rows.
     */
    public function up(): void
    {
        if (!Schema::hasTable('workflow_definitions') || !Schema::hasTable('workflow_definition_steps')) {
            return;
        }

        $exists = DB::table('workflow_definitions')
            ->where('module_key', 'mission')
            ->where('request_type_key', 'mission_request')
            ->exists();

        if ($exists) {
            return;
        }

        $managerRoleId = DB::table('system_roles')->where('code', UserOrgRole::ROLE_MANAGER)->value('id');
        $headRoleId = DB::table('system_roles')->where('code', UserOrgRole::ROLE_HEAD)->value('id');

        $now = now();

        $definitionId = DB::table('workflow_definitions')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'module_key' => 'mission',
            'request_type_key' => 'mission_request',
            'name' => 'Mission request approval',
            'description' => 'Office head endorsement followed by director approval for employee-initiated mission requests.',
            'condition_json' => null,
            'priority' => 10,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $steps = [
            [
                'step_order' => 1,
                'step_key' => 'office_head_endorse',
                'step_name' => 'Office head endorsement',
                'action_type' => 'recommend',
                'org_role' => UserOrgRole::ROLE_MANAGER,
                'actor_responsibility_id' => $managerRoleId,
                'is_final_approval' => false,
            ],
            [
                'step_order' => 2,
                'step_key' => 'director_approve',
                'step_name' => 'Director approval',
                'action_type' => 'approve',
                'org_role' => UserOrgRole::ROLE_HEAD,
                'actor_responsibility_id' => $headRoleId,
                'is_final_approval' => true,
            ],
        ];

        foreach ($steps as $step) {
            DB::table('workflow_definition_steps')->insert([
                'uuid' => (string) Str::uuid(),
                'workflow_definition_id' => $definitionId,
                'step_order' => $step['step_order'],
                'step_key' => $step['step_key'],
                'step_name' => $step['step_name'],
                'action_type' => $step['action_type'],
                'org_role' => $step['org_role'],
                'actor_type' => 'responsibility',
                'actor_responsibility_id' => $step['actor_responsibility_id'],
                'scope_type' => UserOrgRole::SCOPE_SELF_AND_CHILDREN,
                'is_final_approval' => $step['is_final_approval'] ? 1 : 0,
                'is_required' => 1,
                'can_return' => 1,
                'can_reject' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('workflow_definitions')) {
            return;
        }

        $definition = DB::table('workflow_definitions')
            ->where('module_key', 'mission')
            ->where('request_type_key', 'mission_request')
            ->first();

        if (!$definition) {
            return;
        }

        DB::table('workflow_definition_steps')->where('workflow_definition_id', $definition->id)->delete();
        DB::table('workflow_definitions')->where('id', $definition->id)->delete();
    }
};
