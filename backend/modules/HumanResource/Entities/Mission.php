<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use DomainException;
use Modules\HumanResource\Enums\Mission\MissionCreationPath;
use Modules\HumanResource\Enums\Mission\MissionCreationSource;
use Modules\HumanResource\Enums\Mission\MissionStatus;

class Mission extends Model
{
    use HasFactory, SoftDeletes;

    protected $attributes = ['mission_type' => 'other'];

    public const TYPES = [
        'inspection' => 'ចុះត្រួតពិនិត្យមណ្ឌល/អង្គភាព',
        'community_visit' => 'ចុះបំពេញការងារតាមភូមិ/សហគមន៍',
        'provincial_assignment' => 'បំពេញការងារតាមខេត្ត',
        'training' => 'បណ្ដុះបណ្ដាល',
        'meeting' => 'ប្រជុំ/សិក្ខាសាលា',
        'other' => 'បេសកកម្មផ្សេងៗ',
    ];

    protected $fillable = [
        'uuid',
        'title',
        'mission_type',
        'order_number',
        'order_details',
        'start_date',
        'end_date',
        'destination',
        'purpose',
        'order_attachment_path',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
        'rejected_reason',
        'workflow_instance_id',
        'started_at',
        'started_by',
        'completed_at',
        'completed_by',
        'creation_path',
        'creation_source',
        'creation_reason',
        'requester_employee_id',
        'source_department_id',
        'issuer_department_id',
        'mission_type_id',
        'funding_source_id',
        'sponsor_name',
        'transport_type_id',
        'transport_description',
        'workflow_status',
        'workflow_current_step_order',
        'workflow_last_action_at',
        'workflow_snapshot_json',
    ];

    protected $casts = [
        'order_details' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'creation_path' => MissionCreationPath::class,
        'creation_source' => MissionCreationSource::class,
        'lifecycle_status' => MissionStatus::class,
        'revision' => 'integer',
        'lock_version' => 'integer',
        'submitted_at' => 'datetime',
        'issued_at' => 'datetime',
        'actual_returned_at' => 'datetime',
        'report_due_at' => 'datetime',
        'workflow_current_step_order' => 'integer',
        'workflow_last_action_at' => 'datetime',
        'workflow_snapshot_json' => 'array',
    ];

    /** New Phase-3 flows (employee_request/direct) versus pre-existing single-tier missions. */
    public function isLegacyFlow(): bool
    {
        return $this->creation_path === null;
    }

    protected static function booted(): void
    {
        static::saving(function (self $mission): void {
            $completing = ($mission->isDirty('lifecycle_status') && $mission->lifecycle_status === MissionStatus::Completed)
                || ($mission->isDirty('completed_at') && $mission->completed_at !== null && $mission->getOriginal('completed_at') === null);
            if ($completing && ! $mission->hasSubmittedReport()) {
                throw new DomainException('A formally submitted mission report is required before completion.');
            }
        });
    }

    public function hasSubmittedReport(): bool
    {
        return $this->exists && $this->reports()->submitted()->exists();
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function requesterEmployee()
    {
        return $this->belongsTo(Employee::class, 'requester_employee_id');
    }

    public function sourceDepartment()
    {
        return $this->belongsTo(Department::class, 'source_department_id');
    }

    public function issuerDepartment()
    {
        return $this->belongsTo(Department::class, 'issuer_department_id');
    }

    public function missionType()
    {
        return $this->belongsTo(MissionType::class);
    }

    public function fundingSource()
    {
        return $this->belongsTo(\Modules\Planning\Entities\FundingSource::class);
    }

    public function transportType()
    {
        return $this->belongsTo(TransportType::class);
    }

    public function currentWorkflow()
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }

    public function destinations()
    {
        return $this->hasMany(MissionDestination::class)->orderBy('sort_order')->orderBy('id');
    }

    public function statusHistories()
    {
        return $this->hasMany(MissionStatusHistory::class)->orderBy('occurred_at')->orderBy('id');
    }

    public function assignments()
    {
        return $this->hasMany(MissionAssignment::class, 'mission_id', 'id')->orderBy('sort_order')->orderBy('id');
    }

    public function assigner()
    {
        return $this->belongsTo(\App\Models\User::class, 'requested_by');
    }

    public function documents()
    {
        return $this->hasMany(MissionDocument::class);
    }

    public function reports()
    {
        return $this->hasMany(MissionReport::class);
    }

    /**
     * The order letter is final: legacy missions once approved, new-flow
     * missions from the moment the Letter Manager issues the order.
     */
    public function isOrderIssued(): bool
    {
        if ($this->isLegacyFlow()) {
            return $this->status === 'approved';
        }

        return in_array($this->lifecycle_status, [
            MissionStatus::Issued, MissionStatus::OnMission, MissionStatus::PendingReport, MissionStatus::Completed,
        ], true);
    }

    /** "មធ្យោបាយធ្វើដំណើរ" on the letters: a typed override, else the chosen transport type (+ its note). */
    public function transportLabel(): ?string
    {
        $override = trim((string) ($this->order_details['transport'] ?? ''));
        if ($override !== '') {
            return $override;
        }
        $type = $this->transport_type_id ? ($this->transportType?->name_km ?: $this->transportType?->name) : null;
        $label = trim(implode(' ', array_filter([$type, $this->transport_description])));

        return $label !== '' ? $label : null;
    }

    /** "ឧបត្ថម្ភថវិកា" on the letters: a typed override, else the sponsor, else the funding source. */
    public function fundingLabel(): ?string
    {
        foreach ([$this->order_details['funding_source'] ?? null, $this->sponsor_name] as $value) {
            if (trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return $this->funding_source_id ? ($this->fundingSource?->name_km ?: $this->fundingSource?->name) : null;
    }

    /** The "យោង" block, one reference per line (leading dashes are added when printing). */
    public function referenceLines(): array
    {
        $text = (string) ($this->order_details['reference'] ?? '');

        return array_values(array_filter(array_map(
            fn (string $line) => trim(preg_replace('/^\s*[-–•]\s*/u', '', $line)),
            preg_split('/\R/u', $text) ?: []
        ), fn (string $line) => $line !== ''));
    }

    public function getDisplayStatusAttribute(): string
    {
        if ($this->status !== 'approved') {
            return $this->status;
        }

        return $this->completed_at ? 'completed' : ($this->started_at ? 'in_progress' : 'approved');
    }
}
