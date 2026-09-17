<?php

namespace Modules\HumanResource\Support;

use App\Models\User;
use App\Services\AccessControlService;
use Illuminate\Support\Facades\DB;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\Mission;
use Modules\HumanResource\Entities\MissionStatusHistory;
use Modules\HumanResource\Entities\WorkflowDefinitionStep;
use Modules\HumanResource\Entities\WorkflowInstance;
use Modules\HumanResource\Entities\WorkflowInstanceAction;
use Modules\HumanResource\Enums\Mission\MissionStatus;

/**
 * Drives the Flow A (employee request) approval workflow — office head endorsement then
 * director approval — on top of the shared workflow engine also used by Leave. Direct
 * missions (Flow B) never call initialize(); they skip straight to pending_mission_order.
 */
class MissionWorkflowService
{
    public function __construct(
        private readonly WorkflowPolicyService $policy,
        private readonly WorkflowActorResolverService $actorResolver,
        private readonly OrgHierarchyAccessService $hierarchy,
        private readonly AccessControlService $accessControlService,
    ) {
    }

    /**
     * Preview of who a Flow A request from $employeeId would route to, for display on the
     * request form before the mission (and its workflow instance) exists.
     * @return array<int, array{step_name: string, names: string[]}>
     */
    public function previewApprovalRoute(?int $employeeId): array
    {
        if (!$employeeId) {
            return [];
        }

        $employee = Employee::query()->withoutGlobalScopes()
            ->with(['primaryUnitPosting:employee_unit_postings.id,employee_unit_postings.employee_id,employee_unit_postings.department_id'])
            ->find($employeeId, ['id', 'department_id', 'sub_department_id']);
        if (!$employee) {
            return [];
        }

        $departmentId = (int) ($employee->primaryUnitPosting?->department_id
            ?: $employee->sub_department_id
            ?: $employee->department_id
            ?: 0);

        $context = ['employee_id' => $employeeId, 'department_id' => $departmentId];
        $plan = $this->policy->resolveAndBuild('mission', 'mission_request', $context);
        if (!$plan) {
            return [];
        }

        $preview = $this->actorResolver->previewPlan($plan, $context);
        $requesterUserId = (int) (Employee::find($employeeId)?->user_id ?? 0);

        // Exclude the requester's own name — they can never endorse/approve their own request,
        // so showing them as a "resolved candidate" here would be misleading.
        return collect($preview['steps'] ?? [])
            ->sortBy('step_order')
            ->map(fn (array $step) => [
                'step_name' => (string) ($step['step_name'] ?? ''),
                'names' => collect($step['resolved_candidates'] ?? [])
                    ->reject(fn ($c) => $requesterUserId > 0 && (int) ($c['user_id'] ?? 0) === $requesterUserId)
                    ->pluck('full_name')->filter()->values()->all(),
            ])
            ->values()
            ->all();
    }

    /** Resolved candidate names for the mission's current pending step, for display on the detail page. */
    public function currentStepApproverNames(Mission $mission): array
    {
        if (!$mission->workflow_snapshot_json) {
            return [];
        }

        $context = (array) ($mission->workflow_snapshot_json['context'] ?? [
            'employee_id' => $mission->requester_employee_id,
            'department_id' => $this->sourceDepartmentId($mission),
        ]);
        $preview = $this->actorResolver->previewPlan($mission->workflow_snapshot_json, $context);
        $order = (int) ($mission->workflow_current_step_order ?? 0);
        $step = collect($preview['steps'] ?? [])->firstWhere('step_order', $order);

        return $step ? collect($step['resolved_candidates'] ?? [])->pluck('full_name')->filter()->values()->all() : [];
    }

    public function sourceDepartmentId(Mission $mission): int
    {
        if ((int) ($mission->source_department_id ?? 0) > 0) {
            return (int) $mission->source_department_id;
        }

        $employeeId = (int) ($mission->requester_employee_id ?? 0);
        if ($employeeId <= 0) {
            return 0;
        }

        $employee = Employee::query()
            ->withoutGlobalScopes()
            ->with(['primaryUnitPosting:employee_unit_postings.id,employee_unit_postings.employee_id,employee_unit_postings.department_id'])
            ->find($employeeId, ['id', 'department_id', 'sub_department_id']);

        if (!$employee) {
            return 0;
        }

        $postingDepartmentId = (int) ($employee->primaryUnitPosting?->department_id ?? 0);
        if ($postingDepartmentId > 0) {
            return $postingDepartmentId;
        }

        $subDepartmentId = (int) ($employee->sub_department_id ?? 0);
        if ($subDepartmentId > 0) {
            return $subDepartmentId;
        }

        return (int) ($employee->department_id ?? 0);
    }

    /** Starts the Flow A workflow instance for a freshly created request; idempotent. */
    public function initialize(Mission $mission, ?int $submittedBy = null): void
    {
        if ($mission->workflow_instance_id) {
            return;
        }

        $departmentId = $this->sourceDepartmentId($mission);
        $context = [
            'employee_id' => $mission->requester_employee_id,
            'department_id' => $departmentId,
        ];
        $plan = $this->policy->resolveAndBuild('mission', 'mission_request', $context);
        $submittedBy = $submittedBy ?: (int) auth()->id();

        // If the requester is themselves the only resolved office-head candidate (or no office
        // head is configured for their department at all), endorsement is skipped and the
        // request starts directly at director approval — a requester can never self-approve.
        $startStep = null;
        if ($plan && !empty($plan['steps'])) {
            $preview = $this->actorResolver->previewPlan($plan, $context);
            $steps = collect($preview['steps'] ?? [])->sortBy('step_order')->values();
            $firstStep = $steps->first();
            $requesterUserId = (int) ($mission->requesterEmployee?->user_id ?? 0);

            if ($firstStep) {
                $candidateIds = collect($firstStep['resolved_candidates'] ?? [])
                    ->pluck('user_id')->map(fn ($v) => (int) $v)->filter()->values()->all();
                $onlyCandidateIsRequester = $requesterUserId > 0 && $candidateIds === [$requesterUserId];

                $startStep = (empty($candidateIds) || $onlyCandidateIsRequester)
                    ? $steps->first(fn ($s) => (int) $s['step_order'] > (int) $firstStep['step_order'])
                    : $firstStep;
            }
        }

        DB::transaction(function () use ($mission, $plan, $submittedBy, $departmentId, $startStep): void {
            $steps = collect((array) ($plan['steps'] ?? []))->sortBy('step_order')->values();
            $effectiveStartStep = $startStep ?? $steps->first();
            $firstOrder = (int) ($effectiveStartStep['step_order'] ?? 1);
            $skippedOfficeHead = $startStep && (int) $startStep['step_order'] !== (int) ($steps->first()['step_order'] ?? 1);

            $instanceId = null;
            if ($plan && $steps->isNotEmpty()) {
                $instance = WorkflowInstance::create([
                    'module_key' => 'mission',
                    'request_type_key' => 'mission_request',
                    'source_type' => Mission::class,
                    'source_id' => (int) $mission->id,
                    'workflow_definition_id' => (int) ($plan['definition_id'] ?? 0) ?: null,
                    'status' => 'pending',
                    'current_step_order' => $firstOrder,
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
                    'decision_note' => 'បានដាក់សំណើបេសកកម្ម',
                ]);

                $instanceId = (int) $instance->id;
            }

            $startLifecycleStatus = $skippedOfficeHead ? MissionStatus::PendingDirector : MissionStatus::PendingOfficeHead;

            // lifecycle_status is intentionally excluded from mass assignment; forceFill sets it explicitly.
            $mission->forceFill([
                'workflow_instance_id' => $instanceId,
                'workflow_status' => 'pending',
                'workflow_current_step_order' => $instanceId ? $firstOrder : null,
                'workflow_last_action_at' => now(),
                'workflow_snapshot_json' => $plan,
                'source_department_id' => $departmentId ?: $mission->source_department_id,
                'lifecycle_status' => $startLifecycleStatus,
            ])->save();

            if ($skippedOfficeHead) {
                $this->recordHistory($mission, null, MissionStatus::PendingOfficeHead, 'submit', $submittedBy, null);
                $this->recordHistory($mission, MissionStatus::PendingOfficeHead, MissionStatus::PendingDirector, 'skip_office_head', $submittedBy, 'អ្នកស្នើសុំគឺជាប្រធានការិយាល័យ ឬគ្មានប្រធានការិយាល័យផ្សេងទៀត');
            } else {
                $this->recordHistory($mission, null, MissionStatus::PendingOfficeHead, 'submit', $submittedBy, null);
            }
        });
    }

    protected function currentStep(WorkflowInstance $instance): ?WorkflowDefinitionStep
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

    protected function nextStep(WorkflowInstance $instance, int $currentOrder): ?WorkflowDefinitionStep
    {
        $steps = $instance->definition?->steps;
        if (!$steps || $steps->isEmpty()) {
            return null;
        }

        return $steps->filter(fn ($step) => (int) $step->step_order > $currentOrder)
            ->sortBy('step_order')->first();
    }

    protected function isRequester(Mission $mission, User $user): bool
    {
        $requesterUserId = (int) ($mission->requesterEmployee?->user_id ?? $mission->requested_by ?? 0);

        return $requesterUserId > 0 && $requesterUserId === (int) $user->id;
    }

    /**
     * Whether $user may endorse/approve/reject/return the mission's current
     * pending step. Phase 3D.2: centralized onto
     * AccessControlService::canApprove(), the same seam Leave/Notice now
     * use, so a Central Delegation actually works here too. canApprove()
     * already handles the system-admin bypass internally. Self-approval
     * prevention (isRequester) is Mission-domain business logic
     * canApprove() has no knowledge of, so it stays here, checked first,
     * exactly as before.
     */
    public function canAct(User $user, Mission $mission): bool
    {
        if ($this->isRequester($mission, $user)) {
            return false;
        }

        $instance = $mission->currentWorkflow()->with(['definition.steps'])->first();
        if (!$instance) {
            return false;
        }

        return $this->accessControlService->canApprove($user, $instance, $this->sourceDepartmentId($mission));
    }

    /**
     * @param 'endorse'|'approve'|'reject'|'return' $decision
     * @return array{ok: bool, final?: bool, message?: string}
     */
    public function decide(Mission $mission, string $decision, ?User $actor, ?string $note = null): array
    {
        $decision = trim(mb_strtolower($decision));
        if (!in_array($decision, ['endorse', 'approve', 'reject', 'return'], true)) {
            return ['ok' => false, 'message' => 'ការសម្រេចមិនត្រឹមត្រូវ។'];
        }

        if (!$actor) {
            return ['ok' => false, 'message' => 'តម្រូវឲ្យចូលប្រើប្រាស់សិន។'];
        }

        if ($this->isRequester($mission, $actor) && !$this->hierarchy->isSystemAdmin($actor)) {
            return ['ok' => false, 'message' => 'អ្នកមិនអាចសម្រេចលើសំណើផ្ទាល់ខ្លួនបានទេ។'];
        }

        $instance = $mission->currentWorkflow()->with(['definition.steps'])->first();
        if (!$instance || !$instance->definition) {
            // Misconfiguration safety net (e.g. the seeded WorkflowDefinition was disabled):
            // let a system administrator unstick the request instead of leaving it stranded.
            if ($this->hierarchy->isSystemAdmin($actor) && in_array($decision, ['approve', 'endorse', 'reject'], true)) {
                return $this->finalizeWithoutInstance($mission, $decision, $actor, $note);
            }

            return ['ok' => false, 'message' => 'មិនទាន់មានលំហូរអនុម័តសម្រាប់បេសកកម្មនេះទេ។'];
        }

        $currentStep = $this->currentStep($instance);
        if (!$currentStep) {
            return ['ok' => false, 'message' => 'រកមិនឃើញជំហានលំហូរការងារទេ។'];
        }

        if (!$this->accessControlService->canApprove($actor, $instance, $this->sourceDepartmentId($mission))) {
            return ['ok' => false, 'message' => 'អ្នកមិនមានសិទ្ធិសម្រេចជំហាននេះទេ។'];
        }

        if ($decision === 'reject' && !(bool) ($currentStep->can_reject ?? true)) {
            return ['ok' => false, 'message' => 'ជំហាននេះមិនអនុញ្ញាតឲ្យបដិសេធទេ។'];
        }
        if ($decision === 'return' && !(bool) ($currentStep->can_return ?? true)) {
            return ['ok' => false, 'message' => 'ជំហាននេះមិនអនុញ្ញាតឲ្យបញ្ជូនត្រឡប់ទេ។'];
        }

        if ($decision === 'reject') {
            return $this->applyReject($mission, $instance, $currentStep, $actor, $note);
        }

        if ($decision === 'return') {
            return $this->applyReturn($mission, $instance, $currentStep, $actor, $note);
        }

        return $this->applyAdvance($mission, $instance, $currentStep, $actor, $note);
    }

    /** System-admin recovery path when no workflow instance/definition is resolvable. */
    private function finalizeWithoutInstance(Mission $mission, string $decision, User $actor, ?string $note): array
    {
        DB::transaction(function () use ($mission, $decision, $actor, $note): void {
            $from = $mission->lifecycle_status;
            if ($decision === 'reject') {
                $mission->forceFill([
                    'status' => 'rejected',
                    'lifecycle_status' => MissionStatus::Rejected,
                    'workflow_status' => 'rejected',
                    'workflow_last_action_at' => now(),
                    'rejected_reason' => $note,
                ])->save();
                $this->recordHistory($mission, $from, MissionStatus::Rejected, 'reject', (int) $actor->id, $note);

                return;
            }

            $mission->forceFill([
                'status' => 'approved',
                'approved_by' => (int) $actor->id,
                'approved_at' => now(),
                'lifecycle_status' => MissionStatus::PendingMissionOrder,
                'workflow_status' => 'approved',
                'workflow_last_action_at' => now(),
            ])->save();
            $this->recordHistory($mission, $from, MissionStatus::DirectorApproved, 'approve', (int) $actor->id, $note);
            $this->recordHistory($mission, MissionStatus::DirectorApproved, MissionStatus::PendingMissionOrder, 'route_to_letter_manager', (int) $actor->id, null);
        });

        return ['ok' => true, 'final' => true];
    }

    private function applyReject(Mission $mission, WorkflowInstance $instance, WorkflowDefinitionStep $currentStep, User $actor, ?string $note): array
    {
        DB::transaction(function () use ($mission, $instance, $currentStep, $actor, $note): void {
            $action = WorkflowInstanceAction::create([
                'workflow_instance_id' => (int) $instance->id,
                'step_order' => (int) $currentStep->step_order,
                'action_type' => 'reject',
                'action_status' => 'rejected',
                'acted_by' => (int) $actor->id,
                'acted_at' => now(),
                'decision_note' => $note,
            ]);

            $instance->update(['status' => 'rejected', 'current_step_order' => (int) $currentStep->step_order, 'finalized_at' => now()]);

            $from = $mission->lifecycle_status;
            $mission->forceFill([
                'status' => 'rejected',
                'lifecycle_status' => MissionStatus::Rejected,
                'workflow_status' => 'rejected',
                'workflow_current_step_order' => (int) $currentStep->step_order,
                'workflow_last_action_at' => now(),
                'rejected_reason' => $note,
            ])->save();

            $this->recordHistory($mission, $from, MissionStatus::Rejected, 'reject', (int) $actor->id, $note, (int) $action->id);
        });

        return ['ok' => true, 'final' => true];
    }

    private function applyReturn(Mission $mission, WorkflowInstance $instance, WorkflowDefinitionStep $currentStep, User $actor, ?string $note): array
    {
        DB::transaction(function () use ($mission, $instance, $currentStep, $actor, $note): void {
            $action = WorkflowInstanceAction::create([
                'workflow_instance_id' => (int) $instance->id,
                'step_order' => (int) $currentStep->step_order,
                'action_type' => 'return',
                'action_status' => 'returned',
                'acted_by' => (int) $actor->id,
                'acted_at' => now(),
                'decision_note' => $note,
            ]);

            $instance->update(['status' => 'returned', 'current_step_order' => (int) $currentStep->step_order]);

            $from = $mission->lifecycle_status;
            $mission->forceFill([
                'lifecycle_status' => MissionStatus::ReturnedForCorrection,
                'workflow_status' => 'returned',
                'workflow_current_step_order' => (int) $currentStep->step_order,
                'workflow_last_action_at' => now(),
                'rejected_reason' => $note,
            ])->save();

            $this->recordHistory($mission, $from, MissionStatus::ReturnedForCorrection, 'return_for_correction', (int) $actor->id, $note, (int) $action->id);
        });

        return ['ok' => true, 'final' => false];
    }

    private function applyAdvance(Mission $mission, WorkflowInstance $instance, WorkflowDefinitionStep $currentStep, User $actor, ?string $note): array
    {
        $nextStep = $this->nextStep($instance, (int) $currentStep->step_order);
        $isFinal = (bool) $currentStep->is_final_approval || !$nextStep;
        $actionType = $isFinal ? 'approve' : 'recommend';
        $actionStatus = $isFinal ? 'approved' : 'recommended';

        DB::transaction(function () use ($mission, $instance, $currentStep, $nextStep, $isFinal, $actionType, $actionStatus, $actor, $note): void {
            $action = WorkflowInstanceAction::create([
                'workflow_instance_id' => (int) $instance->id,
                'step_order' => (int) $currentStep->step_order,
                'action_type' => $actionType,
                'action_status' => $actionStatus,
                'acted_by' => (int) $actor->id,
                'acted_at' => now(),
                'decision_note' => $note,
            ]);

            $from = $mission->lifecycle_status;

            if ($isFinal) {
                $instance->update(['status' => 'approved', 'current_step_order' => (int) $currentStep->step_order, 'finalized_at' => now()]);

                $mission->forceFill([
                    // Bridges to the legacy status Attendance's mission exemption still reads.
                    'status' => 'approved',
                    'approved_by' => (int) $actor->id,
                    'approved_at' => now(),
                    'lifecycle_status' => MissionStatus::PendingMissionOrder,
                    'workflow_status' => 'approved',
                    'workflow_current_step_order' => (int) $currentStep->step_order,
                    'workflow_last_action_at' => now(),
                ])->save();

                $this->recordHistory($mission, $from, MissionStatus::DirectorApproved, 'approve', (int) $actor->id, $note, (int) $action->id);
                $this->recordHistory($mission, MissionStatus::DirectorApproved, MissionStatus::PendingMissionOrder, 'route_to_letter_manager', (int) $actor->id, null);
            } else {
                $instance->update(['status' => 'pending', 'current_step_order' => (int) $nextStep->step_order]);

                $mission->forceFill([
                    'lifecycle_status' => MissionStatus::PendingDirector,
                    'workflow_status' => 'pending',
                    'workflow_current_step_order' => (int) $nextStep->step_order,
                    'workflow_last_action_at' => now(),
                ])->save();

                $this->recordHistory($mission, $from, MissionStatus::OfficeHeadEndorsed, 'endorse', (int) $actor->id, $note, (int) $action->id);
                $this->recordHistory($mission, MissionStatus::OfficeHeadEndorsed, MissionStatus::PendingDirector, 'route_to_director', (int) $actor->id, null);
            }
        });

        return ['ok' => true, 'final' => $isFinal];
    }

    public function recordHistory(Mission $mission, ?MissionStatus $from, MissionStatus $to, string $action, ?int $actorId, ?string $reason, ?int $workflowActionId = null): MissionStatusHistory
    {
        $history = new MissionStatusHistory();
        $history->forceFill([
            'mission_id' => $mission->id,
            'from_status' => $from,
            'to_status' => $to,
            'action' => $action,
            'acted_by' => $actorId,
            'workflow_action_id' => $workflowActionId,
            'revision' => (int) ($mission->revision ?: 1),
            'reason' => $reason,
            'metadata' => null,
            'occurred_at' => now(),
        ]);
        $history->save();

        return $history;
    }
}
