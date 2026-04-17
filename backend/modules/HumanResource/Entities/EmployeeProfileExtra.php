<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeProfileExtra extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'salutation',
        'passport_expiry_date',
        'national_id_expiry_date',
        'driving_license_expiry_date',
        'civil_service_card_expiry_date',
        'birth_place_state',
        'birth_place_city',
        'birth_place_commune',
        'birth_place_village',
        'is_ethnic_minority',
        'ethnic_minority_name',
        'ethnic_minority_other',
        'current_work_skill',
        'current_position_start_date',
        'current_position_document_number',
        'current_position_document_date',
        'current_salary_type',
        'technical_role_type',
        'framework_type',
        'registration_date',
        'professional_registration_no',
        'institution_contact_no',
        'institution_email',
        'telegram_account',
        'facebook_account',
    ];

    protected $casts = [
        'is_ethnic_minority' => 'boolean',
        'passport_expiry_date' => 'date',
        'national_id_expiry_date' => 'date',
        'driving_license_expiry_date' => 'date',
        'civil_service_card_expiry_date' => 'date',
        'current_position_start_date' => 'date',
        'current_position_document_date' => 'date',
        'registration_date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
