<?php

namespace Modules\HumanResource\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Throwable;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Accounts\Entities\FinancialYear;
use Modules\HumanResource\Entities\ApplyLeave;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\LeaveType;
use Modules\HumanResource\Entities\LeaveTypeYear;
use Modules\HumanResource\Entities\UserOrgRole;
use Modules\HumanResource\Entities\WorkflowInstance;
use Modules\HumanResource\Entities\WorkflowInstanceAction;
use Modules\HumanResource\Entities\WorkflowDefinitionStep;
use Modules\HumanResource\Support\EmployeeServiceHistoryService;
use Modules\HumanResource\Support\OrgHierarchyAccessService;
use Modules\HumanResource\Support\LeaveWorkflowNotificationService;
use Modules\HumanResource\Support\WorkflowActorResolverService;
use Modules\HumanResource\Support\WorkflowPolicyService;

class LeaveRequestApiController extends Controller
{
    public function types(): JsonResponse
    {
        $types = LeaveType::query()
            ->orderBy('id')
            ->get([
                'id',
                'leave_type',
                'leave_type_km',
                'leave_days',
                'policy_key',
                'entitlement_scope',
                'entitlement_unit',
                'entitlement_value',
                'max_per_request',
                'is_paid',
                'requires_attachment',
                'requires_medical_certificate',
            ]);

        return response()->json([
            'response' => [
                'status' => 'ok',
                'data' => $types,
            ],
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $employeeId = $this->resolveEmployeeId($request);
        if ($employeeId <= 0) {
            return $this->employeeNotLinkedResponse();
        }

        $types = LeaveType::query()
            ->orderBy('id')
            ->get([
                'id',
                'leave_type',
                'leave_type_km',
                'leave_days',
                'entitlement_scope',
                'entitlement_unit',
                'entitlement_value',
                'max_per_request',
            ]);

        $perType = [];
        $totalRemaining = 0;
        $financialYear = $this->resolveFinancialYearByDate(now()->toDateString());
        $financialYearId = (int) ($financialYear->id ?? 0);
        $financialYearLabel = (string) ($financialYear->financial_year ?? '');

        foreach ($types as $type) {
            $entitlement = $this->resolveEntitledDays($type);
            $scope = (string) ($type->entitlement_scope ?? 'per_year');
            $used = 0;
            $pending = 0;
            $remaining = $entitlement;

            if ($this->isPerYearLeavePolicy($type)) {
                $used = $this->sumApprovedLeaveDays($employeeId, (int) $type->id, $financialYearId);
                $pending = $this->sumPendingLeaveDays($employeeId, (int) $type->id, $financialYearId);
                $remaining = max($entitlement - $used - $pending, 0);
            } elseif ($this->isPerServiceLifetimeLeavePolicy($type)) {
                $used = $this->sumApprovedLeaveDaysAllYears($employeeId, (int) $type->id);
                $pending = $this->sumPendingLeaveDaysAllYears($employeeId, (int) $type->id);
                $remaining = max($entitlement - $used - $pending, 0);
            }

            $totalRemaining += $remaining;

            $perType[] = [
                'leave_type_id' => (int) $type->id,
                'leave_type' => (string) ($type->leave_type ?? ''),
                'leave_type_km' => (string) ($type->leave_type_km ?? ''),
                'scope' => $scope,
                'entitlement' => $entitlement,
                'used' => $used,
                'pending' => $pending,
                'remaining' => $remaining,
                'max_per_request' => $type->max_per_request !== null
                    ? $this->normalizeLeaveAmountToDays($type, (float) $type->max_per_request)
                    : null,
            ];
        }

        return response()->json([
            'response' => [
                'status' => 'ok',
                'data' => [
                    'employee_id' => $employeeId,
                    'total_remaining' => $totalRemaining,
                    'financial_year_id' => $financialYearId,
                    'financial_year_label' => $financialYearLabel,
                    'types' => $perType,
                ],
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $employeeId = $this->resolveEmployeeId($request);
        if ($employeeId <= 0) {
            return $this->employeeNotLinkedResponse();
        }

        $query = ApplyLeave::query()
            ->with(['leaveType:id,leave_type,leave_type_km'])
            ->where('employee_id', $employeeId)
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $status = strtolower(trim((string) $request->input('status')));
            $query->where(function (Builder $builder) use ($status): void {
                $builder->whereRaw('LOWER(COALESCE(workflow_status, "")) = ?', [$status]);
                if ($status === 'approved') {
                    $builder->orWhere('is_approved', 1);
                }
                if ($status === 'pending') {
                    $builder->orWhere(function (Builder $pending): void {
                        $pending->where('is_approved', 0)
                            ->where('is_approved_by_manager', 1);
                    });
                }
            });
        }

        $rows = $query->paginate((int) $request->input('per_page', 20));

        $items = [];
        foreach ($rows->items() as $row) {
            if ($row instanceof ApplyLeave) {
                $items[] = $this->transformLeave($row);
            }
        }

        return response()->json([
            'response' => [
                'status' => 'ok',
                'data' => [
                    'data' => $items,
                    'current_page' => $rows->currentPage(),
                    'per_page' => $rows->perPage(),
                    'total' => $rows->total(),
                    'last_page' => $rows->lastPage(),
                ],
            ],
        ]);
    }

    public function pendingReview(Request $request): JsonResponse
    {
        try {
            $reviewer = $this->assertCanReview($request);
        } catch (AuthorizationException $e) {
            return response()->json([
                'response' => [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ],
            ], 403);
        }

        $query = ApplyLeave::query()
            ->with([
                'leaveType:id,leave_type,leave_type_km',
                'employee:id,first_name,last_name,employee_id',
                'employee.primaryUnitPosting:employee_unit_postings.id,employee_unit_postings.employee_id,employee_unit_postings.department_id',
                'workflowInstance.definition.steps.systemRole',
            ])
            ->where(function (Builder $builder): void {
                $builder->where('is_approved', 0)
                    ->where(function (Builder $inner): void {
                        $inner->whereNull('workflow_status')
                            ->orWhere('workflow_status', '')
                            ->orWhere('workflow_status', 'pending');
                    });
            })
            ->orderByDesc('id');

        $perPage = max(1, min(100, (int) $request->input('per_page', 20)));
        $page = max(1, (int) $request->input('page', 1));

        if ($this->orgHierarchyAccessService()->isSystemAdmin($reviewer)) {
            $rows = $query->paginate($perPage, ['*'], 'page', $page);
            $pagedLeaves = collect($rows->items());
        } else {
            $allPendingLeaves = $query->get();
            $filteredLeaves = $allPendingLeaves
                ->filter(fn (ApplyLeave $leave) => $this->canCurrentReviewerActOnLeave($reviewer, $leave))
                ->values();

            $total = $filteredLeaves->count();
            $lastPage = max(1, (int) ceil($total / $perPage));
            $page = min($page, $lastPage);
            $offset = ($page - 1) * $perPage;
            $pagedLeaves = $filteredLeaves->slice($offset, $perPage)->values();

            $rows = (object) [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ];
        }

        $items = [];
        foreach ($pagedLeaves as $row) {
            if ($row instanceof ApplyLeave) {
                $payload = $this->transformLeave($row);
                $payload['employee'] = [
                    'id' => (int) optional($row->employee)->id,
                    'user_id' => (int) optional($row->employee)->user_id,
                    'employee_no' => (string) optional($row->employee)->employee_id,
                    'full_name' => trim(((string) optional($row->employee)->first_name) . ' ' . ((string) optional($row->employee)->last_name)),
                ];
                $items[] = $payload;
            }
        }

        $currentPage = method_exists($rows, 'currentPage')
            ? (int) $rows->currentPage()
            : (int) ($rows->current_page ?? 1);
        $resolvedPerPage = method_exists($rows, 'perPage')
            ? (int) $rows->perPage()
            : (int) ($rows->per_page ?? $perPage);
        $total = method_exists($rows, 'total')
            ? (int) $rows->total()
            : (int) ($rows->total ?? count($items));
        $lastPage = method_exists($rows, 'lastPage')
            ? (int) $rows->lastPage()
            : (int) ($rows->last_page ?? 1);

        return response()->json([
            'response' => [
                'status' => 'ok',
                'data' => [
                    'data' => $items,
                    'current_page' => $currentPage,
                    'per_page' => $resolvedPerPage,
                    'total' => $total,
                    'last_page' => $lastPage,
                ],
            ],
        ]);
    }

    public function handoverEmployees(Request $request): JsonResponse
    {
        $employeeId = $this->resolveEmployeeId($request);
        if ($employeeId <= 0) {
            return $this->employeeNotLinkedResponse();
        }

        $rows = Employee::query()
            ->where('is_active', 1)
            ->whereNotNull('user_id')
            ->where('id', '!=', $employeeId)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get([
                'id',
                'employee_id',
                'first_name',
                'last_name',
                'first_name_latin',
                'last_name_latin',
            ]);

        return response()->json([
            'response' => [
                'status' => 'ok',
                'data' => $rows->map(function (Employee $employee): array {
                    return [
                        'id' => (int) $employee->id,
                        'employee_no' => (string) ($employee->employee_id ?? ''),
                        'full_name' => trim((string) ($employee->full_name ?? '')),
                        'full_name_latin' => trim((string) ($employee->full_name_latin ?? '')),
                    ];
                })->values()->all(),
            ],
        ]);
    }

    public function review(Request $request, int $leaveRequest): JsonResponse
    {
        try {
            $reviewer = $this->assertCanReview($request);
        } catch (AuthorizationException $e) {
            return response()->json([
                'response' => [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ],
            ], 403);
        }

        $validated = $request->validate([
            'action' => ['required', 'string', 'in:approve,reject'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $leave = ApplyLeave::query()
            ->with([
                'leaveType:id,leave_type,leave_type_km',
                'employee:id,department_id,sub_department_id',
                'employee.primaryUnitPosting:employee_unit_postings.id,employee_unit_postings.employee_id,employee_unit_postings.department_id',
                'workflowInstance.definition.steps.systemRole',
            ])
            ->find($leaveRequest);
        if (!$leave) {
            return response()->json([
                'response' => [
                    'status' => 'error',
                    'message' => 'Leave request not found',
                ],
            ], 404);
        }

        $currentStatus = strtolower(trim((string) ($leave->workflow_status ?? '')));
        if (in_array($currentStatus, ['approved', 'rejected', 'cancelled'], true)) {
            return response()->json([
                'response' => [
                    'status' => 'error',
                    'message' => 'This request is already finalized',
                ],
            ], 422);
        }

        if (!$this->canCurrentReviewerActOnLeave($reviewer, $leave)) {
            return response()->json([
                'response' => [
                    'status' => 'error',
                    'message' => 'You are not allowed to review this leave request',
                ],
            ], 403);
        }

        $note = trim((string) ($validated['note'] ?? ''));
        $action = trim(mb_strtolower((string) $validated['action']));

        if (!$leave->workflow_instance_id) {
            $this->initializeLeaveWorkflow($leave, (int) ($leave->created_by ?: $reviewer->id));
            $leave = $leave->fresh([
                'leaveType:id,leave_type,leave_type_km',
                'employee:id,department_id,sub_department_id',
                'employee.primaryUnitPosting:employee_unit_postings.id,employee_unit_postings.employee_id,employee_unit_postings.department_id',
                'workflowInstance.definition.steps.systemRole',
            ]);
        }

        $instance = $leave?->workflowInstance;
        $currentStep = $instance ? $this->resolveCurrentWorkflowStep($instance) : null;

        if ($instance && $instance->definition && !$currentStep) {
            return response()->json([
                'response' => [
                    'status' => 'error',
                    'message' => 'Workflow step not found',
                ],
            ], 422);
        }

        if ($instance && $instance->definition && $currentStep && !$this->canUserActOnWorkflowStep($reviewer, $leave, $currentStep)) {
            return response()->json([
                'response' => [
                    'status' => 'error',
                    'message' => 'You are not allowed to review this workflow step',
                ],
            ], 403);
        }

        $isFinal = true;
        $message = '';

        if ($instance && $instance->definition && $currentStep) {
            $nextStep = $this->resolveNextWorkflowStep($instance, (int) $currentStep->step_order);
            $leaveType = $leave->leaveType ?: LeaveType::find((int) ($leave->leave_type_id ?? 0));
            $approvedDays = (int) ($leave->total_apply_day ?? 0);
            $requiresUpperReview = $this->requiresUpperReview($leaveType, $approvedDays);
            if ($action === 'approve' && $requiresUpperReview && !$this->orgHierarchyAccessService()->isSystemAdmin($reviewer) && !$nextStep) {
                return response()->json([
                    'response' => [
                        'status' => 'error',
                        'message' => 'This leave request exceeds allowed days and must be reviewed by an upper approver.',
                    ],
                ], 422);
            }

            $isFinal = ((bool) ($currentStep->is_final_approval ?? false) || !$nextStep)
                && !($action === 'approve' && $requiresUpperReview && $nextStep);
            $isManagerStep = $this->resolveWorkflowStepRoleCode($currentStep) === UserOrgRole::ROLE_MANAGER;

            DB::transaction(function () use (
                $leave,
                $instance,
                $currentStep,
                $nextStep,
                $reviewer,
                $action,
                $isFinal,
                $isManagerStep,
                $note
            ): void {
                if ($action === 'reject') {
                    WorkflowInstanceAction::create([
                        'workflow_instance_id' => (int) $instance->id,
                        'step_order' => (int) $currentStep->step_order,
                        'action_type' => 'reject',
                        'action_status' => 'rejected',
                        'acted_by' => (int) $reviewer->id,
                        'acted_at' => now(),
                        'decision_note' => $note !== '' ? $note : 'Rejected by reviewer',
                    ]);

                    $instance->update([
                        'status' => 'rejected',
                        'current_step_order' => (int) $currentStep->step_order,
                        'finalized_at' => now(),
                    ]);

                    $leave->update([
                        'is_approved' => 0,
                        'workflow_status' => 'rejected',
                        'workflow_current_step_order' => (int) $currentStep->step_order,
                        'workflow_last_action_at' => now(),
                        'manager_approved_description' => $note !== '' ? Str::limit($note, 250, '') : 'Rejected by reviewer',
                        'approved_by_manager' => (int) $reviewer->id,
                        'manager_approved_date' => now(),
                        'is_approved_by_manager' => 0,
                    ]);

                    return;
                }

                $actionType = $isFinal ? 'approve' : 'recommend';
                $actionStatus = $isFinal ? 'approved' : 'recommended';

                WorkflowInstanceAction::create([
                    'workflow_instance_id' => (int) $instance->id,
                    'step_order' => (int) $currentStep->step_order,
                    'action_type' => $actionType,
                    'action_status' => $actionStatus,
                    'acted_by' => (int) $reviewer->id,
                    'acted_at' => now(),
                    'decision_note' => $note !== '' ? $note : ($isFinal ? 'Approved by reviewer' : 'Recommended to next approver'),
                    'payload_json' => [
                        'leave_approved_start_date' => optional($leave->leave_apply_start_date)->format('Y-m-d')
                            ?? (string) $leave->leave_apply_start_date,
                        'leave_approved_end_date' => optional($leave->leave_apply_end_date)->format('Y-m-d')
                            ?? (string) $leave->leave_apply_end_date,
                        'total_approved_day' => (int) ($leave->total_apply_day ?? 0),
                    ],
                ]);

                $update = [
                    'workflow_last_action_at' => now(),
                    'leave_approved_start_date' => $leave->leave_apply_start_date,
                    'leave_approved_end_date' => $leave->leave_apply_end_date,
                    'total_approved_day' => (int) ($leave->total_apply_day ?? 0),
                ];

                if ($isManagerStep) {
                    $update['is_approved_by_manager'] = 1;
                    $update['approved_by_manager'] = (int) $reviewer->id;
                    $update['manager_approved_date'] = now();
                    $update['manager_approved_description'] = $note !== '' ? Str::limit($note, 250, '') : 'Approved by manager';
                }

                if ($isFinal) {
                    $instance->update([
                        'status' => 'approved',
                        'current_step_order' => (int) $currentStep->step_order,
                        'finalized_at' => now(),
                    ]);

                    $update['is_approved'] = 1;
                    $update['approved_by'] = (int) $reviewer->id;
                    $update['leave_approved_date'] = now();
                    $update['workflow_status'] = 'approved';
                    $update['workflow_current_step_order'] = (int) $currentStep->step_order;
                } else {
                    $instance->update([
                        'status' => 'pending',
                        'current_step_order' => (int) $nextStep->step_order,
                    ]);

                    $update['is_approved'] = 0;
                    $update['workflow_status'] = 'pending';
                    $update['workflow_current_step_order'] = (int) $nextStep->step_order;
                }

                $leave->update($update);
            });

            if ($action === 'reject') {
                $message = 'Leave request rejected successfully';
            } else {
                $message = $isFinal
                    ? 'Leave request approved successfully'
                    : 'Leave request sent to next approver';
            }
        } else {
            // Legacy fallback when workflow policy is not configured.
            if ($action === 'approve') {
                $leave->is_approved_by_manager = 1;
                $leave->approved_by_manager = (int) $reviewer->id;
                $leave->manager_approved_date = now();
                $leave->manager_approved_description = $note !== '' ? $note : 'Approved by reviewer';

                $leave->is_approved = 1;
                $leave->approved_by = (int) $reviewer->id;
                $leave->leave_approved_date = now();
                $leave->leave_approved_start_date = $leave->leave_apply_start_date;
                $leave->leave_approved_end_date = $leave->leave_apply_end_date;
                $leave->total_approved_day = (int) ($leave->total_apply_day ?? 0);
                $leave->workflow_status = 'approved';
                $leave->workflow_last_action_at = now();
                $message = 'Leave request approved successfully';
            } else {
                $leave->is_approved_by_manager = 0;
                $leave->approved_by_manager = (int) $reviewer->id;
                $leave->manager_approved_date = now();
                $leave->manager_approved_description = $note !== '' ? $note : 'Rejected by reviewer';
                $leave->is_approved = 0;
                $leave->workflow_status = 'rejected';
                $leave->workflow_last_action_at = now();
                $message = 'Leave request rejected successfully';
            }

            $leave->save();
        }

        if ($action === 'reject') {
            $this->leaveWorkflowNotificationService()->notifyRejected($leave->fresh());
        } elseif ($isFinal) {
            $leave = $leave->fresh(['leaveType']);
            $this->updateLeaveEntitlementTaken($leave, (int) ($leave->total_approved_day ?? $leave->total_apply_day ?? 0));
            $this->logLeaveApprovalHistory($leave, [
                'leave_approved_start_date' => optional($leave->leave_approved_start_date)->format('Y-m-d') ?? (string) $leave->leave_approved_start_date,
                'leave_approved_end_date' => optional($leave->leave_approved_end_date)->format('Y-m-d') ?? (string) $leave->leave_approved_end_date,
                'total_approved_day' => (int) ($leave->total_approved_day ?? $leave->total_apply_day ?? 0),
            ]);
            $this->leaveWorkflowNotificationService()->notifyApproved($leave->fresh());
        } else {
            $this->leaveWorkflowNotificationService()->notifyForwarded($leave->fresh());
        }

        $leave = $leave->fresh('leaveType:id,leave_type,leave_type_km');

        return response()->json([
            'response' => [
                'status' => 'ok',
                'message' => $message,
                'data' => $this->transformLeave($leave),
            ],
        ]);
    }

    public function show(Request $request, int $leaveRequest): JsonResponse
    {
        $employeeId = $this->resolveEmployeeId($request);
        if ($employeeId <= 0) {
            return $this->employeeNotLinkedResponse();
        }

        $row = ApplyLeave::query()
            ->with(['leaveType:id,leave_type,leave_type_km'])
            ->where('employee_id', $employeeId)
            ->find($leaveRequest);

        if (!$row) {
            return response()->json([
                'response' => [
                    'status' => 'error',
                    'message' => 'Leave request not found',
                ],
            ], 404);
        }

        return response()->json([
            'response' => [
                'status' => 'ok',
                'data' => $this->transformLeave($row),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $employeeId = $this->resolveEmployeeId($request);
        if ($employeeId <= 0) {
            return $this->employeeNotLinkedResponse();
        }

        $validated = $request->validate([
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'handover_employee_id' => ['required', 'integer', 'exists:employees,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,txt,rtf,jpeg,jpg,png,gif,svg', 'max:51200'],
        ]);

        $handoverEmployee = Employee::query()
            ->where('is_active', 1)
            ->whereNotNull('user_id')
            ->find((int) $validated['handover_employee_id']);
        if (!$handoverEmployee) {
            return response()->json([
                'response' => [
                    'status' => 'error',
                    'message' => 'Replacement employee must have an active user account',
                ],
            ], 422);
        }
        if ((int) $handoverEmployee->id === $employeeId) {
            return response()->json([
                'response' => [
                    'status' => 'error',
                    'message' => 'Replacement employee must be different from requester',
                ],
            ], 422);
        }

        $start = Carbon::parse($validated['start_date'])->startOfDay();
        $end = Carbon::parse($validated['end_date'])->startOfDay();
        $requestedDays = max(1, $start->diffInDays($end) + 1);

        $overlappingLeave = $this->findOverlappingLeave(
            $employeeId,
            $start->toDateString(),
            $end->toDateString()
        );

        if ($overlappingLeave) {
            return response()->json([
                'response' => [
                    'status' => 'error',
                    'message' => 'មានថ្ងៃស្ទួនជាមួយសំណើច្បាប់ដែលកំពុងរង់ចាំ ឬបានអនុម័ត',
                ],
            ], 422);
        }

        $type = LeaveType::query()->findOrFail((int) $validated['leave_type_id']);
        $requiresAttachment = (bool) ($type->requires_attachment ?? false)
            || (bool) ($type->requires_medical_certificate ?? false);

        if ($requiresAttachment && !$request->hasFile('attachment')) {
            return response()->json([
                'response' => [
                    'status' => 'error',
                    'message' => 'Attachment is required for this leave type',
                ],
            ], 422);
        }

        $financialYear = $this->resolveFinancialYearByDate($start->toDateString());
        $financialYearId = (int) ($financialYear->id ?? 0);

        if ($this->isPerYearLeavePolicy($type)) {
            $entitled = $this->resolveEntitledDays($type);
            $used = $this->sumApprovedLeaveDays($employeeId, (int) $type->id, $financialYearId);
            $pending = $this->sumPendingLeaveDays($employeeId, (int) $type->id, $financialYearId);
            $remaining = max($entitled - $used - $pending, 0);
            if ($requestedDays > $remaining) {
                return response()->json([
                    'response' => [
                        'status' => 'error',
                        'message' => 'ចំនួនថ្ងៃស្នើសុំលើសសមតុល្យច្បាប់ដែលនៅសល់',
                        'data' => [
                            'requested_days' => $requestedDays,
                            'remaining_days' => $remaining,
                        ],
                    ],
                ], 422);
            }
        } elseif ($this->isPerServiceLifetimeLeavePolicy($type)) {
            $entitled = $this->resolveEntitledDays($type);
            $used = $this->sumApprovedLeaveDaysAllYears($employeeId, (int) $type->id);
            $pending = $this->sumPendingLeaveDaysAllYears($employeeId, (int) $type->id);
            $remaining = max($entitled - $used - $pending, 0);
            if ($entitled > 0 && $requestedDays > $remaining) {
                return response()->json([
                    'response' => [
                        'status' => 'error',
                        'message' => 'áž…áŸ†áž“áž½áž“ážáŸ’áž„áŸƒážŸáŸ’áž“áž¾ážŸáž»áŸ†áž›áž¾ážŸážŸáž˜ážáž»áž›áŸ’áž™áž…áŸ’áž”áž¶áž”áŸ‹ážŠáŸ‚áž›áž“áŸ…ážŸáž›áŸ‹',
                        'data' => [
                            'requested_days' => $requestedDays,
                            'remaining_days' => $remaining,
                        ],
                    ],
                ], 422);
            }
        } elseif ($this->isPerRequestLeavePolicy($type)) {
            $perRequestLimit = $this->resolveEntitledDays($type);
            if ($perRequestLimit > 0 && $requestedDays > $perRequestLimit) {
                return response()->json([
                    'response' => [
                        'status' => 'error',
                        'message' => 'Requested leave exceeds this leave type limit',
                        'data' => [
                            'requested_days' => $requestedDays,
                            'max_days' => $perRequestLimit,
                        ],
                    ],
                ], 422);
            }
        }

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $filename = time() . mt_rand(10, 9999) . '.' . $file->extension();
            $attachmentPath = $file->storeAs('leave', $filename, 'public');
        }

        $leave = ApplyLeave::query()->create([
            'employee_id' => $employeeId,
            'handover_employee_id' => (int) $validated['handover_employee_id'],
            'leave_type_id' => (int) $validated['leave_type_id'],
            'leave_apply_start_date' => $start->toDateString(),
            'leave_apply_end_date' => $end->toDateString(),
            'leave_apply_date' => now()->toDateString(),
            'total_apply_day' => $requestedDays,
            'academic_year_id' => $financialYearId > 0 ? $financialYearId : null,
            'reason' => $validated['reason'],
            'location' => $attachmentPath,
            'is_approved_by_manager' => 0,
            'is_approved' => 0,
            'workflow_status' => 'pending',
            'workflow_last_action_at' => now(),
        ]);

        $this->initializeLeaveWorkflow($leave, (int) ($request->user()?->id ?? 0));

        try {
            $this->leaveWorkflowNotificationService()->notifySubmitted($leave->fresh());
        } catch (Throwable $e) {
            Log::warning('Leave workflow notification failed after mobile submit', [
                'leave_id' => (int) $leave->id,
                'employee_id' => (int) $leave->employee_id,
                'submitted_by' => (int) ($request->user()?->id ?? 0),
                'message' => $e->getMessage(),
            ]);
        }
        $leave->load('leaveType:id,leave_type,leave_type_km', 'handoverEmployee:id,user_id,first_name,last_name');

        return response()->json([
            'response' => [
                'status' => 'ok',
                'message' => 'Leave request submitted successfully',
                'data' => $this->transformLeave($leave),
            ],
        ], 201);
    }

    public function cancel(Request $request, int $leaveRequest): JsonResponse
    {
        $employeeId = $this->resolveEmployeeId($request);
        if ($employeeId <= 0) {
            return $this->employeeNotLinkedResponse();
        }

        $leave = ApplyLeave::query()
            ->where('employee_id', $employeeId)
            ->find($leaveRequest);

        if (!$leave) {
            return response()->json([
                'response' => [
                    'status' => 'error',
                    'message' => 'Leave request not found',
                ],
            ], 404);
        }

        if ((int) $leave->is_approved === 1 || strtolower((string) $leave->workflow_status) === 'approved') {
            return response()->json([
                'response' => [
                    'status' => 'error',
                    'message' => 'Approved request cannot be cancelled',
                ],
            ], 422);
        }

        if (strtolower((string) $leave->workflow_status) === 'cancelled') {
            return response()->json([
                'response' => [
                    'status' => 'ok',
                    'message' => 'Already cancelled',
                    'data' => $this->transformLeave($leave),
                ],
            ]);
        }

        $leave->workflow_status = 'cancelled';
        $leave->manager_approved_description = 'Cancelled by requester';
        $leave->workflow_last_action_at = now();
        $leave->save();

        $leave->load('leaveType:id,leave_type,leave_type_km');

        return response()->json([
            'response' => [
                'status' => 'ok',
                'message' => 'Leave request cancelled successfully',
                'data' => $this->transformLeave($leave),
            ],
        ]);
    }

    private function resolveEmployeeId(Request $request): int
    {
        $user = $request->user();
        if (!$user || !method_exists($user, 'employee')) {
            return 0;
        }

        $employee = $user->employee()->first(['id']);
        return (int) ($employee->id ?? 0);
    }

    private function employeeNotLinkedResponse(): JsonResponse
    {
        return response()->json([
            'response' => [
                'status' => 'error',
                'message' => 'User account is not linked to an employee profile',
            ],
        ], 422);
    }

    private function initializeLeaveWorkflow(ApplyLeave $leave, int $submittedBy = 0): void
    {
        if ($leave->workflow_instance_id) {
            return;
        }

        $plan = $this->resolveLeaveWorkflowPlan($leave);

        if (!$plan || empty($plan['steps'])) {
            $leave->update([
                'workflow_status' => 'pending',
                'workflow_current_step_order' => null,
                'workflow_last_action_at' => now(),
                'workflow_snapshot_json' => null,
            ]);
            return;
        }

        DB::transaction(function () use ($leave, $plan, $submittedBy): void {
            $steps = collect((array) ($plan['steps'] ?? []))->sortBy('step_order')->values();
            $firstStep = $steps->first();

            $instance = WorkflowInstance::create([
                'module_key' => 'leave',
                'request_type_key' => 'leave_request',
                'source_type' => ApplyLeave::class,
                'source_id' => (int) $leave->id,
                'workflow_definition_id' => (int) ($plan['definition_id'] ?? 0),
                'status' => 'pending',
                'current_step_order' => (int) ($firstStep['step_order'] ?? 1),
                'submitted_by' => $submittedBy > 0 ? $submittedBy : null,
                'submitted_at' => now(),
                'context_json' => $this->buildLeaveWorkflowContext($leave),
            ]);

            WorkflowInstanceAction::create([
                'workflow_instance_id' => (int) $instance->id,
                'step_order' => 0,
                'action_type' => 'submit',
                'action_status' => 'submitted',
                'acted_by' => $submittedBy > 0 ? $submittedBy : null,
                'acted_at' => now(),
                'decision_note' => 'Leave submitted',
                'payload_json' => [
                    'leave_id' => (int) $leave->id,
                    'total_apply_day' => (int) ($leave->total_apply_day ?? 0),
                ],
            ]);

            $leave->update([
                'workflow_instance_id' => (int) $instance->id,
                'workflow_status' => 'pending',
                'workflow_current_step_order' => (int) ($firstStep['step_order'] ?? 1),
                'workflow_last_action_at' => now(),
                'workflow_snapshot_json' => $plan,
            ]);
        });
    }

    private function buildLeaveWorkflowContext(ApplyLeave $leave): array
    {
        $employee = $leave->employee()
            ->with([
                'department.unitType',
                'employee_type',
                'primaryUnitPosting.department.unitType',
                'primaryUnitPosting.position',
            ])
            ->first();

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

    private function resolveLeaveWorkflowPlan(ApplyLeave $leave): ?array
    {
        $context = $this->buildLeaveWorkflowContext($leave);

        $leavePlan = $this->workflowPolicyService()->resolveAndBuild(
            'leave',
            'leave_request',
            $context
        );
        if ($leavePlan) {
            $leavePlan['source_policy_module_key'] = (string) ($leavePlan['module_key'] ?? 'leave');
            $leavePlan['source_policy_name'] = (string) ($leavePlan['name'] ?? 'Leave workflow');
        }
        $leavePlan = $this->normalizeLeaveWorkflowPlan($leavePlan, $leave);

        if ($this->planUsesSpecificUsers($leavePlan)) {
            return $leavePlan;
        }

        $attendancePlan = $this->workflowPolicyService()->resolveAndBuild(
            'attendance',
            'attendance_adjustment',
            $context
        );
        $attendancePlan = $this->normalizeLeaveWorkflowPlan(
            $this->adaptWorkflowPlanForLeave($attendancePlan),
            $leave
        );

        if ($this->planUsesSpecificUsers($attendancePlan)) {
            return $attendancePlan;
        }

        return $leavePlan;
    }

    private function adaptWorkflowPlanForLeave(?array $plan): ?array
    {
        if (!$plan) {
            return null;
        }

        $plan['module_key'] = 'leave';
        $plan['request_type_key'] = 'leave_request';
        $plan['name'] = (string) ($plan['name'] ?? 'Leave workflow');
        $plan['description'] = (string) ($plan['description'] ?? '');
        $plan['source_policy_module_key'] = (string) ($plan['source_policy_module_key'] ?? 'attendance');
        $plan['source_policy_name'] = (string) ($plan['source_policy_name'] ?? $plan['name']);

        return $plan;
    }

    private function planUsesSpecificUsers(?array $plan): bool
    {
        $steps = collect((array) ($plan['steps'] ?? []))
            ->filter(fn ($step) => is_array($step));

        if ($steps->isEmpty()) {
            return false;
        }

        return $steps->every(function (array $step): bool {
            return (string) ($step['actor_type'] ?? '') === WorkflowDefinitionStep::ACTOR_TYPE_SPECIFIC_USER
                && !empty($step['actor_user_id']);
        });
    }

    private function normalizeLeaveWorkflowPlan(?array $plan, ApplyLeave $leave): ?array
    {
        if (!$plan || empty($plan['steps'])) {
            return $plan;
        }

        $requesterUserId = (int) ($leave->employee()->value('user_id') ?? 0);
        if ($requesterUserId <= 0) {
            return $plan;
        }

        $previewPlan = $this->workflowActorResolverService()->previewPlan(
            $plan,
            $this->buildLeaveWorkflowContext($leave)
        );

        $steps = collect((array) ($previewPlan['steps'] ?? []))->values();
        while ($steps->isNotEmpty()) {
            $firstStep = (array) $steps->first();
            $candidateUserIds = collect((array) ($firstStep['resolved_candidates'] ?? []))
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values();

            if (!$candidateUserIds->contains($requesterUserId)) {
                break;
            }

            $steps = $steps->slice(1)->values();
        }

        if ($steps->isEmpty()) {
            return null;
        }

        $normalizedSteps = $steps->values()->map(function (array $step, int $index) use ($steps) {
            $isFinal = $index === ($steps->count() - 1);
            $step['step_order'] = $index + 1;
            $step['is_final_approval'] = $isFinal;
            if ($isFinal) {
                $step['action_type'] = 'approve';
            }
            return $step;
        })->all();

        $plan['steps'] = $normalizedSteps;
        return $plan;
    }

    private function resolveLeaveSourceDepartmentId(ApplyLeave $leave): int
    {
        $employee = $leave->employee()
            ->with('primaryUnitPosting:employee_unit_postings.id,employee_unit_postings.employee_id,employee_unit_postings.department_id')
            ->first();

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

    private function canCurrentReviewerActOnLeave(User $reviewer, ApplyLeave $leave): bool
    {
        if ((int) ($leave->is_approved ?? 0) === 1) {
            return false;
        }

        if ($this->isRequesterUser($leave, $reviewer)) {
            return false;
        }

        if ($this->orgHierarchyAccessService()->isSystemAdmin($reviewer)) {
            return true;
        }

        $instance = $leave->workflowInstance;
        if (!$instance || !$instance->definition) {
            return $this->canReviewerManageLeaveDepartment($reviewer, $leave);
        }

        $step = $this->resolveCurrentWorkflowStep($instance);
        if (!$step) {
            return false;
        }

        return $this->canUserActOnWorkflowStep($reviewer, $leave, $step);
    }

    private function canReviewerManageLeaveDepartment(User $reviewer, ApplyLeave $leave): bool
    {
        $sourceDepartmentId = $this->resolveLeaveSourceDepartmentId($leave);
        if ($sourceDepartmentId <= 0) {
            return $reviewer->can('create_leave_approval') || $reviewer->can('update_leave_application');
        }

        return $this->orgHierarchyAccessService()->canManageDepartment($reviewer, $sourceDepartmentId)
            || $this->orgHierarchyAccessService()->canApproveDepartment($reviewer, $sourceDepartmentId);
    }

    private function resolveCurrentWorkflowStep(WorkflowInstance $instance): ?WorkflowDefinitionStep
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

    private function resolveNextWorkflowStep(WorkflowInstance $instance, int $currentOrder): ?WorkflowDefinitionStep
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

    private function canUserActOnWorkflowStep(User $user, ApplyLeave $leave, WorkflowDefinitionStep $step): bool
    {
        if ($this->isRequesterUser($leave, $user)) {
            return false;
        }

        if ($this->orgHierarchyAccessService()->isSystemAdmin($user)) {
            return true;
        }

        $sourceDepartmentId = $this->resolveLeaveSourceDepartmentId($leave);
        return $this->workflowActorResolverService()->canUserActOnStep($user, $step, $sourceDepartmentId, 'leave');
    }

    private function isRequesterUser(ApplyLeave $leave, User $user): bool
    {
        $requesterUserId = (int) ($leave->employee?->user_id ?? 0);
        if ($requesterUserId <= 0) {
            $requesterUserId = (int) ($leave->employee()->value('user_id') ?? 0);
        }

        return $requesterUserId > 0 && $requesterUserId === (int) $user->id;
    }

    private function resolveWorkflowStepRoleCode(WorkflowDefinitionStep $step): string
    {
        if (method_exists($step, 'getEffectiveRoleCode')) {
            return (string) $step->getEffectiveRoleCode();
        }

        return (string) ($step->org_role ?? '');
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

    private function leaveWorkflowNotificationService(): LeaveWorkflowNotificationService
    {
        return app(LeaveWorkflowNotificationService::class);
    }

    private function isPerYearLeavePolicy(?LeaveType $leaveType): bool
    {
        if (!$leaveType) {
            return false;
        }

        return (string) ($leaveType->entitlement_scope ?? 'per_year') === 'per_year';
    }

    private function isPerRequestLeavePolicy(?LeaveType $leaveType): bool
    {
        if (!$leaveType) {
            return false;
        }

        return (string) ($leaveType->entitlement_scope ?? '') === 'per_request';
    }

    private function isPerServiceLifetimeLeavePolicy(?LeaveType $leaveType): bool
    {
        if (!$leaveType) {
            return false;
        }

        return (string) ($leaveType->entitlement_scope ?? '') === 'per_service_lifetime';
    }

    private function resolveEntitledDays(LeaveType $leaveType): int
    {
        $value = $leaveType->entitlement_value;
        if ($value === null || $value === '') {
            $value = $leaveType->leave_days;
        }

        return $this->normalizeLeaveAmountToDays($leaveType, (float) $value);
    }

    private function normalizeLeaveAmountToDays(LeaveType $leaveType, float $value): int
    {
        $normalized = (string) ($leaveType->entitlement_unit ?? 'day') === 'month'
            ? $value * 30
            : $value;

        return max((int) round($normalized), 0);
    }

    private function resolveFinancialYearByDate(string $date): FinancialYear
    {
        $day = Carbon::parse($date)->toDateString();

        $match = FinancialYear::query()
            ->where('status', 1)
            ->whereDate('start_date', '<=', $day)
            ->whereDate('end_date', '>=', $day)
            ->orderByDesc('id')
            ->first();

        if ($match) {
            return $match;
        }

        $year = Carbon::parse($day)->year;
        $yearText = (string) $year;

        return FinancialYear::query()->firstOrCreate(
            ['financial_year' => $yearText],
            [
                'financial_year' => $yearText,
                'start_date' => $year . '-01-01',
                'end_date' => $year . '-12-31',
                'status' => 1,
                'is_close' => 0,
            ]
        );
    }

    private function resolveFinancialYearRange(int $academicYearId): array
    {
        $financialYear = FinancialYear::query()->find($academicYearId);
        if (!$financialYear) {
            return [null, null];
        }

        $rangeStart = !empty($financialYear->start_date)
            ? Carbon::parse($financialYear->start_date)->toDateString()
            : null;
        $rangeEnd = !empty($financialYear->end_date)
            ? Carbon::parse($financialYear->end_date)->toDateString()
            : null;

        return [$rangeStart, $rangeEnd];
    }

    private function sumApprovedLeaveDays(
        int $employeeId,
        int $leaveTypeId,
        int $academicYearId
    ): int {
        [$rangeStart, $rangeEnd] = $this->resolveFinancialYearRange($academicYearId);

        $query = ApplyLeave::query()
            ->where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($academicYearId, $rangeStart, $rangeEnd) {
                $q->where('academic_year_id', $academicYearId);

                if ($rangeStart && $rangeEnd) {
                    $q->orWhere(function ($legacy) use ($rangeStart, $rangeEnd) {
                        $legacy->whereNull('academic_year_id')
                            ->whereRaw(
                                'DATE(COALESCE(leave_approved_start_date, leave_apply_start_date)) BETWEEN ? AND ?',
                                [$rangeStart, $rangeEnd]
                            );
                    });
                }
            })
            ->where(function ($q) {
                $q->where('is_approved', 1)
                    ->orWhere('workflow_status', 'approved');
            });

        return max((int) $query->sum(DB::raw('COALESCE(total_approved_day, total_apply_day, 0)')), 0);
    }

    private function sumPendingLeaveDays(
        int $employeeId,
        int $leaveTypeId,
        int $academicYearId
    ): int {
        [$rangeStart, $rangeEnd] = $this->resolveFinancialYearRange($academicYearId);

        $query = ApplyLeave::query()
            ->where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($academicYearId, $rangeStart, $rangeEnd) {
                $q->where('academic_year_id', $academicYearId);

                if ($rangeStart && $rangeEnd) {
                    $q->orWhere(function ($legacy) use ($rangeStart, $rangeEnd) {
                        $legacy->whereNull('academic_year_id')
                            ->whereRaw('DATE(leave_apply_start_date) BETWEEN ? AND ?', [$rangeStart, $rangeEnd]);
                    });
                }
            })
            ->where('is_approved', 0)
            ->where(function ($q) {
                $q->whereIn('workflow_status', ['pending', 'draft'])
                    ->orWhereNull('workflow_status');
            });

        return max((int) $query->sum(DB::raw('COALESCE(total_apply_day, 0)')), 0);
    }

    private function sumApprovedLeaveDaysAllYears(int $employeeId, int $leaveTypeId): int
    {
        $query = ApplyLeave::query()
            ->where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->where('is_approved', 1)
                    ->orWhere('workflow_status', 'approved');
            });

        return max((int) $query->sum(DB::raw('COALESCE(total_approved_day, total_apply_day, 0)')), 0);
    }

    private function sumPendingLeaveDaysAllYears(int $employeeId, int $leaveTypeId): int
    {
        $query = ApplyLeave::query()
            ->where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->whereNull('deleted_at')
            ->where('is_approved', 0)
            ->where(function ($q) {
                $q->whereIn('workflow_status', ['pending', 'draft'])
                    ->orWhereNull('workflow_status');
            });

        return max((int) $query->sum(DB::raw('COALESCE(total_apply_day, 0)')), 0);
    }

    private function requiresUpperReview(?LeaveType $leaveType, int $days): bool
    {
        if (!$leaveType || $days <= 0) {
            return false;
        }

        $maxPerRequest = $this->normalizeLeaveAmountToDays($leaveType, (float) ($leaveType->max_per_request ?? 0));
        if ($maxPerRequest <= 0) {
            return false;
        }

        return (float) $days > $maxPerRequest;
    }

    private function normalizeAcademicYearIdForLeave(ApplyLeave $leave): int
    {
        $currentId = (int) ($leave->academic_year_id ?? 0);
        if ($currentId > 0 && FinancialYear::query()->where('id', $currentId)->exists()) {
            return $currentId;
        }

        if (!empty($leave->leave_apply_start_date)) {
            $referenceDate = Carbon::parse($leave->leave_apply_start_date)->toDateString();
        } elseif (!empty($leave->leave_approved_start_date)) {
            $referenceDate = Carbon::parse($leave->leave_approved_start_date)->toDateString();
        } else {
            $referenceDate = now()->toDateString();
        }

        $financialYear = $this->resolveFinancialYearByDate($referenceDate);
        if ((int) $financialYear->id > 0 && (int) $leave->academic_year_id !== (int) $financialYear->id) {
            $leave->academic_year_id = (int) $financialYear->id;
            $leave->save();
        }

        return (int) $financialYear->id;
    }

    private function updateLeaveEntitlementTaken(ApplyLeave $leave, int $approvedDays): void
    {
        if ((int) ($leave->employee_id ?? 0) <= 0 || (int) ($leave->leave_type_id ?? 0) <= 0 || $approvedDays < 0) {
            return;
        }

        $leaveType = $leave->leaveType ?: LeaveType::find((int) $leave->leave_type_id);
        if (!$leaveType || !$this->isPerYearLeavePolicy($leaveType)) {
            return;
        }

        $academicYearId = $this->normalizeAcademicYearIdForLeave($leave);
        if ($academicYearId <= 0) {
            return;
        }

        $entitled = $this->resolveEntitledDays($leaveType);
        $balance = LeaveTypeYear::firstOrCreate(
            [
                'employee_id' => (int) $leave->employee_id,
                'leave_type_id' => (int) $leave->leave_type_id,
                'academic_year_id' => $academicYearId,
            ],
            [
                'entitled' => $entitled,
                'taken' => 0,
            ]
        );

        $approvedTaken = $this->sumApprovedLeaveDays(
            (int) $leave->employee_id,
            (int) $leave->leave_type_id,
            $academicYearId
        );

        if ($balance->entitled === null || (int) $balance->entitled <= 0) {
            $balance->entitled = $entitled;
        }
        $balance->taken = max($approvedTaken, 0);
        $balance->save();
    }

    private function logLeaveApprovalHistory(ApplyLeave $leave, array $validated): void
    {
        $leaveType = $leave->leaveType ?: LeaveType::find((int) $leave->leave_type_id);
        $details = sprintf(
            '%s | %s to %s | %s days',
            $leaveType?->display_name ?? 'Leave',
            (string) ($validated['leave_approved_start_date'] ?? ''),
            (string) ($validated['leave_approved_end_date'] ?? ''),
            (string) ($validated['total_approved_day'] ?? 0)
        );

        $this->historyService()->log(
            (int) $leave->employee_id,
            'leave_approved',
            'Leave approved',
            $details,
            (string) ($validated['leave_approved_start_date'] ?? now()->toDateString()),
            null,
            null,
            'apply_leave',
            $leave->id,
            [
                'leave_type_id' => $leaveType?->id,
                'total_approved_day' => (int) ($validated['total_approved_day'] ?? 0),
            ]
        );
    }

    private function findOverlappingLeave(
        int $employeeId,
        string $startDate,
        string $endDate,
        ?int $excludeLeaveId = null
    ): ?ApplyLeave {
        if ($employeeId <= 0 || $startDate === '' || $endDate === '') {
            return null;
        }

        $query = ApplyLeave::query()
            ->where('employee_id', $employeeId)
            ->whereNull('deleted_at')
            ->whereDate('leave_apply_start_date', '<=', $endDate)
            ->whereDate('leave_apply_end_date', '>=', $startDate)
            ->where(function ($q) {
                $q->where('is_approved', 1)
                    ->orWhereIn('workflow_status', ['pending', 'approved', 'draft'])
                    ->orWhereNull('workflow_status');
            });

        if ($excludeLeaveId) {
            $query->where('id', '!=', $excludeLeaveId);
        }

        return $query->orderByDesc('id')->first();
    }

    private function historyService(): EmployeeServiceHistoryService
    {
        return app(EmployeeServiceHistoryService::class);
    }

    private function transformLeave(ApplyLeave $row): array
    {
        $status = $this->resolveStatus($row);
        $attachmentPath = trim((string) ($row->location ?? ''));
        $attachmentUrl = $attachmentPath !== '' ? asset('storage/' . ltrim($attachmentPath, '/')) : null;
        $workflowDisplay = $this->resolveLeaveWorkflowDisplay($row);

        return [
            'id' => (int) $row->id,
            'uuid' => (string) ($row->uuid ?? ''),
            'employee_id' => (int) $row->employee_id,
            'handover_employee_id' => (int) ($row->handover_employee_id ?? 0),
            'handover_employee_name' => trim((string) ($row->handoverEmployee?->full_name ?? '')) !== ''
                ? trim((string) $row->handoverEmployee?->full_name)
                : trim(((string) ($row->handoverEmployee?->first_name ?? '')) . ' ' . ((string) ($row->handoverEmployee?->last_name ?? ''))),
            'leave_type_id' => (int) $row->leave_type_id,
            'leave_type' => (string) optional($row->leaveType)->leave_type,
            'leave_type_km' => (string) optional($row->leaveType)->leave_type_km,
            'start_date' => optional($row->leave_apply_start_date)->format('Y-m-d') ?? (string) $row->leave_apply_start_date,
            'end_date' => optional($row->leave_apply_end_date)->format('Y-m-d') ?? (string) $row->leave_apply_end_date,
            'requested_days' => (int) ($row->total_apply_day ?? 0),
            'approved_days' => (int) ($row->total_approved_day ?? 0),
            'reason' => (string) ($row->reason ?? ''),
            'location' => $attachmentPath,
            'attachment_url' => $attachmentUrl,
            'status' => $status,
            'workflow_status' => (string) ($row->workflow_status ?? ''),
            'workflow_current_step_order' => $workflowDisplay['current_step_order'],
            'workflow_current_step_name' => $workflowDisplay['current_step_name'],
            'workflow_current_actor_name' => $workflowDisplay['current_actor_name'],
            'workflow_source_policy_module_key' => $workflowDisplay['source_policy_module_key'],
            'workflow_source_policy_name' => $workflowDisplay['source_policy_name'],
            'workflow_steps' => $workflowDisplay['steps'],
            'is_approved' => (int) ($row->is_approved ?? 0),
            'submitted_at' => optional($row->created_at)?->toIso8601String(),
            'updated_at' => optional($row->updated_at)?->toIso8601String(),
        ];
    }

    private function resolveLeaveWorkflowDisplay(ApplyLeave $row): array
    {
        $plan = is_array($row->workflow_snapshot_json) ? $row->workflow_snapshot_json : null;
        if (!$plan) {
            $plan = $this->resolveLeaveWorkflowPlan($row);
        }

        if (!$plan || empty($plan['steps'])) {
            return [
                'current_step_order' => null,
                'current_step_name' => '',
                'current_actor_name' => '',
                'source_policy_module_key' => '',
                'source_policy_name' => '',
                'steps' => [],
            ];
        }

        $preview = $this->workflowActorResolverService()->previewPlan(
            $plan,
            $this->buildLeaveWorkflowContext($row)
        );

        $currentOrder = (int) ($row->workflow_current_step_order ?? 0);
        $steps = collect((array) ($preview['steps'] ?? []))
            ->map(function (array $step) use ($currentOrder): array {
                $candidate = collect((array) ($step['resolved_candidates'] ?? []))->first();
                $actorName = trim((string) (($candidate['full_name'] ?? '') ?: ($candidate['email'] ?? '')));

                return [
                    'step_order' => (int) ($step['step_order'] ?? 0),
                    'step_name' => (string) ($step['step_name'] ?? ''),
                    'action_type' => (string) ($step['action_type'] ?? ''),
                    'actor_name' => $actorName,
                    'is_final_approval' => (bool) ($step['is_final_approval'] ?? false),
                    'is_current' => $currentOrder > 0 && (int) ($step['step_order'] ?? 0) === $currentOrder,
                ];
            })
            ->values();

        $currentStep = $steps->firstWhere('is_current', true);

        return [
            'current_step_order' => $currentStep['step_order'] ?? ($currentOrder > 0 ? $currentOrder : null),
            'current_step_name' => (string) ($currentStep['step_name'] ?? ''),
            'current_actor_name' => (string) ($currentStep['actor_name'] ?? ''),
            'source_policy_module_key' => (string) ($preview['source_policy_module_key'] ?? $preview['module_key'] ?? ''),
            'source_policy_name' => (string) ($preview['source_policy_name'] ?? $preview['name'] ?? ''),
            'steps' => $steps->all(),
        ];
    }

    private function resolveStatus(ApplyLeave $row): string
    {
        $workflowStatus = strtolower(trim((string) ($row->workflow_status ?? '')));
        if ($workflowStatus !== '') {
            return $workflowStatus;
        }

        if ((int) $row->is_approved === 1) {
            return 'approved';
        }

        if ((int) $row->is_approved_by_manager === 1) {
            return 'pending';
        }

        return 'pending';
    }

    private function assertCanReview(Request $request): User
    {
        $user = $request->user();
        if (!$user instanceof User) {
            throw new AuthorizationException('Unauthorized');
        }

        if ($user->can('create_leave_approval') || $user->can('update_leave_application')) {
            return $user;
        }

        throw new AuthorizationException('You do not have permission to review leave requests');
    }
}
