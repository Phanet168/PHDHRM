<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceDailySnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'snapshot_date',
        'shift_id',
        'attendance_status',
        'in_time',
        'out_time',
        'worked_minutes',
        'late_minutes',
        'early_leave_minutes',
        'leave_id',
        'mission_id',
        'is_holiday',
        'is_day_off',
        'policy_payload',
        'computed_at',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'in_time' => 'datetime',
        'out_time' => 'datetime',
        'is_holiday' => 'boolean',
        'is_day_off' => 'boolean',
        'policy_payload' => 'array',
        'computed_at' => 'datetime',
    ];
}
