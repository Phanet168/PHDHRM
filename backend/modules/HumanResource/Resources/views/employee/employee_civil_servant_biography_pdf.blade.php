<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <title>ជីវប្រវត្តិមន្ត្រីរាជការ</title>
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
        $isMale = str_contains($genderText, 'ប្រុស');
        $isFemale = str_contains($genderText, 'ស្រី');
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

        $trainingRows = collect();

        if (
            $form['general_education'] !== ''
            || $form['current_workplace'] !== ''
        ) {
            $trainingRows->push([
                'category' => 'កម្រិតវប្បធម៌ទូទៅ',
                'country' => 'កម្ពុជា',
                'place' => $clean(data_get($profileData, 'batch_source', data_get($profileData, 'current_work_place', ''))),
                'certificate' => $form['general_education'],
                'from' => '',
                'to' => '',
            ]);
        }

        foreach (($education_histories ?? collect()) as $row) {
            $degree = $clean(data_get($row, 'degree_level', ''));
            $major = $clean(data_get($row, 'major_subject', ''));
            $category = trim(implode(' - ', array_filter([$degree, $major])));
            $trainingRows->push([
                'category' => $category !== '' ? $category : 'ការបណ្តុះបណ្តាលជំនាញ',
                'country' => 'កម្ពុជា',
                'place' => $clean(data_get($row, 'institution_name', '')),
                'certificate' => $clean(data_get($row, 'note', '')),
                'from' => $toKhmerDate(data_get($row, 'start_date')),
                'to' => $toKhmerDate(data_get($row, 'end_date')),
            ]);
        }

        foreach (($academic_infos ?? collect()) as $row) {
            $trainingRows->push([
                'category' => $clean(data_get($row, 'exam_title', '')),
                'country' => 'កម្ពុជា',
                'place' => $clean(data_get($row, 'institute_name', '')),
                'certificate' => $clean(data_get($row, 'result', data_get($row, 'exam_title', ''))),
                'from' => '',
                'to' => $clean(data_get($row, 'graduation_year', '')),
            ]);
        }

        foreach (($foreign_languages ?? collect()) as $row) {
            $languageName = $clean(data_get($row, 'language_name', ''));
            $levels = array_filter([
                $clean(data_get($row, 'speaking_level', '')),
                $clean(data_get($row, 'reading_level', '')),
                $clean(data_get($row, 'writing_level', '')),
            ]);

            $trainingRows->push([
                'category' => trim('ចំណេះដឹងភាសាបរទេស' . ($languageName !== '' ? ' - ' . $languageName : '')),
                'country' => 'កម្ពុជា',
                'place' => $clean(data_get($row, 'institution_name', '')),
                'certificate' => $clean(data_get($row, 'result', implode(' / ', $levels))),
                'from' => $toKhmerDate(data_get($row, 'start_date')),
                'to' => $toKhmerDate(data_get($row, 'end_date')),
            ]);
        }

        $trainingRows = $trainingRows
            ->filter(function (array $row): bool {
                return collect($row)->filter(function ($value) {
                    return trim((string) $value) !== '';
                })->isNotEmpty();
            })
            ->take(8)
            ->values();

        while ($trainingRows->count() < 8) {
            $trainingRows->push([
                'category' => '',
                'country' => '',
                'place' => '',
                'certificate' => '',
                'from' => '',
                'to' => '',
            ]);
        }
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
                <div>អង្គភាព</div>
                <div>{{ $form['current_workplace'] !== '' ? $form['current_workplace'] : '................................' }}</div>
            </td>
            <td class="nation-block">
                <p class="nation-line">ព្រះរាជាណាចក្រកម្ពុជា</p>
                <p class="nation-line">ជាតិ សាសនា ព្រះមហាក្សត្រ</p>
                <p class="symbol-line">3</p>
                <p class="symbol-line">3</p>
            </td>
            <td class="photo-block">
                <div class="photo-box">
                    @if ($photoUri)
                        <img src="{{ $photoUri }}" alt="Profile photo">
                    @else
                        <div>រូបថតថ្មី<br>៤ x ៦</div>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <div class="document-title">ជីវប្រវត្តិមន្ត្រីរាជការ</div>

    <div class="section-title">ក-ព័ត៌មានផ្ទាល់ខ្លួន</div>

    <table class="info-table">
        <tr>
            <td class="label">១. នាមត្រកូល និងនាម</td>
            <td class="line-cell">{{ $form['full_name'] }}</td>
            <td class="label">អក្សរឡាតាំង</td>
            <td class="line-cell">{{ $form['full_name_latin'] }}</td>
            <td class="gender-cell">ប្រុស {{ $genderMark($isMale) }}</td>
            <td class="gender-cell">ស្រី {{ $genderMark($isFemale) }}</td>
        </tr>
        <tr>
            <td class="label">២. ថ្ងៃខែឆ្នាំកំណើត</td>
            <td colspan="2" class="line-cell">កើតថ្ងៃទី {{ $birthDateParts['day'] }} ខែ {{ $birthDateParts['month'] }} ឆ្នាំ {{ $birthDateParts['year'] }}</td>
            <td class="label">សញ្ជាតិ</td>
            <td colspan="2" class="line-cell">{{ $form['nationality'] }}</td>
        </tr>
        <tr>
            <td class="label">៣. ជនជាតិ</td>
            <td class="line-cell">{{ $form['ethnic_group'] }}</td>
            <td class="label">ទីកន្លែងកំណើត</td>
            <td colspan="3" class="line-cell">
                នៅភូមិ {{ $form['birth_village'] }}
                ឃុំ/សង្កាត់ {{ $form['birth_commune'] }}
                ក្រុង/ស្រុក {{ $form['birth_district'] }}
                រាជធានី/ខេត្ត {{ $form['birth_province'] }}
            </td>
        </tr>
        <tr>
            <td class="label">៤. អាសយដ្ឋានបច្ចុប្បន្ន</td>
            <td colspan="5" class="line-cell">
                @if($form['current_address_prefix'] !== '')
                {{ $form['current_address_prefix'] }}
                @endif
                នៅភូមិ {{ $form['current_village'] }}
                ឃុំ/សង្កាត់ {{ $form['current_commune'] }}
                ក្រុង/ស្រុក {{ $form['current_district'] }}
                រាជធានី/ខេត្ត {{ $form['current_province'] }}
            </td>
        </tr>
        <tr>
            <td class="label">៥. លេខទូរស័ព្ទ</td>
            <td class="line-cell">{{ $form['phone'] }}</td>
            <td class="label">ថ្ងៃចូលបម្រើការងារ</td>
            <td class="line-cell">{{ $serviceDate }}</td>
            <td class="label">មុខតំណែង</td>
            <td class="line-cell">{{ $form['position'] }}</td>
        </tr>
    </table>

    <table class="id-table">
        <tr>
            <td>
                <div class="id-box">
                    <span class="id-label">លេខសម្គាល់បុគ្គលិក</span>
                    <span class="id-value">{{ $form['employee_id'] }}</span>
                </div>
            </td>
            <td>
                <div class="id-box">
                    <span class="id-label">អត្តសញ្ញាណប័ណ្ណសញ្ជាតិខ្មែរ</span>
                    <span class="id-value">{{ $form['national_id_no'] }}</span>
                </div>
            </td>
            <td>
                <div class="id-box">
                    <span class="id-label">អត្តលេខមន្ត្រីរាជការ</span>
                    <span class="id-value">{{ $form['official_id_10'] }}</span>
                </div>
            </td>
        </tr>
    </table>

    <p class="role-line">ទីកន្លែងធ្វើការបច្ចុប្បន្នៈ <span class="line-cell small" style="display:inline-block; min-width: 70%;">{{ $form['current_workplace'] }}</span></p>

    <div class="section-title">ខ-កម្រិតវប្បធម៌ទូទៅ ការបណ្តុះបណ្តាលវិជ្ជាជីវៈ និងការបណ្តុះបណ្តាលបន្ត</div>

    <table class="training-table">
        <thead>
        <tr>
            <th style="width: 19%;">វគ្គ ឬកម្រិតសិក្សា</th>
            <th style="width: 11%;">ប្រទេស</th>
            <th style="width: 28%;">គ្រឹះស្ថានសិក្សា ឬកន្លែងបណ្តុះបណ្តាល</th>
            <th style="width: 20%;">សញ្ញាបត្រ ឬលទ្ធផលដែលទទួលបាន</th>
            <th style="width: 11%;">ថ្ងៃ.ខែ.ឆ្នាំ ចូល</th>
            <th style="width: 11%;">ថ្ងៃ.ខែ.ឆ្នាំ បញ្ចប់</th>
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
        ខ្ញុំសូមធានាថា ព័ត៌មានដែលបានបំពេញក្នុងជីវប្រវត្តិនេះ ពិតជាត្រឹមត្រូវតាមការពិត ហើយបើមានការក្លែងបន្លំ ឬខុសពីការពិត ខ្ញុំសូមទទួលខុសត្រូវចំពោះមុខច្បាប់ជាធរមាន។
    </p>

    <table class="signature-table">
        <tr>
            <td>
                <div>បានឃើញ និងបញ្ជាក់ថា</div>
                <div>ជីវប្រវត្តិនេះពិតជាត្រឹមត្រូវ</div>
                <div class="signature-line">ប្រធានអង្គភាព</div>
            </td>
            <td>
                <div>{{ $form['issue_place'] !== '' ? $form['issue_place'] : 'ធ្វើនៅ' }} ថ្ងៃទី {{ $form['issue_day'] }} ខែ {{ $form['issue_month'] }} ឆ្នាំ {{ $form['issue_year'] }}</div>
                <div class="signature-line">សាមីខ្លួន</div>
            </td>
        </tr>
    </table>
</div>
</body>
</html>
