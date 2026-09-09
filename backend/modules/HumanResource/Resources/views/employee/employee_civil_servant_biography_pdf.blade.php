<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <title>áž‡áž¸ážœáž”áŸ’ážšážœážáŸ’ážáž·áž˜áž“áŸ’ážáŸ’ážšáž¸ážšáž¶áž‡áž€áž¶ážš</title>
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

        $khmerM1FontPath = storage_path('fonts/khmer M1.volt.ttf');
        if (!is_file($khmerM1FontPath)) {
            $khmerM1FontPath = collect(glob(storage_path('fonts/*M1*.ttf')) ?: [])->first();
        }

        $tacteingFontPath = storage_path('fonts/TACTENG.TTF');
        if (!is_file($tacteingFontPath)) {
            $tacteingFontPath = collect(glob(storage_path('fonts/*TACT*.TTF')) ?: [])->first();
        }

        $khmerTitleFontPath = storage_path('fonts/KhmerOSmuollight.ttf');
        if (!is_file($khmerTitleFontPath)) {
            $khmerTitleFontPath = storage_path('fonts/KhmerOSmuol.ttf');
        }
        if (!is_file($khmerTitleFontPath)) {
            $khmerTitleFontPath = collect(glob(storage_path('fonts/khmerhead_normal_*.ttf')) ?: [])->first();
        }

        $khmerBodyFontUri = $fontToFileUri($khmerBodyFontPath);
        $khmerM1FontUri = $fontToFileUri($khmerM1FontPath);
        $tacteingFontUri = $fontToFileUri($tacteingFontPath);
        $khmerTitleFontUri = $fontToFileUri($khmerTitleFontPath);

        $toKhmerDigits = static function ($value): string {
            return strtr((string) $value, [
                '0' => 'áŸ ',
                '1' => 'áŸ¡',
                '2' => 'áŸ¢',
                '3' => 'áŸ£',
                '4' => 'áŸ¤',
                '5' => 'áŸ¥',
                '6' => 'áŸ¦',
                '7' => 'áŸ§',
                '8' => 'áŸ¨',
                '9' => 'áŸ©',
            ]);
        };

        $clean = static function ($value) use ($toKhmerDigits): string {
            return trim($toKhmerDigits((string) $value));
        };

        $cleanLatin = static function ($value): string {
            return trim((string) $value);
        };

        $toKhmerDate = static function ($value, string $format = 'd/m/Y') use ($toKhmerDigits): string {
            if (blank($value)) {
                return '';
            }

            try {
                return $toKhmerDigits(\Carbon\Carbon::parse($value)->format($format));
            } catch (\Throwable $exception) {
                return $toKhmerDigits((string) $value);
            }
        };

        $dateParts = static function ($value) use ($toKhmerDigits): array {
            if (blank($value)) {
                return ['day' => '', 'month' => '', 'year' => ''];
            }

            try {
                $date = \Carbon\Carbon::parse($value);

                return [
                    'day' => $toKhmerDigits($date->format('d')),
                    'month' => $toKhmerDigits($date->format('m')),
                    'year' => $toKhmerDigits($date->format('Y')),
                ];
            } catch (\Throwable $exception) {
                return ['day' => '', 'month' => '', 'year' => ''];
            }
        };

        $profileData = (array) ($profile ?? []);
        $employeeData = $employee ?? null;

        $photoPath = trim((string) ($photo_path ?? ''));
        if ($photoPath === '') {
            $candidates = [
                trim((string) data_get($profileData, 'profile_img_location', '')),
                trim((string) data_get($profileData, 'profile_image', '')),
                trim((string) data_get($employeeData, 'profile_img_location', '')),
                trim((string) data_get($employeeData, 'profile_image', '')),
            ];

            foreach ($candidates as $candidate) {
                if ($candidate === '') {
                    continue;
                }

                $normalized = str_replace('\\', '/', $candidate);
                $possiblePaths = [
                    $candidate,
                    public_path('storage/' . ltrim($normalized, '/')),
                    public_path(ltrim($normalized, '/')),
                    storage_path('app/public/' . ltrim($normalized, '/')),
                ];

                foreach ($possiblePaths as $possiblePath) {
                    if (is_file($possiblePath)) {
                        $photoPath = $possiblePath;
                        break 2;
                    }
                }
            }
        }
        $photoUri = $fontToFileUri($photoPath);

        $genderText = $clean(data_get($profileData, 'gender', ''));
        $isMale = str_contains($genderText, 'áž”áŸ’ážšáž»ážŸ');
        $isFemale = str_contains($genderText, 'ážŸáŸ’ážšáž¸');
        $genderMark = static function (bool $checked): string {
            return $checked ? '[x]' : '[ ]';
        };

        $birthDateParts = $dateParts(data_get($profileData, 'date_of_birth'));
        $serviceDate = $toKhmerDate(data_get($profileData, 'service_start_date'));

        $form = [
            'full_name' => $clean(data_get($profileData, 'full_name', '')),
            'full_name_latin' => $cleanLatin(data_get($profileData, 'full_name_latin', '')),
            'nationality' => $clean(data_get($profileData, 'nationality', data_get($profileData, 'citizenship', ''))),
            'ethnic_group' => $clean(data_get($profileData, 'ethnic_group', data_get($profileData, 'nationality', ''))),
            'birth_village' => $clean(data_get($profileData, 'birth_place_village', '')),
            'birth_commune' => $clean(data_get($profileData, 'birth_place_commune', '')),
            'birth_district' => $clean(data_get($profileData, 'birth_place_city', '')),
            'birth_province' => $clean(data_get($profileData, 'birth_place_state', '')),
            'current_village' => $clean(data_get($profileData, 'present_address_village', '')),
            'current_commune' => $clean(data_get($profileData, 'present_address_commune', '')),
            'current_district' => $clean(data_get($profileData, 'present_address_city', '')),
            'current_province' => $clean(data_get($profileData, 'present_address_state', '')),
            'current_address_prefix' => $clean(data_get($profileData, 'present_address_prefix', '')),
            'phone' => $clean(
                data_get($employeeData, 'phone')
                    ?: data_get($employeeData, 'cell_phone')
                    ?: data_get($employeeData, 'home_phone')
                    ?: ''
            ),
            'employee_id' => $clean(data_get($profileData, 'employee_id', '')),
            'national_id_no' => $clean(data_get($profileData, 'national_id_no', data_get($profileData, 'national_id', ''))),
            'official_id_10' => $clean(data_get($profileData, 'official_id_10', '')),
            'current_workplace' => $clean(data_get($profileData, 'current_work_place', '')),
            'position' => $clean(data_get($profileData, 'position', data_get($profileData, 'role', ''))),
            'general_education' => $clean(
                data_get($profileData, 'general_education_highest_level', data_get($profileData, 'national_education_level', ''))
            ),
            'technical_skill' => $clean(data_get($profileData, 'technical_skill', '')),
            'issue_place' => $clean(data_get($profileData, 'issue_place', '')),
            'issue_day' => $clean(data_get($profileData, 'issue_day', '')),
            'issue_month' => $clean(data_get($profileData, 'issue_month', '')),
            'issue_year' => $clean(data_get($profileData, 'issue_year', '')),
        ];

        $trainingRows = collect($civil_servant_training_rows ?? [])
            ->map(function ($row) use ($clean, $toKhmerDate) {
                return [
                    'category' => $clean(data_get($row, 'category', '')),
                    'country' => $clean(data_get($row, 'country', '')),
                    'place' => $clean(data_get($row, 'place', '')),
                    'certificate' => $clean(data_get($row, 'certificate', '')),
                    'from' => $clean($toKhmerDate(data_get($row, 'from', ''))),
                    'to' => $clean($toKhmerDate(data_get($row, 'to', ''))),
                ];
            })
            ->filter(function (array $row): bool {
                return collect($row)->contains(function ($value) {
                    return trim((string) $value) !== '';
                });
            })
            ->take(8)
            ->values();

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

        @if (!empty($khmerM1FontUri))
        @font-face {
            font-family: "Khmer M1";
            font-style: normal;
            font-weight: normal;
            src: url("{{ $khmerM1FontUri }}") format("truetype");
        }
        @endif

        @if (!empty($khmerTitleFontUri))
        @font-face {
            font-family: "Khmer OS Muol Light";
            font-style: normal;
            font-weight: normal;
            src: url("{{ $khmerTitleFontUri }}") format("truetype");
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
            margin: 14mm 13mm 12mm 13mm;
        }

        body {
            margin: 0;
            color: #000;
            font-family: "Khmer OS Siemreap", "DejaVu Sans", sans-serif;
            font-size: 12px;
            line-height: 1.55;
        }

        .page {
            width: 100%;
        }

        .top-table,
        .info-table,
        .id-table,
        .training-table,
        .signature-table {
            width: 100%;
            border-collapse: collapse;
        }

        .top-table td {
            vertical-align: top;
        }

        .org-block {
            width: 31%;
            font-family: "Khmer M1", "Khmer OS Siemreap", sans-serif;
            font-size: 12px;
            line-height: 1.5;
            padding-top: 3mm;
        }

        .nation-block {
            width: 39%;
            text-align: center;
            font-family: "Khmer M1", "Khmer OS Siemreap", sans-serif;
        }

        .photo-block {
            width: 30%;
            text-align: right;
        }

        .photo-box {
            width: 34mm;
            height: 45mm;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #000;
            overflow: hidden;
            text-align: center;
            font-size: 11px;
            line-height: 1.5;
        }

        .photo-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .nation-line {
            margin: 0;
        }

        .symbol-line {
            margin: 0;
            font-family: "Tacteing", "Khmer M1", serif;
            font-size: 22px;
            line-height: 1;
        }

        .document-title {
            margin: 8mm 0 5mm;
            text-align: center;
            font-family: "Khmer M1", "Khmer OS Siemreap", sans-serif;
            font-size: 18px;
        }

        .section-title {
            margin: 4mm 0 2mm;
            font-family: "Khmer OS Muol Light", "Khmer M1", sans-serif;
            font-size: 12px;
        }

        .info-table td {
            padding: 2px 4px;
            vertical-align: top;
        }

        .line-cell {
            border-bottom: 1px dotted #000;
            min-height: 18px;
        }

        .label {
            white-space: nowrap;
        }

        .gender-cell {
            white-space: nowrap;
            text-align: right;
        }

        .id-table {
            margin-top: 4mm;
            table-layout: fixed;
        }

        .id-table td {
            width: 33.33%;
            padding: 0 4px;
        }

        .id-box {
            border: 1px solid #000;
            padding: 6px 8px;
            min-height: 36px;
            text-align: center;
        }

        .id-box .id-label {
            display: block;
            margin-bottom: 4px;
            font-size: 11px;
        }

        .id-box .id-value {
            display: block;
            min-height: 16px;
            border-top: 1px dotted #000;
            padding-top: 3px;
        }

        .role-line {
            margin: 4mm 0 0;
        }

        .training-table {
            margin-top: 3mm;
            table-layout: fixed;
        }

        .training-table th,
        .training-table td {
            border: 1px solid #000;
            padding: 5px 6px;
            vertical-align: top;
            font-size: 11px;
        }

        .training-table th {
            text-align: center;
            font-family: "Khmer M1", "Khmer OS Siemreap", sans-serif;
            font-size: 10.5px;
            line-height: 1.4;
        }

        .declaration {
            margin: 5mm 0 3mm;
            text-align: justify;
        }

        .signature-table td {
            width: 50%;
            padding-top: 2mm;
            vertical-align: top;
            text-align: center;
        }

        .signature-line {
            margin-top: 22mm;
        }

        .small {
            font-size: 11px;
        }
    </style>
</head>
<body>
<div class="page">
    <table class="top-table">
        <tr>
            <td class="org-block">
                <div>áž¢áž„áŸ’áž‚áž—áž¶áž–</div>
                <div>{{ $form['current_workplace'] !== '' ? $form['current_workplace'] : '................................' }}</div>
            </td>
            <td class="nation-block">
                <p class="nation-line">áž–áŸ’ážšáŸ‡ážšáž¶áž‡áž¶ážŽáž¶áž…áž€áŸ’ážšáž€áž˜áŸ’áž–áž»áž‡áž¶</p>
                <p class="nation-line">áž‡áž¶ážáž· ážŸáž¶ážŸáž“áž¶ áž–áŸ’ážšáŸ‡áž˜áž áž¶áž€áŸ’ážŸážáŸ’ážš</p>
                <p class="symbol-line">3</p>
                <p class="symbol-line">3</p>
            </td>
            <td class="photo-block">
                <div class="photo-box">
                    @if ($photoUri)
                        <img src="{{ $photoUri }}" alt="Profile photo">
                    @else
                        <div>ážšáž¼áž”ážážážáŸ’áž˜áž¸<br>áŸ¤ x áŸ¦</div>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <div class="document-title">áž‡áž¸ážœáž”áŸ’ážšážœážáŸ’ážáž·áž˜áž“áŸ’ážáŸ’ážšáž¸ážšáž¶áž‡áž€áž¶ážš</div>

    <div class="section-title">áž€-áž–áŸážáŸŒáž˜áž¶áž“áž•áŸ’áž‘áž¶áž›áŸ‹ážáŸ’áž›áž½áž“</div>

    <table class="info-table">
        <tr>
            <td class="label">áŸ¡. áž“áž¶áž˜ážáŸ’ážšáž€áž¼áž› áž“áž·áž„áž“áž¶áž˜</td>
            <td class="line-cell">{{ $form['full_name'] }}</td>
            <td class="label">áž¢áž€áŸ’ážŸážšáž¡áž¶ážáž¶áŸ†áž„</td>
            <td class="line-cell">{{ $form['full_name_latin'] }}</td>
            <td class="gender-cell">áž”áŸ’ážšáž»ážŸ {{ $genderMark($isMale) }}</td>
            <td class="gender-cell">ážŸáŸ’ážšáž¸ {{ $genderMark($isFemale) }}</td>
        </tr>
        <tr>
            <td class="label">áŸ¢. ážáŸ’áž„áŸƒážáŸ‚áž†áŸ’áž“áž¶áŸ†áž€áŸ†ážŽáž¾áž</td>
            <td colspan="2" class="line-cell">áž€áž¾ážážáŸ’áž„áŸƒáž‘áž¸ {{ $birthDateParts['day'] }} ážáŸ‚ {{ $birthDateParts['month'] }} áž†áŸ’áž“áž¶áŸ† {{ $birthDateParts['year'] }}</td>
            <td class="label">ážŸáž‰áŸ’áž‡áž¶ážáž·</td>
            <td colspan="2" class="line-cell">{{ $form['nationality'] }}</td>
        </tr>
        <tr>
            <td class="label">áŸ£. áž‡áž“áž‡áž¶ážáž·</td>
            <td class="line-cell">{{ $form['ethnic_group'] }}</td>
            <td class="label">áž‘áž¸áž€áž“áŸ’áž›áŸ‚áž„áž€áŸ†ážŽáž¾áž</td>
            <td colspan="3" class="line-cell">
                áž“áŸ…áž—áž¼áž˜áž· {{ $form['birth_village'] }}
                ážƒáž»áŸ†/ážŸáž„áŸ’áž€áž¶ážáŸ‹ {{ $form['birth_commune'] }}
                áž€áŸ’ážšáž»áž„/ážŸáŸ’ážšáž»áž€ {{ $form['birth_district'] }}
                ážšáž¶áž‡áž’áž¶áž“áž¸/ážáŸážáŸ’áž {{ $form['birth_province'] }}
            </td>
        </tr>
        <tr>
            <td class="label">áŸ¤. áž¢áž¶ážŸáž™ážŠáŸ’áž‹áž¶áž“áž”áž…áŸ’áž…áž»áž”áŸ’áž”áž“áŸ’áž“</td>
            <td colspan="5" class="line-cell">
                @if($form['current_address_prefix'] !== '')
                {{ $form['current_address_prefix'] }}
                @endif
                áž“áŸ…áž—áž¼áž˜áž· {{ $form['current_village'] }}
                ážƒáž»áŸ†/ážŸáž„áŸ’áž€áž¶ážáŸ‹ {{ $form['current_commune'] }}
                áž€áŸ’ážšáž»áž„/ážŸáŸ’ážšáž»áž€ {{ $form['current_district'] }}
                ážšáž¶áž‡áž’áž¶áž“áž¸/ážáŸážáŸ’áž {{ $form['current_province'] }}
            </td>
        </tr>
        <tr>
            <td class="label">áŸ¥. áž›áŸážáž‘áž¼ážšážŸáŸáž–áŸ’áž‘</td>
            <td class="line-cell">{{ $form['phone'] }}</td>
            <td class="label">ážáŸ’áž„áŸƒáž…áž¼áž›áž”áž˜áŸ’ážšáž¾áž€áž¶ážšáž„áž¶ážš</td>
            <td class="line-cell">{{ $serviceDate }}</td>
            <td class="label">áž˜áž»ážážáŸ†ážŽáŸ‚áž„</td>
            <td class="line-cell">{{ $form['position'] }}</td>
        </tr>
    </table>

    <table class="id-table">
        <tr>
            <td>
                <div class="id-box">
                    <span class="id-label">áž›áŸážážŸáž˜áŸ’áž‚áž¶áž›áŸ‹áž”áž»áž‚áŸ’áž‚áž›áž·áž€</span>
                    <span class="id-value">{{ $form['employee_id'] }}</span>
                </div>
            </td>
            <td>
                <div class="id-box">
                    <span class="id-label">áž¢ážáŸ’ážážŸáž‰áŸ’áž‰áž¶ážŽáž”áŸážŽáŸ’ážŽážŸáž‰áŸ’áž‡áž¶ážáž·ážáŸ’áž˜áŸ‚ážš</span>
                    <span class="id-value">{{ $form['national_id_no'] }}</span>
                </div>
            </td>
            <td>
                <div class="id-box">
                    <span class="id-label">áž¢ážáŸ’ážáž›áŸážáž˜áž“áŸ’ážáŸ’ážšáž¸ážšáž¶áž‡áž€áž¶ážš</span>
                    <span class="id-value">{{ $form['official_id_10'] }}</span>
                </div>
            </td>
        </tr>
    </table>

    <p class="role-line">áž‘áž¸áž€áž“áŸ’áž›áŸ‚áž„áž’áŸ’ážœáž¾áž€áž¶ážšáž”áž…áŸ’áž…áž»áž”áŸ’áž”áž“áŸ’áž“áŸˆ <span class="line-cell small" style="display:inline-block; min-width: 70%;">{{ $form['current_workplace'] }}</span></p>

    <div class="section-title">áž-áž€áž˜áŸ’ážšáž·ážážœáž”áŸ’áž”áž’áž˜áŸŒáž‘áž¼áž‘áŸ… áž€áž¶ážšáž”ážŽáŸ’ážáž»áŸ‡áž”ážŽáŸ’ážáž¶áž›ážœáž·áž‡áŸ’áž‡áž¶áž‡áž¸ážœáŸˆ áž“áž·áž„áž€áž¶ážšáž”ážŽáŸ’ážáž»áŸ‡áž”ážŽáŸ’ážáž¶áž›áž”áž“áŸ’áž</div>

    <table class="training-table">
        <thead>
        <tr>
            <th style="width: 19%;">ážœáž‚áŸ’áž‚ áž¬áž€áž˜áŸ’ážšáž·ážážŸáž·áž€áŸ’ážŸáž¶</th>
            <th style="width: 11%;">áž”áŸ’ážšáž‘áŸážŸ</th>
            <th style="width: 28%;">áž‚áŸ’ážšáž¹áŸ‡ážŸáŸ’ážáž¶áž“ážŸáž·áž€áŸ’ážŸáž¶ áž¬áž€áž“áŸ’áž›áŸ‚áž„áž”ážŽáŸ’ážáž»áŸ‡áž”ážŽáŸ’ážáž¶áž›</th>
            <th style="width: 20%;">ážŸáž‰áŸ’áž‰áž¶áž”ážáŸ’ážš áž¬áž›áž‘áŸ’áž’áž•áž›ážŠáŸ‚áž›áž‘áž‘áž½áž›áž”áž¶áž“</th>
            <th style="width: 11%;">ážáŸ’áž„áŸƒ.ážáŸ‚.áž†áŸ’áž“áž¶áŸ† áž…áž¼áž›</th>
            <th style="width: 11%;">ážáŸ’áž„áŸƒ.ážáŸ‚.áž†áŸ’áž“áž¶áŸ† áž”áž‰áŸ’áž…áž”áŸ‹</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($trainingRows as $row)
            <tr>
                <td>{{ $row['category'] }}</td>
                <td style="text-align: center;">{{ $row['country'] }}</td>
                <td>{{ $row['place'] }}</td>
                <td>{{ $row['certificate'] }}</td>
                <td style="text-align: center;">{{ $row['from'] }}</td>
                <td style="text-align: center;">{{ $row['to'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <p class="declaration">
        ážáŸ’áž‰áž»áŸ†ážŸáž¼áž˜áž’áž¶áž“áž¶ážáž¶ áž–áŸážáŸŒáž˜áž¶áž“ážŠáŸ‚áž›áž”áž¶áž“áž”áŸ†áž–áŸáž‰áž€áŸ’áž“áž»áž„áž‡áž¸ážœáž”áŸ’ážšážœážáŸ’ážáž·áž“áŸáŸ‡ áž–áž·ážáž‡áž¶ážáŸ’ážšáž¹áž˜ážáŸ’ážšáž¼ážœážáž¶áž˜áž€áž¶ážšáž–áž·áž áž áž¾áž™áž”áž¾áž˜áž¶áž“áž€áž¶ážšáž€áŸ’áž›áŸ‚áž„áž”áž“áŸ’áž›áŸ† áž¬ážáž»ážŸáž–áž¸áž€áž¶ážšáž–áž·áž ážáŸ’áž‰áž»áŸ†ážŸáž¼áž˜áž‘áž‘áž½áž›ážáž»ážŸážáŸ’ážšáž¼ážœáž…áŸ†áž–áŸ„áŸ‡áž˜áž»ážáž…áŸ’áž”áž¶áž”áŸ‹áž‡áž¶áž’ážšáž˜áž¶áž“áŸ”
    </p>

    <table class="signature-table">
        <tr>
            <td>
                <div>áž”áž¶áž“ážƒáž¾áž‰ áž“áž·áž„áž”áž‰áŸ’áž‡áž¶áž€áŸ‹ážáž¶</div>
                <div>áž‡áž¸ážœáž”áŸ’ážšážœážáŸ’ážáž·áž“áŸáŸ‡áž–áž·ážáž‡áž¶ážáŸ’ážšáž¹áž˜ážáŸ’ážšáž¼ážœ</div>
                <div class="signature-line">áž”áŸ’ážšáž’áž¶áž“áž¢áž„áŸ’áž‚áž—áž¶áž–</div>
            </td>
            <td>
                <div>{{ $form['issue_place'] !== '' ? $form['issue_place'] : 'áž’áŸ’ážœáž¾áž“áŸ…' }} ážáŸ’áž„áŸƒáž‘áž¸ {{ $form['issue_day'] }} ážáŸ‚ {{ $form['issue_month'] }} áž†áŸ’áž“áž¶áŸ† {{ $form['issue_year'] }}</div>
                <div class="signature-line">ážŸáž¶áž˜áž¸ážáŸ’áž›áž½áž“</div>
            </td>
        </tr>
    </table>
</div>
</body>
</html>
