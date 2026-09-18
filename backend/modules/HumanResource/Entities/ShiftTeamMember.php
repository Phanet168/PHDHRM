<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Model;

class ShiftTeamMember extends Model
{
    protected $fillable = [
        'shift_team_id',
        'employee_id',
        'is_active',
        'joined_at',
        'left_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'joined_at' => 'date',
        'left_at' => 'date',
    ];

    public function team()
    {
        return $this->belongsTo(ShiftTeam::class, 'shift_team_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
