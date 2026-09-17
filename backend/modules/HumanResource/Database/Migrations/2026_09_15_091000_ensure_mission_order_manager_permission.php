<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $menuId = DB::table('per_menus')->where('menu_name', 'Mission')->value('id');
        if (! $menuId) {
            return;
        }

        DB::table('permissions')->updateOrInsert(
            ['name' => 'manage_mission_order', 'guard_name' => 'web'],
            ['per_menu_id' => $menuId, 'updated_at' => now()]
        );

        $id = DB::table('permissions')->where('name', 'manage_mission_order')->where('guard_name', 'web')->value('id');
        $roleId = DB::table('roles')->where('name', 'Super Admin')->where('guard_name', 'web')->value('id');
        if ($roleId) {
            DB::table('role_has_permissions')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $id]);
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Preserve role grants and existing permissions on rollback.
    }
};
