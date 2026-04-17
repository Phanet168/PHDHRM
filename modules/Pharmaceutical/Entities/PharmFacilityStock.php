<?php

namespace Modules\Pharmaceutical\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\HumanResource\Entities\Department;

class PharmFacilityStock extends Model
{
    use SoftDeletes;

    protected $table = 'pharm_facility_stocks';

    protected $fillable = [
        'department_id',
        'medicine_id',
        'quantity',
        'batch_no',
        'expiry_date',
        'unit_price',
        'updated_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'expiry_date' => 'date',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function medicine()
    {
        return $this->belongsTo(PharmMedicine::class, 'medicine_id');
    }
}
