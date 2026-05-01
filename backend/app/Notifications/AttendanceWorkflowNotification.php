<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\HumanResource\Entities\AttendanceAdjustment;

class AttendanceWorkflowNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly AttendanceAdjustment $adjustment,
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
        $employeeName = trim((string) ($this->adjustment->employee?->full_name ?? ''));
        $attendanceDate = optional($this->adjustment->attendance?->attendance_date)->format('Y-m-d')
            ?? (string) ($this->adjustment->attendance?->attendance_date ?? '');

        return array_merge([
            'type' => 'attendance_workflow',
            'context' => $this->context,
            'adjustment_id' => (int) $this->adjustment->id,
            'adjustment_uuid' => (string) $this->adjustment->uuid,
            'employee_id' => (int) ($this->adjustment->employee_id ?? 0),
            'employee_name' => $employeeName,
            'attendance_id' => (int) ($this->adjustment->attendance_id ?? 0),
            'attendance_date' => $attendanceDate,
            'old_time' => optional($this->adjustment->old_time)?->format('Y-m-d H:i:s')
                ?? (string) ($this->adjustment->old_time ?? ''),
            'new_time' => optional($this->adjustment->new_time)?->format('Y-m-d H:i:s')
                ?? (string) ($this->adjustment->new_time ?? ''),
            'reason' => trim((string) ($this->adjustment->reason ?? '')),
            'workflow_status' => (string) ($this->adjustment->status ?? ''),
            'title' => $this->title,
            'message' => $this->message,
            'link' => $this->link,
        ], $this->extra);
    }
}
