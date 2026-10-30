<?php

namespace Modules\HumanResource\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\HumanResource\Enums\Mission\MissionStatus;
use Modules\HumanResource\Support\MissionAccess;

class MissionResource extends JsonResource
{
    public function toArray($request): array
    {
        $access = app(MissionAccess::class);
        $participant = $access->participant($this->resource);
        $myEmployeeId = $access->employeeId();
        $team = $this->assignments->where('status', 'active')->map(fn ($a) => [
            'id' => (int) $a->employee_id,
            'name' => $a->employee?->full_name ?? '',
            'employee_id' => $a->employee?->employee_id,
            'initial' => mb_substr($a->employee?->full_name ?? '', 0, 1),
        ])->values();
        $lifecycle = $this->lifecycle_status;
        $details = $this->order_details ?? [];
        $orderIssued = $this->resource->isOrderIssued();

        return [
            'id' => (int) $this->id, 'uuid' => $this->uuid, 'title' => $this->title,
            'mission_type' => $this->mission_type,
            'mission_type_label' => \Modules\HumanResource\Entities\Mission::TYPES[$this->mission_type] ?? $this->mission_type,
            'order_number' => $this->order_number,
            'order_details' => $this->order_details ?? (object) [],
            'order_officers' => $this->assignments->where('status', 'active')->map(fn ($a) => [
                'employee_id' => (int) $a->employee_id,
                'honorific' => $a->honorific_on_order,
                'name' => $a->name_on_order ?? $a->employee?->full_name,
                'position' => $a->position_on_order,
            ])->values(),
            // Everything printed on the order / travel-visa letters, already resolved for display.
            'order' => [
                'issued' => $orderIssued,
                'issued_at' => $this->issued_at?->toIso8601String(),
                'number' => $this->order_number,
                'letter_code' => $details['letter_code'] ?? null,
                'issued_on' => $details['issued_on'] ?? null,
                'lunar_date' => $details['lunar_date'] ?? null,
                'issue_place' => $details['issue_place'] ?? null,
                'references' => $this->resource->referenceLines(),
                'transport' => $this->resource->transportLabel(),
                'funding' => $this->resource->fundingLabel(),
                'signatory_title' => $details['signatory_title'] ?? null,
            ],
            'destination' => $this->destination, 'purpose' => $this->purpose,
            'start_date' => $this->start_date->toDateString(), 'end_date' => $this->end_date->toDateString(),
            'duration_days' => $this->start_date->diffInDays($this->end_date) + 1,
            'status' => $this->status, 'display_status' => $this->display_status,
            'lifecycle_status' => $lifecycle?->value,
            'lifecycle_label' => $lifecycle?->label(),
            'assignments_count' => $team->count(), 'team_members' => $team,
            // The caller is an active member of the team (a requester may not be).
            'is_participant' => $participant,
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
                'employee_name' => ($a = $this->assignments->firstWhere('employee_id', $r->employee_id))
                    ? trim($a->honorific_on_order.' '.($a->name_on_order ?? $a->employee?->full_name)) : null,
                'is_mine' => $myEmployeeId !== null && (int) $r->employee_id === $myEmployeeId,
                // The official report's sections ៤ / ៥ / ៦; null for older summary-only reports.
                'activities' => $r->activities,
                'results' => $r->results === null ? [] : array_values(array_filter(array_map('trim', preg_split('/\R/u', $r->results) ?: []))),
                'recommendations' => $r->recommendations,
                'submitted_at' => $r->updated_at->toIso8601String(),
            ])),
            'timeline' => $this->whenLoaded('statusHistories', fn () => $this->statusHistories->map(fn ($h) => [
                'status' => $h->to_status?->value ?? (string) $h->to_status,
                'label' => $h->to_status instanceof MissionStatus ? $h->to_status->label() : (string) $h->to_status,
                'actor' => $h->actor?->full_name,
                'note' => $h->reason,
                'at' => $h->occurred_at?->toIso8601String(),
            ])->values()),
            'letters' => [
                'order' => $orderIssued,
                'travel_visa' => $orderIssued && $participant,
                'report' => $orderIssued && $participant && $this->relationLoaded('reports')
                    && $this->reports->contains(fn ($r) => (int) $r->employee_id === $myEmployeeId && $r->submitted_at && trim((string) $r->summary) !== ''),
            ],
            'actions' => [
                'can_start' => $participant && $this->status === 'approved' && ! $this->started_at && ! $this->completed_at
                    && ($this->resource->isLegacyFlow() || $lifecycle === MissionStatus::Issued)
                    && now()->toDateString() >= $this->start_date->toDateString() && now()->toDateString() <= $this->end_date->toDateString(),
                'can_report' => $participant && $this->status === 'approved' && (bool) $this->started_at && ! $this->completed_at,
            ],
        ];
    }
}
