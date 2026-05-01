<style>
    .leave-approve-form .modal-body {
        max-height: calc(100vh - 220px);
        overflow-y: auto;
        background: linear-gradient(180deg, #f4f8fc 0%, #fbfdff 100%);
        padding: 1.25rem 1.5rem;
    }

    .leave-approve-form .modal-footer {
        background: #fff;
        border-top: 1px solid #e2e8f0;
        gap: 0.75rem;
        padding: 1rem 1.5rem 1.25rem;
    }

    .leave-approve-form .form-control {
        min-height: 46px;
        border-radius: 12px;
    }

    .leave-approve-form textarea.form-control {
        min-height: 120px;
    }

    @media (max-width: 991.98px) {
        .leave-approve-form .modal-body {
            max-height: calc(100vh - 170px);
        }
    }

    @media (max-width: 767.98px) {
        .leave-approve-form .modal-body {
            max-height: none;
            padding: 1rem;
        }

        .leave-approve-form .modal-footer {
            position: sticky;
            bottom: 0;
            z-index: 2;
            padding: 0.9rem 1rem max(0.9rem, env(safe-area-inset-bottom));
        }

        .leave-approve-form .modal-footer .btn {
            width: 100%;
            min-height: 46px;
        }
    }
</style>

<form id="leadForm" class="leave-approve-form" action="{{ route('leave.approved', $row->uuid) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    <div class="modal-body">
        <div class="row">

            <div class="col-md-12 mt-3">
                <div class="row">
                    <label for="leave_approved_start_date"
                        class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('from_date') }}
                        <span class="text-danger">*</span></label>
                    <div class="col-sm-9 col-md-12 col-xl-9">
                        <input type="text" class="form-control date_picker" id="approved_start_date"
                            name="leave_approved_start_date"
                            value="{{ old('leave_approved_start_date') ?? $row->leave_apply_start_date }}" required>
                    </div>

                    @if ($errors->has('leave_approved_start_date'))
                        <div class="error text-danger m-2">{{ $errors->first('leave_approved_start_date') }}
                        </div>
                    @endif
                </div>
            </div>


            <div class="col-md-12 mt-3">
                <div class="row">
                    <label for="leave_approved_end_date"
                        class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('end_date') }}
                        <span class="text-danger">*</span></label>
                    <div class="col-sm-9 col-md-12 col-xl-9">
                        <input type="text" class="form-control date_picker" id="approved_end_date"
                            name="leave_approved_end_date"
                            value="{{ old('leave_approved_end_date') ?? $row->leave_apply_end_date }}" required>
                    </div>

                    @if ($errors->has('leave_approved_end_date'))
                        <div class="error text-danger m-2">{{ $errors->first('leave_approved_end_date') }}
                        </div>
                    @endif
                </div>
            </div>

            <div class="col-md-12 mt-3">
                <div class="row">
                    <label for="total_approved_day"
                        class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('total_days') }}</label>
                    <div class="col-sm-9 col-md-12 col-xl-9">
                        <input type="text" class="form-control" id="approved_total_day" name="total_approved_day"
                            placeholder="Total Days" value="{{ old('total_approved_day') ?? $row->total_apply_day }}"
                            readonly>
                    </div>

                    @if ($errors->has('total_approved_day'))
                        <div class="error text-danger m-2">{{ $errors->first('total_approved_day') }}</div>
                    @endif
                </div>
            </div>

            <div class="col-md-12 mt-3">
                <div class="row">
                    <label for="reject_reason"
                        class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('reject_reason', 'Reject reason') }}</label>
                    <div class="col-sm-9 col-md-12 col-xl-9">
                        <textarea class="form-control" name="reject_reason" id="reject_reason_{{ $row->id }}"
                            placeholder="{{ localize('required_when_rejecting', 'Required when rejecting') }}"></textarea>
                    </div>
                </div>
            </div>


        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">{{ localize('close') }}</button>
        <button class="btn btn-info submit_button" type="submit" name="decision_action" value="recommend">
            {{ localize('recommend_to_upper', 'Recommend to upper level') }}
        </button>
        <button class="btn btn-primary submit_button" type="submit" name="decision_action" value="approve"
            id="create_submit">{{ localize('approve_leave') }}</button>
        <button class="btn btn-outline-danger submit_button" type="submit"
            formaction="{{ route('leave.reject', $row->uuid) }}"
            onclick="
                var reason = document.getElementById('reject_reason_{{ $row->id }}');
                if (!reason || !reason.value.trim()) {
                    alert('{{ localize('reject_reason_is_required', 'Reject reason is required.') }}');
                    return false;
                }
            ">
            {{ localize('reject', 'Reject') }}
        </button>
    </div>
</form>

<script src="{{ asset('backend/assets/dist/js/custom.js') }}"></script>
<script src="{{ module_asset('HumanResource/js/hrcommon.js') }}"></script>
