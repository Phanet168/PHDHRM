<?php

namespace Modules\HumanResource\Http\Controllers;

use App\Models\User;
use Brian2694\Toastr\Facades\Toastr;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\HumanResource\Entities\Notice;
use Modules\HumanResource\Entities\WorkflowInstance;
use Modules\HumanResource\Entities\WorkflowInstanceAction;
use Modules\HumanResource\Entities\WorkflowDefinitionStep;
use Modules\HumanResource\Support\NoticeDispatchService;
use Modules\HumanResource\Support\OrgHierarchyAccessService;
use Modules\HumanResource\Support\OrgUnitRuleService;
use Modules\HumanResource\Support\WorkflowActorResolverService;
use Modules\HumanResource\Support\WorkflowPolicyService;
use Spatie\Permission\Models\Role;

class NoticeController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:read_notice')->only(['index']);
        $this->middleware('permission:create_notice', ['only' => ['create', 'store']]);
        $this->middleware('permission:update_notice', ['only' => ['edit', 'update', 'submit', 'approve', 'reject', 'send']]);
        $this->middleware('permission:delete_notice', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index()
    {
        $dbData = Notice::query()
            ->with(['workflowInstance.definition.steps.systemRole'])
            ->withoutGlobalScope('sortByLatest')
            ->orderByDesc('notice_date')
            ->orderByDesc('id')
            ->get();

        $users = User::query()
            ->withoutGlobalScope('sortByLatest')
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'email']);

        $roles = Role::query()->orderBy('name')->get(['id', 'name']);

        $departments = app(OrgUnitRuleService::class)->hierarchyOptions();
        $currentUser = Auth::user();
        $canApproveMap = [];
        foreach ($dbData as $notice) {
            $canApproveMap[(int) $notice->id] = $this->canCurrentUserApproveNotice($notice, $currentUser);
        }

        return view('humanresource::notice.index', compact('dbData', 'users', 'roles', 'departments', 'canApproveMap'));
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create()
    {
        return view('humanresource::notice.create');
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        $request->merge([
            'notice_date' => $this->normalizeNoticeDate($request->input('notice_date')),
            'scheduled_at' => $this->normalizeNoticeDateTime($request->input('scheduled_at')),
        ]);

        $validated = $request->validate($this->rules());

        $payload = $this->buildPayload($request, $validated, null);
        $payload['notice_attachment'] = $request->hasFile('notice_attachment')
            ? $this->storeAttachment($request)
            : '';

        $notice = Notice::create($payload);
        $this->syncNoticeWorkflowAfterSave($notice, Auth::id());

        return redirect()->route('notice.index')->with('success', localize('data_save'));
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        return view('humanresource::notice.edit');
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(Request $request, Notice $notice)
    {
        $request->merge([
            'notice_date' => $this->normalizeNoticeDate($request->input('notice_date')),
            'scheduled_at' => $this->normalizeNoticeDateTime($request->input('scheduled_at')),
        ]);

        $validated = $request->validate($this->rules());

        $payload = $this->buildPayload($request, $validated, $notice);

        if ($request->hasFile('notice_attachment')) {
            $payload['notice_attachment'] = $this->storeAttachment($request);
        }

        $notice->update($payload);
        $this->syncNoticeWorkflowAfterSave($notice->fresh(), Auth::id());

        return redirect()->route('notice.index')->with('update', localize('data_update'));
    }

    public function submit(Notice $notice)
    {
        if (!in_array((string) $notice->status, [Notice::STATUS_DRAFT, Notice::STATUS_REJECTED], true)) {
            Toastr::warning(localize('notice_can_not_submit_now', 'This notice cannot be submitted from current status.'));
            return redirect()->route('notice.index');
        }

        $notice->update([
            'status' => Notice::STATUS_PENDING_APPROVAL,
            'workflow_status' => 'pending',
            'rejected_by' => null,
            'rejected_at' => null,
            'rejected_reason' => null,
        ]);
        $this->initializeNoticeWorkflow($notice->fresh(), Auth::id(), true);

        Toastr::success(localize('notice_submitted_for_approval', 'Notice submitted for approval.'));

        return redirect()->route('notice.index');
    }

    public function approve(Notice $notice, NoticeDispatchService $dispatchService)
    {
        if ((string) $notice->status !== Notice::STATUS_PENDING_APPROVAL) {
            Toastr::warning(localize('notice_not_pending_for_approval', 'This notice is not pending for approval.'));
            return redirect()->route('notice.index');
        }

        $result = $this->applyNoticeWorkflowDecision($notice, Auth::user(), 'approve');
        if (!($result['ok'] ?? false)) {
            Toastr::warning((string) ($result['message'] ?? localize('permission_denied', 'Permission denied.')));
            return redirect()->route('notice.index');
        }

        $notice = $notice->fresh();
        $isFinalApproval = (bool) ($result['final'] ?? false);
        if ($isFinalApproval && $notice && (string) $notice->status === Notice::STATUS_APPROVED && $notice->sent_at === null) {
            $summary = $this->dispatchNotice($notice, $dispatchService);
            if (($summary['failed'] ?? 0) > 0) {
                Toastr::warning(localize('notice_sent_partial', 'Notice sent with some failures.'));
            } else {
                Toastr::success(localize('notice_sent_successfully', 'Notice sent successfully.'));
            }
        }

        Toastr::success(
            ($result['final'] ?? false)
                ? localize('notice_approved_successfully', 'Notice approved successfully.')
                : localize('notice_sent_to_next_approver', 'Notice sent to next approver.')
        );

        return redirect()->route('notice.index');
    }

    public function reject(Request $request, Notice $notice)
    {
        $request->validate([
            'rejected_reason' => ['required', 'string', 'max:2000'],
        ]);

        if ((string) $notice->status !== Notice::STATUS_PENDING_APPROVAL) {
            Toastr::warning(localize('notice_not_pending_for_approval', 'This notice is not pending for approval.'));
            return redirect()->route('notice.index');
        }

        $result = $this->applyNoticeWorkflowDecision(
            $notice,
            Auth::user(),
            'reject',
            (string) $request->input('rejected_reason')
        );
        if (!($result['ok'] ?? false)) {
            Toastr::warning((string) ($result['message'] ?? localize('permission_denied', 'Permission denied.')));
            return redirect()->route('notice.index');
        }

        Toastr::success(localize('notice_rejected_successfully', 'Notice rejected successfully.'));

        return redirect()->route('notice.index');
    }

    public function send(Notice $notice, NoticeDispatchService $dispatchService)
    {
        if (!in_array((string) $notice->status, [Notice::STATUS_APPROVED, Notice::STATUS_SCHEDULED, Notice::STATUS_PARTIAL_FAILED], true)) {
            Toastr::warning(localize('notice_not_ready_to_send', 'Please approve notice before sending.'));
            return redirect()->route('notice.index');
        }

        $summary = $this->dispatchNotice($notice, $dispatchService);

        if (($summary['failed'] ?? 0) > 0) {
            Toastr::warning(localize('notice_sent_partial', 'Notice sent with some failures.'));
        } else {
            Toastr::success(localize('notice_sent_successfully', 'Notice sent successfully.'));
        }

        return redirect()->route('notice.index');
    }

    private function dispatchNotice(Notice $notice, NoticeDispatchService $dispatchService): array
    {
        $summary = $dispatchService->deliver($notice);

        $status = Notice::STATUS_SENT;
        if (($summary['failed'] ?? 0) > 0) {
            $status = Notice::STATUS_PARTIAL_FAILED;
        }

        $notice->update([
            'status' => $status,
            'sent_by' => Auth::id(),
            'sent_at' => now(),
            'delivery_total' => (int) ($summary['total'] ?? 0),
            'delivery_success' => (int) ($summary['success'] ?? 0),
            'delivery_failed' => (int) ($summary['failed'] ?? 0),
            'delivery_last_error' => $summary['last_error'] ?? null,
        ]);

        return $summary;
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy(Notice $notice)
    {
        $notice->delete();
        Toastr::success('Notice Deleted successfully :)', 'Success');
        return response()->json(['success' => 'success']);
    }

    private function syncNoticeWorkflowAfterSave(Notice $notice, ?int $actorId = null): void
    {
        $status = (string) ($notice->status ?? Notice::STATUS_DRAFT);

        if (in_array($status, [Notice::STATUS_DRAFT, Notice::STATUS_REJECTED], true)) {
            $notice->update([
                'workflow_status' => $status === Notice::STATUS_REJECTED ? 'rejected' : 'draft',
                'workflow_current_step_order' => null,
                'workflow_last_action_at' => now(),
            ]);
            return;
        }

        if ($status === Notice::STATUS_PENDING_APPROVAL) {
            $this->initializeNoticeWorkflow($notice, $actorId, false);
        }
    }

    private function initializeNoticeWorkflow(Notice $notice, ?int $actorId = null, bool $reset = false): void
    {
        if ($notice->workflow_instance_id && !$reset) {
            return;
        }

        if ($reset && $notice->workflow_instance_id) {
            WorkflowInstance::query()
                ->where('id', (int) $notice->workflow_instance_id)
                ->delete();

            $notice->update([
                'workflow_instance_id' => null,
                'workflow_current_step_order' => null,
            ]);
            $notice = $notice->fresh();
        }

        $context = $this->buildNoticeWorkflowContext($notice);
        $plan = $this->workflowPolicyService()->resolveAndBuild(
            'notice',
            'notice_general',
            $context
        );

        if (!$plan || empty($plan['steps'])) {
            $notice->update([
                'workflow_status' => 'pending',
                'workflow_current_step_order' => null,
                'workflow_last_action_at' => now(),
                'workflow_snapshot_json' => null,
            ]);
            return;
        }

        DB::transaction(function () use ($notice, $context, $plan, $actorId): void {
            $steps = collect((array) ($plan['steps'] ?? []))->sortBy('step_order')->values();
            $firstStep = $steps->first();
            $submittedBy = $actorId ?: Auth::id();

            $instance = WorkflowInstance::create([
                'module_key' => 'notice',
                'request_type_key' => 'notice_general',
                'source_type' => Notice::class,
                'source_id' => (int) $notice->id,
                'workflow_definition_id' => (int) ($plan['definition_id'] ?? 0),
                'status' => 'pending',
                'current_step_order' => (int) ($firstStep['step_order'] ?? 1),
                'submitted_by' => $submittedBy,
                'submitted_at' => now(),
                'context_json' => $context,
            ]);

            WorkflowInstanceAction::create([
                'workflow_instance_id' => (int) $instance->id,
                'step_order' => 0,
                'action_type' => 'submit',
                'action_status' => 'submitted',
                'acted_by' => $submittedBy,
                'acted_at' => now(),
                'decision_note' => localize('notice_submitted_for_approval', 'Notice submitted for approval.'),
                'payload_json' => [
                    'notice_id' => (int) $notice->id,
                    'audience_type' => (string) $notice->audience_type,
                ],
            ]);

            $notice->update([
                'workflow_instance_id' => (int) $instance->id,
                'workflow_status' => 'pending',
                'workflow_current_step_order' => (int) ($firstStep['step_order'] ?? 1),
                'workflow_last_action_at' => now(),
                'workflow_snapshot_json' => $plan,
            ]);
        });
    }

    private function canCurrentUserApproveNotice(Notice $notice, ?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ((string) $notice->status !== Notice::STATUS_PENDING_APPROVAL) {
            return false;
        }

        if ($this->orgHierarchyAccessService()->isSystemAdmin($user)) {
            return true;
        }

        $instance = $notice->workflowInstance;
        if (!$instance || !$instance->definition) {
            return $user->can('update_notice');
        }

        $step = $this->resolveCurrentNoticeStep($instance);
        if (!$step) {
            return false;
        }

        return $this->canUserActOnNoticeStep($user, $notice, $step);
    }

    private function resolveCurrentNoticeStep(WorkflowInstance $instance): ?WorkflowDefinitionStep
    {
        $steps = $instance->definition?->steps;
        if (!$steps || $steps->isEmpty()) {
            return null;
        }

        $order = (int) ($instance->current_step_order ?? 0);
        if ($order <= 0) {
            return $steps->sortBy('step_order')->first();
        }

        return $steps->firstWhere('step_order', $order);
    }

    private function resolveNextNoticeStep(WorkflowInstance $instance, int $currentOrder): ?WorkflowDefinitionStep
    {
        $steps = $instance->definition?->steps;
        if (!$steps || $steps->isEmpty()) {
            return null;
        }

        return $steps
            ->filter(fn ($step) => (int) $step->step_order > $currentOrder)
            ->sortBy('step_order')
            ->first();
    }

    private function canUserActOnNoticeStep(User $user, Notice $notice, WorkflowDefinitionStep $step): bool
    {
        if ($this->orgHierarchyAccessService()->isSystemAdmin($user)) {
            return true;
        }

        $context = (array) ($notice->workflowInstance?->context_json ?? []);
        $sourceDepartmentId = (int) ($context['department_id'] ?? 0);
        if ($sourceDepartmentId <= 0) {
            $sourceDepartmentId = $this->inferNoticeDepartmentId($notice);
        }

        return $this->workflowActorResolverService()->canUserActOnStep($user, $step, $sourceDepartmentId);
    }

    private function applyNoticeWorkflowDecision(
        Notice $notice,
        ?User $actor,
        string $decision,
        ?string $note = null
    ): array {
        if (!$actor) {
            return [
                'ok' => false,
                'message' => localize('authentication_required', 'Authentication required.'),
            ];
        }

        if ((string) $notice->status !== Notice::STATUS_PENDING_APPROVAL) {
            return [
                'ok' => false,
                'message' => localize('notice_not_pending_for_approval', 'This notice is not pending for approval.'),
            ];
        }

        if (!$notice->workflow_instance_id) {
            $this->initializeNoticeWorkflow($notice, (int) $notice->created_by, false);
            $notice = $notice->fresh(['workflowInstance.definition.steps']);
        }

        $instance = $notice->workflowInstance;
        $step = $instance ? $this->resolveCurrentNoticeStep($instance) : null;

        if ($instance && $step && !$this->canUserActOnNoticeStep($actor, $notice, $step)) {
            return [
                'ok' => false,
                'message' => localize('not_allowed_for_current_step', 'You are not allowed to approve this step.'),
            ];
        }

        // Fallback: legacy no-workflow behavior.
        if (!$instance || !$step) {
            if ($decision === 'reject') {
                $notice->update([
                    'status' => Notice::STATUS_REJECTED,
                    'workflow_status' => 'rejected',
                    'rejected_by' => Auth::id(),
                    'rejected_at' => now(),
                    'rejected_reason' => $note,
                ]);

                return ['ok' => true, 'final' => true];
            }

            $targetStatus = Notice::STATUS_APPROVED;
            if ($notice->scheduled_at && Carbon::parse((string) $notice->scheduled_at)->isFuture()) {
                $targetStatus = Notice::STATUS_SCHEDULED;
            }

            $notice->update([
                'status' => $targetStatus,
                'workflow_status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            return ['ok' => true, 'final' => true];
        }

        if ($decision === 'reject') {
            DB::transaction(function () use ($notice, $instance, $step, $actor, $note): void {
                WorkflowInstanceAction::create([
                    'workflow_instance_id' => (int) $instance->id,
                    'step_order' => (int) $step->step_order,
                    'action_type' => 'reject',
                    'action_status' => 'rejected',
                    'acted_by' => (int) $actor->id,
                    'acted_at' => now(),
                    'decision_note' => $note,
                ]);

                $instance->update([
                    'status' => 'rejected',
                    'finalized_at' => now(),
                ]);

                $notice->update([
                    'status' => Notice::STATUS_REJECTED,
                    'workflow_status' => 'rejected',
                    'workflow_current_step_order' => (int) $step->step_order,
                    'workflow_last_action_at' => now(),
                    'rejected_by' => (int) $actor->id,
                    'rejected_at' => now(),
                    'rejected_reason' => $note,
                ]);
            });

            return ['ok' => true, 'final' => true];
        }

        $nextStep = $this->resolveNextNoticeStep($instance, (int) $step->step_order);
        $isFinal = (bool) $step->is_final_approval || !$nextStep;
        $actionType = (string) ($step->action_type ?: ($isFinal ? 'approve' : 'recommend'));
        $actionStatus = $actionType === 'recommend' ? 'recommended' : 'approved';

        DB::transaction(function () use ($notice, $instance, $step, $nextStep, $isFinal, $actionType, $actionStatus, $actor): void {
            WorkflowInstanceAction::create([
                'workflow_instance_id' => (int) $instance->id,
                'step_order' => (int) $step->step_order,
                'action_type' => $actionType,
                'action_status' => $actionStatus,
                'acted_by' => (int) $actor->id,
                'acted_at' => now(),
                'decision_note' => localize('approved', 'Approved'),
            ]);

            if ($isFinal) {
                $targetStatus = Notice::STATUS_APPROVED;
                if ($notice->scheduled_at && Carbon::parse((string) $notice->scheduled_at)->isFuture()) {
                    $targetStatus = Notice::STATUS_SCHEDULED;
                }

                $instance->update([
                    'status' => 'approved',
                    'current_step_order' => (int) $step->step_order,
                    'finalized_at' => now(),
                ]);

                $notice->update([
                    'status' => $targetStatus,
                    'workflow_status' => 'approved',
                    'workflow_current_step_order' => (int) $step->step_order,
                    'workflow_last_action_at' => now(),
                    'approved_by' => (int) $actor->id,
                    'approved_at' => now(),
                    'rejected_by' => null,
                    'rejected_at' => null,
                    'rejected_reason' => null,
                ]);
            } else {
                $instance->update([
                    'status' => 'pending',
                    'current_step_order' => (int) $nextStep->step_order,
                ]);

                $notice->update([
                    'status' => Notice::STATUS_PENDING_APPROVAL,
                    'workflow_status' => 'pending',
                    'workflow_current_step_order' => (int) $nextStep->step_order,
                    'workflow_last_action_at' => now(),
                ]);
            }
        });

        return ['ok' => true, 'final' => $isFinal];
    }

    private function buildNoticeWorkflowContext(Notice $notice): array
    {
        return [
            'department_id' => $this->inferNoticeDepartmentId($notice),
            'audience_type' => (string) $notice->audience_type,
        ];
    }

    private function inferNoticeDepartmentId(Notice $notice): int
    {
        if ((string) $notice->audience_type === Notice::AUDIENCE_DEPARTMENTS) {
            $targetDepartments = array_map('intval', (array) ($notice->audience_departments ?? []));
            if (!empty($targetDepartments)) {
                return (int) $targetDepartments[0];
            }
        }

        $creator = User::query()
            ->with(['primaryActiveAssignment:id,user_id,department_id'])
            ->find((int) $notice->created_by);
        if (!$creator) {
            return 0;
        }

        $canonicalDepartmentId = (int) ($creator->primaryActiveAssignment?->department_id ?? 0);
        if ($canonicalDepartmentId > 0) {
            return $canonicalDepartmentId;
        }

        $role = $this->orgHierarchyAccessService()->effectiveOrgRoles($creator)->first();
        return (int) ($role->department_id ?? 0);
    }

    private function workflowPolicyService(): WorkflowPolicyService
    {
        return app(WorkflowPolicyService::class);
    }

    private function workflowActorResolverService(): WorkflowActorResolverService
    {
        return app(WorkflowActorResolverService::class);
    }

    private function orgHierarchyAccessService(): OrgHierarchyAccessService
    {
        return app(OrgHierarchyAccessService::class);
    }

    private function orgUnitRuleService(): OrgUnitRuleService
    {
        return app(OrgUnitRuleService::class);
    }

    private function normalizeNoticeDate(?string $input): ?string
    {
        if (empty($input)) {
            return null;
        }

        $value = trim($input);
        $formats = ['d/m/Y', 'd-m-Y', 'Y-m-d'];

        foreach ($formats as $format) {
            try {
                $dt = Carbon::createFromFormat($format, $value);
                if ($dt && $dt->format($format) === $value) {
                    return $dt->format('Y-m-d');
                }
            } catch (\Throwable $e) {
                // Try next format.
            }
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function normalizeNoticeDateTime(?string $input): ?string
    {
        if (empty($input)) {
            return null;
        }

        $value = trim($input);
        $formats = ['Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i', 'd/m/Y H:i'];

        foreach ($formats as $format) {
            try {
                $dt = Carbon::createFromFormat($format, $value);
                if ($dt && $dt->format($format) === $value) {
                    return $dt->format('Y-m-d H:i:s');
                }
            } catch (\Throwable $e) {
                // Try next format.
            }
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function rules(): array
    {
        return [
            'notice_type' => ['required', 'string', 'max:255'],
            'notice_descriptiion' => ['required', 'string'],
            'notice_date' => ['required', 'date_format:Y-m-d'],
            'notice_by' => ['required', 'string', 'max:255'],
            'notice_attachment' => ['nullable', 'file', 'max:10240'],
            'audience_type' => ['required', Rule::in(Notice::audienceTypes())],
            'audience_users' => ['nullable', 'array'],
            'audience_users.*' => ['integer', 'exists:users,id'],
            'audience_roles' => ['nullable', 'array'],
            'audience_roles.*' => ['integer', 'exists:roles,id'],
            'audience_departments' => ['nullable', 'array'],
            'audience_departments.*' => ['integer', 'exists:departments,id'],
            'delivery_channels' => ['nullable', 'array'],
            'delivery_channels.*' => ['string', Rule::in(['in_app', 'telegram'])],
            'scheduled_at' => ['nullable', 'date_format:Y-m-d H:i:s'],
            'workflow_action' => ['nullable', Rule::in(['draft', 'submit'])],
        ];
    }

    private function buildPayload(Request $request, array $validated, ?Notice $notice): array
    {
        $audienceType = (string) ($validated['audience_type'] ?? Notice::AUDIENCE_ALL);
        $targets = [];

        if ($audienceType === Notice::AUDIENCE_USERS) {
            $targets['users'] = $this->sanitizeIntegerArray($request->input('audience_users', []));
        } elseif ($audienceType === Notice::AUDIENCE_ROLES) {
            $targets['roles'] = $this->sanitizeIntegerArray($request->input('audience_roles', []));
        } elseif ($audienceType === Notice::AUDIENCE_DEPARTMENTS) {
            $targets['departments'] = $this->sanitizeIntegerArray($request->input('audience_departments', []));
        }

        $channels = array_values(array_unique(array_filter((array) $request->input('delivery_channels', []))));
        if (empty($channels)) {
            $channels = ['in_app'];
        }

        $status = $notice ? (string) $notice->status : Notice::STATUS_DRAFT;
        $workflowAction = (string) $request->input('workflow_action', 'draft');

        if ($workflowAction === 'submit') {
            $status = Notice::STATUS_PENDING_APPROVAL;
        } elseif ($workflowAction === 'draft') {
            $status = Notice::STATUS_DRAFT;
        }

        $resetWorkflowMeta = in_array($status, [Notice::STATUS_DRAFT, Notice::STATUS_PENDING_APPROVAL], true);

        return [
            'notice_type' => $validated['notice_type'],
            'notice_descriptiion' => $validated['notice_descriptiion'],
            'notice_date' => $validated['notice_date'],
            'notice_by' => $validated['notice_by'],
            'status' => $status,
            'audience_type' => $audienceType,
            'audience_targets' => $targets,
            'delivery_channels' => $channels,
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'approved_by' => $resetWorkflowMeta ? null : ($notice?->approved_by),
            'approved_at' => $resetWorkflowMeta ? null : ($notice?->approved_at),
            'rejected_by' => $resetWorkflowMeta ? null : ($notice?->rejected_by),
            'rejected_at' => $resetWorkflowMeta ? null : ($notice?->rejected_at),
            'rejected_reason' => $resetWorkflowMeta ? null : ($notice?->rejected_reason),
            'sent_by' => $resetWorkflowMeta ? null : ($notice?->sent_by),
            'sent_at' => $resetWorkflowMeta ? null : ($notice?->sent_at),
            'delivery_total' => $resetWorkflowMeta ? 0 : ((int) ($notice?->delivery_total ?? 0)),
            'delivery_success' => $resetWorkflowMeta ? 0 : ((int) ($notice?->delivery_success ?? 0)),
            'delivery_failed' => $resetWorkflowMeta ? 0 : ((int) ($notice?->delivery_failed ?? 0)),
            'delivery_last_error' => $resetWorkflowMeta ? null : ($notice?->delivery_last_error),
        ];
    }

    private function storeAttachment(Request $request): string
    {
        $requestFile = $request->file('notice_attachment');
        $filename = time() . rand(10, 1000) . '.' . $requestFile->extension();

        return $requestFile->storeAs('notice', $filename, 'public');
    }

    private function sanitizeIntegerArray(array $values): array
    {
        return collect($values)
            ->map(fn ($item) => (int) $item)
            ->filter(fn ($item) => $item > 0)
            ->unique()
            ->values()
            ->all();
    }
}
