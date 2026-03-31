@extends('backend.layouts.app')
@section('title', localize('attendance_exceptions', 'Attendance exceptions'))
@section('content')
    @include('humanresource::attendance_header')
    <div class="card mb-4 fixed-tab-body">
        @include('backend.layouts.common.validation')
        @include('backend.layouts.common.message')
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="fs-17 fw-semi-bold mb-0">{{ localize('attendance_exceptions', 'Attendance exceptions') }}</h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <form action="{{ route('attendances.exceptions') }}" method="GET" class="mb-4">
                <div class="row">
                    <div class="col-md-4">
                        <label for="date" class="form-label">{{ localize('date') }}</label>
                        <input type="date" class="form-control" id="date" name="date" value="{{ $date }}">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-success w-100">{{ localize('search') }}</button>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead>
                        <tr>
                            <th>{{ localize('sl') }}</th>
                            <th>{{ localize('employee_id') }}</th>
                            <th>{{ localize('employee') }}</th>
                            <th>{{ localize('department') }}</th>
                            <th>{{ localize('date') }}</th>
                            <th>{{ localize('in_time') }}</th>
                            <th>{{ localize('out_time') }}</th>
                            <th>{{ localize('punch_count', 'Punch count') }}</th>
                            <th>{{ localize('reason') }}</th>
                            <th>{{ localize('action') }}</th>
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
                                <td>{{ $row->employee?->full_name ?? '-' }}</td>
                                <td>{{ $workplaceName }}</td>
                                <td>{{ \Carbon\Carbon::parse($row->attendance_date)->format('Y-m-d') }}</td>
                                <td>{{ \Carbon\Carbon::parse($row->first_punch)->format('H:i:s') }}</td>
                                <td>{{ \Carbon\Carbon::parse($row->last_punch)->format('H:i:s') }}</td>
                                <td>{{ $row->punch_count }}</td>
                                <td>
                                    @if ($row->exception_reason === 'UNPAIRED_PUNCH')
                                        <span class="badge badge-danger-soft">{{ localize('unpaired_punch', 'Unpaired punch') }}</span>
                                    @else
                                        <span class="badge badge-warning-soft">{{ $row->exception_reason ?? '-' }}</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('reports.attendance-log-details', $row->employee_id) }}"
                                        class="btn btn-primary btn-sm">
                                        <i class="fa fa-eye"></i> {{ localize('details') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center">{{ localize('no_data_found') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

