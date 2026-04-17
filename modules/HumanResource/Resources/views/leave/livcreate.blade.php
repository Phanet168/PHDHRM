<!-- Modal -->
@php
    $policyLabels = [
        'annual' => localize('leave_policy_annual', 'ឈប់ប្រចាំឆ្នាំ'),
        'short' => localize('leave_policy_short', 'ឈប់រយៈពេលខ្លី'),
        'sick' => localize('leave_policy_sick', 'ឈប់សម្រាកព្យាបាលជំងឺ'),
        'maternity' => localize('leave_policy_maternity', 'ឈប់លំហែមាតុភាព'),
        'unpaid' => localize('leave_policy_unpaid', 'ឈប់គ្មានបៀវត្ស'),
        'other' => localize('other', 'ផ្សេងៗ'),
    ];
    $scopeLabels = [
        'per_year' => localize('scope_per_year', 'ក្នុងមួយឆ្នាំ'),
        'per_request' => localize('scope_per_request', 'ក្នុងមួយសំណើ'),
        'per_service_lifetime' => localize('scope_per_service_lifetime', 'ក្នុងរយៈពេលបម្រើការងារ'),
        'manual' => localize('scope_manual', 'កំណត់ដោយដៃ'),
    ];
    $unitLabels = [
        'day' => localize('day', 'ថ្ងៃ'),
        'month' => localize('month', 'ខែ'),
    ];
@endphp
<div class="modal fade" id="addLeaveApplication" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
    aria-labelledby="staticBackdropLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="staticBackdropLabel">
                    {{ localize('leave_application_create') }}
                </h5>
            </div>
            <form class="validateForm leave-application-form" action="{{ route('leave.store') }}" method="POST"
                data-leave-balance-url="{{ route('leave.balance') }}"
                enctype="multipart/form-data">
                @csrf
                <div class="modal-body text-start">
                    <div class="row">

                        <div class="col-md-12 mt-3">
                            <div class="row">
                                <label for="employee_id"
                                    class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold text-start">{{ localize('employee') }}<span
                                        class="text-danger">*</span></label>
                                <div class="col-sm-9 col-md-12 col-xl-9">
                                    <select name="employee_id" id="employee_id" required
                                        class="select-basic-single leave-employee-select">
                                        <option value="" selected disabled>{{ localize('select_employee') }}
                                        </option>
                                        @foreach ($employees as $employee)
                                            <option value="{{ $employee->id }}">{{ $employee->full_name }}</option>
                                        @endforeach

                                    </select>

                                    @if ($errors->has('employee_id'))
                                        <div class="error text-danger m-2">{{ $errors->first('employee_id') }}</div>
                                    @endif
                                </div>

                            </div>
                        </div>

                        <div class="col-md-12 mt-3">
                            <div class="row">
                                <label for="leave_type_id"
                                    class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold text-start">{{ localize('leave_type') }}<span
                                        class="text-danger">*</span></label>
                                <div class="col-sm-9 col-md-12 col-xl-9">
                                    <select name="leave_type_id" required id="leave_type_id"
                                        class="select-basic-single leave-type-select">
                                        <option value="" selected disabled>{{ localize('select_leave_type') }}
                                        </option>
                                        @foreach ($leaveTypes as $leaveType)
                                            @php
                                                $entitlementValue = $leaveType->entitlement_value ?? $leaveType->leave_days;
                                                $entitlementUnit =
                                                    $unitLabels[$leaveType->entitlement_unit ?? 'day'] ??
                                                    ($leaveType->entitlement_unit ?? localize('day', 'ថ្ងៃ'));
                                                $entitlementScope =
                                                    $scopeLabels[$leaveType->entitlement_scope ?? 'per_year'] ??
                                                    ($leaveType->entitlement_scope ?? localize('scope_per_year', 'ក្នុងមួយឆ្នាំ'));
                                                $policyLabel =
                                                    $policyLabels[$leaveType->policy_key ?? 'other'] ??
                                                    ($leaveType->policy_key ?? localize('other', 'ផ្សេងៗ'));
                                            @endphp
                                            <option value="{{ $leaveType->id }}"
                                                data-policy-label="{{ $policyLabel }}"
                                                data-entitlement-value="{{ $entitlementValue }}"
                                                data-entitlement-unit="{{ $entitlementUnit }}"
                                                data-entitlement-scope="{{ $entitlementScope }}"
                                                data-max-per-request="{{ $leaveType->max_per_request }}"
                                                data-is-paid="{{ (int) (bool) $leaveType->is_paid }}"
                                                data-requires-attachment="{{ (int) (bool) $leaveType->requires_attachment }}"
                                                data-requires-medical="{{ (int) (bool) $leaveType->requires_medical_certificate }}">
                                                {{ $leaveType->display_name }}</option>
                                        @endforeach

                                    </select>

                                    @if ($errors->has('leave_type_id'))
                                        <div class="error text-danger m-2">{{ $errors->first('leave_type_id') }}</div>
                                    @endif
                                </div>

                            </div>
                        </div>

                        <div class="col-md-12 mt-2">
                            <div class="alert alert-info leave-policy-hint mb-0 small" role="alert">
                                {{ localize('select_leave_type_to_view_policy', 'សូមជ្រើសប្រភេទច្បាប់ ដើម្បីមើលលក្ខខណ្ឌសិទ្ធិច្បាប់') }}
                            </div>
                        </div>

                        <div class="col-md-12 mt-2">
                            <div class="alert alert-secondary leave-balance-hint mb-0 small" role="alert">
                                {{ localize('select_employee_and_leave_type_to_view_balance', 'សូមជ្រើសបុគ្គលិក និងប្រភេទច្បាប់ ដើម្បីមើលថ្ងៃនៅសល់') }}
                            </div>
                        </div>

                        <div class="col-md-12 mt-3">
                            <div class="row">
                                <label for="leave_apply_start_date"
                                    class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('from_date') }}
                                    <span class="text-danger">*</span></label>
                                <div class="col-sm-9 col-md-12 col-xl-9">
                                    <input type="text" required class="form-control date_picker leave-start-date"
                                        id="start_date"
                                        name="leave_apply_start_date" placeholder="{{ localize('from_date') }}"
                                        value="{{ current_date() }}" required>
                                </div>

                                @if ($errors->has('leave_apply_start_date'))
                                    <div class="error text-danger m-2">{{ $errors->first('leave_apply_start_date') }}
                                    </div>
                                @endif
                            </div>
                        </div>


                        <div class="col-md-12 mt-3">
                            <div class="row">
                                <label for="leave_apply_end_date"
                                    class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('end_date') }}
                                    <span class="text-danger">*</span></label>
                                <div class="col-sm-9 col-md-12 col-xl-9">
                                    <input type="text" class="form-control date_picker leave-end-date" id="end_date"
                                        name="leave_apply_end_date" placeholder="{{ localize('end_date') }}"
                                        value="" required>
                                </div>

                                @if ($errors->has('leave_apply_end_date'))
                                    <div class="error text-danger m-2">{{ $errors->first('leave_apply_end_date') }}
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="col-md-12 mt-3">
                            <div class="row">
                                <label for="total_apply_day"
                                    class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('total_days') }}</label>
                                <div class="col-sm-9 col-md-12 col-xl-9">
                                    <input type="text" class="form-control leave-total-day" id="total_day"
                                        name="total_apply_day"
                                        placeholder="{{ localize('total_days') }}"
                                        value="{{ old('total_apply_day') }}" readonly>
                                </div>

                                @if ($errors->has('total_apply_day'))
                                    <div class="error text-danger m-2">{{ $errors->first('total_apply_day') }}</div>
                                @endif
                            </div>
                        </div>

                        <div class="col-md-12 mt-3">
                            <div class="row">
                                <label for="location"
                                    class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold leave-attachment-label">{{ localize('application_hard_copy') }}
                                    <span class="text-danger leave-attachment-required d-none">*</span></label>
                                <div class="col-sm-9 col-md-12 col-xl-9">
                                    <input type="file" class="form-control leave-attachment-input" id="location"
                                        name="location"
                                        data-has-old-file="0"
                                        accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.rtf,.jpeg,.jpg,.png,.gif,.svg">
                                    <small class="text-muted">
                                        {{ localize('allowed_files', 'ប្រភេទឯកសារ: pdf, doc, docx, xls, xlsx, txt, rtf, jpeg, jpg, png, gif, svg') }}
                                    </small>
                                </div>

                                @if ($errors->has('location'))
                                    <div class="error text-danger m-2">{{ $errors->first('location') }}</div>
                                @endif
                            </div>
                        </div>

                        <div class="col-md-12 mt-3">
                            <div class="row">
                                <label for="reason"
                                    class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('reason') }}</label>
                                <div class="col-sm-9 col-md-12 col-xl-9">
                                    <textarea class="form-control" name="reason" id="reason">{{ old('reason') }}</textarea>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger"
                        data-bs-dismiss="modal">{{ localize('close') }}</button>
                    <button class="btn btn-primary submit_button">{{ localize('save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
