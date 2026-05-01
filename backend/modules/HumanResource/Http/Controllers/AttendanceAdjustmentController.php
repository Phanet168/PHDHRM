<?php

namespace Modules\HumanResource\Http\Controllers;

use App\Notifications\AttendanceWorkflowNotification;
use App\Notifications\CorrespondenceAssignedNotification;
use App\Notifications\LeaveWorkflowNotification;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\HumanResource\Entities\AttendanceAdjustment;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Services\AttendanceAdjustmentService;
use Modules\HumanResource\Support\OrgRolePermissionService;

class AttendanceAdjustmentController extends Controller
{
    public function __construct(
        private readonly AttendanceAdjustmentService $adjustmentService,
        private readonly OrgRolePermissionService $rolePermissionService
    ) {
    }

    public function index(Request $request): mixed
    {
        abort_unless($this->canManageAttendanceAction('manage_exceptions'), 403);

        $query = AttendanceAdjustment::query()
            ->with(['employee', 'attendance', 'createdBy'])
            ->orderByDesc('id');

        if ($request->filled('employee_id')) {
            $query->where('employee_id', (int) $request->input('employee_id'));
        }
        if ($request->filled('date_from')) {
            $query->whereHas('attendance', fn ($q) => $q->where('attendance_date', '>=', $request->input('date_from')));
        }
        if ($request->filled('date_to')) {
            $query->whereHas('attendance', fn ($q) => $q->where('attendance_date', '<=', $request->input('date_to')));
        }

        if ($request->expectsJson()) {
            $items = $query->paginate(20);
            return response()->json(['status' => 'ok', 'data' => $items]);
        }

        $adjustments = $query->paginate(20);
        $employees = Employee::query()
            ->where('is_active', 1)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'employee_id']);

        return view('humanresource::attendance.adjustments', compact('adjustments', 'employees'));
    }

    public function store(Request $request): mixed
    {
        $validated = $request->validate([
            'employee_id'       => ['required', 'integer', 'exists:employees,id'],
            'attendance_id'     => ['nullable', 'integer', 'exists:attendances,id'],
            'new_time'          => ['nullable', 'date_format:H:i'],
            'new_machine_state' => ['nullable', 'string', 'in:in,out'],
            'reason'            => ['required', 'string', 'max:2000'],
        ]);

        $employee = Employee::query()
            ->withoutGlobalScope('sortByLatest')
            ->findOrFail((int) $validated['employee_id'], ['id', 'department_id', 'sub_department_id']);
        $departmentId = (int) ($employee->sub_department_id ?: $employee->department_id ?: 0);

        abort_unless($this->canManageAttendanceAction('create_adjustment', $departmentId), 403);

        $result = $this->adjustmentService->request($validated);

        if ($request->expectsJson()) {
            return response()->json(['result' => $result], $result['status'] === 'ok' ? 201 : 422);
        }

        if ($result['status'] === 'ok') {
            return redirect()->route('attendance-adjustments.index')
                ->with('success', localize('adjustment_submitted', 'ការស្នើសុំកែប្រែបានរក្សាទុករួចរាល់'));
        }

        return redirect()->back()->withErrors(['error' => $result['message'] ?? 'Error'])->withInput();
    }

    public function openNotification(string $notification)
    {
        $user = Auth::user();
        abort_unless($user, 403);

        /** @var DatabaseNotification|null $record */
        $record = $user->notifications()
            ->where('type', AttendanceWorkflowNotification::class)
            ->where('id', $notification)
            ->first();
        abort_unless($record, 404);

        if ($record->read_at === null) {
            $record->markAsRead();
        }

        $link = trim((string) data_get($record->data, 'link', route('attendance-adjustments.index')));

        return redirect()->to($link !== '' ? $link : route('attendance-adjustments.index'));
    }

    public function clearNotifications()
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $deleted = $user->notifications()
            ->where('type', AttendanceWorkflowNotification::class)
            ->delete();

        Toastr::success(
            localize('attendance_notifications_cleared', 'បានលុបការជូនដំណឹងវត្តមានរួចរាល់។'),
            localize('success', 'ជោគជ័យ')
        );

        return back()->with('attendance_notifications_cleared_count', (int) $deleted);
    }

    public function clearUnifiedNotifications()
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $deleted = $user->notifications()
            ->whereIn('type', $this->unifiedNotificationTypes())
            ->delete();

        Toastr::success(
            localize('notifications_cleared', 'បានលុបការជូនដំណឹងទាំងអស់រួចរាល់។'),
            localize('success', 'ជោគជ័យ')
        );

        return back()->with('workflow_notifications_cleared_count', (int) $deleted);
    }

    public function makeUnifiedNotificationsUnread()
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $updated = $user->notifications()
            ->whereIn('type', $this->unifiedNotificationTypes())
            ->whereNotNull('read_at')
            ->update(['read_at' => null]);

        Toastr::success(
            localize('notifications_marked_unread', 'បានបង្កើតការជូនដំណឹងមិនទាន់អានវិញរួចរាល់។'),
            localize('success', 'ជោគជ័យ')
        );

        return back()->with('workflow_notifications_marked_unread_count', (int) $updated);
    }

    protected function unifiedNotificationTypes(): array
    {
        return [
            LeaveWorkflowNotification::class,
            AttendanceWorkflowNotification::class,
            CorrespondenceAssignedNotification::class,
        ];
    }

    protected function canManageAttendanceAction(string $actionKey, ?int $departmentId = null): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        if ($this->rolePermissionService->canUserPerform(
            $user,
            'attendance',
            $actionKey,
            $departmentId,
            []
        )) {
            return true;
        }

        return match ($actionKey) {
            'create_adjustment' => $user->can('create_attendance_adjustment'),
            'manage_exceptions' => $user->can('read_attendance_adjustment'),
            default => false,
        } || request()->expectsJson();
    }
}
