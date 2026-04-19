<?php

namespace Modules\HumanResource\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\HumanResource\Entities\Gender;
use Modules\HumanResource\Entities\MaritalStatus;

class EmployeeUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'salutation' => 'nullable|string|max:40',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'last_name_latin' => 'nullable|string|max:191',
            'first_name_latin' => 'nullable|string|max:191',
            'password' => 'nullable|string|min:6|confirmed',
            'nationality' => 'nullable|string|max:120',
            'passport_no' => 'nullable|string|max:120',
            'passport_expiry_date' => 'nullable|date',
            'national_id_no' => 'nullable|string|max:120',
            'national_id_expiry_date' => 'nullable|date',
            'driving_license_expiry_date' => 'nullable|date',
            'religion' => 'nullable|string|max:120',
            'ethnic_group' => 'nullable|string|max:120',
            'is_ethnic_minority' => 'nullable|in:0,1',
            'ethnic_minority_name' => 'nullable|string|max:120|required_if:is_ethnic_minority,1',
            'ethnic_minority_other' => 'nullable|string|max:120|required_if:ethnic_minority_name,other',
            'no_of_kids' => 'nullable|integer|min:0|max:30',
            'spouse_count' => 'nullable|integer|min:0|max:10',
            'is_full_right_officer' => 'required|in:1,0',
            'service_start_date' => 'nullable|date',
            'full_right_date' => 'nullable|date',
            'legal_document_type' => 'required_if:is_full_right_officer,1|nullable|in:royal_decree,sub_decree,decision,proclamation,deika,other',
            'legal_document_number' => 'required_if:is_full_right_officer,1|nullable|string|max:120',
            'legal_document_date' => 'required_if:is_full_right_officer,1|nullable|date',
            'legal_document_subject' => 'required_if:is_full_right_officer,1|nullable|string',
            'official_id_10' => [
                'required_if:is_full_right_officer,1',
                'nullable',
                'regex:/^\d{10}$/',
                Rule::unique('employees', 'official_id_10')->ignore((string) $this->route('employee'), 'uuid'),
            ],
            'attendance_time_id' => 'nullable|integer',
            'employee_type_id' => 'required|integer',
            'date_of_birth' => 'required',
            'birth_place_state_id' => ['required', 'regex:/^\d{2}$/'],
            'birth_place_city_id' => ['required', 'regex:/^\d{4}$/'],
            'birth_place_commune_id' => ['required', 'regex:/^\d{6}$/'],
            'birth_place_village_id' => ['required', 'regex:/^\d{8}$/'],
            'birth_place_state' => 'nullable|string|max:191',
            'birth_place_city' => 'nullable|string|max:191',
            'birth_place_commune' => 'nullable|string|max:191',
            'birth_place_village' => 'nullable|string|max:191',
            'present_address_state_id' => ['required', 'regex:/^\d{2}$/'],
            'present_address_city_id' => ['required', 'regex:/^\d{4}$/'],
            'present_address_commune_id' => ['required', 'regex:/^\d{6}$/'],
            'present_address_village_id' => ['required', 'regex:/^\d{8}$/'],
            'joining_date' => 'nullable|date',
            'hire_date' => 'nullable|date',
            'gender_id' => 'required',
            'sos' => 'nullable|string|max:60',
            'position_id' => 'required',
            'department_id' => 'required',
            'service_state' => 'nullable|in:active,suspended,inactive',
            'work_status_name' => 'nullable|string|max:191',
            'work_permit' => 'nullable|in:0,1',
            'card_no' => 'nullable|required_if:work_permit,1|string|max:120',
            'civil_service_card_expiry_date' => 'nullable|required_if:work_permit,1|date',
            'pay_frequency_id' => 'nullable|integer|exists:pay_frequencies,id',
            'current_work_skill' => 'nullable|string|max:191',
            'skill_name' => 'nullable|string|max:191',
            'current_position_start_date' => 'nullable|date',
            'current_position_document_number' => 'nullable|string|max:120',
            'current_position_document_date' => 'nullable|date',
            'current_salary_type' => 'nullable|string|max:80',
            'employee_grade' => 'nullable|string|max:80',
            'technical_role_type' => 'nullable|string|max:80',
            'framework_type' => 'nullable|string|max:80',
            'registration_date' => 'nullable|date',
            'professional_registration_no' => 'nullable|string|max:120',
            'institution_contact_no' => 'nullable|string|max:60',
            'institution_email' => 'nullable|email|max:191',
            'national_education_level' => 'nullable|in:1,2,3,4',
            'telegram_account' => 'nullable|string|max:191',
            'facebook_account' => 'nullable|string|max:191',
            'blood_group' => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-,unknown',
            'health_condition' => 'nullable|string|max:191',
            'chronic_disease_history' => 'nullable|string',
            'severe_disease_history' => 'nullable|string',
            'surgery_history' => 'nullable|string',
            'regular_medication' => 'nullable|string',
            'allergy_reaction' => 'nullable|string',
            'vaccine_name' => 'nullable|string|max:191',
            'vaccine_protection' => 'nullable|string|max:191',
            'vaccination_date' => 'nullable|date',
            'vaccination_place' => 'nullable|string|max:191',
            'vaccination_records' => 'nullable|array',
            'vaccination_records.*.vaccine_name' => 'nullable|string|max:191',
            'vaccination_records.*.vaccine_protection' => 'nullable|string|max:191',
            'vaccination_records.*.vaccination_date' => 'nullable|date',
            'vaccination_records.*.vaccination_place' => 'nullable|string|max:191',
            'covid_vaccine_dose' => 'nullable|string|max:30',
            'covid_vaccine_name' => 'nullable|string|max:191',
            'covid_vaccine_date' => 'nullable|date',
            'is_disable' => 'nullable|in:1,0',
            'disabilities_desc' => 'nullable|string|max:191',
            'uniform_shirt_size' => 'nullable|string|max:30',
            'uniform_pants_size' => 'nullable|string|max:30',
            'uniform_shoe_size' => 'nullable|string|max:30',
            'profile_image' => 'image|mimes:jpg,png,webp',
            'employee_docs' => 'array',
            'family_members' => 'array',
            'family_members.*.relation_type' => 'nullable|string|max:80',
            'family_members.*.occupation' => 'nullable|string|max:191',
            'family_members.*.salutation' => 'nullable|string|max:40',
            'family_members.*.last_name_km' => 'nullable|string|max:191',
            'family_members.*.first_name_km' => 'nullable|string|max:191',
            'family_members.*.last_name_latin' => 'nullable|string|max:191',
            'family_members.*.first_name_latin' => 'nullable|string|max:191',
            'family_members.*.gender' => 'nullable|string|max:20',
            'family_members.*.nationality' => 'nullable|string|max:120',
            'family_members.*.ethnicity' => 'nullable|string|max:120',
            'family_members.*.date_of_birth' => 'nullable|date',
            'family_members.*.birth_place_state' => 'nullable|string|max:191',
            'family_members.*.birth_place_city' => 'nullable|string|max:191',
            'family_members.*.birth_place_commune' => 'nullable|string|max:191',
            'family_members.*.birth_place_village' => 'nullable|string|max:191',
            'family_members.*.present_address_state' => 'nullable|string|max:191',
            'family_members.*.present_address_city' => 'nullable|string|max:191',
            'family_members.*.present_address_commune' => 'nullable|string|max:191',
            'family_members.*.present_address_village' => 'nullable|string|max:191',
            'family_members.*.phone' => 'nullable|string|max:60',
            'family_members.*.is_deceased' => 'nullable|in:0,1',
            'education_histories' => 'array',
            'education_histories.*.institution_name' => 'nullable|string|max:191',
            'education_histories.*.start_date' => 'nullable|date',
            'education_histories.*.end_date' => 'nullable|date',
            'education_histories.*.degree_level' => 'nullable|string|max:191',
            'education_histories.*.major_subject' => 'nullable|string|max:191',
            'education_histories.*.note' => 'nullable|string',
            'foreign_languages' => 'array',
            'foreign_languages.*.language_name' => 'nullable|string|max:120',
            'foreign_languages.*.speaking_level' => 'nullable|string|max:10',
            'foreign_languages.*.reading_level' => 'nullable|string|max:10',
            'foreign_languages.*.writing_level' => 'nullable|string|max:10',
            'foreign_languages.*.institution_name' => 'nullable|string|max:191',
            'foreign_languages.*.start_date' => 'nullable|date',
            'foreign_languages.*.end_date' => 'nullable|date',
            'foreign_languages.*.result' => 'nullable|string|max:191',
            'bank_accounts' => 'array',
            'bank_accounts.*.account_name' => 'nullable|string|max:191',
            'bank_accounts.*.account_number' => 'nullable|string|max:120',
            'bank_accounts.*.bank_name' => 'nullable|string|max:191',
            'bank_accounts.*.attachment' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,txt,rtf,jpeg,jpg,png,gif,svg|max:51200',
            'pay_grade_histories' => 'array',
            'pay_grade_histories.*.start_date' => 'nullable|date',
            'pay_grade_histories.*.end_date' => 'nullable|date',
            'pay_grade_histories.*.status' => 'nullable|in:active,inactive',
            'pay_grade_histories.*.note' => 'nullable|string',
            'work_histories' => 'array',
            'work_histories.*.work_status_name' => 'nullable|string|max:191',
            'work_histories.*.start_date' => 'nullable|date',
            'work_histories.*.document_reference' => 'nullable|string|max:191',
            'work_histories.*.document_date' => 'nullable|date',
            'work_histories.*.note' => 'nullable|string',
            'incentives' => 'array',
            'incentives.*.incentive_date' => 'nullable|date',
            'incentives.*.hierarchy_level' => 'nullable|string|max:191',
            'incentives.*.nationality_type' => 'nullable|string|max:191',
            'incentives.*.incentive_type' => 'nullable|string|max:191',
            'incentives.*.incentive_class' => 'nullable|string|max:191',
            'incentives.*.reason' => 'nullable|string',
            'family_attachments' => 'array',
            'family_attachments.*.title' => 'nullable|string|max:191',
            'family_attachments.*.file' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,txt,rtf,jpeg,jpg,png,gif,svg|max:51200',
            'family_attachments.*.expiry_date' => 'nullable|date',
            'bank_attachments' => 'array',
            'bank_attachments.*.title' => 'nullable|string|max:191',
            'bank_attachments.*.file' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,txt,rtf,jpeg,jpg,png,gif,svg|max:51200',
            'bank_attachments.*.expiry_date' => 'nullable|date',
            'is_supervisor' => 'required|in:1,0',
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation()
    {
        $rows = $this->normalizeFamilyMemberRows((array) $this->input('family_members', []));
        $educationRows = $this->normalizeYearOnlyRows((array) $this->input('education_histories', []));
        $languageRows = $this->normalizeYearOnlyRows((array) $this->input('foreign_languages', []));
        [$spouseCount, $kidsCount] = $this->calculateFamilySummary($rows);

        $selectedMaritalStatus = (int) $this->input('marital_status_id');
        $allowSpouseAndKids = $this->allowSpouseAndKidsByMaritalStatus($selectedMaritalStatus);
        if (!$allowSpouseAndKids) {
            $spouseCount = 0;
            $kidsCount = 0;
        }

        $lastNameLatin = $this->toUpperLatin($this->input('last_name_latin'));
        $firstNameLatin = $this->toUpperLatin($this->input('first_name_latin'));
        $isEthnicMinority = in_array((string) $this->input('is_ethnic_minority'), ['1', 'true', 'on'], true) ? '1' : '0';
        $ethnicMinorityName = trim((string) $this->input('ethnic_minority_name', ''));
        $ethnicMinorityOther = trim((string) $this->input('ethnic_minority_other', ''));

        if ($isEthnicMinority !== '1') {
            $ethnicMinorityName = null;
            $ethnicMinorityOther = null;
        } elseif (mb_strtolower($ethnicMinorityName, 'UTF-8') !== 'other') {
            $ethnicMinorityOther = null;
        }

        $this->merge([
            'last_name_latin' => $lastNameLatin,
            'first_name_latin' => $firstNameLatin,
            'is_ethnic_minority' => $isEthnicMinority,
            'ethnic_minority_name' => $ethnicMinorityName,
            'ethnic_minority_other' => $ethnicMinorityOther,
            'passport_expiry_date' => $this->normalizeCalendarDate($this->input('passport_expiry_date')),
            'national_id_expiry_date' => $this->normalizeCalendarDate($this->input('national_id_expiry_date')),
            'driving_license_expiry_date' => $this->normalizeCalendarDate($this->input('driving_license_expiry_date')),
            'civil_service_card_expiry_date' => $this->normalizeCalendarDate($this->input('civil_service_card_expiry_date')),
            'family_members' => $rows,
            'education_histories' => $educationRows,
            'foreign_languages' => $languageRows,
            'spouse_count' => $spouseCount,
            'no_of_kids' => $kidsCount,
        ]);
    }

    protected function normalizeYearOnlyRows(array $rows): array
    {
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $row['start_date'] = $this->normalizeYearOnlyDate($row['start_date'] ?? null);
            $row['end_date'] = $this->normalizeYearOnlyDate($row['end_date'] ?? null);
            $rows[$index] = $row;
        }

        return $rows;
    }

    protected function normalizeYearOnlyDate($value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        if (preg_match('/^\d{4}$/', $text)) {
            return $text . '-01-01';
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $text)->format('Y-m-d');
            } catch (\Throwable $e) {
            }
        }

        try {
            return Carbon::parse($text)->format('Y-m-d');
        } catch (\Throwable $e) {
            return $text;
        }
    }

    protected function normalizeCalendarDate($value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'm-d-Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $text)->format('Y-m-d');
            } catch (\Throwable $e) {
            }
        }

        return $text;
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $this->validateEmploymentDateConsistency($validator);

            $selectedMaritalStatus = (int) $this->input('marital_status_id');
            $allowSpouseAndKids = $this->allowSpouseAndKidsByMaritalStatus($selectedMaritalStatus);
            $employeeGenderKey = $this->employeeGenderKey();
            $familyRows = (array) $this->input('family_members', []);

            foreach ($familyRows as $index => $row) {
                $relation = $this->normalizeFamilyRelation($row['relation_type'] ?? null);
                $salutation = $this->normalizeSalutation($row['salutation'] ?? null);
                $memberGender = mb_strtolower(trim((string) ($row['gender'] ?? '')));

                if ($relation !== '') {
                    if (!$allowSpouseAndKids && in_array($relation, ['wife', 'husband', 'son', 'daughter'], true)) {
                        $validator->errors()->add("family_members.$index.relation_type", 'ព័ត៌មានប្តី/ប្រពន្ធ និងកូន អាចបញ្ចូលបានសម្រាប់ស្ថានភាពរៀបការ/ពោះម៉ាយប៉ុណ្ណោះ។');
                    }

                    if ($employeeGenderKey === 'male' && $relation === 'husband') {
                        $validator->errors()->add("family_members.$index.relation_type", 'បុគ្គលិកភេទប្រុស មិនអាចជ្រើសប្រភេទ "ប្តី" បានទេ។');
                    }

                    if ($employeeGenderKey === 'female' && $relation === 'wife') {
                        $validator->errors()->add("family_members.$index.relation_type", 'បុគ្គលិកភេទស្រី មិនអាចជ្រើសប្រភេទ "ប្រពន្ធ" បានទេ។');
                    }
                }

                if ($salutation !== '') {
                    $allowedSalutations = [];

                    if ($relation === 'wife') {
                        $allowedSalutations = ['mrs', 'lok_chumteav'];
                    } elseif ($relation === 'husband') {
                        $allowedSalutations = ['mr', 'excellency'];
                    } elseif ($relation === 'son') {
                        $allowedSalutations = ['boy'];
                    } elseif ($relation === 'daughter') {
                        $allowedSalutations = ['girl'];
                    } elseif ($relation === 'mother') {
                        $allowedSalutations = ['mrs', 'lok_chumteav'];
                    } elseif ($relation === 'father') {
                        $allowedSalutations = ['mr', 'excellency'];
                    } elseif (in_array($memberGender, ['male', 'female'], true)) {
                        $allowedSalutations = $memberGender === 'male'
                            ? ['boy', 'mr', 'excellency']
                            : ['girl', 'miss', 'mrs', 'lok_chumteav'];
                    }

                    if (!empty($allowedSalutations) && !in_array($salutation, $allowedSalutations, true)) {
                        $validator->errors()->add("family_members.$index.salutation", 'វន្ទនាការមិនត្រូវនឹងប្រភេទសមាជិកគ្រួសារ/ភេទ។');
                    }
                }
            }
        });
    }

    protected function validateEmploymentDateConsistency($validator): void
    {
        $dob = $this->parseInputDate($this->input('date_of_birth'));
        $joiningDate = $this->parseInputDate($this->input('joining_date'));
        $serviceStartDate = $this->parseInputDate($this->input('service_start_date'));

        if ($dob && $dob->isFuture()) {
            $validator->errors()->add('date_of_birth', 'ថ្ងៃខែឆ្នាំកំណើតមិនអាចលើសថ្ងៃបច្ចុប្បន្នបានទេ។');
        }

        if ($dob && $joiningDate && $dob->gt($joiningDate)) {
            $validator->errors()->add('joining_date', 'ថ្ងៃចូលធ្វើការ ត្រូវធំជាង ឬស្មើថ្ងៃខែឆ្នាំកំណើត។');
        }

        if ($dob && $serviceStartDate && $dob->gt($serviceStartDate)) {
            $validator->errors()->add('service_start_date', 'ថ្ងៃចាប់ផ្តើមសេវា ត្រូវធំជាង ឬស្មើថ្ងៃខែឆ្នាំកំណើត។');
        }

        if ($joiningDate && $serviceStartDate && $joiningDate->gt($serviceStartDate)) {
            $validator->errors()->add('service_start_date', 'ថ្ងៃចាប់ផ្តើមសេវា មិនអាចតូចជាងថ្ងៃចូលធ្វើការបានទេ។');
        }

    }

    protected function parseInputDate($value): ?Carbon
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $text);
                if ($parsed instanceof Carbon) {
                    return $parsed->startOfDay();
                }
            } catch (\Throwable $e) {
            }
        }

        try {
            return Carbon::parse($text)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function normalizeFamilyMemberRows(array $rows): array
    {
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $row['relation_type'] = $this->normalizeFamilyRelation($row['relation_type'] ?? null);
            $row['salutation'] = $this->normalizeSalutation($row['salutation'] ?? null);
            $row['last_name_latin'] = $this->toUpperLatin($row['last_name_latin'] ?? null);
            $row['first_name_latin'] = $this->toUpperLatin($row['first_name_latin'] ?? null);
            $row['date_of_birth'] = $this->normalizeDateInputForStorage($row['date_of_birth'] ?? null);
            $rows[$index] = $row;
        }

        return $rows;
    }

    protected function normalizeDateInputForStorage($value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        $parsed = $this->parseInputDate($text);
        if ($parsed instanceof Carbon) {
            return $parsed->format('Y-m-d');
        }

        return $text;
    }

    protected function toUpperLatin($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        return mb_strtoupper($text, 'UTF-8');
    }

    protected function calculateFamilySummary(array $rows): array
    {
        $spouseCount = 0;
        $kidsCount = 0;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $relation = $this->normalizeFamilyRelation($row['relation_type'] ?? null);
            if ($relation === 'wife' || $relation === 'husband') {
                $spouseCount++;
            }
            if ($relation === 'son' || $relation === 'daughter') {
                $kidsCount++;
            }
        }

        return [$spouseCount, $kidsCount];
    }

    protected function normalizeFamilyRelation(?string $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }

        $normalized = $this->normalizeComparisonKey($raw);
        $map = [
            'wife' => 'wife',
            'ប្រពន្ធ' => 'wife',
            'husband' => 'husband',
            'ប្តី' => 'husband',
            'ប្ដី' => 'husband',
            'son' => 'son',
            'កូនប្រុស' => 'son',
            'daughter' => 'daughter',
            'កូនស្រី' => 'daughter',
            'mother' => 'mother',
            'ម្តាយបង្កើត' => 'mother',
            'ម្ដាយបង្កើត' => 'mother',
            'ម្តាយ' => 'mother',
            'ម្ដាយ' => 'mother',
            'father' => 'father',
            'ឪពុកបង្កើត' => 'father',
            'ឪពុក' => 'father',
        ];

        return $map[$normalized] ?? $raw;
    }

    protected function normalizeSalutation(?string $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }

        $normalized = $this->normalizeComparisonKey($raw);
        $map = [
            'boy' => 'boy',
            'កុមារា' => 'boy',
            'girl' => 'girl',
            'កុមារី' => 'girl',
            'mr' => 'mr',
            'mr.' => 'mr',
            'លោក' => 'mr',
            'miss' => 'miss',
            'ms' => 'miss',
            'ms.' => 'miss',
            'កញ្ញា' => 'miss',
            'mrs' => 'mrs',
            'mrs.' => 'mrs',
            'លោកស្រី' => 'mrs',
            'excellency' => 'excellency',
            'ឯកឧត្តម' => 'excellency',
            'lok chumteav' => 'lok_chumteav',
            'lok_chumteav' => 'lok_chumteav',
            'លោកជំទាវ' => 'lok_chumteav',
        ];

        return $map[$normalized] ?? $raw;
    }

    protected function singleMaritalStatusId(): ?int
    {
        $rows = MaritalStatus::query()->get();
        foreach ($rows as $row) {
            $candidates = [
                $this->normalizeComparisonKey((string) ($row->name ?? '')),
                $this->normalizeComparisonKey((string) ($row->name_km ?? '')),
                $this->normalizeComparisonKey((string) ($row->name_en ?? '')),
            ];

            foreach ($candidates as $name) {
                if ($name === '') {
                    continue;
                }

                if ($name === 'single' || str_contains($name, 'single') || $name === 'នៅលីវ') {
                    return (int) $row->id;
                }
            }
        }

        return null;
    }

    protected function allowSpouseAndKidsByMaritalStatus(int $statusId): bool
    {
        $statusKey = $this->maritalStatusKeyById($statusId);
        return in_array($statusKey, ['married', 'widowed'], true);
    }

    protected function maritalStatusKeyById(int $statusId): string
    {
        if ($statusId <= 0) {
            return '';
        }

        $status = MaritalStatus::query()->find($statusId);
        if (!$status) {
            return '';
        }

        $candidates = [
            $this->normalizeComparisonKey((string) ($status->name ?? '')),
            $this->normalizeComparisonKey((string) ($status->name_km ?? '')),
            $this->normalizeComparisonKey((string) ($status->name_en ?? '')),
        ];

        foreach ($candidates as $name) {
            if ($name === '') {
                continue;
            }

            if ($name === 'single' || str_contains($name, 'single') || $name === 'នៅលីវ') {
                return 'single';
            }

            if ($name === 'married' || str_contains($name, 'married') || $name === 'រៀបការ') {
                return 'married';
            }

            if (
                $name === 'widowed' ||
                str_contains($name, 'widow') ||
                $name === 'ពោះម៉ាយ' ||
                $name === 'មេម៉ាយ'
            ) {
                return 'widowed';
            }
        }

        return 'other';
    }

    protected function employeeGenderKey(): string
    {
        $genderId = (int) $this->input('gender_id');
        if ($genderId <= 0) {
            return '';
        }

        $gender = Gender::query()->find($genderId);
        if (!$gender) {
            return '';
        }

        $name = $this->normalizeComparisonKey((string) ($gender->gender_name ?? ''));
        if ($name === 'female' || str_contains($name, 'female') || $name === 'ស្រី') {
            return 'female';
        }
        if ($name === 'male' || str_contains($name, 'male') || $name === 'ប្រុស') {
            return 'male';
        }

        return '';
    }

    protected function normalizeComparisonKey(?string $value): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        $normalized = $this->normalizeKhmerText($text);
        return mb_strtolower(trim($normalized), 'UTF-8');
    }

    protected function normalizeKhmerText(?string $text): string
    {
        $value = trim((string) $text);
        if ($value === '') {
            return '';
        }

        if (preg_match('/\p{Khmer}/u', $value)) {
            return $value;
        }

        if (!str_contains($value, 'Ãƒ')) {
            return $value;
        }

        $decoded = @utf8_encode($value);
        if (is_string($decoded) && $decoded !== '' && preg_match('/\p{Khmer}/u', $decoded)) {
            return trim($decoded);
        }

        $iconv = @iconv('Windows-1252', 'UTF-8//IGNORE', $value);
        if (is_string($iconv) && $iconv !== '' && preg_match('/\p{Khmer}/u', $iconv)) {
            return trim($iconv);
        }

        return $value;
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'gender_id.required' => 'សូមជ្រើសភេទ។',
            'position_id.required' => 'សូមជ្រើសមុខតំណែង។',
            'department_id.required' => 'សូមជ្រើសអង្គភាព។',
            'birth_place_state_id.required' => 'សូមជ្រើសទីកន្លែងកំណើត - ខេត្ត/រាជធានី។',
            'birth_place_city_id.required' => 'សូមជ្រើសទីកន្លែងកំណើត - ក្រុង/ស្រុក/ខណ្ឌ។',
            'birth_place_commune_id.required' => 'សូមជ្រើសទីកន្លែងកំណើត - ឃុំ/សង្កាត់។',
            'birth_place_village_id.required' => 'សូមជ្រើសទីកន្លែងកំណើត - ភូមិ។',
            'present_address_state_id.required' => 'សូមជ្រើសអាសយដ្ឋានបច្ចុប្បន្ន - ខេត្ត/រាជធានី។',
            'present_address_city_id.required' => 'សូមជ្រើសអាសយដ្ឋានបច្ចុប្បន្ន - ក្រុង/ស្រុក/ខណ្ឌ។',
            'present_address_commune_id.required' => 'សូមជ្រើសអាសយដ្ឋានបច្ចុប្បន្ន - ឃុំ/សង្កាត់។',
            'present_address_village_id.required' => 'សូមជ្រើសអាសយដ្ឋានបច្ចុប្បន្ន - ភូមិ។',
            'is_full_right_officer.required' => 'សូមបញ្ជាក់ស្ថានភាពមន្ត្រីក្របខណ្ឌពេញសិទ្ធិ។',
            'official_id_10.required_if' => 'សូមបញ្ចូលអត្តលេខមន្ត្រី 10 ខ្ទង់ សម្រាប់មន្ត្រីក្របខណ្ឌពេញសិទ្ធិ។',
            'official_id_10.regex' => 'អត្តលេខមន្ត្រីត្រូវមានតែ 10 ខ្ទង់ប៉ុណ្ណោះ។',
            'official_id_10.unique' => 'អត្តលេខមន្ត្រីនេះមានរួចហើយ។',
            'legal_document_type.required_if' => 'សូមជ្រើសប្រភេទឯកសារច្បាប់។',
            'legal_document_number.required_if' => 'សូមបញ្ចូលលេខឯកសារច្បាប់។',
            'legal_document_date.required_if' => 'សូមជ្រើសកាលបរិច្ឆេទឯកសារច្បាប់។',
            'legal_document_subject.required_if' => 'សូមបញ្ចូលកម្មវត្ថុនៃឯកសារច្បាប់។',
        ];
    }

}






