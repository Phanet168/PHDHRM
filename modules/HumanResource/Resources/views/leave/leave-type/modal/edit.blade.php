<!-- Modal -->
<div class="modal fade" id="update-leave-type-{{ $data->id }}" data-bs-backdrop="static" data-bs-keyboard="false"
    tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="staticBackdropLabel">
                    @lang('language.edit_leave_type')
                </h5>
            </div>
            <form class="validateEditForm" action="{{ route('leave-types.update', $data->uuid) }}" method="POST">
                @method('PATCH')
                @csrf
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12 mt-3">
                            <div class="row">
                                <label for="leave_type"
                                    class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('leave_type') }}
                                    <span class="text-danger">*</span></label>
                                <div class="col-sm-9 col-md-12 col-xl-9">
                                    <input type="text" required class="form-control" name="leave_type"
                                        placeholder="{{ localize('leave_type') }}" value="{{ $data->leave_type }}"
                                        required>
                                </div>

                                @if ($errors->has('leave_type'))
                                    <div class="error text-danger m-2">{{ $errors->first('leave_type') }}
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="col-md-12 mt-3">
                            <div class="row">
                                <label for="leave_type_km"
                                    class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('leave_type_km', 'ឈ្មោះប្រភេទច្បាប់ (ខ្មែរ)') }}</label>
                                <div class="col-sm-9 col-md-12 col-xl-9">
                                    <input type="text" class="form-control" name="leave_type_km"
                                        placeholder="{{ localize('leave_type_km', 'ឈ្មោះប្រភេទច្បាប់ (ខ្មែរ)') }}"
                                        value="{{ $data->leave_type_km }}">
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12 mt-3">
                            <div class="row">
                                <label for="leave_code"
                                    class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('leave_code') }}
                                    <span class="text-danger">*</span></label>
                                <div class="col-sm-9 col-md-12 col-xl-9">
                                    <input type="text" class="form-control" name="leave_code"
                                        placeholder="{{ localize('leave_code') }}" value="{{ $data->leave_code }}"
                                        required>
                                </div>

                                @if ($errors->has('leave_code'))
                                    <div class="error text-danger m-2">{{ $errors->first('leave_code') }}
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="col-md-12 mt-3">
                            <div class="row">
                                <label for="leave_days"
                                    class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('leave_days') }}</label>
                                <div class="col-sm-9 col-md-12 col-xl-9">
                                    <input type="text" class="form-control" required name="leave_days"
                                        placeholder="{{ localize('leave_days') }}" value="{{ $data->leave_days }}">
                                </div>

                                @if ($errors->has('leave_days'))
                                    <div class="error text-danger m-2">{{ $errors->first('leave_days') }}</div>
                                @endif
                            </div>
                        </div>

                        <div class="col-md-12 mt-3">
                            <div class="row">
                                <label for="policy_key"
                                    class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('leave_policy', 'គោលនយោបាយ') }}
                                    <span class="text-danger">*</span></label>
                                <div class="col-sm-9 col-md-12 col-xl-9">
                                    <select name="policy_key" class="form-control select-basic-single" required>
                                        <option value="annual" {{ $data->policy_key === 'annual' ? 'selected' : '' }}>{{ localize('leave_policy_annual', 'ឈប់ប្រចាំឆ្នាំ') }}</option>
                                        <option value="short" {{ $data->policy_key === 'short' ? 'selected' : '' }}>{{ localize('leave_policy_short', 'ឈប់រយៈពេលខ្លី') }}</option>
                                        <option value="sick" {{ $data->policy_key === 'sick' ? 'selected' : '' }}>{{ localize('leave_policy_sick', 'ឈប់សម្រាកព្យាបាលជំងឺ') }}</option>
                                        <option value="maternity" {{ $data->policy_key === 'maternity' ? 'selected' : '' }}>{{ localize('leave_policy_maternity', 'ឈប់លំហែមាតុភាព') }}</option>
                                        <option value="unpaid" {{ $data->policy_key === 'unpaid' ? 'selected' : '' }}>{{ localize('leave_policy_unpaid', 'ឈប់គ្មានបៀវត្ស') }}</option>
                                        <option value="other" {{ $data->policy_key === 'other' ? 'selected' : '' }}>{{ localize('other', 'ផ្សេងៗ') }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12 mt-3">
                            <div class="row">
                                <label for="entitlement_scope"
                                    class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('entitlement_scope', 'វិសាលភាពសិទ្ធិ') }}</label>
                                <div class="col-sm-9 col-md-12 col-xl-9">
                                    <select name="entitlement_scope" class="form-control select-basic-single">
                                        <option value="per_year" {{ $data->entitlement_scope === 'per_year' ? 'selected' : '' }}>{{ localize('scope_per_year', 'ក្នុងមួយឆ្នាំ') }}</option>
                                        <option value="per_request" {{ $data->entitlement_scope === 'per_request' ? 'selected' : '' }}>{{ localize('scope_per_request', 'ក្នុងមួយសំណើ') }}</option>
                                        <option value="per_service_lifetime" {{ $data->entitlement_scope === 'per_service_lifetime' ? 'selected' : '' }}>{{ localize('scope_per_service_lifetime', 'ក្នុងរយៈពេលបម្រើការងារ') }}</option>
                                        <option value="manual" {{ $data->entitlement_scope === 'manual' ? 'selected' : '' }}>{{ localize('scope_manual', 'កំណត់ដោយដៃ') }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12 mt-3">
                            <div class="row">
                                <label for="entitlement_unit"
                                    class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('entitlement_unit', 'ឯកតា') }}</label>
                                <div class="col-sm-9 col-md-12 col-xl-9">
                                    <select name="entitlement_unit" class="form-control select-basic-single">
                                        <option value="day" {{ $data->entitlement_unit === 'day' ? 'selected' : '' }}>{{ localize('day', 'ថ្ងៃ') }}</option>
                                        <option value="month" {{ $data->entitlement_unit === 'month' ? 'selected' : '' }}>{{ localize('month', 'ខែ') }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12 mt-3">
                            <div class="row">
                                <label for="entitlement_value"
                                    class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('entitlement_value', 'ចំនួនសិទ្ធិ') }}</label>
                                <div class="col-sm-9 col-md-12 col-xl-9">
                                    <input type="number" step="0.01" min="0" class="form-control"
                                        name="entitlement_value" placeholder="{{ localize('entitlement_value', 'ចំនួនសិទ្ធិ') }}"
                                        value="{{ $data->entitlement_value }}">
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12 mt-3">
                            <div class="row">
                                <label for="max_per_request"
                                    class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('max_per_request', 'អតិបរមាក្នុងមួយសំណើ') }}</label>
                                <div class="col-sm-9 col-md-12 col-xl-9">
                                    <input type="number" step="0.01" min="0" class="form-control"
                                        name="max_per_request" placeholder="{{ localize('max_per_request', 'អតិបរមាក្នុងមួយសំណើ') }}"
                                        value="{{ $data->max_per_request }}">
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12 mt-3">
                            <div class="row">
                                <label class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('paid_status', 'បៀវត្ស') }}</label>
                                <div class="col-sm-9 col-md-12 col-xl-9">
                                    <select name="is_paid" class="form-control select-basic-single">
                                        <option value="1" {{ (int) $data->is_paid === 1 ? 'selected' : '' }}>{{ localize('paid', 'មានបៀវត្ស') }}</option>
                                        <option value="0" {{ (int) $data->is_paid === 0 ? 'selected' : '' }}>{{ localize('unpaid', 'គ្មានបៀវត្ស') }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12 mt-3">
                            <div class="row">
                                <label class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('requires_attachment', 'តម្រូវឯកសារភ្ជាប់') }}</label>
                                <div class="col-sm-9 col-md-12 col-xl-9">
                                    <select name="requires_attachment" class="form-control select-basic-single">
                                        <option value="1" {{ (int) $data->requires_attachment === 1 ? 'selected' : '' }}>{{ localize('yes', 'បាទ/ចាស') }}</option>
                                        <option value="0" {{ (int) $data->requires_attachment === 0 ? 'selected' : '' }}>{{ localize('no', 'ទេ') }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12 mt-3">
                            <div class="row">
                                <label class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('requires_medical_certificate', 'តម្រូវវិញ្ញាបនបត្រវេជ្ជសាស្ត្រ') }}</label>
                                <div class="col-sm-9 col-md-12 col-xl-9">
                                    <select name="requires_medical_certificate" class="form-control select-basic-single">
                                        <option value="1" {{ (int) $data->requires_medical_certificate === 1 ? 'selected' : '' }}>{{ localize('yes', 'បាទ/ចាស') }}</option>
                                        <option value="0" {{ (int) $data->requires_medical_certificate === 0 ? 'selected' : '' }}>{{ localize('no', 'ទេ') }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12 mt-3">
                            <div class="row">
                                <label for="notes"
                                    class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('notes', 'កំណត់សម្គាល់') }}</label>
                                <div class="col-sm-9 col-md-12 col-xl-9">
                                    <textarea class="form-control" name="notes" rows="2"
                                        placeholder="{{ localize('notes', 'កំណត់សម្គាល់') }}">{{ $data->notes }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">@lang('language.close')</button>
                    <button class="btn btn-primary submit_button">@lang('language.update')</button>
                </div>
            </form>
        </div>
    </div>
</div>
