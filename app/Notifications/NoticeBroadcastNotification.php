<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\HumanResource\Entities\Notice;

class NoticeBroadcastNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Notice $notice)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'notice_id' => (int) $this->notice->id,
            'notice_uuid' => (string) $this->notice->uuid,
            'title' => (string) $this->notice->notice_type,
            'message' => (string) $this->notice->notice_descriptiion,
            'notice_date' => optional($this->notice->notice_date)->format('Y-m-d'),
            'notice_by' => (string) $this->notice->notice_by,
            'attachment' => $this->notice->notice_attachment ? asset('storage/' . $this->notice->notice_attachment) : null,
            'link' => route('notice.index'),
        ];
    }
}

