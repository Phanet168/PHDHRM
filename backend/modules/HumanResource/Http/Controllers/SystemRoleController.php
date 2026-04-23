<?php

namespace Modules\HumanResource\Http\Controllers;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\HumanResource\Entities\SystemRole;

class SystemRoleController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:read_org_governance|read_department', ['only' => ['index']]);
        $this->middleware('permission:create_org_governance|create_department', ['only' => ['store']]);
        $this->middleware('permission:update_org_governance|update_department', ['only' => ['update']]);
        $this->middleware('permission:delete_org_governance|delete_department', ['only' => ['destroy']]);
    }

    public function index()
    {
        $roles = SystemRole::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('humanresource::master-data.system-roles.index', [
            'roles' => $roles,
        ]);
    }

    public function store(Request $request)
    {
        $request->merge([
            'code' => trim(mb_strtolower((string) $request->input('code', ''))),
            'name' => trim((string) $request->input('name', '')),
            'name_km' => trim((string) $request->input('name_km', '')),
        ]);

        $validated = $request->validate([
            'code'     => ['required', 'string', 'max:32', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('system_roles', 'code')],
            'name'     => ['required', 'string', 'max:120', 'regex:/^(?=.*\S).+$/u'],
            'name_km'  => ['nullable', 'string', 'max:120'],
            'level'    => ['required', 'integer', 'min:0', 'max:255'],
            'can_approve' => ['required', 'boolean'],
            'is_active'   => ['required', 'boolean'],
            'sort_order'  => ['nullable', 'integer', 'min:0', 'max:255'],
        ]);

        SystemRole::create([
            'code'        => trim(mb_strtolower((string) $validated['code'])),
            'name'        => trim((string) $validated['name']),
            'name_km'     => !empty($validated['name_km']) ? trim($validated['name_km']) : null,
            'level'       => (int) $validated['level'],
            'can_approve' => (bool) $validated['can_approve'],
            'is_system'   => false,
            'is_active'   => (bool) $validated['is_active'],
            'sort_order'  => (int) ($validated['sort_order'] ?? 0),
            'created_by'  => auth()->id(),
        ]);

        Toastr::success(localize('data_save', 'Data saved'));
        return redirect()->route('system-roles.index');
    }

    public function update(Request $request, SystemRole $system_role)
    {
        $request->merge([
            'code' => trim(mb_strtolower((string) $request->input('code', ''))),
            'name' => trim((string) $request->input('name', '')),
            'name_km' => trim((string) $request->input('name_km', '')),
        ]);

        $validated = $request->validate([
            'code'     => ['required', 'string', 'max:32', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('system_roles', 'code')->ignore($system_role->id)],
            'name'     => ['required', 'string', 'max:120', 'regex:/^(?=.*\S).+$/u'],
            'name_km'  => ['nullable', 'string', 'max:120'],
            'level'    => ['required', 'integer', 'min:0', 'max:255'],
            'can_approve' => ['required', 'boolean'],
            'is_active'   => ['required', 'boolean'],
            'sort_order'  => ['nullable', 'integer', 'min:0', 'max:255'],
        ]);

        $system_role->update([
            'code'        => trim(mb_strtolower((string) $validated['code'])),
            'name'        => trim((string) $validated['name']),
            'name_km'     => !empty($validated['name_km']) ? trim($validated['name_km']) : null,
            'level'       => (int) $validated['level'],
            'can_approve' => (bool) $validated['can_approve'],
            'is_active'   => (bool) $validated['is_active'],
            'sort_order'  => (int) ($validated['sort_order'] ?? 0),
            'updated_by'  => auth()->id(),
        ]);

        Toastr::success(localize('data_update', 'Data updated'));
        return redirect()->route('system-roles.index');
    }

    public function destroy(SystemRole $system_role)
    {
        if ($system_role->is_system) {
            return response()->json([
                'success' => false,
                'message' => localize('cannot_delete_system_role', 'Cannot delete system-protected roles.'),
            ], 422);
        }

        // Check for usage
        $usageCount = $system_role->userOrgRoles()->count()
            + $system_role->modulePermissions()->count()
            + $system_role->workflowSteps()->count();

        if ($usageCount > 0) {
            return response()->json([
                'success' => false,
                'message' => localize('role_in_use', 'This role is currently in use and cannot be deleted.'),
            ], 422);
        }

        $system_role->delete();

        return response()->json([
            'success' => true,
            'message' => localize('data_delete', 'Deleted successfully'),
        ]);
    }
}
