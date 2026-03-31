@php
    $policyLabels = [
        'annual' => localize('leave_policy_annual', 'ážˆáž”áŸ‹áž”áŸ’ážšáž…áž¶áŸ†áž†áŸ’áž“áž¶áŸ†'),
        'short' => localize('leave_policy_short', 'ážˆáž”áŸ‹ážšáž™áŸˆáž–áŸáž›ážáŸ’áž›áž¸'),
        'sick' => localize('leave_policy_sick', 'ážˆáž”áŸ‹ážŸáž˜áŸ’ážšáž¶áž€áž–áŸ’áž™áž¶áž”áž¶áž›áž‡áŸ†áž„ážº'),
        'maternity' => localize('leave_policy_maternity', 'ážˆáž”áŸ‹áž›áŸ†áž áŸ‚áž˜áž¶ážáž»áž—áž¶áž–'),
        'unpaid' => localize('leave_policy_unpaid', 'ážˆáž”áŸ‹áž‚áŸ’áž˜áž¶áž“áž”áŸ€ážœážáŸ’ážŸ'),
        'other' => localize('other', 'áž•áŸ’ážŸáŸáž„áŸ—'),
    ];
    $scopeLabels = [
        'per_year' => localize('scope_per_year', 'áž€áŸ’áž“áž»áž„áž˜áž½áž™áž†áŸ’áž“áž¶áŸ†'),
        'per_request' => localize('scope_per_request', 'áž€áŸ’áž“áž»áž„áž˜áž½áž™ážŸáŸ†ážŽáž¾'),
        'per_service_lifetime' => localize('scope_per_service_lifetime', 'áž€áŸ’áž“áž»áž„ážšáž™áŸˆáž–áŸáž›áž”áž˜áŸ’ážšáž¾áž€áž¶ážšáž„áž¶ážš'),
        'manual' => localize('scope_manual', 'áž€áŸ†ážŽážáŸ‹ážŠáŸ„áž™ážŠáŸƒ'),
    ];
    $unitLabels = [
        'day' => localize('day', 'ážáŸ’áž„áŸƒ'),
        'month' => localize('month', 'ážáŸ‚'),
    ];
@endphp
<form class="validateEditForm leave-application-form" action="{{ route('leave.update', $row->uuid) }}" method="POST"
    data-leave-balance-url="{{ route('leave.balance') }}"
    enctype="multipart/form-data">
    @csrf
    @method('PUT')
    <div class="modal-body">
        <div class="row">
            <div class="col-md-12 mt-3">
                <div class="row">
                    <label for="employee_id"
                        class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold text-start">{{ localize('employee') }}<span
                            class="text-danger">*</span></label>
                    <div class="col-sm-9 col-md-12 col-xl-9">
                        <select name="employee_id" required id="employee_id"
                            class="select-basic-single w-100 leave-employee-select">
                            <option value="">{{ localize('select_employee') }}</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}"
                                    {{ $row->employee_id == $employee->id ? 'selected' : '' }}>
                                    {{ $employee->full_name }}</option>
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
                            class="select-basic-single w-100 leave-type-select">
                            <option value="">{{ localize('select_leave_type') }}</option>
                            @foreach ($leaveTypes as $leaveType)
                                @php
                                    $entitlementValue = $leaveType->entitlement_value ?? $leaveType->leave_days;
                                    $entitlementUnit =
                                        $unitLabels[$leaveType->entitlement_unit ?? 'day'] ??
                                        ($leaveType->entitlement_unit ?? localize('day', 'ážáŸ’áž„áŸƒ'));
                                    $entitlementScope =
                                        $scopeLabels[$leaveType->entitlement_scope ?? 'per_year'] ??
                                        ($leaveType->entitlement_scope ?? localize('scope_per_year', 'áž€áŸ’áž“áž»áž„áž˜áž½áž™áž†áŸ’áž“áž¶áŸ†'));
                                    $policyLabel =
                                        $policyLabels[$leaveType->policy_key ?? 'other'] ??
                                        ($leaveType->policy_key ?? localize('other', 'áž•áŸ’ážŸáŸáž„áŸ—'));
                                @endphp
                                <option value="{{ $leaveType->id }}"
                                    {{ $row->leave_type_id == $leaveType->id ? 'selected' : '' }}
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
                    {{ localize('select_leave_type_to_view_policy', 'ážŸáž¼áž˜áž‡áŸ’ážšáž¾ážŸáž”áŸ’ážšáž—áŸáž‘áž…áŸ’áž”áž¶áž”áŸ‹ ážŠáž¾áž˜áŸ’áž”áž¸áž˜áž¾áž›áž›áž€áŸ’ážážážŽáŸ’ážŒážŸáž·áž‘áŸ’áž’áž·áž…áŸ’áž”áž¶áž”áŸ‹') }}
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
                        <input type="text" required class="form-control date_picker leave-start-date" id=""
                            name="leave_apply_start_date"
                            value="{{ old('leave_apply_start_date') ?? $row->leave_apply_start_date }}" required>
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
                        <input type="text" required class="form-control date_picker leave-end-date" id=""
                            name="leave_apply_end_date"
                            value="{{ old('leave_apply_end_date') ?? $row->leave_apply_end_date }}" required>
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
                        <input type="text" class="form-control leave-total-day" id="edit_total_day"
                            name="total_apply_day"
                            placeholder="{{ localize('total_days') }}"
                            value="{{ old('total_apply_day') ?? $row->total_apply_day }}" readonly>
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
                            data-has-old-file="{{ !empty($row->location) ? 1 : 0 }}"
                            accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.rtf,.jpeg,.jpg,.png,.gif,.svg">
                        <small class="text-muted">
                            {{ localize('allowed_files', 'áž”áŸ’ážšáž—áŸáž‘áž¯áž€ážŸáž¶ážš: pdf, doc, docx, xls, xlsx, txt, rtf, jpeg, jpg, png, gif, svg') }}
                        </small>

                        @if (!empty($row->location))
                            <small>
                                <a href="{{ asset('storage/' . $row->location) }}" target="_blank">
                                    @php
                                        $myFile = asset('storage/' . $row->location);
                                        $name = basename($myFile);
                                    @endphp
                                    {{ $name }}
                                </a>
                            </small>
                        @endif
                    </div>
                </div>
                <input type="hidden" name="oldlocation" value="{{ $row->location }}">
                <input type="hidden" name="leave_uuid" value="{{ $row->uuid }}">
            </div>

            <div class="col-md-12 mt-3">
                <div class="row">
                    <label for="location"
                        class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('reason') }}</label>
                    <div class="col-sm-9 col-md-12 col-xl-9">
                        <textarea name="reason" class="form-control" id="reason" cols="65" rows="3">{{ old('reason') ?? $row->reason }}</textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">{{ localize('close') }}</button>
        <button class="btn btn-primary submit_button" id="create_submit">{{ localize('update') }}</button>
    </div>
</form>


<script src="{{ asset('backend/assets/dist/js/custom.js') }}"></script>

