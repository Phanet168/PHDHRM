<?php

namespace Modules\HumanResource\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\HumanResource\Entities\SystemRole;

class GovernanceLookupSeeder extends Seeder
{
    /**
     * Seed baseline responsibilities in system_roles (idempotent, non-destructive).
     */
    public function run(): void
    {
        $defaults = [
            [
                'code' => 'head',
                'name' => 'Head / Director',
                'name_km' => 'ប្រធាន',
                'level' => 1,
                'can_approve' => true,
                'is_system' => true,
                'sort_order' => 1,
            ],
            [
                'code' => 'deputy_head',
                'name' => 'Deputy Head',
                'name_km' => 'អនុប្រធាន',
                'level' => 2,
                'can_approve' => true,
                'is_system' => true,
                'sort_order' => 2,
            ],
            [
                'code' => 'manager',
                'name' => 'Manager / Office Chief',
                'name_km' => 'ប្រធានការិយាល័យ',
                'level' => 3,
                'can_approve' => true,
                'is_system' => true,
                'sort_order' => 3,
            ],
            [
                'code' => 'reviewer',
                'name' => 'Reviewer',
                'name_km' => 'អ្នកពិនិត្យ',
                'level' => 4,
                'can_approve' => false,
                'is_system' => true,
                'sort_order' => 4,
            ],
            [
                'code' => 'staff',
                'name' => 'Staff',
                'name_km' => 'មន្ត្រី',
                'level' => 5,
                'can_approve' => false,
                'is_system' => true,
                'sort_order' => 5,
            ],
            [
                'code' => 'viewer',
                'name' => 'Viewer',
                'name_km' => 'អ្នកមើល',
                'level' => 6,
                'can_approve' => false,
                'is_system' => true,
                'sort_order' => 6,
            ],
        ];

        foreach ($defaults as $row) {
            $role = SystemRole::query()->firstOrNew([
                'code' => (string) $row['code'],
            ]);

            if (!$role->exists) {
                $role->uuid = (string) Str::uuid();
                $role->name = (string) $row['name'];
                $role->name_km = (string) $row['name_km'];
                $role->level = (int) $row['level'];
                $role->can_approve = (bool) $row['can_approve'];
                $role->is_system = (bool) $row['is_system'];
                $role->is_active = true;
                $role->sort_order = (int) $row['sort_order'];
                $role->save();
                continue;
            }

            // Fill only missing fields to avoid overriding existing tenant/custom values.
            $dirty = false;
            if (empty($role->name)) {
                $role->name = (string) $row['name'];
                $dirty = true;
            }
            if (empty($role->name_km)) {
                $role->name_km = (string) $row['name_km'];
                $dirty = true;
            }
            if ($role->level === null) {
                $role->level = (int) $row['level'];
                $dirty = true;
            }
            if ($role->sort_order === null) {
                $role->sort_order = (int) $row['sort_order'];
                $dirty = true;
            }
            if ($role->is_active === null) {
                $role->is_active = true;
                $dirty = true;
            }
            if ($role->is_system === null) {
                $role->is_system = (bool) $row['is_system'];
                $dirty = true;
            }

            if ($dirty) {
                $role->save();
            }
        }
    }
}
