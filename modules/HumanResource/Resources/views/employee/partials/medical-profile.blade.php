@php
    $emp = $employee ?? null;
    $isKhmerUi = app()->getLocale() === 'km';

    $basicHealthTitle = $isKhmerUi ? '១. ព័ត៌មានមូលដ្ឋានសុខភាព' : '1. Basic health information';
    $medicineAllergyTitle = $isKhmerUi ? '២. ព័ត៌មានថ្នាំ និងអាលែកស៊ី' : '2. Medication and allergy';
    $vaccineTitle = $isKhmerUi ? '៣. វ៉ាក់សាំង និងការពារ' : '3. Vaccination and prevention';

    $bloodGroupLabel = $isKhmerUi ? 'ក្រុមឈាម' : 'Blood group';
    $healthConditionLabel = $isKhmerUi ? 'ស្ថានភាពសុខភាពទូទៅ' : 'General health condition';
    $chronicDiseaseLabel = $isKhmerUi ? 'ប្រវត្តិជំងឺរ៉ាំរ៉ៃ (លើសសម្ពាធឈាម ទឹកនោមផ្អែម...)' : 'Chronic disease history (hypertension, diabetes, etc.)';
    $severeDiseaseLabel = $isKhmerUi ? 'ប្រវត្តិជំងឺធ្ងន់ធ្ងរ' : 'Severe disease history';
    $surgeryHistoryLabel = $isKhmerUi ? 'ប្រវត្តិវះកាត់' : 'Surgery history';
    $regularMedicationLabel = $isKhmerUi ? 'ថ្នាំប្រើជាប្រចាំ' : 'Regular medication';
    $allergyReactionLabel = $isKhmerUi ? 'ប្រតិកម្មអាលែកស៊ី (ថ្នាំ អាហារ ឬផ្សេងៗ)' : 'Allergy reaction (medicine, food, others)';
    $disabilityLabel = $isKhmerUi ? 'ពិការភាព' : 'Disability';
    $disabilityDescriptionLabel = $isKhmerUi ? 'បរិយាយពិការភាព' : 'Disability description';
    $hasDisabilityLabel = $isKhmerUi ? 'មាន' : 'Yes';
    $noDisabilityLabel = $isKhmerUi ? 'មិនមាន' : 'No';
    $vaccineNameLabel = $isKhmerUi ? 'ឈ្មោះវ៉ាក់សាំង' : 'Vaccine name';
    $vaccineProtectionLabel = $isKhmerUi ? 'ការពារជំងឺ' : 'Disease protection';
    $vaccinationDateLabel = $isKhmerUi ? 'ថ្ងៃខែឆ្នាំចាក់' : 'Vaccination date';
    $vaccinationPlaceLabel = $isKhmerUi ? 'ទីកន្លែងចាក់' : 'Vaccination place';

    $bloodGroupOptions = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    $unknownBloodGroupValue = 'unknown';
    $unknownBloodGroupLabel = $isKhmerUi ? 'មិនទាន់ដឹង' : 'Unknown';
    $selectedBloodGroup = old('blood_group', optional($emp)->blood_group);
    $selectedIsDisable = old('is_disable', optional($emp)->is_disable);
    $vaccinationRows = old('vaccination_records');
    if (!is_array($vaccinationRows)) {
        $vaccinationRows = [];
        if ($emp && method_exists($emp, 'vaccinations')) {
            $vaccinationRows = $emp->vaccinations->map(function ($row) {
                return [
                    'vaccine_name' => $row->vaccine_name,
                    'vaccine_protection' => $row->vaccine_protection,
                    'vaccination_date' => $row->vaccination_date,
                    'vaccination_place' => $row->vaccination_place,
                ];
            })->toArray();
        }

        if (empty($vaccinationRows) && $emp) {
            if (!empty($emp->vaccine_name) || !empty($emp->vaccine_protection) || !empty($emp->vaccination_date) || !empty($emp->vaccination_place) || !empty($emp->covid_vaccine_name) || !empty($emp->covid_vaccine_date)) {
                $vaccinationRows[] = [
                    'vaccine_name' => $emp->vaccine_name ?: $emp->covid_vaccine_name,
                    'vaccine_protection' => $emp->vaccine_protection,
                    'vaccination_date' => $emp->vaccination_date ?: $emp->covid_vaccine_date,
                    'vaccination_place' => $emp->vaccination_place,
                ];
            }
        }
    }
    if (empty($vaccinationRows)) {
        $vaccinationRows = [[]];
    }
@endphp

<div class="gov-section-card mb-3">
    <h6 class="gov-section-title">{{ $basicHealthTitle }}</h6>
    <div class="row g-3">
        <div class="col-md-6">
            <label for="blood_group" class="form-label">{{ $bloodGroupLabel }}</label>
            <select name="blood_group" id="blood_group" class="form-select">
                <option value="">{{ $isKhmerUi ? 'សូមជ្រើសរើសក្រុមឈាម' : 'Select blood group' }}</option>
                @foreach ($bloodGroupOptions as $bloodGroupOption)
                    <option value="{{ $bloodGroupOption }}" {{ (string) $selectedBloodGroup === (string) $bloodGroupOption ? 'selected' : '' }}>
                        {{ $bloodGroupOption }}
                    </option>
                @endforeach
                <option value="{{ $unknownBloodGroupValue }}" {{ (string) $selectedBloodGroup === $unknownBloodGroupValue ? 'selected' : '' }}>
                    {{ $unknownBloodGroupLabel }}
                </option>
            </select>
            @if ($errors->has('blood_group'))
                <div class="error text-danger text-start">{{ $errors->first('blood_group') }}</div>
            @endif
        </div>
        <div class="col-md-6">
            <label for="health_condition" class="form-label">{{ $healthConditionLabel }}</label>
            <input type="text" name="health_condition" id="health_condition" class="form-control"
                value="{{ old('health_condition', optional($emp)->health_condition) }}">
            @if ($errors->has('health_condition'))
                <div class="error text-danger text-start">{{ $errors->first('health_condition') }}</div>
            @endif
        </div>
        <div class="col-md-6">
            <label for="chronic_disease_history" class="form-label">{{ $chronicDiseaseLabel }}</label>
            <textarea name="chronic_disease_history" id="chronic_disease_history" class="form-control" rows="3">{{ old('chronic_disease_history', optional($emp)->chronic_disease_history) }}</textarea>
            @if ($errors->has('chronic_disease_history'))
                <div class="error text-danger text-start">{{ $errors->first('chronic_disease_history') }}</div>
            @endif
        </div>
        <div class="col-md-6">
            <label for="severe_disease_history" class="form-label">{{ $severeDiseaseLabel }}</label>
            <textarea name="severe_disease_history" id="severe_disease_history" class="form-control" rows="3">{{ old('severe_disease_history', optional($emp)->severe_disease_history) }}</textarea>
            @if ($errors->has('severe_disease_history'))
                <div class="error text-danger text-start">{{ $errors->first('severe_disease_history') }}</div>
            @endif
        </div>
        <div class="col-md-12">
            <label for="surgery_history" class="form-label">{{ $surgeryHistoryLabel }}</label>
            <textarea name="surgery_history" id="surgery_history" class="form-control" rows="3">{{ old('surgery_history', optional($emp)->surgery_history) }}</textarea>
            @if ($errors->has('surgery_history'))
                <div class="error text-danger text-start">{{ $errors->first('surgery_history') }}</div>
            @endif
        </div>
        <div class="col-md-6">
            <label for="medical_is_disable" class="form-label">{{ $disabilityLabel }}</label>
            <select name="is_disable" id="medical_is_disable" class="form-select">
                <option value="">{{ $isKhmerUi ? 'សូមជ្រើសរើស' : 'Please select' }}</option>
                <option value="1" {{ (string) $selectedIsDisable === '1' ? 'selected' : '' }}>{{ $hasDisabilityLabel }}</option>
                <option value="0" {{ (string) $selectedIsDisable === '0' ? 'selected' : '' }}>{{ $noDisabilityLabel }}</option>
            </select>
            @if ($errors->has('is_disable'))
                <div class="error text-danger text-start">{{ $errors->first('is_disable') }}</div>
            @endif
        </div>
        <div class="col-md-6" id="medical-disability-description-wrapper">
            <label for="medical_disabilities_desc" class="form-label">{{ $disabilityDescriptionLabel }}</label>
            <input type="text" name="disabilities_desc" id="medical_disabilities_desc" class="form-control"
                value="{{ old('disabilities_desc', optional($emp)->disabilities_desc) }}">
            @if ($errors->has('disabilities_desc'))
                <div class="error text-danger text-start">{{ $errors->first('disabilities_desc') }}</div>
            @endif
        </div>
    </div>
</div>

<div class="gov-section-card mb-3">
    <h6 class="gov-section-title">{{ $medicineAllergyTitle }}</h6>
    <div class="row g-3">
        <div class="col-md-6">
            <label for="regular_medication" class="form-label">{{ $regularMedicationLabel }}</label>
            <textarea name="regular_medication" id="regular_medication" class="form-control" rows="3">{{ old('regular_medication', optional($emp)->regular_medication) }}</textarea>
            @if ($errors->has('regular_medication'))
                <div class="error text-danger text-start">{{ $errors->first('regular_medication') }}</div>
            @endif
        </div>
        <div class="col-md-6">
            <label for="allergy_reaction" class="form-label">{{ $allergyReactionLabel }}</label>
            <textarea name="allergy_reaction" id="allergy_reaction" class="form-control" rows="3">{{ old('allergy_reaction', optional($emp)->allergy_reaction) }}</textarea>
            @if ($errors->has('allergy_reaction'))
                <div class="error text-danger text-start">{{ $errors->first('allergy_reaction') }}</div>
            @endif
        </div>
    </div>
</div>

<div class="gov-section-card mb-3">
    <h6 class="gov-section-title">{{ $vaccineTitle }}</h6>
    <div class="table-responsive mb-2">
        <table class="table table-bordered" id="vaccination-records-table">
            <thead>
                <tr>
                    <th>{{ $vaccineNameLabel }}</th>
                    <th>{{ $vaccineProtectionLabel }}</th>
                    <th>{{ $vaccinationDateLabel }}</th>
                    <th>{{ $vaccinationPlaceLabel }}</th>
                    <th width="80">{{ localize('action') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($vaccinationRows as $idx => $row)
                    <tr>
                        <td><input type="text" name="vaccination_records[{{ $idx }}][vaccine_name]" class="form-control" value="{{ $row['vaccine_name'] ?? '' }}"></td>
                        <td><input type="text" name="vaccination_records[{{ $idx }}][vaccine_protection]" class="form-control" value="{{ $row['vaccine_protection'] ?? '' }}"></td>
                        <td><input type="date" name="vaccination_records[{{ $idx }}][vaccination_date]" class="form-control" value="{{ $row['vaccination_date'] ?? '' }}"></td>
                        <td><input type="text" name="vaccination_records[{{ $idx }}][vaccination_place]" class="form-control" value="{{ $row['vaccination_place'] ?? '' }}"></td>
                        <td><button type="button" class="btn btn-sm btn-danger repeater-remove">{{ localize('delete') }}</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <button type="button" class="btn btn-sm btn-primary repeater-add" data-target="#vaccination-records-table" data-repeater="vaccination_records">
        + {{ localize('add_more') }}
    </button>

    @if ($errors->has('vaccination_records'))
        <div class="error text-danger text-start mt-2">{{ $errors->first('vaccination_records') }}</div>
    @endif
    @if ($errors->has('vaccination_records.*.vaccine_name'))
        <div class="error text-danger text-start mt-2">{{ $errors->first('vaccination_records.*.vaccine_name') }}</div>
    @endif
    @if ($errors->has('vaccination_records.*.vaccine_protection'))
        <div class="error text-danger text-start">{{ $errors->first('vaccination_records.*.vaccine_protection') }}</div>
    @endif
    @if ($errors->has('vaccination_records.*.vaccination_date'))
        <div class="error text-danger text-start">{{ $errors->first('vaccination_records.*.vaccination_date') }}</div>
    @endif
    @if ($errors->has('vaccination_records.*.vaccination_place'))
        <div class="error text-danger text-start">{{ $errors->first('vaccination_records.*.vaccination_place') }}</div>
    @endif
</div>
