<?php

namespace Modules\Pharmaceutical\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\HumanResource\Entities\Department;

class PharmDispensing extends Model
{
    use SoftDeletes;

    protected $table = 'pharm_dispensings';

    protected $fillable = [
        'reference_no',
        'department_id',
        'dispensing_date',
        'patient_name',
        'patient_id_no',
        'patient_gender',
        'patient_age',
        'diagnosis',
        'note',
        'dispensed_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'dispensing_date' => 'date',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function items()
    {
        return $this->hasMany(PharmDispensingItem::class, 'dispensing_id');
    }

    public function dispenser()
    {
        return $this->belongsTo(User::class, 'dispensed_by');
    }
}
