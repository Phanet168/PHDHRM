<?php

namespace Modules\HumanResource\Http\Controllers;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\HumanResource\Entities\Position;
use Modules\HumanResource\Entities\ResponsibilityTemplate;
use Modules\HumanResource\Entities\SystemRole;
use Modules\HumanResource\Entities\UserAssignment;
use Modules\HumanResource\Services\ResponsibilityTemplateService;

class ResponsibilityTemplateController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:read_org_governance|read_department')->only(['index']);
        $this->middleware('permission:create_org_governance|create_department')->only(['store']);
        $this->middleware('permission:update_org_governance|update_department')->only(['update']);
        $this->middleware('permission:delete_org_governance|delete_department')->only(['destroy']);
    }

    public function index(Request $request, ResponsibilityTemplateService $templateService)
    {
        $selectedModule = trim((string) $request->query('module_key', ''));
        $selectedStatus = (string) $request->query('is_active', '');

        $records = ResponsibilityTemplate::query()
            ->withoutGlobalScope('sortByLatest')
            ->with([
                'position:id,position_name,position_name_km',
                'responsibility:id,code,name,name_km',
            ])
            ->when($selectedModule !== '', fn ($q) => $q->where('module_key', $selectedModule))
            ->when($selectedStatus === '1' || $selectedStatus === '0', fn ($q) => $q->where('is_active', (int) $selectedStatus))
            ->orderBy('module_key')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $positions = Position::query()
            ->withoutGlobalScope('sortByLatest')
            ->where('is_active', 1)
            ->orderBy('position_name')
            ->get(['id', 'position_name', 'position_name_km']);
        $responsibilities = SystemRole::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'code', 'name', 'name_km']);

        $moduleActionMap = $templateService->moduleActionMap();
        $moduleOptions = collect($templateService->moduleOptions())
            ->merge($records->pluck('module_key')->values())
            ->filter()
            ->unique()
            ->values()
            ->all();

        return view('humanresource::master-data.responsibility-templates.index', [
            'records' => $records,
            'positions' => $positions,
            'responsibilities' => $responsibilities,
            'module_options' => $moduleOptions,
            'module_action_map' => $moduleActionMap,
            'scope_options' => UserAssignment::scopeOptions(),
            'scope_labels' => $this->scopeLabels(),
            'action_labels' => array_merge($this->actionLabels(), $templateService->actionLabels()),
            'selected_module' => $selectedModule,
            'selected_status' => $selectedStatus,
        ]);
    }

    public function store(Request $request, ResponsibilityTemplateService $templateService)
    {
        $validated = $this->validatedPayload($request, null, $templateService);

        ResponsibilityTemplate::create(array_merge($validated, [
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]));

        Toastr::success(localize('data_save', 'Data saved'));
        return redirect()->route('responsibility-templates.index');
    }

    public function update(
        Request $request,
        ResponsibilityTemplate $responsibility_template,
        ResponsibilityTemplateService $templateService
    ) {
        $validated = $this->validatedPayload($request, $responsibility_template, $templateService);

        $responsibility_template->update(array_merge($validated, [
            'updated_by' => auth()->id(),
        ]));

        Toastr::success(localize('data_update', 'Data updated'));
        return redirect()->route('responsibility-templates.index');
    }

    public function destroy(ResponsibilityTemplate $responsibility_template): JsonResponse
    {
        if ((bool) $responsibility_template->is_system) {
            return response()->json([
                'success' => false,
                'message' => localize(
                    'cannot_delete_system_template',
                    'System template cannot be deleted. You can deactivate it instead.'
                ),
            ], 422);
        }

        $hasAssignments = $responsibility_template->userAssignments()
            ->whereNull('deleted_at')
            ->exists();
        if ($hasAssignments) {
            return response()->json([
                'success' => false,
                'message' => localize(
                    'template_in_use_cannot_delete',
                    'Template is currently used by user assignments and cannot be deleted.'
                ),
            ], 422);
        }

        $responsibility_template->deleted_by = auth()->id();
        $responsibility_template->save();
        $responsibility_template->delete();

        return response()->json([
            'success' => true,
            'message' => localize('data_delete', 'Deleted successfully'),
        ]);
    }

    protected function validatedPayload(
        Request $request,
        ?ResponsibilityTemplate $editing,
        ResponsibilityTemplateService $templateService
    ): array {
        $moduleActionMap = $templateService->moduleActionMap();
        $moduleOptions = array_keys($moduleActionMap);

        $validated = $request->validate([
            'module_key' => ['required', 'string', 'max:64', Rule::in($moduleOptions)],
            'template_key' => [
                'required',
                'string',
                'max:80',
                'regex:/^[a-zA-Z0-9._-]+$/',
                Rule::unique('responsibility_templates', 'template_key')
                    ->where(function ($query) use ($request) {
                        $query->where('module_key', trim(mb_strtolower((string) $request->input('module_key'))));
                    })
                    ->ignore($editing?->id),
            ],
            'name' => ['required', 'string', 'max:190'],
            'name_km' => ['nullable', 'string', 'max:190'],
            // Position is placement metadata only; module rights are granted by
            // responsibility templates attached to canonical user assignments.
            'position_id' => ['nullable', 'integer', Rule::exists('positions', 'id')],
            'responsibility_id' => ['required', 'integer', Rule::exists('system_roles', 'id')->where('is_active', 1)],
            'action_presets' => ['nullable', 'array'],
            'action_presets.*' => ['nullable', 'string', 'max:64'],
            'default_scope_type' => ['required', Rule::in(UserAssignment::scopeOptions())],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['required', 'boolean'],
            'note' => ['nullable', 'string'],
        ]);

        $moduleKey = trim(mb_strtolower((string) $validated['module_key']));
        $actionPresets = $templateService->normalizeActionKeys((array) ($validated['action_presets'] ?? []));
        $allowedActions = (array) ($moduleActionMap[$moduleKey] ?? []);
        $invalidAction = collect($actionPresets)->first(fn ($action) => !in_array($action, $allowedActions, true));
        if (!empty($invalidAction)) {
            abort(422, localize('invalid_action_for_module', 'Invalid action for selected module.'));
        }

        return [
            'module_key' => $moduleKey,
            'template_key' => trim(mb_strtolower((string) $validated['template_key'])),
            'name' => trim((string) $validated['name']),
            'name_km' => !empty($validated['name_km']) ? trim((string) $validated['name_km']) : null,
            'position_id' => null,
            'responsibility_id' => (int) $validated['responsibility_id'],
            'action_presets_json' => $actionPresets,
            'default_scope_type' => trim((string) $validated['default_scope_type']),
            'sort_order' => (int) ($validated['sort_order'] ?? 100),
            'is_active' => (bool) $validated['is_active'],
            'note' => !empty($validated['note']) ? trim((string) $validated['note']) : null,
        ];
    }

    protected function scopeLabels(): array
    {
        return [
            UserAssignment::SCOPE_SELF_ONLY => localize('scope_self_only', 'Self only'),
            UserAssignment::SCOPE_SELF_UNIT_ONLY => localize('scope_self_unit_only', 'Self unit only'),
            UserAssignment::SCOPE_SELF_AND_CHILDREN => localize('scope_self_and_children', 'Self and children'),
            UserAssignment::SCOPE_ALL => localize('scope_all', 'All'),
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
}
