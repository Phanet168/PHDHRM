<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AccessControlAuditQueryService;
use App\Services\AccessControlAuditService;
use App\Services\AccessControlService;
use App\Services\OrganizationScopeService;
use App\Services\PermissionCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\HumanResource\Entities\Delegation;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\Position;
use Modules\HumanResource\Entities\SystemRole;
use Modules\HumanResource\Entities\UserAssignment;
use Modules\HumanResource\Entities\UserOrgRole;
use Modules\HumanResource\Entities\WorkflowDefinition;
use Modules\HumanResource\Entities\WorkflowDefinitionStep;
use Modules\HumanResource\Services\DelegationService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Phase 3A/3B: Access Control Center (មជ្ឈមណ្ឌលគ្រប់គ្រងសិទ្ធិ).
 *
 * This is the centralized authorization admin screen, built entirely on the
 * Phase 2 foundation (AccessControlService, PermissionCatalogService, Spatie
 * roles/permissions, model_has_permissions) plus, from Phase 3B, the
 * organization-scope layer (OrganizationScopeService over the existing
 * UserAssignment table -- no new schema). It does not introduce a second
 * permission system, does not touch existing module authorization
 * (HR/Payroll/Attendance/Leave/Mission/Correspondence/Planning/
 * Pharmaceutical), and does not modify Gate::before / Super Admin bypass
 * behavior.
 *
 * Every mutating action here is additionally protected by the SAME
 * pre-existing Spatie permissions already used by modules/UserManagement's
 * RoleManagementController/UserManagementController conventions
 * (read/create/update/delete _role_list, read/create/update/delete
 * _user_list) -- see routes/web.php for the per-action middleware.
 */
class AccessControlCenterController extends Controller
{
    /** Roles that must not be renamed, have their permissions edited, or be deleted from this screen. */
    private const PROTECTED_ROLE_NAMES = ['Super Admin'];

    /** Khmer labels for PermissionCatalogService::MODULE_LABELS's fixed 8-value set. */
    private const CATALOG_MODULE_LABELS_KM = [
        'planning' => 'ផែនការ',
        'pharmaceutical' => 'ឱសថស្ថាន',
        'correspondence' => 'លិខិតឆ្លងឆ្លើយ',
        'accounts' => 'គណនេយ្យ',
        'report' => 'របាយការណ៍',
        'setting' => 'ការកំណត់',
        'user_management' => 'គ្រប់គ្រងអ្នកប្រើប្រាស់',
        'human_resource' => 'ធនធានមនុស្ស',
    ];

    /** Khmer labels for PermissionCatalogService::ACTION_PREFIXES's normalized action set (plus the "other"/dot-notation leftovers actually observed in the catalog). */
    private const CATALOG_ACTION_LABELS_KM = [
        'create' => 'បង្កើត',
        'view' => 'មើល',
        'update' => 'កែប្រែ',
        'delete' => 'លុប',
        'approve' => 'អនុម័ត',
        'reject' => 'បដិសេធ',
        'export' => 'នាំចេញ',
        'manage' => 'គ្រប់គ្រង',
        'manage_master_data' => 'គ្រប់គ្រងទិន្នន័យមូលដ្ឋាន',
        'review' => 'ពិនិត្យ',
        'submit' => 'ដាក់ស្នើ',
        'consolidate' => 'បូកសរុប',
        'comment' => 'មតិយោបល់',
        'calculate' => 'គណនា',
        'update_own' => 'កែប្រែផ្ទាល់ខ្លួន',
        'other' => 'ផ្សេងទៀត',
    ];

    public function __construct(
        private readonly AccessControlService $accessControlService,
        private readonly PermissionCatalogService $permissionCatalog,
        private readonly OrganizationScopeService $organizationScopeService,
        private readonly DelegationService $delegationService,
        private readonly AccessControlAuditService $auditService,
        private readonly AccessControlAuditQueryService $auditQueryService,
    ) {
    }

    public function index(): View
    {
        return view('backend.access-control.index', [
            'summary' => $this->buildSummary(),
        ]);
    }

    public function summary(): JsonResponse
    {
        return response()->json(['status' => 'ok', 'data' => $this->buildSummary()]);
    }

    private function buildSummary(): array
    {
        return [
            'active_users' => User::query()->where('is_active', true)->count(),
            'roles' => Role::query()->where('guard_name', 'web')->count(),
            'permissions' => Permission::query()->where('guard_name', 'web')->count(),
            'organizational_units' => Department::query()->where('is_active', true)->count(),
        ];
    }

    // ------------------------------------------------------------------
    // Permission catalog (presentation layer over PermissionCatalogService)
    // ------------------------------------------------------------------

    public function catalog(): JsonResponse
    {
        $grouped = $this->permissionCatalog->groupedByModule()->map(function ($entries, $module) {
            return [
                'module' => $module,
                'module_label' => self::CATALOG_MODULE_LABELS_KM[$module]
                    ?? ($entries->first()['module_label'] ?? $module),
                'resources' => $entries->groupBy('resource')->map(function ($resourceEntries, $resource) {
                    $resourceDisplayName = $resourceEntries->first()['display_name'] ?? $resource;

                    return [
                        'resource' => $resource,
                        'display_name' => localize($resourceDisplayName, $resourceDisplayName),
                        'permissions' => $resourceEntries->map(fn ($entry) => [
                            'id' => Permission::where('name', $entry['permission'])->value('id'),
                            'name' => $entry['permission'],
                            'action' => $entry['action'],
                            'action_label' => self::CATALOG_ACTION_LABELS_KM[$entry['action']]
                                ?? $this->humanizeKey($entry['action']),
                            'display_name' => $entry['display_name'],
                        ])->values(),
                    ];
                })->values(),
            ];
        })->values();

        return response()->json(['status' => 'ok', 'data' => $grouped]);
    }

    // ------------------------------------------------------------------
    // Roles & Permissions
    // ------------------------------------------------------------------

    public function roles(): JsonResponse
    {
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => $this->roleSummary($role));

        return response()->json(['status' => 'ok', 'data' => $roles]);
    }

    public function roleShow(Role $role): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'data' => array_merge($this->roleSummary($role->loadCount('users')), [
                'permission_ids' => $role->permissions()->pluck('permissions.id')->values(),
            ]),
        ]);
    }

    private function roleSummary(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'user_count' => (int) ($role->users_count ?? $role->users()->count()),
            'protected' => $this->isProtectedRole($role),
        ];
    }

    private function isProtectedRole(Role $role): bool
    {
        return in_array($role->name, self::PROTECTED_ROLE_NAMES, true);
    }

    public function roleStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('roles', 'name')->where('guard_name', 'web')],
        ]);

        $role = DB::transaction(fn () => Role::create(['name' => $validated['name'], 'guard_name' => 'web']));

        $this->auditService->record(
            AccessControlAuditService::CATEGORY_ROLE_PERMISSION,
            'role_created',
            $role,
            $role->name,
            [],
            ['name' => $role->name]
        );

        return response()->json([
            'status' => 'ok',
            'message' => 'តួនាទីត្រូវបានបង្កើតដោយជោគជ័យ។',
            'data' => $this->roleSummary($role),
        ], 201);
    }

    public function roleUpdate(Request $request, Role $role): JsonResponse
    {
        if ($this->isProtectedRole($role)) {
            return $this->protectedRoleResponse();
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role->id)],
        ]);

        $beforeName = $role->name;

        DB::transaction(function () use ($role, $validated) {
            $role->update(['name' => $validated['name']]);
        });

        $this->auditService->record(
            AccessControlAuditService::CATEGORY_ROLE_PERMISSION,
            'role_renamed',
            $role,
            $validated['name'],
            ['name' => $beforeName],
            ['name' => $validated['name']]
        );

        return response()->json([
            'status' => 'ok',
            'message' => 'ព័ត៌មានតួនាទីត្រូវបានកែប្រែ។',
            'data' => $this->roleSummary($role->fresh()->loadCount('users')),
        ]);
    }

    public function roleDestroy(Role $role): JsonResponse
    {
        if ($this->isProtectedRole($role)) {
            return $this->protectedRoleResponse();
        }

        $userCount = $role->users()->count();
        if ($userCount > 0) {
            return response()->json([
                'status' => 'error',
                'message' => "មិនអាចលុបតួនាទីនេះបានទេ ព្រោះមានអ្នកប្រើប្រាស់ {$userCount} នាក់កំពុងប្រើប្រាស់វា។ សូមដកតួនាទីនេះចេញពីអ្នកប្រើប្រាស់ទាំងអស់ជាមុនសិន។",
            ], 422);
        }

        $roleName = $role->name;
        $permissionNames = $role->permissions()->pluck('name')->all();
        $roleId = $role->id;

        DB::transaction(function () use ($role) {
            $role->permissions()->detach();
            $role->delete();
        });

        $this->auditService->record(
            AccessControlAuditService::CATEGORY_ROLE_PERMISSION,
            'role_deleted',
            null,
            $roleName,
            ['name' => $roleName, 'permissions' => $permissionNames, 'role_id' => $roleId],
            []
        );

        return response()->json(['status' => 'ok', 'message' => 'តួនាទីត្រូវបានលុបដោយជោគជ័យ។']);
    }

    public function rolePermissionsUpdate(Request $request, Role $role): JsonResponse
    {
        if ($this->isProtectedRole($role)) {
            return $this->protectedRoleResponse();
        }

        $validated = $request->validate([
            'permission_ids' => ['array'],
            'permission_ids.*' => ['integer'],
        ]);

        $requestedIds = array_map('intval', $validated['permission_ids'] ?? []);
        $validIds = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('id', $requestedIds)
            ->pluck('id')
            ->all();

        $beforeNames = $role->permissions()->pluck('name')->all();

        DB::transaction(function () use ($role, $validIds) {
            $role->syncPermissions($validIds);
        });

        $role->forgetCachedPermissions();

        $afterNames = $role->fresh()->permissions()->pluck('name')->all();

        $this->auditService->record(
            AccessControlAuditService::CATEGORY_ROLE_PERMISSION,
            'role_permissions_updated',
            $role,
            $role->name,
            ['permissions' => $beforeNames],
            ['permissions' => $afterNames]
        );

        return response()->json([
            'status' => 'ok',
            'message' => 'សិទ្ធិរបស់តួនាទីត្រូវបានធ្វើបច្ចុប្បន្នភាព។',
            'data' => [
                'added' => array_values(array_diff($afterNames, $beforeNames)),
                'removed' => array_values(array_diff($beforeNames, $afterNames)),
                'permission_ids' => $validIds,
            ],
        ]);
    }

    private function protectedRoleResponse(): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => 'តួនាទីនេះជាតួនាទីប្រព័ន្ធដែលការពារទុក (Super Admin) មិនអាចកែប្រែ ឬលុបបានពីអេក្រង់នេះឡើយ។',
        ], 422);
    }

    // ------------------------------------------------------------------
    // Users
    // ------------------------------------------------------------------

    public function usersSearch(Request $request): JsonResponse
    {
        $keyword = trim((string) $request->query('q', ''));

        $query = User::query()
            ->with(['employee:id,user_id,employee_id,first_name,last_name,department_id,sub_department_id,position_id',
                'employee.department:id,department_name', 'employee.sub_department:id,department_name',
                'employee.position:id,position_name,position_name_km'])
            ->orderBy('full_name');

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('full_name', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%")
                    ->orWhere('user_name', 'like', "%{$keyword}%")
                    ->orWhereHas('employee', function ($employeeQuery) use ($keyword) {
                        // full_name is a computed Employee accessor (last_name . ' ' . first_name),
                        // not a real column -- an unqualified reference here would silently
                        // resolve against the OUTER users.full_name column instead of erroring
                        // (correlated-subquery column scoping), never actually matching the
                        // employee's own name. Query the real columns instead.
                        $employeeQuery->where('employee_id', 'like', "%{$keyword}%")
                            ->orWhere('first_name', 'like', "%{$keyword}%")
                            ->orWhere('last_name', 'like', "%{$keyword}%")
                            ->orWhereRaw("CONCAT(last_name, ' ', first_name) like ?", ["%{$keyword}%"]);
                    });
            });
        }

        $users = $query->limit(30)->get()->map(fn (User $user) => $this->userListItem($user));

        return response()->json(['status' => 'ok', 'data' => $users]);
    }

    private function userListItem(User $user): array
    {
        $employee = $user->employee;
        $unit = $employee?->sub_department?->department_name ?: $employee?->department?->department_name;

        return [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'employee_code' => $employee?->employee_id,
            'position_name' => $employee?->position?->position_name_km ?: $employee?->position?->position_name,
            'unit_name' => $unit,
            'is_active' => (bool) $user->is_active,
        ];
    }

    public function userShow(User $user): JsonResponse
    {
        $user->load(['employee.department:id,department_name', 'employee.sub_department:id,department_name', 'employee.position:id,position_name,position_name_km']);

        $roles = $user->roles()->where('guard_name', 'web')->get(['roles.id', 'roles.name']);
        $directPermissions = $user->getDirectPermissions()->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values();

        return response()->json([
            'status' => 'ok',
            'data' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'is_active' => (bool) $user->is_active,
                'employee_code' => $user->employee?->employee_id,
                'unit_name' => $user->employee?->sub_department?->department_name ?: $user->employee?->department?->department_name,
                'position_name' => $user->employee?->position?->position_name_km ?: $user->employee?->position?->position_name,
                'super_admin' => $this->accessControlService->isSuperAdmin($user),
                'roles' => $roles,
                'direct_permissions' => $directPermissions,
                'scope' => $this->accessControlService->resolveScope($user),
                'effective_permission_count' => count($this->accessControlService->effectivePermissionNames($user)),
                'scope_groups' => $this->organizationScopeService->assignmentGroups($user),
            ],
        ]);
    }

    // ------------------------------------------------------------------
    // Organization Scope (Phase 3B)
    // ------------------------------------------------------------------

    public function orgTree(): JsonResponse
    {
        return response()->json(['status' => 'ok', 'data' => $this->organizationScopeService->hierarchyPickerOptions()]);
    }

    public function userScopes(User $user): JsonResponse
    {
        return response()->json(['status' => 'ok', 'data' => $this->organizationScopeService->assignmentGroups($user)]);
    }

    public function userScopeUpdate(Request $request, User $user, string $groupKey): JsonResponse
    {
        $validated = $request->validate([
            'scope_type' => ['required', 'string', Rule::in(OrganizationScopeService::presentationOptions())],
            'department_ids' => ['required', 'array', 'min:1'],
            'department_ids.*' => ['integer'],
        ]);

        $beforeGroup = collect($this->organizationScopeService->assignmentGroups($user))
            ->firstWhere('group_key', $groupKey);

        try {
            $data = $this->organizationScopeService->updateGroupScope(
                $user,
                $groupKey,
                $validated['scope_type'],
                $validated['department_ids'],
                Auth::id()
            );
        } catch (ValidationException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => collect($exception->errors())->flatten()->first() ?? 'មានបញ្ហាកើតឡើង។',
            ], 422);
        }

        $this->auditService->record(
            AccessControlAuditService::CATEGORY_ORGANIZATION_SCOPE,
            'organization_scope_updated',
            $user,
            $user->full_name,
            [
                'scope' => $beforeGroup['presentation_scope_label'] ?? null,
                'units' => collect($beforeGroup['selected_departments'] ?? [])->pluck('name')->all(),
            ],
            [
                'scope' => $data['presentation_scope_label'] ?? null,
                'units' => collect($data['selected_departments'] ?? [])->pluck('name')->all(),
            ]
        );

        return response()->json([
            'status' => 'ok',
            'message' => 'វិសាលភាពអង្គភាពត្រូវបានធ្វើបច្ចុប្បន្នភាព។',
            'data' => $data,
        ]);
    }

    public function userRolesUpdate(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role_ids' => ['array'],
            'role_ids.*' => ['integer'],
        ]);

        $requestedIds = array_map('intval', $validated['role_ids'] ?? []);
        $validIds = Role::query()->where('guard_name', 'web')->whereIn('id', $requestedIds)->pluck('id')->all();

        $superAdminRoleId = Role::query()->where('name', 'Super Admin')->where('guard_name', 'web')->value('id');
        if ($superAdminRoleId
            && in_array($superAdminRoleId, $user->roles()->pluck('roles.id')->all(), true)
            && !in_array($superAdminRoleId, $validIds, true)
        ) {
            $otherSuperAdmins = User::query()
                ->where('id', '!=', $user->id)
                ->whereHas('roles', fn ($q) => $q->where('roles.id', $superAdminRoleId))
                ->count();
            $otherSystemAdmins = User::query()->where('id', '!=', $user->id)->where('user_type_id', 1)->count();

            if ($otherSuperAdmins === 0 && $otherSystemAdmins === 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'មិនអាចដកតួនាទី Super Admin ចេញបានទេ ព្រោះនេះជាអ្នកប្រើប្រាស់ Super Admin ចុងក្រោយក្នុងប្រព័ន្ធ។',
                ], 422);
            }
        }

        $beforeNames = $user->roles()->pluck('roles.name')->all();

        DB::transaction(function () use ($user, $validIds) {
            $user->syncRoles($validIds);
        });

        $afterNames = $user->fresh()->roles()->pluck('roles.name')->all();

        $this->auditService->record(
            AccessControlAuditService::CATEGORY_USER_ACCESS,
            'user_roles_updated',
            $user,
            $user->full_name,
            ['roles' => $beforeNames],
            ['roles' => $afterNames]
        );

        return response()->json([
            'status' => 'ok',
            'message' => 'តួនាទីរបស់អ្នកប្រើប្រាស់ត្រូវបានធ្វើបច្ចុប្បន្នភាព។',
            'data' => $user->fresh()->roles()->get(['roles.id', 'roles.name']),
        ]);
    }

    public function userDirectPermissionsUpdate(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'permission_ids' => ['array'],
            'permission_ids.*' => ['integer'],
        ]);

        $requestedIds = array_map('intval', $validated['permission_ids'] ?? []);
        $validIds = Permission::query()->where('guard_name', 'web')->whereIn('id', $requestedIds)->pluck('id')->all();

        $beforeNames = $user->getDirectPermissions()->pluck('name')->all();

        DB::transaction(function () use ($user, $validIds) {
            $user->syncPermissions($validIds);
        });

        $afterNames = $user->fresh()->getDirectPermissions()->pluck('name')->all();

        $this->auditService->record(
            AccessControlAuditService::CATEGORY_USER_ACCESS,
            'user_direct_permissions_updated',
            $user,
            $user->full_name,
            ['direct_permissions' => $beforeNames],
            ['direct_permissions' => $afterNames]
        );

        return response()->json([
            'status' => 'ok',
            'message' => 'សិទ្ធិផ្ទាល់ខ្លួនរបស់អ្នកប្រើប្រាស់ត្រូវបានធ្វើបច្ចុប្បន្នភាព។',
            'data' => $user->fresh()->getDirectPermissions()->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values(),
        ]);
    }

    // ------------------------------------------------------------------
    // Approval Authority (ការអនុម័ត).
    //
    // Reuses the SAME workflow engine already driving Leave/Notice/Mission/
    // Correspondence/Attendance approvals (WorkflowDefinition,
    // WorkflowDefinitionStep) and the SAME permissions already protecting the
    // existing Workflow Policies screen
    // (Modules\HumanResource\Http\Controllers\WorkflowPolicyController) --
    // read_org_governance|read_department for viewing,
    // update_org_governance|update_department for editing. This does not
    // replace that screen; it is an additional, simplified surface over the
    // exact same tables for admins who prefer to configure approval steps
    // from the Access Control Center without leaving it.
    // ------------------------------------------------------------------

    private const ACTION_TYPE_OPTIONS = ['review', 'recommend', 'approve'];

    private const APPROVAL_MODULE_LABELS = [
        'leave' => 'ការឈប់សម្រាក (Leave)',
        'notice' => 'សេចក្តីជូនដំណឹង (Notice)',
        'mission' => 'បេសកកម្ម (Mission)',
        'correspondence' => 'លិខិតឆ្លងឆ្លើយ (Correspondence)',
        'attendance' => 'វត្តមាន (Attendance)',
    ];

    public function approvals(): JsonResponse
    {
        $definitions = WorkflowDefinition::query()
            ->withCount('steps')
            ->orderBy('module_key')
            ->orderBy('request_type_key')
            ->orderBy('priority')
            ->get()
            ->map(fn (WorkflowDefinition $definition) => $this->approvalDefinitionSummary($definition));

        return response()->json(['status' => 'ok', 'data' => $definitions]);
    }

    private function approvalDefinitionSummary(WorkflowDefinition $definition): array
    {
        return [
            'id' => $definition->id,
            'module_key' => $definition->module_key,
            'module_label' => self::APPROVAL_MODULE_LABELS[$definition->module_key] ?? $this->humanizeKey((string) $definition->module_key),
            'request_type_key' => $definition->request_type_key,
            'name' => $definition->name,
            'priority' => $definition->priority,
            'is_active' => (bool) $definition->is_active,
            'step_count' => (int) ($definition->steps_count ?? $definition->steps()->count()),
        ];
    }

    private function humanizeKey(string $key): string
    {
        return ucwords(str_replace(['_', '-', '.'], ' ', trim($key)));
    }

    public function approvalOptions(): JsonResponse
    {
        return response()->json(['status' => 'ok', 'data' => [
            'action_type_options' => collect(self::ACTION_TYPE_OPTIONS)->map(fn ($v) => ['value' => $v, 'label' => $this->actionTypeLabel($v)])->values(),
            'actor_type_options' => collect(WorkflowDefinitionStep::actorTypeOptions())->map(fn ($v) => ['value' => $v, 'label' => $this->actorTypeLabel($v)])->values(),
            'scope_type_options' => collect(UserOrgRole::scopeOptions())->map(fn ($v) => ['value' => $v, 'label' => $this->scopeTypeLabel($v)])->values(),
            'positions' => Position::query()->withoutGlobalScope('sortByLatest')->where('is_active', 1)->orderBy('position_name')->get(['id', 'position_name', 'position_name_km']),
            'responsibilities' => SystemRole::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(['id', 'code', 'name', 'name_km']),
            'spatie_roles' => Role::query()->where('guard_name', 'web')->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->withoutGlobalScope('sortByLatest')->where('is_active', 1)->orderBy('full_name')->limit(500)->get(['id', 'full_name', 'email']),
        ]]);
    }

    private function actionTypeLabel(string $value): string
    {
        return [
            'review' => 'ពិនិត្យ',
            'recommend' => 'ផ្តល់យោបល់/ស្នើ',
            'approve' => 'អនុម័ត',
        ][$value] ?? $value;
    }

    private function actorTypeLabel(string $value): string
    {
        return [
            WorkflowDefinitionStep::ACTOR_TYPE_SPECIFIC_USER => 'អ្នកប្រើប្រាស់ជាក់លាក់ (Specific user)',
            WorkflowDefinitionStep::ACTOR_TYPE_POSITION => 'តួនាទីការងារ (Position)',
            WorkflowDefinitionStep::ACTOR_TYPE_RESPONSIBILITY => 'ការទទួលខុសត្រូវ (Responsibility)',
            WorkflowDefinitionStep::ACTOR_TYPE_SPATIE_ROLE => 'តួនាទីប្រព័ន្ធ (Spatie role)',
        ][$value] ?? $value;
    }

    private function scopeTypeLabel(string $value): string
    {
        return [
            UserOrgRole::SCOPE_SELF_ONLY => 'ខ្លួនឯង',
            UserOrgRole::SCOPE_SELF_UNIT_ONLY => 'អង្គភាពរបស់ខ្លួន',
            UserOrgRole::SCOPE_SELF_AND_CHILDREN => 'អង្គភាពរបស់ខ្លួន និងអង្គភាពក្រោមឱវាទ',
            UserOrgRole::SCOPE_ALL => 'អង្គភាពទាំងមូល',
        ][$value] ?? $value;
    }

    public function approvalShow(WorkflowDefinition $workflowDefinition): JsonResponse
    {
        $workflowDefinition->load([
            'steps' => fn ($q) => $q->orderBy('step_order'),
            'steps.actorUser:id,full_name,email',
            'steps.actorPosition:id,position_name,position_name_km',
            'steps.actorResponsibility:id,code,name,name_km',
            'steps.actorRole:id,name',
        ]);

        return response()->json(['status' => 'ok', 'data' => array_merge(
            $this->approvalDefinitionSummary($workflowDefinition),
            [
                'description' => $workflowDefinition->description,
                'steps' => $workflowDefinition->steps->map(fn (WorkflowDefinitionStep $step) => $this->approvalStepPayload($step))->values(),
            ]
        )]);
    }

    private function approvalStepPayload(WorkflowDefinitionStep $step): array
    {
        return [
            'id' => $step->id,
            'step_order' => $step->step_order,
            'step_name' => $step->step_name,
            'action_type' => $step->action_type,
            'actor_type' => $step->getEffectiveActorType(),
            'actor_user_id' => $step->actor_user_id,
            'actor_position_id' => $step->actor_position_id,
            'actor_responsibility_id' => $step->actor_responsibility_id,
            'actor_role_id' => $step->actor_role_id,
            'actor_label' => $this->actorDescription($step),
            'scope_type' => $step->scope_type,
            'scope_label' => $this->scopeTypeLabel((string) $step->scope_type),
            'is_final_approval' => (bool) $step->is_final_approval,
            'is_required' => (bool) $step->is_required,
            'can_return' => (bool) $step->can_return,
            'can_reject' => (bool) $step->can_reject,
        ];
    }

    private function actorDescription(WorkflowDefinitionStep $step): string
    {
        return match ($step->getEffectiveActorType()) {
            WorkflowDefinitionStep::ACTOR_TYPE_SPECIFIC_USER => $step->actorUser?->full_name ?? 'N/A',
            WorkflowDefinitionStep::ACTOR_TYPE_POSITION => $step->actorPosition?->position_name_km ?: ($step->actorPosition?->position_name ?? 'N/A'),
            WorkflowDefinitionStep::ACTOR_TYPE_SPATIE_ROLE => $step->actorRole?->name ?? 'N/A',
            default => $step->actorResponsibility?->name_km ?: ($step->actorResponsibility?->name ?? 'N/A'),
        };
    }

    public function approvalUpdate(Request $request, WorkflowDefinition $workflowDefinition): JsonResponse
    {
        try {
            $payload = $this->validateApprovalPayload($request);
        } catch (ValidationException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => collect($exception->errors())->flatten()->first() ?? 'មានបញ្ហាកើតឡើង។',
            ], 422);
        }

        $beforeName = $workflowDefinition->name;
        $beforeSteps = $workflowDefinition->steps()
            ->orderBy('step_order')
            ->get(['step_order', 'step_name', 'actor_type', 'scope_type'])
            ->map->only(['step_order', 'step_name', 'actor_type', 'scope_type'])
            ->values()->all();

        DB::transaction(function () use ($workflowDefinition, $payload): void {
            $workflowDefinition->update([
                'name' => $payload['name'],
                'priority' => $payload['priority'],
                'is_active' => $payload['is_active'],
                'updated_by' => Auth::id(),
            ]);

            // Steps use SoftDeletes; force delete old rows first to avoid
            // unique (workflow_definition_id, step_order) conflicts when
            // recreating the matrix -- same approach as the existing
            // Workflow Policies screen's update().
            $workflowDefinition->steps()->withTrashed()->forceDelete();

            foreach ($payload['steps'] as $step) {
                $workflowDefinition->steps()->create(array_merge($step, [
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]));
            }
        });

        $afterSteps = collect($payload['steps'])
            ->map(fn (array $s) => collect($s)->only(['step_order', 'step_name', 'actor_type', 'scope_type'])->all())
            ->sortBy('step_order')->values()->all();

        $this->auditService->record(
            AccessControlAuditService::CATEGORY_APPROVAL_AUTHORITY,
            'approval_authority_updated',
            $workflowDefinition,
            self::APPROVAL_MODULE_LABELS[$workflowDefinition->module_key] ?? $workflowDefinition->module_key,
            ['name' => $beforeName, 'steps' => $beforeSteps],
            ['name' => $payload['name'], 'steps' => $afterSteps]
        );

        return response()->json([
            'status' => 'ok',
            'message' => 'ការកំណត់ការអនុម័តត្រូវបានធ្វើបច្ចុប្បន្នភាព។',
            'data' => $this->approvalShow($workflowDefinition->fresh())->getData(true)['data'],
        ]);
    }

    private function validateApprovalPayload(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'priority' => ['required', 'integer', 'min:1', 'max:9999'],
            'is_active' => ['required', 'boolean'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.step_order' => ['required', 'integer', 'min:1', 'max:255'],
            'steps.*.step_name' => ['required', 'string', 'max:190'],
            'steps.*.action_type' => ['required', Rule::in(self::ACTION_TYPE_OPTIONS)],
            'steps.*.actor_type' => ['required', Rule::in(WorkflowDefinitionStep::actorTypeOptions())],
            'steps.*.actor_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'steps.*.actor_position_id' => ['nullable', 'integer', Rule::exists('positions', 'id')],
            'steps.*.actor_responsibility_id' => ['nullable', 'integer', Rule::exists('system_roles', 'id')],
            'steps.*.actor_role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')],
            'steps.*.scope_type' => ['required', Rule::in(UserOrgRole::scopeOptions())],
            'steps.*.is_final_approval' => ['required', 'boolean'],
            'steps.*.is_required' => ['required', 'boolean'],
            'steps.*.can_return' => ['required', 'boolean'],
            'steps.*.can_reject' => ['required', 'boolean'],
        ]);

        $steps = collect((array) $validated['steps'])
            ->map(fn (array $step) => $this->normalizeApprovalStep($step))
            ->sortBy('step_order')
            ->values();

        if ($steps->pluck('step_order')->unique()->count() !== $steps->count()) {
            throw ValidationException::withMessages([
                'steps' => 'លំដាប់ជំហាន (Step order) ត្រូវតែមិនស្ទួនគ្នា។',
            ]);
        }

        if ($steps->where('is_final_approval', true)->count() === 0) {
            throw ValidationException::withMessages([
                'steps' => 'ត្រូវការជំហានយ៉ាងហោចណាស់មួយដែលជាការអនុម័តចុងក្រោយ (Final approval)។',
            ]);
        }

        return [
            'name' => trim((string) $validated['name']),
            'priority' => (int) $validated['priority'],
            'is_active' => (bool) $validated['is_active'],
            'steps' => $steps->all(),
        ];
    }

    private function normalizeApprovalStep(array $step): array
    {
        $actorType = (string) $step['actor_type'];

        $normalized = [
            'step_order' => (int) $step['step_order'],
            'step_name' => trim((string) $step['step_name']),
            'action_type' => trim((string) $step['action_type']),
            'actor_type' => $actorType,
            'actor_user_id' => $actorType === WorkflowDefinitionStep::ACTOR_TYPE_SPECIFIC_USER ? (int) ($step['actor_user_id'] ?? 0) ?: null : null,
            'actor_position_id' => $actorType === WorkflowDefinitionStep::ACTOR_TYPE_POSITION ? (int) ($step['actor_position_id'] ?? 0) ?: null : null,
            'actor_role_id' => $actorType === WorkflowDefinitionStep::ACTOR_TYPE_SPATIE_ROLE ? (int) ($step['actor_role_id'] ?? 0) ?: null : null,
            'scope_type' => (string) $step['scope_type'],
            'is_final_approval' => (bool) $step['is_final_approval'],
            'is_required' => (bool) $step['is_required'],
            'can_return' => (bool) $step['can_return'],
            'can_reject' => (bool) $step['can_reject'],
        ];

        if ($actorType === WorkflowDefinitionStep::ACTOR_TYPE_RESPONSIBILITY) {
            $responsibilityId = (int) ($step['actor_responsibility_id'] ?? 0);
            if ($responsibilityId <= 0) {
                throw ValidationException::withMessages([
                    'steps' => 'ជំហានប្រភេទ "ការទទួលខុសត្រូវ" ត្រូវការជ្រើសរើសការទទួលខុសត្រូវមួយ។',
                ]);
            }
            // Keep the legacy system_role_id/org_role columns in sync so
            // WorkflowActorResolverService's legacy fallback path (and any
            // other reader of those columns, e.g. LeaveWorkflowNotificationService)
            // sees the exact same shape the existing Workflow Policies screen
            // already produces -- no divergent second representation.
            $normalized['actor_responsibility_id'] = $responsibilityId;
            $normalized['system_role_id'] = $responsibilityId;
            $normalized['org_role'] = (string) (SystemRole::query()->where('id', $responsibilityId)->value('code') ?? '');
        } else {
            $normalized['actor_responsibility_id'] = null;
            $normalized['system_role_id'] = null;
            $normalized['org_role'] = '';
        }

        if ($actorType === WorkflowDefinitionStep::ACTOR_TYPE_SPECIFIC_USER && empty($normalized['actor_user_id'])) {
            throw ValidationException::withMessages(['steps' => 'ជំហានប្រភេទ "អ្នកប្រើប្រាស់ជាក់លាក់" ត្រូវការជ្រើសរើសអ្នកប្រើប្រាស់មួយ។']);
        }
        if ($actorType === WorkflowDefinitionStep::ACTOR_TYPE_POSITION && empty($normalized['actor_position_id'])) {
            throw ValidationException::withMessages(['steps' => 'ជំហានប្រភេទ "តួនាទីការងារ" ត្រូវការជ្រើសរើសតួនាទីមួយ។']);
        }
        if ($actorType === WorkflowDefinitionStep::ACTOR_TYPE_SPATIE_ROLE && empty($normalized['actor_role_id'])) {
            throw ValidationException::withMessages(['steps' => 'ជំហានប្រភេទ "តួនាទីប្រព័ន្ធ" ត្រូវការជ្រើសរើសតួនាទីមួយ។']);
        }

        return $normalized;
    }

    // ------------------------------------------------------------------
    // Per-user approval authority (NEW capability, section 13 "Effective
    // Access" companion view for approvals specifically).
    // ------------------------------------------------------------------

    public function userApprovalAuthority(User $user): JsonResponse
    {
        $rows = collect($this->accessControlService->approvalAuthorityForUser($user))
            ->map(fn (array $row) => array_merge($row, [
                'module_label' => self::APPROVAL_MODULE_LABELS[$row['module_key']] ?? $this->humanizeKey($row['module_key']),
            ]))
            ->values();

        return response()->json(['status' => 'ok', 'data' => $rows]);
    }

    // ------------------------------------------------------------------
    // Delegation (ផ្ទេរសិទ្ធិ). Phase 3D.1: reuses the same permissions
    // already protecting Organization Scope editing (read/update_org_governance)
    // -- no new permission was created for this. See DelegationService for
    // every actual authority/scope/no-chain/no-self-delegation rule; this
    // controller is presentation-only.
    // ------------------------------------------------------------------

    /** Capabilities that are categorically never delegatable, shown to the admin as a fixed reassurance list (section 27 "WILL NOT GRANT"). */
    private const NEVER_DELEGATABLE_LABELS_KM = [
        'រដ្ឋបាលប្រាក់បៀវត្សរ៍ (Payroll Administration)',
        'កែប្រែទិន្នន័យបុគ្គលិក (Personnel Editing)',
        'គ្រប់គ្រងអ្នកប្រើប្រាស់ (User Management)',
        'គ្រប់គ្រងតួនាទី/សិទ្ធិ (Role & Permission Management)',
        'គ្រប់គ្រងវិសាលភាពអង្គភាព (Organization Scope Management)',
        'ការកំណត់រចនាសម្ព័ន្ធប្រព័ន្ធ (System Administration)',
    ];

    public function delegations(Request $request): JsonResponse
    {
        $rows = $this->delegationService->list([
            'delegator_user_id' => $request->query('delegator_user_id'),
            'delegatee_user_id' => $request->query('delegatee_user_id'),
            'authority_module_key' => $request->query('authority_module_key'),
            'department_id' => $request->query('department_id'),
            'state' => $request->query('state', 'active'),
        ])->map(fn (Delegation $delegation) => $this->delegationSummary($delegation));

        return response()->json(['status' => 'ok', 'data' => $rows]);
    }

    private function delegationSummary(Delegation $delegation): array
    {
        return [
            'uuid' => $delegation->uuid,
            'delegator' => $delegation->delegator ? $this->userListItem($delegation->delegator) : null,
            'delegatee' => $delegation->delegatee ? $this->userListItem($delegation->delegatee) : null,
            'authority_module_key' => $delegation->authority_module_key,
            'authority_module_label' => self::APPROVAL_MODULE_LABELS[$delegation->authority_module_key] ?? $this->humanizeKey($delegation->authority_module_key),
            'scope_type' => $delegation->scope_type,
            'scope_department_ids' => $delegation->scopeDepartmentIds(),
            'starts_at' => optional($delegation->starts_at)->toIso8601String(),
            'ends_at' => optional($delegation->ends_at)->toIso8601String(),
            'reason' => $delegation->reason,
            'state' => $delegation->computedState(),
            'revoked_at' => optional($delegation->revoked_at)->toIso8601String(),
        ];
    }

    /** Authorities a candidate delegator could actually delegate right now (section 23: never show what they don't hold). */
    public function delegationAuthorities(User $user): JsonResponse
    {
        $rows = collect($this->delegationService->delegatableAuthoritiesFor($user))
            ->map(fn (array $row) => [
                'module_key' => $row['module_key'],
                'module_label' => self::APPROVAL_MODULE_LABELS[$row['module_key']] ?? $this->humanizeKey($row['module_key']),
            ]);

        return response()->json(['status' => 'ok', 'data' => $rows->values()]);
    }

    /**
     * The org tree, annotated with which units are inside this candidate
     * delegator's own effective scope (section 25: visible but disabled,
     * with a reason, outside their authority -- computed server-side, not
     * assumed client-side, per section 27).
     */
    public function delegationBoundary(User $user): JsonResponse
    {
        $boundaryIds = $this->delegationService->delegatorBoundaryDepartmentIds($user);

        $tree = $this->organizationScopeService->hierarchyPickerOptions()->map(function ($node) use ($boundaryIds) {
            $inScope = $boundaryIds === null || in_array((int) $node['id'], $boundaryIds, true);

            return array_merge($node, [
                'in_scope' => $inScope,
                'disabled_reason' => $inScope ? null : 'នៅក្រៅសិទ្ធិរបស់អ្នកផ្ទេរ (Outside delegator\'s authority)',
            ]);
        });

        return response()->json([
            'status' => 'ok',
            'data' => [
                'unrestricted' => $boundaryIds === null,
                'boundary_department_ids' => $boundaryIds,
                'tree' => $tree->values(),
            ],
        ]);
    }

    public function delegationOptions(): JsonResponse
    {
        return response()->json(['status' => 'ok', 'data' => [
            'scope_type_options' => collect(\App\Services\OrganizationScopeService::presentationOptions())->map(fn ($v) => [
                'value' => $v,
                'label' => \App\Services\OrganizationScopeService::LABELS_KM[$v] ?? $v,
            ])->values(),
            'never_delegatable' => self::NEVER_DELEGATABLE_LABELS_KM,
        ]]);
    }

    public function delegationStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'delegator_user_id' => ['required', 'integer'],
            'delegatee_user_id' => ['required', 'integer'],
            'authority_module_key' => ['required', 'string'],
            'scope_type' => ['required', 'string', Rule::in(\App\Services\OrganizationScopeService::presentationOptions())],
            'department_ids' => ['required', 'array', 'min:1'],
            'department_ids.*' => ['integer'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $dbScopeType = $this->presentationScopeToDb((string) $validated['scope_type']);
        $departmentIds = $validated['scope_type'] === \App\Services\OrganizationScopeService::SELECTED_UNITS
            ? $validated['department_ids']
            : [$validated['department_ids'][0]];

        try {
            $delegation = $this->delegationService->create([
                'delegator_user_id' => (int) $validated['delegator_user_id'],
                'delegatee_user_id' => (int) $validated['delegatee_user_id'],
                'authority_module_key' => (string) $validated['authority_module_key'],
                'scope_type' => $dbScopeType,
                'scope_department_ids' => $departmentIds,
                'starts_at' => $validated['starts_at'],
                'ends_at' => $validated['ends_at'],
                'reason' => $validated['reason'] ?? null,
            ], Auth::id());
        } catch (ValidationException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => collect($exception->errors())->flatten()->first() ?? 'មានបញ្ហាកើតឡើង។',
            ], 422);
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'ការផ្ទេរសិទ្ធិត្រូវបានបង្កើតដោយជោគជ័យ។',
            'data' => $this->delegationSummary($delegation),
        ], 201);
    }

    public function delegationRevoke(Request $request, Delegation $delegation): JsonResponse
    {
        $reason = $request->input('reason');
        $delegation = $this->delegationService->revoke($delegation, Auth::id(), $reason);

        return response()->json([
            'status' => 'ok',
            'message' => 'ការផ្ទេរសិទ្ធិត្រូវបានដកហូតដោយជោគជ័យ។',
            'data' => $this->delegationSummary($delegation),
        ]);
    }

    private function presentationScopeToDb(string $presentation): string
    {
        return match ($presentation) {
            \App\Services\OrganizationScopeService::SELF => UserAssignment::SCOPE_SELF_ONLY,
            \App\Services\OrganizationScopeService::UNIT => UserAssignment::SCOPE_SELF_UNIT_ONLY,
            \App\Services\OrganizationScopeService::UNIT_TREE => UserAssignment::SCOPE_SELF_AND_CHILDREN,
            \App\Services\OrganizationScopeService::ORGANIZATION => UserAssignment::SCOPE_ALL,
            \App\Services\OrganizationScopeService::SELECTED_UNITS => UserAssignment::SCOPE_SELF_ONLY,
            default => UserAssignment::SCOPE_SELF_ONLY,
        };
    }

    // ------------------------------------------------------------------
    // Audit History (Phase 3E). Read-only presentation over
    // AccessControlAuditQueryService, which merges the `activity_log` table
    // (written by AccessControlAuditService -- Role/Permission, User
    // Access, Organization Scope, Approval Authority) with `delegations`
    // rows read directly (Delegation create/revoke already carry
    // created_by/revoked_by/revoked_at natively, so they are not
    // double-logged into activity_log). No new generic audit table.
    // ------------------------------------------------------------------

    public function auditHistory(Request $request): JsonResponse
    {
        $result = $this->auditQueryService->query([
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'actor_id' => $request->query('actor_id'),
            'target_user_id' => $request->query('target_user_id'),
            'category' => $request->query('category'),
            'q' => $request->query('q'),
            'page' => (int) $request->query('page', 1),
            'per_page' => (int) $request->query('per_page', 20),
        ]);

        return response()->json(['status' => 'ok'] + $result);
    }

    public function auditHistoryOptions(): JsonResponse
    {
        return response()->json(['status' => 'ok', 'data' => [
            'category_options' => AccessControlAuditQueryService::categoryOptions(),
        ]]);
    }
}
