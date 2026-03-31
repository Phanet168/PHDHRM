@extends('backend.layouts.app')
@section('title', localize('retirement_report', 'Retirement report'))

@section('content')
    @include('humanresource::employee_header')

    <div class="card mb-3 fixed-tab-body">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2 no-print">
            <h6 class="fs-17 fw-semi-bold mb-0">{{ localize('retirement_report', 'Retirement report') }}</h6>
            <div class="d-flex gap-2">
                <a href="{{ route('employee-retirements.index') }}" class="btn btn-sm btn-secondary">
                    <i class="fa fa-arrow-left me-1"></i>{{ localize('back', 'Back') }}
                </a>
                <a href="{{ route('employee-retirements.report.export', ['year' => $report_year, 'forecast_years' => $forecast_years]) }}"
                    class="btn btn-sm btn-primary">
                    <i class="fa fa-file-excel-o me-1"></i>{{ localize('export_excel', 'Export Excel') }}
                </a>
                <a href="{{ route('employee-retirements.report.export-pdf', ['year' => $report_year, 'forecast_years' => $forecast_years, 'retired_scope' => $retired_scope]) }}"
                    class="btn btn-sm btn-danger">
                    <i class="fa fa-file-pdf-o me-1"></i>{{ localize('export_pdf', 'Export PDF') }}
                </a>
                <button type="button" class="btn btn-sm btn-success" onclick="window.print()">
                    <i class="fa fa-print me-1"></i>{{ localize('print', 'Print') }}
                </button>
            </div>
        </div>

        <div class="card-body">
            <div class="border rounded p-3 mb-3 no-print">
                <div class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <div class="small text-muted">{{ localize('current_template', 'Current template') }}</div>
                        <div class="fw-semibold">
                            {{ $retirement_template_name ?: localize('no_template_uploaded', 'No template uploaded (use default export).') }}
                        </div>
                        @if ($retirement_template_updated_at)
                            <div class="small text-muted">
                                {{ localize('last_updated', 'Last updated') }}: {{ $retirement_template_updated_at }}
                            </div>
                        @endif
                    </div>
                    <div class="col-md-7">
                        <form method="POST" action="{{ route('employee-retirements.report.template.upload') }}"
                            enctype="multipart/form-data" class="row g-2 align-items-end">
                            @csrf
                            <div class="col-md-8">
                                <label for="template_file" class="form-label mb-1">
                                    {{ localize('upload_template_xlsx', 'Upload template (.xlsx)') }}
                                </label>
                                <input id="template_file" name="template_file" type="file" class="form-control"
                                    accept=".xlsx" required>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-outline-primary w-100">
                                    <i class="fa fa-upload me-1"></i>{{ localize('upload_template', 'Upload template') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <form method="GET" action="{{ route('employee-retirements.report') }}" class="row g-3 align-items-end no-print mb-3">
                <div class="col-md-2">
                    <label for="year" class="form-label">{{ localize('year', 'Year') }}</label>
                    <input id="year" type="number" name="year" min="1900" max="2100" class="form-control"
                        value="{{ $report_year }}">
                </div>
                <div class="col-md-2">
                    <label for="forecast_years" class="form-label">{{ localize('forecast_years', 'Forecast years') }}</label>
                    <input id="forecast_years" type="number" name="forecast_years" min="1" max="10" class="form-control"
                        value="{{ $forecast_years }}">
                </div>
                <div class="col-md-3">
                    <label for="retired_scope" class="form-label">{{ localize('retired_records_scope', 'Retired records scope') }}</label>
                    <select id="retired_scope" name="retired_scope" class="form-select">
                        <option value="year" {{ $retired_scope === 'year' ? 'selected' : '' }}>
                            {{ localize('selected_year_only', 'Selected year only') }}
                        </option>
                        <option value="all" {{ $retired_scope === 'all' ? 'selected' : '' }}>
                            {{ localize('all_years', 'All years') }}
                        </option>
                    </select>
                </div>
                <div class="col-md-5 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-filter me-1"></i>{{ localize('filter', 'Filter') }}
                    </button>
                    <a href="{{ route('employee-retirements.report') }}" class="btn btn-outline-danger">
                        <i class="fa fa-refresh me-1"></i>{{ localize('reset', 'Reset') }}
                    </a>
                </div>
            </form>

            <div class="border rounded p-3 mb-3">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">{{ localize('retirement_list_for_year', 'Retirement list for year') }}</div>
                            <div class="fs-3 fw-bold text-primary">{{ $yearly_employees->count() }}</div>
                            <div class="small text-muted">{{ $report_year }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">{{ localize('retirement_forecast', 'Retirement forecast') }}</div>
                            <div class="fs-3 fw-bold text-warning">{{ $forecast_employees->count() }}</div>
                            <div class="small text-muted">{{ $forecast_start_year }} - {{ $forecast_end_year }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">{{ localize('already_retired_records', 'Already retired records') }}</div>
                            <div class="fs-3 fw-bold text-success">{{ $retired_records->count() }}</div>
                            <div class="small text-muted">
                                {{ localize('retirement_age_policy', 'Retirement age policy') }}: {{ $retirement_age }}+
                            </div>
                        </div>
                    </div>
                </div>
                <div class="small text-muted mt-3">
                    {{ localize('report_generated_on', 'Report generated on') }}: {{ $as_of }}
                </div>
            </div>

            <div class="border rounded mb-3">
                <div class="p-3 border-bottom">
                    <h6 class="mb-0">{{ localize('retirement_list_for_year', 'Retirement list for year') }}: {{ $report_year }}</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped table-sm mb-0 align-middle">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th>{{ localize('staff_id', 'Staff ID') }}</th>
                                <th>{{ localize('official_id_10', 'Official ID (10)') }}</th>
                                <th>{{ localize('employee_name', 'Employee name') }}</th>
                                <th>{{ localize('position', 'Position') }}</th>
                                <th>{{ localize('unit', 'Unit') }}</th>
                                <th>{{ localize('date_of_birth', 'Date of birth') }}</th>
                                <th>{{ localize('retirement_date', 'Retirement date') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($yearly_employees as $employee)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $employee->employee_id }}</td>
                                    <td>{{ $employee->official_id_10 ?: '-' }}</td>
                                    <td>{{ $employee->full_name }}</td>
                                    <td>{{ $employee->position?->position_name_km ?: ($employee->position?->position_name ?: '-') }}</td>
                                    <td>{{ $employee->display_unit_path ?: $employee->display_unit_name }}</td>
                                    <td>{{ $employee->date_of_birth }}</td>
                                    <td>{{ $employee->retirement_date }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-3">
                                        {{ localize('no_retirement_data_for_year', 'No retirement data found for selected year.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="border rounded mb-3">
                <div class="p-3 border-bottom">
                    <h6 class="mb-0">
                        {{ localize('retirement_forecast', 'Retirement forecast') }}:
                        {{ $forecast_start_year }} - {{ $forecast_end_year }}
                    </h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped table-sm mb-0 align-middle">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th>{{ localize('staff_id', 'Staff ID') }}</th>
                                <th>{{ localize('employee_name', 'Employee name') }}</th>
                                <th>{{ localize('unit', 'Unit') }}</th>
                                <th>{{ localize('retirement_date', 'Retirement date') }}</th>
                                <th>{{ localize('days_left', 'Days left') }}</th>
                                <th>{{ localize('retirement_year', 'Retirement year') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($forecast_employees as $employee)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $employee->employee_id }}</td>
                                    <td>{{ $employee->full_name }}</td>
                                    <td>{{ $employee->display_unit_path ?: $employee->display_unit_name }}</td>
                                    <td>{{ $employee->retirement_date }}</td>
                                    <td>{{ $employee->days_to_retirement }}</td>
                                    <td>{{ \Carbon\Carbon::parse($employee->retirement_date)->year }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-3">
                                        {{ localize('no_forecast_retirement_data', 'No forecast retirement data found.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="border rounded">
                <div class="p-3 border-bottom">
                    <h6 class="mb-0">{{ localize('already_retired_records', 'Already retired records') }}</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped table-sm mb-0 align-middle">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th>{{ localize('staff_id', 'Staff ID') }}</th>
                                <th>{{ localize('employee_name', 'Employee name') }}</th>
                                <th>{{ localize('event_date', 'Event date') }}</th>
                                <th>{{ localize('from_status', 'From status') }}</th>
                                <th>{{ localize('to_status', 'To status') }}</th>
                                <th>{{ localize('details', 'Details') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($retired_records as $record)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $record->employee?->employee_id ?: '-' }}</td>
                                    <td>{{ $record->employee?->full_name ?: '-' }}</td>
                                    <td>{{ optional($record->event_date)->format('Y-m-d') ?: '-' }}</td>
                                    <td>{{ $record->from_value ?: '-' }}</td>
                                    <td>{{ $record->to_value ?: '-' }}</td>
                                    <td>{{ $record->details ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-3">
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

@push('css')
    <style>
        @media print {
            .no-print,
            .dashboard_heading,
            .main-header,
            .main-sidebar,
            .main-footer {
                display: none !important;
            }

            .main-content {
                margin: 0 !important;
                padding: 0 !important;
            }

            .card,
            .border {
                border: 1px solid #ced4da !important;
            }
        }
    </style>
@endpush
