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
<div class="modal fade leave-request-modal" id="addLeaveApplication" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
    aria-labelledby="staticBackdropLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-fullscreen-lg-down modal-dialog-scrollable leave-request-modal-dialog">
        <div class="modal-content border-0 shadow overflow-hidden">
            <div class="modal-header leave-request-modal-header border-bottom px-4 py-3">
                <div>
                    <h5 class="modal-title mb-1" id="staticBackdropLabel">
                        {{ localize('leave_application_create') }}
                    </h5>
                    <div class="small text-muted">
                        {{ localize('leave_application_form_hint', 'សូមបំពេញព័ត៌មានសំណើសុំច្បាប់ឲ្យបានត្រឹមត្រូវ') }}
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form class="validateForm leave-application-form" action="{{ route('leave.store') }}" method="POST"
                data-leave-balance-url="{{ route('leave.balance') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-body leave-request-modal-body text-start px-4 py-3">
                    @php($selectedEmployee = $employees->firstWhere('id', $currentEmployeeId ?? 0))
                    @include('humanresource::leave._form_fields', ['selectedEmployee' => $selectedEmployee])
                </div>
                <div class="modal-footer leave-request-modal-footer border-top bg-white px-4 py-3">
                    <button type="button" class="btn btn-outline-secondary"
                        data-bs-dismiss="modal">{{ localize('close') }}</button>
                    <button class="btn btn-primary submit_button">{{ localize('save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    (function () {
        if (typeof window.initLeaveFormSelects === 'function') {
            window.initLeaveFormSelects($('#addLeaveApplication'));
        }
    })();
</script>
