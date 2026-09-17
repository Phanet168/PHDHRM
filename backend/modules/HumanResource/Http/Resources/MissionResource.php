<?php

namespace Modules\HumanResource\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\HumanResource\Support\MissionAccess;

class MissionResource extends JsonResource
{
    public function toArray($request): array
    {
        $access = app(MissionAccess::class);
        $participant = $access->participant($this->resource);
        $team = $this->assignments->where('status', 'active')->map(fn ($a) => [
            'id' => (int) $a->employee_id,
            'name' => $a->employee?->full_name ?? '',
            'employee_id' => $a->employee?->employee_id,
            'initial' => mb_substr($a->employee?->full_name ?? '', 0, 1),
        ])->values();

        return [
            'id' => (int) $this->id, 'uuid' => $this->uuid, 'title' => $this->title,
            'mission_type' => $this->mission_type,
            'mission_type_label' => \Modules\HumanResource\Entities\Mission::TYPES[$this->mission_type] ?? $this->mission_type,
            'order_number' => $this->order_number,
            'order_details' => $this->order_details ?? (object) [],
            'order_officers' => $this->assignments->map(fn ($a) => [
                'employee_id' => (int) $a->employee_id,
                'name' => $a->name_on_order ?? $a->employee?->full_name,
                'position' => $a->position_on_order,
            ])->values(),
            'destination' => $this->destination, 'purpose' => $this->purpose,
            'start_date' => $this->start_date->toDateString(), 'end_date' => $this->end_date->toDateString(),
            'duration_days' => $this->start_date->diffInDays($this->end_date) + 1,
            'status' => $this->status, 'display_status' => $this->display_status,
            'assignments_count' => $team->count(), 'team_members' => $team,
            'assigner' => $this->assigner ? ['id' => (int) $this->assigner->id, 'name' => $this->assigner->full_name,
                'initial' => mb_substr($this->assigner->full_name ?? '', 0, 1)] : null,
            'approved_at' => $this->approved_at?->toIso8601String(), 'rejected_reason' => $this->rejected_reason,
            'started_at' => $this->started_at?->toIso8601String(), 'completed_at' => $this->completed_at?->toIso8601String(),
            'documents' => $this->documents->map(fn ($d) => [
                'id' => (int) $d->id, 'name' => $d->name, 'size' => (int) $d->size, 'mime_type' => $d->mime_type,
                'download_url' => route('api.v1.missions.documents.download', [$this->id, $d->id]),
            ])->values(),
            'reports' => $this->whenLoaded('reports', fn () => $this->reports->map(fn ($r) => [
                'id' => (int) $r->id, 'employee_id' => (int) $r->employee_id, 'summary' => $r->summary,
                'submitted_at' => $r->updated_at->toIso8601String(),
            ])),
            'actions' => [
                'can_start' => $participant && $this->status === 'approved' && ! $this->started_at && ! $this->completed_at
                    && now()->toDateString() >= $this->start_date->toDateString() && now()->toDateString() <= $this->end_date->toDateString(),
                'can_report' => $participant && $this->status === 'approved' && (bool) $this->started_at && ! $this->completed_at,
            ],
        ];
    }
}
