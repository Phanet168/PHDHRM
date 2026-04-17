<?php

namespace Modules\Correspondence\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\HumanResource\Entities\Department;

class CorrespondenceLetterDistribution extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING_ACK = 'pending_ack';
    public const STATUS_ACKNOWLEDGED = 'acknowledged';
    public const STATUS_FEEDBACK_SENT = 'feedback_sent';
    public const STATUS_CLOSED = 'closed';

    public const TYPE_TO = 'to';
    public const TYPE_CC = 'cc';

    protected $table = 'correspondence_letter_distributions';

    protected $fillable = [
        'letter_id',
        'target_department_id',
        'target_user_id',
        'distribution_type',
        'child_letter_id',
        'distributed_by',
        'distributed_at',
        'acknowledged_at',
        'acknowledgement_note',
        'feedback_note',
        'feedback_at',
        'status',
    ];

    protected $casts = [
        'distributed_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'feedback_at' => 'datetime',
    ];

    public function letter()
    {
        return $this->belongsTo(CorrespondenceLetter::class, 'letter_id');
    }

    public function targetDepartment()
    {
        return $this->belongsTo(Department::class, 'target_department_id');
    }

    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function childLetter()
    {
        return $this->belongsTo(CorrespondenceLetter::class, 'child_letter_id');
    }
}
