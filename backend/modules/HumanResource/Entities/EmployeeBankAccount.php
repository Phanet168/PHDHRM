<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeBankAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'account_name',
        'account_number',
        'bank_name',
        'attachment_name',
        'attachment_path',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}

