@extends('backend.layouts.app')
@section('title', localize('hr_dashboard', 'HR Dashboard'))

@section('content')
    <div class="body-content">
        <main class="flex-shrink-0">
            <div class="container-fluid p-4">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                            <h6 class="fs-17 fw-semi-bold mb-0">
                                {{ localize('dashboard_overview', 'Dashboard overview') }}
                            </h6>
                            <a href="{{ route('employee-pay-promotions.index', ['year' => $selected_year, 'tab' => 'summary']) }}"
                                class="btn btn-sm btn-outline-primary">
                                <i class="fa fa-line-chart me-1"></i>
                                {{ localize('open_promotion_module', 'Open promotion module') }}
                            </a>
                        </div>

                        <form action="{{ route('hr.dashboard') }}" method="get" class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <label for="dashboard_year" class="form-label mb-1">
                                    {{ localize('year', 'Year') }}
                                </label>
                                <input type="number" min="1950" max="2100" class="form-control"
                                    id="dashboard_year" name="year" value="{{ $selected_year }}">
                            </div>
                            <div class="col-md-5">
                                <label for="dashboard_department_id" class="form-label mb-1">
                                    {{ localize('department', 'Department') }}
                                </label>
                                <select name="department_id" id="dashboard_department_id" class="form-select">
                                    <option value="">{{ localize('all_department', 'All departments') }}</option>
                                    @foreach ($departments as $department)
                                        <option value="{{ $department->id }}"
                                            {{ (int) request('department_id') === (int) $department->id ? 'selected' : '' }}>
                                            {{ $department->department_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 d-flex gap-2">
                                <button type="submit" class="btn btn-success">
                                    <i class="fa fa-search me-1"></i>{{ localize('search', 'Search') }}
                                </button>
                                <a href="{{ route('hr.dashboard') }}" class="btn btn-outline-secondary">
                                    {{ localize('reset', 'Reset') }}
                                </a>
                            </div>
                        </form>

                        <div class="small text-muted mt-2">
                            {{ localize('promotion_cutoff_april_1', 'Promotion calculation cutoff date') }}:
                            <strong>{{ display_date($cutoff_date) }}</strong>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6 col-xl-3">
                        <div class="card h-100 border-start border-4 border-primary">
                            <div class="card-body">
                                <div class="text-muted small mb-1">{{ localize('total_staff', 'Total staff') }}</div>
                                <div class="fs-3 fw-bold">{{ $total_employee }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card h-100 border-start border-4 border-success">
                            <div class="card-body">
                                <div class="text-muted small mb-1">
                                    {{ localize('officers_due_for_pay_promotion', 'Officers due for pay promotion') }}
                                </div>
                                <div class="fs-3 fw-bold">{{ $due_pay_promotion_count }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card h-100 border-start border-4 border-info">
                            <div class="card-body">
                                <div class="text-muted small mb-1">
                                    {{ localize('officers_due_for_position_promotion', 'Officers due for rank/position promotion') }}
                                </div>
                                <div class="fs-3 fw-bold">{{ $due_position_promotion_count }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card h-100 border-start border-4 border-warning">
                            <div class="card-body">
                                <div class="text-muted small mb-1">
                                    {{ localize('pending_requests', 'Pending requests') }}
                                </div>
                                <div class="fs-3 fw-bold">{{ $pending_request_count }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <div class="card h-100 border-start border-4 border-secondary">
                            <div class="card-body">
                                <div class="text-muted small mb-1">
                                    {{ localize('approved_requests', 'Approved requests') }}
                                </div>
                                <div class="fs-3 fw-bold">{{ $approved_request_count }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card h-100 border-start border-4 border-danger">
                            <div class="card-body">
                                <div class="text-muted small mb-1">
                                    {{ localize('rejected_requests', 'Rejected requests') }}
                                </div>
                                <div class="fs-3 fw-bold">{{ $rejected_request_count }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card h-100 border-start border-4 border-dark">
                            <div class="card-body">
                                <div class="text-muted small mb-1">
                                    {{ localize('upcoming_notifications', 'Upcoming notifications (3 months)') }}
                                </div>
                                <div class="fs-3 fw-bold">{{ $notification_count }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card h-100 border-start border-4 border-success-subtle">
                            <div class="card-body">
                                <div class="text-muted small mb-1">
                                    {{ localize('contract_renewal_in_60_days', 'Contract renewal in 60 days') }}
                                </div>
                                <div class="fs-3 fw-bold">{{ $contract_renew_employees }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-lg-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h6 class="mb-0">{{ localize('today_attendance', "Today's attendance") }}</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="todays_attendance" height="220"></canvas>
                                <div class="row text-center mt-3 g-2">
                                    <div class="col-6">
                                        <div class="p-2 bg-light rounded">
                                            <div class="small text-muted">{{ localize('present', 'Present') }}</div>
                                            <div class="fw-bold">{{ $today_attenedence }}</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-2 bg-light rounded">
                                            <div class="small text-muted">{{ localize('absent', 'Absent') }}</div>
                                            <div class="fw-bold">{{ $today_absense }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-8">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <h6 class="mb-0">{{ localize('pending_promotion_requests', 'Pending promotion requests') }}</h6>
                                <a href="{{ route('employee-pay-promotions.index', ['year' => $selected_year, 'tab' => 'pending']) }}"
                                    class="btn btn-sm btn-outline-primary">
                                    {{ localize('view_all', 'View all') }}
                                </a>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>{{ localize('employee', 'Employee') }}</th>
                                                <th>{{ localize('unit', 'Unit') }}</th>
                                                <th>{{ localize('requested_pay_level', 'Requested pay level') }}</th>
                                                <th>{{ localize('effective_date', 'Effective date') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($pending_requests_preview as $requestItem)
                                                <tr>
                                                    <td>{{ $loop->iteration }}</td>
                                                    <td>
                                                        {{ $requestItem->employee?->employee_id ?: '-' }} -
                                                        {{ $requestItem->employee?->full_name ?: '-' }}
                                                    </td>
                                                    <td>
                                                        {{ $requestItem->employee?->sub_department?->department_name
                                                            ?: ($requestItem->employee?->department?->department_name ?: '-') }}
                                                    </td>
                                                    <td>
                                                        {{ trim((string) ($requestItem->payLevel?->level_name_km ?? '')) !== ''
                                                            ? $requestItem->payLevel?->level_name_km
                                                            : ($requestItem->payLevel?->level_code ?? '-') }}
                                                    </td>
                                                    <td>{{ display_date($requestItem->start_date) }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted py-3">
                                                        {{ localize('empty_data', 'No data found') }}
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <h6 class="mb-0">{{ localize('deadline_notifications', 'Deadline notifications') }}</h6>
                                <a href="{{ route('employee-pay-promotions.index', ['year' => $selected_year, 'tab' => 'summary']) }}"
                                    class="btn btn-sm btn-outline-secondary">
                                    {{ localize('open_summary', 'Open summary') }}
                                </a>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>{{ localize('employee', 'Employee') }}</th>
                                                <th>{{ localize('unit', 'Unit') }}</th>
                                                <th>{{ localize('promotion_type', 'Promotion type') }}</th>
                                                <th>{{ localize('due_date', 'Due date') }}</th>
                                                <th>{{ localize('days_left', 'Days left') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($due_notifications as $row)
                                                <tr>
                                                    <td>{{ $loop->iteration }}</td>
                                                    <td>{{ data_get($row, 'employee_code', '-') }} - {{ data_get($row, 'full_name', '-') }}</td>
                                                    <td>{{ data_get($row, 'unit_name', '-') }}</td>
                                                    <td>
                                                        @if(data_get($row, 'promotion_type') === 'position')
                                                            <span class="badge bg-info">{{ localize('position_promotion', 'Position promotion') }}</span>
                                                        @else
                                                            <span class="badge bg-success">{{ localize('pay_promotion', 'Pay promotion') }}</span>
                                                        @endif
                                                    </td>
                                                    <td>{{ display_date(data_get($row, 'due_date')) }}</td>
                                                    <td>{{ data_get($row, 'days_left', '-') }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted py-3">
                                                        {{ localize('no_upcoming_notifications', 'No upcoming notifications for next 3 months') }}
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <input type="hidden" id="today_attenedence" value="{{ $today_attenedence }}">
    <input type="hidden" id="today_absence" value="{{ $today_absense }}">
    <input type="hidden" id="today_label_present" value="{{ localize('present', 'Present') }}">
    <input type="hidden" id="today_label_absent" value="{{ localize('absent', 'Absent') }}">
@endsection

@push('js')
    <script src="{{ module_asset('HumanResource/js/index.js') }}"></script>
@endpush
