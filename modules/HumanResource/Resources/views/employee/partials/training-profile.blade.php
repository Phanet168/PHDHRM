@php
    $emp = $employee ?? null;

    $educationRows = old('education_histories');
    if (!is_array($educationRows)) {
        $educationRows = $emp ? $emp->educationHistories->map(fn($r) => $r->toArray())->toArray() : [];
    }
    if (empty($educationRows)) {
        $educationRows = [[]];
    }

    $languageRows = old('foreign_languages');
    if (!is_array($languageRows)) {
        $languageRows = $emp ? $emp->foreignLanguages->map(fn($r) => $r->toArray())->toArray() : [];
    }
    if (empty($languageRows)) {
        $languageRows = [[]];
    }

    $isKhmerUi = app()->getLocale() === 'km';
    $educationHistoryTitle = $isKhmerUi ? 'ប្រវត្តិសិក្សា' : 'Education history';
    $foreignLanguageTitle = $isKhmerUi ? 'ភាសាបរទេស' : 'Foreign languages';
    $nationalEducationLabel = $isKhmerUi ? 'កម្រិតវប្បធម៌ជាតិ' : 'National general education level';
    $highestEducationLabel = $isKhmerUi ? 'កម្រិតវប្បធម៌ខ្ពស់បំផុត' : 'Highest educational qualification';
    $degreeLevelLabel = $isKhmerUi ? 'កម្រិតសញ្ញាបត្រ' : 'Degree level';
    $majorSubjectLabel = $isKhmerUi ? 'មុខវិជ្ជា/ជំនាញសិក្សា' : 'Major / field of study';
    $speakingLabel = $isKhmerUi ? 'ការសន្ទនា' : 'Speaking';
    $readingLabel = $isKhmerUi ? 'ការអាន' : 'Reading';
    $writingLabel = $isKhmerUi ? 'ការសរសេរ' : 'Writing';
    $startYearLabel = $isKhmerUi ? 'ឆ្នាំចាប់ផ្តើម' : 'Start year';
    $endYearLabel = $isKhmerUi ? 'ឆ្នាំបញ្ចប់' : 'End year';
    $languageStartDateLabel = $isKhmerUi ? 'ថ្ងៃខែឆ្នាំចាប់ផ្តើម / ឆ្នាំ' : 'Start date / year';
    $languageEndDateLabel = $isKhmerUi ? 'ថ្ងៃខែឆ្នាំបញ្ចប់ / ឆ្នាំ' : 'End date / year';
    $yearPlaceholder = $isKhmerUi ? 'ឧ. ២០២០' : 'e.g. 2020';
    $yearHint = $isKhmerUi ? 'បញ្ចូលតែឆ្នាំបានគ្រប់គ្រាន់ មិនចាំបាច់បញ្ចូលថ្ងៃ និង ខែទេ។' : 'Year only is enough. Day and month are not required.';
    $languageDateHint = $isKhmerUi ? 'សម្រាប់ភាសាបរទេស អាចបញ្ចូលថ្ងៃ ខែ ឆ្នាំពេញលេញ ឬបញ្ចូលតែឆ្នាំក៏បាន។ ឧ. 14/05/2020 ឬ 2020' : 'For foreign languages, enter a full date or just a year. Example: 14/05/2020 or 2020.';
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
@endphp

<div class="gov-section-card mb-3">
    <h6 class="gov-section-title">{{ localize('training_information') }}</h6>
    <div class="form-group mb-2 mx-0 row">
        <label for="national_education_level" class="col-lg-3 col-form-label ps-0">{{ $nationalEducationLabel }}</label>
        <div class="col-lg-9">
            <select name="national_education_level" id="national_education_level"
                class="form-select {{ $errors->first('national_education_level') ? 'is-invalid' : '' }}">
                <option value="">{{ $isKhmerUi ? 'ជ្រើសរើសកម្រិតវប្បធម៌ជាតិ' : 'Select national education level' }}</option>
                @foreach ($nationalEducationOptions as $value => $label)
                    <option value="{{ $value }}" @selected((string) $selectedNationalEducation === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
            @if ($errors->has('national_education_level'))
                <div class="error text-danger text-start">{{ $errors->first('national_education_level') }}</div>
            @endif
        </div>
    </div>
    <div class="form-group mb-0 mx-0 row">
        <label for="highest_educational_qualification" class="col-lg-3 col-form-label ps-0">{{ $highestEducationLabel }}</label>
        <div class="col-lg-9">
            <input type="text" name="highest_educational_qualification" id="highest_educational_qualification"
                class="form-control {{ $errors->first('highest_educational_qualification') ? 'is-invalid' : '' }}"
                value="{{ old('highest_educational_qualification', $emp?->highest_educational_qualification) }}" autocomplete="off"
                placeholder="{{ $highestEducationLabel }}">
            @if ($errors->has('highest_educational_qualification'))
                <div class="error text-danger text-start">{{ $errors->first('highest_educational_qualification') }}</div>
            @endif
        </div>
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
    <div class="table-responsive mb-2">
        <table class="table table-bordered" id="education-history-table">
            <thead>
                <tr>
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
                @foreach ($educationRows as $idx => $row)
                    @php
                        $selectedDegreeLevel = $matchDegreeLevelOption($row['degree_level'] ?? null);
                        $rawDegreeLevel = trim((string) ($row['degree_level'] ?? ''));
                    @endphp
                    <tr>
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
                    <th>{{ localize('result') }}</th>
                    <th width="80">{{ localize('action') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($languageRows as $idx => $row)
                    <tr>
                        <td><input type="text" name="foreign_languages[{{ $idx }}][language_name]" class="form-control" value="{{ $row['language_name'] ?? '' }}"></td>
                        <td><input type="text" name="foreign_languages[{{ $idx }}][speaking_level]" class="form-control" value="{{ $row['speaking_level'] ?? '' }}" placeholder="A/B/C"></td>
                        <td><input type="text" name="foreign_languages[{{ $idx }}][reading_level]" class="form-control" value="{{ $row['reading_level'] ?? '' }}" placeholder="A/B/C"></td>
                        <td><input type="text" name="foreign_languages[{{ $idx }}][writing_level]" class="form-control" value="{{ $row['writing_level'] ?? '' }}" placeholder="A/B/C"></td>
                        <td><input type="text" name="foreign_languages[{{ $idx }}][institution_name]" class="form-control" value="{{ $row['institution_name'] ?? '' }}"></td>
                        <td><input type="text" name="foreign_languages[{{ $idx }}][start_date]" class="form-control" value="{{ $flexDateValue($row['start_date'] ?? null) }}" placeholder="DD/MM/YYYY / {{ $yearPlaceholder }}"></td>
                        <td><input type="text" name="foreign_languages[{{ $idx }}][end_date]" class="form-control" value="{{ $flexDateValue($row['end_date'] ?? null) }}" placeholder="DD/MM/YYYY / {{ $yearPlaceholder }}"></td>
                        <td><input type="text" name="foreign_languages[{{ $idx }}][result]" class="form-control" value="{{ $row['result'] ?? '' }}"></td>
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
