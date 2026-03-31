<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeEducationHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'institution_name',
        'start_date',
        'end_date',
        'degree_level',
        'major_subject',
        'note',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
