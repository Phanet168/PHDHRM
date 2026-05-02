<?php

namespace Modules\HumanResource\Support;

use App\Models\User;
use App\Notifications\LeaveWorkflowNotification;
use Illuminate\Support\Collection;
use Modules\HumanResource\Entities\ApplyLeave;
use Modules\HumanResource\Entities\WorkflowDefinitionStep;

class LeaveWorkflowNotificationService
{
    public function __construct(private readonly WorkflowActorResolverService $workflowActorResolverService)
    {
    }

    public function notifySubmitted(ApplyLeave $leave): void
    {
        $leave = $this->hydrateLeave($leave);

        $requester = $this->requesterUser($leave);
        if ($requester) {
            $requester->notify(new LeaveWorkflowNotification(
                $leave,
                'submitted',
                localize('leave_request_submitted', 'Leave request submitted'),
                localize('leave_request_submitted_message', 'Your leave request has been submitted and is waiting for workflow review.'),
                route('leave.index'),
                [
                    'audience_key' => 'requester',
                    'audience_label' => localize('notification_audience_requester', 'អ្នកស្នើសុំ'),
                ]
            ));
        }

        $this->notifyHandoverEmployee($leave);
        $this->notifyCurrentStepActors($leave, 'pending_review');
    }

    public function notifyForwarded(ApplyLeave $leave): void
    {
        $leave = $this->hydrateLeave($leave);

        if ($requester = $this->requesterUser($leave)) {
            $requester->notify(new LeaveWorkflowNotification(
                $leave,
                'forwarded',
                localize('leave_request_forwarded', 'Leave request forwarded'),
                localize('leave_request_forwarded_message', 'Your leave request was reviewed and sent to the next approver.'),
                route('leave.index'),
                [
                    'audience_key' => 'requester',
                    'audience_label' => localize('notification_audience_requester', 'អ្នកស្នើសុំ'),
                ]
            ));
        }

        $this->notifyCurrentStepActors($leave, 'pending_review');
    }

    public function notifyApproved(ApplyLeave $leave): void
    {
        $leave = $this->hydrateLeave($leave);

        if ($requester = $this->requesterUser($leave)) {
            $requester->notify(new LeaveWorkflowNotification(
                $leave,
                'approved',
                localize('leave_request_approved', 'Leave request approved'),
                localize('leave_request_approved_message', 'Your leave request has been approved. Please print the form and submit the hard copy to administration.'),
                route('leave.print', $leave->uuid),
                [
                    'audience_key' => 'requester',
                    'audience_label' => localize('notification_audience_requester', 'អ្នកស្នើសុំ'),
                ]
            ));
        }
    }

    public function notifyRejected(ApplyLeave $leave): void
    {
        $leave = $this->hydrateLeave($leave);

        if ($requester = $this->requesterUser($leave)) {
            $requester->notify(new LeaveWorkflowNotification(
                $leave,
                'rejected',
                localize('leave_request_rejected', 'Leave request rejected'),
                localize('leave_request_rejected_message', 'Your leave request was rejected. Open the request to review the note.'),
                route('leave.index'),
                [
                    'audience_key' => 'requester',
                    'audience_label' => localize('notification_audience_requester', 'អ្នកស្នើសុំ'),
                ]
            ));
        }
    }

    protected function notifyCurrentStepActors(ApplyLeave $leave, string $context): void
    {
        $step = $this->resolveCurrentStep($leave);
        if (!$step) {
            return;
        }

        $actionLabel = $this->stepActionLabel($step);
        $recipients = $this->currentStepRecipients($leave);
        foreach ($recipients as $recipient) {
            if (!$recipient instanceof User) {
                continue;
            }

            $recipient->notify(new LeaveWorkflowNotification(
                $leave,
                $context,
                localize('leave_request_waiting_for_you', 'Leave request waiting for you'),
                localize('leave_request_waiting_for_you_message', 'A leave request is waiting for your action:')
                    . ' ' . $actionLabel . '.',
                route('leave.review-form', $leave->uuid),
                [
                    'audience_key' => 'reviewer',
                    'audience_label' => localize('notification_audience_reviewer', 'អ្នកពិនិត្យ/អនុម័ត'),
                    'step_name' => (string) ($step->step_name ?? ''),
                    'step_order' => (int) ($step->step_order ?? 0),
                    'action_type' => (string) ($step->action_type ?? ''),
                    'is_final_approval' => (bool) ($step->is_final_approval ?? false),
                ]
            ));
        }
    }

    protected function currentStepRecipients(ApplyLeave $leave): Collection
    {
        $step = $this->resolveCurrentStep($leave);
        if (!$step) {
            return collect();
        }

        $plan = [
            'module_key' => 'leave',
            'steps' => [[
                'step_order' => (int) ($step->step_order ?? 0),
                'action_type' => (string) ($step->action_type ?? ''),
                'actor_user_id' => (int) ($step->actor_user_id ?? 0),
                'actor_position_id' => (int) ($step->actor_position_id ?? 0),
                'actor_responsibility_id' => (int) ($step->actor_responsibility_id ?? 0),
                'actor_role_id' => (int) ($step->actor_role_id ?? 0),
                'system_role_id' => (int) ($step->system_role_id ?? 0),
                'org_role' => (string) ($step->org_role ?? ''),
                'is_final_approval' => (bool) ($step->is_final_approval ?? false),
            ]],
        ];

        $preview = $this->workflowActorResolverService->previewPlan($plan, $this->buildWorkflowContext($leave));
        $userIds = collect((array) data_get($preview, 'steps.0.resolved_candidates', []))
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->withoutGlobalScope('sortByLatest')
            ->whereIn('id', $userIds->all())
            ->get()
            ->keyBy('id')
            ->only($userIds->all())
            ->values();
    }

    protected function resolveCurrentStep(ApplyLeave $leave): ?WorkflowDefinitionStep
    {
        $instance = $leave->workflowInstance;
        if (!$instance || !$instance->definition || !$instance->definition->relationLoaded('steps')) {
            return null;
        }

        $steps = $instance->definition->steps;
        if ($steps->isEmpty()) {
            return null;
        }

        $currentOrder = (int) ($instance->current_step_order ?: $leave->workflow_current_step_order ?: 0);
        if ($currentOrder <= 0) {
            return $steps->sortBy('step_order')->first();
        }

        return $steps->firstWhere('step_order', $currentOrder);
    }

    protected function stepActionLabel(WorkflowDefinitionStep $step): string
    {
        $actionType = trim((string) ($step->action_type ?? ''));

        return match ($actionType) {
            'recommend', 'review' => localize('give_opinion', 'Give opinion / recommend'),
            'approve' => localize('approve', 'Approve'),
            default => localize('review', 'Review'),
        };
    }

    protected function requesterUser(ApplyLeave $leave): ?User
    {
        $userId = (int) ($leave->employee?->user_id ?? 0);
        if ($userId <= 0) {
            return null;
        }

        return User::query()
            ->withoutGlobalScope('sortByLatest')
            ->find($userId);
    }

    protected function notifyHandoverEmployee(ApplyLeave $leave): void
    {
        $handoverUserId = (int) ($leave->handoverEmployee?->user_id ?? 0);
        if ($handoverUserId <= 0) {
            return;
        }

        $handoverUser = User::query()
            ->withoutGlobalScope('sortByLatest')
            ->find($handoverUserId);

        if (!$handoverUser) {
            return;
        }

        $requesterName = trim((string) ($leave->employee?->full_name ?? ''));
        $leaveTypeName = trim((string) ($leave->leaveType?->display_name ?? ''));
        $dateRange = trim(
            (
                optional($leave->leave_apply_start_date)->format('Y-m-d')
                ?? (string) ($leave->leave_apply_start_date ?? '')
            ) . ' - ' . (
                optional($leave->leave_apply_end_date)->format('Y-m-d')
                ?? (string) ($leave->leave_apply_end_date ?? '')
            ),
            ' -'
        );

        $messageParts = array_filter([
            $requesterName !== '' ? $requesterName : null,
            localize('handover_leave_notification_message', 'selected you as the replacement employee for leave'),
            $leaveTypeName !== '' ? '(' . $leaveTypeName . ')' : null,
            $dateRange !== '' ? localize('date_range', 'Date') . ': ' . $dateRange : null,
        ]);

        $handoverUser->notify(new LeaveWorkflowNotification(
            $leave,
            'handover_assigned',
            localize('handover_leave_notification_title', 'You were assigned as replacement employee'),
            implode(' ', $messageParts),
            route('leave.index'),
            [
                'audience_key' => 'handover',
                'audience_label' => localize('notification_audience_handover', 'អ្នកជំនួសការងារ'),
                'handover_employee_id' => (int) ($leave->handover_employee_id ?? 0),
                'handover_employee_name' => trim((string) ($leave->handoverEmployee?->full_name ?? '')),
            ]
        ));
    }

    protected function hydrateLeave(ApplyLeave $leave): ApplyLeave
    {
        $leave->loadMissing([
            'leaveType:id,leave_type,leave_type_km',
            'employee:id,user_id,first_name,last_name,department_id,sub_department_id,position_id,employee_type_id,is_full_right_officer',
            'employee.primaryUnitPosting:employee_unit_postings.id,employee_unit_postings.employee_id,employee_unit_postings.department_id,employee_unit_postings.position_id',
            'employee.primaryUnitPosting.department:id,department_name,unit_type_id',
            'employee.primaryUnitPosting.department.unitType:id,code',
            'employee.department:id,department_name,unit_type_id',
            'employee.department.unitType:id,code',
            'handoverEmployee:id,user_id,first_name,last_name',
            'workflowInstance.definition.steps.systemRole',
        ]);

        return $leave;
    }

    protected function buildWorkflowContext(ApplyLeave $leave): array
    {
        $employee = $leave->employee;
        $currentDepartment = $employee?->primaryUnitPosting?->department ?: $employee?->department;
        $currentDepartmentId = (int) ($employee?->primaryUnitPosting?->department_id
            ?: $employee?->sub_department_id
            ?: $employee?->department_id
            ?: 0);
        $currentPositionId = (int) ($employee?->primaryUnitPosting?->position_id
            ?: $employee?->position_id
            ?: 0);

        return [
            'days' => (float) ($leave->total_apply_day ?? 0),
            'employee_id' => (int) ($leave->employee_id ?? 0),
            'department_id' => $currentDepartmentId > 0 ? $currentDepartmentId : null,
            'position_id' => $currentPositionId > 0 ? $currentPositionId : null,
            'employee_type_id' => (int) ($employee?->employee_type_id ?? 0),
            'employee_type_code' => '',
            'org_unit_type_id' => (int) ($currentDepartment?->unit_type_id ?? 0),
            'org_unit_type_code' => (string) ($currentDepartment?->unitType?->code ?? ''),
            'is_full_right' => (bool) ($employee?->is_full_right_officer ?? false),
        ];
    }
}
