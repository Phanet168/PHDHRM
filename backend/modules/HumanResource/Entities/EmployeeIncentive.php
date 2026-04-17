<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeIncentive extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'incentive_date',
        'hierarchy_level',
        'nationality_type',
        'incentive_type',
        'incentive_class',
        'reason',
    ];

    protected $casts = [
        'incentive_date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}

