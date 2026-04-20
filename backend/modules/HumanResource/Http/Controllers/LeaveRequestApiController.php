<?php

namespace Modules\HumanResource\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\HumanResource\Entities\ApplyLeave;
use Modules\HumanResource\Entities\LeaveType;

class LeaveRequestApiController extends Controller
{
    public function types(): JsonResponse
    {
        $types = LeaveType::query()
            ->orderBy('id')
            ->get(['id', 'leave_type', 'leave_type_km', 'leave_days', 'policy_key', 'max_per_request', 'is_paid', 'requires_attachment']);

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

        $types = LeaveType::query()->orderBy('id')->get(['id', 'leave_type', 'leave_type_km', 'leave_days']);

        $perType = [];
        $totalRemaining = 0;

        foreach ($types as $type) {
            $entitlement = max(0, (int) ($type->leave_days ?? 0));
            $used = (int) ApplyLeave::query()
                ->where('employee_id', $employeeId)
                ->where('leave_type_id', $type->id)
                ->where('is_approved', 1)
                ->sum('total_approved_day');

            $remaining = max(0, $entitlement - $used);
            $totalRemaining += $remaining;

            $perType[] = [
                'leave_type_id' => (int) $type->id,
                'leave_type' => (string) ($type->leave_type ?? ''),
                'leave_type_km' => (string) ($type->leave_type_km ?? ''),
                'entitlement' => $entitlement,
                'used' => $used,
                'remaining' => $remaining,
            ];
        }

        return response()->json([
            'response' => [
                'status' => 'ok',
                'data' => [
                    'employee_id' => $employeeId,
                    'total_remaining' => $totalRemaining,
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
            $this->assertCanReview($request);
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

        $rows = $query->paginate((int) $request->input('per_page', 20));

        $items = [];
        foreach ($rows->items() as $row) {
            if ($row instanceof ApplyLeave) {
                $payload = $this->transformLeave($row);
                $payload['employee'] = [
                    'id' => (int) optional($row->employee)->id,
                    'employee_no' => (string) optional($row->employee)->employee_id,
                    'full_name' => trim(((string) optional($row->employee)->first_name) . ' ' . ((string) optional($row->employee)->last_name)),
                ];
                $items[] = $payload;
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

        $leave = ApplyLeave::query()->with('leaveType:id,leave_type,leave_type_km')->find($leaveRequest);
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

        $note = trim((string) ($validated['note'] ?? ''));

        if ($validated['action'] === 'approve') {
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
        } else {
            $leave->is_approved_by_manager = 0;
            $leave->approved_by_manager = (int) $reviewer->id;
            $leave->manager_approved_date = now();
            $leave->manager_approved_description = $note !== '' ? $note : 'Rejected by reviewer';
            $leave->is_approved = 0;
            $leave->workflow_status = 'rejected';
            $leave->workflow_last_action_at = now();
        }

        $leave->save();

        return response()->json([
            'response' => [
                'status' => 'ok',
                'message' => $validated['action'] === 'approve'
                    ? 'Leave request approved successfully'
                    : 'Leave request rejected successfully',
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
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,txt,rtf,jpeg,jpg,png,gif,svg', 'max:51200'],
        ]);

        $start = Carbon::parse($validated['start_date'])->startOfDay();
        $end = Carbon::parse($validated['end_date'])->startOfDay();
        $requestedDays = max(1, $start->diffInDays($end) + 1);

        $hasOverlap = ApplyLeave::query()
            ->where('employee_id', $employeeId)
            ->where(function (Builder $query) {
                $query->where('is_approved', 1)
                    ->orWhere('is_approved_by_manager', 1)
                    ->orWhereIn('workflow_status', ['pending', 'approved']);
            })
            ->whereDate('leave_apply_start_date', '<=', $validated['end_date'])
            ->whereDate('leave_apply_end_date', '>=', $validated['start_date'])
            ->exists();

        if ($hasOverlap) {
            return response()->json([
                'response' => [
                    'status' => 'error',
                    'message' => 'មានថ្ងៃស្ទួនជាមួយសំណើច្បាប់ដែលកំពុងរង់ចាំ ឬបានអនុម័ត',
                ],
            ], 422);
        }

        $type = LeaveType::query()->findOrFail((int) $validated['leave_type_id']);
        $entitlement = (int) ($type->leave_days ?? 0);
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

        if ($entitlement > 0) {
            $used = (int) ApplyLeave::query()
                ->where('employee_id', $employeeId)
                ->where('leave_type_id', $type->id)
                ->where('is_approved', 1)
                ->sum('total_approved_day');

            $remaining = max(0, $entitlement - $used);
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
        }

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $filename = time() . mt_rand(10, 9999) . '.' . $file->extension();
            $attachmentPath = $file->storeAs('leave', $filename, 'public');
        }

        $leave = ApplyLeave::query()->create([
            'employee_id' => $employeeId,
            'leave_type_id' => (int) $validated['leave_type_id'],
            'leave_apply_start_date' => $start->toDateString(),
            'leave_apply_end_date' => $end->toDateString(),
            'leave_apply_date' => now()->toDateString(),
            'total_apply_day' => $requestedDays,
            'reason' => $validated['reason'],
            'location' => $attachmentPath,
            'is_approved_by_manager' => 0,
            'is_approved' => 0,
            'workflow_status' => 'pending',
            'workflow_last_action_at' => now(),
        ]);

        $leave->load('leaveType:id,leave_type,leave_type_km');

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

    private function transformLeave(ApplyLeave $row): array
    {
        $status = $this->resolveStatus($row);
        $attachmentPath = trim((string) ($row->location ?? ''));
        $attachmentUrl = $attachmentPath !== '' ? asset('storage/' . ltrim($attachmentPath, '/')) : null;

        return [
            'id' => (int) $row->id,
            'uuid' => (string) ($row->uuid ?? ''),
            'employee_id' => (int) $row->employee_id,
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
            'is_approved' => (int) ($row->is_approved ?? 0),
            'submitted_at' => optional($row->created_at)?->toIso8601String(),
            'updated_at' => optional($row->updated_at)?->toIso8601String(),
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

    private function assertCanReview(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            throw new AuthorizationException('Unauthorized');
        }

        if ($user->can('create_leave_approval') || $user->can('update_leave_application')) {
            return $user;
        }

        throw new AuthorizationException('You do not have permission to review leave requests');
    }
}
