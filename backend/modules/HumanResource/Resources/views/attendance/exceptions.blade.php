@extends('backend.layouts.app')
@section('title', localize('attendance_exceptions', 'ករណីពុំប្រក្រតី'))
@section('content')
    @include('humanresource::attendance_header')

    {{-- Sub-tab bar: Exceptions / Missing / Adjustments --}}
    <div class="card fixed-tab mb-2">
        <ul class="nav nav-pills px-3 py-2 gap-1">
            <li class="nav-item">
                <a class="nav-link active py-1 px-3" href="{{ route('attendances.exceptions') }}">
                    <i class="fa fa-exclamation-circle me-1"></i>{{ localize('attendance_exceptions', 'ករណីពុំប្រក្រតី') }}
                </a>
            </li>
            @can('read_missing_attendance')
                <li class="nav-item">
                    <a class="nav-link py-1 px-3" href="{{ route('attendances.missingAttendance') }}">
                        <i class="fa fa-user-times me-1"></i>{{ localize('missing_attendance', 'បុគ្គលិករំលងការចូល-ចេញ') }}
                    </a>
                </li>
            @endcan
            @can('read_attendance_adjustment')
                <li class="nav-item">
                    <a class="nav-link py-1 px-3" href="{{ route('attendance-adjustments.index') }}">
                        <i class="fa fa-edit me-1"></i>{{ localize('attendance_adjustments', 'ការកែប្រែ') }}
                    </a>
                </li>
            @endcan
        </ul>
    </div>

    <div class="card mb-4 fixed-tab-body">
        @include('backend.layouts.common.validation')
        @include('backend.layouts.common.message')
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="fs-17 fw-semi-bold mb-0">
                    <i class="fa fa-exclamation-triangle text-warning me-1"></i>
                    {{ localize('attendance_exceptions', 'ករណីពុំប្រក្រតី') }}
                </h6>
                <span class="badge badge-danger-soft fs-13">
                    {{ localize('total', 'សរុប') }}: {{ $exceptions->count() }}
                </span>
            </div>
        </div>
        <div class="card-body">
            {{-- Filter: date range + exception type --}}
            <form action="{{ route('attendances.exceptions') }}" method="GET" class="mb-4">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label for="date_from" class="form-label fw-semibold">{{ localize('date_from', 'ចាប់ពីថ្ងៃ') }}</label>
                        <input type="date" class="form-control" id="date_from" name="date_from"
                            value="{{ request('date_from', $date ?? date('Y-m-d')) }}">
                    </div>
                    <div class="col-md-3">
                        <label for="date_to" class="form-label fw-semibold">{{ localize('date_to', 'ដល់ថ្ងៃ') }}</label>
                        <input type="date" class="form-control" id="date_to" name="date_to"
                            value="{{ request('date_to', $date ?? date('Y-m-d')) }}">
                    </div>
                    <div class="col-md-3">
                        <label for="exception_type" class="form-label fw-semibold">{{ localize('exception_type', 'ប្រភេទករណី') }}</label>
                        <select class="form-control" id="exception_type" name="exception_type">
                            <option value="">{{ localize('all', 'ទាំងអស់') }}</option>
                            <option value="UNPAIRED_PUNCH" @selected(request('exception_type') === 'UNPAIRED_PUNCH')>{{ localize('unpaired_punch', 'Punch គូរគ្នាមិនបាន') }}</option>
                            <option value="MISSING_OUT" @selected(request('exception_type') === 'MISSING_OUT')>{{ localize('missing_out', 'ខ្វះម៉ោងចេញ') }}</option>
                            <option value="MISSING_IN" @selected(request('exception_type') === 'MISSING_IN')>{{ localize('missing_in', 'ខ្វះម៉ោងចូល') }}</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-success flex-grow-1">
                            <i class="fa fa-search me-1"></i>{{ localize('search', 'ស្វែងរក') }}
                        </button>
                        <a href="{{ route('attendances.exceptions') }}" class="btn btn-outline-secondary">
                            <i class="fa fa-redo"></i>
                        </a>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>{{ localize('sl', 'លរ') }}</th>
                            <th>{{ localize('employee_id', 'លេខបុគ្គលិក') }}</th>
                            <th>{{ localize('employee', 'ឈ្មោះបុគ្គលិក') }}</th>
                            <th>{{ localize('department', 'អង្គភាព') }}</th>
                            <th>{{ localize('date', 'ថ្ងៃ') }}</th>
                            <th>{{ localize('in_time', 'ម៉ោងចូល') }}</th>
                            <th>{{ localize('out_time', 'ម៉ោងចេញ') }}</th>
                            <th>{{ localize('punch_count', 'ចំនួន Punch') }}</th>
                            <th>{{ localize('exception_type', 'ប្រភេទករណី') }}</th>
                            <th>{{ localize('action', 'សកម្មភាព') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($exceptions as $row)
                            @php
                                $workplaceName =
                                    $row->workplace?->department_name ??
                                    $row->employee?->sub_department?->department_name ??
                                    $row->employee?->department?->department_name ??
                                    '-';
                            @endphp
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $row->employee?->employee_id ?? '-' }}</td>
                                <td class="fw-semibold">{{ $row->employee?->full_name ?? '-' }}</td>
                                <td>{{ $workplaceName }}</td>
                                <td>{{ \Carbon\Carbon::parse($row->attendance_date)->format('d/m/Y') }}</td>
                                <td>{{ $row->first_punch ? \Carbon\Carbon::parse($row->first_punch)->format('H:i') : '-' }}</td>
                                <td>{{ $row->last_punch ? \Carbon\Carbon::parse($row->last_punch)->format('H:i') : '-' }}</td>
                                <td class="text-center">
                                    <span class="badge {{ $row->punch_count % 2 !== 0 ? 'badge-danger-soft' : 'badge-warning-soft' }}">
                                        {{ $row->punch_count }}
                                    </span>
                                </td>
                                <td>
                                    @if ($row->exception_reason === 'UNPAIRED_PUNCH')
                                        <span class="badge badge-danger-soft">{{ localize('unpaired_punch', 'Punch គូរគ្នាមិនបាន') }}</span>
                                    @elseif ($row->exception_reason === 'MISSING_OUT')
                                        <span class="badge badge-warning-soft">{{ localize('missing_out', 'ខ្វះម៉ោងចេញ') }}</span>
                                    @elseif ($row->exception_reason === 'MISSING_IN')
                                        <span class="badge badge-warning-soft">{{ localize('missing_in', 'ខ្វះម៉ោងចូល') }}</span>
                                    @else
                                        <span class="badge badge-warning-soft">{{ $row->exception_reason ?? '-' }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="{{ route('reports.attendance-log-details', $row->employee_id) }}"
                                            class="btn btn-primary btn-sm" title="{{ localize('view_details', 'មើលលម្អិត') }}">
                                            <i class="fa fa-eye"></i>
                                        </a>
                                        @can('create_attendance_adjustment')
                                            <a href="{{ route('attendance-adjustments.index', ['employee_id' => $row->employee_id, 'date' => \Carbon\Carbon::parse($row->attendance_date)->format('Y-m-d'), 'mode' => 'create']) }}"
                                                class="btn btn-warning btn-sm" title="{{ localize('create_adjustment', 'ចុះបញ្ជីកែ') }}">
                                                <i class="fa fa-edit"></i> {{ localize('adjust', 'កែ') }}
                                            </a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-4 text-muted">
                                    <i class="fa fa-check-circle text-success me-2"></i>{{ localize('no_exceptions_found', 'មិនមានករណីពុំប្រក្រតីទេ') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

