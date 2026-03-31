<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeWorkHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'work_status_name',
        'start_date',
        'document_reference',
        'document_date',
        'note',
    ];

    protected $casts = [
        'start_date' => 'date',
        'document_date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}

