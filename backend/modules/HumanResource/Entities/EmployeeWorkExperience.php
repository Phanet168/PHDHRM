<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeWorkExperience extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'sector_category',
        'start_date',
        'end_date',
        'position_title',
        'institution_name',
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
