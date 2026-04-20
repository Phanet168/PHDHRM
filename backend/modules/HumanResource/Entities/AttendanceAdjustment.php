<?php

namespace Modules\HumanResource\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AttendanceAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'employee_id',
        'attendance_id',
        'old_time',
        'new_time',
        'old_machine_state',
        'new_machine_state',
        'reason',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
        'rejected_reason',
        'workflow_instance_id',
        'audit_meta',
    ];

    protected $casts = [
        'old_time' => 'datetime',
        'new_time' => 'datetime',
        'approved_at' => 'datetime',
        'audit_meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (!$model->uuid) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class, 'attendance_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
