@extends('backend.layouts.app')
@section('title', localize('retirement_management', 'Retirement management'))

@section('content')
    @include('humanresource::employee_header')
    @include('backend.layouts.common.validation')

    @php
        $retirementCandidates = $due_employees
            ->merge($upcoming_employees)
            ->unique('id')
            ->sortBy('retirement_date')
            ->values();
    @endphp

    <div class="card mb-3 fixed-tab-body">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h6 class="fs-17 fw-semi-bold mb-0">{{ localize('retirement_management', 'Retirement management') }}</h6>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <a href="{{ route('employee-retirements.report') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="fa fa-print me-1"></i>{{ localize('retirement_report', 'Retirement report') }}
                </a>
                <form method="GET" action="{{ route('employee-retirements.index') }}" class="d-flex gap-2 align-items-center">
                    <label for="months" class="small text-muted mb-0">{{ localize('upcoming_window_months', 'Upcoming window (months)') }}</label>
                    <input id="months" type="number" min="1" max="60" name="months" class="form-control form-control-sm"
                        value="{{ $upcoming_months }}" style="width: 90px;">
                    <button type="submit" class="btn btn-sm btn-primary">{{ localize('filter', 'Filter') }}</button>
                </form>
            </div>
        </div>

        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">{{ localize('retirement_due_now', 'Due for retirement now') }}</div>
                        <div class="fs-3 fw-bold text-danger">{{ $due_employees->count() }}</div>
                        <div class="small text-muted">{{ localize('as_of_date', 'As of date') }}: {{ $as_of }}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">{{ localize('retirement_upcoming', 'Upcoming retirement') }}</div>
                        <div class="fs-3 fw-bold text-warning">{{ $upcoming_employees->count() }}</div>
                        <div class="small text-muted">
                            {{ localize('next_n_months', 'Next :months months') }}: {{ $upcoming_months }}
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">{{ localize('retirement_processed_total', 'Processed retirement records') }}</div>
                        <div class="fs-3 fw-bold text-success">{{ $retirement_histories->count() }}</div>
                        <div class="small text-muted">{{ localize('retirement_age_policy', 'Retirement age policy') }}: {{ $retirement_age }}+</div>
                    </div>
                </div>
            </div>

            <div class="alert alert-info mb-3">
                {{ localize('retirement_info_note', 'When retirement is processed, the system automatically updates service state to inactive and records work/service history.') }}
            </div>

            <form action="{{ route('employee-retirements.store') }}" method="POST" class="border rounded p-3 mb-4">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="employee_id" class="form-label">
                            {{ localize('select_employee', 'Select employee') }} <span class="text-danger">*</span>
                        </label>
                        <select id="employee_id" name="employee_id" class="form-select" required>
                            <option value="">{{ localize('select_employee', 'Select employee') }}</option>
                            @foreach ($retirementCandidates as $employee)
                                <option value="{{ $employee->id }}"
                                    data-unit="{{ $employee->display_unit_path ?: $employee->display_unit_name }}"
                                    data-retirement-date="{{ $employee->retirement_date }}"
                                    data-days="{{ $employee->days_to_retirement }}"
                                    {{ (int) old('employee_id') === (int) $employee->id ? 'selected' : '' }}>
                                    {{ $employee->employee_id }} - {{ $employee->full_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="effective_date" class="form-label">
                            {{ localize('effective_date', 'Effective date') }} <span class="text-danger">*</span>
                        </label>
                        <input id="effective_date" type="date" name="effective_date" class="form-control"
                            value="{{ old('effective_date', $as_of) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label for="document_date" class="form-label">{{ localize('document_date', 'Document date') }}</label>
                        <input id="document_date" type="date" name="document_date" class="form-control"
                            value="{{ old('document_date') }}">
                    </div>

                    <div class="col-md-4">
                        <label for="document_reference" class="form-label">{{ localize('document_reference', 'Document reference') }}</label>
                        <input id="document_reference" type="text" name="document_reference" class="form-control"
                            value="{{ old('document_reference') }}" placeholder="Ex: 012/2026">
                    </div>
                    <div class="col-md-4">
                        <label for="current_unit_preview" class="form-label">{{ localize('current_unit', 'Current unit') }}</label>
                        <input id="current_unit_preview" type="text" class="form-control bg-light" value="-" readonly>
                    </div>
                    <div class="col-md-4">
                        <label for="retirement_date_preview" class="form-label">{{ localize('retirement_date', 'Retirement date') }}</label>
                        <input id="retirement_date_preview" type="text" class="form-control bg-light" value="-" readonly>
                    </div>

                    <div class="col-12">
                        <label for="note" class="form-label">{{ localize('note', 'Note') }}</label>
                        <input id="note" type="text" name="note" class="form-control" value="{{ old('note') }}"
                            placeholder="{{ localize('optional_note', 'Optional note') }}">
                    </div>
                </div>

                <div class="text-end mt-3">
                    <button type="submit" class="btn btn-danger">
                        <i class="fa fa-user-times me-1"></i> {{ localize('process_retirement', 'Process retirement') }}
                    </button>
                </div>
            </form>

            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="border rounded">
                        <div class="p-3 border-bottom">
                            <h6 class="mb-0">{{ localize('retirement_due_now', 'Due for retirement now') }}</h6>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th width="5%">#</th>
                                        <th>{{ localize('employee', 'Employee') }}</th>
                                        <th>{{ localize('retirement_date', 'Retirement date') }}</th>
                                        <th>{{ localize('unit', 'Unit') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($due_employees as $employee)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $employee->employee_id }} - {{ $employee->full_name }}</td>
                                            <td>{{ $employee->retirement_date }}</td>
                                            <td>{{ $employee->display_unit_path ?: $employee->display_unit_name }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-3">
                                                {{ localize('no_due_retirement_employee', 'No employee is currently due for retirement.') }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="border rounded">
                        <div class="p-3 border-bottom">
                            <h6 class="mb-0">{{ localize('retirement_upcoming', 'Upcoming retirement') }}</h6>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th width="5%">#</th>
                                        <th>{{ localize('employee', 'Employee') }}</th>
                                        <th>{{ localize('retirement_date', 'Retirement date') }}</th>
                                        <th>{{ localize('days_left', 'Days left') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($upcoming_employees as $employee)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $employee->employee_id }} - {{ $employee->full_name }}</td>
                                            <td>{{ $employee->retirement_date }}</td>
                                            <td>{{ $employee->days_to_retirement }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-3">
                                                {{ localize('no_upcoming_retirement_employee', 'No upcoming retirement employees in selected period.') }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="border rounded mt-4">
                <div class="p-3 border-bottom">
                    <h6 class="mb-0">{{ localize('retirement_history', 'Retirement history') }}</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped mb-0 align-middle">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th>{{ localize('employee', 'Employee') }}</th>
                                <th>{{ localize('event_date', 'Event date') }}</th>
                                <th>{{ localize('from_status', 'From status') }}</th>
                                <th>{{ localize('to_status', 'To status') }}</th>
                                <th>{{ localize('details', 'Details') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($retirement_histories as $history)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $history->employee?->employee_id }} - {{ $history->employee?->full_name ?? '-' }}</td>
                                    <td>{{ optional($history->event_date)->format('Y-m-d') ?: '-' }}</td>
                                    <td>{{ $history->from_value ?: '-' }}</td>
                                    <td>{{ $history->to_value ?: '-' }}</td>
                                    <td>{{ $history->details ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-3">
                                        {{ localize('no_retirement_history', 'No retirement records found yet.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js')
    <script>
        (function() {
            "use strict";

            var employeeSelect = document.getElementById('employee_id');
            var currentUnitPreview = document.getElementById('current_unit_preview');
            var retirementDatePreview = document.getElementById('retirement_date_preview');

            function refreshPreview() {
                if (!employeeSelect) {
                    return;
                }

                var option = employeeSelect.options[employeeSelect.selectedIndex];
                if (!option || !option.value) {
                    if (currentUnitPreview) currentUnitPreview.value = '-';
                    if (retirementDatePreview) retirementDatePreview.value = '-';
                    return;
                }

                if (currentUnitPreview) {
                    currentUnitPreview.value = option.getAttribute('data-unit') || '-';
                }

                if (retirementDatePreview) {
                    retirementDatePreview.value = option.getAttribute('data-retirement-date') || '-';
                }
            }

            employeeSelect && employeeSelect.addEventListener('change', refreshPreview);
            refreshPreview();
        })();

        (function($) {
            "use strict";
            if (!$ || !$.fn || !$.fn.select2) {
                return;
            }

            $('#employee_id').select2({
                width: '100%',
                allowClear: true,
                placeholder: "{{ localize('select_employee', 'Select employee') }}"
            });

            $('#employee_id').on('select2:select select2:clear', function() {
                this.dispatchEvent(new Event('change'));
            });
        })(window.jQuery);
    </script>
@endpush
