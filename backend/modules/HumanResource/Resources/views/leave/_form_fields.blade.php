@php
    $selectedEmployee = $selectedEmployee ?? null;
    $row = $row ?? null;
    $formUid = 'leave_' . substr(md5(($row?->uuid ?? 'create') . '_' . uniqid('', true)), 0, 8);
@endphp

<style>
    .leave-application-form {
        --leave-border: #d9e4ef;
        --leave-soft-bg: linear-gradient(180deg, #f8fbff 0%, #fdfefe 100%);
        --leave-title: #243447;
        --leave-muted: #6e7f91;
    }

    .leave-request-modal .modal-content {
        background: #f7fbff;
    }

    .leave-request-modal .leave-request-modal-header {
        background: rgba(255, 255, 255, 0.96);
        backdrop-filter: blur(10px);
    }

    .leave-request-modal .leave-request-modal-body {
        max-height: calc(100vh - 220px);
        overflow-y: auto;
        background: linear-gradient(180deg, #f4f8fc 0%, #fbfdff 100%);
        scroll-padding-bottom: 6rem;
    }

    .leave-request-modal .leave-request-modal-footer {
        gap: 0.75rem;
    }

    .leave-request-modal .leave-request-modal-footer .btn {
        min-height: 44px;
        min-width: 116px;
        border-radius: 12px;
    }

    .leave-application-form .leave-form-section {
        border: 1px solid var(--leave-border);
        border-radius: 18px;
        padding: 1.15rem 1.2rem;
        background: var(--leave-soft-bg);
        box-shadow: 0 10px 24px rgba(31, 111, 235, 0.05);
    }

    .leave-application-form .leave-form-title {
        font-weight: 600;
        margin-bottom: 1rem;
        color: var(--leave-title);
        font-size: 1rem;
    }

    .leave-application-form .leave-help-text {
        color: var(--leave-muted);
        font-size: 0.9rem;
    }

    .leave-application-form .form-label {
        color: #33475b;
        margin-bottom: 0.45rem;
    }

    .leave-application-form .form-control,
    .leave-application-form .form-select {
        min-height: 44px;
        border-radius: 12px;
        border-color: #cfdae6;
        box-shadow: none;
        font-size: 16px;
    }

    .leave-application-form textarea.form-control {
        min-height: 108px;
    }

    .leave-application-form .leave-note {
        border-radius: 14px;
        border: 1px solid transparent;
        padding: 0.95rem 1rem;
        margin-bottom: 0;
    }

    .leave-application-form .leave-note-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.75rem;
    }

    .leave-application-form .leave-note-title {
        font-size: 0.95rem;
        color: #17324d;
    }

    .leave-application-form .leave-note-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
        gap: 0.7rem;
    }

    .leave-application-form .leave-note-item {
        background: rgba(255, 255, 255, 0.78);
        border: 1px solid rgba(159, 183, 208, 0.35);
        border-radius: 12px;
        padding: 0.7rem 0.8rem;
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
    }

    .leave-application-form .leave-note-item-highlight {
        border-color: rgba(25, 135, 84, 0.3);
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.94) 0%, rgba(229, 247, 238, 0.95) 100%);
    }

    .leave-application-form .leave-note-label {
        font-size: 0.77rem;
        color: #5f7286;
    }

    .leave-application-form .leave-note-value {
        font-size: 0.96rem;
        color: #17324d;
        line-height: 1.35;
    }

    .leave-application-form .leave-note-meta {
        margin-top: 0.85rem;
        font-size: 0.8rem;
        color: #637487;
        line-height: 1.5;
    }

    .leave-application-form .leave-hidden-value {
        position: absolute;
        opacity: 0;
        pointer-events: none;
        height: 0;
        width: 0;
    }

    @media (max-width: 991.98px) {
        .leave-request-modal .leave-request-modal-body {
            max-height: calc(100vh - 180px);
        }

        .leave-application-form .leave-note-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 767.98px) {
        .leave-request-modal .modal-dialog {
            margin: 0;
        }

        .leave-request-modal .leave-request-modal-header {
            padding: 1rem !important;
            align-items: flex-start;
        }

        .leave-request-modal .leave-request-modal-body {
            max-height: none;
            padding: 1rem !important;
            scroll-padding-bottom: 8rem;
        }

        .leave-request-modal .leave-request-modal-footer {
            position: sticky;
            bottom: 0;
            z-index: 2;
            padding: 0.9rem 1rem max(0.9rem, env(safe-area-inset-bottom)) !important;
            box-shadow: 0 -10px 24px rgba(28, 56, 88, 0.08);
        }

        .leave-request-modal .leave-request-modal-footer .btn {
            width: 100%;
            min-width: 0;
        }

        .leave-application-form .leave-form-section {
            border-radius: 16px;
            padding: 1rem;
            margin-bottom: 1rem !important;
        }

        .leave-application-form .leave-form-title {
            font-size: 0.95rem;
            margin-bottom: 0.85rem;
        }

        .leave-application-form .leave-help-text,
        .leave-application-form .leave-note-meta {
            font-size: 0.78rem;
        }

        .leave-application-form .leave-note {
            padding: 0.85rem;
        }

        .leave-application-form .leave-note-grid {
            grid-template-columns: minmax(0, 1fr);
            gap: 0.6rem;
        }

        .leave-application-form .leave-note-item {
            padding: 0.7rem;
        }
    }
</style>

<div class="leave-form-section mb-4">
    <div class="leave-form-title">{{ localize('request_information', 'ព័ត៌មានសំណើ') }}</div>
    <div class="row g-3">
        <div class="col-lg-6">
            <label for="employee_display_{{ $formUid }}" class="form-label fw-semibold">
                {{ localize('employee') }}<span class="text-danger">*</span>
            </label>
            @if (!empty($isSystemAdmin))
                @php
                    $selectedEmployeeLabel = '';
                    $selectedEmployeeId = old('employee_id') ?? $row->employee_id ?? $currentEmployeeId ?? null;
                    if ($selectedEmployeeId) {
                        $selectedEmployeeLabel = optional($employees->firstWhere('id', (int) $selectedEmployeeId))->full_name ?? '';
                    }
                @endphp
                <input type="text" id="employee_display_{{ $formUid }}" class="form-control leave-datalist-input"
                    list="employee_options_{{ $formUid }}" placeholder="{{ localize('select_employee') }}"
                    value="{{ $selectedEmployeeLabel }}"
                    data-hidden-target="#employee_hidden_{{ $formUid }}">
                <datalist id="employee_options_{{ $formUid }}">
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->full_name }}" data-id="{{ $employee->id }}"></option>
                    @endforeach
                </datalist>
                <input type="hidden" id="employee_hidden_{{ $formUid }}" name="employee_id"
                    value="{{ $selectedEmployeeId }}" class="leave-employee-select leave-hidden-value" required>
            @else
                <input type="text" class="form-control"
                    value="{{ $selectedEmployee?->full_name ?? $row->employee?->full_name ?? '-' }}" readonly>
                <input type="hidden" name="employee_id" value="{{ $currentEmployeeId ?? $row->employee_id }}"
                    class="leave-employee-select">
                <small class="leave-help-text d-block mt-2">
                    {{ localize('leave_request_employee_auto', 'សំណើនេះនឹងស្នើសុំសម្រាប់អ្នកប្រើប្រាស់ដែលកំពុងចូលប្រើប្រាស់ប្រព័ន្ធ') }}
                </small>
            @endif
            @if ($errors->has('employee_id'))
                <div class="error text-danger mt-2">{{ $errors->first('employee_id') }}</div>
            @endif
        </div>

        <div class="col-lg-6">
            <label for="handover_display_{{ $formUid }}" class="form-label fw-semibold">
                {{ localize('handover_employee', 'អ្នកទទួលការងារជំនួស') }}<span class="text-danger">*</span>
            </label>
            @php
                $selectedHandoverId = old('handover_employee_id') ?? $row->handover_employee_id ?? null;
                $selectedHandoverLabel = '';
                if ($selectedHandoverId) {
                    $selectedHandoverLabel = optional($handoverEmployees->firstWhere('id', (int) $selectedHandoverId))->full_name ?? '';
                }
            @endphp
            <input type="text" id="handover_display_{{ $formUid }}" class="form-control leave-datalist-input"
                list="handover_options_{{ $formUid }}"
                placeholder="{{ localize('select_handover_employee', 'ជ្រើសអ្នកទទួលការងារជំនួស') }}"
                value="{{ $selectedHandoverLabel }}"
                data-hidden-target="#handover_hidden_{{ $formUid }}">
            <datalist id="handover_options_{{ $formUid }}">
                @foreach ($handoverEmployees as $employee)
                    <option value="{{ $employee->full_name }}" data-id="{{ $employee->id }}"></option>
                @endforeach
            </datalist>
            <input type="hidden" id="handover_hidden_{{ $formUid }}" name="handover_employee_id"
                value="{{ $selectedHandoverId }}" class="leave-hidden-value" required>
            <small class="leave-help-text d-block mt-2">
                {{ localize('handover_employee_hint', 'អ្នកនេះនឹងទទួលខុសត្រូវបន្តក្នុងអំឡុងពេលអ្នកឈប់សម្រាក') }}
            </small>
            @if ($errors->has('handover_employee_id'))
                <div class="error text-danger mt-2">{{ $errors->first('handover_employee_id') }}</div>
            @endif
        </div>

        <div class="col-12">
            <label for="leave_type_display_{{ $formUid }}" class="form-label fw-semibold">
                {{ localize('leave_type') }}<span class="text-danger">*</span>
            </label>
            @php
                $selectedLeaveTypeId = old('leave_type_id') ?? $row->leave_type_id ?? null;
                $selectedLeaveTypeLabel = '';
                if ($selectedLeaveTypeId) {
                    $selectedLeaveTypeLabel = optional($leaveTypes->firstWhere('id', (int) $selectedLeaveTypeId))->display_name ?? '';
                }
            @endphp
            <input type="text" id="leave_type_display_{{ $formUid }}" class="form-control leave-datalist-input"
                list="leave_type_options_{{ $formUid }}" placeholder="{{ localize('select_leave_type') }}"
                value="{{ $selectedLeaveTypeLabel }}"
                data-hidden-target="#leave_type_hidden_{{ $formUid }}">
            <datalist id="leave_type_options_{{ $formUid }}">
                @foreach ($leaveTypes as $leaveType)
                    <option value="{{ $leaveType->display_name }}" data-id="{{ $leaveType->id }}"></option>
                @endforeach
            </datalist>
            <input type="hidden" id="leave_type_hidden_{{ $formUid }}" name="leave_type_id"
                value="{{ $selectedLeaveTypeId }}" class="leave-type-select leave-hidden-value" required>

            <select class="leave-type-meta d-none">
                <option value="">{{ localize('select_leave_type') }}</option>
                @foreach ($leaveTypes as $leaveType)
                    @php
                        $entitlementValue = $leaveType->entitlement_value ?? $leaveType->leave_days;
                        $normalizedEntitlementValue = ($leaveType->entitlement_unit ?? 'day') === 'month'
                            ? (int) round((float) $entitlementValue * 30)
                            : (int) round((float) $entitlementValue);
                        $entitlementUnit =
                            localize('day', 'ថ្ងៃ');
                        $entitlementScope =
                            $scopeLabels[$leaveType->entitlement_scope ?? 'per_year'] ??
                            ($leaveType->entitlement_scope ?? localize('scope_per_year', 'ក្នុងមួយឆ្នាំ'));
                        $policyLabel =
                            $policyLabels[$leaveType->policy_key ?? 'other'] ??
                            ($leaveType->policy_key ?? localize('other', 'ផ្សេងៗ'));
                        $normalizedMaxPerRequest = $leaveType->max_per_request !== null
                            ? (($leaveType->entitlement_unit ?? 'day') === 'month'
                                ? (int) round((float) $leaveType->max_per_request * 30)
                                : (float) $leaveType->max_per_request)
                            : null;
                    @endphp
                    <option value="{{ $leaveType->id }}"
                        data-policy-label="{{ $policyLabel }}"
                        data-entitlement-value="{{ $normalizedEntitlementValue }}"
                        data-entitlement-unit="{{ $entitlementUnit }}"
                        data-entitlement-scope="{{ $entitlementScope }}"
                        data-max-per-request="{{ $normalizedMaxPerRequest }}"
                        data-is-paid="{{ (int) (bool) $leaveType->is_paid }}"
                        data-requires-attachment="{{ (int) (bool) $leaveType->requires_attachment }}"
                        data-requires-medical="{{ (int) (bool) $leaveType->requires_medical_certificate }}">
                        {{ $leaveType->display_name }}
                    </option>
                @endforeach
            </select>
            @if ($errors->has('leave_type_id'))
                <div class="error text-danger mt-2">{{ $errors->first('leave_type_id') }}</div>
            @endif
        </div>

        <div class="col-lg-6">
            <div class="alert alert-info leave-note leave-policy-hint small" role="alert">
                {{ localize('select_leave_type_to_view_policy', 'សូមជ្រើសប្រភេទច្បាប់ ដើម្បីមើលលក្ខខណ្ឌសិទ្ធិច្បាប់') }}
            </div>
        </div>

        <div class="col-lg-6">
            <div class="alert alert-secondary leave-note leave-balance-hint small" role="alert">
                {{ localize('select_employee_and_leave_type_to_view_balance', 'សូមជ្រើសបុគ្គលិក និងប្រភេទច្បាប់ ដើម្បីមើលថ្ងៃនៅសល់') }}
            </div>
        </div>
    </div>
</div>

<div class="leave-form-section">
    <div class="leave-form-title">{{ localize('leave_period', 'រយៈពេលឈប់សម្រាក') }}</div>
    <div class="row g-3">
        <div class="col-md-6">
            <label for="leave_apply_start_date" class="form-label fw-semibold">
                {{ localize('from_date') }}<span class="text-danger">*</span>
            </label>
            <input type="text" required class="form-control date_picker leave-start-date"
                name="leave_apply_start_date"
                value="{{ old('leave_apply_start_date') ?? ($row->leave_apply_start_date ?? current_date()) }}" required>
            @if ($errors->has('leave_apply_start_date'))
                <div class="error text-danger mt-2">{{ $errors->first('leave_apply_start_date') }}</div>
            @endif
        </div>

        <div class="col-md-6">
            <label for="leave_apply_end_date" class="form-label fw-semibold">
                {{ localize('end_date') }}<span class="text-danger">*</span>
            </label>
            <input type="text" required class="form-control date_picker leave-end-date"
                name="leave_apply_end_date"
                value="{{ old('leave_apply_end_date') ?? ($row->leave_apply_end_date ?? '') }}" required>
            @if ($errors->has('leave_apply_end_date'))
                <div class="error text-danger mt-2">{{ $errors->first('leave_apply_end_date') }}</div>
            @endif
        </div>

        <div class="col-md-6">
            <label for="total_apply_day" class="form-label fw-semibold">{{ localize('total_days') }}</label>
            <input type="text" class="form-control leave-total-day" name="total_apply_day"
                placeholder="{{ localize('total_days') }}"
                value="{{ old('total_apply_day') ?? ($row->total_apply_day ?? '') }}" readonly>
            @if ($errors->has('total_apply_day'))
                <div class="error text-danger mt-2">{{ $errors->first('total_apply_day') }}</div>
            @endif
        </div>

        <div class="col-md-6">
            <label for="location" class="form-label fw-semibold leave-attachment-label">
                {{ localize('application_hard_copy') }}
                <span class="text-danger leave-attachment-required d-none">*</span>
            </label>
            <input type="file" class="form-control leave-attachment-input" id="location" name="location"
                data-has-old-file="{{ !empty($row?->location) ? 1 : 0 }}"
                accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.rtf,.jpeg,.jpg,.png,.gif,.svg">
            <small class="leave-help-text d-block mt-2">
                {{ localize('allowed_files', 'ប្រភេទឯកសារ: pdf, doc, docx, xls, xlsx, txt, rtf, jpeg, jpg, png, gif, svg') }}
            </small>
            @if (!empty($row?->location))
                <small class="d-block mt-2">
                    <a href="{{ asset('storage/' . $row->location) }}" target="_blank">
                        {{ basename(asset('storage/' . $row->location)) }}
                    </a>
                </small>
            @endif
            @if ($errors->has('location'))
                <div class="error text-danger mt-2">{{ $errors->first('location') }}</div>
            @endif
        </div>

        <div class="col-12">
            <label for="reason" class="form-label fw-semibold">{{ localize('reason') }}</label>
            <textarea name="reason" class="form-control" id="reason" rows="4"
                placeholder="{{ localize('leave_reason_placeholder', 'សូមបញ្ជាក់មូលហេតុនៃការស្នើសុំច្បាប់') }}">{{ old('reason') ?? ($row->reason ?? '') }}</textarea>
        </div>
    </div>
</div>

<script>
    (function () {
        function bindDatalistInput(input) {
            if (!input) {
                return;
            }

            const hiddenSelector = input.getAttribute('data-hidden-target');
            const hiddenInput = hiddenSelector ? document.querySelector(hiddenSelector) : null;
            const listId = input.getAttribute('list');
            const dataList = listId ? document.getElementById(listId) : null;

            if (!hiddenInput || !dataList) {
                return;
            }

            const syncValue = function () {
                const current = (input.value || '').trim().toLowerCase();
                let matchedId = '';

                Array.prototype.forEach.call(dataList.options, function (option) {
                    if ((option.value || '').trim().toLowerCase() === current) {
                        matchedId = option.getAttribute('data-id') || '';
                    }
                });

                hiddenInput.value = matchedId;
                if (window.jQuery) {
                    window.jQuery(hiddenInput).trigger('change');
                }
            };

            input.addEventListener('input', syncValue);
            input.addEventListener('change', syncValue);
            input.addEventListener('blur', syncValue);
        }

        const root = document.currentScript ? document.currentScript.parentElement : document;
        const scope = root && root.closest ? (root.closest('form') || document) : document;
        Array.prototype.forEach.call(scope.querySelectorAll('.leave-datalist-input'), bindDatalistInput);
    })();
</script>
