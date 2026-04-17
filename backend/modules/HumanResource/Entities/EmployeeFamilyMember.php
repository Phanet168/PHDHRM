<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeFamilyMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'relation_type',
        'occupation',
        'salutation',
        'last_name_km',
        'first_name_km',
        'last_name_latin',
        'first_name_latin',
        'gender',
        'nationality',
        'ethnicity',
        'date_of_birth',
        'birth_place_state',
        'birth_place_city',
        'birth_place_commune',
        'birth_place_village',
        'present_address_state',
        'present_address_city',
        'present_address_commune',
        'present_address_village',
        'phone',
        'is_deceased',
        'note',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'is_deceased' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
