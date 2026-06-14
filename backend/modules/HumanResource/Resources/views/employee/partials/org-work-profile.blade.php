@php
    $emp = $employee ?? null;
    $extra = $emp?->profileExtra;

    $skills = collect($professional_skills ?? []);
    $payLevels = collect($pay_levels ?? []);

    $toDateInputValue = static function ($value): ?string {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->format('Y-m-d');
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return null;
            }

            try {
                return \Carbon\Carbon::parse($trimmed)->format('Y-m-d');
            } catch (\Throwable $e) {
                return $trimmed;
            }
        }

        return null;
    };

    $activePayLevelName = trim((string) ($emp?->currentPayGradeHistory?->payLevel?->level_name_km ?? ''));
    $activePayLevelCode = trim((string) ($emp?->currentPayGradeHistory?->payLevel?->level_code ?? ''));
    $latestPayLevelName = trim((string) ($emp?->latestPayGradeHistory?->payLevel?->level_name_km ?? ''));
    $latestPayLevelCode = trim((string) ($emp?->latestPayGradeHistory?->payLevel?->level_code ?? ''));
    $activePayLevelStartDate = $toDateInputValue($emp?->currentPayGradeHistory?->start_date);
    $latestPayLevelStartDate = $toDateInputValue($emp?->latestPayGradeHistory?->start_date);

    $normalizePayLevelKey = static function (?string $value): string {
        $normalized = strtoupper(trim((string) $value));
        $normalized = preg_replace('/\s+/u', '', $normalized) ?? $normalized;

        return $normalized;
    };

    $currentSkill = old('skill_name', $emp?->skill_name ?: $extra?->current_work_skill);
    $currentPayLevel = old(
        'employee_grade',
        $emp?->employee_grade
            ?: $extra?->current_salary_type
            ?: $activePayLevelName
            ?: $activePayLevelCode
            ?: $latestPayLevelName
            ?: $latestPayLevelCode
    );
    $currentTechnicalRoleType = old('technical_role_type', $extra?->technical_role_type);
    $currentFrameworkType = old('framework_type', $extra?->framework_type);
    $currentLastPromotionDate = old(
        'promotion_date',
        $activePayLevelStartDate
            ?: $latestPayLevelStartDate
            ?: $toDateInputValue($emp?->promotion_date)
            ?: ''
    );
    $employeeTypes = collect($employee_types ?? []);
    $selectedEmployeeTypeId = old('employee_type_id', (string) ($emp?->employee_type_id ?? ''));
    $isCreateEmployeeForm = $emp === null;

    if ((string) $currentTechnicalRoleType === 'technical') {
        $currentTechnicalRoleType = 'health_technical_officer';
    } elseif ((string) $currentTechnicalRoleType === 'administrative') {
        $currentTechnicalRoleType = 'non_health_technical_officer';
    }

    $currentPositionLabel = $emp?->position?->position_name_km ?: ($emp?->position?->position_name ?: '');
    $currentPositionStartDate = old('current_position_start_date', $toDateInputValue($extra?->current_position_start_date));

    if (empty($currentPositionStartDate) && $emp) {
        $postings = $emp->unitPostings()
            ->whereNotNull('position_id')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get(['position_id', 'start_date']);

        $currentPositionId = (int) ($emp->position_id ?? 0);
        $resolvedRoleStartDate = null;

        if ($currentPositionId > 0) {
            foreach ($postings as $posting) {
                if ((int) ($posting->position_id ?? 0) !== $currentPositionId) {
                    break;
                }

                $resolvedRoleStartDate = $toDateInputValue($posting->start_date) ?: $resolvedRoleStartDate;
            }
        }

        $currentPositionStartDate = $resolvedRoleStartDate ?: $toDateInputValue($emp->service_start_date ?: $emp->joining_date);
    }

    $payLevelKmByCode = [];
    $payLevelKmByName = [];
    foreach ($payLevels as $level) {
        $levelCode = trim((string) ($level->level_code ?? ''));
        $levelNameKm = trim((string) ($level->level_name_km ?? ''));
        $levelNameKm = preg_replace('/\.\.+/u', '.', $levelNameKm) ?? $levelNameKm;

        if ($levelNameKm !== '') {
            $payLevelKmByName[$normalizePayLevelKey($levelNameKm)] = $levelNameKm;
        }

        if ($levelCode !== '' && $levelNameKm !== '') {
            $payLevelKmByCode[$normalizePayLevelKey($levelCode)] = $levelNameKm;
        }
    }

    $currentPayLevelKey = $normalizePayLevelKey($currentPayLevel);
    if ($currentPayLevelKey !== '') {
        if (isset($payLevelKmByCode[$currentPayLevelKey])) {
            $currentPayLevel = $payLevelKmByCode[$currentPayLevelKey];
        } elseif (isset($payLevelKmByName[$currentPayLevelKey])) {
            $currentPayLevel = $payLevelKmByName[$currentPayLevelKey];
        }
    }
@endphp

<div class="gov-section-card mb-3">
    <h6 class="gov-section-title">{{ localize('current_work_information') }}</h6>
    <div class="form-group mb-2 mx-0 row">
        <label for="employee_type_id" class="col-lg-3 col-form-label ps-0">
            {{ localize('employee_type') }}
            @if ($isCreateEmployeeForm)
                <span class="text-danger">*</span>
            @endif
        </label>
        <div class="col-lg-9">
            <select name="employee_type_id" id="employee_type_id"
                class="form-select {{ $isCreateEmployeeForm ? 'required-field' : '' }}"
                {{ $isCreateEmployeeForm ? 'required' : '' }}>
                <option value="">{{ localize('select_employee_type') }}</option>
                @foreach ($employeeTypes as $employeeType)
                    @php
                        $employeeTypeName = strtolower(trim((string) $employeeType->name));
                        $employeeTypeLabel = $employeeTypeLabelMap[$employeeTypeName] ?? $employeeType->name;
                        $isCadreEmployeeType = $employeeTypeName === 'full time' || str_contains($employeeTypeName, 'civil');
                    @endphp
                    <option value="{{ $employeeType->id }}" data-type="{{ $employeeTypeName }}" data-cadre="{{ $isCadreEmployeeType ? '1' : '0' }}"
                        {{ (string) $employeeType->id === (string) $selectedEmployeeTypeId ? 'selected' : '' }}>
                        {{ $employeeTypeLabel }}
                    </option>
                @endforeach
            </select>
            @if ($errors->has('employee_type_id'))
                <div class="error text-danger text-start">{{ $errors->first('employee_type_id') }}</div>
            @endif
        </div>
    </div>

    <div class="form-group mb-2 mx-0 row">
        <label for="skill_name" class="col-lg-3 col-form-label ps-0">{{ localize('specialization') }}</label>
        <div class="col-lg-9">
            @if ($skills->isNotEmpty())
                <select name="skill_name" id="skill_name" class="form-select">
                    <option value="">{{ localize('select_specialization') }}</option>
                    @php
                        $skillValues = [];
                    @endphp
                    @foreach ($skills as $skill)
                        @php
                            $skillLabel = trim((string) ($skill->name_km ?: $skill->name_en));
                            $skillValues[] = $skillLabel;
                        @endphp
                        <option value="{{ $skillLabel }}" {{ (string) $currentSkill === (string) $skillLabel ? 'selected' : '' }}>
                            {{ $skillLabel }}
                        </option>
                    @endforeach
                    @if (!empty($currentSkill) && !in_array((string) $currentSkill, $skillValues, true))
                        <option value="{{ $currentSkill }}" selected>{{ $currentSkill }}</option>
                    @endif
                </select>
            @else
                <input type="text" name="skill_name" id="skill_name" class="form-control"
                    value="{{ $currentSkill }}" placeholder="{{ localize('enter_specialization') }}">
            @endif
        </div>
    </div>

    <div class="form-group mb-2 mx-0 row">
        <label for="employee_grade" class="col-lg-3 col-form-label ps-0">{{ app()->getLocale() === 'km' ? 'ក្របខណ្ឌឋានន្តរស័ក្កិ និងថ្នាក់' : 'Pay framework, rank, and grade' }}</label>
        <div class="col-lg-9">
            @if ($payLevels->isNotEmpty())
                <select name="employee_grade" id="employee_grade" class="form-select">
                    <option value="">{{ localize('select_pay_level') }}</option>
                    @php
                        $payLevelValues = [];
                    @endphp
                    @foreach ($payLevels as $level)
                        @php
                            $levelNameKm = trim((string) ($level->level_name_km ?? ''));
                            $levelNameKm = preg_replace('/\.\.+/u', '.', $levelNameKm) ?? $levelNameKm;
                            $levelValue = $levelNameKm !== '' ? $levelNameKm : trim((string) $level->level_code);
                            $payLevelValues[] = $levelValue;
                        @endphp
                        <option value="{{ $levelValue }}" {{ (string) $currentPayLevel === (string) $levelValue ? 'selected' : '' }}>
                            {{ $levelValue }}
                        </option>
                    @endforeach
                    @if (!empty($currentPayLevel) && !in_array((string) $currentPayLevel, $payLevelValues, true))
                        <option value="{{ $currentPayLevel }}" selected>{{ $currentPayLevel }}</option>
                    @endif
                </select>
            @else
                <input type="text" name="employee_grade" id="employee_grade" class="form-control"
                    value="{{ $currentPayLevel }}" placeholder="{{ localize('enter_pay_level') }}">
            @endif
        </div>
    </div>

    <div class="form-group mb-2 mx-0 row">
        <label for="current_role_display" class="col-lg-3 col-form-label ps-0">{{ localize('current_role') }}</label>
        <div class="col-lg-9">
            <input type="text" id="current_role_display" class="form-control"
                value="{{ old('current_role_display', $currentPositionLabel) }}" readonly>
        </div>
    </div>

    <div class="form-group mb-2 mx-0 row">
        <label for="current_position_start_date" class="col-lg-3 col-form-label ps-0">{{ localize('role_start_date') }}</label>
        <div class="col-lg-9">
            <input type="date" name="current_position_start_date" id="current_position_start_date" class="form-control"
                value="{{ $currentPositionStartDate }}">
        </div>
    </div>

    <div class="form-group mb-2 mx-0 row">
        <label for="current_position_document_number" class="col-lg-3 col-form-label ps-0">{{ localize('role_document_number') }}</label>
        <div class="col-lg-9">
            <input type="text" name="current_position_document_number" id="current_position_document_number"
                class="form-control"
                value="{{ old('current_position_document_number', $extra?->current_position_document_number) }}"
                placeholder="{{ localize('role_document_number_hint') }}">
        </div>
    </div>

    <div class="form-group mb-2 mx-0 row">
        <label for="current_position_document_date" class="col-lg-3 col-form-label ps-0">{{ localize('role_document_date') }}</label>
        <div class="col-lg-9">
            <input type="date" name="current_position_document_date" id="current_position_document_date"
                class="form-control"
                value="{{ old('current_position_document_date', $toDateInputValue($extra?->current_position_document_date)) }}">
        </div>
    </div>

    <div class="form-group mb-2 mx-0 row">
        <label for="promotion_date" class="col-lg-3 col-form-label ps-0">{{ app()->getLocale() === 'km' ? 'ថ្ងៃឡើងថ្នាក់ចុងក្រោយ' : 'Last promotion date' }}</label>
        <div class="col-lg-9">
            <input type="date" name="promotion_date" id="promotion_date" class="form-control"
                value="{{ $currentLastPromotionDate }}">
        </div>
    </div>

    <div class="form-group mb-2 mx-0 row">
        <label for="technical_role_type" class="col-lg-3 col-form-label ps-0">{{ localize('technical_role_type') }}</label>
        <div class="col-lg-9">
            <select name="technical_role_type" id="technical_role_type" class="form-select">
                <option value="">{{ localize('select_one') }}</option>
                <option value="health_technical_officer" {{ (string) $currentTechnicalRoleType === 'health_technical_officer' ? 'selected' : '' }}>{{ localize('technical_role_health') }}</option>
                <option value="non_health_technical_officer" {{ (string) $currentTechnicalRoleType === 'non_health_technical_officer' ? 'selected' : '' }}>{{ localize('technical_role_non_health') }}</option>
            </select>
        </div>
    </div>

    <div class="form-group mb-0 mx-0 row">
        <label for="framework_type" class="col-lg-3 col-form-label ps-0">{{ localize('framework_type') }}</label>
        <div class="col-lg-9">
            <select name="framework_type" id="framework_type" class="form-select">
                <option value="">{{ localize('select_one') }}</option>
                <option value="technical" {{ (string) $currentFrameworkType === 'technical' ? 'selected' : '' }}>{{ localize('framework_technical') }}</option>
                <option value="health" {{ (string) $currentFrameworkType === 'health' ? 'selected' : '' }}>{{ localize('framework_health') }}</option>
                <option value="administrative" {{ (string) $currentFrameworkType === 'administrative' ? 'selected' : '' }}>{{ localize('framework_administrative') }}</option>
                <option value="unknown" {{ (string) $currentFrameworkType === 'unknown' ? 'selected' : '' }}>{{ localize('framework_unknown') }}</option>
            </select>
        </div>
    </div>
</div>

<div class="gov-section-card mb-3">
    <h6 class="gov-section-title">{{ localize('professional_registration_group') }}</h6>
    <p class="text-muted small mb-3">{{ localize('professional_registration_note') }}</p>

    <div class="form-group mb-2 mx-0 row">
        <label for="registration_date" class="col-lg-3 col-form-label ps-0">{{ localize('registration_date') }}</label>
        <div class="col-lg-9">
            <input type="date" name="registration_date" id="registration_date" class="form-control"
                value="{{ old('registration_date', $toDateInputValue($extra?->registration_date)) }}">
        </div>
    </div>

    <div class="form-group mb-2 mx-0 row">
        <label for="professional_registration_no" class="col-lg-3 col-form-label ps-0">{{ localize('professional_registration_no') }}</label>
        <div class="col-lg-9">
            <input type="text" name="professional_registration_no" id="professional_registration_no" class="form-control"
                value="{{ old('professional_registration_no', $extra?->professional_registration_no) }}">
        </div>
    </div>

    <div class="form-group mb-2 mx-0 row">
        <label for="institution_contact_no" class="col-lg-3 col-form-label ps-0">{{ localize('institution_contact_no') }}</label>
        <div class="col-lg-9">
            <input type="text" name="institution_contact_no" id="institution_contact_no" class="form-control"
                value="{{ old('institution_contact_no', $extra?->institution_contact_no) }}">
        </div>
    </div>

    <div class="form-group mb-0 mx-0 row">
        <label for="institution_email" class="col-lg-3 col-form-label ps-0">{{ localize('institution_email') }}</label>
        <div class="col-lg-9">
            <input type="email" name="institution_email" id="institution_email" class="form-control"
                value="{{ old('institution_email', $extra?->institution_email) }}">
        </div>
    </div>
</div>

@push('js')
<script>
    (function () {
        var positionSelect = document.querySelector('select[name="position_id"]');
        var serviceStartInput = document.getElementById('service_start_date');
        var roleDisplay = document.getElementById('current_role_display');
        var roleStartInput = document.getElementById('current_position_start_date');
        var payLevelInput = document.getElementById('employee_grade');
        var promotionDateInput = document.getElementById('promotion_date');

        var syncCurrentRole = function () {
            if (!positionSelect || !roleDisplay) {
                return;
            }
            var selected = positionSelect.options[positionSelect.selectedIndex];
            roleDisplay.value = selected ? selected.text.trim() : '';
        };

        var syncRoleStartFromServiceDate = function () {
            if (!roleStartInput || !serviceStartInput) {
                return;
            }

            if (!roleStartInput.value && serviceStartInput.value) {
                roleStartInput.value = serviceStartInput.value;
            }
        };

        var placePromotionDateNextToPayLevel = function () {
            if (!payLevelInput || !promotionDateInput) {
                return;
            }

            var payLevelRow = payLevelInput.closest('.form-group.row');
            var promotionDateRow = promotionDateInput.closest('.form-group.row');

            if (!payLevelRow || !promotionDateRow || payLevelRow.nextElementSibling === promotionDateRow) {
                return;
            }

            payLevelRow.insertAdjacentElement('afterend', promotionDateRow);
        };

        if (positionSelect) {
            positionSelect.addEventListener('change', syncCurrentRole);
            syncCurrentRole();
        }

        if (serviceStartInput) {
            serviceStartInput.addEventListener('change', syncRoleStartFromServiceDate);
            syncRoleStartFromServiceDate();
        }

        placePromotionDateNextToPayLevel();
    })();
</script>
@endpush
