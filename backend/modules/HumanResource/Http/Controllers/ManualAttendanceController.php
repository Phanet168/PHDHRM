<?php

namespace Modules\HumanResource\Http\Controllers;

use App\Imports\AttendanceImport;
use App\Imports\ManualAttendanceImport;
use App\Models\AttendanceScanLog;
use App\Models\MobileDeviceRegistration;
use Brian2694\Toastr\Facades\Toastr;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Modules\HumanResource\Entities\Attendance;
use Modules\HumanResource\Entities\AttendanceAdjustment;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\Holiday;
use Modules\HumanResource\Entities\ManualAttendance;
use Modules\HumanResource\Entities\WeekHoliday;
use Modules\HumanResource\Entities\PointSettings;
use Modules\HumanResource\Entities\PointAttendance;
use Modules\HumanResource\Entities\RewardPoint;
use Modules\HumanResource\Entities\UserAssignment;
use Modules\HumanResource\Entities\UserOrgRole;
use Modules\HumanResource\Entities\WorkflowDefinition;
use Modules\HumanResource\Entities\WorkflowDefinitionStep;
use Modules\HumanResource\Services\AttendanceCaptureService;
use Modules\HumanResource\Services\QrAttendanceTokenService;
use Modules\HumanResource\Support\OrgScopeService;
use Modules\HumanResource\Support\OrgUnitRuleService;

class ManualAttendanceController extends Controller
{
    private function scopedEmployeeQuery(OrgScopeService $orgScopeService)
    {
        $query = Employee::query()->where('is_active', 1);

        $accessibleDepartmentIds = $orgScopeService->accessibleDepartmentIds(auth()->user());
        if (is_array($accessibleDepartmentIds) && count($accessibleDepartmentIds) > 0) {
            $departmentIds = array_map('intval', $accessibleDepartmentIds);
            $query->where(function ($inner) use ($departmentIds) {
                $inner->whereIn('department_id', $departmentIds)
                    ->orWhereIn('sub_department_id', $departmentIds);
            });
        }

        return $query;
    }

    private function ensureEmployeeIsAccessible(int $employeeId, OrgScopeService $orgScopeService): Employee
    {
        return $this->scopedEmployeeQuery($orgScopeService)->findOrFail($employeeId);
    }

    private function ensureEmployeesAreAccessible(array $employeeIds, OrgScopeService $orgScopeService): void
    {
        $requestedIds = array_values(array_unique(array_map('intval', $employeeIds)));
        $accessibleIds = $this->scopedEmployeeQuery($orgScopeService)
            ->whereIn('id', $requestedIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        sort($requestedIds);
        sort($accessibleIds);

        if ($requestedIds !== $accessibleIds) {
            abort(403, 'You are not allowed to manage attendance for one or more selected employees.');
        }
    }

    public function __construct()
    {
        $this->middleware(['auth', 'verified', 'permission:attendance_management']);
        $this->middleware('permission:attendance_management', ['only' => ['create', 'store', 'edit', 'update', 'destroy', 'bulk', 'monthlyAttendanceBulkImport', 'monthlyCreate', 'monthlyStore', 'missingAttendance', 'missingAttendanceStore']]);
        $this->middleware('permission:read_attendance', ['only' => ['workflow', 'create', 'store', 'exceptions', 'qrCreate', 'qrGenerate']]);
        $this->middleware('permission:create_attendance', ['only' => ['create', 'store']]);
        $this->middleware('permission:create_monthly_attendance', ['only' => ['monthlyCreate', 'monthlyStore']]);
        $this->middleware('permission:create_missing_attendance', ['only' => ['missingAttendance', 'missingAttendanceStore']]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Renderable
     */
    public function create(OrgScopeService $orgScopeService)
    {
        $employee = $this->scopedEmployeeQuery($orgScopeService)->get();

        return view('humanresource::attendance.create', compact('employee'));
    }

    public function workflow(OrgScopeService $orgScopeService, OrgUnitRuleService $orgUnitRuleService)
    {
        $today = Carbon::today()->toDateString();
        $accessibleDepartmentIds = $orgScopeService->accessibleDepartmentIds(auth()->user());
        $onlineWindowMinutes = max(1, (int) config('humanresource.attendance.device_online_window_minutes', 10));
        $onlineSince = Carbon::now()->subMinutes($onlineWindowMinutes);
        $recentActivityLimit = max(1, min(50, (int) config('humanresource.attendance.device_recent_activity_limit', 12)));

        $employeeQuery = $this->scopedEmployeeQuery($orgScopeService);
        $employeeIds = $employeeQuery->pluck('id')->map(fn ($id) => (int) $id)->all();

        $deviceQuery = MobileDeviceRegistration::query()->with('user.employee');
        if (is_array($accessibleDepartmentIds) && count($accessibleDepartmentIds) > 0) {
            $departmentIds = array_map('intval', $accessibleDepartmentIds);
            $deviceQuery->where(function ($q) use ($departmentIds) {
                // Show devices where employee is in accessible departments
                $q->whereHas('user.employee', function ($q2) use ($departmentIds) {
                    $q2->whereIn('department_id', $departmentIds)
                        ->orWhereIn('sub_department_id', $departmentIds);
                })
                // Also show devices whose user has no linked employee (e.g. admin accounts)
                ->orWhereDoesntHave('user.employee');
            });
        }

        $activeDeviceCount = (clone $deviceQuery)->where('status', 'active')->count();
        $onlineDeviceCount = (clone $deviceQuery)
            ->where('status', 'active')
            ->whereNotNull('last_login_at')
            ->where('last_login_at', '>=', $onlineSince)
            ->count();
        $offlineDeviceCount = max(0, $activeDeviceCount - $onlineDeviceCount);

        $recentDeviceActivity = (clone $deviceQuery)
            ->orderByRaw('COALESCE(last_login_at, created_at) DESC')
            ->limit($recentActivityLimit)
            ->get()
            ->map(function (MobileDeviceRegistration $device) use ($onlineSince) {
                $isOnline = $device->status === 'active'
                    && $device->last_login_at !== null
                    && $device->last_login_at->greaterThanOrEqualTo($onlineSince);
                $device->is_online = $isOnline;
                return $device;
            });

        $employeesInScope = count($employeeIds);
        $todayPresent = empty($employeeIds)
            ? 0
            : Attendance::query()
                ->whereIn('employee_id', $employeeIds)
                ->whereDate('time', $today)
                ->distinct('employee_id')
                ->count('employee_id');
        $todayAbsent = max(0, $employeesInScope - $todayPresent);
        $todayExceptions = empty($employeeIds)
            ? 0
            : Attendance::query()
                ->whereIn('employee_id', $employeeIds)
                ->whereDate('time', $today)
                ->where('exception_flag', true)
                ->distinct('employee_id')
                ->count('employee_id');
        $pendingAdjustments = empty($employeeIds)
            ? 0
            : AttendanceAdjustment::query()
                ->whereIn('employee_id', $employeeIds)
                ->where(function ($query) {
                    $query->where('status', 'pending')
                        ->orWhereNull('status');
                })
                ->count();

        $workflow = [
            'employees_in_scope' => $employeesInScope,
            'today_attendance' => $todayPresent,
            'today_present' => $todayPresent,
            'today_absent' => $todayAbsent,
            'pending_adjustments' => $pendingAdjustments,
            'today_exceptions' => $todayExceptions,
            'missing_today' => empty($employeeIds) ? 0 : $this->scopedEmployeeQuery($orgScopeService)->doesntHave('attendances', 'and', function ($query) use ($today) {
                $query->whereDate('time', $today);
            })->count(),
            'device_pending' => (clone $deviceQuery)->where('status', 'pending')->count(),
            'device_active' => $activeDeviceCount,
            'device_blocked' => (clone $deviceQuery)->where('status', 'blocked')->count(),
            'device_online' => $onlineDeviceCount,
            'device_offline' => $offlineDeviceCount,
            'device_online_window_minutes' => $onlineWindowMinutes,
            'device_recent_activity' => $recentDeviceActivity,
            'qr_units' => is_array($accessibleDepartmentIds)
                ? $orgUnitRuleService->hierarchyOptions()->filter(fn ($option) => in_array((int) data_get($option, 'id', 0), array_map('intval', $accessibleDepartmentIds), true))->count()
                : $orgUnitRuleService->hierarchyOptions()->count(),
        ];

        $requestTypeOptions = $this->attendanceRequestTypeOptions();
        $orgUnitOptions = $orgUnitRuleService->hierarchyOptions();
        $orgUnitTree = $orgUnitRuleService->hierarchyTree();
        if (is_array($accessibleDepartmentIds) && count($accessibleDepartmentIds) > 0) {
            $allowedDepartmentIds = collect($accessibleDepartmentIds)
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->flatMap(fn (int $id) => $orgUnitRuleService->branchIdsIncludingSelf($id))
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();
            $orgUnitOptions = $orgUnitOptions
                ->filter(fn ($option) => in_array((int) data_get($option, 'id', 0), $allowedDepartmentIds, true))
                ->values();
            $orgUnitTree = $this->filterOrgUnitTree($orgUnitTree, $allowedDepartmentIds);
        }

        $departmentOptionMap = $orgUnitOptions
            ->mapWithKeys(function ($option) {
                $id = (int) data_get($option, 'id', 0);
                if ($id <= 0) {
                    return [];
                }

                $label = trim((string) (data_get($option, 'path') ?: data_get($option, 'name') ?: ('#' . $id)));
                return [$id => $label];
            })
            ->all();

        $approverUserOptions = $this->attendanceApproverUserOptions($accessibleDepartmentIds, $orgUnitRuleService);

        $approvalPolicies = WorkflowDefinition::query()
            ->with(['steps.systemRole', 'steps.actorUser:id,full_name,email'])
            ->where('module_key', 'attendance')
            ->whereIn('request_type_key', array_keys($requestTypeOptions))
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->map(function (WorkflowDefinition $definition) use ($requestTypeOptions, $departmentOptionMap) {
                $condition = (array) ($definition->condition_json ?? []);
                $steps = $definition->steps->sortBy('step_order')->values();
                $approverSteps = $steps->map(function (WorkflowDefinitionStep $step, int $index) use ($steps) {
                    $isFinal = (bool) $step->is_final_approval || $index === ($steps->count() - 1);

                    return [
                        'user_id' => !empty($step->actor_user_id) ? (int) $step->actor_user_id : null,
                        'label' => $this->workflowStepActorLabel($step),
                        'is_final' => $isFinal,
                        'stage_label' => $isFinal
                            ? localize('final_level', 'ចុងក្រោយ')
                            : ('ជាន់ទី ' . ($index + 1)),
                        'stage_hint' => $isFinal
                            ? localize('final_decision_stage', 'អនុម័ត និងសម្រេចចុងក្រោយ')
                            : localize('comment_recommend_stage', 'ផ្តល់មតិ និងបញ្ជូនបន្ត'),
                    ];
                })->values();
                $firstStep = $approverSteps->get(0);
                $middleStep = $approverSteps->get(1);
                $finalStep = $approverSteps->last();
                $departmentIds = collect((array) ($condition['department_ids'] ?? []))
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->unique()
                    ->values()
                    ->all();
                $departmentLabels = collect($departmentIds)
                    ->map(fn (int $id) => (string) ($departmentOptionMap[$id] ?? ('#' . $id)))
                    ->values()
                    ->all();

                $minDays = isset($condition['min_days']) && $condition['min_days'] !== '' ? (float) $condition['min_days'] : null;
                $maxDays = isset($condition['max_days']) && $condition['max_days'] !== '' ? (float) $condition['max_days'] : null;
                $dayCondition = localize('all', 'ទាំងអស់');
                if ($minDays !== null && $maxDays !== null) {
                    $dayCondition = rtrim(rtrim((string) $minDays, '0'), '.') . ' - ' . rtrim(rtrim((string) $maxDays, '0'), '.');
                } elseif ($minDays !== null) {
                    $dayCondition = '>= ' . rtrim(rtrim((string) $minDays, '0'), '.');
                } elseif ($maxDays !== null) {
                    $dayCondition = '<= ' . rtrim(rtrim((string) $maxDays, '0'), '.');
                }

                return [
                    'id' => (int) $definition->id,
                    'name' => (string) $definition->name,
                    'description' => (string) ($definition->description ?? ''),
                    'request_type_key' => (string) $definition->request_type_key,
                    'request_type_label' => (string) ($requestTypeOptions[$definition->request_type_key] ?? $definition->request_type_key),
                    'priority' => (int) $definition->priority,
                    'is_active' => (bool) $definition->is_active,
                    'day_condition' => $dayCondition,
                    'min_days' => $minDays,
                    'max_days' => $maxDays,
                    'department_ids' => $departmentIds,
                    'department_labels' => $departmentLabels,
                    'first_approver_user_id' => $firstStep['user_id'] ?? null,
                    'first_approver_label' => $firstStep['label'] ?? '',
                    'middle_approver_user_id' => $middleStep['user_id'] ?? null,
                    'middle_approver_label' => $middleStep['label'] ?? '',
                    'final_approver_user_id' => $finalStep['user_id'] ?? null,
                    'final_approver_label' => $finalStep['label'] ?? '',
                    'approver_steps' => $approverSteps->all(),
                    'steps_count' => $steps->count(),
                ];
            })
            ->values();

        return view('humanresource::attendance.workflow', compact(
            'workflow',
            'today',
            'approvalPolicies',
            'requestTypeOptions',
            'approverUserOptions',
            'orgUnitOptions',
            'orgUnitTree'
        ));
    }

    private function filterOrgUnitTree(array $nodes, array $allowedDepartmentIds): array
    {
        $allowedMap = array_fill_keys(array_map('intval', $allowedDepartmentIds), true);
        $filtered = [];

        foreach ($nodes as $node) {
            $children = $this->filterOrgUnitTree((array) ($node['children'] ?? []), $allowedDepartmentIds);
            $nodeId = (int) ($node['id'] ?? 0);
            $isAllowed = isset($allowedMap[$nodeId]);

            if (!$isAllowed && empty($children)) {
                continue;
            }

            $node['children'] = $children;
            $node['is_selectable'] = $isAllowed;
            $filtered[] = $node;
        }

        return $filtered;
    }

    public function storeWorkflowPolicy(Request $request, OrgScopeService $orgScopeService, OrgUnitRuleService $orgUnitRuleService)
    {
        $validated = $this->validateWorkflowPolicyPayload($request, $orgScopeService, $orgUnitRuleService);
        $actorId = (int) (auth()->id() ?? 0);

        DB::transaction(function () use ($validated, $actorId): void {
            $definition = WorkflowDefinition::query()->create([
                'module_key' => 'attendance',
                'request_type_key' => (string) $validated['request_type_key'],
                'name' => (string) $validated['name'],
                'description' => (string) ($validated['description'] ?? ''),
                'condition_json' => $this->buildWorkflowConditionJson($validated),
                'priority' => (int) $validated['priority'],
                'is_active' => (bool) ($validated['is_active'] ?? false),
                'created_by' => $actorId > 0 ? $actorId : null,
                'updated_by' => $actorId > 0 ? $actorId : null,
            ]);

            $this->replaceAttendanceWorkflowSteps($definition, $validated, $actorId);
        });

        return redirect()->route('attendances.workflow')
            ->with('success', localize('workflow_policy_created', 'បានបង្កើតលក្ខខណ្ឌអនុម័តវត្តមានរួចរាល់'));
    }

    public function updateWorkflowPolicy(Request $request, int $policyId, OrgScopeService $orgScopeService, OrgUnitRuleService $orgUnitRuleService)
    {
        $definition = WorkflowDefinition::query()
            ->where('module_key', 'attendance')
            ->findOrFail($policyId);

        $validated = $this->validateWorkflowPolicyPayload($request, $orgScopeService, $orgUnitRuleService);
        $actorId = (int) (auth()->id() ?? 0);

        DB::transaction(function () use ($definition, $validated, $actorId): void {
            $definition->update([
                'request_type_key' => (string) $validated['request_type_key'],
                'name' => (string) $validated['name'],
                'description' => (string) ($validated['description'] ?? ''),
                'condition_json' => $this->buildWorkflowConditionJson($validated),
                'priority' => (int) $validated['priority'],
                'is_active' => (bool) ($validated['is_active'] ?? false),
                'updated_by' => $actorId > 0 ? $actorId : null,
            ]);

            $this->replaceAttendanceWorkflowSteps($definition, $validated, $actorId);
        });

        return redirect()->route('attendances.workflow')
            ->with('success', localize('workflow_policy_updated', 'បានកែសម្រួលលក្ខខណ្ឌអនុម័តវត្តមានរួចរាល់'));
    }

    public function destroyWorkflowPolicy(int $policyId)
    {
        $definition = WorkflowDefinition::query()
            ->where('module_key', 'attendance')
            ->findOrFail($policyId);

        $actorId = (int) (auth()->id() ?? 0);

        DB::transaction(function () use ($definition, $actorId): void {
            $definition->steps()->withTrashed()->forceDelete();
            $definition->update([
                'updated_by' => $actorId > 0 ? $actorId : null,
                'deleted_by' => $actorId > 0 ? $actorId : null,
            ]);
            $definition->delete();
        });

        return redirect()->route('attendances.workflow')
            ->with('success', localize('workflow_policy_deleted', 'បានលុបលក្ខខណ្ឌអនុម័តវត្តមានរួចរាល់'));
    }

    private function validateWorkflowPolicyPayload(
        Request $request,
        OrgScopeService $orgScopeService,
        OrgUnitRuleService $orgUnitRuleService
    ): array {
        $requestTypeKeys = array_keys($this->attendanceRequestTypeOptions());
        $validated = $request->validate([
            'request_type_key' => ['required', 'string', Rule::in($requestTypeKeys)],
            'name' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:500'],
            'department_ids' => ['required', 'array', 'min:1'],
            'department_ids.*' => ['integer', 'exists:departments,id'],
            'min_days' => ['nullable', 'numeric', 'min:0'],
            'max_days' => ['nullable', 'numeric', 'gte:min_days'],
            'first_approver_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'middle_approver_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'final_approver_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['priority'] = !empty($validated['priority']) ? (int) $validated['priority'] : 100;

        $validated['department_ids'] = $this->normalizeDepartmentIds((array) ($validated['department_ids'] ?? []));
        if (empty($validated['department_ids'])) {
            throw ValidationException::withMessages([
                'department_ids' => localize('please_select_org_unit', 'Please select at least one organization unit.'),
            ]);
        }

        $this->ensureSelectedDepartmentsInScope($validated['department_ids'], $orgScopeService);

        $selectedApprovers = collect([
            !empty($validated['first_approver_user_id']) ? (int) $validated['first_approver_user_id'] : null,
            !empty($validated['middle_approver_user_id']) ? (int) $validated['middle_approver_user_id'] : null,
            !empty($validated['final_approver_user_id']) ? (int) $validated['final_approver_user_id'] : null,
        ])->filter(fn ($userId) => !empty($userId))->values();

        if ($selectedApprovers->isEmpty()) {
            throw ValidationException::withMessages([
                'first_approver_user_id' => localize(
                    'please_select_at_least_one_approver',
                    'Please select at least one approver.'
                ),
            ]);
        }

        if ($selectedApprovers->count() !== $selectedApprovers->unique()->count()) {
            throw ValidationException::withMessages([
                'middle_approver_user_id' => localize(
                    'approver_must_be_different',
                    'Each selected approver must be different.'
                ),
            ]);
        }

        $approverSteps = [];
        foreach ($selectedApprovers->values() as $index => $userId) {
            $profile = $this->resolveApproverProfileForDepartments(
                (int) $userId,
                $validated['department_ids'],
                $orgUnitRuleService
            );

            if ($profile === null) {
                throw ValidationException::withMessages([
                    'first_approver_user_id' => localize(
                        'approver_not_scoped_manager',
                        'Selected approver must be an active manager for selected units.'
                    ),
                ]);
            }

            $approverSteps[] = [
                'user_id' => (int) $userId,
                'system_role_id' => !empty($profile['system_role_id']) ? (int) $profile['system_role_id'] : null,
                'role_code' => (string) ($profile['role_code'] ?? UserOrgRole::ROLE_MANAGER),
                'level' => (int) ($profile['level'] ?? 999),
                'is_final' => $index === ($selectedApprovers->count() - 1),
            ];
        }

        for ($index = 1; $index < count($approverSteps); $index++) {
            if ((int) $approverSteps[$index]['level'] >= (int) $approverSteps[$index - 1]['level']) {
                throw ValidationException::withMessages([
                    'middle_approver_user_id' => localize(
                        'approval_level_sequence_invalid',
                        'Next approver must be at a higher management level than the previous approver.'
                    ),
                ]);
            }
        }

        $normalizedApprovers = array_pad(array_column($approverSteps, 'user_id'), 3, null);
        $validated['first_approver_user_id'] = $normalizedApprovers[0];
        $validated['middle_approver_user_id'] = $normalizedApprovers[1];
        $validated['final_approver_user_id'] = $normalizedApprovers[2];
        $validated['first_approver_system_role_id'] = $approverSteps[0]['system_role_id'] ?? null;
        $validated['first_approver_role_code'] = $approverSteps[0]['role_code'] ?? null;
        $validated['middle_approver_system_role_id'] = $approverSteps[1]['system_role_id'] ?? null;
        $validated['middle_approver_role_code'] = $approverSteps[1]['role_code'] ?? null;
        $validated['final_approver_system_role_id'] = $approverSteps[2]['system_role_id'] ?? null;
        $validated['final_approver_role_code'] = $approverSteps[2]['role_code'] ?? null;
        $validated['approver_steps'] = $approverSteps;

        return $validated;
    }

    private function buildWorkflowConditionJson(array $validated): array
    {
        $condition = [];

        $departmentIds = $this->normalizeDepartmentIds((array) ($validated['department_ids'] ?? []));
        if (!empty($departmentIds)) {
            $condition['department_ids'] = $departmentIds;
        }

        if (array_key_exists('min_days', $validated) && $validated['min_days'] !== null && $validated['min_days'] !== '') {
            $condition['min_days'] = (float) $validated['min_days'];
        }

        if (array_key_exists('max_days', $validated) && $validated['max_days'] !== null && $validated['max_days'] !== '') {
            $condition['max_days'] = (float) $validated['max_days'];
        }

        return $condition;
    }

    private function replaceAttendanceWorkflowSteps(WorkflowDefinition $definition, array $validated, int $actorId = 0): void
    {
        $definition->steps()->withTrashed()->forceDelete();

        $configuredApprovers = collect((array) ($validated['approver_steps'] ?? []))
            ->filter(fn ($step) => !empty($step['user_id']))
            ->values();

        if ($configuredApprovers->isNotEmpty()) {
            $steps = [];
            foreach ($configuredApprovers as $index => $approverStep) {
                $isFinal = $index === ($configuredApprovers->count() - 1);

                $steps[] = [
                    'step_order' => $index + 1,
                    'step_key' => $isFinal ? 'attendance_final_approval' : ('attendance_recommend_' . ($index + 1)),
                    'step_name' => $isFinal
                        ? localize('attendance_final_approval_step_name', 'អនុម័តចុងក្រោយ')
                        : localize('attendance_recommend_step_name', 'ផ្តល់មតិ និងបញ្ជូនបន្ត'),
                    'action_type' => $isFinal ? 'approve' : 'recommend',
                    'actor_type' => WorkflowDefinitionStep::ACTOR_TYPE_SPECIFIC_USER,
                    'actor_user_id' => (int) $approverStep['user_id'],
                    'org_role' => (string) ($approverStep['role_code'] ?? ($isFinal ? UserOrgRole::ROLE_HEAD : UserOrgRole::ROLE_MANAGER)),
                    'system_role_id' => !empty($approverStep['system_role_id']) ? (int) $approverStep['system_role_id'] : null,
                    'scope_type' => UserOrgRole::SCOPE_SELF_ONLY,
                    'is_final_approval' => $isFinal ? 1 : 0,
                    'is_required' => 1,
                    'can_return' => 1,
                    'can_reject' => 1,
                    'created_by' => $actorId > 0 ? $actorId : null,
                    'updated_by' => $actorId > 0 ? $actorId : null,
                ];
            }

            $definition->steps()->createMany($steps);
            return;
        }

        $steps = [
            [
                'step_order' => 1,
                'step_key' => 'attendance_review',
                'step_name' => localize('attendance_review_step_name', 'ពិនិត្យសំណើ'),
                'action_type' => 'review',
                'actor_type' => WorkflowDefinitionStep::ACTOR_TYPE_SPECIFIC_USER,
                'actor_user_id' => (int) $validated['first_approver_user_id'],
                'org_role' => (string) ($validated['first_approver_role_code'] ?? UserOrgRole::ROLE_MANAGER),
                'system_role_id' => !empty($validated['first_approver_system_role_id']) ? (int) $validated['first_approver_system_role_id'] : null,
                'scope_type' => UserOrgRole::SCOPE_SELF_ONLY,
                'is_final_approval' => 0,
                'is_required' => 1,
                'can_return' => 1,
                'can_reject' => 1,
                'created_by' => $actorId > 0 ? $actorId : null,
                'updated_by' => $actorId > 0 ? $actorId : null,
            ],
        ];

        $stepOrder = 2;
        if (!empty($validated['middle_approver_user_id'])) {
            $steps[] = [
                'step_order' => $stepOrder,
                'step_key' => 'attendance_middle_approval',
                'step_name' => localize('attendance_middle_approval_step_name', 'អនុម័តជាន់ទី២'),
                'action_type' => 'approve',
                'actor_type' => WorkflowDefinitionStep::ACTOR_TYPE_SPECIFIC_USER,
                'actor_user_id' => (int) $validated['middle_approver_user_id'],
                'org_role' => (string) ($validated['middle_approver_role_code'] ?? UserOrgRole::ROLE_MANAGER),
                'system_role_id' => !empty($validated['middle_approver_system_role_id']) ? (int) $validated['middle_approver_system_role_id'] : null,
                'scope_type' => UserOrgRole::SCOPE_SELF_ONLY,
                'is_final_approval' => 0,
                'is_required' => 1,
                'can_return' => 1,
                'can_reject' => 1,
                'created_by' => $actorId > 0 ? $actorId : null,
                'updated_by' => $actorId > 0 ? $actorId : null,
            ];
            $stepOrder++;
        }

        $steps[] = [
                'step_order' => $stepOrder,
                'step_key' => 'attendance_final_approval',
                'step_name' => localize('attendance_final_approval_step_name', 'អនុម័តចុងក្រោយ'),
                'action_type' => 'approve',
                'actor_type' => WorkflowDefinitionStep::ACTOR_TYPE_SPECIFIC_USER,
                'actor_user_id' => (int) $validated['final_approver_user_id'],
                'org_role' => (string) ($validated['final_approver_role_code'] ?? UserOrgRole::ROLE_HEAD),
                'system_role_id' => !empty($validated['final_approver_system_role_id']) ? (int) $validated['final_approver_system_role_id'] : null,
                'scope_type' => UserOrgRole::SCOPE_SELF_ONLY,
                'is_final_approval' => 1,
                'is_required' => 1,
                'can_return' => 1,
                'can_reject' => 1,
                'created_by' => $actorId > 0 ? $actorId : null,
                'updated_by' => $actorId > 0 ? $actorId : null,
            ];

        $definition->steps()->createMany($steps);
    }

    private function workflowStepActorLabel(WorkflowDefinitionStep $step): string
    {
        $userLabel = trim((string) ($step->actorUser?->full_name ?? $step->actorUser?->email ?? ''));
        if ($userLabel !== '') {
            return $userLabel;
        }

        return (string) ($step->systemRole?->name_km ?: $step->systemRole?->name ?: $step->getEffectiveRoleCode() ?: '-');
    }

    private function attendanceApproverUserOptions(?array $accessibleDepartmentIds, OrgUnitRuleService $orgUnitRuleService)
    {
        $query = UserAssignment::query()
            ->withoutGlobalScope('sortByLatest')
            ->effective()
            ->with([
                'user:id,full_name,email',
                'user.employee:id,user_id,employee_id,first_name,last_name,is_active',
                'department:id,department_name',
                'responsibility:id,code,name,name_km,level,can_approve,is_active',
            ])
            ->whereHas('user')
            ->whereHas('responsibility', function ($q) {
                $q->where('is_active', true)
                    ->where('can_approve', true)
                    ->where('level', '>', 0);
            });

        if (is_array($accessibleDepartmentIds) && count($accessibleDepartmentIds) > 0) {
            $query->whereIn('department_id', array_map('intval', $accessibleDepartmentIds));
        }

        $assignments = $query->get();

        return $assignments
            ->groupBy(fn (UserAssignment $assignment) => (int) $assignment->user_id)
            ->map(function ($rows, $userId) {
                $uid = (int) $userId;
                if ($uid <= 0) {
                    return null;
                }

                /** @var \Illuminate\Support\Collection<int, UserAssignment> $rows */
                $bestAssignment = $rows
                    ->sortBy(fn (UserAssignment $item) => (int) ($item->responsibility?->level ?? 999))
                    ->first();
                if (!$bestAssignment || !$bestAssignment->user) {
                    return null;
                }

                $employee = $bestAssignment->user->employee;
                $employeeName = trim(((string) ($employee?->first_name ?? '')) . ' ' . ((string) ($employee?->last_name ?? '')));
                if ($employeeName === '') {
                    $employeeName = trim(((string) ($employee?->last_name ?? '')) . ' ' . ((string) ($employee?->first_name ?? '')));
                }

                $userName = trim((string) ($bestAssignment->user->full_name ?? ''));
                $name = $employeeName !== '' ? $employeeName : $userName;
                if ($name === '') {
                    $name = trim((string) ($bestAssignment->user->email ?? ('User #' . $uid)));
                }

                $roleLabel = trim((string) ($bestAssignment->responsibility?->name_km ?: $bestAssignment->responsibility?->name ?: ''));
                $employeeCode = trim((string) ($employee?->employee_id ?? ''));
                $email = trim((string) ($bestAssignment->user->email ?? ''));
                $unitNames = $rows
                    ->map(fn (UserAssignment $item) => trim((string) ($item->department?->department_name ?? '')))
                    ->filter()
                    ->unique()
                    ->values();

                $labelParts = [$name];
                if ($employeeCode !== '') {
                    $labelParts[] = $employeeCode;
                }
                if ($roleLabel !== '') {
                    $labelParts[] = $roleLabel;
                }

                $searchParts = [
                    $name,
                    $employeeName,
                    trim(((string) ($employee?->last_name ?? '')) . ' ' . ((string) ($employee?->first_name ?? ''))),
                    $userName,
                    $employeeCode,
                    $roleLabel,
                    $email,
                    $unitNames->implode(' '),
                ];
                $searchText = implode(' ', array_values(array_filter(array_unique(array_map(
                    fn ($part) => trim((string) $part),
                    $searchParts
                )))));

                return [
                    'id' => $uid,
                    'label' => implode(' • ', array_filter($labelParts, fn ($part) => trim((string) $part) !== '')),
                    'search_text' => $searchText,
                    'sort_key' => mb_strtolower($name . ' ' . $employeeCode),
                ];
            })
            ->filter()
            ->sortBy(fn (array $item) => (string) ($item['sort_key'] ?? $item['label'] ?? ''))
            ->values();
    }

    private function normalizeDepartmentIds(array $departmentIds): array
    {
        return collect($departmentIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function ensureSelectedDepartmentsInScope(array $departmentIds, OrgScopeService $orgScopeService): void
    {
        $normalized = $this->normalizeDepartmentIds($departmentIds);
        if (empty($normalized)) {
            throw ValidationException::withMessages([
                'department_ids' => localize('please_select_org_unit', 'Please select at least one organization unit.'),
            ]);
        }

        $accessibleDepartmentIds = $orgScopeService->accessibleDepartmentIds(auth()->user());
        if (!is_array($accessibleDepartmentIds)) {
            return;
        }
        if (count($accessibleDepartmentIds) === 0) {
            throw ValidationException::withMessages([
                'department_ids' => localize('you_do_not_have_org_scope', 'You do not have organization scope to configure workflow.'),
            ]);
        }

        $allowed = $this->normalizeDepartmentIds($accessibleDepartmentIds);
        foreach ($normalized as $departmentId) {
            if (!in_array($departmentId, $allowed, true)) {
                throw ValidationException::withMessages([
                    'department_ids' => localize(
                        'you_are_not_allowed_to_manage_selected_units',
                        'You are not allowed to configure workflow for one or more selected units.'
                    ),
                ]);
            }
        }
    }

    private function resolveApproverProfileForDepartments(
        int $userId,
        array $departmentIds,
        OrgUnitRuleService $orgUnitRuleService
    ): ?array {
        if ($userId <= 0) {
            return null;
        }

        $targetDepartmentIds = $this->normalizeDepartmentIds($departmentIds);
        if (empty($targetDepartmentIds)) {
            return null;
        }

        $assignments = UserAssignment::query()
            ->withoutGlobalScope('sortByLatest')
            ->effective()
            ->where('user_id', $userId)
            ->with(['responsibility:id,code,level,can_approve,is_active'])
            ->get()
            ->filter(function (UserAssignment $assignment) {
                $role = $assignment->responsibility;
                return $role
                    && (bool) $role->is_active
                    && (bool) $role->can_approve
                    && (int) ($role->level ?? 0) > 0;
            })
            ->values();

        if ($assignments->isEmpty()) {
            return null;
        }

        $matchedAssignments = collect();
        foreach ($targetDepartmentIds as $departmentId) {
            $matched = $assignments->first(
                fn (UserAssignment $assignment) => $this->assignmentCoversDepartment(
                    $assignment,
                    $departmentId,
                    $orgUnitRuleService
                )
            );

            if (!$matched) {
                return null;
            }

            $matchedAssignments->push($matched);
        }

        $bestAssignment = $matchedAssignments
            ->sortBy(fn (UserAssignment $assignment) => (int) ($assignment->responsibility?->level ?? 999))
            ->first();
        if (!$bestAssignment || !$bestAssignment->responsibility) {
            return null;
        }

        return [
            'system_role_id' => (int) ($bestAssignment->responsibility_id ?? 0),
            'role_code' => (string) ($bestAssignment->responsibility->code ?? ''),
            'level' => (int) ($bestAssignment->responsibility->level ?? 0),
        ];
    }

    private function assignmentCoversDepartment(
        UserAssignment $assignment,
        int $departmentId,
        OrgUnitRuleService $orgUnitRuleService
    ): bool {
        $targetDepartmentId = (int) $departmentId;
        if ($targetDepartmentId <= 0) {
            return false;
        }

        $assignmentDepartmentId = (int) ($assignment->department_id ?? 0);
        if ($assignmentDepartmentId <= 0) {
            return false;
        }

        $scopeType = trim((string) ($assignment->scope_type ?: UserAssignment::SCOPE_SELF_AND_CHILDREN));
        return match ($scopeType) {
            UserAssignment::SCOPE_SELF_ONLY => $assignmentDepartmentId === $targetDepartmentId,
            UserAssignment::SCOPE_SELF_UNIT_ONLY => in_array(
                $targetDepartmentId,
                $this->siblingSameTypeDepartmentIds($assignmentDepartmentId),
                true
            ),
            UserAssignment::SCOPE_ALL => true,
            default => in_array(
                $targetDepartmentId,
                $orgUnitRuleService->branchIdsIncludingSelf($assignmentDepartmentId),
                true
            ),
        };
    }

    private function siblingSameTypeDepartmentIds(int $departmentId): array
    {
        $department = Department::query()
            ->withoutGlobalScopes()
            ->find($departmentId, ['id', 'parent_id', 'unit_type_id']);

        if (!$department || !$department->parent_id || !$department->unit_type_id) {
            return [$departmentId];
        }

        return Department::query()
            ->withoutGlobalScopes()
            ->where('parent_id', (int) $department->parent_id)
            ->where('unit_type_id', (int) $department->unit_type_id)
            ->where('is_active', 1)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function attendanceRequestTypeOptions(): array
    {
        return [
            'attendance_adjustment' => localize('attendance_adjustment', 'កែប្រែវត្តមាន'),
            'attendance_exception' => localize('attendance_exception', 'ករណីខុសវត្តមាន'),
        ];
    }

    /**
     * QR attendance page — static printed workplace QR.
     */
    public function qrCreate(Request $request, OrgUnitRuleService $orgUnitRuleService, OrgScopeService $orgScopeService)
    {
        $orgUnitOptions = $orgUnitRuleService->hierarchyOptions();
        $scopedDepartmentIds = $orgScopeService->accessibleDepartmentIds(auth()->user());

        if (is_array($scopedDepartmentIds) && count($scopedDepartmentIds) > 0) {
            $allowedDepartmentIds = array_map('intval', $scopedDepartmentIds);
            $orgUnitOptions = $orgUnitOptions
                ->filter(function ($option) use ($allowedDepartmentIds) {
                    return in_array((int) data_get($option, 'id', 0), $allowedDepartmentIds, true);
                })
                ->values();
        }

        $selectedWorkplaceId = $request->integer('workplace_id', 0) ?: null;
        $selectedWorkplace   = null;
        $scanLogs            = collect();
        $logDate             = $request->input('log_date', date('Y-m-d'));

        if ($selectedWorkplaceId) {
            $selectedWorkplace = Department::withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->find($selectedWorkplaceId);

            $scanLogs = AttendanceScanLog::with(['employee', 'workplace'])
                ->where('workplace_id', $selectedWorkplaceId)
                ->whereDate('created_at', $logDate)
                ->orderByDesc('created_at')
                ->limit(100)
                ->get();
        }

        return view('humanresource::attendance.qr', [
            'orgUnitOptions'      => $orgUnitOptions,
            'generated'           => null,
            'selectedWorkplaceId' => $selectedWorkplaceId,
            'selectedWorkplace'   => $selectedWorkplace,
            'scanLogs'            => $scanLogs,
            'logDate'             => $logDate,
        ]);
    }

    /**
     * Generate a permanent signed QR token for a static printed workplace poster.
     */
    public function qrGenerate(Request $request, OrgUnitRuleService $orgUnitRuleService, OrgScopeService $orgScopeService)
    {
        $validated = $request->validate([
            'workplace_id' => 'required|integer|exists:departments,id',
        ]);

        $scopedDepartmentIds = $orgScopeService->accessibleDepartmentIds(auth()->user());

        if (is_array($scopedDepartmentIds) && count($scopedDepartmentIds) > 0) {
            $allowedDepartmentIds = array_map('intval', $scopedDepartmentIds);
            if (!in_array((int) $validated['workplace_id'], $allowedDepartmentIds, true)) {
                return back()
                    ->withErrors([
                        'workplace_id' => localize('you_are_not_allowed_to_generate_qr_for_this_unit', 'You are not allowed to generate QR for this unit'),
                    ])
                    ->withInput();
            }
        }


        // Permanent QR: expires in 10 years (not a time-limited event QR).
        $issuedAt  = Carbon::now();
        $expiresAt = $issuedAt->copy()->addYears(10);

        $token = QrAttendanceTokenService::generate([
            'wid' => (int) $validated['workplace_id'],
            'uid' => (int) auth()->id(),
            'iat' => $issuedAt->timestamp,
            'exp' => $expiresAt->timestamp,
        ]);

        $workplace = Department::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->find((int) $validated['workplace_id']);

        // Payload encoded in QR: only type + token + workplace_id (no expiry shown to user).
        $qrPayload     = [
            'type'         => 'attendance_qr',
            'qr_token'     => $token,
            'workplace_id' => (int) $validated['workplace_id'],
        ];
        $qrPayloadJson = json_encode($qrPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $generated = [
            'token'             => $token,
            'payload_json'      => $qrPayloadJson,
            'workplace_name'    => $workplace?->department_name,
            'generated_at'      => $issuedAt,
            'qr_image_url'      => 'https://api.qrserver.com/v1/create-qr-code/?size=320x320&margin=0&data=' . rawurlencode((string) $qrPayloadJson),
            'qr_image_url_print'=> 'https://api.qrserver.com/v1/create-qr-code/?size=800x800&margin=10&data=' . rawurlencode((string) $qrPayloadJson),
        ];

        $orgUnitOptions = $orgUnitRuleService->hierarchyOptions();

        if (is_array($scopedDepartmentIds) && count($scopedDepartmentIds) > 0) {
            $allowedDepartmentIds = array_map('intval', $scopedDepartmentIds);
            $orgUnitOptions = $orgUnitOptions
                ->filter(function ($option) use ($allowedDepartmentIds) {
                    return in_array((int) data_get($option, 'id', 0), $allowedDepartmentIds, true);
                })
                ->values();
        }

        $logDate  = $request->input('log_date', date('Y-m-d'));
        $scanLogs = AttendanceScanLog::with(['employee', 'workplace'])
            ->where('workplace_id', (int) $validated['workplace_id'])
            ->whereDate('created_at', $logDate)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return view('humanresource::attendance.qr', [
            'orgUnitOptions'      => $orgUnitOptions,
            'generated'           => $generated,
            'selectedWorkplaceId' => (int) $validated['workplace_id'],
            'selectedWorkplace'   => $workplace,
            'scanLogs'            => $scanLogs,
            'logDate'             => $logDate,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, OrgScopeService $orgScopeService)
    {
        $validated = $request->validate([
            'employee_id' => 'required',
            'time' => 'required',
        ]);

        $employee = $this->ensureEmployeeIsAccessible((int) $validated['employee_id'], $orgScopeService);

        $attendance_history = [
            'uid'    => $employee->id,
            'state'  => 1,
            'id'     => 0,
            'time'   => $request->input('time'),
        ];

        $neTime = Carbon::parse($request->time)->format('Y-m-d H:i:s');
        $validated['time'] = $neTime;

        // attendance (dedupe + exception sync)
        $resp = AttendanceCaptureService::capture([
            'employee_id' => (int) $employee->id,
            'time' => $validated['time'],
            'attendance_source' => 'manual',
        ]);
        if ($resp) {
            $resp_attend = $this->insert_attendance_point($attendance_history);

            return redirect()->route('attendances.create')->with('success', localize('data_save'));
        } else {
            return redirect()->route('attendances.create')->with('error', localize('error'));
        }
    }

    /**
     * Insert attendance point when gets call from Attendance module for employee
     * this will both calculate attendance point on add
     * update and delete of attendance
     */
    public function insert_attendance_point($data = array())
    {
        /**
         * Getting from point settings
         */
        $point_settings = $this->get_last_record();
        if ($point_settings == null) {
            return false;
        }
        $attendence_start = strtotime($point_settings->attendance_start);
        $attendence_end = strtotime($point_settings->attendance_end);
        $attendence_point = $point_settings->attendance_point;

        /**
         * Getting Year,Month,day and time from Employee attendance in_time of Attendance Form
         **/
        $dt = Carbon::parse($data['time']);
        $date = $dt->format('Y-m-d');
        $date_y = $dt->year;
        $date_m = $dt->month;
        $date_d = $dt->day;
        $time_to_insert = $dt->format('H:i');
        $time = $dt->format('H:i:s');

        // Checking if attendance point already exists in point_attendance table 
        $point_attendence_rec = DB::table("point_attendances")
            ->where('employee_id', $data['uid'])
            ->whereRaw("YEAR(create_date) = ?", [$date_y])
            ->whereRaw("MONTH(create_date) = ?", [$date_m])
            ->whereRaw("DAY(create_date) = ?", [$date_d])
            ->first();

        $respo_s = true;

        if (!$point_attendence_rec) {

            //point attendence data to insert in point_attendence table
            $atten_data['employee_id'] = $data['uid'];
            $atten_data['in_time'] = $time_to_insert;
            $atten_data['create_date'] = $date;
            $atten_data['point'] = 0;

            $respo_s = PointAttendance::create($atten_data);
        } else {

            $worked_hour = $this->employee_worked_hour_today($data['uid'], $data['time']);
            $emp_in_time = $this->employee_attn_in_time($data);
            $attn_in_time = strtotime($emp_in_time);

            $point_attendence_data['in_time'] = $emp_in_time;

            //Checking if attendence punch time is occurred more than once
            $attn_history = $this->employee_attn_history($data);

            if ($attn_history >= 2) {

                //Check worked hour is more than 8 or equal 8 hours
                if ($worked_hour >= 8 && (int)$attn_in_time <= (int)$attendence_end) {

                    //Reward point data to insert in point_reward table
                    $point_reward_data['employee_id'] = $data['uid'];
                    $point_reward_data['attendence_point'] = (int)$attendence_point;
                    $point_reward_data['date'] = $date;
                    //If point_attendence is zero for today
                    if ((int)$point_attendence_rec->point <= 0) {
                        $add_reward_point = $this->add_attendence_point_to_reward($point_reward_data);

                        $point_attendence_data['point'] = (int)$attendence_point;
                        if ($add_reward_point) {

                            $pointAttendanceRecord = PointAttendance::find($point_attendence_rec->id);
                            // Update the record with new data
                            $respo_s = $pointAttendanceRecord->update($point_attendence_data);
                        }
                    }
                } else {

                    //if get point that will deduct from point_attendence and point_reward 
                    if ((int)$point_attendence_rec->point >= (int)$attendence_point) {

                        $point_attendence_data['point'] = 0;
                        $pointAttendanceRecord = PointAttendance::find($point_attendence_rec->id);
                        // Update the record with new data
                        $update_attendence_point_a = $pointAttendanceRecord->update($point_attendence_data);

                        if ($update_attendence_point_a) {
                            //Reward point data to insert in point_reward table
                            $point_reward_data_d['employee_id'] = $data['uid'];
                            $point_reward_data_d['deduct_attendence_point'] = (int)$attendence_point;
                            $point_reward_data_d['date'] = $date;

                            $respo_s = $this->deduct_attendence_point_to_reward($point_reward_data_d);
                        }
                    }
                }
            } else {
                if ((int)$point_attendence_rec->point >= (int)$attendence_point) {

                    $point_attendence_data['point'] = 0;

                    $pointAttendanceRecord = PointAttendance::find($point_attendence_rec->id);
                    // Update the record with new data
                    $update_attendence_point_b = $pointAttendanceRecord->update($point_attendence_data);

                    if ($update_attendence_point_b) {
                        //Reward point data to insert in point_reward table
                        $point_reward_data_e['employee_id'] = $data['uid'];
                        $point_reward_data_e['deduct_attendence_point'] = (int)$attendence_point;
                        $point_reward_data_e['date'] = $date;

                        $respo_s = $this->deduct_attendence_point_to_reward($point_reward_data_e);
                    }
                }
            }
        }

        if ($respo_s) {
            return true;
        } else {
            return false;
        }
    }

    /*Insert attendence point to employee point_reward database table*/
    private function add_attendence_point_to_reward($data = array())
    {
        $date = Carbon::parse($data['date']);
        $date_y = $date->year;
        $date_m = $date->month;
        $data['date'] = $date;

        $point_reward_rec = DB::table("reward_points")
            ->where('employee_id', $data['employee_id'])
            ->whereNull('deleted_at')
            ->whereYear('date', $date_y)
            ->whereMonth('date', $date_m)
            ->first();

        if ($point_reward_rec && $point_reward_rec->id != null) {

            // Adding attendence point with existing attendence reward point, if employee already exists in point_reward table..
            $attendence_point = (int)$point_reward_rec->attendance + (int)$data['attendence_point'];
            $total = (int)$point_reward_rec->management + (int)$point_reward_rec->collaborative + $attendence_point;
            $point_reward_data['attendance'] = $attendence_point;
            $point_reward_data['total'] = $total;

            $pointRewardRecord = RewardPoint::find($point_reward_rec->id);
            // Update the record with new data
            $update_reward_point = $pointRewardRecord->update($point_reward_data);

            if ($update_reward_point) {
                return true;
            } else {
                return false;
            }
        } else {
            // Inserting attendence point, if employee not exists in point_reward table..
            $point_reward_insert['date'] = $date;
            $point_reward_insert['attendance'] = $data['attendence_point'];
            $point_reward_insert['total'] = $data['attendence_point'];
            $point_reward_insert['employee_id'] = $data['employee_id'];

            $insert_reward_point = RewardPoint::create($point_reward_insert);

            if ($insert_reward_point) {
                return true;
            } else {
                return false;
            }
        }
    }

    /*Deduct attendence point to employee point_reward database table*/
    private function deduct_attendence_point_to_reward($data = array())
    {
        $date = Carbon::parse($data['date']);
        $date_y = $date->year;
        $date_m = $date->month;
        $data['date'] = $date;

        $point_reward_rec = DB::table("reward_points")
            ->where('employee_id', $data['employee_id'])
            ->whereNull('deleted_at')
            ->whereYear('date', $date_y)
            ->whereMonth('date', $date_m)
            ->first();

        if ($point_reward_rec && $point_reward_rec->id != null) {

            // Adding attendence point with existing attendence reward point, if employee already exists in point_reward table..
            $attendence_point = (int)$point_reward_rec->attendance - (int)$data['deduct_attendence_point'];
            $total = (int)$point_reward_rec->management + (int)$point_reward_rec->collaborative + $attendence_point;
            $point_reward_data['attendance'] = $attendence_point;
            $point_reward_data['total'] = $total;

            $pointRewardRecord = RewardPoint::find($point_reward_rec->id);
            // Update the record with new data
            $update_reward_point = $pointRewardRecord->update($point_reward_data);

            if ($update_reward_point) {
                return true;
            } else {
                return false;
            }
        }
    }

    public function employee_attn_history($data)
    {
        $att_dates = date("Y-m-d", strtotime($data['time']));
        // Convert the given date to a Carbon instance
        $date = Carbon::createFromFormat('Y-m-d', $att_dates);
        // Get the next day's date
        $nextDayDate = $date->addDay()->toDateString();

        $att_in = DB::table('attendances')
            ->where('employee_id', $data['uid'])
            ->whereNull('deleted_at')
            ->whereRaw("time > ?", [$att_dates])
            ->whereRaw("time < ?", [$nextDayDate])
            ->orderBy('id', 'ASC')
            ->count();

        return $att_in;
    }

    public function employee_attn_in_time($data)
    {
        $attendence = DB::table('attendances as a')
            ->selectRaw('a.time, MIN(a.time) as intime, MAX(a.time) as outtime, a.employee_id as uid')
            ->where('a.time', '>', date('Y-m-d', strtotime($data['time'])))
            ->where('a.employee_id', $data['uid'])
            ->whereNull('a.deleted_at')
            ->orderBy('a.time', 'ASC')
            ->get();

        $in_time = null;
        if (!empty($attendence[0]->intime)) {
            $in_time = Carbon::createFromFormat('Y-m-d H:i:s', $attendence[0]->intime)->format('H:i');
        }

        return $in_time;
    }

    /**
     * Calculating totalNetworkHours for an employee current_day
     */
    public function employee_worked_hour_today($employee_id, $mydate)
    {

        $totalhour = 0;
        $totalwasthour = 0;
        $totalnetworkhour = 0;

        $attenddata = DB::table('attendances as a')
            ->select('a.time', DB::raw('MIN(a.time) as intime'), DB::raw('MAX(a.time) as outtime'), 'a.employee_id as uid')
            ->where('a.time', 'LIKE', '%' . date("Y-m-d", strtotime($mydate)) . '%')
            ->where('a.employee_id', $employee_id)
            ->whereNull('a.deleted_at')
            ->get();

        // Getting totalWorkHours
        $date_a = Carbon::createFromFormat('Y-m-d H:i:s', $attenddata[0]->outtime);
        $date_b = Carbon::createFromFormat('Y-m-d H:i:s', $attenddata[0]->intime);
        $interval = $date_a->diff($date_b);

        $totalwhour = $interval->format('%h:%i:%s');

        // End of Getting totalWorkHours

        $att_dates = date("Y-m-d", strtotime($attenddata[0]->time));
        // Convert the given date to a Carbon instance
        $exist_date = Carbon::createFromFormat('Y-m-d', $att_dates);
        // Get the next day's date
        $nextDayDate = $exist_date->addDay()->toDateString();
        $att_in = DB::table('attendances as a')
            ->select('a.*', 'b.first_name', 'b.last_name')
            ->leftJoin('employees as b', 'a.employee_id', '=', 'b.id')
            ->where('a.employee_id', $attenddata[0]->uid)
            ->whereRaw("a.time > ?", [$att_dates])
            ->whereRaw("a.time < ?", [$nextDayDate])
            ->whereNull('a.deleted_at')
            ->orderBy('a.time', 'ASC')
            ->get();

        $ix = 1;
        $in_data = [];
        $out_data = [];
        foreach ($att_in as $attendancedata) {

            if ($ix % 2) {
                $status = "IN";
                $in_data[$ix] = $attendancedata->time;
            } else {
                $status = "OUT";
                $out_data[$ix] = $attendancedata->time;
            }
            $ix++;
        }

        $result_in = array_values($in_data);
        $result_out = array_values($out_data);
        $total = [];
        $count_out = count($result_out);

        if ($count_out >= 2) {
            $n_out = $count_out - 1;
        } else {
            $n_out = 0;
        }
        for ($i = 0; $i < $n_out; $i++) {

            $date_a = Carbon::parse($result_in[$i + 1]);
            $date_b = Carbon::parse($result_out[$i]);
            $interval = $date_a->diff($date_b);

            $total[$i] = $interval->format('%h:%i:%s');
        }

        $hou = 0;
        $min = 0;
        $sec = 0;
        $totaltime = '00:00:00';
        $length = sizeof($total);

        for ($x = 0; $x <= $length; $x++) {
            $split = explode(":", @$total[$x]);
            $hou += @(int)$split[0];
            $min += @$split[1];
            $sec += @$split[2];
        }

        $seconds = $sec % 60;
        $minutes = $sec / 60;
        $minutes = (int)$minutes;
        $minutes += $min;
        $hours = $minutes / 60;
        $minutes = $minutes % 60;
        $hours = (int)$hours;
        $hours += $hou % 24;

        $totalwasthour = $hours . ":" . $minutes . ":" . $seconds;

        $date_a = Carbon::parse($totalwhour);
        $date_b = Carbon::parse($totalwasthour);
        $networkhours = $date_a->diff($date_b);

        $totalnetworkhour = $networkhours->h;

        return (int)$totalnetworkhour;
    }

    /**
     * Get Point Settings
     */
    public function get_last_record()
    {
        // point_settings info
        return PointSettings::select('*')
            ->first();
    }

    public function edit(ManualAttendance $attendance, OrgScopeService $orgScopeService)
    {
        $this->ensureEmployeeIsAccessible((int) $attendance->employee_id, $orgScopeService);
        $attendance->load('employee');
        $employee = $this->scopedEmployeeQuery($orgScopeService)->get();

        return view('humanresource::attendance.edit', compact('attendance', 'employee'));
    }

    public function update(Request $request, Attendance $attendance, OrgScopeService $orgScopeService)
    {
        $validated = $request->validate([
            'employee_id' => 'required',
            'time' => 'required',
        ]);
        $this->ensureEmployeeIsAccessible((int) $attendance->employee_id, $orgScopeService);
        $employee = $this->ensureEmployeeIsAccessible((int) $validated['employee_id'], $orgScopeService);
        $oldEmployeeId = (int) $attendance->employee_id;
        $oldDate = Carbon::parse($attendance->time)->toDateString();

        $attendance_history = [
            'uid'    => $employee->id,
            'state'  => 1,
            'id'     => 0,
            'time'   => $request->input('time'),
        ];


        $neTime = Carbon::parse($request->time)->format('Y-m-d H:i:s');
    $validated['employee_id'] = $employee->id;
        $validated['time'] = $neTime;

        // manual attendance
        $resp = $attendance->update($validated);
        if ($resp) {

            $resp_attend = $this->insert_attendance_point($attendance_history);
            AttendanceCaptureService::syncDailyExceptionStatus($oldEmployeeId, $oldDate);
            AttendanceCaptureService::syncDailyExceptionStatus((int) $attendance->employee_id, Carbon::parse($attendance->time)->toDateString());
            return redirect()->route('reports.attendance-log-details', $attendance->employee_id)->with('success', localize('data_save'));
        } else {
            return redirect()->back()->with('error', localize('error'));
        }
    }

    /**
     * @param Attendance $attendance
     */
    public function destroy(Attendance $attendance, OrgScopeService $orgScopeService)
    {
        $this->ensureEmployeeIsAccessible((int) $attendance->employee_id, $orgScopeService);
        $employeeId = (int) $attendance->employee_id;
        $date = Carbon::parse($attendance->time)->toDateString();

        $attendance_history = [
            'uid'    => $attendance->employee_id,
            'state'  => 1,
            'id'     => 0,
            'time'   => $attendance->time,
        ];

        $resp = $attendance->delete();
        if ($resp) {
            $resp_attend = $this->insert_attendance_point($attendance_history);
            AttendanceCaptureService::syncDailyExceptionStatus($employeeId, $date);
            return response()->json(['data' => null, 'message' => localize('data_deleted_successfully'), 'status' => 200]);
        } else {
            return response()->json(['data' => null, 'message' => localize('something_error'), 'status' => 500]);
        }
    }

    public function bulk(Request $request)
    {
        $request->validate([
            'bulk' => 'required|mimes:xlsx|max:2048',
        ], [
            'bulk.required' => 'The file is required',
            'bulk.mimes' => 'The file must be an Excel file',
            'bulk.max' => 'The file size must be less than 2MB',
        ]);

        try {
            $allowedEmployeeIds = $this->scopedEmployeeQuery(app(OrgScopeService::class))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $export = Excel::import(new AttendanceImport($allowedEmployeeIds), $request->file('bulk'));
            Toastr::success(localize('data_imported_successfully'));
            return redirect()->route('attendances.create');
        } catch (\Exception $e) {
            Toastr::error(localize('operation_failed' . $e->getMessage()));
            return redirect()->route('attendances.create');
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function monthlyAttendanceBulkImport(Request $request)
    {
        $request->validate([
            'monthly_bulk' => 'required|mimes:xlsx|max:2048',
        ], [
            'monthly_bulk.required' => 'The file is required',
            'monthly_bulk.mimes' => 'The file must be an Excel file',
            'monthly_bulk.max' => 'The file size must be less than 2MB',
        ]);

        try {
            $allowedEmployeeIds = $this->scopedEmployeeQuery(app(OrgScopeService::class))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            Excel::import(new AttendanceImport($allowedEmployeeIds), $request->file('monthly_bulk'));

            return redirect()->route('attendances.monthlyCreate')->with('success', localize('data_imported_successfully'));
        } catch (\Exception $e) {
            return $e;
            Toastr::error(localize('operation_failed'));

            return redirect()->route('attendances.monthlyCreate');
        }
    }

    public function monthlyCreate(Request $request, OrgScopeService $orgScopeService, OrgUnitRuleService $orgUnitRuleService)
    {
        $selectedDepartmentId = $request->integer('department_id', 0) ?: null;
        $selectedEmployeeId = $request->input('employee_id');
        $selectedYear  = (int) $request->input('year', now()->year);
        $selectedMonth = (int) $request->input('month', now()->month);

        $employeeQuery = $this->scopedEmployeeQuery($orgScopeService);
        if ($selectedDepartmentId) {
            $employeeQuery->where(function ($q) use ($selectedDepartmentId) {
                $q->where('department_id', (int) $selectedDepartmentId)
                    ->orWhere('sub_department_id', (int) $selectedDepartmentId);
            });
        }

        $employees = $employeeQuery
            ->with(['department', 'sub_department'])
            ->get(['id', 'first_name', 'middle_name', 'last_name', 'employee_id', 'department_id', 'sub_department_id']);

        $orgUnitOptions = $orgUnitRuleService->hierarchyOptions();
        $scopedDepartmentIds = $orgScopeService->accessibleDepartmentIds(auth()->user());
        if (is_array($scopedDepartmentIds) && count($scopedDepartmentIds) > 0) {
            $allowedDepartmentIds = array_map('intval', $scopedDepartmentIds);
            $orgUnitOptions = $orgUnitOptions
                ->filter(function ($option) use ($allowedDepartmentIds) {
                    return in_array((int) data_get($option, 'id', 0), $allowedDepartmentIds, true);
                })
                ->values();
        }

        // Compute days in selected month for grid header
        $daysInMonth = \Carbon\Carbon::create($selectedYear, $selectedMonth, 1)->daysInMonth;
        $monthDays = range(1, $daysInMonth);

        // Load snapshots for the month
        $startDate = \Carbon\Carbon::create($selectedYear, $selectedMonth, 1)->startOfDay();
        $endDate   = \Carbon\Carbon::create($selectedYear, $selectedMonth, $daysInMonth)->endOfDay();

        $employeeIds = $employees->pluck('id')->all();

        $snapshotQuery = \Modules\HumanResource\Entities\AttendanceDailySnapshot::query()
            ->whereBetween('snapshot_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereIn('employee_id', $employeeIds);

        if ($selectedEmployeeId) {
            $snapshotQuery->where('employee_id', (int) $selectedEmployeeId);
        }

        // Group snapshots by employee_id → day
        $snapshotMap = $snapshotQuery->get()
            ->groupBy('employee_id')
            ->map(fn ($rows) => $rows->keyBy(fn ($r) => (int) \Carbon\Carbon::parse($r->snapshot_date)->day));

        // If no snapshot data, fallback to attendance records for display
        $displayEmployees = $selectedEmployeeId
            ? $employees->where('id', (int) $selectedEmployeeId)
            : $employees;

        $employee = $employees; // backward-compat for monthlyStore view

        return view('humanresource::attendance.monthlycreate', compact(
            'employee', 'employees', 'displayEmployees', 'snapshotMap',
            'selectedYear', 'selectedMonth', 'selectedEmployeeId', 'selectedDepartmentId', 'orgUnitOptions',
            'daysInMonth', 'monthDays'
        ));
    }

    public function monthlyStore(Request $request, OrgScopeService $orgScopeService)
    {
        $request->validate([
            'employee_id' => 'required|integer',
            'year' => 'required|integer',
            'month' => 'required|integer|min:1|max:12',
            'in_time' => 'required',
            'out_time' => 'required',
        ]);

        $employee = $this->ensureEmployeeIsAccessible((int) $request->employee_id, $orgScopeService);
        $year = $request->year;
        $month = $request->month;
        $in_time = Carbon::parse($request->in_time)->format('H:i:s');
        $out_time = Carbon::parse($request->out_time)->format('H:i:s');
        $daysInMonth = Carbon::create($year, $month)->daysInMonth;
        $weeklyHoliday = WeekHoliday::first();

        $publicHoliday = Holiday::whereMonth('start_date', $month)->whereYear('start_date', $year)->get()->toArray();
        $p_holidays = [];
        // public holiday day name add in $p_holidays array
        foreach ($publicHoliday as $key => $value) {
            if ($value['total_day'] > 1) {
                // carbon period start date and end date
                $start_date = Carbon::parse($value['start_date']);
                $end_date = Carbon::parse($value['end_date']);
                $period = \Carbon\CarbonPeriod::create($start_date, $end_date);
                foreach ($period as $date) {
                    $p_holidays[] = $date->format('d');
                }
            } else {
                $p_holidays[] = Carbon::parse($value['start_date'])->format('d');
            }
        }

        $holidays = array_map('trim', explode(',', strtoupper($weeklyHoliday->dayname)));

        try {
            DB::beginTransaction();
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $checkDay = Carbon::createFromFormat('Y-m-d', $year . '-' . $month . '-' . $day)->format('l');
                if (in_array(strtoupper($checkDay), $holidays) || in_array((string) $day, $p_holidays)) {
                    continue;
                }

                $inTime = Carbon::createFromFormat('Y-m-d H:i:s', $year . '-' . $month . '-' . $day . ' ' . $in_time)->format('Y-m-d H:i:s');
                $outTime = Carbon::createFromFormat('Y-m-d H:i:s', $year . '-' . $month . '-' . $day . ' ' . $out_time)->format('Y-m-d H:i:s');

                AttendanceCaptureService::capture([
                    'employee_id' => (int) $employee->id,
                    'time' => $inTime,
                    'attendance_source' => 'monthly',
                ]);
                AttendanceCaptureService::capture([
                    'employee_id' => (int) $employee->id,
                    'time' => $outTime,
                    'attendance_source' => 'monthly',
                ]);
            }
            DB::commit();

            return redirect()->route('attendances.monthlyCreate')->with('success', localize('data_save'));
        } catch (\Throwable $th) {
            DB::rollback();

            return redirect()->route('attendances.monthlyCreate')->with('error', localize('error'));
        }
    }

    public function missingAttendance(Request $request, OrgScopeService $orgScopeService)
    {
        $date = $request->date;
        // if date is not set then set current date
        if (!$date) {
            $date = Carbon::now()->format('Y-m-d');
        } else {
            $date = Carbon::parse($date)->format('Y-m-d');
        }
        $missingAttendance = $this->scopedEmployeeQuery($orgScopeService)
            ->with(['position:id,position_name'])
            ->doesntHave('attendances', 'and', function ($query) use ($date) {
                $query->whereDate('time', $date);
            })
            ->get(['id', 'first_name', 'middle_name', 'last_name', 'position_id', 'employee_id']);
        return view('humanresource::attendance.missing', compact('missingAttendance', 'date'));
    }

    public function missingAttendanceStore(Request $request, OrgScopeService $orgScopeService)
    {
        $request->validate([
            'employee_id' => 'required|array',
            'employee_id.*' => 'required|integer',
            'in_time' => 'required|array',
            'in_time.*' => 'required|date_format:H:i',
            'out_time' => 'required|array',
            'out_time.*' => 'required|date_format:H:i',
            'date' => 'required|date',
        ]);

        $this->ensureEmployeesAreAccessible($request->employee_id, $orgScopeService);

        try {
            DB::beginTransaction();
            $in_time = $request->in_time;
            $out_time = $request->out_time;
            $employee_id = $request->employee_id;
            $date = Carbon::parse($request->date);

            foreach ($employee_id as $key => $value) {
                $inDateTime = $date->copy()->modify($in_time[$key]);
                $outDateTime = $date->copy()->modify($out_time[$key]);

                AttendanceCaptureService::capture([
                    'employee_id' => (int) $value,
                    'time' => $inDateTime->format('Y-m-d H:i:s'),
                    'attendance_source' => 'missing',
                ]);
                AttendanceCaptureService::capture([
                    'employee_id' => (int) $value,
                    'time' => $outDateTime->format('Y-m-d H:i:s'),
                    'attendance_source' => 'missing',
                ]);
            }

            DB::commit();
            return response()->json(['data' => null, 'message' => localize('attendance_save_successfully'), 'status' => 200]);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json(['data' => null, 'message' => localize('something_went_wrong') . $th->getMessage(), 'status' => 500]);
        }
    }

    /**
     * List attendance exception rows (odd IN/OUT punches).
     */
    public function exceptions(Request $request, OrgScopeService $orgScopeService)
    {
        $date = $request->date ? Carbon::parse($request->date)->format('Y-m-d') : Carbon::now()->format('Y-m-d');
        $accessibleDepartmentIds = $orgScopeService->accessibleDepartmentIds(auth()->user());

        $exceptions = Attendance::query()
            ->selectRaw('employee_id, workplace_id, DATE(time) as attendance_date, COUNT(*) as punch_count, MIN(time) as first_punch, MAX(time) as last_punch, MAX(exception_reason) as exception_reason')
            ->with([
                'employee:id,employee_id,first_name,middle_name,last_name,department_id,sub_department_id',
                'employee.position:id,position_name',
                'workplace:id,department_name',
            ])
            ->whereDate('time', $date)
            ->groupBy('employee_id', 'workplace_id', 'attendance_date')
            ->havingRaw('COUNT(*) % 2 = 1')
            ->orderBy('employee_id')
            ->get();

        if (is_array($accessibleDepartmentIds) && count($accessibleDepartmentIds) > 0) {
            $departmentIds = array_map('intval', $accessibleDepartmentIds);
            $exceptions = $exceptions->filter(function ($row) use ($departmentIds) {
                $employee = $row->employee;
                return in_array((int) ($employee?->department_id ?? 0), $departmentIds, true)
                    || in_array((int) ($employee?->sub_department_id ?? 0), $departmentIds, true)
                    || in_array((int) ($row->workplace_id ?? 0), $departmentIds, true);
            })->values();
        }

        return view('humanresource::attendance.exceptions', compact('exceptions', 'date'));
    }
}
