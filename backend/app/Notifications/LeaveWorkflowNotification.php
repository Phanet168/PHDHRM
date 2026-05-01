<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\HumanResource\Entities\ApplyLeave;

class LeaveWorkflowNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly ApplyLeave $leave,
        private readonly string $context,
        private readonly string $title,
        private readonly string $message,
        private readonly string $link,
        private readonly array $extra = [],
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $leaveTypeName = trim((string) ($this->leave->leaveType?->display_name ?? ''));
        $employeeName = trim((string) ($this->leave->employee?->full_name ?? ''));

        return array_merge([
            'type' => 'leave_workflow',
            'context' => $this->context,
            'leave_id' => (int) $this->leave->id,
            'leave_uuid' => (string) $this->leave->uuid,
            'employee_id' => (int) ($this->leave->employee_id ?? 0),
            'employee_name' => $employeeName,
            'leave_type_name' => $leaveTypeName,
            'from_date' => optional($this->leave->leave_apply_start_date)->format('Y-m-d')
                ?? (string) ($this->leave->leave_apply_start_date ?? ''),
            'to_date' => optional($this->leave->leave_apply_end_date)->format('Y-m-d')
                ?? (string) ($this->leave->leave_apply_end_date ?? ''),
            'total_days' => (int) ($this->leave->total_apply_day ?? 0),
            'workflow_status' => (string) ($this->leave->workflow_status ?? ''),
            'title' => $this->title,
            'message' => $this->message,
            'link' => $this->link,
        ], $this->extra);
    }
}
