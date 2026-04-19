<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftRoster extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'employee_id',
        'shift_id',
        'roster_date',
        'is_day_off',
        'is_holiday',
        'created_by',
        'note',
    ];

    protected $casts = [
        'roster_date' => 'date',
        'is_day_off' => 'boolean',
        'is_holiday' => 'boolean',
    ];

    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift_id', 'id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id');
    }
}
