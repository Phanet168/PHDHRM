<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <title>ព័ត៌មានលម្អិតបុគ្គលិក</title>
    @php
        $fontToFileUri = static function (?string $path): ?string {
            if (!$path || !is_file($path)) {
                return null;
            }

            return 'file:///' . ltrim(str_replace('\\', '/', $path), '/');
        };

        $khmerBodyFontPath = storage_path('fonts/KhmerOSsiemreap.ttf');
        if (!is_file($khmerBodyFontPath)) {
            $khmerBodyFontPath = collect(glob(storage_path('fonts/khmerbody_normal_*.ttf')) ?: [])->first();
        }

        $khmerHeadFontPath = storage_path('fonts/khmer M1.volt.ttf');
        if (!is_file($khmerHeadFontPath)) {
            $khmerHeadFontPath = collect(glob(storage_path('fonts/*M1*.ttf')) ?: [])->first();
        }

        $tacteingFontPath = storage_path('fonts/TACTENG.TTF');
        if (!is_file($tacteingFontPath)) {
            $tacteingFontPath = collect(glob(storage_path('fonts/*TACT*.TTF')) ?: [])->first();
        }

        $khmerBodyFontUri = $fontToFileUri($khmerBodyFontPath);
        $khmerHeadFontUri = $fontToFileUri($khmerHeadFontPath);
        $tacteingFontUri = $fontToFileUri($tacteingFontPath);

        $toKhmerDigits = static function ($value): string {
            return strtr((string) $value, [
                '0' => '០',
                '1' => '១',
                '2' => '២',
                '3' => '៣',
                '4' => '៤',
                '5' => '៥',
                '6' => '៦',
                '7' => '៧',
                '8' => '៨',
                '9' => '៩',
            ]);
        };

        $normalizeKhmerText = static function ($value): string {
            $clean = trim((string) $value);
            if ($clean === '') {
                return '';
            }

            if (preg_match('/\p{Khmer}/u', $clean)) {
                return $clean;
            }

            $looksMojibake = str_contains($clean, 'Ã')
                || str_contains($clean, 'Â')
                || str_contains($clean, 'áŸ')
                || str_contains($clean, 'â€')
                || str_contains($clean, 'Æ');

            if (!$looksMojibake) {
                return $clean;
            }

            $candidates = [$clean];
            foreach ($candidates as $candidate) {
                foreach ([
                    ['UTF-8', 'Windows-1252//IGNORE'],
                    ['Windows-1252', 'UTF-8//IGNORE'],
                    ['UTF-8', 'ISO-8859-1//IGNORE'],
                    ['ISO-8859-1', 'UTF-8//IGNORE'],
                ] as [$from, $to]) {
                    $decoded = @iconv($from, $to, $candidate);
                    if (!is_string($decoded) || $decoded === '') {
                        continue;
                    }

                    $decoded = trim($decoded);
                    if ($decoded === '') {
                        continue;
                    }

                    if (preg_match('/\p{Khmer}/u', $decoded)) {
                        return $decoded;
                    }
                }
            }

            return $clean;
        };

        $text = static function ($value) use ($normalizeKhmerText): string {
            $clean = $normalizeKhmerText($value);
            return $clean === '' ? '-' : $clean;
        };

        $khDate = static function ($value) use ($toKhmerDigits, $normalizeKhmerText): string {
            if (blank($value)) {
                return '-';
            }

            try {
                return $toKhmerDigits(\Illuminate\Support\Carbon::parse($value)->format('d/m/Y'));
            } catch (\Throwable $exception) {
                return $toKhmerDigits($normalizeKhmerText($value));
            }
        };

        $genderKh = static function ($value) use ($text, $normalizeKhmerText): string {
            $raw = $normalizeKhmerText($value);
            if ($raw === '') {
                return '-';
            }

            $lower = mb_strtolower($raw, 'UTF-8');
            return match ($lower) {
                'male', 'm', 'ប្រុស' => 'ប្រុស',
                'female', 'f', 'ស្រី' => 'ស្រី',
                default => $text($raw),
            };
        };

        $relationKh = static function ($value) use ($text, $normalizeKhmerText): string {
            $raw = $normalizeKhmerText($value);
            if ($raw === '') {
                return '-';
            }

            $lower = mb_strtolower($raw, 'UTF-8');
            return match ($lower) {
                'wife', 'spouse_wife' => 'ប្រពន្ធ',
                'husband', 'spouse_husband' => 'ប្តី',
                'son', 'child_male' => 'កូនប្រុស',
                'daughter', 'child_female' => 'កូនស្រី',
                'father' => 'ឪពុកបង្កើត',
                'mother' => 'ម្តាយបង្កើត',
                default => $text($raw),
            };
        };

        $buildAddress = static function ($village, $commune, $district, $province) use ($text, $normalizeKhmerText): string {
            $parts = [];
            $village = $normalizeKhmerText($village);
            $commune = $normalizeKhmerText($commune);
            $district = $normalizeKhmerText($district);
            $province = $normalizeKhmerText($province);

            if ($village !== '') {
                $parts[] = 'ភូមិ ' . $village;
            }
            if ($commune !== '') {
                $parts[] = 'ឃុំ/សង្កាត់ ' . $commune;
            }
            if ($district !== '') {
                $parts[] = 'ក្រុង/ស្រុក ' . $district;
            }
            if ($province !== '') {
                $parts[] = 'ខេត្ត ' . $province;
            }

            return empty($parts) ? '-' : implode(' ', $parts);
        };

        $profile = $profile ?? [];
        $employee = $employee ?? null;
        $familyMembers = collect($family_members ?? []);
        $educationHistories = collect($education_histories ?? []);
        $foreignLanguages = collect($foreign_languages ?? []);
        $workHistories = collect($work_histories ?? []);
        $serviceHistories = collect($service_histories ?? []);
        $bankAccounts = collect($bank_accounts ?? []);

        $logoSrc = null;
        try {
            $logoCandidates = [];
            // Prefer official province emblem (round fish logo) for header documents.
            foreach ((glob(public_path('assets/logo/logo/Logo*.png')) ?: []) as $emblem) {
                $logoCandidates[] = $emblem;
            }
            $logo = trim((string) data_get($application ?? null, 'logo', ''));
            if ($logo !== '') {
                $clean = ltrim(str_replace('\\', '/', $logo), '/');
                $logoCandidates[] = public_path('storage/' . $clean);
                $logoCandidates[] = storage_path('app/public/' . $clean);
                $logoCandidates[] = public_path($clean);
            }
            $logoCandidates[] = public_path('assets/HRM1.png');

            foreach ($logoCandidates as $candidate) {
                if (!is_file($candidate)) {
                    continue;
                }

                $ext = strtolower((string) pathinfo($candidate, PATHINFO_EXTENSION));
                $mimeByExt = [
                    'png' => 'image/png',
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'gif' => 'image/gif',
                    'svg' => 'image/svg+xml',
                ];
                $mime = $mimeByExt[$ext] ?? null;

                if ($mime !== null) {
                    $data = @file_get_contents($candidate);
                    if ($data !== false) {
                        $logoSrc = 'data:' . $mime . ';base64,' . base64_encode($data);
                        break;
                    }
                }

                $logoSrc = 'file:///' . ltrim(str_replace('\\', '/', $candidate), '/');
                break;
            }
        } catch (\Throwable $exception) {
            $logoSrc = null;
        }

        $employeeNameKh = trim((string) data_get($profile, 'full_name', data_get($employee, 'full_name')));
        $employeeNameLatin = trim((string) data_get($profile, 'full_name_latin', data_get($employee, 'full_name_latin')));
        $employeeGender = data_get($profile, 'gender', data_get($employee, 'gender.gender_name'));
        $employeeGenderId = (int) data_get($profile, 'gender_id', data_get($employee, 'gender_id', 0));
        $employeeGenderLabel = $genderKh($employeeGender);
        if (($employeeGenderLabel === '-' || !preg_match('/\p{Khmer}/u', $employeeGenderLabel)) && $employeeGenderId === 1) {
            $employeeGenderLabel = 'ប្រុស';
        } elseif (($employeeGenderLabel === '-' || !preg_match('/\p{Khmer}/u', $employeeGenderLabel)) && $employeeGenderId === 2) {
            $employeeGenderLabel = 'ស្រី';
        } elseif ($employeeGenderLabel !== '-' && !preg_match('/\p{Khmer}/u', $employeeGenderLabel)) {
            $employeeGenderLabel = '-';
        }
        $employeeDob = data_get($profile, 'date_of_birth', data_get($employee, 'date_of_birth'));
        $employeeOfficialId10 = trim((string) (data_get($profile, 'official_id_10', data_get($employee, 'official_id_10'))));
        $employeeCardNo = trim((string) (data_get($profile, 'employee_id', data_get($employee, 'employee_id'))));
        $employeePosition = trim((string) (data_get($profile, 'position_name', data_get($employee, 'position.position_name_km', data_get($employee, 'position.position_name')))));
        $employeeUnit = trim((string) (data_get($profile, 'department_name', data_get($employee, 'department.department_name_km', data_get($employee, 'department.department_name')))));
        $employeeSkill = trim((string) data_get($profile, 'current_work_skill', data_get($profile, 'technical_skill', data_get($employee, 'skill_name', ''))));
        $employeeWorkStatus = trim((string) data_get($profile, 'work_status_name', data_get($employee, 'work_status_name', '')));
        $employeeSalaryLevel = trim((string) data_get($profile, 'current_salary_grade', data_get($employee, 'employee_grade', '')));
        $employeeType = trim((string) data_get($profile, 'employee_type_name', data_get($employee, 'employee_type.name', data_get($employee, 'employee_type.employee_type_name'))));

        $nationalIdNo = trim((string) data_get($profile, 'national_id_no', data_get($employee, 'national_id_no', data_get($employee, 'national_id'))));
        $passportNo = trim((string) data_get($profile, 'passport_no', data_get($employee, 'passport_no')));
        $drivingLicenseNo = trim((string) data_get($profile, 'driving_license_no', data_get($employee, 'driving_license_no')));
        $nationalIdExpiry = data_get($profile, 'national_id_expiry_date');
        $passportExpiry = data_get($profile, 'passport_expiry_date');
        $drivingLicenseExpiry = data_get($profile, 'driving_license_expiry_date');
        $registrationDate = data_get($profile, 'registration_date');
        $professionalRegistrationNo = trim((string) data_get($profile, 'professional_registration_no'));
        $institutionContactNo = trim((string) data_get($profile, 'institution_contact_no'));
        $institutionEmail = trim((string) data_get($profile, 'institution_email'));
        $telegramAccount = trim((string) data_get($profile, 'telegram_account'));
        $facebookAccount = trim((string) data_get($profile, 'facebook_account'));
        $currentPositionStartDate = data_get($profile, 'current_position_start_date');
        $currentPositionDocumentNo = trim((string) data_get($profile, 'current_position_document_number'));
        $currentPositionDocumentDate = data_get($profile, 'current_position_document_date');
        $technicalRoleType = trim((string) data_get($profile, 'technical_role_type'));
        $frameworkType = trim((string) data_get($profile, 'framework_type'));

        $birthPlace = trim((string) data_get($profile, 'birth_place_full', data_get($profile, 'birth_place')));
        $presentAddress = trim((string) data_get($profile, 'present_address_full', data_get($profile, 'present_address')));

        // Use province name only on date line (not organization name).
        $issuePlace = 'ស្ទឹងត្រែង';
        $issueDateLine = $issuePlace . ' ថ្ងៃទី ' . $toKhmerDigits(now()->format('d')) . ' ខែ ' . $toKhmerDigits(now()->format('m')) . ' ឆ្នាំ ' . $toKhmerDigits(now()->format('Y'));
        $lunarDateText = trim((string) data_get($profile, 'lunar_date_text', ''));
        $solarDateText = trim((string) data_get($profile, 'solar_date_text', ''));
    @endphp
    <style>
        @if (!empty($khmerBodyFontUri))
        @font-face {
            font-family: "Khmer OS Siemreap";
            font-style: normal;
            font-weight: normal;
            src: url("{{ $khmerBodyFontUri }}") format("truetype");
        }
        @endif

        @if (!empty($khmerHeadFontUri))
        @font-face {
            font-family: "Khmer M1";
            font-style: normal;
            font-weight: normal;
            src: url("{{ $khmerHeadFontUri }}") format("truetype");
        }
        @endif

        @if (!empty($tacteingFontUri))
        @font-face {
            font-family: "Tacteing";
            font-style: normal;
            font-weight: normal;
            src: url("{{ $tacteingFontUri }}") format("truetype");
        }
        @endif

        @page {
            size: A4 portrait;
            margin: 1.5cm 1.4cm 1.8cm 1.8cm;
        }

        body {
            margin: 0;
            color: #111;
            font-size: 11.5pt;
            line-height: 1.45;
            font-family: "khmerbody", "khmerfallback", "Khmer OS Siemreap", "Noto Sans Khmer", "DejaVu Sans", sans-serif;
        }

        .head-font {
            font-family: "Khmer M1", "khmerhead", "khmerbody", "khmerfallback", "Khmer OS Siemreap", "Noto Sans Khmer", "DejaVu Sans", sans-serif;
            font-weight: normal;
            line-height: 1.55;
        }

        .page {
            width: 100%;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        .header-left {
            width: 32%;
            vertical-align: top;
            text-align: center;
            padding-top: 52px;
        }

        .header-center {
            width: 42%;
            vertical-align: top;
            text-align: center;
        }

        .header-right {
            width: 26%;
            vertical-align: top;
            text-align: right;
        }

        .logo {
            width: 96px;
            height: 96px;
            object-fit: contain;
            display: block;
            margin: 0 auto 8px auto;
        }

        .org-line {
            font-size: 10.5pt;
            margin: 0 0 2px 0;
            line-height: 1.5;
            padding-bottom: 2px;
            color: #0b3b8f;
            font-weight: normal;
            text-align: center;
            letter-spacing: 0;
            word-break: keep-all;
            overflow-wrap: normal;
        }

        .kingdom-line {
            margin: 0;
            font-size: 16pt;
            color: #0b3b8f;
            line-height: 1.5;
            padding-bottom: 2px;
            font-weight: normal;
        }

        .ornament {
            margin: 2px 0 0 0;
            font-size: 40pt;
            color: #0b3b8f;
            font-family: "Tacteing", "Khmer M1", "Khmer OS Siemreap", "Noto Sans Khmer", "DejaVu Sans", serif;
            font-weight: normal;
            line-height: 1;
        }

        .doc-title {
            text-align: center;
            margin: 6px 0 14px 0;
            font-size: 17pt;
            color: #0b3b8f;
            font-family: "Khmer M1", "Khmer OS Siemreap", "Noto Sans Khmer", "DejaVu Sans", serif;
            font-weight: normal;
            line-height: 1.5;
        }

        .section {
            margin-bottom: 10px;
            page-break-inside: avoid;
        }

        .section-title {
            padding: 6px 8px;
            border: 1px solid #2f3a4a;
            background: #eef2f7;
            font-weight: bold;
            color: #10233f;
        }

        .kv-table,
        .list-table {
            width: 100%;
            border-collapse: collapse;
        }

        .kv-table th,
        .kv-table td,
        .list-table th,
        .list-table td {
            border: 1px solid #2f3a4a;
            padding: 5px 7px;
            vertical-align: top;
        }

        .kv-table th {
            width: 18%;
            background: #f7f9fc;
            text-align: left;
            font-weight: bold;
        }

        .list-table thead th {
            background: #f2f5fa;
            text-align: left;
            font-weight: bold;
        }

        .muted {
            color: #55657d;
        }

        .no-data {
            text-align: center;
            color: #55657d;
            font-style: italic;
        }

        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
        }

        .signature-table td {
            width: 50%;
            text-align: center;
            vertical-align: top;
        }

        .signature-date {
            margin-bottom: 8px;
        }

        .date-with-logo {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            white-space: nowrap;
        }

        .date-logo {
            width: 24px;
            height: 24px;
            object-fit: contain;
            vertical-align: middle;
        }

        .signature-gap {
            height: 70px;
        }
    </style>
</head>
<body>
<div class="page">
    <table class="header-table">
        <tr>
            <td class="header-left">
                @if (!empty($logoSrc))
                    <img src="{{ $logoSrc }}" class="logo" alt="logo">
                @endif
                <p class="org-line head-font">រដ្ឋបាលខេត្តស្ទឹងត្រែង</p>
                <p class="org-line head-font">មន្ទីរសុខាភិបាលនៃរដ្ឋបាលខេត្ត</p>
            </td>
            <td class="header-center">
                <p class="kingdom-line head-font">ព្រះរាជាណាចក្រកម្ពុជា</p>
                <p class="kingdom-line head-font">ជាតិ សាសនា ព្រះមហាក្សត្រ</p>
                <p class="ornament">6</p>
            </td>
            <td class="header-right"></td>
        </tr>
    </table>

    <h1 class="doc-title head-font">ព័ត៌មានលម្អិតអំពីមន្ត្រីរាជការ</h1>

    <div class="section">
        <div class="section-title">១. ព័ត៌មានផ្ទាល់ខ្លួន</div>
        <table class="kv-table">
            <tr>
                <th>គោតនាម និងនាម</th>
                <td>{{ $text($employeeNameKh) }}</td>
                <th>ឈ្មោះឡាតាំង</th>
                <td>{{ $text($employeeNameLatin) }}</td>
            </tr>
            <tr>
                <th>ភេទ</th>
                <td>{{ $employeeGenderLabel }}</td>
                <th>ថ្ងៃខែឆ្នាំកំណើត</th>
                <td>{{ $khDate($employeeDob) }}</td>
            </tr>
            <tr>
                <th>ទីកន្លែងកំណើត</th>
                <td colspan="3">{{ $text($birthPlace) }}</td>
            </tr>
            <tr>
                <th>អាសយដ្ឋានបច្ចុប្បន្ន</th>
                <td colspan="3">{{ $text($presentAddress) }}</td>
            </tr>
            <tr>
                <th>អត្តលេខមន្ត្រី (១០ ខ្ទង់)</th>
                <td>{{ $text($employeeOfficialId10) }}</td>
                <th>លេខសម្គាល់បុគ្គលិក</th>
                <td>{{ $text($employeeCardNo) }}</td>
            </tr>
            <tr>
                <th>លេខទូរសព្ទ</th>
                <td>{{ $text(data_get($employee, 'phone')) }}</td>
                <th>អ៊ីមែល</th>
                <td>
            <tr>
                <th>អត្តសញ្ញាណប័ណ្ណជាតិ</th>
                <td>{{ $text($nationalIdNo) }}</td>
                <th>សុពលភាពអត្តសញ្ញាណប័ណ្ណ</th>
                <td>{{ $khDate($nationalIdExpiry) }}</td>
            </tr>
            <tr>
                <th>លេខលិខិតឆ្លងដែន</th>
                <td>{{ $text($passportNo) }}</td>
                <th>សុពលភាពលិខិតឆ្លងដែន</th>
                <td>{{ $khDate($passportExpiry) }}</td>
            </tr>
            <tr>
                <th>លេខប័ណ្ណបើកបរ</th>
                <td>{{ $text($drivingLicenseNo) }}</td>
                <th>សុពលភាពប័ណ្ណបើកបរ</th>
                <td>{{ $khDate($drivingLicenseExpiry) }}</td>
            </tr>
            <tr>
                <th>សញ្ជាតិ</th>
                <td>{{ $text(data_get($profile, 'citizenship')) }}</td>
                <th>ជនជាតិ</th>
                <td>{{ $text(data_get($profile, 'nationality')) }}</td>
            </tr>
            <tr>
                <th>សាសនា</th>
                <td>{{ $text(data_get($profile, 'religion')) }}</td>
                <th>ស្ថានភាពអាពាហ៍ពិពាហ៍</th>
                <td>
            <tr>
                <th>Telegram</th>
                <td>{{ $text($telegramAccount) }}</td>
                <th>Facebook</th>
                <td>{{ $text($facebookAccount) }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">២. ព័ត៌មានអង្គភាព និងការងារ</div>
        <table class="kv-table">
            <tr>
                <th>អង្គភាព</th>
                <td>{{ $text($employeeUnit) }}</td>
                <th>មុខតំណែង</th>
                <td>{{ $text($employeePosition) }}</td>
            </tr>
            <tr>
                <th>ជំនាញបច្ចុប្បន្ន</th>
                <td>{{ $text($employeeSkill) }}</td>
                <th>ស្ថានភាពការងារ</th>
                <td>{{ $text($employeeWorkStatus) }}</td>
            </tr>
            <tr>
                <th>ប្រភេទបុគ្គលិក</th>
                <td>{{ $text($employeeType) }}</td>
                <th>កាំប្រាក់</th>
                <td>{{ $text($employeeSalaryLevel) }}</td>
            </tr>
            <tr>
                <th>ថ្ងៃចូលបម្រើការងារ</th>
                <td>{{ $khDate(data_get($profile, 'service_start_date')) }}</td>
                <th>ថ្ងៃពេញសិទ្ធ</th>
                <td>
            <tr>
                <th>មុខងារបច្ចេកទេស</th>
                <td>{{ $text($technicalRoleType) }}</td>
                <th>ក្របខណ្ឌ</th>
                <td>{{ $text($frameworkType) }}</td>
            </tr>
            <tr>
                <th>ថ្ងៃចាប់ផ្តើមតួនាទី</th>
                <td>{{ $khDate($currentPositionStartDate) }}</td>
                <th>លេខលិខិតតួនាទី</th>
                <td>{{ $text($currentPositionDocumentNo) }}</td>
            </tr>
            <tr>
                <th>កាលបរិច្ឆេទលិខិតតួនាទី</th>
                <td>{{ $khDate($currentPositionDocumentDate) }}</td>
                <th>កាលបរិច្ឆេទចុះបញ្ជីគណៈវិជ្ជាជីវៈ</th>
                <td>{{ $khDate($registrationDate) }}</td>
            </tr>
            <tr>
                <th>លេខចុះបញ្ជីគណៈវិជ្ជាជីវៈ</th>
                <td>{{ $text($professionalRegistrationNo) }}</td>
                <th>លេខទំនាក់ទំនងស្ថាប័ន</th>
                <td>{{ $text($institutionContactNo) }}</td>
            </tr>
            <tr>
                <th>អ៊ីមែលស្ថាប័ន</th>
                <td colspan="3">{{ $text($institutionEmail) }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">៣. សមាជិកគ្រួសារ</div>
        <table class="list-table">
            <thead>
            <tr>
                <th style="width: 4%;">ល.រ</th>
                <th style="width: 13%;">ប្រភេទសមាជិក</th>
                <th style="width: 16%;">ឈ្មោះ</th>
                <th style="width: 7%;">ភេទ</th>
                <th style="width: 11%;">ថ្ងៃខែឆ្នាំកំណើត</th>
                <th style="width: 12%;">សញ្ជាតិ/ជនជាតិ</th>
                <th style="width: 11%;">មុខរបរ</th>
                <th style="width: 26%;">អាសយដ្ឋានបច្ចុប្បន្ន</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($familyMembers as $idx => $member)
                <tr>
                    <td>{{ $toKhmerDigits($idx + 1) }}</td>
                    <td>{{ $relationKh($member->relation_type) }}</td>
                    <td>{{ $text(trim((string) (($member->last_name_km ?? '') . ' ' . ($member->first_name_km ?? '')))) }}</td>
                    <td>{{ $genderKh($member->gender) }}</td>
                    <td>{{ $khDate($member->date_of_birth) }}</td>
                    <td>{{ $text(trim((string) (($member->nationality ?? '') . ' / ' . ($member->ethnicity ?? '')))) }}</td>
                    <td>{{ $text($member->occupation) }}</td>
                    <td>{{ $buildAddress($member->present_address_village, $member->present_address_commune, $member->present_address_city, $member->present_address_state) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="no-data">មិនទាន់មានទិន្នន័យសមាជិកគ្រួសារ</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">៤. ប្រវត្តិសិក្សា និងភាសាបរទេស</div>
        <table class="list-table" style="margin-bottom: 6px;">
            <thead>
            <tr>
                <th style="width: 4%;">ល.រ</th>
                <th style="width: 30%;">គ្រឹះស្ថានសិក្សា</th>
                <th style="width: 20%;">កម្រិតសញ្ញាបត្រ</th>
                <th style="width: 18%;">មុខវិជ្ជា</th>
                <th style="width: 14%;">ចាប់ផ្តើម</th>
                <th style="width: 14%;">បញ្ចប់</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($educationHistories as $idx => $row)
                <tr>
                    <td>{{ $toKhmerDigits($idx + 1) }}</td>
                    <td>{{ $text($row->institution_name) }}</td>
                    <td>{{ $text($row->degree_level) }}</td>
                    <td>{{ $text($row->major_subject) }}</td>
                    <td>{{ $khDate($row->start_date) }}</td>
                    <td>{{ $khDate($row->end_date) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="no-data">មិនទាន់មានប្រវត្តិសិក្សា</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        <table class="list-table">
            <thead>
            <tr>
                <th style="width: 4%;">ល.រ</th>
                <th style="width: 16%;">ភាសា</th>
                <th style="width: 12%;">និយាយ</th>
                <th style="width: 12%;">អាន</th>
                <th style="width: 12%;">សរសេរ</th>
                <th style="width: 22%;">គ្រឹះស្ថាន</th>
                <th style="width: 11%;">ចាប់ផ្តើម</th>
                <th style="width: 11%;">បញ្ចប់</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($foreignLanguages as $idx => $row)
                <tr>
                    <td>{{ $toKhmerDigits($idx + 1) }}</td>
                    <td>{{ $text($row->language_name) }}</td>
                    <td>{{ $text($row->speaking_level) }}</td>
                    <td>{{ $text($row->reading_level) }}</td>
                    <td>{{ $text($row->writing_level) }}</td>
                    <td>{{ $text($row->institution_name) }}</td>
                    <td>{{ $khDate($row->start_date) }}</td>
                    <td>{{ $khDate($row->end_date) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="no-data">មិនទាន់មានព័ត៌មានភាសាបរទេស</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">៥. ប្រវត្តិការងារ និងព័ត៌មានស្ថានភាព</div>
        <table class="list-table" style="margin-bottom: 6px;">
            <thead>
            <tr>
                <th style="width: 4%;">ល.រ</th>
                <th style="width: 15%;">ស្ថានភាពការងារ</th>
                <th style="width: 14%;">ថ្ងៃចាប់ផ្តើម</th>
                <th style="width: 24%;">យោងលិខិត</th>
                <th style="width: 13%;">ថ្ងៃលិខិត</th>
                <th style="width: 30%;">កំណត់សម្គាល់</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($workHistories as $idx => $row)
                <tr>
                    <td>{{ $toKhmerDigits($idx + 1) }}</td>
                    <td>{{ $text($row->work_status_name) }}</td>
                    <td>{{ $khDate($row->start_date) }}</td>
                    <td>{{ $text($row->document_reference) }}</td>
                    <td>{{ $khDate($row->document_date) }}</td>
                    <td>{{ $text($row->note) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="no-data">មិនទាន់មានប្រវត្តិការងារ</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        <table class="list-table">
            <thead>
            <tr>
                <th style="width: 4%;">ល.រ</th>
                <th style="width: 15%;">ប្រភេទព្រឹត្តិការណ៍</th>
                <th style="width: 13%;">កាលបរិច្ឆេទ</th>
                <th style="width: 20%;">ចំណងជើង</th>
                <th style="width: 48%;">ព័ត៌មានលម្អិត</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($serviceHistories as $idx => $row)
                <tr>
                    <td>{{ $toKhmerDigits($idx + 1) }}</td>
                    <td>{{ $text($row->event_type) }}</td>
                    <td>{{ $khDate($row->event_date) }}</td>
                    <td>{{ $text($row->title) }}</td>
                    <td>{{ $text($row->details) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="no-data">មិនទាន់មានប្រវត្តិសេវាកម្ម</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">៦. ព័ត៌មានគណនីធនាគារ</div>
        <table class="list-table">
            <thead>
            <tr>
                <th style="width: 4%;">ល.រ</th>
                <th style="width: 26%;">ឈ្មោះគណនី</th>
                <th style="width: 24%;">លេខគណនី</th>
                <th style="width: 24%;">ឈ្មោះធនាគារ</th>
                <th style="width: 22%;">ឯកសារភ្ជាប់</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($bankAccounts as $idx => $row)
                <tr>
                    <td>{{ $toKhmerDigits($idx + 1) }}</td>
                    <td>{{ $text($row->account_name) }}</td>
                    <td>{{ $text($row->account_number) }}</td>
                    <td>{{ $text($row->bank_name) }}</td>
                    <td class="muted">{{ trim((string) ($row->attachment_name ?? '')) !== '' ? $row->attachment_name : '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="no-data">មិនទាន់មានព័ត៌មានគណនីធនាគារ</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <table class="signature-table">
        <tr>
            <td>
                <div class="signature-date date-with-logo">
                    <span>{{ $issueDateLine }}</span>
                </div>
                <div class="head-font">បានឃើញ និងឯកភាព</div>
                <div class="head-font">ប្រធានអង្គភាព</div>
                <div class="signature-gap"></div>
            </td>
            <td>
                @if ($lunarDateText !== '' && $lunarDateText !== '-')
                    <div class="signature-date">{{ $lunarDateText }}</div>
                @endif
                @if ($solarDateText !== '' && $solarDateText !== '-')
                    <div class="signature-date">{{ $solarDateText }}</div>
                @else
                    <div class="signature-date date-with-logo">
                        <span>{{ $issueDateLine }}</span>
                    </div>
                @endif
                <div class="head-font">មន្ត្រីគ្រប់គ្រងបុគ្គលិក</div>
                <div class="signature-gap"></div>
            </td>
        </tr>
    </table>
</div>
</body>
</html>
