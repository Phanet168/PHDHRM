<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Modules\Correspondence\Entities\CorrespondenceLetter;
use Modules\Correspondence\Entities\CorrespondenceLetterDistribution;

class CorrespondenceAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly CorrespondenceLetter $letter,
        private readonly ?CorrespondenceLetterDistribution $distribution,
        private readonly ?User $assignedBy,
        private readonly string $context = 'distributed',
        private readonly ?string $note = null,
        private readonly ?string $targetDepartmentName = null,
        private readonly ?int $targetDepartmentId = null,
        private readonly ?int $targetUserId = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $distribution = $this->distribution;
        $assignedAt = $distribution?->distributed_at ?? now();

        $targetDepartmentId = $this->targetDepartmentId
            ?? ((int) ($distribution?->target_department_id ?? 0) ?: null);

        $targetUserId = $this->targetUserId
            ?? ((int) ($distribution?->target_user_id ?? 0) ?: null);

        return [
            'type' => 'correspondence_assignment',
            'context' => $this->context,
            'letter_id' => (int) $this->letter->id,
            'distribution_id' => $distribution ? (int) $distribution->id : null,
            'registry_no' => (string) ($this->letter->registry_no ?? ''),
            'letter_no' => (string) ($this->letter->letter_no ?? ''),
            'subject' => (string) ($this->letter->subject ?? ''),
            'letter_type' => (string) ($this->letter->letter_type ?? ''),
            'from_org' => (string) ($this->letter->from_org ?? ''),
            'to_org' => (string) ($this->letter->to_org ?? ''),
            'assigned_by' => (string) ($this->assignedBy?->full_name ?? ''),
            'assigned_by_id' => (int) ($this->assignedBy?->id ?? 0) ?: null,
            'assigned_at' => $assignedAt ? $assignedAt->format('Y-m-d H:i') : null,
            'note' => $this->note !== '' ? $this->note : null,
            'target_department_id' => $targetDepartmentId,
            'target_department_name' => $this->targetDepartmentName,
            'target_user_id' => $targetUserId,
            'link' => route('correspondence.show', $this->letter->id),
        ];
    }
}
