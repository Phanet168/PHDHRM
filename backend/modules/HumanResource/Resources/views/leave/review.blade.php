@extends('backend.layouts.app')
@section('title', localize('leave_review', 'Leave Review'))

@section('content')
    @include('humanresource::leave_header')

    <div class="card mb-4">
        @include('backend.layouts.common.validation')
        @include('backend.layouts.common.message')

        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h6 class="fs-17 fw-semi-bold mb-0">{{ localize('leave_review', 'Leave Review') }}</h6>
                <div class="small text-muted mt-1">
                    {{ $leave->employee?->full_name }} | {{ $leave->leaveType?->display_name }} |
                    {{ $leave->leave_apply_start_date }} - {{ $leave->leave_apply_end_date }}
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('leave.approval') }}" class="btn btn-outline-secondary btn-sm">
                    {{ localize('back', 'Back') }}
                </a>
                @if (($leave->workflow_status ?? '') === 'approved' || (int) $leave->is_approved === 1)
                    <a href="{{ route('leave.print', $leave->uuid) }}" class="btn btn-success btn-sm">
                        {{ localize('print', 'Print') }}
                    </a>
                @endif
            </div>
        </div>

        <div class="card-body">
            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="border rounded-3 p-3 bg-light h-100 shadow-sm">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="fw-semibold">{{ localize('request_details', 'Request details') }}</div>
                            <span class="badge bg-secondary-subtle text-dark">
                                {{ $leave->workflow_status ?: '-' }}
                            </span>
                        </div>

                        <div class="row g-3 small">
                            <div class="col-12">
                                <div class="text-muted">{{ localize('employee_name') }}</div>
                                <div class="fw-semibold">{{ $leave->employee?->full_name ?: '-' }}</div>
                            </div>
                            <div class="col-12">
                                <div class="text-muted">{{ localize('department', 'Department') }}</div>
                                <div class="fw-semibold">{{ $leave->employee?->primaryUnitPosting?->department?->department_name ?: '-' }}</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted">{{ localize('type') }}</div>
                                <div class="fw-semibold">{{ $leave->leaveType?->display_name ?: '-' }}</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted">{{ localize('days') }}</div>
                                <div class="fw-semibold">{{ $leave->total_apply_day ?: '-' }}</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted">{{ localize('leave_start_date') }}</div>
                                <div class="fw-semibold">{{ $leave->leave_apply_start_date ?: '-' }}</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted">{{ localize('leave_end_date') }}</div>
                                <div class="fw-semibold">{{ $leave->leave_apply_end_date ?: '-' }}</div>
                            </div>
                            <div class="col-12">
                                <div class="text-muted">{{ localize('replacement_employee', 'Replacement employee') }}</div>
                                <div class="fw-semibold">{{ $leave->handoverEmployee?->full_name ?: '-' }}</div>
                            </div>
                            <div class="col-12">
                                <div class="text-muted">{{ localize('reason') }}</div>
                                <div class="fw-semibold">{{ $leave->reason ?: '-' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="border rounded-3 p-3 shadow-sm">
                        <div class="fw-semibold mb-3">{{ localize('decision_form', 'Decision form') }}</div>
                        @php($row = $leave)
                        @include('humanresource::leave.approveleave')
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
