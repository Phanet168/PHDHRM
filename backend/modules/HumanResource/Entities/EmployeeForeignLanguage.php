<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeForeignLanguage extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'language_name',
        'speaking_level',
        'reading_level',
        'writing_level',
        'institution_name',
        'start_date',
        'end_date',
        'result',
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

