@extends('backend.layouts.app')
@section('title', $employee->full_name)

@push('css')
    <link rel="stylesheet" href="{{ module_asset('HumanResource/css/employee-show.css') }}">
@endpush

@section('content')
    @include('humanresource::employee_header')

    @php
        $km = app()->getLocale() === 'km' || (string) (app_setting()->lang?->value ?? '') === 'km';
        $extra = $employee->profileExtra;
        $l = fn($kh, $en) => $km ? $kh : $en;
        $t = fn($v) => trim((string) $v) !== '' ? trim((string) $v) : '-';
        $d = function ($v) use ($t) {
            if (blank($v)) return '-';
            try { return display_date($v); } catch (\Throwable $e) { return $t($v); }
        };
        $y = function ($v) use ($t) {
            if (blank($v)) return '-';
            try {
                return \Illuminate\Support\Carbon::parse($v)->format('Y');
            } catch (\Throwable $e) {
                if (preg_match('/^(\d{4})/', trim((string) $v), $matches)) {
                    return $matches[1];
                }

                return $t($v);
            }
        };
        $g = function ($v) use ($km, $t) {
            $v = trim((string) $v);
            if ($v === '') return '-';
            if (!$km) return $v;
            return match (mb_strtolower($v, 'UTF-8')) {
                'male', 'm', 'ប្រុស' => 'ប្រុស',
                'female', 'f', 'ស្រី' => 'ស្រី',
                default => $t($v),
            };
        };
        $degreeLevelLabels = [
            'no_formal_qualification' => $l('មិនទាន់មានសញ្ញាបត្រ', 'No formal qualification'),
            'literacy_certificate' => $l('វិញ្ញាបនបត្រអក្ខរកម្ម', 'Literacy certificate'),
            'primary_certificate' => $l('វិញ្ញាបនបត្របឋមសិក្សា', 'Primary certificate'),
            'lower_secondary_diploma' => $l('សញ្ញាបត្របឋមភូមិ', 'Lower secondary diploma'),
            'upper_secondary_diploma' => $l('សញ្ញាបត្រទុតិយភូមិ', 'Upper secondary diploma'),
            'vocational_certificate' => $l('វិញ្ញាបនបត្រវិជ្ជាជីវៈ', 'Vocational certificate'),
            'technical_diploma' => $l('សញ្ញាបត្របច្ចេកទេស', 'Technical diploma'),
            'associate_degree' => $l('បរិញ្ញាបត្ររង', 'Associate degree'),
            'higher_diploma' => $l('ឌីប្លូមជាន់ខ្ពស់', 'Higher diploma'),
            'bachelor_degree' => $l('បរិញ្ញាបត្រ', 'Bachelor degree'),
            'professional_bachelor_degree' => $l('បរិញ្ញាបត្រវិជ្ជាជីវៈ', 'Professional bachelor degree'),
            'postgraduate_certificate' => $l('វិញ្ញាបនបត្រក្រោយបរិញ្ញាបត្រ', 'Postgraduate certificate'),
            'postgraduate_diploma' => $l('សញ្ញាបត្រក្រោយបរិញ្ញាបត្រ', 'Postgraduate diploma'),
            'master_degree' => $l('បរិញ្ញាបត្រជាន់ខ្ពស់', 'Master degree'),
            'specialist_degree' => $l('សញ្ញាបត្រឯកទេស', 'Specialist degree'),
            'doctorate_degree' => $l('បណ្ឌិត', 'Doctorate degree'),
            'postdoctorate_degree' => $l('ក្រោយបណ្ឌិត', 'Postdoctorate degree'),
        ];
        $degree = function ($v) use ($degreeLevelLabels, $t) {
            $text = trim((string) $v);
            if ($text === '') {
                return '-';
            }

            if (array_key_exists($text, $degreeLevelLabels)) {
                return $degreeLevelLabels[$text];
            }

            $needle = mb_strtolower($text, 'UTF-8');
            foreach ($degreeLevelLabels as $label) {
                if ($needle === mb_strtolower((string) $label, 'UTF-8')) {
                    return $label;
                }
            }

            return $t($text);
        };
        $m = function ($v) use ($km, $t) {
            $v = trim((string) $v);
            if ($v === '') return '-';
            if (!$km) return $v;
            return match (mb_strtolower($v, 'UTF-8')) {
                'single', 'នៅលីវ' => 'នៅលីវ',
                'married', 'រៀបការ' => 'រៀបការ',
                'widow', 'widowed', 'មេម៉ាយ' => 'មេម៉ាយ',
                'widower', 'ពោះម៉ាយ' => 'ពោះម៉ាយ',
                default => $t($v),
            };
        };
        $r = function ($v) use ($km, $t) {
            $v = trim((string) $v);
            if ($v === '') return '-';
            if (!$km) return $v;
            return match (mb_strtolower($v, 'UTF-8')) {
                'wife', 'ប្រពន្ធ' => 'ប្រពន្ធ',
                'husband', 'ប្តី', 'ប្ដី' => 'ប្តី',
                'son', 'កូនប្រុស' => 'កូនប្រុស',
                'daughter', 'កូនស្រី' => 'កូនស្រី',
                'mother', 'ម្តាយ', 'ម្ដាយ', 'ម្តាយបង្កើត', 'ម្ដាយបង្កើត' => 'ម្តាយបង្កើត',
                'father', 'ឪពុក', 'ឪពុកបង្កើត' => 'ឪពុកបង្កើត',
                default => $t($v),
            };
        };
        $doc = function ($v) use ($km, $t) {
            $v = trim((string) $v);
            if ($v === '') return '-';
            if (!$km) return $v;
            return match (mb_strtolower($v, 'UTF-8')) {
                'royal_decree', 'royal decree', 'ព្រះរាជក្រឹត្យ' => 'ព្រះរាជក្រឹត្យ',
                'sub_decree', 'sub decree', 'អនុក្រឹត្យ' => 'អនុក្រឹត្យ',
                'proclamation', 'announcement', 'ប្រកាស' => 'ប្រកាស',
                'decision', 'សេចក្តីសម្រេច' => 'សេចក្តីសម្រេច',
                'deika', 'decree', 'ដីកា' => 'ដីកា',
                default => $t($v),
            };
        };
        $etype = fn($v) => !$km ? $t($v) : match (trim((string) $v)) {
            'position_change' => 'ឡើង/ប្ដូរតួនាទី',
            'transfer' => 'ផ្លាស់ប្តូរកន្លែងការងារ',
            'status_change' => 'ប្ដូរស្ថានភាពការងារ',
            default => $t($v),
        };
        $etitle = fn($v) => !$km ? $t($v) : match (trim((string) $v)) {
            'Position changed' => 'កែប្រែតួនាទី',
            'Workplace transferred' => 'ផ្លាស់ប្តូរកន្លែងការងារ',
            'Employee status changed' => 'កែប្រែស្ថានភាពមន្ត្រី',
            default => $t($v),
        };
        $normalizeNationalEducationCode = static function ($value): string {
            $text = trim((string) $value);
            if ($text === '') {
                return '';
            }

            if (in_array($text, ['1', '2', '3', '4'], true)) {
                return $text;
            }

            $normalized = mb_strtolower($text, 'UTF-8');
            $exactMap = [
                'primary' => '1',
                'lower secondary' => '2',
                'upper secondary' => '3',
                'other' => '4',
                'បឋម' => '1',
                'បឋមភូមិ' => '2',
                'ទុតិយភូមិ' => '3',
                'ផ្សេងៗ' => '4',
                'ផ្សេងទៀត' => '4',
                'ផ្សេងៗទៀត' => '4',
                'primary_certificate' => '1',
                'lower_secondary_diploma' => '2',
                'upper_secondary_diploma' => '3',
                'vocational_certificate' => '3',
                'technical_diploma' => '3',
                'associate_degree' => '3',
                'higher_diploma' => '3',
                'bachelor_degree' => '3',
                'professional_bachelor_degree' => '3',
                'postgraduate_certificate' => '3',
                'postgraduate_diploma' => '3',
                'master_degree' => '3',
                'specialist_degree' => '3',
                'doctorate_degree' => '3',
                'postdoctorate_degree' => '3',
            ];
            if (isset($exactMap[$normalized])) {
                return $exactMap[$normalized];
            }

            if (str_contains($normalized, 'បឋមភូមិ') || str_contains($normalized, 'lower secondary')) {
                return '2';
            }
            if (
                str_contains($normalized, 'ទុតិយភូមិ')
                || str_contains($normalized, 'upper secondary')
                || str_contains($normalized, 'បរិញ្ញា')
                || str_contains($normalized, 'master')
                || str_contains($normalized, 'doctor')
            ) {
                return '3';
            }
            if (str_contains($normalized, 'បឋម') || str_contains($normalized, 'primary')) {
                return '1';
            }
            if (str_contains($normalized, 'ផ្សេង')) {
                return '4';
            }

            return '';
        };
        $nationalEducationCode = $normalizeNationalEducationCode($employee->national_education_level);
        if ($nationalEducationCode === '') {
            $nationalEducationCode = $normalizeNationalEducationCode($employee->highest_educational_qualification);
        }
        if ($nationalEducationCode === '') {
            $degreeRank = [
                'primary_certificate' => 1,
                'lower_secondary_diploma' => 2,
                'upper_secondary_diploma' => 3,
                'vocational_certificate' => 3,
                'technical_diploma' => 3,
                'associate_degree' => 3,
                'higher_diploma' => 3,
                'bachelor_degree' => 3,
                'professional_bachelor_degree' => 3,
                'postgraduate_certificate' => 3,
                'postgraduate_diploma' => 3,
                'master_degree' => 3,
                'specialist_degree' => 3,
                'doctorate_degree' => 3,
                'postdoctorate_degree' => 3,
            ];
            $highestRank = 0;
            foreach ($employee->educationHistories as $history) {
                $degreeKey = trim((string) ($history->degree_level ?? ''));
                if ($degreeKey === '') {
                    continue;
                }

                $rank = (int) ($degreeRank[$degreeKey] ?? 0);
                if ($rank === 0) {
                    $derivedCode = $normalizeNationalEducationCode($degreeKey);
                    $rank = match ($derivedCode) {
                        '1' => 1,
                        '2' => 2,
                        '3' => 3,
                        default => 0,
                    };
                }
                if ($rank > $highestRank) {
                    $highestRank = $rank;
                }
            }

            if ($highestRank === 1) {
                $nationalEducationCode = '1';
            } elseif ($highestRank === 2) {
                $nationalEducationCode = '2';
            } elseif ($highestRank >= 3) {
                $nationalEducationCode = '3';
            }
        }
        $nationalEducationLabelMap = [
            '1' => $l('បឋម', 'Primary'),
            '2' => $l('បឋមភូមិ', 'Lower secondary'),
            '3' => $l('ទុតិយភូមិ', 'Upper secondary'),
            '4' => $l('ផ្សេងៗ', 'Other'),
        ];
        $edu = $nationalEducationLabelMap[$nationalEducationCode] ?? '-';
        $service = match ((string) ($employee->service_state ?? 'active')) {
            'suspended' => $l('ផ្អាកបណ្ដោះអាសន្ន', 'Suspended'),
            'inactive' => $l('អសកម្ម', 'Inactive'),
            default => $l('សកម្ម', 'Active'),
        };
        $position = optional($employee->position)->position_name_km ?: optional($employee->position)->position_name;
        $unit = optional($employee->sub_department)->department_name ?: optional($employee->department)->department_name;
        $birth = implode(' > ', array_filter([optional($extra)->birth_place_state, optional($extra)->birth_place_city, optional($extra)->birth_place_commune, optional($extra)->birth_place_village])) ?: $t($employee->legacy_pob_code);
        $address = implode(' > ', array_filter([$employee->present_address_state, $employee->present_address_city, $employee->present_address_post_code, $employee->present_address_address])) ?: $t($employee->present_address);
        $phone = $employee->phone ?: $employee->cell_phone ?: $employee->business_phone;
        $image = $employee->profile_img_location ? asset('storage/' . $employee->profile_img_location) : asset('backend/assets/dist/img/avatar-1.jpg');
        $bankRows = $employee->bankAccounts;
        if ($bankRows->isEmpty() && !empty($bank_info) && ($bank_info->acc_number || $bank_info->bank_name || $bank_info->account_name)) {
            $bankRows = collect([(object) ['account_name' => $bank_info->account_name ?: $employee->full_name, 'account_number' => $bank_info->acc_number, 'bank_name' => $bank_info->bank_name]]);
        }
        $educationRows = $employee->educationHistories;
        $languageRows = $employee->foreignLanguages;
        $payLevel = $employee->employee_grade ?: optional($extra)->current_salary_type;
        $fullRightText = (int) ($employee->is_full_right_officer ?? 0) === 1 ? $l('ពេញសិទ្ធ', 'Full-right') : $l('មិនទាន់ពេញសិទ្ធ', 'Not yet full-right');
        $workStatusText = $t($employee->work_status_name ?: $service);
        $ethnicMinorityLabelMap = [
            'kuy' => $l('កួយ', 'Kuy'),
            'phnong' => $l('ព្នង', 'Phnong'),
            'tumpuon' => $l('ទំពួន', 'Tumpuon'),
            'jarai' => $l('ចារ៉ាយ', 'Jarai'),
            'kreung' => $l('ក្រឹង', 'Kreung'),
            'brao' => $l('ប្រៅ', 'Brao'),
            'kavet' => $l('កាវ៉ែត', 'Kavet'),
            'kachok' => $l('កាចក់', 'Kachok'),
            'stieng' => $l('ស្ទៀង', 'Stieng'),
            'por' => $l('ព័រ', 'Por'),
            'saoch' => $l('ស្អូច', "Sa'och"),
            'lun' => $l('លុន', 'Lun'),
            'mil' => $l('មិល', 'Mil'),
            'chong' => $l('ចុង', 'Chong'),
            'other' => $l('ផ្សេងៗ', 'Other'),
        ];
        $ethnicMinorityDisplay = $l('មិនមែន', 'No');
        if ((int) (optional($extra)->is_ethnic_minority ?? 0) === 1) {
            $minorityCode = mb_strtolower(trim((string) (optional($extra)->ethnic_minority_name ?? '')), 'UTF-8');
            $minorityText = $ethnicMinorityLabelMap[$minorityCode] ?? $t(optional($extra)->ethnic_minority_name);
            if ($minorityCode === 'other' && trim((string) (optional($extra)->ethnic_minority_other ?? '')) !== '') {
                $minorityText = $t(optional($extra)->ethnic_minority_other) . ' (' . $ethnicMinorityLabelMap['other'] . ')';
            }
            $ethnicMinorityDisplay = $minorityText;
        }
    @endphp

    <div class="profile_show profile-show-page">
    <div class="row px-3 mb-3">
        <div class="col-12 d-flex justify-content-end gap-2">
            <a href="{{ route('employees.profile.print', $employee->id) }}" class="btn btn-primary btn-sm" target="_blank" rel="noopener"><i class="fa fa-print me-1"></i>{{ $l('បោះពុម្ពប្រវត្តិរូបសង្ខេប', 'Print summary profile') }}</a>
            <a href="{{ route('employees.profile.print-detail', $employee->id) }}" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener"><i class="fa fa-file-text-o me-1"></i>{{ $l('បោះពុម្ពព័ត៌មានលម្អិត', 'Print detailed employee information') }}</a>
        </div>
    </div>

    <div class="row px-3 mb-3">
        <div class="col-12">
            <div class="card profile-summary-card shadow-sm border-0">
                <div class="profile-summary-bar"></div>
                <div class="card-body p-4">
                    <div class="row g-4 align-items-center">
                        <div class="col-md-auto text-center">
                            <img src="{{ $image }}" alt="{{ $employee->full_name }}" class="profile-summary-avatar">
                        </div>
                        <div class="col-lg">
                            <div class="profile-summary-tag mb-2">{{ $l('ប្រវត្តិរូបមន្ត្រី', 'Officer profile') }}</div>
                            <h2 class="profile-summary-name mb-1">{{ $employee->full_name ?: '-' }}</h2>
                            <div class="profile-summary-latin mb-2">{{ $t($employee->full_name_latin) }}</div>
                            <div class="profile-summary-line mb-2">
                                <span><i class="fa fa-sitemap me-1"></i>{{ $t($unit) }}</span>
                                <span><i class="fa fa-id-badge me-1"></i>{{ $t($position) }}</span>
                            </div>
                            <div class="profile-summary-line text-muted">
                                <span><i class="fa fa-phone me-1"></i>{{ $t($phone) }}</span>
                                <span><i class="fa fa-map-marker me-1"></i>{{ $address }}</span>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="profile-summary-stats">
                                <div class="profile-summary-stat">
                                    <div class="profile-summary-stat-label">{{ $l('អត្តលេខមន្ត្រី ១០ ខ្ទង់', 'Official ID') }}</div>
                                    <div class="profile-summary-stat-value">{{ $employee->official_id_10 ?: '-' }}</div>
                                </div>
                                <div class="profile-summary-stat">
                                    <div class="profile-summary-stat-label">{{ $l('លេខរៀងមន្ត្រី', 'Employee No.') }}</div>
                                    <div class="profile-summary-stat-value">{{ $employee->employee_id ?: '-' }}</div>
                                </div>
                                <div class="profile-summary-stat">
                                    <div class="profile-summary-stat-label">{{ $l('ស្ថានភាពការងារ', 'Work status') }}</div>
                                    <div class="profile-summary-stat-value">{{ $workStatusText }}</div>
                                </div>
                                <div class="profile-summary-stat">
                                    <div class="profile-summary-stat-label">{{ $l('កាំប្រាក់', 'Pay level') }}</div>
                                    <div class="profile-summary-stat-value">{{ $t($payLevel) }}</div>
                                </div>
                                <div class="profile-summary-stat">
                                    <div class="profile-summary-stat-label">{{ $l('ស្ថានភាពមន្ត្រី', 'Service state') }}</div>
                                    <div class="profile-summary-stat-value">{{ $service }}</div>
                                </div>
                                <div class="profile-summary-stat">
                                    <div class="profile-summary-stat-label">{{ $l('ស្ថានភាពពេញសិទ្ធ', 'Full-right status') }}</div>
                                    <div class="profile-summary-stat-value">{{ $fullRightText }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row px-3">
        <div class="col-lg-6">
            <div class="card mb-4 shadow-sm border-0"><div class="card-header bg-white"><h5 class="mb-0 fw-bold">{{ $l('ព័ត៌មានផ្ទាល់ខ្លួន', 'Personal information') }}</h5></div><div class="card-body">
                <div class="row mb-2"><div class="col-sm-5 text-muted">{{ $l('វន្ទនាការ', 'Salutation') }}</div><div class="col-sm-7">{{ $t(optional($extra)->salutation) }}</div></div>
                <div class="row mb-2"><div class="col-sm-5 text-muted">{{ $l('ភេទ', 'Gender') }}</div><div class="col-sm-7">{{ $g(optional($employee->gender)->gender_name) }}</div></div>
                <div class="row mb-2"><div class="col-sm-5 text-muted">{{ $l('ថ្ងៃខែឆ្នាំកំណើត', 'Date of birth') }}</div><div class="col-sm-7">{{ $d($employee->date_of_birth) }}</div></div>
                <div class="row mb-2"><div class="col-sm-5 text-muted">{{ $l('ទីកន្លែងកំណើត', 'Place of birth') }}</div><div class="col-sm-7">{{ $birth }}</div></div>
                <div class="row mb-2"><div class="col-sm-5 text-muted">{{ $l('សញ្ជាតិ', 'Nationality') }}</div><div class="col-sm-7">{{ $t($employee->nationality ?: $employee->citizenship) }}</div></div>
                <div class="row mb-2"><div class="col-sm-5 text-muted">{{ $l('សាសនា', 'Religion') }}</div><div class="col-sm-7">{{ $t($employee->religion) }}</div></div>
                <div class="row mb-2"><div class="col-sm-5 text-muted">{{ $l('ជនជាតិ/ក្រុមជនជាតិ', 'Ethnic group') }}</div><div class="col-sm-7">{{ $t($employee->ethnic_group) }}</div></div>
                <div class="row mb-2"><div class="col-sm-5 text-muted">{{ $l('ជនជាតិភាគតិច', 'Ethnic minority') }}</div><div class="col-sm-7">{{ $ethnicMinorityDisplay }}</div></div>
                <div class="row mb-2"><div class="col-sm-5 text-muted">{{ $l('ស្ថានភាពអាពាហ៍ពិពាហ៍', 'Marital status') }}</div><div class="col-sm-7">{{ $m(optional($employee->marital_status)->name) }}</div></div>
                <div class="row mb-2"><div class="col-sm-5 text-muted">{{ $l('អ៊ីមែល', 'Email') }}</div><div class="col-sm-7">{{ $t($employee->email ?: $employee->home_email) }}</div></div>
                <div class="row mb-2"><div class="col-sm-5 text-muted">Telegram</div><div class="col-sm-7">{{ $t(optional($extra)->telegram_account) }}</div></div>
                <div class="row mb-2"><div class="col-sm-5 text-muted">Facebook</div><div class="col-sm-7">{{ $t(optional($extra)->facebook_account) }}</div></div>
                <div class="row mb-0"><div class="col-sm-5 text-muted">{{ $l('អាសយដ្ឋានបច្ចុប្បន្ន', 'Current address') }}</div><div class="col-sm-7">{{ $address }}</div></div>
            </div></div>

            <div class="card mb-4 shadow-sm border-0"><div class="card-header bg-white"><h5 class="mb-0 fw-bold">{{ $l('អត្តសញ្ញាណ និងឯកសារផ្ទាល់ខ្លួន', 'Identity and personal documents') }}</h5></div><div class="card-body">
                <div class="row mb-2"><div class="col-sm-5 text-muted">{{ $l('លេខអត្តសញ្ញាណប័ណ្ណសញ្ជាតិខ្មែរ', 'National ID no.') }}</div><div class="col-sm-7">{{ $t($employee->national_id_no ?: $employee->national_id) }}</div></div>
                <div class="row mb-2"><div class="col-sm-5 text-muted">{{ $l('សុពលភាពអត្តសញ្ញាណប័ណ្ណ', 'National ID expiry date') }}</div><div class="col-sm-7">{{ $d(optional($extra)->national_id_expiry_date) }}</div></div>
                <div class="row mb-2"><div class="col-sm-5 text-muted">{{ $l('លេខលិខិតឆ្លងដែន', 'Passport no.') }}</div><div class="col-sm-7">{{ $t($employee->passport_no) }}</div></div>
                <div class="row mb-2"><div class="col-sm-5 text-muted">{{ $l('សុពលភាពលិខិតឆ្លងដែន', 'Passport expiry date') }}</div><div class="col-sm-7">{{ $d(optional($extra)->passport_expiry_date) }}</div></div>
                <div class="row mb-2"><div class="col-sm-5 text-muted">{{ $l('លេខប័ណ្ណបើកបរ', 'Driving license no.') }}</div><div class="col-sm-7">{{ $t($employee->driving_license_no) }}</div></div>
                <div class="row mb-0"><div class="col-sm-5 text-muted">{{ $l('សុពលភាពប័ណ្ណបើកបរ', 'Driving license expiry date') }}</div><div class="col-sm-7">{{ $d(optional($extra)->driving_license_expiry_date) }}</div></div>
            </div></div>

            <div class="card mb-4 shadow-sm border-0"><div class="card-header bg-white"><h5 class="mb-0 fw-bold">{{ $l('ព័ត៌មានអង្គភាព និងពេញសិទ្ធ', 'Work and full-right information') }}</h5></div><div class="card-body">
                <div class="row mb-2"><div class="col-sm-5 text-muted">{{ $l('អង្គភាពបច្ចុប្បន្ន', 'Current unit') }}</div><div class="col-sm-7">{{ $t($unit) }}</div></div>
                <div class="row mb-2"><div class="col-sm-5 text-muted">{{ $l('តួនាទី', 'Position') }}</div><div class="col-sm-7">{{ $t($position) }}</div></div>
                <div class="row mb-2"><div class="col-sm-5 text-muted">{{ $l('ជំនាញ', 'Specialization') }}</div><div class="col-sm-7">{{ $t($employee->skill_name ?: optional($extra)->current_work_skill) }}</div></div>
                <div class="row mb-2"><div class="col-sm-5 text-muted">{{ $l('កាំប្រាក់', 'Pay level') }}</div><div class="col-sm-7">{{ $t($employee->employee_grade ?: optional($extra)->current_salary_type) }}</div></div>
                <div class="row mb-2"><div class="col-sm-5 text-muted">{{ $l('ថ្ងៃចូលបម្រើការងារ', 'Service start date') }}</div><div class="col-sm-7">{{ $d($employee->service_start_date ?: $employee->joining_date) }}</div></div>
                <div class="row mb-2"><div class="col-sm-5 text-muted">{{ $l('ស្ថានភាពមន្ត្រី', 'Service state') }}</div><div class="col-sm-7">{{ $service }}</div></div>
                <div class="row mb-2"><div class="col-sm-5 text-muted">{{ $l('ស្ថានភាពការងារ', 'Work status') }}</div><div class="col-sm-7">{{ $t($employee->work_status_name) }}</div></div>
                <div class="row mb-2"><div class="col-sm-5 text-muted">{{ $l('ស្ថានភាពពេញសិទ្ធ', 'Full-right status') }}</div><div class="col-sm-7">{{ (int) ($employee->is_full_right_officer ?? 0) === 1 ? $l('ពេញសិទ្ធ', 'Full-right') : $l('មិនទាន់ពេញសិទ្ធ', 'Not yet full-right') }}</div></div>
                <div class="row mb-2"><div class="col-sm-5 text-muted">{{ $l('ថ្ងៃពេញសិទ្ធ', 'Full-right date') }}</div><div class="col-sm-7">{{ $d($employee->full_right_date) }}</div></div>
                <div class="row mb-2"><div class="col-sm-5 text-muted">{{ $l('ប្រភេទលិខិត', 'Document type') }}</div><div class="col-sm-7">{{ $doc($employee->legal_document_type) }}</div></div>
                <div class="row mb-0"><div class="col-sm-5 text-muted">{{ $l('លេខលិខិត', 'Document number') }}</div><div class="col-sm-7">{{ $t($employee->legal_document_number) }}</div></div>
            </div></div>

            <div class="card mb-4 shadow-sm border-0"><div class="card-header bg-white"><h5 class="mb-0 fw-bold">{{ $l('ប្រវត្តិការងារ', 'Service history') }}</h5></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-sm mb-0 align-middle"><thead><tr><th>{{ $l('កាលបរិច្ឆេទ', 'Date') }}</th><th>{{ $l('ប្រភេទ', 'Type') }}</th><th>{{ $l('ចំណងជើង', 'Title') }}</th><th>{{ $l('ព័ត៌មានលម្អិត', 'Details') }}</th></tr></thead><tbody>
                @forelse ($service_histories as $history)
                    <tr><td>{{ $d($history->event_date) }}</td><td>{{ $etype($history->event_type) }}</td><td>{{ $etitle($history->title) }}</td><td>{{ $t($history->details) }}</td></tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-3">{{ $l('មិនទាន់មានប្រវត្តិការងារ', 'No service history found') }}</td></tr>
                @endforelse
            </tbody></table></div></div></div>
        </div>

        <div class="col-lg-6">
            <div class="card mb-4 shadow-sm border-0"><div class="card-header bg-white"><h5 class="mb-0 fw-bold">{{ $l('សមាជិកគ្រួសារ', 'Family members') }}</h5></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-sm mb-0 align-middle"><thead><tr><th>{{ $l('ប្រភេទសមាជិក', 'Relation') }}</th><th>{{ $l('ឈ្មោះ', 'Name') }}</th><th>{{ $l('ភេទ', 'Gender') }}</th><th>{{ $l('ទូរសព្ទ', 'Phone') }}</th></tr></thead><tbody>
                @forelse ($employee->familyMembers as $member)
                    <tr><td>{{ $r($member->relation_type) }}</td><td>{{ $t(trim(($member->last_name_km ?: '') . ' ' . ($member->first_name_km ?: ''))) }}</td><td>{{ $g($member->gender) }}</td><td>{{ $t($member->phone) }}</td></tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-3">{{ $l('មិនទាន់មានទិន្នន័យគ្រួសារ', 'No family data found') }}</td></tr>
                @endforelse
            </tbody></table></div></div></div>

            <div class="card mb-4 shadow-sm border-0"><div class="card-header bg-white"><h5 class="mb-0 fw-bold">{{ $l('ព័ត៌មានធនាគារ', 'Bank information') }}</h5></div><div class="card-body"><div class="row mb-3"><div class="col-md-6"><strong>{{ $l('លេខប័ណ្ណសមាជិក ប.ស.ស', 'NSSF no.') }}:</strong> {{ $t($employee->sos) }}</div><div class="col-md-6"><strong>{{ $l('លេខ TIN', 'TIN no.') }}:</strong> {{ $t(optional($employee_file)->tin_no) }}</div></div><div class="table-responsive"><table class="table table-sm mb-0 align-middle"><thead><tr><th>{{ $l('ឈ្មោះគណនី', 'Account name') }}</th><th>{{ $l('លេខគណនី', 'Account number') }}</th><th>{{ $l('ឈ្មោះធនាគារ', 'Bank name') }}</th></tr></thead><tbody>
                @forelse ($bankRows as $row)
                    <tr><td>{{ $t($row->account_name ?? null) }}</td><td>{{ $t($row->account_number ?? null) }}</td><td>{{ $t($row->bank_name ?? null) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted py-3">{{ $l('មិនទាន់មានព័ត៌មានធនាគារ', 'No bank information found') }}</td></tr>
                @endforelse
            </tbody></table></div></div></div>

            <div class="card mb-4 shadow-sm border-0"><div class="card-header bg-white"><h5 class="mb-0 fw-bold">{{ $l('ការបណ្តុះបណ្តាល', 'Training information') }}</h5></div><div class="card-body"><div class="row mb-3"><div class="col-md-6"><strong>{{ $l('កម្រិតវប្បធម៌ជាតិ', 'National education level') }}:</strong> {{ $edu }}</div><div class="col-md-6"><strong>{{ $l('កម្រិតវប្បធម៌ខ្ពស់បំផុត', 'Highest educational qualification') }}:</strong> {{ $t($employee->highest_educational_qualification) }}</div></div>
                <h6 class="fw-bold mb-2">{{ $l('ប្រវត្តិសិក្សា', 'Education history') }}</h6>
                <div class="table-responsive mb-3">
                    <table class="table table-sm mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>{{ $l('ស្ថាប័នសិក្សា', 'Institution') }}</th>
                                <th>{{ $l('ឆ្នាំចាប់ផ្តើម', 'Start year') }}</th>
                                <th>{{ $l('ឆ្នាំបញ្ចប់', 'End year') }}</th>
                                <th>{{ $l('កម្រិតសញ្ញាបត្រ', 'Degree level') }}</th>
                                <th>{{ $l('មុខវិជ្ជា/ជំនាញសិក្សា', 'Major / field of study') }}</th>
                                <th>{{ $l('សម្គាល់', 'Remarks') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($educationRows as $row)
                                <tr>
                                    <td>{{ $t($row->institution_name ?? null) }}</td>
                                    <td>{{ $y($row->start_date ?? null) }}</td>
                                    <td>{{ $y($row->end_date ?? null) }}</td>
                                    <td>{{ $degree($row->degree_level ?? null) }}</td>
                                    <td>{{ $t($row->major_subject ?? null) }}</td>
                                    <td>{{ $t($row->note ?? null) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-3">{{ $l('មិនទាន់មានប្រវត្តិសិក្សា', 'No education history found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <h6 class="fw-bold mb-2">{{ $l('ភាសាបរទេស', 'Foreign languages') }}</h6><ul class="mb-0">@forelse ($languageRows as $row)<li>{{ $t($row->language_name ?? null) }} | {{ $l('និយាយ', 'Speaking') }}: {{ $t($row->speaking_level ?? null) }} | {{ $l('អាន', 'Reading') }}: {{ $t($row->reading_level ?? null) }} | {{ $l('សរសេរ', 'Writing') }}: {{ $t($row->writing_level ?? null) }}</li>@empty<li class="text-muted">{{ $l('មិនទាន់មានព័ត៌មានភាសាបរទេស', 'No foreign language data found') }}</li>@endforelse</ul>
            </div></div>

            <div class="card mb-4 shadow-sm border-0"><div class="card-header bg-white"><h5 class="mb-0 fw-bold">{{ $l('ឯកសារ និងកំណត់ត្រាលិខិត', 'Documents and legal records') }}</h5></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-sm mb-0 align-middle"><thead><tr><th>{{ $l('ប្រភេទ', 'Type') }}</th><th>{{ $l('ចំណងជើង/លេខ', 'Title / number') }}</th><th>{{ $l('ថ្ងៃខែឆ្នាំ', 'Date') }}</th><th>{{ $l('សម្គាល់', 'Notes') }}</th></tr></thead><tbody>
                @forelse ($employee->employee_docs as $row)
                    <tr><td>{{ $l('ឯកសារភ្ជាប់', 'Attachment') }}</td><td>{{ $t($row->doc_title) }}</td><td>{{ $d($row->expiry_date) }}</td><td>@if (!empty($row->file_path))<a href="{{ url('/public/storage/' . $row->file_path) }}" target="_blank" rel="noopener noreferrer">{{ $l('មើលឯកសារ', 'View file') }}</a>@else-@endif</td></tr>
                @empty @endforelse
                @forelse ($legal_records as $row)
                    <tr><td>{{ $doc($row->document_type) }}</td><td>{{ $t($row->document_number) }}</td><td>{{ $d($row->document_date ?: $row->effective_date) }}</td><td>{{ $t($row->subject) }}</td></tr>
                @empty
                    @if ($employee->employee_docs->isEmpty())<tr><td colspan="4" class="text-center text-muted py-3">{{ $l('មិនទាន់មានឯកសារ ឬកំណត់ត្រាលិខិត', 'No documents or legal records found') }}</td></tr>@endif
                @endforelse
            </tbody></table></div></div></div>
        </div>
    </div>
    </div>
@endsection
