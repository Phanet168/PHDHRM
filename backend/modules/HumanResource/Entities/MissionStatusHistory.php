<?php

namespace Modules\HumanResource\Entities;

use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\HumanResource\Enums\Mission\ApprovalAction;
use Modules\HumanResource\Enums\Mission\MissionStatus;

class MissionStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'mission_status_histories';

    // Audit fields are assigned explicitly by trusted server code, never fill(request).
    protected $guarded = ['*'];

    protected $casts = [
        'from_status' => MissionStatus::class, 'to_status' => MissionStatus::class,
        'revision' => 'integer', 'metadata' => 'array', 'occurred_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new DomainException('Mission history is append-only.'));
        static::deleting(fn () => throw new DomainException('Mission history is append-only.'));
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }

    public function workflowAction(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstanceAction::class);
    }

    public function getApprovalActionAttribute(): ?ApprovalAction
    {
        // Full history also includes submit/issue/complete; the shared workflow's
        // action_type must not be cast globally to a mission-only approval enum.
        return ApprovalAction::tryFrom((string) $this->action);
    }
}
