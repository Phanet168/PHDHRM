<?php

namespace Modules\HumanResource\Http\Requests;

use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Modules\HumanResource\Entities\Employee;

class EmployeeSelfUpdateRequest extends EmployeeUpdateRequest
{
    protected ?Employee $selfEmployee = null;
    protected bool $selfEmployeeResolved = false;

    public function rules()
    {
        $rules = parent::rules();
        $employee = $this->selfEmployee();
        $employeeUuid = (string) ($employee->uuid ?? '');
        $linkedUserId = (int) ($employee?->user?->id ?? 0);

        // HR-controlled fields stay read-only in self-service mode, so they must not
        // block a personal-profile update if legacy records are incomplete.
        $rules['employee_type_id'] = 'nullable|integer';
        $rules['position_id'] = 'nullable';
        $rules['department_id'] = 'nullable';
        $rules['is_full_right_officer'] = 'nullable|in:1,0';
        $rules['service_start_date'] = 'nullable|date';
        $rules['full_right_date'] = 'nullable|date';
        $rules['legal_document_type'] = 'nullable|in:royal_decree,sub_decree,decision,proclamation,deika,other';
        $rules['legal_document_number'] = 'nullable|string|max:120';
        $rules['legal_document_date'] = 'nullable|date';
        $rules['legal_document_subject'] = 'nullable|string';
        $rules['official_id_10'] = [
            'nullable',
            'regex:/^\d{10}$/',
            Rule::unique('employees', 'official_id_10')
                ->where(fn($query) => $query->whereNull('deleted_at'))
                ->ignore((string) optional($employee)->uuid, 'uuid'),
        ];
        $rules['email'] = [
            'nullable',
            'email',
            'max:191',
        ];
        $rules['phone'] = [
            'nullable',
            'string',
            'max:60',
        ];
        $rules['work_permit'] = 'nullable|in:0,1';
        $rules['card_no'] = 'nullable|string|max:120';
        $rules['civil_service_card_expiry_date'] = 'nullable|date';
        $rules['current_work_skill'] = 'nullable|string|max:191';
        $rules['skill_name'] = 'nullable|string|max:191';
        $rules['current_position_start_date'] = 'nullable|date';
        $rules['current_position_document_number'] = 'nullable|string|max:120';
        $rules['current_position_document_date'] = 'nullable|date';
        $rules['current_salary_type'] = 'nullable|string|max:80';
        $rules['employee_grade'] = 'nullable|string|max:80';
        $rules['technical_role_type'] = 'nullable|string|max:80';
        $rules['framework_type'] = 'nullable|string|max:80';
        $rules['registration_date'] = 'nullable|date';
        $rules['professional_registration_no'] = 'nullable|string|max:120';
        $rules['institution_contact_no'] = 'nullable|string|max:60';
        $rules['institution_email'] = 'nullable|email|max:191';
        $rules['birth_place_state_id'] = ['nullable', 'regex:/^\d{2}$/'];
        $rules['birth_place_city_id'] = ['nullable', 'regex:/^\d{4}$/'];
        $rules['birth_place_commune_id'] = ['nullable', 'regex:/^\d{6}$/'];
        $rules['birth_place_village_id'] = ['nullable', 'regex:/^\d{8}$/'];
        $rules['present_address_state_id'] = ['nullable', 'regex:/^\d{2}$/'];
        $rules['present_address_city_id'] = ['nullable', 'regex:/^\d{4}$/'];
        $rules['present_address_commune_id'] = ['nullable', 'regex:/^\d{6}$/'];
        $rules['present_address_village_id'] = ['nullable', 'regex:/^\d{8}$/'];
        $rules['present_address_house_no'] = 'nullable|string|max:60';
        $rules['present_address_street_no'] = 'nullable|string|max:60';
        $rules['is_supervisor'] = 'nullable|in:1,0';

        return $rules;
    }

    protected function prepareForValidation()
    {
        $employee = $this->selfEmployee();

        if ($employee) {
            $this->merge($this->lockedEmployeePayload($employee));
            $this->merge($this->lockedProfileExtraPayload($employee));
        }

        parent::prepareForValidation();

        $this->merge([
            'birth_place_state_id' => $this->normalizeGazetteerCode($this->input('birth_place_state_id'), 2),
            'birth_place_city_id' => $this->normalizeGazetteerCode($this->input('birth_place_city_id'), 4),
            'birth_place_commune_id' => $this->normalizeGazetteerCode($this->input('birth_place_commune_id'), 6),
            'birth_place_village_id' => $this->normalizeGazetteerCode($this->input('birth_place_village_id'), 8),
            'present_address_state_id' => $this->normalizeGazetteerCode($this->input('present_address_state_id'), 2),
            'present_address_city_id' => $this->normalizeGazetteerCode($this->input('present_address_city_id'), 4),
            'present_address_commune_id' => $this->normalizeGazetteerCode($this->input('present_address_commune_id'), 6),
            'present_address_village_id' => $this->normalizeGazetteerCode($this->input('present_address_village_id'), 8),
        ]);
    }

    protected function selfEmployee(): ?Employee
    {
        if ($this->selfEmployeeResolved) {
            return $this->selfEmployee;
        }

        $this->selfEmployeeResolved = true;

        $user = $this->user();
        if (!$user) {
            return null;
        }

        $this->selfEmployee = $user->employee()
            ->with('profileExtra', 'user')
            ->first();

        return $this->selfEmployee;
    }

    protected function lockedEmployeePayload(Employee $employee): array
    {
        $allowedFields = array_flip([
            'first_name',
            'middle_name',
            'last_name',
            'last_name_latin',
            'first_name_latin',
            'email',
            'phone',
            'alternate_phone',
            'nationality',
            'passport_no',
            'national_id_no',
            'driving_license_no',
            'religion',
            'ethnic_group',
            'date_of_birth',
            'birth_place_state_id',
            'birth_place_city_id',
            'birth_place_commune_id',
            'birth_place_village_id',
            'birth_place_state',
            'birth_place_city',
            'birth_place_commune',
            'birth_place_village',
            'present_address',
            'present_address_state_id',
            'present_address_city_id',
            'present_address_commune_id',
            'present_address_village_id',
            'present_address_state',
            'present_address_city',
            'present_address_post_code',
            'present_address_address',
            'gender_id',
            'marital_status_id',
            'no_of_kids',
            'spouse_count',
            'emergency_contact_person',
            'emergency_contact_relationship',
            'emergency_contact',
            'degree_name',
            'university_name',
            'passing_year',
            'cgp',
            'blood_group',
            'health_condition',
            'chronic_disease_history',
            'severe_disease_history',
            'surgery_history',
            'regular_medication',
            'allergy_reaction',
            'is_disable',
            'disabilities_desc',
            'uniform_shirt_size',
            'uniform_pants_size',
            'uniform_shoe_size',
            'sos',
        ]);

        $payload = [];
        foreach ($employee->getFillable() as $field) {
            if (isset($allowedFields[$field]) || $field === 'profile_image') {
                continue;
            }

            $payload[$field] = $this->normalizeLockedValue($employee->getAttribute($field));
        }

        $payload['department_id'] = ($employee->sub_department_id ?: $employee->department_id) ?: null;

        return $payload;
    }

    protected function lockedProfileExtraPayload(Employee $employee): array
    {
        $extra = $employee->profileExtra;
        if (!$extra) {
            return [];
        }

        $allowedFields = array_flip([
            'salutation',
            'passport_expiry_date',
            'national_id_expiry_date',
            'driving_license_expiry_date',
            'birth_place_state',
            'birth_place_city',
            'birth_place_commune',
            'birth_place_village',
            'is_ethnic_minority',
            'ethnic_minority_name',
            'ethnic_minority_other',
            'telegram_account',
            'facebook_account',
        ]);

        $payload = [];
        foreach ($extra->getFillable() as $field) {
            if ($field === 'employee_id' || isset($allowedFields[$field])) {
                continue;
            }

            $payload[$field] = $this->normalizeLockedValue($extra->getAttribute($field));
        }

        return $payload;
    }

    protected function normalizeLockedValue($value)
    {
        if ($value instanceof Carbon) {
            return $value->format('Y-m-d');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return $value;
    }

    protected function normalizeGazetteerCode($value, int $length): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        return preg_match('/^\d{' . $length . '}$/', $text) ? $text : null;
    }
}
