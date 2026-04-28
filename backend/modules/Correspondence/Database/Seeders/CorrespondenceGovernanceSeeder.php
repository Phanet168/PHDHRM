<?php

namespace Modules\Correspondence\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CorrespondenceGovernanceSeeder extends Seeder
{
    public function run(): void
    {
        foreach ((array) config('governance.templates', []) as $template) {
            DB::table('correspondence_responsibility_templates')->updateOrInsert(
                ['template_key' => (string) $template['template_key']],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => (string) $template['name'],
                    'name_km' => $template['name_km'] ?? null,
                    'responsibility_key' => (string) $template['responsibility_key'],
                    'actions_json' => json_encode(array_values((array) $template['actions'])),
                    'conditions_json' => json_encode((array) ($template['conditions'] ?? [])),
                    'reviewer_rules_json' => json_encode((array) ($template['reviewer_rules'] ?? [])),
                    'approver_rules_json' => json_encode((array) ($template['approver_rules'] ?? [])),
                    'commenter_rules_json' => json_encode((array) ($template['commenter_rules'] ?? [])),
                    'default_scope_type' => (string) ($template['default_scope_type'] ?? 'self_and_children'),
                    'sort_order' => (int) ($template['sort_order'] ?? 100),
                    'is_system' => true,
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        foreach ((array) config('governance.workflow_policies', []) as $policy) {
            DB::table('correspondence_workflow_policies')->updateOrInsert(
                ['policy_key' => (string) $policy['policy_key']],
                [
                    'uuid' => (string) Str::uuid(),
                    'request_type_key' => (string) $policy['request_type_key'],
                    'name' => (string) $policy['name'],
                    'name_km' => $policy['name_km'] ?? null,
                    'conditions_json' => json_encode((array) ($policy['conditions'] ?? [])),
                    'steps_json' => json_encode((array) ($policy['steps'] ?? [])),
                    'priority' => (int) ($policy['priority'] ?? 100),
                    'is_system' => true,
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
