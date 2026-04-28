<?php

namespace Modules\HumanResource\Http\Controllers;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Modules\HumanResource\Entities\OrgRoleModulePermission;
use Modules\HumanResource\Entities\SystemRole;
use Modules\HumanResource\Entities\UserOrgRole;

class OrgRoleModulePermissionController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:read_org_governance|read_department', ['only' => ['index']]);
        $this->middleware('permission:create_org_governance|create_department', ['only' => ['store']]);
        $this->middleware('permission:update_org_governance|update_department', ['only' => ['update']]);
        $this->middleware('permission:delete_org_governance|delete_department', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        $selectedModule = trim((string) $request->query('module_key', ''));
        $selectedRole = trim((string) $request->query('org_role', ''));
        $selectedStatus = (string) $request->query('is_active', '');
        $advancedMode = $request->boolean('advanced');

        $matrixReady = $this->isMatrixReady();
        $records = collect();
        if ($matrixReady) {
            $records = OrgRoleModulePermission::query()
                ->with('systemRole:id,code,name,name_km')
                ->when($selectedModule !== '', fn ($q) => $q->where('module_key', $selectedModule))
                ->when($selectedRole !== '', fn ($q) => $q->where('org_role', $selectedRole))
                ->when($selectedStatus === '1' || $selectedStatus === '0', fn ($q) => $q->where('is_active', (int) $selectedStatus))
                ->orderBy('module_key')
                ->orderBy('action_key')
                ->orderBy('org_role')
                ->get();
        }

        $moduleActionMap = OrgRoleModulePermission::moduleActionMap();
        $actionLabels = $this->actionLabels();

        $systemRoles = SystemRole::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'code', 'name', 'name_km']);

        return view('humanresource::master-data.org-role-module-permissions.index', [
            'records' => $records,
            'module_action_map' => $moduleActionMap,
            'module_options' => array_keys($moduleActionMap),
            'org_role_options' => UserOrgRole::roleOptions(),
            'system_roles' => $systemRoles,
            'role_labels' => UserOrgRole::roleLabels(),
            'action_labels' => $actionLabels,
            'selected_module' => $selectedModule,
            'selected_role' => $selectedRole,
            'selected_status' => $selectedStatus,
            'matrix_ready' => $matrixReady,
            'advanced_mode' => $advancedMode,
        ]);
    }

    public function store(Request $request)
    {
        $advancedMode = $request->boolean('advanced_mode');
        if (!$this->isMatrixReady()) {
            Toastr::error(localize('permission_matrix_not_ready', 'Permission matrix table is not ready. Please run migration first.'));
            return redirect()->route('org-role-module-permissions.index');
        }

        $validated = $this->validated($request);
        $systemRoleId = UserOrgRole::resolveSystemRoleIdByCode($validated['org_role']);

        $exists = OrgRoleModulePermission::query()
            ->where('module_key', $validated['module_key'])
            ->where('action_key', $validated['action_key'])
            ->where(function ($query) use ($validated, $systemRoleId) {
                $query->where('org_role', $validated['org_role']);
                if ($systemRoleId) {
                    $query->orWhere('system_role_id', $systemRoleId);
                }
            })
            ->exists();

        if ($exists) {
            return back()
                ->withErrors([
                    'org_role' => localize('duplicate_role_permission', 'This role permission already exists.'),
                ])
                ->withInput();
        }

        OrgRoleModulePermission::create(array_merge($validated, [
            'system_role_id' => $systemRoleId,
        ]));

        Toastr::success(localize('data_save', 'Data saved'));
        return redirect()->route('org-role-module-permissions.index', [
            'advanced' => $advancedMode ? 1 : 0,
        ]);
    }

    public function update(Request $request, OrgRoleModulePermission $org_role_module_permission)
    {
        $advancedMode = $request->boolean('advanced_mode');
        if (!$this->isMatrixReady()) {
            Toastr::error(localize('permission_matrix_not_ready', 'Permission matrix table is not ready. Please run migration first.'));
            return redirect()->route('org-role-module-permissions.index');
        }

        $validated = $this->validated($request);
        $systemRoleId = UserOrgRole::resolveSystemRoleIdByCode($validated['org_role']);

        $exists = OrgRoleModulePermission::query()
            ->where('id', '!=', (int) $org_role_module_permission->id)
            ->where('module_key', $validated['module_key'])
            ->where('action_key', $validated['action_key'])
            ->where(function ($query) use ($validated, $systemRoleId) {
                $query->where('org_role', $validated['org_role']);
                if ($systemRoleId) {
                    $query->orWhere('system_role_id', $systemRoleId);
                }
            })
            ->exists();

        if ($exists) {
            return back()
                ->withErrors([
                    'org_role' => localize('duplicate_role_permission', 'This role permission already exists.'),
                ])
                ->withInput();
        }

        $org_role_module_permission->update(array_merge($validated, [
            'system_role_id' => $systemRoleId,
        ]));

        Toastr::success(localize('data_update', 'Data updated'));
        return redirect()->route('org-role-module-permissions.index', [
            'advanced' => $advancedMode ? 1 : 0,
        ]);
    }

    public function destroy(OrgRoleModulePermission $org_role_module_permission)
    {
        if (!$this->isMatrixReady()) {
            return response()->json([
                'success' => false,
                'message' => localize('permission_matrix_not_ready', 'Permission matrix table is not ready. Please run migration first.'),
            ], 422);
        }

        $org_role_module_permission->delete();

        return response()->json([
            'success' => true,
            'message' => localize('data_delete', 'Deleted successfully'),
        ]);
    }

    protected function validated(Request $request): array
    {
        $moduleActionMap = OrgRoleModulePermission::moduleActionMap();
        $moduleOptions = array_keys($moduleActionMap);

        $validated = $request->validate([
            'module_key' => ['required', Rule::in($moduleOptions)],
            'action_key' => ['required', 'string', 'max:64'],
            'org_role' => ['required', Rule::in(UserOrgRole::roleOptions())],
            'is_active' => ['required', 'boolean'],
            'note' => ['nullable', 'string'],
        ]);

        $moduleKey = trim(mb_strtolower((string) $validated['module_key']));
        $actionKey = trim(mb_strtolower((string) $validated['action_key']));

        $allowedActions = $moduleActionMap[$moduleKey] ?? [];
        if (!in_array($actionKey, $allowedActions, true)) {
            abort(422, localize('invalid_action_for_module', 'Invalid action for selected module.'));
        }

        return [
            'module_key' => $moduleKey,
            'action_key' => $actionKey,
            'org_role' => trim((string) $validated['org_role']),
            'is_active' => (bool) $validated['is_active'],
            'note' => !empty($validated['note']) ? trim((string) $validated['note']) : null,
        ];
    }

    protected function actionLabels(): array
    {
        return [
            'create_incoming' => localize('create_incoming_letter', 'Create incoming letter'),
            'create_outgoing' => localize('create_outgoing_letter', 'Create outgoing letter'),
            'delegate' => localize('delegate', 'Delegate'),
            'office_comment' => localize('office_comment', 'Office comment'),
            'deputy_review' => localize('deputy_review', 'Deputy review'),
            'director_decision' => localize('director_decision', 'Director decision'),
            'distribute' => localize('distribute', 'Distribute'),
            'acknowledge' => localize('acknowledge', 'Acknowledge'),
            'feedback' => localize('feedback', 'Feedback'),
            'close' => localize('close_case', 'Close case'),
            'print' => localize('print', 'Print'),
            'create' => localize('create', 'Create'),
            'review' => localize('review', 'Review'),
            'recommend' => localize('recommend', 'Recommend'),
            'approve' => localize('approve', 'Approve'),
            'reject' => localize('reject', 'Reject'),
            'publish' => localize('publish', 'Publish'),
            'finalize' => localize('finalize', 'Finalize'),
            'create_adjustment' => localize('attendance_create_adjustment', 'Create attendance adjustment'),
            'review_adjustment' => localize('attendance_review_adjustment', 'Review attendance adjustment'),
            'approve_adjustment' => localize('attendance_approve_adjustment', 'Approve attendance adjustment'),
            'finalize_adjustment' => localize('attendance_finalize_adjustment', 'Finalize attendance adjustment'),
            'manage_exceptions' => localize('attendance_manage_exceptions', 'Manage attendance exceptions'),
        ];
    }

    protected function isMatrixReady(): bool
    {
        return Schema::hasTable('org_role_module_permissions');
    }
}
