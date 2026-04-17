<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Model;

class EmployeeVaccination extends Model
{
    protected $fillable = [
        'employee_id',
        'vaccine_name',
        'vaccine_protection',
        'vaccination_date',
        'vaccination_place',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id');
    }
}

