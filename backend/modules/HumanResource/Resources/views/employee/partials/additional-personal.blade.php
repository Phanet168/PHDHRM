@php
    $emp = $employee ?? null;
    $extra = optional($emp)->profileExtra;
    $birthPlaceText = old('legacy_pob_code', optional($emp)->legacy_pob_code);
    $birthParts = array_values(array_filter(array_map('trim', explode('>', (string) $birthPlaceText))));
    $birthProvinceName = old('birth_place_state', optional($extra)->birth_place_state ?: ($birthParts[0] ?? ''));
    $birthDistrictName = old('birth_place_city', optional($extra)->birth_place_city ?: ($birthParts[1] ?? ''));
    $birthCommuneName = old('birth_place_commune', optional($extra)->birth_place_commune ?: ($birthParts[2] ?? ''));
    $birthVillageName = old('birth_place_village', optional($extra)->birth_place_village ?: ($birthParts[3] ?? ''));
    $birthProvinceId = (string) old('birth_place_state_id', optional($emp)->birth_place_state_id ?? '');
    $birthDistrictId = (string) old('birth_place_city_id', optional($emp)->birth_place_city_id ?? '');
    $birthCommuneId = (string) old('birth_place_commune_id', optional($emp)->birth_place_commune_id ?? '');
    $birthVillageId = (string) old('birth_place_village_id', optional($emp)->birth_place_village_id ?? '');
    $birthProvinceInitial = $birthProvinceId !== '' ? $birthProvinceId : $birthProvinceName;
    $birthDistrictInitial = $birthDistrictId !== '' ? $birthDistrictId : $birthDistrictName;
    $birthCommuneInitial = $birthCommuneId !== '' ? $birthCommuneId : $birthCommuneName;
    $birthVillageInitial = $birthVillageId !== '' ? $birthVillageId : $birthVillageName;
    $currentStateName = old('present_address_state', optional($emp)->present_address_state);
    $currentCityName = old('present_address_city', optional($emp)->present_address_city);
    $currentCommuneName = old('present_address_post_code', optional($emp)->present_address_post_code);
    $currentVillageName = old('present_address_address', optional($emp)->present_address_address);
    $currentStateId = (string) old('present_address_state_id', optional($emp)->present_address_state_id ?? '');
    $currentCityId = (string) old('present_address_city_id', optional($emp)->present_address_city_id ?? '');
    $currentCommuneId = (string) old('present_address_commune_id', optional($emp)->present_address_commune_id ?? '');
    $currentVillageId = (string) old('present_address_village_id', optional($emp)->present_address_village_id ?? '');
    $currentStateInitial = $currentStateId !== '' ? $currentStateId : (string) $currentStateName;
    $currentCityInitial = $currentCityId !== '' ? $currentCityId : (string) $currentCityName;
    $currentCommuneInitial = $currentCommuneId !== '' ? $currentCommuneId : (string) $currentCommuneName;
    $currentVillageInitial = $currentVillageId !== '' ? $currentVillageId : (string) $currentVillageName;
    $isKhmerUi = app()->getLocale() === 'km' || (string) (app_setting()->lang?->value ?? '') === 'km';
    $provinceLabel = $isKhmerUi ? 'ខេត្ត/រាជធានី' : 'Province/Capital';
    $districtLabel = $isKhmerUi ? 'ក្រុង/ស្រុក/ខណ្ឌ' : 'City/District/Khan';
    $communeLabel = $isKhmerUi ? 'ឃុំ/សង្កាត់' : 'Commune/Sangkat';
    $villageLabel = $isKhmerUi ? 'ភូមិ' : 'Village';
    $selectProvinceLabel = $isKhmerUi ? 'ជ្រើសរើសខេត្ត/រាជធានី' : 'Select province/capital';
    $selectDistrictLabel = $isKhmerUi ? 'ជ្រើសរើសក្រុង/ស្រុក/ខណ្ឌ' : 'Select city/district/khan';
    $selectCommuneLabel = $isKhmerUi ? 'ជ្រើសរើសឃុំ/សង្កាត់' : 'Select commune/sangkat';
    $selectVillageLabel = $isKhmerUi ? 'ជ្រើសរើសភូមិ' : 'Select village';
    $placeOfBirthLabel = localize('place_of_birth');
    $birthPlaceHint = $isKhmerUi
        ? 'សូមបំពេញទីកន្លែងកំណើតតាមលំដាប់៖ ខេត្ត/រាជធានី > ក្រុង/ស្រុក/ខណ្ឌ > ឃុំ/សង្កាត់ > ភូមិ'
        : 'Please fill place of birth in order: Province/Capital > City/District/Khan > Commune/Sangkat > Village';
    $uniformSizeGroupTitle = $isKhmerUi ? 'ព័ត៌មានទំហំឯកសណ្ឋាន' : 'Uniform size information';
    $shirtSizeLabel = $isKhmerUi ? 'ទំហំអាវ' : 'Shirt size';
    $pantsSizeLabel = $isKhmerUi ? 'ទំហំខោ' : 'Pants size';
    $shoeSizeLabel = $isKhmerUi ? 'ទំហំស្បែកជើង' : 'Shoe size';
    $uniformSizePlaceholder = $isKhmerUi ? 'ឧ. M, L, XL ឬ 39, 40...' : 'e.g. M, L, XL or 39, 40...';
    $ethnicMinorityLabel = $isKhmerUi ? 'ជនជាតិភាគតិច' : 'Ethnic minority';
    $ethnicMinorityYesLabel = $isKhmerUi ? 'បាទ/ចាស' : 'Yes';
    $ethnicMinorityDropdownLabel = $isKhmerUi ? 'ជ្រើសរើសជនជាតិភាគតិច' : 'Select ethnic minority';
    $ethnicMinorityOtherLabel = $isKhmerUi ? 'ផ្សេងៗ (សូមបញ្ជាក់)' : 'Other (please specify)';
    $ethnicMinorityHint = $isKhmerUi ? 'ធីក បាទ/ចាស ប្រសិនបើជាជនជាតិភាគតិច' : 'Check Yes if employee belongs to an ethnic minority';
    $ethnicMinorityOptions = [
        ['value' => 'kuy', 'km' => 'កួយ', 'en' => 'Kuy'],
        ['value' => 'phnong', 'km' => 'ព្នង', 'en' => 'Phnong'],
        ['value' => 'tumpuon', 'km' => 'ទំពួន', 'en' => 'Tumpuon'],
        ['value' => 'jarai', 'km' => 'ចារ៉ាយ', 'en' => 'Jarai'],
        ['value' => 'kreung', 'km' => 'ក្រឹង', 'en' => 'Kreung'],
        ['value' => 'brao', 'km' => 'ប្រៅ', 'en' => 'Brao'],
        ['value' => 'kavet', 'km' => 'កាវ៉ែត', 'en' => 'Kavet'],
        ['value' => 'kachok', 'km' => 'កាចក់', 'en' => 'Kachok'],
        ['value' => 'stieng', 'km' => 'ស្ទៀង', 'en' => 'Stieng'],
        ['value' => 'por', 'km' => 'ព័រ', 'en' => 'Por'],
        ['value' => 'saoch', 'km' => 'ស្អូច', 'en' => 'Sa\'och'],
        ['value' => 'lun', 'km' => 'លុន', 'en' => 'Lun'],
        ['value' => 'mil', 'km' => 'មិល', 'en' => 'Mil'],
        ['value' => 'chong', 'km' => 'ចុង', 'en' => 'Chong'],
        ['value' => 'other', 'km' => 'ផ្សេងៗ', 'en' => 'Other'],
    ];
    $isEthnicMinority = (string) old('is_ethnic_minority', (int) (optional($extra)->is_ethnic_minority ?? 0)) === '1';
    $ethnicMinorityName = trim((string) old('ethnic_minority_name', optional($extra)->ethnic_minority_name));
    $ethnicMinorityOther = old('ethnic_minority_other', optional($extra)->ethnic_minority_other);
    $khGazetteerPath = public_path('module-assets/HumanResource/data/cambodia_gazetteer.json');
    $khGazetteerVersion = is_file($khGazetteerPath) ? filemtime($khGazetteerPath) : time();
    $khGazetteerUrl = asset('module-assets/HumanResource/data/cambodia_gazetteer.json') . '?v=' . $khGazetteerVersion;
    $nationalIdExpiryDate = old('national_id_expiry_date', optional($extra?->national_id_expiry_date)->format('Y-m-d'));
    $passportExpiryDate = old('passport_expiry_date', optional($extra?->passport_expiry_date)->format('Y-m-d'));
    $drivingLicenseExpiryDate = old('driving_license_expiry_date', optional($extra?->driving_license_expiry_date)->format('Y-m-d'));
@endphp

<div class="gov-section-card mb-3">
    <h6 class="gov-section-title">{{ localize('identity_and_nationality') }}</h6>
    <div class="row g-3">
        <div class="col-md-6">
            <div class="form-group mb-2 mx-0 row">
                <label for="salutation" class="col-lg-3 col-form-label ps-0">{{ localize('salutation') }}</label>
                <div class="col-lg-9">
                    <select name="salutation" id="salutation" class="form-select">
                        <option value="">{{ localize('select_one') }}</option>
                        @php
                            $salutations = [
                                ['value' => 'លោក', 'label' => localize('salutation_mr')],
                                ['value' => 'កញ្ញា', 'label' => localize('salutation_miss')],
                                ['value' => 'លោកស្រី', 'label' => localize('salutation_mrs')],
                                ['value' => 'ឯកឧត្តម', 'label' => localize('salutation_excellency')],
                                ['value' => 'លោកជំទាវ', 'label' => localize('salutation_lok_chumteav')],
                            ];
                        @endphp
                        @foreach ($salutations as $item)
                            <option value="{{ $item['value'] }}" {{ old('salutation', optional($extra)->salutation) === $item['value'] ? 'selected' : '' }}>{{ $item['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="form-group mb-2 mx-0 row">
                <label for="nationality" class="col-lg-3 col-form-label ps-0">{{ localize('nationality') }}</label>
                <div class="col-lg-9">
                    <input type="text" name="nationality" id="nationality" class="form-control"
                        value="{{ old('nationality', optional($emp)->nationality) }}" placeholder="{{ localize('nationality_placeholder') }}">
                </div>
            </div>

            <div class="form-group mb-2 mx-0 row">
                <label for="religion" class="col-lg-3 col-form-label ps-0">{{ localize('religion') }}</label>
                <div class="col-lg-9">
                    <input type="text" name="religion" id="religion" class="form-control"
                        value="{{ old('religion', optional($emp)->religion) }}" placeholder="{{ localize('religion') }}">
                    @if ($errors->has('religion'))
                        <div class="error text-danger text-start">{{ $errors->first('religion') }}</div>
                    @endif
                </div>
            </div>

            <div class="form-group mb-0 mx-0 row">
                <label for="ethnic_group" class="col-lg-3 col-form-label ps-0">{{ localize('ethnic_group') }}</label>
                <div class="col-lg-9">
                    <input type="text" name="ethnic_group" id="ethnic_group" class="form-control"
                        value="{{ old('ethnic_group', optional($emp)->ethnic_group) }}" placeholder="{{ localize('ethnic_group') }}">
                    @if ($errors->has('ethnic_group'))
                        <div class="error text-danger text-start">{{ $errors->first('ethnic_group') }}</div>
                    @endif
                </div>
            </div>

            <div class="form-group mt-2 mb-0 mx-0 row">
                <label for="is_ethnic_minority" class="col-lg-3 col-form-label ps-0">{{ $ethnicMinorityLabel }}</label>
                <div class="col-lg-9">
                    <input type="hidden" name="is_ethnic_minority" value="0">
                    <div class="d-flex align-items-center gap-3">
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" id="is_ethnic_minority" name="is_ethnic_minority" value="1"
                                {{ $isEthnicMinority ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_ethnic_minority">{{ $ethnicMinorityYesLabel }}</label>
                        </div>
                        <small class="text-muted">{{ $ethnicMinorityHint }}</small>
                    </div>
                    @if ($errors->has('is_ethnic_minority'))
                        <div class="error text-danger text-start">{{ $errors->first('is_ethnic_minority') }}</div>
                    @endif
                </div>
            </div>

            <div class="form-group mt-2 mb-0 mx-0 row" id="ethnic-minority-name-row" {{ $isEthnicMinority ? '' : 'hidden' }}>
                <label for="ethnic_minority_name" class="col-lg-3 col-form-label ps-0">{{ $ethnicMinorityDropdownLabel }}</label>
                <div class="col-lg-9">
                    <select name="ethnic_minority_name" id="ethnic_minority_name" class="form-select"
                        {{ $isEthnicMinority ? '' : 'disabled' }}>
                        <option value="">{{ localize('select_one') }}</option>
                        @foreach ($ethnicMinorityOptions as $option)
                            <option value="{{ $option['value'] }}"
                                {{ $ethnicMinorityName === $option['value'] ? 'selected' : '' }}>
                                {{ $isKhmerUi ? $option['km'] : $option['en'] }}
                            </option>
                        @endforeach
                    </select>
                    @if ($errors->has('ethnic_minority_name'))
                        <div class="error text-danger text-start">{{ $errors->first('ethnic_minority_name') }}</div>
                    @endif
                </div>
            </div>

            <div class="form-group mt-2 mb-0 mx-0 row" id="ethnic-minority-other-row"
                {{ $isEthnicMinority && mb_strtolower($ethnicMinorityName, 'UTF-8') === 'other' ? '' : 'hidden' }}>
                <label for="ethnic_minority_other" class="col-lg-3 col-form-label ps-0">{{ $ethnicMinorityOtherLabel }}</label>
                <div class="col-lg-9">
                    <input type="text" name="ethnic_minority_other" id="ethnic_minority_other" class="form-control"
                        value="{{ $ethnicMinorityOther }}"
                        {{ $isEthnicMinority && mb_strtolower($ethnicMinorityName, 'UTF-8') === 'other' ? '' : 'disabled' }}
                        placeholder="{{ $ethnicMinorityOtherLabel }}">
                    @if ($errors->has('ethnic_minority_other'))
                        <div class="error text-danger text-start">{{ $errors->first('ethnic_minority_other') }}</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group mb-2 mx-0 row">
                <label for="gender_id" class="col-lg-3 col-form-label ps-0">{{ localize('gender') }} <span class="text-danger">*</span></label>
                <div class="col-lg-9">
                    @php
                        $genderKhmerLabel = function ($rawName) {
                            $name = trim((string) $rawName);
                            $key = mb_strtolower($name);

                            if ($name === '') {
                                return '';
                            }

                            if (preg_match('/\p{Khmer}/u', $name)) {
                                return $name;
                            }

                            if (in_array($key, ['male', 'm', 'man', 'boy'], true)) {
                                return 'ប្រុស';
                            }

                            if (in_array($key, ['female', 'f', 'woman', 'girl'], true)) {
                                return 'ស្រី';
                            }

                            return $name;
                        };

                        $genderBinaryOptions = [];
                        foreach (($genders ?? []) as $genderOption) {
                            if (!is_object($genderOption)) {
                                continue;
                            }

                            $rawName = trim((string) ($genderOption->gender_name ?? ''));
                            $normalized = mb_strtolower($rawName);
                            $detectedType = null;

                            if (in_array($normalized, ['male', 'm', 'man', 'boy', 'ប្រុស'], true)) {
                                $detectedType = 'male';
                            } elseif (in_array($normalized, ['female', 'f', 'woman', 'girl', 'ស្រី'], true)) {
                                $detectedType = 'female';
                            }

                            if ($detectedType === null || isset($genderBinaryOptions[$detectedType])) {
                                continue;
                            }

                            $genderBinaryOptions[$detectedType] = [
                                'id' => $genderOption->id,
                                'type' => $detectedType,
                                'label' => $genderKhmerLabel($rawName),
                            ];
                        }

                        $genderBinaryOptions = array_values($genderBinaryOptions);
                    @endphp
                    <select name="gender_id" id="gender_id" class="form-select required-field" required>
                        <option value="">{{ localize('select_gender') }}</option>
                        @foreach ($genderBinaryOptions as $gender)
                            <option value="{{ $gender['id'] }}"
                                data-gender-key="{{ $gender['type'] }}"
                                {{ old('gender_id', optional($emp)->gender_id) == $gender['id'] ? 'selected' : '' }}>
                                {{ $gender['label'] }}
                            </option>
                        @endforeach
                    </select>
                    @if ($errors->has('gender_id'))
                        <div class="error text-danger text-start">{{ $errors->first('gender_id') }}</div>
                    @endif
                </div>
            </div>

            <div class="form-group mb-0 mx-0 row">
                <label for="date_of_birth" class="col-lg-3 col-form-label ps-0">{{ localize('date_of_birth') }} <span class="text-danger">*</span></label>
                <div class="col-lg-9">
                    <input type="date" name="date_of_birth" id="date_of_birth" class="form-control required-field"
                        value="{{ old('date_of_birth', optional($emp)->date_of_birth) }}" required>
                    @if ($errors->has('date_of_birth'))
                        <div class="error text-danger text-start">{{ $errors->first('date_of_birth') }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<div class="gov-section-card mb-3">
    <h6 class="gov-section-title">{{ localize('date_of_birth') }} & {{ localize('place_of_birth') }}</h6>
    <p class="text-muted small mb-2">{{ $birthPlaceHint }}</p>
    <div id="kh-birth-cascade"
        data-source-url="{{ $khGazetteerUrl }}"
        data-placeholder-province="{{ $selectProvinceLabel }}"
        data-placeholder-district="{{ $selectDistrictLabel }}"
        data-placeholder-commune="{{ $selectCommuneLabel }}"
        data-placeholder-village="{{ $selectVillageLabel }}">
        <div class="form-group mb-2 mx-0 row">
            <label for="birth_place_state" class="col-lg-3 col-form-label ps-0">{{ $placeOfBirthLabel }} - {{ $provinceLabel }}</label>
            <div class="col-lg-9">
                <select name="birth_place_state_id" id="birth_place_state" class="form-select required-field" data-initial="{{ $birthProvinceInitial }}" required>
                    <option value="">{{ $selectProvinceLabel }}</option>
                </select>
                @if ($errors->has('birth_place_state_id'))
                    <div class="error text-danger text-start">{{ $errors->first('birth_place_state_id') }}</div>
                @endif
            </div>
        </div>
        <div class="form-group mb-2 mx-0 row">
            <label for="birth_place_city" class="col-lg-3 col-form-label ps-0">{{ $placeOfBirthLabel }} - {{ $districtLabel }}</label>
            <div class="col-lg-9">
                <select name="birth_place_city_id" id="birth_place_city" class="form-select required-field" data-initial="{{ $birthDistrictInitial }}" required>
                    <option value="">{{ $selectDistrictLabel }}</option>
                </select>
                @if ($errors->has('birth_place_city_id'))
                    <div class="error text-danger text-start">{{ $errors->first('birth_place_city_id') }}</div>
                @endif
            </div>
        </div>
        <div class="form-group mb-2 mx-0 row">
            <label for="birth_place_commune" class="col-lg-3 col-form-label ps-0">{{ $placeOfBirthLabel }} - {{ $communeLabel }}</label>
            <div class="col-lg-9">
                <select name="birth_place_commune_id" id="birth_place_commune" class="form-select required-field" data-initial="{{ $birthCommuneInitial }}" required>
                    <option value="">{{ $selectCommuneLabel }}</option>
                </select>
                @if ($errors->has('birth_place_commune_id'))
                    <div class="error text-danger text-start">{{ $errors->first('birth_place_commune_id') }}</div>
                @endif
            </div>
        </div>
        <div class="form-group mb-2 mx-0 row">
            <label for="birth_place_village" class="col-lg-3 col-form-label ps-0">{{ $placeOfBirthLabel }} - {{ $villageLabel }}</label>
            <div class="col-lg-9">
                <select name="birth_place_village_id" id="birth_place_village" class="form-select required-field" data-initial="{{ $birthVillageInitial }}" required>
                    <option value="">{{ $selectVillageLabel }}</option>
                </select>
                @if ($errors->has('birth_place_village_id'))
                    <div class="error text-danger text-start">{{ $errors->first('birth_place_village_id') }}</div>
                @endif
            </div>
        </div>
    </div>

    <input type="hidden" name="birth_place_state" id="birth_place_state_name" value="{{ $birthProvinceName }}">
    <input type="hidden" name="birth_place_city" id="birth_place_city_name" value="{{ $birthDistrictName }}">
    <input type="hidden" name="birth_place_commune" id="birth_place_commune_name" value="{{ $birthCommuneName }}">
    <input type="hidden" name="birth_place_village" id="birth_place_village_name" value="{{ $birthVillageName }}">

</div>

<div class="gov-section-card mb-3">
    <h6 class="gov-section-title">{{ localize('current_address') }}</h6>
    <div id="kh-address-cascade"
        data-source-url="{{ $khGazetteerUrl }}"
        data-placeholder-province="{{ $selectProvinceLabel }}"
        data-placeholder-district="{{ $selectDistrictLabel }}"
        data-placeholder-commune="{{ $selectCommuneLabel }}"
        data-placeholder-village="{{ $selectVillageLabel }}">
        <div class="form-group mb-2 mx-0 row">
            <label for="present_address_state" class="col-lg-3 col-form-label ps-0">{{ $provinceLabel }}</label>
            <div class="col-lg-9">
                <select name="present_address_state_id" id="present_address_state" class="form-select required-field"
                    data-initial="{{ $currentStateInitial }}">
                    <option value="">{{ $selectProvinceLabel }}</option>
                </select>
                @if ($errors->has('present_address_state_id'))
                    <div class="error text-danger text-start">{{ $errors->first('present_address_state_id') }}</div>
                @endif
            </div>
        </div>
        <div class="form-group mb-2 mx-0 row">
            <label for="present_address_city" class="col-lg-3 col-form-label ps-0">{{ $districtLabel }}</label>
            <div class="col-lg-9">
                <select name="present_address_city_id" id="present_address_city" class="form-select required-field"
                    data-initial="{{ $currentCityInitial }}">
                    <option value="">{{ $selectDistrictLabel }}</option>
                </select>
                @if ($errors->has('present_address_city_id'))
                    <div class="error text-danger text-start">{{ $errors->first('present_address_city_id') }}</div>
                @endif
            </div>
        </div>
        <div class="form-group mb-2 mx-0 row">
            <label for="present_address_post_code" class="col-lg-3 col-form-label ps-0">{{ $communeLabel }}</label>
            <div class="col-lg-9">
                <select name="present_address_commune_id" id="present_address_post_code" class="form-select required-field"
                    data-initial="{{ $currentCommuneInitial }}">
                    <option value="">{{ $selectCommuneLabel }}</option>
                </select>
                @if ($errors->has('present_address_commune_id'))
                    <div class="error text-danger text-start">{{ $errors->first('present_address_commune_id') }}</div>
                @endif
            </div>
        </div>
        <div class="form-group mb-2 mx-0 row">
            <label for="present_address_address" class="col-lg-3 col-form-label ps-0">{{ $villageLabel }}</label>
            <div class="col-lg-9">
                <select name="present_address_village_id" id="present_address_address" class="form-select required-field"
                    data-initial="{{ $currentVillageInitial }}">
                    <option value="">{{ $selectVillageLabel }}</option>
                </select>
                @if ($errors->has('present_address_village_id'))
                    <div class="error text-danger text-start">{{ $errors->first('present_address_village_id') }}</div>
                @endif
            </div>
        </div>
    </div>
    <input type="hidden" name="present_address_state" id="present_address_state_name" value="{{ $currentStateName }}">
    <input type="hidden" name="present_address_city" id="present_address_city_name" value="{{ $currentCityName }}">
    <input type="hidden" name="present_address_post_code" id="present_address_commune_name" value="{{ $currentCommuneName }}">
    <input type="hidden" name="present_address_address" id="present_address_village_name" value="{{ $currentVillageName }}">
    <input type="hidden" name="present_address" id="present_address" value="{{ old('present_address', optional($emp)->present_address) }}">
</div>

<div class="gov-section-card mb-3">
    <h6 class="gov-section-title">{{ $uniformSizeGroupTitle }}</h6>
    <div class="row g-3">
        <div class="col-md-4">
            <label for="uniform_shirt_size" class="form-label">{{ $shirtSizeLabel }}</label>
            <input type="text" name="uniform_shirt_size" id="uniform_shirt_size" class="form-control"
                value="{{ old('uniform_shirt_size', optional($emp)->uniform_shirt_size) }}"
                placeholder="{{ $uniformSizePlaceholder }}">
            @if ($errors->has('uniform_shirt_size'))
                <div class="error text-danger text-start">{{ $errors->first('uniform_shirt_size') }}</div>
            @endif
        </div>
        <div class="col-md-4">
            <label for="uniform_pants_size" class="form-label">{{ $pantsSizeLabel }}</label>
            <input type="text" name="uniform_pants_size" id="uniform_pants_size" class="form-control"
                value="{{ old('uniform_pants_size', optional($emp)->uniform_pants_size) }}"
                placeholder="{{ $uniformSizePlaceholder }}">
            @if ($errors->has('uniform_pants_size'))
                <div class="error text-danger text-start">{{ $errors->first('uniform_pants_size') }}</div>
            @endif
        </div>
        <div class="col-md-4">
            <label for="uniform_shoe_size" class="form-label">{{ $shoeSizeLabel }}</label>
            <input type="text" name="uniform_shoe_size" id="uniform_shoe_size" class="form-control"
                value="{{ old('uniform_shoe_size', optional($emp)->uniform_shoe_size) }}"
                placeholder="{{ $uniformSizePlaceholder }}">
            @if ($errors->has('uniform_shoe_size'))
                <div class="error text-danger text-start">{{ $errors->first('uniform_shoe_size') }}</div>
            @endif
        </div>
    </div>
</div>

<div class="gov-section-card mb-3">
    <h6 class="gov-section-title">{{ localize('personal_documents_and_validity') }}</h6>
    <p class="text-muted small mb-3">{{ localize('validity_date_hint') }}</p>

    <div class="row g-3">
        <div class="col-md-6">
            <label for="national_id_no" class="form-label">{{ localize('national_id_no') }}</label>
            <input type="text" name="national_id_no" id="national_id_no" class="form-control"
                value="{{ old('national_id_no', optional($emp)->national_id_no) }}"
                placeholder="{{ localize('enter_national_id_no') }}">
            @if ($errors->has('national_id_no'))
                <div class="error text-danger text-start">{{ $errors->first('national_id_no') }}</div>
            @endif
        </div>
        <div class="col-md-6">
            <label for="national_id_expiry_date" class="form-label">{{ localize('national_id_expiry_date') }}</label>
            <input type="date" name="national_id_expiry_date" id="national_id_expiry_date" class="form-control"
                value="{{ $nationalIdExpiryDate }}">
            @if ($errors->has('national_id_expiry_date'))
                <div class="error text-danger text-start">{{ $errors->first('national_id_expiry_date') }}</div>
            @endif
        </div>

        <div class="col-md-6">
            <label for="passport_no" class="form-label">{{ localize('passport_no') }}</label>
            <input type="text" name="passport_no" id="passport_no" class="form-control"
                value="{{ old('passport_no', optional($emp)->passport_no) }}"
                placeholder="{{ localize('enter_passport_no') }}">
            @if ($errors->has('passport_no'))
                <div class="error text-danger text-start">{{ $errors->first('passport_no') }}</div>
            @endif
        </div>
        <div class="col-md-6">
            <label for="passport_expiry_date" class="form-label">{{ localize('passport_expiry_date') }}</label>
            <input type="date" name="passport_expiry_date" id="passport_expiry_date" class="form-control"
                value="{{ $passportExpiryDate }}">
            @if ($errors->has('passport_expiry_date'))
                <div class="error text-danger text-start">{{ $errors->first('passport_expiry_date') }}</div>
            @endif
        </div>

        <div class="col-md-6">
            <label for="driving_license_no" class="form-label">{{ localize('driving_license_no') }}</label>
            <input type="text" name="driving_license_no" id="driving_license_no" class="form-control"
                value="{{ old('driving_license_no', optional($emp)->driving_license_no) }}"
                placeholder="{{ localize('enter_driving_license_no') }}">
            @if ($errors->has('driving_license_no'))
                <div class="error text-danger text-start">{{ $errors->first('driving_license_no') }}</div>
            @endif
        </div>
        <div class="col-md-6">
            <label for="driving_license_expiry_date" class="form-label">{{ localize('driving_license_expiry_date') }}</label>
            <input type="date" name="driving_license_expiry_date" id="driving_license_expiry_date" class="form-control"
                value="{{ $drivingLicenseExpiryDate }}">
            @if ($errors->has('driving_license_expiry_date'))
                <div class="error text-danger text-start">{{ $errors->first('driving_license_expiry_date') }}</div>
            @endif
        </div>
    </div>
</div>
