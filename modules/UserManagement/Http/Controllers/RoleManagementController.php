<?php

namespace Modules\UserManagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\UserManagement\Entities\PerMenu;
use Modules\UserManagement\Http\DataTables\MenuListDataTable;
use Modules\UserManagement\Http\DataTables\PermissionListDataTable;
use Modules\UserManagement\Http\DataTables\RoleListDataTable;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleManagementController extends Controller
{
    /**
     * Ensure Pharmaceutical module permissions exist so they show up in role add/edit.
     */
    private function ensurePharmaceuticalRolePermissions(): void
    {
        $menu = PerMenu::firstOrCreate([
            'menu_name' => 'Pharmaceutical Management',
        ], [
            'parentmenu_id' => null,
            'lable' => 1,
        ]);

        $basePermissionNames = [
            'create_pharmaceutical_management',
            'read_pharmaceutical_management',
            'update_pharmaceutical_management',
            'delete_pharmaceutical_management',
        ];

        $moduleGroups = [
            'Pharm Medicines' => 'pharm_medicines',
            'Pharm Distributions' => 'pharm_distributions',
            'Pharm Dispensings' => 'pharm_dispensings',
            'Pharm Reports' => 'pharm_reports',
            'Pharm Stock' => 'pharm_stock',
            'Pharm Users' => 'pharm_users',
        ];

        $superAdminRole = Role::where('name', 'Super Admin')->where('guard_name', 'web')->first();

        foreach ($basePermissionNames as $permissionName) {
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ], [
                'per_menu_id' => $menu->id,
            ]);

            if ($superAdminRole && !$superAdminRole->hasPermissionTo($permission->name)) {
                $superAdminRole->givePermissionTo($permission);
            }
        }

        foreach ($moduleGroups as $submenuName => $permissionPrefix) {
            $submenu = PerMenu::firstOrCreate([
                'parentmenu_id' => $menu->id,
                'menu_name' => $submenuName,
            ], [
                'lable' => 2,
            ]);

            foreach (['create', 'read', 'update', 'delete'] as $action) {
                $permission = Permission::firstOrCreate([
                    'name' => $action . '_' . $permissionPrefix,
                    'guard_name' => 'web',
                ], [
                    'per_menu_id' => $submenu->id,
                ]);

                if ($superAdminRole && !$superAdminRole->hasPermissionTo($permission->name)) {
                    $superAdminRole->givePermissionTo($permission);
                }
            }
        }
    }

    /**
     * Ensure Correspondence module permissions exist so they show up in role add/edit.
     */
    private function ensureCorrespondenceRolePermissions(): void
    {
        $menu = PerMenu::firstOrCreate([
            'menu_name' => 'Correspondence Management',
        ], [
            'parentmenu_id' => null,
            'lable' => 1,
        ]);

        $permissionNames = [
            'create_correspondence_management',
            'read_correspondence_management',
            'update_correspondence_management',
            'delete_correspondence_management',
        ];

        $superAdminRole = Role::where('name', 'Super Admin')->where('guard_name', 'web')->first();

        foreach ($permissionNames as $permissionName) {
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ], [
                'per_menu_id' => $menu->id,
            ]);

            if ($superAdminRole && !$superAdminRole->hasPermissionTo($permission->name)) {
                $superAdminRole->givePermissionTo($permission);
            }
        }
    }

    //role list
    public function roleList(RoleListDataTable $dataTable)
    {
        return $dataTable->render('usermanagement::role-management.role-list');
    }

    //role add
    public function roleCreate()
    {
        $this->ensurePharmaceuticalRolePermissions();
        $this->ensureCorrespondenceRolePermissions();
        $permissions = Permission::all();
        $perMenu = PerMenu::with('subMenu', 'permission')->whereNull('parentmenu_id')->orderBy('id', 'ASC')->get();
        return view('usermanagement::role-management.role-add', compact('permissions', 'perMenu'));
    }

    //role add
    public function roleView()
    {
        $this->ensurePharmaceuticalRolePermissions();
        $this->ensureCorrespondenceRolePermissions();
        $permissions = Permission::all();
        $perMenu = PerMenu::with('subMenu', 'permission')->where('parentmenu_id', 'null')->orderBy('id', 'ASC')->get();
        return view('usermanagement::role-management.role-view', compact('permissions', 'perMenu'));
    }

    //role store
    public function roleStore(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required',
            'permission' => 'required',
        ]);

        $role = Role::create($validated);
        $role->syncPermissions($request->permission);
        return redirect()->route('role.list')->with('success', 'Role Created Successfully');
    }

    //role edit
    public function roleEdit(Role $role)
    {
        $this->ensurePharmaceuticalRolePermissions();
        $this->ensureCorrespondenceRolePermissions();
        $roles = $role;
        $permissions = Permission::all();
        $perMenu = PerMenu::with('subMenu', 'permission')->whereNull('parentmenu_id')->orderBy('id', 'ASC')->get();
        $roleHasPermission = $role->permissions->pluck('name');

        return view('usermanagement::role-management.role-edit', compact('roles', 'permissions', 'perMenu', 'roleHasPermission'));
    }

    //role update
    public function roleUpdate(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required',
            'permission' => 'required',
        ]);

        $role = Role::findOrFail($request->role_id);
        $role->update($validated);
        $role->syncPermissions($request->permission);
        return redirect()->back()->with('success', 'Role Updated Successfully');
    }

    //role delete
    public function roleDelete(Request $request)
    {
        $id = $request->id;
        $role = Role::findOrFail($id);
        $role->permissions()->detach();
        $role->delete();
        return response()->json(['status' => 'success', 'message' => 'Role Deleted Successfully']);
    }

    //menu list
    public function menuList(MenuListDataTable $dataTable)
    {
        $menuName = PerMenu::all();
        return $dataTable->render('usermanagement::role-management.menu-list', compact('menuName'));
    }

    //menu create
    public function menuStore(Request $request)
    {
        $validated = $request->validate([
            'menu_name' => 'required',
            'parentmenu_id' => '',
        ]);
        if (!empty($request->parentmenu_id)) {
            $menuDetail = PerMenu::findOrFail($request->parentmenu_id);
            $validated['lable'] = (int) $menuDetail->lable + 1;
        } else {
            $validated['lable'] = 1;
            $validated['parentmenu_id'] = 0;
        }

        PerMenu::create($validated);
        return response()->json(['status' => 'success', 'message' => 'Menu Create Successfully']);
    }

    //menu edit
    public function menuEdit($id)
    {
        $data = PerMenu::where('id', $id)->first();
        $menuName = PerMenu::all();
        return response()->view('usermanagement::role-management.menu-edit', compact('data', 'menuName'));
    }

    //menu update
    public function menuUpdate(Request $request, PerMenu $menupermission)
    {
        $validated = $request->validate([
            'menu_name' => 'required',
            'parentmenu_id' => '',
        ]);

        if (!empty($request->parentmenu_id)) {
            $menuDetail = PerMenu::findOrFail($request->parentmenu_id);
            $validated['lable'] = (int) $menuDetail->lable + 1;
        } else {
            $validated['lable'] = 1;
            $validated['parentmenu_id'] = 0;
        }

        $menupermission->update($validated);
        return response()->json(['status' => 'success', 'message' => 'Menu Updated Successfully']);
    }

    //menu delete
    public function menuDelete(Request $request)
    {
        $id = $request->id;
        $menupermission = PerMenu::findOrFail($id);
        $subMenu = PerMenu::where('parentmenu_id', $id)->get();
        foreach ($subMenu as $key => $value) {
            $childMenu = PerMenu::where('parentmenu_id', $value->id)->get();
            foreach ($childMenu as $key => $value) {
                $value->permission()->delete();
                $value->delete();
            }
            $value->permission()->delete();
            $value->delete();
        }
        $menupermission->permission()->delete();
        $menupermission->delete();

        return response()->json(['status' => 'success', 'message' => 'Menu Deleted Successfully']);
    }

    //permission list
    public function permissionList(PermissionListDataTable $dataTable)
    {
        $perMenu = PerMenu::all();
        return $dataTable->render('usermanagement::role-management.permission-list', compact('perMenu'));
    }

    //permissionStore
    public function permissionStore(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required',
            'per_menu_id' => 'required',
        ]);
        Permission::create($validated);
        return response()->json(['status' => 'success', 'message' => 'Permission Create Successfully']);
    }

    //permission delete
    public function permissionDelete(Request $request)
    {
        $id = $request->id;
        $permission = Permission::findOrFail($id);
        $permission->delete();
        return response()->json(['status' => 'success', 'message' => 'Permission Deleted Successfully']);
    }
}
