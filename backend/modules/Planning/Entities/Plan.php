<?php

namespace Modules\Planning\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Planning\Support\GeneratesUuid;

class Plan extends Model
{
    use GeneratesUuid;
    use HasFactory;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CONSOLIDATED = 'consolidated';

    protected $fillable = [
        'org_unit_id',
        'program_id',
        'sub_program_id',
        'activity_cluster_id',
        'plan_type',
        'title',
        'reference_no',
        'year',
        'start_year',
        'end_year',
        'period_type',
        'period_no',
        'workflow_status',
        'is_locked',
        'objective',
        'summary',
        'background',
        'assumptions',
        'total_estimated_cost',
        'total_personnel_cost',
        'total_revenue_amount',
        'submitted_at',
        'submitted_by',
        'approved_at',
        'approved_by',
        'reviewed_at',
        'reviewed_by',
        'rejected_at',
        'rejected_by',
        'rejection_reason',
        'consolidated_at',
        'consolidated_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_locked' => 'bool',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'rejected_at' => 'datetime',
        'consolidated_at' => 'datetime',
        'total_estimated_cost' => 'decimal:2',
        'total_personnel_cost' => 'decimal:2',
        'total_revenue_amount' => 'decimal:2',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function orgUnit()
    {
        return $this->belongsTo(OrgUnit::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function subProgram()
    {
        return $this->belongsTo(SubProgram::class);
    }

    public function activityCluster()
    {
        return $this->belongsTo(ActivityCluster::class);
    }

    public function items()
    {
        return $this->hasMany(PlanItem::class)->orderBy('sort_order');
    }

    public function revenueLines()
    {
        return $this->hasMany(PlanRevenueLine::class)->orderBy('sort_order');
    }

    public function approvals()
    {
        return $this->hasMany(PlanApproval::class)->latest('acted_at');
    }

    public function comments()
    {
        return $this->hasMany(PlanComment::class)->latest();
    }

    public function attachments()
    {
        return $this->hasMany(PlanAttachment::class)->latest();
    }

    public function childRollups()
    {
        return $this->hasMany(PlanRollup::class, 'parent_plan_id');
    }

    public function parentRollups()
    {
        return $this->hasMany(PlanRollup::class, 'child_plan_id');
    }

    public function isEditable(): bool
    {
        return in_array($this->workflow_status, [self::STATUS_DRAFT, self::STATUS_REJECTED], true) && !$this->is_locked;
    }
}
