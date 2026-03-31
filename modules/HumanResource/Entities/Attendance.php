<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\HumanResource\Entities\Department;

class Attendance extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'id',
        'employee_id',
        'workplace_id',
        'machine_id',
        'machine_state',
        'attendance_source',
        'source_reference',
        'scan_latitude',
        'scan_longitude',
        'time',
        'exception_flag',
        'exception_reason',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function workplace()
    {
        return $this->belongsTo(Department::class, 'workplace_id', 'id');
    }

}
