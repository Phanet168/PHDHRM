<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeePayGradeHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'pay_level_id',
        'start_date',
        'end_date',
        'status',
        'promotion_type',
        'document_reference',
        'document_date',
        'next_review_date',
        'note',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'document_date' => 'date',
        'next_review_date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function payLevel()
    {
        return $this->belongsTo(GovPayLevel::class, 'pay_level_id')->withTrashed();
    }
}
