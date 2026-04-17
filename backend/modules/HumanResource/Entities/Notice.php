<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\HumanResource\Entities\WorkflowInstance;

class Notice extends Model
{
    use HasFactory,SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_SENT = 'sent';
    public const STATUS_PARTIAL_FAILED = 'partial_failed';
    public const STATUS_ARCHIVED = 'archived';

    public const AUDIENCE_ALL = 'all';
    public const AUDIENCE_USERS = 'users';
    public const AUDIENCE_ROLES = 'roles';
    public const AUDIENCE_DEPARTMENTS = 'departments';

    protected $fillable =  [
        'id',
        'uuid',
        'notice_type',
        'notice_descriptiion', 
        'notice_date',
        'notice_attachment',
        'notice_by',
        'status',
        'audience_type',
        'audience_targets',
        'delivery_channels',
        'scheduled_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejected_reason',
        'sent_by',
        'sent_at',
        'delivery_total',
        'delivery_success',
        'delivery_failed',
        'delivery_last_error',
        'workflow_instance_id',
        'workflow_status',
        'workflow_current_step_order',
        'workflow_last_action_at',
        'workflow_snapshot_json',
    ];

    protected $casts = [
        'notice_date' => 'date',
        'scheduled_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'sent_at' => 'datetime',
        'audience_targets' => 'array',
        'delivery_channels' => 'array',
        'delivery_total' => 'integer',
        'delivery_success' => 'integer',
        'delivery_failed' => 'integer',
        'workflow_instance_id' => 'integer',
        'workflow_current_step_order' => 'integer',
        'workflow_last_action_at' => 'datetime',
        'workflow_snapshot_json' => 'array',
    ];
    

    protected static function boot()
    {
        parent::boot();
        if(Auth::check()){
            self::creating(function($model) {
                $model->uuid = (string) Str::uuid();
                $model->created_by = Auth::id();
            });

            self::updating(function($model) {
                $model->updated_by = Auth::id();
            });

            self::deleted(function($model){
                $model->updated_by = Auth::id();
                $model->save();
            });
        }

        static::addGlobalScope('sortByLatest', function (Builder $builder) {
            $builder->orderByDesc('id');
        });

    }

    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_PENDING_APPROVAL,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_SCHEDULED,
            self::STATUS_SENT,
            self::STATUS_PARTIAL_FAILED,
            self::STATUS_ARCHIVED,
        ];
    }

    public static function audienceTypes(): array
    {
        return [
            self::AUDIENCE_ALL,
            self::AUDIENCE_USERS,
            self::AUDIENCE_ROLES,
            self::AUDIENCE_DEPARTMENTS,
        ];
    }

    public function deliveries()
    {
        return $this->hasMany(NoticeDelivery::class, 'notice_id', 'id');
    }

    public function workflowInstance()
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }

    public function getAudienceUsersAttribute(): array
    {
        return array_map('intval', (array) data_get($this->audience_targets, 'users', []));
    }

    public function getAudienceRolesAttribute(): array
    {
        return array_map('intval', (array) data_get($this->audience_targets, 'roles', []));
    }

    public function getAudienceDepartmentsAttribute(): array
    {
        return array_map('intval', (array) data_get($this->audience_targets, 'departments', []));
    }

    public function getNormalizedChannelsAttribute(): array
    {
        $channels = array_filter(array_map('trim', (array) ($this->delivery_channels ?? [])));

        if (empty($channels)) {
            return ['in_app'];
        }

        return array_values(array_unique($channels));
    }
    
    
}
