<?php

namespace Modules\HumanResource\Http\Controllers;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\HumanResource\Entities\Position;
use Modules\HumanResource\Entities\SystemRole;
use Modules\HumanResource\Entities\UserOrgRole;
use Modules\HumanResource\Entities\WorkflowDefinition;
use Modules\HumanResource\Entities\WorkflowDefinitionStep;
use Modules\HumanResource\Support\WorkflowActorResolverService;
use Modules\HumanResource\Support\WorkflowPolicyService;
use Spatie\Permission\Models\Role;

class WorkflowPolicyController extends Controller
{
    protected array $actionTypeOptions = ['review', 'recommend', 'approve'];

    public function __construct()
    {
        $this->middleware('permission:read_org_governance|read_department')->only(['index', 'preview']);
        $this->middleware('permission:create_org_governance|create_department')->only(['store']);
        $this->middleware('permission:update_org_governance|update_department')->only(['update']);
        $this->middleware('permission:delete_org_governance|delete_department')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $moduleKey = trim((string) $request->query('module_key', ''));
        $requestTypeKey = trim((string) $request->query('request_type_key', ''));
        $moduleCatalog = $this->workflowCatalog();
        $policyTemplates = $this->policyTemplates();

        $query = WorkflowDefinition::query()
            ->with([
                'steps.systemRole:id,code,name,name_km',
                'steps.actorUser:id,full_name,email',
                'steps.actorPosition:id,position_name,position_name_km',
                'steps.actorResponsibility:id,code,name,name_km',
                'steps.actorRole:id,name',
            ])
            ->orderBy('module_key')
            ->orderBy('request_type_key')
            ->orderBy('priority')
            ->orderBy('id');

        if ($moduleKey !== '') {
            $query->where('module_key', $moduleKey);
        }

        if ($requestTypeKey !== '') {
            $query->where('request_type_key', $requestTypeKey);
        }

        $definitions = $query->get();

        $dbPairs = WorkflowDefinition::query()
            ->select('module_key', 'request_type_key')
            ->distinct()
            ->orderBy('module_key')
            ->orderBy('request_type_key')
            ->get();

        $dbModuleKeys = $dbPairs
            ->pluck('module_key')
            ->filter()
            ->values();

        $moduleOptions = collect(array_keys($moduleCatalog))
            ->merge($dbModuleKeys)
            ->filter()
            ->unique()
            ->values();

        $requestTypeOptionsByModule = [];
        foreach ($moduleOptions as $moduleOption) {
            $catalogRequestTypes = array_keys((array) data_get($moduleCatalog, $moduleOption . '.request_types', []));
            $dbRequestTypes = $dbPairs
                ->where('module_key', $moduleOption)
                ->pluck('request_type_key')
                ->filter()
                ->values()
                ->all();

            $requestTypeOptionsByModule[$moduleOption] = collect($catalogRequestTypes)
                ->merge($dbRequestTypes)
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        $requestTypeOptions = collect($requestTypeOptionsByModule)
            ->flatten(1)
            ->filter()
            ->unique()
            ->values();

        $moduleLabels = $moduleOptions
            ->mapWithKeys(function ($key) use ($moduleCatalog) {
                $label = (string) data_get($moduleCatalog, $key . '.label', '');
                return [$key => ($label !== '' ? $label : $this->humanizeKey((string) $key))];
            })
            ->all();

        $requestTypeLabels = [];
        foreach ($requestTypeOptionsByModule as $moduleOption => $requestTypeOptionsPerModule) {
            foreach ($requestTypeOptionsPerModule as $requestTypeOption) {
                $label = (string) data_get($moduleCatalog, $moduleOption . '.request_types.' . $requestTypeOption . '.label', '');
                if ($label === '') {
                    $label = $this->humanizeKey((string) $requestTypeOption);
                }
                if (!array_key_exists($requestTypeOption, $requestTypeLabels)) {
                    $requestTypeLabels[$requestTypeOption] = $label;
                }
            }
        }

        $responsibilities = SystemRole::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'code', 'name', 'name_km']);
        $positions = Position::query()
            ->withoutGlobalScope('sortByLatest')
            ->where('is_active', 1)
            ->orderBy('position_name')
            ->get(['id', 'position_name', 'position_name_km']);
        $spatieRoles = Role::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('humanresource::master-data.workflow-policies.index', [
            'definitions' => $definitions,
            'module_options' => $moduleOptions,
            'request_type_options' => $requestTypeOptions,
            'request_type_options_by_module' => $requestTypeOptionsByModule,
            'selected_module_key' => $moduleKey,
            'selected_request_type_key' => $requestTypeKey,
            'module_catalog' => $moduleCatalog,
            'module_labels' => $moduleLabels,
            'request_type_labels' => $requestTypeLabels,
            'policy_templates' => $policyTemplates,
            'org_role_options' => UserOrgRole::roleOptions(),
            'scope_type_options' => UserOrgRole::scopeOptions(),
            'action_type_options' => $this->actionTypeOptions,
            'org_role_labels' => UserOrgRole::roleLabels(),
            'scope_type_labels' => $this->scopeTypeLabels(),
            'action_type_labels' => $this->actionTypeLabels(),
            'actor_type_options' => WorkflowDefinitionStep::actorTypeOptions(),
            'actor_type_labels' => $this->actorTypeLabels(),
            'responsibilities' => $responsibilities,
            'positions' => $positions,
            'spatie_roles' => $spatieRoles,
        ]);
    }

    public function store(Request $request)
    {
        $payload = $this->validatePayload($request);

        DB::transaction(function () use ($payload): void {
            $definition = WorkflowDefinition::create([
                'module_key' => $payload['module_key'],
                'request_type_key' => $payload['request_type_key'],
                'name' => $payload['name'],
                'description' => $payload['description'],
                'condition_json' => $payload['condition_json'],
                'priority' => $payload['priority'],
                'is_active' => $payload['is_active'],
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            foreach ($payload['steps'] as $step) {
                $definition->steps()->create(array_merge($step, [
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]));
            }
        });

        Toastr::success(localize('data_save', 'Data saved'));
        return redirect()->route('workflow-policies.index');
    }

    public function update(Request $request, WorkflowDefinition $workflow_policy)
    {
        $payload = $this->validatePayload($request);

        DB::transaction(function () use ($workflow_policy, $payload): void {
            $workflow_policy->update([
                'module_key' => $payload['module_key'],
                'request_type_key' => $payload['request_type_key'],
                'name' => $payload['name'],
                'description' => $payload['description'],
                'condition_json' => $payload['condition_json'],
                'priority' => $payload['priority'],
                'is_active' => $payload['is_active'],
                'updated_by' => Auth::id(),
            ]);

            // Steps use SoftDeletes; force delete old rows to avoid unique conflicts
            // on (workflow_definition_id, step_order) when recreating the matrix.
            $workflow_policy->steps()->withTrashed()->forceDelete();

            foreach ($payload['steps'] as $step) {
                $workflow_policy->steps()->create(array_merge($step, [
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]));
            }
        });

        Toastr::success(localize('data_update', 'Data updated'));
        return redirect()->route('workflow-policies.index');
    }

    public function destroy(WorkflowDefinition $workflow_policy): JsonResponse
    {
        DB::transaction(function () use ($workflow_policy): void {
            $workflow_policy->steps()->withTrashed()->forceDelete();
            $workflow_policy->update(['deleted_by' => Auth::id()]);
            $workflow_policy->delete();
        });

        return response()->json([
            'success' => true,
            'message' => localize('data_delete', 'Deleted successfully'),
        ]);
    }

    public function preview(
        Request $request,
        WorkflowPolicyService $workflowPolicyService,
        WorkflowActorResolverService $actorResolverService
    ): JsonResponse {
        $validated = $request->validate([
            'module_key' => ['required', 'string', 'max:64'],
            'request_type_key' => ['required', 'string', 'max:64'],
            'days' => ['nullable', 'numeric', 'min:0'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'employee_type_id' => ['nullable', 'integer'],
            'employee_type_code' => ['nullable', 'string', 'max:80'],
            'org_unit_type_id' => ['nullable', 'integer'],
            'org_unit_type_code' => ['nullable', 'string', 'max:80'],
            'is_full_right' => ['nullable', 'boolean'],
        ]);

        $plan = $workflowPolicyService->resolveAndBuild(
            (string) $validated['module_key'],
            (string) $validated['request_type_key'],
            $validated
        );

        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => localize('workflow_policy_not_found', 'No workflow policy matched this request context.'),
            ], 404);
        }

        $plan = $actorResolverService->previewPlan($plan, $validated);

        return response()->json([
            'success' => true,
            'message' => localize('workflow_policy_found', 'Workflow policy matched successfully.'),
            'data' => $plan,
        ]);
    }

    protected function validatePayload(Request $request): array
    {
        $roleOptions = UserOrgRole::roleOptions();

        $validated = $request->validate([
            'module_key' => ['required', 'string', 'max:64'],
            'request_type_key' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', 'integer', 'min:1', 'max:9999'],
            'is_active' => ['required', 'boolean'],
            'condition_json' => ['nullable', 'string'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.step_order' => ['required', 'integer', 'min:1', 'max:255'],
            'steps.*.step_key' => ['nullable', 'string', 'max:64'],
            'steps.*.step_name' => ['required', 'string', 'max:190'],
            'steps.*.action_type' => ['required', Rule::in($this->actionTypeOptions)],

            // Legacy role fields (temporary coexist).
            'steps.*.org_role' => ['nullable', Rule::in($roleOptions)],
            'steps.*.system_role_id' => ['nullable', 'integer', Rule::exists('system_roles', 'id')],

            // New actor-based fields.
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

        $conditionJson = $this->normalizeConditionJson((string) ($validated['condition_json'] ?? ''));
        if ($conditionJson === null) {
            throw ValidationException::withMessages([
                'condition_json' => localize('invalid_json_format', 'Condition JSON format is invalid.'),
            ]);
        }

        $steps = collect((array) ($validated['steps'] ?? []))
            ->map(function (array $step): array {
                $normalized = [
                    'step_order' => (int) $step['step_order'],
                    'step_key' => !empty($step['step_key']) ? trim((string) $step['step_key']) : null,
                    'step_name' => trim((string) $step['step_name']),
                    'action_type' => trim((string) $step['action_type']),
                    'scope_type' => $this->normalizeScope((string) $step['scope_type']),
                    'is_final_approval' => (bool) $step['is_final_approval'],
                    'is_required' => (bool) $step['is_required'],
                    'can_return' => (bool) $step['can_return'],
                    'can_reject' => (bool) $step['can_reject'],
                    'org_role' => !empty($step['org_role']) ? trim((string) $step['org_role']) : null,
                    'system_role_id' => !empty($step['system_role_id']) ? (int) $step['system_role_id'] : null,
                    'actor_type' => trim((string) ($step['actor_type'] ?? WorkflowDefinitionStep::ACTOR_TYPE_RESPONSIBILITY)),
                    'actor_user_id' => !empty($step['actor_user_id']) ? (int) $step['actor_user_id'] : null,
                    'actor_position_id' => !empty($step['actor_position_id']) ? (int) $step['actor_position_id'] : null,
                    'actor_responsibility_id' => !empty($step['actor_responsibility_id']) ? (int) $step['actor_responsibility_id'] : null,
                    'actor_role_id' => !empty($step['actor_role_id']) ? (int) $step['actor_role_id'] : null,
                ];

                return $this->normalizeActorStep($normalized);
            })
            ->sortBy('step_order')
            ->values();

        if ($steps->pluck('step_order')->unique()->count() !== $steps->count()) {
            throw ValidationException::withMessages([
                'steps' => localize('duplicate_step_order', 'Step order must be unique.'),
            ]);
        }

        if ($steps->where('is_final_approval', true)->count() === 0) {
            throw ValidationException::withMessages([
                'steps' => localize('workflow_final_step_required', 'At least one step must be marked as final approval.'),
            ]);
        }

        $moduleKey = trim(mb_strtolower((string) $validated['module_key']));
        $requestTypeKey = trim(mb_strtolower((string) $validated['request_type_key']));

        $moduleCatalog = $this->workflowCatalog();
        if (array_key_exists($moduleKey, $moduleCatalog)) {
            $catalogRequestTypes = array_keys((array) data_get($moduleCatalog, $moduleKey . '.request_types', []));
            $existingRequestTypes = WorkflowDefinition::query()
                ->where('module_key', $moduleKey)
                ->pluck('request_type_key')
                ->filter()
                ->unique()
                ->values()
                ->all();
            $allowedRequestTypes = collect($catalogRequestTypes)
                ->merge($existingRequestTypes)
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (!empty($allowedRequestTypes) && !in_array($requestTypeKey, $allowedRequestTypes, true)) {
                throw ValidationException::withMessages([
                    'request_type_key' => localize('invalid_request_type_for_module', 'Request type does not match the selected module.'),
                ]);
            }
        }

        return [
            'module_key' => $moduleKey,
            'request_type_key' => $requestTypeKey,
            'name' => trim((string) $validated['name']),
            'description' => !empty($validated['description']) ? trim((string) $validated['description']) : null,
            'priority' => (int) $validated['priority'],
            'is_active' => (bool) $validated['is_active'],
            'condition_json' => $conditionJson,
            'steps' => $steps->all(),
        ];
    }

    protected function normalizeActorStep(array $step): array
    {
        $actorType = (string) ($step['actor_type'] ?? WorkflowDefinitionStep::ACTOR_TYPE_RESPONSIBILITY);
        if (!in_array($actorType, WorkflowDefinitionStep::actorTypeOptions(), true)) {
            throw ValidationException::withMessages([
                'steps' => localize('invalid_actor_type', 'Invalid actor type selected.'),
            ]);
        }

        // Clear irrelevant columns.
        $step['actor_user_id'] = $actorType === WorkflowDefinitionStep::ACTOR_TYPE_SPECIFIC_USER ? $step['actor_user_id'] : null;
        $step['actor_position_id'] = $actorType === WorkflowDefinitionStep::ACTOR_TYPE_POSITION ? $step['actor_position_id'] : null;
        $step['actor_role_id'] = $actorType === WorkflowDefinitionStep::ACTOR_TYPE_SPATIE_ROLE ? $step['actor_role_id'] : null;

        if ($actorType === WorkflowDefinitionStep::ACTOR_TYPE_RESPONSIBILITY) {
            $resolvedResponsibilityId = (int) ($step['actor_responsibility_id'] ?? 0);
            if ($resolvedResponsibilityId <= 0) {
                $resolvedResponsibilityId = (int) ($step['system_role_id'] ?? 0);
            }
            if ($resolvedResponsibilityId <= 0 && !empty($step['org_role'])) {
                $resolvedResponsibilityId = (int) (SystemRole::query()
                    ->where('code', (string) $step['org_role'])
                    ->value('id') ?? 0);
            }

            if ($resolvedResponsibilityId <= 0) {
                throw ValidationException::withMessages([
                    'steps' => localize('responsibility_actor_required', 'Responsibility actor requires a responsibility role.'),
                ]);
            }

            $step['actor_responsibility_id'] = $resolvedResponsibilityId;
            $step['system_role_id'] = $resolvedResponsibilityId;
            $step['org_role'] = $this->resolveRoleCodeByResponsibilityId($resolvedResponsibilityId);
        } else {
            $step['actor_responsibility_id'] = null;
        }

        if ($actorType === WorkflowDefinitionStep::ACTOR_TYPE_SPECIFIC_USER && empty($step['actor_user_id'])) {
            throw ValidationException::withMessages([
                'steps' => localize('specific_user_actor_required', 'Specific-user actor requires user selection.'),
            ]);
        }

        if ($actorType === WorkflowDefinitionStep::ACTOR_TYPE_POSITION && empty($step['actor_position_id'])) {
            throw ValidationException::withMessages([
                'steps' => localize('position_actor_required', 'Position actor requires a position selection.'),
            ]);
        }

        if ($actorType === WorkflowDefinitionStep::ACTOR_TYPE_SPATIE_ROLE && empty($step['actor_role_id'])) {
            throw ValidationException::withMessages([
                'steps' => localize('spatie_role_actor_required', 'Spatie-role actor requires role selection.'),
            ]);
        }

        // Fallback logic for old scope alias.
        $step['scope_type'] = $this->normalizeScope((string) ($step['scope_type'] ?? UserOrgRole::SCOPE_SELF_AND_CHILDREN));

        return $step;
    }

    protected function resolveRoleCodeByResponsibilityId(int $responsibilityId): ?string
    {
        if ($responsibilityId <= 0) {
            return null;
        }

        $code = SystemRole::query()->where('id', $responsibilityId)->value('code');
        if (!$code) {
            return null;
        }

        return (string) $code;
    }

    protected function normalizeScope(string $scope): string
    {
        $scope = trim(mb_strtolower($scope));
        if ($scope === UserOrgRole::SCOPE_SELF) {
            return UserOrgRole::SCOPE_SELF_ONLY;
        }
        return $scope;
    }

    protected function normalizeConditionJson(string $raw): ?array
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return [];
        }

        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    protected function scopeTypeLabels(): array
    {
        return [
            UserOrgRole::SCOPE_SELF_ONLY => localize('scope_self_only', 'Self only'),
            UserOrgRole::SCOPE_SELF_UNIT_ONLY => localize('scope_self_unit_only', 'Self unit only'),
            UserOrgRole::SCOPE_SELF_AND_CHILDREN => localize('scope_self_and_children', 'Self and children'),
            UserOrgRole::SCOPE_ALL => localize('scope_all', 'All units'),
        ];
    }

    protected function actionTypeLabels(): array
    {
        return [
            'review' => localize('action_review', 'ពិនិត្យ'),
            'recommend' => localize('action_recommend', 'ផ្តល់យោបល់/ស្នើ'),
            'approve' => localize('action_approve', 'អនុម័ត'),
        ];
    }

    protected function actorTypeLabels(): array
    {
        return [
            WorkflowDefinitionStep::ACTOR_TYPE_SPECIFIC_USER => localize('actor_specific_user', 'Specific user'),
            WorkflowDefinitionStep::ACTOR_TYPE_POSITION => localize('actor_position', 'Position'),
            WorkflowDefinitionStep::ACTOR_TYPE_RESPONSIBILITY => localize('actor_responsibility', 'Responsibility'),
            WorkflowDefinitionStep::ACTOR_TYPE_SPATIE_ROLE => localize('actor_spatie_role', 'Spatie role (fallback)'),
        ];
    }

    protected function workflowCatalog(): array
    {
        return [
            'correspondence' => [
                'label' => localize('correspondence_management', 'Correspondence Management'),
                'request_types' => [
                    'incoming_letter' => [
                        'label' => localize('incoming_letters', 'Incoming letters'),
                    ],
                    'outgoing_letter' => [
                        'label' => localize('outgoing_letters', 'Outgoing letters'),
                    ],
                ],
            ],
            'leave' => [
                'label' => localize('leave', 'Leave'),
                'request_types' => [
                    'leave_request' => [
                        'label' => localize('leave_request', 'Leave request'),
                    ],
                ],
            ],
            'notice' => [
                'label' => localize('notice', 'Notice'),
                'request_types' => [
                    'notice_general' => [
                        'label' => localize('notice_general', 'General notice'),
                    ],
                ],
            ],
        ];
    }

    protected function policyTemplates(): array
    {
        return [
            'correspondence::incoming_letter' => [
                'name' => localize('incoming_letter_workflow', 'Incoming letter workflow'),
                'description' => localize('incoming_letter_workflow_desc', 'Office comment -> deputy review -> director final approval.'),
                'priority' => 10,
                'condition_json' => [],
                'steps' => [
                    [
                        'step_order' => 1,
                        'step_key' => 'office_comment',
                        'step_name' => localize('office_comment_review', 'Office comment review'),
                        'action_type' => 'review',
                        'actor_type' => WorkflowDefinitionStep::ACTOR_TYPE_RESPONSIBILITY,
                        'org_role' => UserOrgRole::ROLE_MANAGER,
                        'scope_type' => UserOrgRole::SCOPE_SELF_AND_CHILDREN,
                        'is_final_approval' => 0,
                        'is_required' => 1,
                        'can_return' => 1,
                        'can_reject' => 1,
                    ],
                    [
                        'step_order' => 2,
                        'step_key' => 'deputy_review',
                        'step_name' => localize('deputy_review', 'Deputy review'),
                        'action_type' => 'recommend',
                        'actor_type' => WorkflowDefinitionStep::ACTOR_TYPE_RESPONSIBILITY,
                        'org_role' => UserOrgRole::ROLE_DEPUTY_HEAD,
                        'scope_type' => UserOrgRole::SCOPE_SELF_AND_CHILDREN,
                        'is_final_approval' => 0,
                        'is_required' => 1,
                        'can_return' => 1,
                        'can_reject' => 1,
                    ],
                    [
                        'step_order' => 3,
                        'step_key' => 'director_decision',
                        'step_name' => localize('director_final_approval', 'Director final approval'),
                        'action_type' => 'approve',
                        'actor_type' => WorkflowDefinitionStep::ACTOR_TYPE_RESPONSIBILITY,
                        'org_role' => UserOrgRole::ROLE_HEAD,
                        'scope_type' => UserOrgRole::SCOPE_SELF_AND_CHILDREN,
                        'is_final_approval' => 1,
                        'is_required' => 1,
                        'can_return' => 1,
                        'can_reject' => 1,
                    ],
                ],
            ],
            'correspondence::outgoing_letter' => [
                'name' => localize('outgoing_letter_workflow', 'Outgoing letter workflow'),
                'description' => localize('outgoing_letter_workflow_desc', 'Manager review -> director final approval.'),
                'priority' => 20,
                'condition_json' => [],
                'steps' => [
                    [
                        'step_order' => 1,
                        'step_key' => 'outgoing_review',
                        'step_name' => localize('outgoing_manager_review', 'Outgoing manager review'),
                        'action_type' => 'review',
                        'actor_type' => WorkflowDefinitionStep::ACTOR_TYPE_RESPONSIBILITY,
                        'org_role' => UserOrgRole::ROLE_MANAGER,
                        'scope_type' => UserOrgRole::SCOPE_SELF_AND_CHILDREN,
                        'is_final_approval' => 0,
                        'is_required' => 1,
                        'can_return' => 1,
                        'can_reject' => 1,
                    ],
                    [
                        'step_order' => 2,
                        'step_key' => 'outgoing_approval',
                        'step_name' => localize('outgoing_director_approval', 'Outgoing director approval'),
                        'action_type' => 'approve',
                        'actor_type' => WorkflowDefinitionStep::ACTOR_TYPE_RESPONSIBILITY,
                        'org_role' => UserOrgRole::ROLE_HEAD,
                        'scope_type' => UserOrgRole::SCOPE_SELF_AND_CHILDREN,
                        'is_final_approval' => 1,
                        'is_required' => 1,
                        'can_return' => 1,
                        'can_reject' => 1,
                    ],
                ],
            ],
            'leave::leave_request' => [
                'name' => localize('leave_workflow_default', 'Leave workflow'),
                'description' => localize('leave_workflow_default_desc', 'Default leave policy template.'),
                'priority' => 100,
                'condition_json' => [],
                'steps' => [
                    [
                        'step_order' => 1,
                        'step_key' => 'manager_review',
                        'step_name' => localize('manager_review', 'Manager review'),
                        'action_type' => 'review',
                        'actor_type' => WorkflowDefinitionStep::ACTOR_TYPE_RESPONSIBILITY,
                        'org_role' => UserOrgRole::ROLE_MANAGER,
                        'scope_type' => UserOrgRole::SCOPE_SELF_AND_CHILDREN,
                        'is_final_approval' => 0,
                        'is_required' => 1,
                        'can_return' => 1,
                        'can_reject' => 1,
                    ],
                    [
                        'step_order' => 2,
                        'step_key' => 'head_approval',
                        'step_name' => localize('head_final_approval', 'Head final approval'),
                        'action_type' => 'approve',
                        'actor_type' => WorkflowDefinitionStep::ACTOR_TYPE_RESPONSIBILITY,
                        'org_role' => UserOrgRole::ROLE_HEAD,
                        'scope_type' => UserOrgRole::SCOPE_SELF_AND_CHILDREN,
                        'is_final_approval' => 1,
                        'is_required' => 1,
                        'can_return' => 1,
                        'can_reject' => 1,
                    ],
                ],
            ],
            'notice::notice_general' => [
                'name' => localize('notice_workflow_default', 'Notice workflow'),
                'description' => localize('notice_workflow_default_desc', 'Default notice approval template.'),
                'priority' => 100,
                'condition_json' => [],
                'steps' => [
                    [
                        'step_order' => 1,
                        'step_key' => 'notice_approval',
                        'step_name' => localize('head_approval', 'Head approval'),
                        'action_type' => 'approve',
                        'actor_type' => WorkflowDefinitionStep::ACTOR_TYPE_RESPONSIBILITY,
                        'org_role' => UserOrgRole::ROLE_HEAD,
                        'scope_type' => UserOrgRole::SCOPE_SELF_AND_CHILDREN,
                        'is_final_approval' => 1,
                        'is_required' => 1,
                        'can_return' => 1,
                        'can_reject' => 1,
                    ],
                ],
            ],
        ];
    }

    protected function humanizeKey(string $key): string
    {
        return ucwords(str_replace(['_', '-', '.'], ' ', trim($key)));
    }
}
