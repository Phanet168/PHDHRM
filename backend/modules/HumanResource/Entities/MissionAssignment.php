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
    ];

    public function mission()
    {
        return $this->belongsTo(Mission::class, 'mission_id', 'id');
    }
}
