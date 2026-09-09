@php
    $emp = $employee ?? null;

    $modelDateValue = static function ($model, string $key): string {
        if (!is_object($model)) {
            return '';
        }

        if (method_exists($model, 'getRawOriginal')) {
            $raw = trim((string) $model->getRawOriginal($key));
            if ($raw !== '') {
                return $raw;
            }
        }

        $value = $model->{$key} ?? null;
        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->format('Y-m-d');
        }

        return trim((string) $value);
    };

    $educationRows = old('education_histories');
    if (!is_array($educationRows)) {
        $educationRows = $emp
            ? $emp->educationHistories->map(function ($r) use ($modelDateValue) {
                return [
                    'country_name' => $r->country_name,
                    'institution_name' => $r->institution_name,
                    'start_date' => $modelDateValue($r, 'start_date'),
                    'end_date' => $modelDateValue($r, 'end_date'),
                    'degree_level' => $r->degree_level,
                    'major_subject' => $r->major_subject,
                    'note' => $r->note,
                ];
            })->toArray()
            : [];
    }
    if (empty($educationRows)) {
        $educationRows = [[]];
    }

    $languageRows = old('foreign_languages');
    if (!is_array($languageRows)) {
        $languageRows = $emp
            ? $emp->foreignLanguages->map(function ($r) use ($modelDateValue) {
                return [
                    'country_name' => $r->country_name,
                    'language_name' => $r->language_name,
                    'speaking_level' => $r->speaking_level,
                    'reading_level' => $r->reading_level,
                    'writing_level' => $r->writing_level,
                    'institution_name' => $r->institution_name,
                    'start_date' => $modelDateValue($r, 'start_date'),
                    'end_date' => $modelDateValue($r, 'end_date'),
                    'result' => $r->result,
                ];
            })->toArray()
            : [];
    }
    if (empty($languageRows)) {
        $languageRows = [[]];
    }

    $academicRows = old('academic_infos');
    if (!is_array($academicRows)) {
        $academicRows = collect($academicInfos ?? ($emp?->academicInfos ?? []))
            ->map(function ($r) use ($modelDateValue) {
                return [
                    'exam_title' => $r->exam_title,
                    'certificate_type' => $r->certificate_type,
                    'certificate_type_other' => $r->certificate_type_other,
                    'country_name' => $r->country_name,
                    'institute_name' => $r->institute_name,
                    'result' => $r->result,
                    'start_date' => $modelDateValue($r, 'start_date'),
                    'end_date' => $modelDateValue($r, 'end_date'),
                    'graduation_year' => $r->graduation_year,
                ];
            })->toArray();
    }
    if (empty($academicRows)) {
        $academicRows = [[]];
    }

    $isKhmerUi = app()->getLocale() === 'km';
    $trainingInformationTitle = $isKhmerUi ? '- កំរិតវប្បធម៌ទូទៅ (បំពេញយកចុងក្រោយ)' : 'National general education level (latest completed)';
    $educationHistoryTitle = $isKhmerUi ? '- កំរិតបណ្តុះបណ្តាលមុខវិជ្ជាជីវៈមូលដ្ឋាន និងក្រោយមូលដ្ឋាន' : 'Basic and post-basic professional training';
    $foreignLanguageTitle = $isKhmerUi ? '- ចំណេះដឹងភាសាបរទេស' : 'Foreign language knowledge';
    $continuingTrainingTitle = $isKhmerUi ? '- ការបណ្តុះបណ្តាល និង វគ្គសិក្សាកំពុងបន្ត (ត្រូវបំពេញវគ្គសិក្សាដែលថ្មីៗ សំខាន់ និង ចាំបាច់)' : 'Continuing training and current courses (recent, important, and necessary only)';
    $nationalEducationLabel = $isKhmerUi ? 'កម្រិតវប្បធម៌ជាតិ' : 'National general education level';
    $highestEducationLabel = $isKhmerUi ? 'កម្រិតវប្បធម៌ខ្ពស់បំផុត' : 'Highest educational qualification';
    $degreeLevelLabel = $isKhmerUi ? 'កម្រិតសញ្ញាបត្រ' : 'Degree level';
    $majorSubjectLabel = $isKhmerUi ? 'មុខវិជ្ជា/ជំនាញសិក្សា' : 'Major / field of study';
    $countryLabel = $isKhmerUi ? 'ប្រទេស' : 'Country';
    $courseTitleLabel = $isKhmerUi ? 'ឈ្មោះវគ្គ/ប្រភេទការបណ្តុះបណ្តាល' : 'Course / training title';
    $certificateTypeLabel = $isKhmerUi ? 'ប្រភេទសញ្ញាបត្រ' : 'Certificate type';
    $certificateTypeOtherLabel = $isKhmerUi ? 'បញ្ជាក់ប្រភេទផ្សេងៗ' : 'Specify other type';
    $certificateResultLabel = $isKhmerUi ? 'សញ្ញាបត្រ/វិញ្ញាបនបត្រ/លទ្ធផលទទួលបាន' : 'Certificate / result obtained';
    $speakingLabel = $isKhmerUi ? 'ការសន្ទនា' : 'Speaking';
    $readingLabel = $isKhmerUi ? 'ការអាន' : 'Reading';
    $writingLabel = $isKhmerUi ? 'ការសរសេរ' : 'Writing';
    $languageLevelOptions = [
        'basic' => $isKhmerUi ? 'កម្រិតមូលដ្ឋាន' : 'Basic level',
        'medium' => $isKhmerUi ? 'កម្រិតមធ្យម' : 'Intermediate level',
        'good' => $isKhmerUi ? 'កម្រិតល្អ' : 'Good level',
        'expert' => $isKhmerUi ? 'កម្រិតស្ទាត់ជំនាញ' : 'Proficient level',
    ];
    $startYearLabel = $isKhmerUi ? 'ឆ្នាំចាប់ផ្តើម' : 'Start year';
    $endYearLabel = $isKhmerUi ? 'ឆ្នាំបញ្ចប់' : 'End year';
    $languageStartDateLabel = $isKhmerUi ? 'ថ្ងៃខែឆ្នាំចាប់ផ្តើម / ឆ្នាំ' : 'Start date / year';
    $languageEndDateLabel = $isKhmerUi ? 'ថ្ងៃខែឆ្នាំបញ្ចប់ / ឆ្នាំ' : 'End date / year';
    $yearPlaceholder = $isKhmerUi ? 'ឧ. ២០២០' : 'e.g. 2020';
    $yearHint = $isKhmerUi ? 'បញ្ចូលតែឆ្នាំបានគ្រប់គ្រាន់ មិនចាំបាច់បញ្ចូលថ្ងៃ និង ខែទេ។' : 'Year only is enough. Day and month are not required.';
    $languageDateHint = $isKhmerUi ? 'សម្រាប់ភាសាបរទេស អាចបញ្ចូលថ្ងៃ ខែ ឆ្នាំពេញលេញ ឬបញ្ចូលតែឆ្នាំក៏បាន។ ឧ. 14/05/2020 ឬ 2020' : 'For foreign languages, enter a full date or just a year. Example: 14/05/2020 or 2020.';
    $continuingTrainingDateHint = $isKhmerUi ? 'សម្រាប់ការបណ្តុះបណ្តាលបន្តក្នុងពេលកំពុងបំពេញការងារ អាចបញ្ចូលថ្ងៃ ខែ ឆ្នាំពេញលេញ ឬបញ្ចូលតែឆ្នាំក៏បាន។ ឧ. 14/05/2020 ឬ 2020' : 'For in-service continuing training, enter a full date or just a year. Example: 14/05/2020 or 2020.';
    $yearValue = static function ($value): string {
        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->format('Y');
        }

        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        if (preg_match('/^(\d{4})/', $text, $matches)) {
            return $matches[1];
        }

        return $text;
    };
    $dateValue = static function ($value): string {
        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->format('Y-m-d');
        }

        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        try {
            return \Illuminate\Support\Carbon::parse($text)->format('Y-m-d');
        } catch (\Throwable $e) {
            return '';
        }
    };
    $nationalEducationOptions = [
        '1' => $isKhmerUi ? 'បឋម' : 'Primary',
        '2' => $isKhmerUi ? 'បឋមភូមិ' : 'Lower secondary',
        '3' => $isKhmerUi ? 'ទុតិយភូមិ' : 'Upper secondary',
        '4' => $isKhmerUi ? 'ផ្សេងៗ' : 'Other',
    ];
    $normalizeNationalEducationValue = static function ($value): string {
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
    $selectedNationalEducation = $normalizeNationalEducationValue(old('national_education_level', $emp?->national_education_level));
    if ($selectedNationalEducation === '') {
        $selectedNationalEducation = $normalizeNationalEducationValue($emp?->highest_educational_qualification);
    }
    $degreeLevelOptions = [
        'no_formal_qualification' => $isKhmerUi ? 'មិនទាន់មានសញ្ញាបត្រ' : 'No formal qualification',
        'literacy_certificate' => $isKhmerUi ? 'វិញ្ញាបនបត្រអក្ខរកម្ម' : 'Literacy certificate',
        'primary_certificate' => $isKhmerUi ? 'វិញ្ញាបនបត្របឋមសិក្សា' : 'Primary certificate',
        'lower_secondary_diploma' => $isKhmerUi ? 'សញ្ញាបត្របឋមភូមិ' : 'Lower secondary diploma',
        'upper_secondary_diploma' => $isKhmerUi ? 'សញ្ញាបត្រទុតិយភូមិ' : 'Upper secondary diploma',
        'vocational_certificate' => $isKhmerUi ? 'វិញ្ញាបនបត្រវិជ្ជាជីវៈ' : 'Vocational certificate',
        'technical_diploma' => $isKhmerUi ? 'សញ្ញាបត្របច្ចេកទេស' : 'Technical diploma',
        'associate_degree' => $isKhmerUi ? 'បរិញ្ញាបត្ររង' : 'Associate degree',
        'higher_diploma' => $isKhmerUi ? 'ឌីប្លូមជាន់ខ្ពស់' : 'Higher diploma',
        'bachelor_degree' => $isKhmerUi ? 'បរិញ្ញាបត្រ' : 'Bachelor degree',
        'professional_bachelor_degree' => $isKhmerUi ? 'បរិញ្ញាបត្រវិជ្ជាជីវៈ' : 'Professional bachelor degree',
        'postgraduate_certificate' => $isKhmerUi ? 'វិញ្ញាបនបត្រក្រោយបរិញ្ញាបត្រ' : 'Postgraduate certificate',
        'postgraduate_diploma' => $isKhmerUi ? 'សញ្ញាបត្រក្រោយបរិញ្ញាបត្រ' : 'Postgraduate diploma',
        'master_degree' => $isKhmerUi ? 'បរិញ្ញាបត្រជាន់ខ្ពស់' : 'Master degree',
        'specialist_degree' => $isKhmerUi ? 'សញ្ញាបត្រឯកទេស' : 'Specialist degree',
        'doctorate_degree' => $isKhmerUi ? 'បណ្ឌិត' : 'Doctorate degree',
        'postdoctorate_degree' => $isKhmerUi ? 'ក្រោយបណ្ឌិត' : 'Postdoctorate degree',
    ];
    $matchDegreeLevelOption = static function ($value) use ($degreeLevelOptions): string {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        if (array_key_exists($text, $degreeLevelOptions)) {
            return $text;
        }

        $needle = mb_strtolower($text, 'UTF-8');
        foreach ($degreeLevelOptions as $optionValue => $optionLabel) {
            if ($needle === mb_strtolower((string) $optionLabel, 'UTF-8')) {
                return $optionValue;
            }
        }

        return '';
    };
    $matchLanguageLevelOption = static function ($value) use ($languageLevelOptions): string {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        if (array_key_exists($text, $languageLevelOptions)) {
            return $text;
        }

        $normalized = mb_strtolower($text, 'UTF-8');
        $map = [
            'a' => 'expert',
            'b' => 'good',
            'c' => 'medium',
            'good' => 'good',
            'excellent' => 'expert',
            'advanced' => 'expert',
            'proficient' => 'expert',
            'expert' => 'expert',
            'fair' => 'medium',
            'intermediate' => 'medium',
            'average' => 'medium',
            'medium' => 'medium',
            'basic' => 'basic',
            'elementary' => 'basic',
            'beginner' => 'basic',
            'poor' => 'basic',
            'កម្រិតស្ទាត់ជំនាញ' => 'expert',
            'កម្រិតល្អ' => 'good',
            'កម្រិតមធ្យម' => 'medium',
            'កម្រិតមូលដ្ឋាន' => 'basic',
            'ស្ទាត់ជំនាញ' => 'expert',
            'ល្អ' => 'good',
            'មធ្យម' => 'medium',
            'មូលដ្ឋាន' => 'basic',
        ];

        return $map[$normalized] ?? '';
    };
    $flexDateValue = static function ($value): string {
        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->month === 1 && $value->day === 1
                ? $value->format('Y')
                : $value->format('d/m/Y');
        }

        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        if (preg_match('/^\d{4}$/', $text)) {
            return $text;
        }

        try {
            $date = \Illuminate\Support\Carbon::parse($text);
            return $date->month === 1 && $date->day === 1
                ? $date->format('Y')
                : $date->format('d/m/Y');
        } catch (\Throwable $e) {
            return $text;
        }
    };
    $certificateTypeOptions = [
        'វិញ្ញាបនបត្រ' => $isKhmerUi ? 'វិញ្ញាបនបត្រ' : 'Certificate',
        'សញ្ញាបត្រ' => $isKhmerUi ? 'សញ្ញាបត្រ' : 'Diploma',
        'លិខិតបញ្ជាក់' => $isKhmerUi ? 'លិខិតបញ្ជាក់' : 'Letter of completion',
        'អាជ្ញាបណ្ណ' => $isKhmerUi ? 'អាជ្ញាបណ្ណ' : 'License',
        'បរិញ្ញាបត្ររង' => $isKhmerUi ? 'បរិញ្ញាបត្ររង' : 'Associate degree',
        'បរិញ្ញាបត្រ' => $isKhmerUi ? 'បរិញ្ញាបត្រ' : 'Bachelor degree',
        'បរិញ្ញាបត្រជាន់ខ្ពស់' => $isKhmerUi ? 'បរិញ្ញាបត្រជាន់ខ្ពស់' : 'Master degree',
        'បណ្ឌិត' => $isKhmerUi ? 'បណ្ឌិត' : 'Doctorate',
        'ផ្សេងៗ' => $isKhmerUi ? 'ផ្សេងៗ' : 'Other',
    ];
    $countryTranslations = [
        'Afghanistan' => 'អាហ្វហ្គានីស្ថាន',
        'Albania' => 'អាល់បានី',
        'Algeria' => 'អាល់ហ្សេរី',
        'Andorra' => 'អង់ដូរ៉ា',
        'Angola' => 'អង់ហ្គោឡា',
        'Antigua and Barbuda' => 'អង់ទីហ្គា និង បាប៊ុយដា',
        'Argentina' => 'អាហ្សង់ទីន',
        'Armenia' => 'អាមេនី',
        'Australia' => 'អូស្ត្រាលី',
        'Austria' => 'អូទ្រីស',
        'Azerbaijan' => 'អាស៊ែបៃហ្សង់',
        'Bahamas' => 'បាហាម៉ាស',
        'Bahrain' => 'បារ៉ែន',
        'Bangladesh' => 'បង់ក្លាដែស',
        'Barbados' => 'បាបាដូស',
        'Belarus' => 'បេឡារុស',
        'Belgium' => 'បែលហ្ស៊ិក',
        'Belize' => 'បេលីស',
        'Benin' => 'បេណាំង',
        'Bhutan' => 'ប៊ូតង់',
        'Bolivia' => 'បូលីវី',
        'Bosnia and Herzegovina' => 'បូស្ន៊ី និង ហឺហ្សេហ្គោវីនា',
        'Botswana' => 'បុតស្វាណា',
        'Brazil' => 'ប្រេស៊ីល',
        'Brunei' => 'ប្រ៊ុយណេ',
        'Bulgaria' => 'ប៊ុលហ្គារី',
        'Burkina Faso' => 'បួគីណាហ្វាសូ',
        'Burundi' => 'ប៊ូរុនឌី',
        'Cambodia' => 'កម្ពុជា',
        'Cameroon' => 'កាមេរូន',
        'Canada' => 'កាណាដា',
        'Cape Verde' => 'កាបវែដ',
        'Central African Republic' => 'សាធារណរដ្ឋអាហ្វ្រិកកណ្ដាល',
        'Chad' => 'ឆាដ',
        'Chile' => 'ឈីលី',
        'China' => 'ចិន',
        'Colombi' => 'កូឡុំប៊ី',
        'Comoros' => 'កូម័រ',
        'Congo (Brazzaville)' => 'កុងហ្គោ ប្រាសាវីល',
        'Congo' => 'កុងហ្គោ',
        'Costa Rica' => 'កូស្តារីកា',
        "Cote d'Ivoire" => 'កូតឌីវ័រ',
        'Croatia' => 'ក្រូអាត',
        'Cuba' => 'គុយបា',
        'Cyprus' => 'ស៊ីប',
        'Czech Republic' => 'សាធារណរដ្ឋឆេក',
        'Denmark' => 'ដាណឺម៉ាក',
        'Djibouti' => 'ជីប៊ូទី',
        'Dominica' => 'ដូមីនីកា',
        'Dominican Republic' => 'សាធារណរដ្ឋដូមីនីកែន',
        'East Timor (Timor Timur)' => 'ទីម័រខាងកើត',
        'Ecuador' => 'អេក្វាឌ័រ',
        'Egypt' => 'អេហ្ស៊ីប',
        'El Salvador' => 'អែលសាល់វ៉ាឌ័រ',
        'Equatorial Guinea' => 'ហ្គីណេអេក្វាទ័រ',
        'Eritrea' => 'អេរីទ្រា',
        'Estonia' => 'អេស្តូនី',
        'Ethiopia' => 'អេត្យូពី',
        'Fiji' => 'ហ្វីជី',
        'Finland' => 'ហ្វាំងឡង់',
        'France' => 'បារាំង',
        'Gabon' => 'ហ្គាបុង',
        'Gambia' => 'ហ្គាំប៊ី',
        'Georgia' => 'ហ្សកហ្ស៊ី',
        'Germany' => 'អាល្លឺម៉ង់',
        'Ghana' => 'ហ្គាណា',
        'Greece' => 'ក្រិក',
        'Grenada' => 'ហ្គ្រេណាដា',
        'Guatemala' => 'ហ្គាតេម៉ាឡា',
        'Guinea' => 'ហ្គីណេ',
        'Guinea-Bissau' => 'ហ្គីណេប៊ីសៅ',
        'Guyana' => 'ហ្គាយអាណា',
        'Haiti' => 'ហៃទី',
        'Honduras' => 'ហុងឌូរ៉ាស',
        'Hungary' => 'ហុងគ្រី',
        'Iceland' => 'អ៊ីស្លង់',
        'India' => 'ឥណ្ឌា',
        'Indonesia' => 'ឥណ្ឌូណេស៊ី',
        'Iran' => 'អ៊ីរ៉ង់',
        'Iraq' => 'អ៊ីរ៉ាក់',
        'Ireland' => 'អៀរឡង់',
        'Israel' => 'អ៊ីស្រាអែល',
        'Italy' => 'អ៊ីតាលី',
        'Jamaica' => 'ហ្សាម៉ាអ៊ីក',
        'Japan' => 'ជប៉ុន',
        'Jordan' => 'ហ្ស៊កដានី',
        'Kazakhstan' => 'កាហ្សាក់ស្ថាន',
        'Kenya' => 'កេនយ៉ា',
        'Kiribati' => 'គីរីបាទី',
        'Korea, North' => 'កូរ៉េខាងជើង',
        'Korea, South' => 'កូរ៉េខាងត្បូង',
        'Kuwait' => 'គុយវ៉ែត',
        'Kyrgyzstan' => 'កៀហ្ស៊ីស៊ីស្ថាន',
        'Laos' => 'ឡាវ',
        'Latvia' => 'ឡាតវី',
        'Lebanon' => 'លីបង់',
        'Lesotho' => 'ឡេសូតូ',
        'Liberia' => 'លីបេរីយ៉ា',
        'Libya' => 'លីប៊ី',
        'Liechtenstein' => 'លិចតិនស្តាញ',
        'Lithuania' => 'លីទុយអានី',
        'Luxembourg' => 'លុចសំបួ',
        'Macedonia' => 'ម៉ាសេដូនី',
        'Madagascar' => 'ម៉ាដាហ្គាស្ការ',
        'Malawi' => 'ម៉ាឡាវី',
        'Malaysia' => 'ម៉ាឡេស៊ី',
        'Maldives' => 'ម៉ាល់ឌីវ',
        'Mali' => 'ម៉ាលី',
        'Malta' => 'ម៉ាល់តា',
        'Marshall Islands' => 'កោះម៉ាស្យាល់',
        'Mauritania' => 'ម៉ូរីតានី',
        'Mauritius' => 'ម៉ូរីស',
        'Mexico' => 'ម៉ិកស៊ិក',
        'Micronesia' => 'មីក្រូណេស៊ី',
        'Moldova' => 'ម៉ុលដូវា',
        'Monaco' => 'ម៉ូណាកូ',
        'Mongolia' => 'ម៉ុងហ្គោលី',
        'Morocco' => 'ម៉ារ៉ុក',
        'Mozambique' => 'ម៉ូសំប៊ិក',
        'Myanmar' => 'មីយ៉ាន់ម៉ា',
        'Namibia' => 'ណាមីប៊ី',
        'Nauru' => 'ណូរូ',
        'Nepal' => 'នេប៉ាល់',
        'Netherlands' => 'ហូឡង់',
        'New Zealand' => 'នូវែលសេឡង់',
        'Nicaragua' => 'នីការ៉ាហ្គា',
        'Niger' => 'នីហ្សេ',
        'Nigeria' => 'នីហ្សេរីយ៉ា',
        'Norway' => 'ន័រវែស',
        'Oman' => 'អូម៉ង់',
        'Pakistan' => 'ប៉ាគីស្ថាន',
        'Palau' => 'ប៉ាឡៅ',
        'Panama' => 'ប៉ាណាម៉ា',
        'Papua New Guinea' => 'ប៉ាពួញូហ្គីណេ',
        'Paraguay' => 'ប៉ារ៉ាហ្គាយ',
        'Peru' => 'ប៉េរូ',
        'Philippines' => 'ហ្វីលីពីន',
        'Poland' => 'ប៉ូឡូញ',
        'Portugal' => 'ព័រទុយហ្គាល់',
        'Qatar' => 'កាតា',
        'Romania' => 'រូម៉ានី',
        'Russia' => 'រុស្ស៊ី',
        'Rwanda' => 'រវ៉ាន់ដា',
        'Saint Kitts and Nevis' => 'សាំងគីត និង នេវីស',
        'Saint Lucia' => 'សាំងលូស៊ីយ៉ា',
        'Saint Vincent' => 'សាំងវ៉ាំងសង់',
        'Samoa' => 'សាម័រ',
        'San Marino' => 'សានម៉ារីណូ',
        'Sao Tome and Principe' => 'សៅតូមេ និង ប្រាំងស៊ីប',
        'Saudi Arabia' => 'អារ៉ាប៊ីសាអូឌីត',
        'Senegal' => 'សេណេហ្គាល់',
        'Serbia and Montenegro' => 'ស៊ែប៊ី និង ម៉ុងតេណេហ្គ្រោ',
        'Seychelles' => 'សីសែល',
        'Sierra Leone' => 'សៀរ៉ាឡេអូន',
        'Singapore' => 'សិង្ហបុរី',
        'Slovakia' => 'ស្លូវ៉ាគី',
        'Slovenia' => 'ស្លូវេនី',
        'Solomon Islands' => 'កោះសូឡូម៉ុង',
        'Somalia' => 'សូម៉ាលី',
        'South Africa' => 'អាហ្វ្រិកខាងត្បូង',
        'Spain' => 'អេស្ប៉ាញ',
        'Sri Lanka' => 'ស្រីលង្កា',
        'Sudan' => 'ស៊ូដង់',
        'Suriname' => 'ស៊ូរីណាម',
        'Swaziland' => 'ស្វាស៊ីឡង់',
        'Sweden' => 'ស៊ុយអែត',
        'Switzerland' => 'ស្វីស',
        'Syria' => 'ស៊ីរី',
        'Taiwan' => 'តៃវ៉ាន់',
        'Tajikistan' => 'តាជីគីស្ថាន',
        'Tanzania' => 'តង់សានី',
        'Thailand' => 'ថៃ',
        'Togo' => 'តូហ្គោ',
        'Tonga' => 'តុងហ្គា',
        'Trinidad and Tobago' => 'ទ្រីនីដាដ និង តូបាហ្គោ',
        'Tunisia' => 'ទុយនេស៊ី',
        'Turkey' => 'ទួរគី',
        'Turkmenistan' => 'តួកមេនីស្ថាន',
        'Tuvalu' => 'ទូវ៉ាលូ',
        'Uganda' => 'អ៊ូហ្គង់ដា',
        'Ukraine' => 'អ៊ុយក្រែន',
        'United Arab Emirates' => 'អារ៉ាប់រួម',
        'United Kingdom' => 'ចក្រភពអង់គ្លេស',
        'United States' => 'សហរដ្ឋអាមេរិក',
        'Uruguay' => 'អ៊ុយរូហ្គាយ',
        'Uzbekistan' => 'អ៊ូសបេគីស្ថាន',
        'Vanuatu' => 'វ៉ានូអាទូ',
        'Vatican City' => 'បុរីវ៉ាទីកង់',
        'Venezuela' => 'វេណេស៊ុយអេឡា',
        'Vietnam' => 'វៀតណាម',
        'Yemen' => 'យេម៉ែន',
        'Zambia' => 'សំប៊ី',
        'Zimbabwe' => 'ហ្ស៊ីមបាវ៉េ',
    ];
    $toUiCountryName = static function ($value) use ($countryTranslations, $isKhmerUi): string {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        if (!$isKhmerUi) {
            return $text;
        }

        $candidates = [$text];

        if (str_ends_with($text, ', The')) {
            $base = trim(substr($text, 0, -5));
            if ($base !== '') {
                $candidates[] = $base;
                $candidates[] = 'The ' . $base;
            }
        }

        if (str_starts_with($text, 'The ')) {
            $base = trim(substr($text, 4));
            if ($base !== '') {
                $candidates[] = $base;
                $candidates[] = $base . ', The';
            }
        }

        foreach ($candidates as $candidate) {
            if (isset($countryTranslations[$candidate])) {
                return $countryTranslations[$candidate];
            }
        }

        return $text;
    };
    $generalEducationDegreeKeys = [
        'no_formal_qualification',
        'literacy_certificate',
        'primary_certificate',
        'lower_secondary_diploma',
        'upper_secondary_diploma',
    ];
    $countryOptions = collect($countries ?? [])
        ->pluck('country_name')
        ->filter(fn ($value) => trim((string) $value) !== '')
        ->map(fn ($value) => [
            'value' => $toUiCountryName($value),
            'label' => $toUiCountryName($value),
        ])
        ->unique('value')
        ->sortBy('label', SORT_NATURAL)
        ->values();
    $generalEducationRow = old('general_education');
    $professionalEducationRows = $educationRows;
    if (!is_array($generalEducationRow)) {
        $generalEducationRows = collect($educationRows)
            ->filter(function ($row) use ($generalEducationDegreeKeys) {
                $degree = trim((string) data_get($row, 'degree_level', ''));
                return $degree !== '' && in_array($degree, $generalEducationDegreeKeys, true);
            })
            ->sortByDesc(function ($row) {
                return data_get($row, 'end_date') ?: data_get($row, 'start_date') ?: '';
            })
            ->values();

        $generalEducationRow = $generalEducationRows->first() ?: [];
        $professionalEducationRows = collect($educationRows)
            ->reject(function ($row) use ($generalEducationDegreeKeys) {
                $degree = trim((string) data_get($row, 'degree_level', ''));
                return $degree !== '' && in_array($degree, $generalEducationDegreeKeys, true);
            })
            ->values()
            ->all();
    }
    if (!is_array($generalEducationRow)) {
        $generalEducationRow = [];
    }
    if (empty($professionalEducationRows)) {
        $professionalEducationRows = [[]];
    }
    if (trim((string) ($generalEducationRow['degree_level'] ?? '')) === '') {
        $generalEducationRow['degree_level'] = match ((string) $selectedNationalEducation) {
            '1' => 'primary_certificate',
            '2' => 'lower_secondary_diploma',
            '3' => 'upper_secondary_diploma',
            default => '',
        };
    }
    if (trim((string) ($generalEducationRow['note'] ?? '')) === '') {
        $generalEducationRow['note'] = old('highest_educational_qualification', $emp?->highest_educational_qualification);
    }
    $generalEducationSelectedDegreeLevel = $matchDegreeLevelOption($generalEducationRow['degree_level'] ?? null);
    $generalEducationRawDegreeLevel = trim((string) ($generalEducationRow['degree_level'] ?? ''));
    $generalEducationSelectedCountryName = $toUiCountryName($generalEducationRow['country_name'] ?? '');
@endphp

<div class="gov-section-card mb-3">
    <h6 class="gov-section-title">{{ $trainingInformationTitle }}</h6>
    <div class="gov-section-note">{{ $yearHint }}</div>
    <div class="table-responsive">
        <table class="table table-bordered mb-0" id="general-education-table">
            <thead>
                <tr>
                    <th>{{ $countryLabel }}</th>
                    <th>{{ localize('institution_name') }}</th>
                    <th>{{ $startYearLabel }}</th>
                    <th>{{ $endYearLabel }}</th>
                    <th>{{ $degreeLevelLabel }}</th>
                    <th>{{ $highestEducationLabel }}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <select name="general_education[country_name]" class="form-select">
                            <option value="">{{ $isKhmerUi ? 'ជ្រើសរើសប្រទេស' : 'Select country' }}</option>
                            @foreach ($countryOptions as $countryOption)
                                <option value="{{ $countryOption['value'] }}" @selected($generalEducationSelectedCountryName === $countryOption['value'])>{{ $countryOption['label'] }}</option>
                            @endforeach
                            @if ($generalEducationSelectedCountryName !== '' && !$countryOptions->pluck('value')->contains($generalEducationSelectedCountryName))
                                <option value="{{ $generalEducationSelectedCountryName }}" selected>{{ $generalEducationSelectedCountryName }}</option>
                            @endif
                        </select>
                    </td>
                    <td><input type="text" name="general_education[institution_name]" class="form-control" value="{{ $generalEducationRow['institution_name'] ?? '' }}"></td>
                    <td><input type="number" name="general_education[start_date]" class="form-control gov-year-input" value="{{ $yearValue($generalEducationRow['start_date'] ?? null) }}" min="1900" max="2100" step="1" placeholder="{{ $yearPlaceholder }}"></td>
                    <td><input type="number" name="general_education[end_date]" class="form-control gov-year-input" value="{{ $yearValue($generalEducationRow['end_date'] ?? null) }}" min="1900" max="2100" step="1" placeholder="{{ $yearPlaceholder }}"></td>
                    <td>
                        <select name="general_education[degree_level]" class="form-select">
                            <option value="">{{ $isKhmerUi ? 'ជ្រើសរើសកម្រិតសញ្ញាបត្រ' : 'Select degree level' }}</option>
                            @foreach ($degreeLevelOptions as $optionValue => $optionLabel)
                                <option value="{{ $optionValue }}" @selected($generalEducationSelectedDegreeLevel === $optionValue)>{{ $optionLabel }}</option>
                            @endforeach
                            @if ($generalEducationRawDegreeLevel !== '' && $generalEducationSelectedDegreeLevel === '')
                                <option value="{{ $generalEducationRawDegreeLevel }}" selected>{{ $generalEducationRawDegreeLevel }}</option>
                            @endif
                        </select>
                    </td>
                    <td><input type="text" name="general_education[note]" class="form-control" value="{{ $generalEducationRow['note'] ?? old('highest_educational_qualification', $emp?->highest_educational_qualification) }}" placeholder="{{ $highestEducationLabel }}"></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="gov-section-card mb-3">
    <h6 class="gov-section-title">{{ $educationHistoryTitle }}</h6>
    <div class="gov-section-note">{{ $yearHint }}</div>
    <template id="education-degree-level-options-template">
        <option value="">{{ $isKhmerUi ? 'ជ្រើសរើសកម្រិតសញ្ញាបត្រ' : 'Select degree level' }}</option>
        @foreach ($degreeLevelOptions as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
        @endforeach
    </template>
    <template id="country-options-template">
        <option value="">{{ $isKhmerUi ? 'ជ្រើសរើសប្រទេស' : 'Select country' }}</option>
        @foreach ($countryOptions as $countryOption)
            <option value="{{ $countryOption['value'] }}">{{ $countryOption['label'] }}</option>
        @endforeach
    </template>
    <template id="certificate-type-options-template">
        <option value="">{{ $isKhmerUi ? 'ជ្រើសរើសប្រភេទសញ្ញាបត្រ' : 'Select certificate type' }}</option>
        @foreach ($certificateTypeOptions as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
        @endforeach
    </template>
    <template id="language-level-options-template">
        <option value="">{{ $isKhmerUi ? 'ជ្រើសរើសកម្រិត' : 'Select level' }}</option>
        @foreach ($languageLevelOptions as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
        @endforeach
    </template>
    <div class="table-responsive mb-2">
        <table class="table table-bordered" id="education-history-table">
            <thead>
                <tr>
                    <th>{{ $countryLabel }}</th>
                    <th>{{ localize('institution_name') }}</th>
                    <th>{{ $startYearLabel }}</th>
                    <th>{{ $endYearLabel }}</th>
                    <th>{{ $degreeLevelLabel }}</th>
                    <th>{{ $majorSubjectLabel }}</th>
                    <th>{{ localize('remarks') }}</th>
                    <th width="80">{{ localize('action') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($professionalEducationRows as $idx => $row)
                    @php
                        $selectedDegreeLevel = $matchDegreeLevelOption($row['degree_level'] ?? null);
                        $rawDegreeLevel = trim((string) ($row['degree_level'] ?? ''));
                        $selectedCountryName = $toUiCountryName($row['country_name'] ?? '');
                    @endphp
                    <tr>
                        <td>
                            <select name="education_histories[{{ $idx }}][country_name]" class="form-select">
                                <option value="">{{ $isKhmerUi ? 'ជ្រើសរើសប្រទេស' : 'Select country' }}</option>
                                @foreach ($countryOptions as $countryOption)
                                    <option value="{{ $countryOption['value'] }}" @selected($selectedCountryName === $countryOption['value'])>{{ $countryOption['label'] }}</option>
                                @endforeach
                                @if ($selectedCountryName !== '' && !$countryOptions->pluck('value')->contains($selectedCountryName))
                                    <option value="{{ $selectedCountryName }}" selected>{{ $selectedCountryName }}</option>
                                @endif
                            </select>
                        </td>
                        <td><input type="text" name="education_histories[{{ $idx }}][institution_name]" class="form-control" value="{{ $row['institution_name'] ?? '' }}"></td>
                        <td><input type="number" name="education_histories[{{ $idx }}][start_date]" class="form-control gov-year-input" value="{{ $yearValue($row['start_date'] ?? null) }}" min="1900" max="2100" step="1" placeholder="{{ $yearPlaceholder }}"></td>
                        <td><input type="number" name="education_histories[{{ $idx }}][end_date]" class="form-control gov-year-input" value="{{ $yearValue($row['end_date'] ?? null) }}" min="1900" max="2100" step="1" placeholder="{{ $yearPlaceholder }}"></td>
                        <td>
                            <select name="education_histories[{{ $idx }}][degree_level]" class="form-select">
                                <option value="">{{ $isKhmerUi ? 'ជ្រើសរើសកម្រិតសញ្ញាបត្រ' : 'Select degree level' }}</option>
                                @foreach ($degreeLevelOptions as $optionValue => $optionLabel)
                                    <option value="{{ $optionValue }}" @selected($selectedDegreeLevel === $optionValue)>{{ $optionLabel }}</option>
                                @endforeach
                                @if ($rawDegreeLevel !== '' && $selectedDegreeLevel === '')
                                    <option value="{{ $rawDegreeLevel }}" selected>{{ $rawDegreeLevel }}</option>
                                @endif
                            </select>
                        </td>
                        <td><input type="text" name="education_histories[{{ $idx }}][major_subject]" class="form-control" value="{{ $row['major_subject'] ?? '' }}"></td>
                        <td><input type="text" name="education_histories[{{ $idx }}][note]" class="form-control" value="{{ $row['note'] ?? '' }}"></td>
                        <td><button type="button" class="btn btn-sm btn-danger repeater-remove">{{ localize('delete') }}</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <button type="button" class="btn btn-sm btn-primary repeater-add" data-target="#education-history-table" data-repeater="education_histories">
        + {{ localize('add_more') }}
    </button>
</div>

<div class="gov-section-card mb-3">
    <h6 class="gov-section-title">{{ $foreignLanguageTitle }}</h6>
    <div class="gov-section-note">{{ $languageDateHint }}</div>
    <div class="table-responsive mb-2">
        <table class="table table-bordered" id="foreign-language-table">
            <thead>
                <tr>
                    <th>{{ localize('language') }}</th>
                    <th>{{ $speakingLabel }}</th>
                    <th>{{ $readingLabel }}</th>
                    <th>{{ $writingLabel }}</th>
                    <th>{{ localize('institution_name') }}</th>
                    <th>{{ $languageStartDateLabel }}</th>
                    <th>{{ $languageEndDateLabel }}</th>
                    <th>{{ $certificateResultLabel }}</th>
                    <th width="80">{{ localize('action') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($languageRows as $idx => $row)
                    @php
                        $selectedCountryName = $toUiCountryName($row['country_name'] ?? '');
                        $selectedSpeakingLevel = $matchLanguageLevelOption($row['speaking_level'] ?? null);
                        $selectedReadingLevel = $matchLanguageLevelOption($row['reading_level'] ?? null);
                        $selectedWritingLevel = $matchLanguageLevelOption($row['writing_level'] ?? null);
                        $rawSpeakingLevel = trim((string) ($row['speaking_level'] ?? ''));
                        $rawReadingLevel = trim((string) ($row['reading_level'] ?? ''));
                        $rawWritingLevel = trim((string) ($row['writing_level'] ?? ''));
                    @endphp
                    <tr>
                        <td><input type="text" name="foreign_languages[{{ $idx }}][language_name]" class="form-control" value="{{ $row['language_name'] ?? '' }}"></td>
                        <td>
                            <select name="foreign_languages[{{ $idx }}][speaking_level]" class="form-select">
                                <option value="">{{ $isKhmerUi ? 'ជ្រើសរើសកម្រិត' : 'Select level' }}</option>
                                @foreach ($languageLevelOptions as $optionValue => $optionLabel)
                                    <option value="{{ $optionValue }}" @selected($selectedSpeakingLevel === $optionValue)>{{ $optionLabel }}</option>
                                @endforeach
                                @if ($rawSpeakingLevel !== '' && $selectedSpeakingLevel === '')
                                    <option value="{{ $rawSpeakingLevel }}" selected>{{ $rawSpeakingLevel }}</option>
                                @endif
                            </select>
                        </td>
                        <td>
                            <select name="foreign_languages[{{ $idx }}][reading_level]" class="form-select">
                                <option value="">{{ $isKhmerUi ? 'ជ្រើសរើសកម្រិត' : 'Select level' }}</option>
                                @foreach ($languageLevelOptions as $optionValue => $optionLabel)
                                    <option value="{{ $optionValue }}" @selected($selectedReadingLevel === $optionValue)>{{ $optionLabel }}</option>
                                @endforeach
                                @if ($rawReadingLevel !== '' && $selectedReadingLevel === '')
                                    <option value="{{ $rawReadingLevel }}" selected>{{ $rawReadingLevel }}</option>
                                @endif
                            </select>
                        </td>
                        <td>
                            <select name="foreign_languages[{{ $idx }}][writing_level]" class="form-select">
                                <option value="">{{ $isKhmerUi ? 'ជ្រើសរើសកម្រិត' : 'Select level' }}</option>
                                @foreach ($languageLevelOptions as $optionValue => $optionLabel)
                                    <option value="{{ $optionValue }}" @selected($selectedWritingLevel === $optionValue)>{{ $optionLabel }}</option>
                                @endforeach
                                @if ($rawWritingLevel !== '' && $selectedWritingLevel === '')
                                    <option value="{{ $rawWritingLevel }}" selected>{{ $rawWritingLevel }}</option>
                                @endif
                            </select>
                        </td>
                        <td><input type="text" name="foreign_languages[{{ $idx }}][institution_name]" class="form-control" value="{{ $row['institution_name'] ?? '' }}"></td>
                        <td><input type="text" name="foreign_languages[{{ $idx }}][start_date]" class="form-control" value="{{ $flexDateValue($row['start_date'] ?? null) }}" placeholder="DD/MM/YYYY / {{ $yearPlaceholder }}"></td>
                        <td><input type="text" name="foreign_languages[{{ $idx }}][end_date]" class="form-control" value="{{ $flexDateValue($row['end_date'] ?? null) }}" placeholder="DD/MM/YYYY / {{ $yearPlaceholder }}"></td>
                        <td><input type="text" name="foreign_languages[{{ $idx }}][result]" class="form-control" value="{{ $row['result'] ?? '' }}" placeholder="{{ $certificateResultLabel }}"></td>
                        <td><button type="button" class="btn btn-sm btn-danger repeater-remove">{{ localize('delete') }}</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <button type="button" class="btn btn-sm btn-primary repeater-add" data-target="#foreign-language-table" data-repeater="foreign_languages">
        + {{ localize('add_more') }}
    </button>
</div>

<div class="gov-section-card mb-3">
    <h6 class="gov-section-title">{{ $continuingTrainingTitle }}</h6>
    <div class="gov-section-note">{{ $continuingTrainingDateHint }}</div>
    <div class="table-responsive mb-2">
        <table class="table table-bordered" id="academic-info-table">
            <thead>
                <tr>
                    <th>{{ $courseTitleLabel }}</th>
                    <th>{{ $certificateTypeLabel }}</th>
                    <th>{{ $countryLabel }}</th>
                    <th>{{ localize('institution_name') }}</th>
                    <th>{{ $certificateResultLabel }}</th>
                    <th>{{ $languageStartDateLabel }}</th>
                    <th>{{ $languageEndDateLabel }}</th>
                    <th width="80">{{ localize('action') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($academicRows as $idx => $row)
                    @php
                        $selectedCountryName = $toUiCountryName($row['country_name'] ?? '');
                    @endphp
                    <tr>
                        <td><input type="text" name="academic_infos[{{ $idx }}][exam_title]" class="form-control" value="{{ $row['exam_title'] ?? '' }}"></td>
                        <td>
                            <select name="academic_infos[{{ $idx }}][certificate_type]" class="form-select mb-1 academic-certificate-type-select">
                                <option value="">{{ $isKhmerUi ? 'ជ្រើសរើសប្រភេទសញ្ញាបត្រ' : 'Select certificate type' }}</option>
                                @foreach ($certificateTypeOptions as $optionValue => $optionLabel)
                                    <option value="{{ $optionValue }}" @selected(($row['certificate_type'] ?? '') === $optionValue)>{{ $optionLabel }}</option>
                                @endforeach
                            </select>
                            <div class="academic-certificate-type-other-wrapper" @if (($row['certificate_type'] ?? '') !== 'ផ្សេងៗ') style="display:none;" @endif>
                                <input type="text" name="academic_infos[{{ $idx }}][certificate_type_other]" class="form-control academic-certificate-type-other-input" value="{{ $row['certificate_type_other'] ?? '' }}" placeholder="{{ $certificateTypeOtherLabel }}">
                            </div>
                        </td>
                        <td>
                            <select name="academic_infos[{{ $idx }}][country_name]" class="form-select">
                                <option value="">{{ $isKhmerUi ? 'ជ្រើសរើសប្រទេស' : 'Select country' }}</option>
                                @foreach ($countryOptions as $countryOption)
                                    <option value="{{ $countryOption['value'] }}" @selected($selectedCountryName === $countryOption['value'])>{{ $countryOption['label'] }}</option>
                                @endforeach
                                @if ($selectedCountryName !== '' && !$countryOptions->pluck('value')->contains($selectedCountryName))
                                    <option value="{{ $selectedCountryName }}" selected>{{ $selectedCountryName }}</option>
                                @endif
                            </select>
                        </td>
                        <td><input type="text" name="academic_infos[{{ $idx }}][institute_name]" class="form-control" value="{{ $row['institute_name'] ?? '' }}"></td>
                        <td><input type="text" name="academic_infos[{{ $idx }}][result]" class="form-control" value="{{ $row['result'] ?? '' }}"></td>
                        <td><input type="text" name="academic_infos[{{ $idx }}][start_date]" class="form-control" value="{{ $flexDateValue($row['start_date'] ?? null) }}" placeholder="DD/MM/YYYY / {{ $yearPlaceholder }}"></td>
                        <td>
                            <input type="text" name="academic_infos[{{ $idx }}][end_date]" class="form-control" value="{{ $flexDateValue($row['end_date'] ?? ($row['graduation_year'] ?? null)) }}" placeholder="DD/MM/YYYY / {{ $yearPlaceholder }}">
                            <input type="hidden" name="academic_infos[{{ $idx }}][graduation_year]" value="{{ $row['graduation_year'] ?? '' }}">
                        </td>
                        <td><button type="button" class="btn btn-sm btn-danger repeater-remove">{{ localize('delete') }}</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <button type="button" class="btn btn-sm btn-primary repeater-add" data-target="#academic-info-table" data-repeater="academic_infos">
        + {{ localize('add_more') }}
    </button>
</div>


