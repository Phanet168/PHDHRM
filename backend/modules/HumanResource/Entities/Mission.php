<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mission extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'title',
        'start_date',
        'end_date',
        'destination',
        'purpose',
        'order_attachment_path',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
        'rejected_reason',
        'workflow_instance_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function assignments()
    {
        return $this->hasMany(MissionAssignment::class, 'mission_id', 'id');
    }
}
