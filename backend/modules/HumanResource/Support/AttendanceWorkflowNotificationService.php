<?php

namespace Modules\HumanResource\Support;

use App\Models\User;
use App\Notifications\AttendanceWorkflowNotification;
use Illuminate\Support\Collection;
use Modules\HumanResource\Entities\AttendanceAdjustment;

class AttendanceWorkflowNotificationService
{
    public function __construct(
        private readonly WorkflowPolicyService $workflowPolicyService,
        private readonly WorkflowActorResolverService $workflowActorResolverService
    ) {
    }

    public function notifySubmitted(AttendanceAdjustment $adjustment): void
    {
        $adjustment = $this->hydrateAdjustment($adjustment);

        if ($requester = $this->requesterUser($adjustment)) {
            $requester->notify(new AttendanceWorkflowNotification(
                $adjustment,
                'submitted',
                localize('attendance_adjustment_submitted', 'សំណើកែប្រែវត្តមានបានដាក់រួច'),
                localize('attendance_adjustment_submitted_message', 'សំណើកែប្រែវត្តមានរបស់អ្នកបានដាក់រួច និងកំពុងរង់ចាំការពិនិត្យ។'),
                route('attendance-adjustments.index')
            ));
        }

        foreach ($this->currentStepRecipients($adjustment) as $recipient) {
            if (!$recipient instanceof User) {
                continue;
            }

            $recipient->notify(new AttendanceWorkflowNotification(
                $adjustment,
                'pending_review',
                localize('attendance_adjustment_waiting_for_you', 'មានសំណើកែប្រែវត្តមានរង់ចាំអ្នក'),
                localize('attendance_adjustment_waiting_for_you_message', 'មានសំណើកែប្រែវត្តមានថ្មីមួយកំពុងរង់ចាំការពិនិត្យ ឬអនុម័តពីអ្នក។'),
                route('attendance-adjustments.index')
            ));
        }
    }

    protected function currentStepRecipients(AttendanceAdjustment $adjustment): Collection
    {
        $plan = $this->workflowPolicyService->resolveAndBuild(
            'attendance',
            'attendance_adjustment',
            $this->buildWorkflowContext($adjustment)
        );

        if (!$plan || empty($plan['steps'])) {
            return collect();
        }

        $preview = $this->workflowActorResolverService->previewPlan($plan, $this->buildWorkflowContext($adjustment));
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

    protected function requesterUser(AttendanceAdjustment $adjustment): ?User
    {
        $userId = (int) ($adjustment->createdBy?->id ?: $adjustment->employee?->user_id ?: 0);
        if ($userId <= 0) {
            return null;
        }

        return User::query()
            ->withoutGlobalScope('sortByLatest')
            ->find($userId);
    }

    protected function hydrateAdjustment(AttendanceAdjustment $adjustment): AttendanceAdjustment
    {
        $adjustment->loadMissing([
            'attendance:id,attendance_date,time,machine_state',
            'employee:id,user_id,first_name,last_name,department_id,sub_department_id,position_id,employee_type_id,is_full_right_officer',
            'employee.primaryUnitPosting:employee_unit_postings.id,employee_unit_postings.employee_id,employee_unit_postings.department_id,employee_unit_postings.position_id',
            'employee.primaryUnitPosting.department:id,department_name,unit_type_id',
            'employee.primaryUnitPosting.department.unitType:id,code',
            'employee.department:id,department_name,unit_type_id',
            'employee.department.unitType:id,code',
            'createdBy:id',
        ]);

        return $adjustment;
    }

    protected function buildWorkflowContext(AttendanceAdjustment $adjustment): array
    {
        $employee = $adjustment->employee;
        $currentDepartment = $employee?->primaryUnitPosting?->department ?: $employee?->department;
        $currentDepartmentId = (int) ($employee?->primaryUnitPosting?->department_id
            ?: $employee?->sub_department_id
            ?: $employee?->department_id
            ?: 0);
        $currentPositionId = (int) ($employee?->primaryUnitPosting?->position_id
            ?: $employee?->position_id
            ?: 0);

        return [
            'days' => null,
            'employee_id' => (int) ($adjustment->employee_id ?? 0),
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
