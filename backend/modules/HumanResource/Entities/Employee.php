<?php

namespace Modules\HumanResource\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Modules\Accounts\Entities\AccSubcode;
use Modules\HumanResource\Entities\ApplyLeave;
use Modules\HumanResource\Entities\Attendance;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\DutyType;
use Modules\HumanResource\Entities\EmployeeAllowenceDeduction;
use Modules\HumanResource\Entities\EmployeeDocs;
use Modules\HumanResource\Entities\EmployeeFamilyMember;
use Modules\HumanResource\Entities\EmployeeFile;
use Modules\HumanResource\Entities\EmployeeBankAccount;
use Modules\HumanResource\Entities\EmployeeEducationHistory;
use Modules\HumanResource\Entities\EmployeeForeignLanguage;
use Modules\HumanResource\Entities\EmployeeIncentive;
use Modules\HumanResource\Entities\EmployeeLegalRecord;
use Modules\HumanResource\Entities\EmployeePayGradeHistory;
use Modules\HumanResource\Entities\EmployeeSectionAttachment;
use Modules\HumanResource\Entities\EmployeeServiceHistory;
use Modules\HumanResource\Entities\EmployeeUnitPosting;
use Modules\HumanResource\Entities\EmployeeWorkHistory;
use Modules\HumanResource\Entities\EmployeeSalaryType;
use Modules\HumanResource\Entities\EmployeeType;
use Modules\HumanResource\Entities\Gender;
use Modules\HumanResource\Entities\MaritalStatus;
use Modules\HumanResource\Entities\PayFrequency;
use Modules\HumanResource\Entities\Position;
use Modules\HumanResource\Entities\SetupRule;
use Modules\Setting\Entities\Country;

class Employee extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'user_id',
        'card_no',
        'first_name',
        'middle_name',
        'last_name',
        'last_name_latin',
        'first_name_latin',
        'email',
        'phone',
        'profile_image',
        'alternate_phone',
        'employee_group_id',
        'present_address',
        'permanent_address',
        'degree_name',
        'university_name',
        'cgp',
        'passing_year',
        'company_name',
        'working_period',
        'duties',
        'supervisor',
        'signature',
        'is_admin',
        'maiden_name',
        'state_id',
        'city',
        'zip',
        'citizenship',
        'joining_date',
        'hire_date',
        'termination_date',
        'termination_reason',
        'national_id',
        'identification_attachment',
        'nationality',
        'voluntary_termination',
        'rehire_date',
        'rate',
        'pay_frequency_id',
        'duty_type_id',
        'gender_id',
        'marital_status_id',
        'attendance_time_id',
        'employee_type_id',

        'contract_start_date',
        'contract_end_date',

        'position_id',
        'department_id',
        'sub_department_id',
        'branch_id',
        'employee_code',
        'official_id_10',
        'is_full_right_officer',
        'service_start_date',
        'full_right_date',
        'legal_document_type',
        'legal_document_number',
        'legal_document_date',
        'legal_document_subject',
        'legacy_pob_code',
        'legacy_pa_code',
        'legacy_other_info',
        'employee_device_id',

        'highest_educational_qualification',
        'national_education_level',
        'pay_frequency_text',
        'hourly_rate',
        'hourly_rate2',
        'home_department',
        'department_text',
        'class_code',
        'class_code_desc',
        'class_acc_date',
        'class_status',
        'is_supervisor',
        'supervisor_id',
        'supervisor_report',
        'date_of_birth',
        'birth_place_state_id',
        'birth_place_city_id',
        'birth_place_commune_id',
        'birth_place_village_id',
        'ethnic_group',
        'eeo_class_gp',
        'ssn',
        'work_in_city',
        'promotion_date',
        'live_in_state',
        'home_email',
        'business_email',
        'home_phone',
        'business_phone',
        'cell_phone',

        'emergency_contact_person',
        'emergency_contact_relationship',
        'emergency_contact',
        'emergency_contact_country',
        'emergency_contact_state',
        'emergency_contact_city',
        'emergency_contact_post_code',
        'emergency_contact_address',

        'present_address_country',
        'present_address_state_id',
        'present_address_city_id',
        'present_address_commune_id',
        'present_address_village_id',
        'present_address_state',
        'present_address_city',
        'present_address_post_code',
        'present_address_address',

        'permanent_address_country',
        'permanent_address_state',
        'permanent_address_city',
        'permanent_address_post_code',
        'permanent_address_address',

        'skill_type',
        'skill_name',
        'certificate_type',
        'certificate_name',
        'skill_attachment',

        'emergency_home_phone',
        'emergency_work_phone',
        'alter_emergency_contact',
        'alter_emergency_home_phone',
        'alter_emergency_work_phone',
        'sos',
        'monthly_work_hours',
        'employee_grade',
        'religion',
        'no_of_kids',
        'spouse_count',
        'blood_group',
        'health_condition',
        'chronic_disease_history',
        'severe_disease_history',
        'surgery_history',
        'regular_medication',
        'allergy_reaction',
        'vaccine_name',
        'vaccine_protection',
        'vaccination_date',
        'vaccination_place',
        'covid_vaccine_dose',
        'covid_vaccine_name',
        'covid_vaccine_date',
        'is_disable',
        'disabilities_desc',
        'uniform_shirt_size',
        'uniform_pants_size',
        'uniform_shoe_size',
        'profile_img_name',
        'profile_img_location',
        'national_id_no',
        'iqama_no',
        'passport_no',
        'driving_license_no',
        'work_permit',
        'is_active',
        'is_left',
        'work_status_id',
        'work_status_name',
        'service_state',
    ];

    protected static function boot()
    {
        parent::boot();
        if (Auth::check()) {
            self::creating(function ($model) {
                $model->uuid = (string) Str::uuid();
                $model->created_by = Auth::id();
            });

            self::created(function ($model) {
                $model->employee_id = str_pad($model->id, 6, 0, STR_PAD_LEFT);
                $model->save();
            });

            self::updating(function ($model) {
                $model->updated_by = Auth::id();
            });
        }
    }

    public function getFullNameAttribute()
    {
        return trim(preg_replace('/\s+/', ' ', (string) "{$this->last_name} {$this->first_name}"));
    }

    public function getFullNameLatinAttribute()
    {
        $name = trim(preg_replace('/\s+/', ' ', (string) "{$this->last_name_latin} {$this->first_name_latin}"));

        return $name === '' ? '' : mb_strtoupper($name, 'UTF-8');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class)->withTrashed();
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function sub_department()
    {
        return $this->belongsTo(Department::class, 'sub_department_id', 'id');
    }

    public function state()
    {
        return $this->belongsTo(Country::class, 'state_id', 'id');
    }

    public function gender()
    {
        return $this->belongsTo(Gender::class, 'gender_id', 'id');
    }

    public function marital_status()
    {
        return $this->belongsTo(MaritalStatus::class);
    }

    public function employee_type()
    {
        return $this->belongsTo(EmployeeType::class, 'employee_type_id', 'id');
    }

    public function duty_type()
    {
        return $this->belongsTo(DutyType::class, 'duty_type_id', 'id');
    }

    public function pay_frequency()
    {
        return $this->belongsTo(PayFrequency::class);
    }

    public function attendance_time()
    {
        return $this->belongsTo(SetupRule::class, 'attendance_time_id', 'id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'employee_id', 'id');
    }

    public function attendance()
    {
        return $this->hasOne(Attendance::class, 'employee_id', 'id');
    }

    public function allowance_deduction()
    {
        return $this->hasMany(EmployeeAllowenceDeduction::class);
    }

    public function employee_docs()
    {
        return $this->hasMany(EmployeeDocs::class);
    }

    public function employee_files()
    {
        return $this->hasOne(EmployeeFile::class);
    }

    public function employee_salary_types()
    {
        return $this->hasMany(EmployeeSalaryType::class);
    }

    public function allowanceDeduction()
    {
        return $this->hasMany(EmployeeSalaryType::class)->where('type', 'allowance')->orWhere('type', 'deduction');
    }

    public function leave()
    {
        return $this->hasMany(ApplyLeave::class, 'employee_id', 'id');
    }

    public function subCode()
    {
        return $this->hasOne(AccSubcode::class, 'reference_no', 'id');
    }

    public function awards()
    {
        return $this->hasMany(Award::class, 'employee_id', 'id');
    }

    public function unitPostings()
    {
        return $this->hasMany(EmployeeUnitPosting::class, 'employee_id', 'id');
    }

    public function primaryUnitPosting()
    {
        return $this->hasOne(EmployeeUnitPosting::class, 'employee_id', 'id')
            ->where('is_primary', true)
            ->whereNull('end_date')
            ->latestOfMany();
    }

    public function serviceHistories()
    {
        return $this->hasMany(EmployeeServiceHistory::class, 'employee_id', 'id');
    }

    public function statusTransitions()
    {
        return $this->hasMany(EmployeeStatusTransition::class, 'employee_id', 'id');
    }

    public function legalRecords()
    {
        return $this->hasMany(EmployeeLegalRecord::class, 'employee_id', 'id');
    }

    public function currentLegalRecord()
    {
        return $this->hasOne(EmployeeLegalRecord::class, 'employee_id', 'id')
            ->where('is_current', true)
            ->latestOfMany();
    }

    public function familyMembers()
    {
        return $this->hasMany(EmployeeFamilyMember::class, 'employee_id', 'id');
    }

    public function educationHistories()
    {
        return $this->hasMany(EmployeeEducationHistory::class, 'employee_id', 'id');
    }

    public function foreignLanguages()
    {
        return $this->hasMany(EmployeeForeignLanguage::class, 'employee_id', 'id');
    }

    public function vaccinations()
    {
        return $this->hasMany(EmployeeVaccination::class, 'employee_id', 'id');
    }

    public function bankAccounts()
    {
        return $this->hasMany(EmployeeBankAccount::class, 'employee_id', 'id');
    }

    public function payGradeHistories()
    {
        return $this->hasMany(EmployeePayGradeHistory::class, 'employee_id', 'id');
    }

    public function currentPayGradeHistory()
    {
        return $this->hasOne(EmployeePayGradeHistory::class, 'employee_id', 'id')
            ->where('status', 'active')
            ->latestOfMany('start_date');
    }

    public function latestPayGradeHistory()
    {
        return $this->hasOne(EmployeePayGradeHistory::class, 'employee_id', 'id')
            ->latestOfMany('start_date');
    }

    public function workHistories()
    {
        return $this->hasMany(EmployeeWorkHistory::class, 'employee_id', 'id');
    }

    public function incentives()
    {
        return $this->hasMany(EmployeeIncentive::class, 'employee_id', 'id');
    }

    public function sectionAttachments()
    {
        return $this->hasMany(EmployeeSectionAttachment::class, 'employee_id', 'id');
    }

    public function profileExtra()
    {
        return $this->hasOne(EmployeeProfileExtra::class, 'employee_id', 'id');
    }
}
