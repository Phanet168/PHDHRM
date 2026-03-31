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
use Modules\HumanResource\Entities\WorkflowDefinition;
use Modules\HumanResource\Entities\UserOrgRole;
use Modules\HumanResource\Support\WorkflowPolicyService;

class WorkflowPolicyController extends Controller
{
    protected array $actionTypeOptions = ['review', 'recommend', 'approve'];

    public function __construct()
    {
        $this->middleware('permission:read_department')->only(['index', 'preview']);
        $this->middleware('permission:create_department')->only(['store']);
        $this->middleware('permission:update_department')->only(['update']);
        $this->middleware('permission:delete_department')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $moduleKey = trim((string) $request->query('module_key', ''));
        $requestTypeKey = trim((string) $request->query('request_type_key', ''));

        $query = WorkflowDefinition::query()
            ->with('steps')
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

        $moduleOptions = WorkflowDefinition::query()
            ->select('module_key')
            ->distinct()
            ->orderBy('module_key')
            ->pluck('module_key');

        $requestTypeOptions = WorkflowDefinition::query()
            ->select('request_type_key')
            ->distinct()
            ->orderBy('request_type_key')
            ->pluck('request_type_key');

        return view('humanresource::master-data.workflow-policies.index', [
            'definitions' => $definitions,
            'module_options' => $moduleOptions,
            'request_type_options' => $requestTypeOptions,
            'selected_module_key' => $moduleKey,
            'selected_request_type_key' => $requestTypeKey,
            'org_role_options' => UserOrgRole::roleOptions(),
            'scope_type_options' => UserOrgRole::scopeOptions(),
            'action_type_options' => $this->actionTypeOptions,
            'org_role_labels' => $this->orgRoleLabels(),
            'scope_type_labels' => $this->scopeTypeLabels(),
            'action_type_labels' => $this->actionTypeLabels(),
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
                $definition->steps()->create([
                    'step_order' => $step['step_order'],
                    'step_key' => $step['step_key'],
                    'step_name' => $step['step_name'],
                    'action_type' => $step['action_type'],
                    'org_role' => $step['org_role'],
                    'scope_type' => $step['scope_type'],
                    'is_final_approval' => $step['is_final_approval'],
                    'is_required' => $step['is_required'],
                    'can_return' => $step['can_return'],
                    'can_reject' => $step['can_reject'],
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]);
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

            $workflow_policy->steps()->delete();
            foreach ($payload['steps'] as $step) {
                $workflow_policy->steps()->create([
                    'step_order' => $step['step_order'],
                    'step_key' => $step['step_key'],
                    'step_name' => $step['step_name'],
                    'action_type' => $step['action_type'],
                    'org_role' => $step['org_role'],
                    'scope_type' => $step['scope_type'],
                    'is_final_approval' => $step['is_final_approval'],
                    'is_required' => $step['is_required'],
                    'can_return' => $step['can_return'],
                    'can_reject' => $step['can_reject'],
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]);
            }
        });

        Toastr::success(localize('data_update', 'Data updated'));
        return redirect()->route('workflow-policies.index');
    }

    public function destroy(WorkflowDefinition $workflow_policy): JsonResponse
    {
        DB::transaction(function () use ($workflow_policy): void {
            $workflow_policy->steps()->delete();
            $workflow_policy->update(['deleted_by' => Auth::id()]);
            $workflow_policy->delete();
        });

        return response()->json([
            'success' => true,
            'message' => localize('data_delete', 'Deleted successfully'),
        ]);
    }

    public function preview(Request $request, WorkflowPolicyService $workflowPolicyService): JsonResponse
    {
        $validated = $request->validate([
            'module_key' => ['required', 'string', 'max:64'],
            'request_type_key' => ['required', 'string', 'max:64'],
            'days' => ['nullable', 'numeric', 'min:0'],
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

        return response()->json([
            'success' => true,
            'message' => localize('workflow_policy_found', 'Workflow policy matched successfully.'),
            'data' => $plan,
        ]);
    }

    protected function validatePayload(Request $request): array
    {
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
            'steps.*.org_role' => ['required', Rule::in(UserOrgRole::roleOptions())],
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
                return [
                    'step_order' => (int) $step['step_order'],
                    'step_key' => !empty($step['step_key']) ? trim((string) $step['step_key']) : null,
                    'step_name' => trim((string) $step['step_name']),
                    'action_type' => trim((string) $step['action_type']),
                    'org_role' => trim((string) $step['org_role']),
                    'scope_type' => trim((string) $step['scope_type']),
                    'is_final_approval' => (bool) $step['is_final_approval'],
                    'is_required' => (bool) $step['is_required'],
                    'can_return' => (bool) $step['can_return'],
                    'can_reject' => (bool) $step['can_reject'],
                ];
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

        return [
            'module_key' => trim(mb_strtolower((string) $validated['module_key'])),
            'request_type_key' => trim(mb_strtolower((string) $validated['request_type_key'])),
            'name' => trim((string) $validated['name']),
            'description' => !empty($validated['description']) ? trim((string) $validated['description']) : null,
            'priority' => (int) $validated['priority'],
            'is_active' => (bool) $validated['is_active'],
            'condition_json' => $conditionJson,
            'steps' => $steps->all(),
        ];
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

    protected function orgRoleLabels(): array
    {
        return [
            UserOrgRole::ROLE_HEAD => localize('org_role_head', 'ប្រធាន'),
            UserOrgRole::ROLE_DEPUTY_HEAD => localize('org_role_deputy_head', 'អនុប្រធាន'),
            UserOrgRole::ROLE_MANAGER => localize('org_role_manager', 'ប្រធានការិយាល័យ/អ្នកគ្រប់គ្រង'),
        ];
    }

    protected function scopeTypeLabels(): array
    {
        return [
            UserOrgRole::SCOPE_SELF => localize('scope_self', 'តែអង្គភាពខ្លួន'),
            UserOrgRole::SCOPE_SELF_AND_CHILDREN => localize('scope_self_and_children', 'អង្គភាពខ្លួន និងអង្គភាពរង'),
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
}
