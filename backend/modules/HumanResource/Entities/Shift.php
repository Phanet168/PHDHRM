<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shift extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'start_time',
        'end_time',
        'is_cross_day',
        'grace_late_minutes',
        'grace_early_leave_minutes',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_cross_day' => 'boolean',
        'is_active' => 'boolean',
    ];
}
