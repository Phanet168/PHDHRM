<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): View
    {
        $this->authorize('roles.manage');

        $roles = Role::withCount(['permissions', 'users'])->orderBy('name')->get();

        return view('admin.roles.index', compact('roles'));
    }

    public function edit(Role $role): View
    {
        $this->authorize('roles.manage');

        $permissions = Permission::orderBy('name')->get();
        $assigned = $role->permissions->pluck('name')->all();

        return view('admin.roles.edit', compact('role', 'permissions', 'assigned'));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('roles.manage');

        $request->validate(['permissions' => ['array']]);

        $role->syncPermissions($request->input('permissions', []));

        AuditLog::record('updated', 'Role', $role->id, "កែប្រែសិទ្ធិសម្រាប់តួនាទី៖ {$role->name}");

        return redirect()->route('admin.roles.index')->with('status', 'បានធ្វើបច្ចុប្បន្នភាពសិទ្ធិដោយជោគជ័យ។');
    }
}
