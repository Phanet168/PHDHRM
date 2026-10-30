<?php

use Illuminate\Database\Migrations\Migration;
use Modules\UserManagement\Entities\PerMenu;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Mirrors the "Health Activity Report" block in RoleTableSeeder for databases that are already seeded.
 */
return new class extends Migration
{
    private array $menus = [
        'Health Activity Report' => [
            'create_health_activity_report',
            'read_health_activity_report',
            'update_health_activity_report',
            'delete_health_activity_report',
        ],
        'Health Report Settings' => [
            'create_health_report_settings',
            'read_health_report_settings',
            'update_health_report_settings',
            'delete_health_report_settings',
        ],
    ];

    public function up(): void
    {
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $parent = PerMenu::firstOrCreate(['menu_name' => 'Health Activity Report', 'parentmenu_id' => null], ['lable' => 1]);
        $child = PerMenu::firstOrCreate(['menu_name' => 'Health Report Settings', 'parentmenu_id' => $parent->id], ['lable' => 2]);

        foreach ($this->menus as $menu => $names) {
            $menuId = $menu === 'Health Activity Report' ? $parent->id : $child->id;
            foreach ($names as $name) {
                $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['per_menu_id' => $menuId]);
                $permission->assignRole($superAdmin);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', array_merge(...array_values($this->menus)))->delete();
        PerMenu::whereIn('menu_name', array_keys($this->menus))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
