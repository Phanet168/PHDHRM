<?php

namespace Modules\HumanResource\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\HumanResource\Entities\ResponsibilityTemplate;
use Modules\HumanResource\Entities\SystemRole;
use Modules\HumanResource\Entities\UserAssignment;
use Modules\HumanResource\Support\ModuleGovernancePolicyRegistry;

class ResponsibilityTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = app(ModuleGovernancePolicyRegistry::class)
            ->templateDefinitions()
            ->all();

        if (empty($definitions)) {
            $definitions = [
            [
                'module_key' => 'correspondence',
                'template_key' => 'corr_office_manager',
                'name' => 'Correspondence Office Manager',
                'name_km' => 'អ្នកគ្រប់គ្រងលិខិត (ការិយាល័យ)',
                'responsibility_code' => SystemRole::CODE_MANAGER,
                'default_scope_type' => UserAssignment::SCOPE_SELF_AND_CHILDREN,
                'sort_order' => 10,
                'action_presets_json' => [
                    'create_incoming',
                    'create_outgoing',
                    'office_comment',
                    'distribute',
                    'acknowledge',
                    'feedback',
                    'print',
                ],
                'is_system' => true,
            ],
            [
                'module_key' => 'correspondence',
                'template_key' => 'corr_deputy_reviewer',
                'name' => 'Correspondence Deputy Reviewer',
                'name_km' => 'អ្នកពិនិត្យអនុប្រធាន (លិខិត)',
                'responsibility_code' => SystemRole::CODE_DEPUTY_HEAD,
                'default_scope_type' => UserAssignment::SCOPE_SELF_AND_CHILDREN,
                'sort_order' => 20,
                'action_presets_json' => [
                    'delegate',
                    'deputy_review',
                    'distribute',
                    'close',
                    'print',
                ],
                'is_system' => true,
            ],
            [
                'module_key' => 'correspondence',
                'template_key' => 'corr_director_final',
                'name' => 'Correspondence Director Final',
                'name_km' => 'ប្រធានអនុម័តចុងក្រោយ (លិខិត)',
                'responsibility_code' => SystemRole::CODE_HEAD,
                'default_scope_type' => UserAssignment::SCOPE_SELF_AND_CHILDREN,
                'sort_order' => 30,
                'action_presets_json' => [
                    'director_decision',
                    'delegate',
                    'distribute',
                    'close',
                    'print',
                ],
                'is_system' => true,
            ],
            [
                'module_key' => 'attendance',
                'template_key' => 'att_requester',
                'name' => 'Attendance Requester',
                'name_km' => 'អ្នកស្នើកែតម្រូវវត្តមាន',
                'responsibility_code' => SystemRole::CODE_STAFF,
                'default_scope_type' => UserAssignment::SCOPE_SELF_ONLY,
                'sort_order' => 10,
                'action_presets_json' => [
                    'create_adjustment',
                ],
                'is_system' => true,
            ],
            [
                'module_key' => 'attendance',
                'template_key' => 'att_manager_reviewer',
                'name' => 'Attendance Manager Reviewer',
                'name_km' => 'អ្នកពិនិត្យវត្តមាន (អ្នកគ្រប់គ្រង)',
                'responsibility_code' => SystemRole::CODE_MANAGER,
                'default_scope_type' => UserAssignment::SCOPE_SELF_AND_CHILDREN,
                'sort_order' => 20,
                'action_presets_json' => [
                    'review_adjustment',
                    'manage_exceptions',
                ],
                'is_system' => true,
            ],
            [
                'module_key' => 'attendance',
                'template_key' => 'att_head_approver',
                'name' => 'Attendance Final Approver',
                'name_km' => 'អ្នកអនុម័តចុងក្រោយវត្តមាន',
                'responsibility_code' => SystemRole::CODE_HEAD,
                'default_scope_type' => UserAssignment::SCOPE_SELF_AND_CHILDREN,
                'sort_order' => 30,
                'action_presets_json' => [
                    'approve_adjustment',
                    'finalize_adjustment',
                    'manage_exceptions',
                ],
                'is_system' => true,
            ],
            ];
        }

        foreach ($definitions as $definition) {
            $responsibilityId = (int) (SystemRole::query()
                ->where('code', (string) $definition['responsibility_code'])
                ->value('id') ?? 0);

            if ($responsibilityId <= 0) {
                continue;
            }

            $record = ResponsibilityTemplate::query()
                ->withoutGlobalScope('sortByLatest')
                ->withTrashed()
                ->firstOrNew([
                    'module_key' => trim(mb_strtolower((string) $definition['module_key'])),
                    'template_key' => trim(mb_strtolower((string) $definition['template_key'])),
                ]);

            if (!$record->exists) {
                $record->uuid = (string) Str::uuid();
            } elseif ($record->trashed()) {
                $record->restore();
            }

            $record->name = (string) $definition['name'];
            $record->name_km = (string) ($definition['name_km'] ?? '');
            $record->position_id = null;
            $record->responsibility_id = $responsibilityId;
            $record->action_presets_json = array_values(array_unique(array_filter(array_map(
                static fn ($item) => trim(mb_strtolower((string) $item)),
                (array) ($definition['action_presets_json'] ?? [])
            ))));
            $record->default_scope_type = (string) ($definition['default_scope_type'] ?? UserAssignment::SCOPE_SELF_AND_CHILDREN);
            $record->sort_order = (int) ($definition['sort_order'] ?? 100);
            $record->is_system = (bool) ($definition['is_system'] ?? true);
            $record->is_active = true;

            if (empty($record->note)) {
                $record->note = 'System seeded template. You can clone this and create custom templates.';
            }

            $record->save();
        }
    }
}
