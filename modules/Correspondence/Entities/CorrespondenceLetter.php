<?php

namespace Modules\Correspondence\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\HumanResource\Entities\Department;

class CorrespondenceLetter extends Model
{
    use SoftDeletes;

    public const TYPE_INCOMING = 'incoming';
    public const TYPE_OUTGOING = 'outgoing';

    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_ARCHIVED = 'archived';

    public const STEP_INCOMING_RECEIVED = 'incoming_received';
    public const STEP_INCOMING_DELEGATED = 'incoming_delegated';
    public const STEP_INCOMING_OFFICE_COMMENT = 'incoming_office_comment';
    public const STEP_INCOMING_DEPUTY_REVIEW = 'incoming_deputy_review';
    public const STEP_INCOMING_DIRECTOR_DECISION = 'incoming_director_decision';
    public const STEP_INCOMING_DISTRIBUTED = 'incoming_distributed';
    public const STEP_OUTGOING_DRAFT = 'outgoing_draft';
    public const STEP_OUTGOING_DISTRIBUTED = 'outgoing_distributed';
    public const STEP_CLOSED = 'closed';

    public const DECISION_APPROVED = 'approved';
    public const DECISION_REJECTED = 'rejected';

    protected $table = 'correspondence_letters';

    protected $fillable = [
        'letter_type',
        'registry_no',
        'letter_no',
        'subject',
        'from_org',
        'to_org',
        'priority',
        'status',
        'letter_date',
        'received_date',
        'due_date',
        'summary',
        'attachment_path',
        'origin_department_id',
        'assigned_department_id',
        'current_handler_user_id',
        'current_step',
        'final_decision',
        'decision_note',
        'decision_at',
        'completed_at',
        'parent_letter_id',
        'source_distribution_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'letter_date' => 'date',
        'received_date' => 'date',
        'due_date' => 'date',
        'decision_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public static function defaultStepForType(string $type): string
    {
        return $type === self::TYPE_OUTGOING
            ? self::STEP_OUTGOING_DRAFT
            : self::STEP_INCOMING_RECEIVED;
    }

    public static function stepLabels(): array
    {
        return [
            self::STEP_INCOMING_RECEIVED => localize('letter_step_incoming_received', 'Letter received'),
            self::STEP_INCOMING_DELEGATED => localize('letter_step_incoming_delegated', 'Delegated/assigned'),
            self::STEP_INCOMING_OFFICE_COMMENT => localize('letter_step_incoming_office_comment', 'Office comment'),
            self::STEP_INCOMING_DEPUTY_REVIEW => localize('letter_step_incoming_deputy_review', 'Deputy review'),
            self::STEP_INCOMING_DIRECTOR_DECISION => localize('letter_step_incoming_director_decision', 'Director decision'),
            self::STEP_INCOMING_DISTRIBUTED => localize('letter_step_incoming_distributed', 'Distributed to recipients'),
            self::STEP_OUTGOING_DRAFT => localize('letter_step_outgoing_draft', 'Outgoing draft'),
            self::STEP_OUTGOING_DISTRIBUTED => localize('letter_step_outgoing_distributed', 'Outgoing distributed'),
            self::STEP_CLOSED => localize('letter_step_closed', 'Closed'),
        ];
    }

    public function getCurrentStepLabelAttribute(): string
    {
        return (string) (self::stepLabels()[$this->current_step] ?? ucfirst(str_replace('_', ' ', (string) $this->current_step)));
    }

    public function originDepartment()
    {
        return $this->belongsTo(Department::class, 'origin_department_id');
    }

    public function assignedDepartment()
    {
        return $this->belongsTo(Department::class, 'assigned_department_id');
    }

    public function currentHandler()
    {
        return $this->belongsTo(User::class, 'current_handler_user_id');
    }

    public function actions()
    {
        return $this->hasMany(CorrespondenceLetterAction::class, 'letter_id')->orderBy('id');
    }

    public function distributions()
    {
        return $this->hasMany(CorrespondenceLetterDistribution::class, 'letter_id')->orderByDesc('id');
    }

    public function parentLetter()
    {
        return $this->belongsTo(self::class, 'parent_letter_id');
    }

    public function childLetters()
    {
        return $this->hasMany(self::class, 'parent_letter_id')->orderByDesc('id');
    }

    public function sourceDistribution()
    {
        return $this->belongsTo(CorrespondenceLetterDistribution::class, 'source_distribution_id');
    }
}
