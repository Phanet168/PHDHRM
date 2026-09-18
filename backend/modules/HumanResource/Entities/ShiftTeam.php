<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShiftTeam extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'department_id',
        'name',
        'code',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function members()
    {
        return $this->hasMany(ShiftTeamMember::class);
    }

    public function activeEmployees()
    {
        return $this->belongsToMany(Employee::class, 'shift_team_members')
            ->wherePivot('is_active', true)
            ->withPivot(['is_active', 'joined_at', 'left_at']);
    }
}
