<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <title>ប្រវត្តិរូបសង្ខេប</title>
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

        $toDate = static function ($value) use ($toKhmerDigits): string {
            if (blank($value)) {
                return '';
            }
            try {
                return $toKhmerDigits(\Carbon\Carbon::parse($value)->format('d-m-Y'));
            } catch (\Throwable $exception) {
                return $toKhmerDigits((string) $value);
            }
        };

        $khmerOnly = static function ($value) use ($toKhmerDigits): string {
            $text = trim((string) $value);
            if ($text === '') {
                return '';
            }

            $text = $toKhmerDigits($text);

            // Keep Khmer letters, Khmer digits, spaces and common punctuation used in official forms.
            $text = preg_replace('/[^\x{1780}-\x{17FF}\x{19E0}-\x{19FF}\s\-\/\(\)\.,៖។]/u', '', $text) ?? '';

            return trim($text);
        };

        $p = $profile ?? [];
        $workHistory = $p['work_history'] ?? [[], [], []];
        while (count($workHistory) < 3) {
            $workHistory[] = [];
        }
        $maleKh = json_decode('"\u1794\u17d2\u179a\u17bb\u179f"') ?: 'male';
        $femaleKh = json_decode('"\u179f\u17d2\u179a\u17b8"') ?: 'female';
        $resolveGenderKh = static function ($value, $genderId = null) use ($maleKh, $femaleKh): string {
            $raw = trim((string) $value);
            $norm = mb_strtolower($raw, 'UTF-8');

            if (
                str_contains($norm, $maleKh)
                || in_array($norm, ['male', 'm', 'man', 'boy'], true)
                || (int) $genderId === 1
            ) {
                return $maleKh;
            }

            if (
                str_contains($norm, $femaleKh)
                || in_array($norm, ['female', 'f', 'woman', 'girl'], true)
                || (int) $genderId === 2
            ) {
                return $femaleKh;
            }

            return $raw;
        };
        $normalizedGender = $resolveGenderKh(data_get($p, 'gender', ''), data_get($p, 'gender_id'));

        $form = [
            'full_name' => $khmerOnly(data_get($p, 'full_name', '')),
            'gender' => $khmerOnly($normalizedGender),
            'ethnicity' => $khmerOnly(data_get($p, 'nationality', '')),
            'nationality' => $khmerOnly(data_get($p, 'citizenship', '')),
            'birth_date' => $toDate(data_get($p, 'date_of_birth')),
            'birth_place' => $khmerOnly(data_get($p, 'birth_place', '')),
            'current_address' => $khmerOnly(data_get($p, 'present_address', '')),
            'national_education' => $khmerOnly(data_get($p, 'national_education_level', '')),
            'foreign_language_education' => $khmerOnly(data_get($p, 'foreign_education_level', '')),
            'technical_skill' => $khmerOnly(data_get($p, 'technical_skill', '')),
            'certificate_no' => $khmerOnly(data_get($p, 'certificate_no', '')),
            'generation' => $khmerOnly(data_get($p, 'batch', '')),
            'institution' => $khmerOnly(data_get($p, 'batch_source', '')),
            'current_workplace' => $khmerOnly(data_get($p, 'current_work_place', '')),
            'national_id' => $khmerOnly(data_get($p, 'national_id', '')),
            'start_work_date' => $toDate(data_get($p, 'service_start_date')),
            'salary_level' => $khmerOnly(data_get($p, 'current_salary_grade', '')),
            'salary_promoted_date' => $toDate(data_get($p, 'last_salary_upgrade_date')),
            'work_history_1_from' => $khmerOnly(data_get($workHistory, '0.from_year', '')),
            'work_history_1_to' => $khmerOnly(data_get($workHistory, '0.to_year', '')),
            'work_history_1_place' => $khmerOnly(data_get($workHistory, '0.work_place', '')),
            'work_history_2_from' => $khmerOnly(data_get($workHistory, '1.from_year', '')),
            'work_history_2_to' => $khmerOnly(data_get($workHistory, '1.to_year', '')),
            'work_history_2_place' => $khmerOnly(data_get($workHistory, '1.work_place', '')),
            'work_history_3_from' => $khmerOnly(data_get($workHistory, '2.from_year', '')),
            'work_history_3_to' => $khmerOnly(data_get($workHistory, '2.to_year', '')),
            'work_history_3_place' => $khmerOnly(data_get($workHistory, '2.work_place', '')),
            'spouse_name' => $khmerOnly(data_get($p, 'spouse_name', '')),
            'spouse_birth_date' => $toDate(data_get($p, 'spouse_dob')),
            'sons_count' => $khmerOnly(data_get($p, 'sons_count', '')),
            'daughters_count' => $khmerOnly(data_get($p, 'daughters_count', '')),
            'father_name' => $khmerOnly(data_get($p, 'father_name', '')),
            'father_birth_date' => $toDate(data_get($p, 'father_dob')),
            'father_job' => $khmerOnly(data_get($p, 'father_job', '')),
            'mother_name' => $khmerOnly(data_get($p, 'mother_name', '')),
            'mother_birth_date' => $toDate(data_get($p, 'mother_dob')),
            'mother_job' => $khmerOnly(data_get($p, 'mother_job', '')),
            'issue_place' => $khmerOnly(data_get($p, 'issue_place', 'ស្ទឹងត្រែង')),
            'issue_day' => $khmerOnly(data_get($p, 'issue_day', '')),
            'issue_month' => $khmerOnly(data_get($p, 'issue_month', '')),
            'issue_year' => $khmerOnly(data_get($p, 'issue_year', '')),
        ];

        $genderText = trim((string) $form['gender']);
        if ($genderText === '') {
            $genderText = trim((string) $normalizedGender);
        }

        $malePronoun = json_decode('"\u1781\u17d2\u1789\u17bb\u17c6\u1794\u17b6\u1791"') ?: '';
        $femalePronoun = json_decode('"\u1793\u17b6\u1784\u1781\u17d2\u1789\u17bb\u17c6"') ?: '';
        $wifeLabel = json_decode('"\u1788\u17d2\u1798\u17c4\u17c7\u1794\u17d2\u179a\u1796\u1793\u17d2\u1792"') ?: '';
        $husbandLabel = json_decode('"\u1788\u17d2\u1798\u17c4\u17c7\u1794\u17d2\u178f\u17b8"') ?: '';

        $applicantPronoun = $malePronoun;
        $spouseLabel = $wifeLabel;
        if ($genderText !== '') {
            $lowerGender = mb_strtolower($genderText, 'UTF-8');
            if (str_contains($lowerGender, $femaleKh) || $lowerGender === 'female' || $lowerGender === 'f') {
                $applicantPronoun = $femalePronoun;
                $spouseLabel = $husbandLabel;
            }
        }

        $declarationLead = $applicantPronoun . ' ';
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
            margin-top: 2cm;
            margin-right: 2cm;
            margin-bottom: 2cm;
            margin-left: 2.8cm;
        }

        body {
            margin: 0;
            color: #000;
            font-size: 12pt;
            line-height: 1.52;
            font-family: "Khmer OS Siemreap", "Noto Sans Khmer", "DejaVu Sans", sans-serif;
        }

        .title-font {
            font-family: "Khmer M1", "Khmer OS Muol Light", "Khmer OS Siemreap", "Noto Sans Khmer", "DejaVu Sans", sans-serif;
        }

        .index-font {
            font-family: "Tacteing", "Khmer M1", "Khmer OS Muol Light", "Khmer OS Siemreap", "Noto Sans Khmer", "DejaVu Sans", serif;
        }

        .page {
            width: 100%;
        }

        .top-wrap {
            position: relative;
            min-height: 5.0cm;
        }

        .top {
            width: 100%;
            border-collapse: collapse;
        }

        .top td {
            vertical-align: top;
        }

        .top-left {
            width: 100%;
            text-align: center;
        }

        .top-right {
            position: absolute;
            right: 0;
            top: 0;
            width: 3.8cm;
            text-align: right;
        }

        .photo-box {
            width: 3.8cm;
            height: 5.2cm;
            border: 1px solid #000;
            display: inline-block;
        }

        .header-line {
            margin: 0;
            font-size: 12pt;
            line-height: 1.35;
            font-family: "Khmer M1", "Khmer OS Muol Light", "Khmer OS Siemreap", "Noto Sans Khmer", "DejaVu Sans", sans-serif;
        }

        .header-index {
            margin: 2px 0 4px;
            font-size: 28pt;
            line-height: 1;
            font-weight: normal;
        }

        .title {
            margin: 5.0em 0 0;
            text-align: center;
            font-size: 12pt;
            font-weight: normal;
            font-family: "Khmer M1", "Khmer OS Muol Light", "Khmer OS Siemreap", "Noto Sans Khmer", "DejaVu Sans", sans-serif;
        }

        .form-wrap {
            margin-top: 2.2em;
            letter-spacing: 0.14px;
        }

        .form-line,
        .history-line {
            margin: 3px 0 5px;
            white-space: normal;
            line-height: 1.52;
        }

        .history-line {
            margin-left: 12px;
        }

        .dot-fill {
            display: inline;
            border-bottom: none;
            height: auto;
            line-height: inherit;
            vertical-align: baseline;
            margin: 0 6px 0 2px;
            overflow: hidden;
            white-space: nowrap;
        }

        .dot-fill > span {
            display: inline;
            padding: 0;
            max-width: 100%;
            overflow: hidden;
            white-space: nowrap;
            font-weight: 700;
            font-size: 12pt;
        }

        .tab-row {
            display: block;
            white-space: normal;
        }

        .form-line.tab-row {
            padding-left: 150px;
            text-indent: -150px;
        }

        .tab-cell {
            display: inline;
            vertical-align: baseline;
            margin-right: 10px;
        }

        .tab-label {
            display: inline-block;
            min-width: 78px;
        }

        .tab-label-md { min-width: 98px; }
        .tab-label-lg { min-width: 138px; }

        .tab-value {
            display: inline;
            min-width: 0;
            font-weight: 700;
            font-size: 12pt;
        }

        .tab-value-xs { min-width: 50px; }
        .tab-value-sm { min-width: 76px; }
        .tab-value-md { min-width: 118px; }
        .tab-value-lg { min-width: 176px; }
        .tab-value-xl { min-width: 280px; }

        .tab-main-label {
            display: inline-block;
            min-width: 130px;
        }

        .tab-colon {
            display: inline-block;
            width: 10px;
            text-align: center;
        }

        .dot-fill > span:empty {
            font-size: 0;
            padding: 0;
        }

        .w-40 { width: auto; }
        .w-55 { width: auto; }
        .w-65 { width: auto; }
        .w-80 { width: auto; }
        .w-95 { width: auto; }
        .w-110 { width: auto; }
        .w-130 { width: auto; }
        .w-160 { width: auto; }
        .w-220 { width: auto; }
        .w-280 { width: auto; }
        .w-420 { width: auto; }
        .w-full {
            width: auto;
            display: inline;
            margin-left: 0;
        }

        .declaration {
            margin-top: 10px;
            text-align: left;
            line-height: 1.52;
            white-space: normal;
            text-indent: 2em;
        }

        .signature-table,
        .date-line-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .signature-table {
            margin-top: 12px;
        }

        .signature-table td {
            width: 50%;
            vertical-align: top;
            font-size: 12pt;
        }

        .right-block {
            text-align: right;
            padding-left: 8px;
        }

        .left-block {
            text-align: left;
            padding-right: 8px;
        }

        .sign-gap {
            height: 42px;
        }

        .date-line-table {
            margin-top: 3px;
        }

        .date-line-table td {
            padding: 0 2px;
            vertical-align: middle;
        }

        .date-label {
            white-space: nowrap;
        }

        .date-fill {
            border-bottom: none;
            height: auto;
        }

        .sign-title {
            margin: 0;
        }
    </style>
</head>
<body>
<div class="page">
    <div class="top-wrap">
        <table class="top">
            <tr>
                <td class="top-left">
                    <p class="header-line title-font">ព្រះរាជាណាចក្រកម្ពុជា</p>
                    <p class="header-line title-font">ជាតិ សាសនា ព្រះមហាក្សត្រ</p>
                    <p class="header-index index-font">6</p>
                    <p class="title title-font">ប្រវត្តិរូបសង្ខេប</p>
                </td>
            </tr>
        </table>
        <div class="top-right">
            <span class="photo-box"></span>
        </div>
    </div>

    <div class="form-wrap">
        <p class="form-line tab-row"><span class="tab-main-label">១-គោត្តនាម និងនាម</span><span class="tab-colon">៖</span>
            <span class="tab-cell"><span class="tab-value">{{ $form['full_name'] }}</span></span>
            <span class="tab-cell"><span class="tab-label">ភេទ</span><span class="tab-value">{{ $form['gender'] }}</span></span>
            <span class="tab-cell"><span class="tab-label">ជនជាតិ</span><span class="tab-value">{{ $form['ethnicity'] }}</span></span>
            <span class="tab-cell"><span class="tab-label">សញ្ជាតិ</span><span class="tab-value">{{ $form['nationality'] }}</span></span>
        </p>

        <p class="form-line tab-row"><span class="tab-main-label">២-ថ្ងៃ-ខែ-ឆ្នាំកំណើត</span><span class="tab-colon">៖</span>
            <span class="tab-cell"><span class="tab-value tab-value-md">{{ $form['birth_date'] }}</span></span>
            <span class="tab-cell"><span class="tab-label tab-label-lg">ទីកន្លែងកំណើត</span><span class="tab-value tab-value-xl">{{ $form['birth_place'] }}</span></span>
        </p>

        <p class="form-line tab-row"><span class="tab-main-label">៣-អាសយដ្ឋានបច្ចុប្បន្ន</span><span class="tab-colon">៖</span>
            <span class="tab-cell"><span class="tab-value tab-value-xl">{{ $form['current_address'] }}</span></span>
        </p>

        <p class="form-line tab-row"><span class="tab-main-label">៤-កម្រិតវប្បធម៌ជាតិ</span><span class="tab-colon">៖</span>
            <span class="tab-cell"><span class="tab-value tab-value-md">{{ $form['national_education'] }}</span></span>
            <span class="tab-cell"><span class="tab-label tab-label-lg">កម្រិតវប្បធម៌បរទេស</span><span class="tab-value tab-value-md">{{ $form['foreign_language_education'] }}</span></span>
        </p>

        <p class="form-line tab-row"><span class="tab-main-label">៥-បច្ចេកទេសជំនាញ</span><span class="tab-colon">៖</span>
            <span class="tab-cell"><span class="tab-value tab-value-sm">{{ $form['technical_skill'] }}</span></span>
            <span class="tab-cell"><span class="tab-label">អត្ថលេខ</span><span class="tab-value tab-value-sm">{{ $form['certificate_no'] }}</span></span>
            <span class="tab-cell"><span class="tab-label">ជំនាន់</span><span class="tab-value tab-value-xs">{{ $form['generation'] }}</span></span>
            <span class="tab-cell"><span class="tab-label">នៃ</span><span class="tab-value tab-value-sm">{{ $form['institution'] }}</span></span>
        </p>

        <p class="form-line tab-row"><span class="tab-main-label">៦-ទីកន្លែងធ្វើការបច្ចុប្បន្ន</span><span class="tab-colon">៖</span>
            <span class="tab-cell"><span class="tab-value tab-value-xl">{{ $form['current_workplace'] }}</span></span>
        </p>

        <p class="form-line tab-row"><span class="tab-main-label">៧-លេខអត្តសញ្ញាណប័ណ្ណ</span><span class="tab-colon">៖</span>
            <span class="tab-cell"><span class="tab-value tab-value-xl">{{ $form['national_id'] }}</span></span>
        </p>

        <p class="form-line tab-row"><span class="tab-main-label">៨-ថ្ងៃ-ខែ-ឆ្នាំចូលបម្រើការងារ</span><span class="tab-colon">៖</span>
            <span class="tab-cell"><span class="tab-value tab-value-sm">{{ $form['start_work_date'] }}</span></span>
            <span class="tab-cell"><span class="tab-label tab-label-lg">កម្រិតកាំប្រាក់បច្ចុប្បន្ន</span><span class="tab-value tab-value-xs">{{ $form['salary_level'] }}</span></span>
            <span class="tab-cell"><span class="tab-label tab-label-lg">ថ្ងៃ-ខែ-ឆ្នាំឡើងកាំប្រាក់ចុងក្រោយ</span><span class="tab-value tab-value-sm">{{ $form['salary_promoted_date'] }}</span></span>
        </p>

        <p class="form-line tab-row"><span class="tab-main-label">៩-ប្រវត្តិរូបការងារ</span><span class="tab-colon">៖</span>
            <span class="tab-cell"><span class="tab-label">ពីឆ្នាំ</span><span class="tab-value tab-value-xs">{{ $toKhmerDigits($form['work_history_1_from']) }}</span></span>
            <span class="tab-cell"><span class="tab-label">ដល់ឆ្នាំ</span><span class="tab-value tab-value-xs">{{ $toKhmerDigits($form['work_history_1_to']) }}</span></span>
            <span class="tab-cell"><span class="tab-label">ធ្វើការនៅ</span><span class="tab-value tab-value-lg">{{ $form['work_history_1_place'] }}</span></span>
        </p>
        <p class="history-line tab-row">
            <span class="tab-cell"><span class="tab-label">ពីឆ្នាំ</span><span class="tab-value tab-value-xs">{{ $toKhmerDigits($form['work_history_2_from']) }}</span></span>
            <span class="tab-cell"><span class="tab-label">ដល់ឆ្នាំ</span><span class="tab-value tab-value-xs">{{ $toKhmerDigits($form['work_history_2_to']) }}</span></span>
            <span class="tab-cell"><span class="tab-label">ធ្វើការនៅ</span><span class="tab-value tab-value-xl">{{ $form['work_history_2_place'] }}</span></span>
        </p>
        <p class="history-line tab-row">
            <span class="tab-cell"><span class="tab-label">ពីឆ្នាំ</span><span class="tab-value tab-value-xs">{{ $toKhmerDigits($form['work_history_3_from']) }}</span></span>
            <span class="tab-cell"><span class="tab-label">ដល់ឆ្នាំ</span><span class="tab-value tab-value-xs">{{ $toKhmerDigits($form['work_history_3_to']) }}</span></span>
            <span class="tab-cell"><span class="tab-label">ធ្វើការនៅ</span><span class="tab-value tab-value-xl">{{ $form['work_history_3_place'] }}</span></span>
        </p>

        <p class="form-line tab-row"><span class="tab-main-label">១០-{{ $spouseLabel }}</span><span class="tab-colon">៖</span>
            <span class="tab-cell"><span class="tab-value tab-value-sm">{{ $form['spouse_name'] }}</span></span>
            <span class="tab-cell"><span class="tab-label tab-label-lg">ថ្ងៃ-ខែ-ឆ្នាំកំណើត</span><span class="tab-value tab-value-sm">{{ $form['spouse_birth_date'] }}</span></span>
            <span class="tab-cell"><span class="tab-label tab-label-md">ចំនួនកូនប្រុស</span><span class="tab-value tab-value-xs">{{ $toKhmerDigits($form['sons_count']) }}</span></span>
            <span class="tab-cell"><span class="tab-label">ស្រី</span><span class="tab-value tab-value-xs">{{ $toKhmerDigits($form['daughters_count']) }}</span></span>
        </p>

        <p class="form-line tab-row"><span class="tab-main-label">១១-ឪពុកបង្កើតឈ្មោះ</span><span class="tab-colon">៖</span>
            <span class="tab-cell"><span class="tab-value tab-value-sm">{{ $form['father_name'] }}</span></span>
            <span class="tab-cell"><span class="tab-label tab-label-lg">ថ្ងៃ-ខែ-ឆ្នាំកំណើត</span><span class="tab-value tab-value-sm">{{ $form['father_birth_date'] }}</span></span>
            <span class="tab-cell"><span class="tab-label">មុខរបរ</span><span class="tab-value tab-value-sm">{{ $form['father_job'] }}</span></span>
        </p>

        <p class="form-line tab-row"><span class="tab-main-label">១២-ម្តាយបង្កើតឈ្មោះ</span><span class="tab-colon">៖</span>
            <span class="tab-cell"><span class="tab-value tab-value-sm">{{ $form['mother_name'] }}</span></span>
            <span class="tab-cell"><span class="tab-label tab-label-lg">ថ្ងៃ-ខែ-ឆ្នាំកំណើត</span><span class="tab-value tab-value-sm">{{ $form['mother_birth_date'] }}</span></span>
            <span class="tab-cell"><span class="tab-label">មុខរបរ</span><span class="tab-value tab-value-sm">{{ $form['mother_job'] }}</span></span>
        </p>
    </div>

    <p class="declaration">{{ $declarationLead }}សូមធានាអះអាងថា ការសរសេររៀបរាប់ប្រវត្តិរូបនេះ ពិតជាត្រឹមត្រូវ គ្មានក្លែងបន្លំឡើយ បើមានចំណុចណាមួយខុសពីការពិតនោះ {{ $applicantPronoun }} សូមទទួលខុសត្រូវចំពោះមុខច្បាប់ជាធរមាន ។</p>

    <table class="signature-table">
        <tr>
            <td class="left-block">
                <p class="sign-title">បានឃើញ និងបញ្ជាក់ថា</p>
                <p class="sign-title">ប្រវត្តិរូបនេះពិតជាត្រឹមត្រូវ</p>
                <table class="date-line-table">
                    <tr>
                        <td class="date-label">{{ $form['issue_place'] }} ថ្ងៃទី</td>
                        <td class="date-fill"><span>{{ $form['issue_day'] }}</span></td>
                        <td class="date-label">ខែ</td>
                        <td class="date-fill"><span>{{ $form['issue_month'] }}</span></td>
                        <td class="date-label">ឆ្នាំ</td>
                        <td class="date-fill"><span>{{ $form['issue_year'] }}</span></td>
                    </tr>
                </table>
                <div class="sign-gap"></div>
                <p class="title-font">ប្រធានមន្ទីរសុខាភិបាលនៃរដ្ឋបាលខេត្ត</p>
            </td>
            <td class="right-block">
                <table class="date-line-table">
                    <tr>
                        <td class="date-label">{{ $form['issue_place'] }} ថ្ងៃទី</td>
                        <td class="date-fill"><span>{{ $form['issue_day'] }}</span></td>
                        <td class="date-label">ខែ</td>
                        <td class="date-fill"><span>{{ $form['issue_month'] }}</span></td>
                        <td class="date-label">ឆ្នាំ</td>
                        <td class="date-fill"><span>{{ $form['issue_year'] }}</span></td>
                    </tr>
                </table>
                <div class="sign-gap"></div>
                <p>ហត្ថលេខា</p>
            </td>
        </tr>
    </table>
</div>
</body>
</html>
