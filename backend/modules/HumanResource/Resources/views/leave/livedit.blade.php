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
<form class="validateEditForm leave-application-form" action="{{ route('leave.update', $row->uuid) }}" method="POST"
    data-leave-balance-url="{{ route('leave.balance') }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    <div class="modal-body leave-request-modal-body px-4 py-3">
        @php($selectedEmployee = $employees->firstWhere('id', $currentEmployeeId ?? $row->employee_id))
        @include('humanresource::leave._form_fields', ['isEditMode' => true, 'selectedEmployee' => $selectedEmployee])
        <input type="hidden" name="oldlocation" value="{{ $row->location }}">
        <input type="hidden" name="leave_uuid" value="{{ $row->uuid }}">
    </div>
    <div class="modal-footer leave-request-modal-footer border-top bg-white px-4 py-3">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ localize('close') }}</button>
        <button class="btn btn-primary submit_button" id="create_submit">{{ localize('update') }}</button>
    </div>
</form>
<script>
    (function () {
        if (typeof window.initLeaveFormSelects === 'function') {
            window.initLeaveFormSelects($('#edit-application'));
        }
    })();
</script>
