<?php

namespace Modules\HumanResource\Http\Controllers;

use App\Notifications\AttendanceWorkflowNotification;
use App\Notifications\CorrespondenceAssignedNotification;
use App\Notifications\LeaveWorkflowNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Routing\Controller;
use Modules\HumanResource\Entities\Notice;
use Modules\HumanResource\Entities\NoticeDelivery;

class NoticeNotificationApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = (int) ($request->user()?->id ?? 0);
        if ($userId <= 0) {
            return $this->unauthorizedResponse();
        }

        $perPage = (int) $request->input('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $page = max(1, (int) $request->input('page', 1));
        $items = $this->mergedNotificationsForUser($request, $userId);
        $total = count($items);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;
        $rows = array_slice($items, $offset, $perPage);

        return response()->json([
            'response' => [
                'status' => 'ok',
                'data' => [
                    'data' => array_values($rows),
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => $lastPage,
                    'unread_count' => $this->unreadCountForUser($userId),
                ],
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $userId = (int) ($request->user()?->id ?? 0);
        if ($userId <= 0) {
            return $this->unauthorizedResponse();
        }

        return response()->json([
            'response' => [
                'status' => 'ok',
                'data' => [
                    'unread_count' => $this->unreadCountForUser($userId),
                ],
            ],
        ]);
    }

    public function read(Request $request, string $notificationDelivery): JsonResponse
    {
        $userId = (int) ($request->user()?->id ?? 0);
        if ($userId <= 0) {
            return $this->unauthorizedResponse();
        }

        $record = $this->resolveNotificationRecord($request, $userId, $notificationDelivery);
        if (!$record) {
            return response()->json([
                'response' => [
                    'status' => 'error',
                    'message' => 'Notification not found',
                ],
            ], 404);
        }

        if ($record instanceof NoticeDelivery) {
            if ($record->read_at === null) {
                $record->read_at = now();
                $record->save();
            }

            $record->refresh();
            $record->load([
                'notice' => function ($query): void {
                    $query->withoutGlobalScope('sortByLatest');
                },
            ]);
        } elseif ($record instanceof DatabaseNotification && $record->read_at === null) {
            $record->markAsRead();
            $record->refresh();
        }

        return response()->json([
            'response' => [
                'status' => 'ok',
                'message' => 'Notification marked as read',
                'data' => $this->transformNotificationRecord($record),
                'unread_count' => $this->unreadCountForUser($userId),
            ],
        ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $userId = (int) ($request->user()?->id ?? 0);
        if ($userId <= 0) {
            return $this->unauthorizedResponse();
        }

        $marked = $this->baseQuery($userId)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        $leaveNotificationsMarked = (int) $request->user()
            ?->unreadNotifications()
            ->whereIn('type', [LeaveWorkflowNotification::class, AttendanceWorkflowNotification::class, CorrespondenceAssignedNotification::class])
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json([
            'response' => [
                'status' => 'ok',
                'message' => 'All notifications marked as read',
                'data' => [
                    'marked_count' => (int) $marked + $leaveNotificationsMarked,
                    'unread_count' => $this->unreadCountForUser($userId),
                ],
            ],
        ]);
    }

    private function baseQuery(int $userId)
    {
        return NoticeDelivery::query()
            ->where('user_id', $userId)
            ->where('channel', 'in_app')
            ->where(function ($query): void {
                $query->where('status', 'sent')
                    ->orWhereNotNull('sent_at');
            });
    }

    private function unreadCountForUser(int $userId): int
    {
        $noticeCount = (int) $this->baseQuery($userId)
            ->whereNull('read_at')
            ->count();

        $leaveCount = (int) auth()->user()
            ?->unreadNotifications()
            ->whereIn('type', [LeaveWorkflowNotification::class, AttendanceWorkflowNotification::class, CorrespondenceAssignedNotification::class])
            ->count();

        return $noticeCount + $leaveCount;
    }

    private function transformDelivery(NoticeDelivery $delivery): array
    {
        /** @var Notice|null $notice */
        $notice = $delivery->notice;

        return [
            'id' => (int) $delivery->id,
            'notice_id' => (int) ($delivery->notice_id ?? 0),
            'title' => trim((string) ($notice?->notice_type ?? localize('notice', 'Notice'))),
            'description' => trim((string) ($notice?->notice_descriptiion ?? '')),
            'notice_by' => (string) ($notice?->notice_by ?? ''),
            'notice_date' => optional($notice?->notice_date)->format('Y-m-d') ?? (string) ($notice?->notice_date ?? ''),
            'attachment_url' => $this->attachmentUrl($notice?->notice_attachment),
            'status' => (string) ($delivery->status ?? ''),
            'channel' => (string) ($delivery->channel ?? ''),
            'source' => 'notice',
            'type_label' => localize('notice', 'សេចក្តីជូនដំណឹង'),
            'notification_id' => 'notice:' . (int) $delivery->id,
            'sent_at' => optional($delivery->sent_at)?->toIso8601String() ?? optional($delivery->created_at)?->toIso8601String(),
            'read_at' => optional($delivery->read_at)?->toIso8601String(),
            'is_unread' => $delivery->read_at === null,
            'date_display' => $this->formatDisplayDate(
                optional($delivery->sent_at)?->toIso8601String() ?? optional($delivery->created_at)?->toIso8601String(),
                true
            ),
        ];
    }

    private function transformLeaveWorkflowNotification(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];
        $title = trim((string) ($data['title'] ?? localize('leave_workflow', 'Leave workflow')));
        $message = trim((string) ($data['message'] ?? ''));
        $employeeName = trim((string) ($data['employee_name'] ?? ''));
        $leaveTypeName = trim((string) ($data['leave_type_name'] ?? ''));
        $fromDate = trim((string) ($data['from_date'] ?? ''));
        $toDate = trim((string) ($data['to_date'] ?? ''));
        $audienceLabel = trim((string) ($data['audience_label'] ?? ''));
        $stepName = trim((string) ($data['step_name'] ?? ''));
        $context = trim((string) ($data['context'] ?? ''));

        $metaParts = array_values(array_filter([
            $employeeName !== '' ? $employeeName : null,
            $leaveTypeName !== '' ? $leaveTypeName : null,
            trim($fromDate . ' - ' . $toDate, ' -') !== '' ? trim($fromDate . ' - ' . $toDate, ' -') : null,
        ]));

        return [
            'id' => (string) $notification->id,
            'notice_id' => 0,
            'title' => $title !== '' ? $title : localize('leave_workflow', 'Leave workflow'),
            'description' => $message !== '' ? $message : '-',
            'notice_by' => '',
            'notice_date' => $fromDate,
            'attachment_url' => null,
            'status' => 'sent',
            'channel' => 'database',
            'source' => 'leave_workflow',
            'type_label' => localize('leave_workflow_short', 'សុំច្បាប់'),
            'notification_id' => 'leave:' . (string) $notification->id,
            'sent_at' => optional($notification->created_at)?->toIso8601String(),
            'read_at' => optional($notification->read_at)?->toIso8601String(),
            'is_unread' => $notification->read_at === null,
            'audience_label' => $audienceLabel,
            'context_label' => $this->leaveContextLabel($context),
            'step_name' => $stepName,
            'meta' => implode(' | ', array_values(array_filter([
                $audienceLabel !== '' ? $audienceLabel : null,
                $stepName !== '' ? $stepName : null,
                $employeeName !== '' ? $employeeName : null,
                $leaveTypeName !== '' ? $leaveTypeName : null,
                trim($this->formatDisplayDate($fromDate) . ' - ' . $this->formatDisplayDate($toDate), ' -') !== ''
                    ? trim($this->formatDisplayDate($fromDate) . ' - ' . $this->formatDisplayDate($toDate), ' -')
                    : null,
            ]))),
            'date_display' => trim($this->formatDisplayDate($fromDate) . ' - ' . $this->formatDisplayDate($toDate), ' -'),
            'link' => trim((string) ($data['link'] ?? '')),
        ];
    }

    private function transformAttendanceWorkflowNotification(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];
        $title = trim((string) ($data['title'] ?? localize('attendance_workflow', 'Attendance workflow')));
        $message = trim((string) ($data['message'] ?? ''));
        $employeeName = trim((string) ($data['employee_name'] ?? ''));
        $attendanceDate = trim((string) ($data['attendance_date'] ?? ''));
        $reason = trim((string) ($data['reason'] ?? ''));
        $audienceLabel = trim((string) ($data['audience_label'] ?? ''));
        $stepName = trim((string) ($data['step_name'] ?? ''));
        $context = trim((string) ($data['context'] ?? ''));

        $metaParts = array_values(array_filter([
            $employeeName !== '' ? $employeeName : null,
            $attendanceDate !== '' ? $attendanceDate : null,
            $reason !== '' ? $reason : null,
        ]));

        return [
            'id' => (string) $notification->id,
            'notice_id' => 0,
            'title' => $title !== '' ? $title : localize('attendance_workflow', 'Attendance workflow'),
            'description' => $message !== '' ? $message : '-',
            'notice_by' => '',
            'notice_date' => $attendanceDate,
            'attachment_url' => null,
            'status' => 'sent',
            'channel' => 'database',
            'source' => 'attendance_workflow',
            'type_label' => localize('attendance_workflow_short', 'វត្តមាន'),
            'notification_id' => 'attendance:' . (string) $notification->id,
            'sent_at' => optional($notification->created_at)?->toIso8601String(),
            'read_at' => optional($notification->read_at)?->toIso8601String(),
            'is_unread' => $notification->read_at === null,
            'audience_label' => $audienceLabel,
            'context_label' => $this->attendanceContextLabel($context),
            'step_name' => $stepName,
            'meta' => implode(' | ', array_values(array_filter([
                $audienceLabel !== '' ? $audienceLabel : null,
                $stepName !== '' ? $stepName : null,
                $employeeName !== '' ? $employeeName : null,
                $this->formatDisplayDate($attendanceDate),
                $reason !== '' ? $reason : null,
            ]))),
            'date_display' => $this->formatDisplayDate($attendanceDate),
            'link' => trim((string) ($data['link'] ?? '')),
        ];
    }

    private function transformCorrespondenceWorkflowNotification(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];
        $context = trim((string) ($data['context'] ?? ''));
        $letterType = trim((string) ($data['letter_type'] ?? ''));
        $subject = trim((string) ($data['subject'] ?? ''));
        $registryNo = trim((string) ($data['registry_no'] ?? ''));
        $assignedAt = trim((string) ($data['assigned_at'] ?? ''));
        $assignedBy = trim((string) ($data['assigned_by'] ?? ''));
        $targetDepartment = trim((string) ($data['target_department_name'] ?? ''));

        $title = match (true) {
            $context === 'delegated' => localize('correspondence_notification_delegated', 'លិខិតត្រូវបានចាត់តាំងមកអ្នក'),
            $context === 'office_commented' => localize('correspondence_notification_office_commented', 'អង្គភាពបានផ្តល់យោបល់រួច សូមអនុប្រធានពិនិត្យបន្ត'),
            $context === 'deputy_reviewed' => localize('correspondence_notification_deputy_reviewed', 'អនុប្រធានបានពិនិត្យរួច សូមប្រធានមន្ទីរសម្រេច'),
            $letterType === 'outgoing' && $context === 'distributed' => localize('correspondence_notification_outgoing', 'លិខិតចេញមកដល់អ្នក ត្រូវចុចទទួល'),
            $context === 'distributed' => localize('correspondence_notification_distributed', 'មានលិខិតត្រូវអនុវត្ត'),
            default => localize('correspondence_notifications', 'ជូនដំណឹងលិខិតរដ្ឋបាល'),
        };

        return [
            'id' => (string) $notification->id,
            'notice_id' => 0,
            'title' => $title,
            'description' => $subject !== '' ? $subject : ($registryNo !== '' ? $registryNo : '-'),
            'notice_by' => $assignedBy,
            'notice_date' => $assignedAt,
            'attachment_url' => null,
            'status' => 'sent',
            'channel' => 'database',
            'source' => 'correspondence_workflow',
            'type_label' => localize('correspondence_short', 'លិខិត'),
            'notification_id' => 'correspondence:' . (string) $notification->id,
            'sent_at' => optional($notification->created_at)?->toIso8601String(),
            'read_at' => optional($notification->read_at)?->toIso8601String(),
            'is_unread' => $notification->read_at === null,
            'meta' => implode(' | ', array_values(array_filter([
                $targetDepartment !== '' ? $targetDepartment : null,
                $assignedBy !== '' ? $assignedBy : null,
                $this->formatDisplayDate($assignedAt, true),
            ]))),
            'date_display' => $this->formatDisplayDate($assignedAt, true),
            'link' => trim((string) ($data['link'] ?? '')),
        ];
    }

    private function mergedNotificationsForUser(Request $request, int $userId): array
    {
        $noticeRows = $this->baseQuery($userId)
            ->with([
                'notice' => function ($query): void {
                    $query->withoutGlobalScope('sortByLatest');
                },
            ])
            ->whereHas('notice')
            ->orderByRaw('COALESCE(sent_at, created_at) DESC')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (NoticeDelivery $row) => $this->transformDelivery($row))
            ->all();

        $leaveRows = $request->user()
            ?->notifications()
            ->where('type', LeaveWorkflowNotification::class)
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (DatabaseNotification $row) => $this->transformLeaveWorkflowNotification($row))
            ->all() ?? [];

        $attendanceRows = $request->user()
            ?->notifications()
            ->where('type', AttendanceWorkflowNotification::class)
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (DatabaseNotification $row) => $this->transformAttendanceWorkflowNotification($row))
            ->all() ?? [];

        $correspondenceRows = $request->user()
            ?->notifications()
            ->where('type', CorrespondenceAssignedNotification::class)
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (DatabaseNotification $row) => $this->transformCorrespondenceWorkflowNotification($row))
            ->all() ?? [];

        $items = array_merge($noticeRows, $leaveRows, $attendanceRows, $correspondenceRows);
        usort($items, function (array $left, array $right): int {
            $leftTime = strtotime((string) ($left['sent_at'] ?? '')) ?: 0;
            $rightTime = strtotime((string) ($right['sent_at'] ?? '')) ?: 0;

            return $rightTime <=> $leftTime;
        });

        return $items;
    }

    private function resolveNotificationRecord(Request $request, int $userId, string $notificationId): NoticeDelivery|DatabaseNotification|null
    {
        if (str_starts_with($notificationId, 'notice:')) {
            $id = (int) substr($notificationId, 7);
            if ($id <= 0) {
                return null;
            }

            return $this->baseQuery($userId)
                ->where('id', $id)
                ->with([
                    'notice' => function ($query): void {
                        $query->withoutGlobalScope('sortByLatest');
                    },
                ])
                ->first();
        }

        if (str_starts_with($notificationId, 'leave:')) {
            $id = substr($notificationId, 6);
            if ($id === '') {
                return null;
            }

            return $request->user()
                ?->notifications()
                ->where('type', LeaveWorkflowNotification::class)
                ->where('id', $id)
                ->first();
        }

        if (str_starts_with($notificationId, 'attendance:')) {
            $id = substr($notificationId, 11);
            if ($id === '') {
                return null;
            }

            return $request->user()
                ?->notifications()
                ->where('type', AttendanceWorkflowNotification::class)
                ->where('id', $id)
                ->first();
        }

        if (str_starts_with($notificationId, 'correspondence:')) {
            $id = substr($notificationId, 15);
            if ($id === '') {
                return null;
            }

            return $request->user()
                ?->notifications()
                ->where('type', CorrespondenceAssignedNotification::class)
                ->where('id', $id)
                ->first();
        }

        return null;
    }

    private function transformNotificationRecord(NoticeDelivery|DatabaseNotification $record): array
    {
        if ($record instanceof DatabaseNotification) {
            if ($record->type === AttendanceWorkflowNotification::class) {
                return $this->transformAttendanceWorkflowNotification($record);
            }

            if ($record->type === CorrespondenceAssignedNotification::class) {
                return $this->transformCorrespondenceWorkflowNotification($record);
            }

            return $this->transformLeaveWorkflowNotification($record);
        }

        return $this->transformDelivery($record);
    }

    private function attachmentUrl(?string $path): ?string
    {
        $normalized = trim((string) $path);
        if ($normalized === '') {
            return null;
        }

        if (str_starts_with($normalized, 'http://') || str_starts_with($normalized, 'https://')) {
            return $normalized;
        }

        if (str_starts_with($normalized, 'storage/') || str_starts_with($normalized, '/storage/')) {
            return url('/' . ltrim($normalized, '/'));
        }

        return url('/storage/' . ltrim($normalized, '/'));
    }

    private function unauthorizedResponse(): JsonResponse
    {
        return response()->json([
            'response' => [
                'status' => 'error',
                'message' => 'Unauthorized',
            ],
        ], 401);
    }

    private function formatDisplayDate(?string $raw, bool $includeTime = false): string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return '';
        }

        try {
            $date = \Carbon\Carbon::parse($raw);
            return $includeTime ? $date->format('d-m-Y H:i') : $date->format('d-m-Y');
        } catch (\Throwable $e) {
            return $raw;
        }
    }

    private function leaveContextLabel(string $context): string
    {
        return match ($context) {
            'submitted' => localize('notification_context_submitted', 'បានដាក់សំណើ'),
            'forwarded' => localize('notification_context_forwarded', 'បានបញ្ជូនបន្ត'),
            'approved' => localize('notification_context_approved', 'បានអនុម័ត'),
            'rejected' => localize('notification_context_rejected', 'បានបដិសេធ'),
            'handover_assigned' => localize('notification_context_handover', 'បានកំណត់ជាអ្នកជំនួស'),
            'pending_review' => localize('notification_context_pending_review', 'កំពុងរង់ចាំសកម្មភាព'),
            default => '',
        };
    }

    private function attendanceContextLabel(string $context): string
    {
        return match ($context) {
            'submitted' => localize('notification_context_submitted', 'បានដាក់សំណើ'),
            'pending_review' => localize('notification_context_pending_review', 'កំពុងរង់ចាំសកម្មភាព'),
            default => '',
        };
    }
}
