<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\LeaveType;

class ApplyLeave extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'id',
        'uuid',
        'employee_id',
        'handover_employee_id',
        'leave_type_id',
        'academic_year_id',
        'leave_apply_start_date',
        'leave_apply_end_date',
        'leave_apply_date',
        'total_apply_day',
        'leave_approved_start_date',
        'leave_approved_end_date',
        'total_approved_day',
        'is_approved_by_manager',
        'approved_by_manager',
        'manager_approved_date',
        'manager_approved_description',
        'leave_approved_date',
        'approved_by',
        'reason',
        'location',
        'is_approved',
        'workflow_instance_id',
        'workflow_status',
        'workflow_current_step_order',
        'workflow_last_action_at',
        'workflow_snapshot_json',
    ];

    protected $casts = [
        'leave_apply_start_date' => 'date',
        'leave_apply_end_date' => 'date',
        'leave_apply_date' => 'datetime',
        'leave_approved_start_date' => 'date',
        'leave_approved_end_date' => 'date',
        'leave_approved_date' => 'datetime',
        'manager_approved_date' => 'datetime',
        'workflow_instance_id' => 'integer',
        'workflow_current_step_order' => 'integer',
        'workflow_last_action_at' => 'datetime',
        'workflow_snapshot_json' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        self::creating(function ($model) {
            if (blank($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }

            if (Auth::check()) {
                $model->created_by = Auth::id();
            }
        });

        self::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });

        self::deleted(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
                $model->save();
            }
        });

        static::addGlobalScope('sortByLatest', function (Builder $builder) {
            $builder->orderByDesc('id');
        });
    }


    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id')->withoutGlobalScopes();
    }
    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function workflowInstance()
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }

    public function handoverEmployee()
    {
        return $this->belongsTo(Employee::class, 'handover_employee_id', 'id')->withoutGlobalScopes();
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

}
