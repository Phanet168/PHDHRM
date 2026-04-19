<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveEntitlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'academic_year_id',
        'entitled_days',
        'used_days',
        'remaining_days',
        'last_calculated_at',
    ];

    protected $casts = [
        'entitled_days' => 'decimal:2',
        'used_days' => 'decimal:2',
        'remaining_days' => 'decimal:2',
        'last_calculated_at' => 'datetime',
    ];
}
