<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MissionAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'mission_id',
        'employee_id',
        'status',
        'assignment_note',
        'name_on_order',
        'position_on_order',
        'sort_order',
        'honorific_on_order',
        'department_on_order',
    ];

    protected $casts = ['sort_order' => 'integer'];

    public function mission()
    {
        return $this->belongsTo(Mission::class, 'mission_id', 'id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id');
    }
}
