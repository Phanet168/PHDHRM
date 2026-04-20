@extends('backend.layouts.app')
@section('title', localize('leave_type_list'))
@push('css')
@endpush
@section('content')

    @include('humanresource::leave_header')
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

    <div class="card mb-4 fixed-tab-body att-card">
        @include('backend.layouts.common.validation')
        @include('backend.layouts.common.message')
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="fs-17 fw-semi-bold mb-0">{{ localize('leave_type_list') }}</h6>
                </div>
                <div class="text-end">
                    <div class="actions">
                        @can('create_leave_type')
                            <a href="#" class="btn btn-success btn-sm" data-bs-toggle="modal"
                                data-bs-target="#create-leave-type"><i
                                    class="fa fa-plus-circle"></i>&nbsp;{{ localize('add_leave_type') }}</a>
                            @include('humanresource::leave.leave-type.modal.create')
                        @endcan
                    </div>
                </div>

            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="example" class="table display table-bordered table-striped table-hover">
                    <thead>
                        <tr>
                            <th width="5%">{{ localize('sl') }}</th>
                            <th width="20%">{{ localize('leave_type') }}</th>
                            <th width="8%">{{ localize('leave_code') }}</th>
                            <th width="14%">{{ localize('leave_policy', 'គោលនយោបាយ') }}</th>
                            <th width="14%">{{ localize('entitlement', 'សិទ្ធិឈប់') }}</th>
                            <th width="10%">{{ localize('paid_status', 'បៀវត្ស') }}</th>
                            <th width="10%">{{ localize('document_requirement', 'ឯកសារភ្ជាប់') }}</th>
                            <th width="19%">{{ localize('action') }}</th>

                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dbData as $key => $data)
                            <tr>
                                <td>{{ $key + 1 }}</td>
                                <td>
                                    <div>{{ $data->display_name }}</div>
                                    @if (!empty($data->leave_type_km) && !empty($data->leave_type))
                                        <small class="text-muted">{{ $data->leave_type }}</small>
                                    @endif
                                </td>
                                <td><code>{{ $data->leave_code }}</code></td>
                                <td>{{ $policyLabels[$data->policy_key] ?? ($data->policy_key ?: '-') }}</td>
                                <td>
                                    @php
                                        $value = $data->entitlement_value ?? $data->leave_days;
                                        $unit = $unitLabels[$data->entitlement_unit] ?? ($data->entitlement_unit ?: '');
                                        $scope = $scopeLabels[$data->entitlement_scope] ?? ($data->entitlement_scope ?: '');
                                    @endphp
                                    {{ $value !== null ? rtrim(rtrim((string) $value, '0'), '.') : '-' }}
                                    {{ $unit }} / {{ $scope }}
                                </td>
                                <td>
                                    @if ((bool) $data->is_paid)
                                        <span class="badge bg-success">{{ localize('paid', 'មានបៀវត្ស') }}</span>
                                    @else
                                        <span class="badge bg-warning text-dark">{{ localize('unpaid', 'គ្មានបៀវត្ស') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ((bool) $data->requires_attachment || (bool) $data->requires_medical_certificate)
                                        <span class="badge bg-info">
                                            {{ localize('required', 'តម្រូវ') }}
                                        </span>
                                    @else
                                        <span class="badge bg-secondary">{{ localize('not_required', 'មិនតម្រូវ') }}</span>
                                    @endif
                                </td>

                                <td>
                                    @can('update_leave_type')
                                        <a href="#" class="btn btn-primary-soft btn-sm me-1" data-bs-toggle="modal"
                                            data-bs-target="#update-leave-type-{{ $data->id }}" title="Edit"><i
                                                class="fa fa-edit"></i></a>
                                        @include('humanresource::leave.leave-type.modal.edit')
                                    @endcan
                                    @can('delete_leave_type')
                                        <a href="javascript:void(0)" class="btn btn-danger-soft btn-sm delete-confirm"
                                            data-bs-toggle="tooltip" title="Delete"
                                            data-route="{{ route('leave-types.destroy', $data->uuid) }}"
                                            data-csrf="{{ csrf_token() }}"><i class="fa fa-trash"></i></a>
                                    @endcan
                                </td>
                            </tr>

                        @empty
                            <tr>
                                <td colspan="7" class="text-center">{{ localize('empty_data') }}</td>
                            </tr>
                        @endforelse

                    </tbody>
                </table>
            </div>
        </div>
    </div>

@endsection
@push('js')
    <script src="{{ module_asset('HumanResource/js/hrcommon.js') }}"></script>
@endpush
