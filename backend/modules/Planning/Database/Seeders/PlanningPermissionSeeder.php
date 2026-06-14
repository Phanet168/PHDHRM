<?php

namespace Modules\Planning\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PlanningPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'planning.view',
            'planning.create',
            'planning.update_own',
            'planning.review',
            'planning.approve',
            'planning.consolidate',
            'planning.export',
            'planning.comment',
            'planning.manage_master_data',
        ];

        foreach ($permissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissionMap = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $permissions)
            ->get()
            ->keyBy('name');

        Role::findOrCreate('staff', 'web')->syncPermissions([
            $permissionMap['planning.view'],
            $permissionMap['planning.create'],
            $permissionMap['planning.update_own'],
            $permissionMap['planning.comment'],
        ]);

        Role::findOrCreate('reviewer', 'web')->syncPermissions([
            $permissionMap['planning.view'],
            $permissionMap['planning.review'],
            $permissionMap['planning.comment'],
            $permissionMap['planning.export'],
        ]);

        Role::findOrCreate('manager', 'web')->syncPermissions([
            $permissionMap['planning.view'],
            $permissionMap['planning.review'],
            $permissionMap['planning.approve'],
            $permissionMap['planning.consolidate'],
            $permissionMap['planning.export'],
            $permissionMap['planning.comment'],
        ]);

        Role::findOrCreate('admin', 'web')->syncPermissions($permissionMap->values());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
