@extends('backend.layouts.app')
@section('title', localize('daily_snapshot', 'ទិន្នន័យការចូល-ចេញប្រចាំថ្ងៃ'))
@section('content')
    @include('humanresource::attendance_header')

    <div class="card mb-4 fixed-tab-body">
        @include('backend.layouts.common.validation')
        @include('backend.layouts.common.message')

        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="fs-17 fw-semi-bold mb-0">
                <i class="fa fa-table text-primary me-1"></i>
                {{ localize('daily_snapshot', 'ទិន្នន័យការចូល-ចេញប្រចាំថ្ងៃ') }}
            </h6>
            <div class="d-flex gap-2">
                {{-- Regenerate button --}}
                @can('create_attendance_snapshot')
                    <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#regenerateModal">
                        <i class="fa fa-sync me-1"></i>{{ localize('regenerate', 'បង្កើតឡើងវិញ') }}
                    </button>
                @endcan
            </div>
        </div>

        <div class="card-body">
            {{-- Filter --}}
            <form action="{{ route('attendance-snapshots.daily') }}" method="GET" class="mb-4">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">{{ localize('date', 'ថ្ងៃ') }}</label>
                        <input type="date" class="form-control" name="date" value="{{ $selectedDate }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">{{ localize('employee', 'បុគ្គលិក') }}</label>
                        <select name="employee_id" class="form-control select-basic-single">
                            <option value="">{{ localize('all_employees', 'បុគ្គលិកទាំងអស់') }}</option>
                            @foreach($employees as $emp)
                                <option value="{{ $emp->id }}" @selected($selectedEmployeeId == $emp->id)>
                                    {{ $emp->full_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-success flex-grow-1">
                            <i class="fa fa-search me-1"></i>{{ localize('search', 'ស្វែងរក') }}
                        </button>
                        <a href="{{ route('attendance-snapshots.daily') }}" class="btn btn-outline-secondary">
                            <i class="fa fa-redo"></i>
                        </a>
                    </div>
                </div>
            </form>

            {{-- Status legend --}}
            <div class="d-flex flex-wrap gap-2 mb-3">
                <span class="badge badge-success-soft px-3 py-1">P — {{ localize('present', 'មានវត្តមាន') }}</span>
                <span class="badge badge-danger-soft px-3 py-1">A — {{ localize('absent', 'អវត្តមាន') }}</span>
                <span class="badge badge-warning-soft px-3 py-1">L — {{ localize('late', 'យឺត') }}</span>
                <span class="badge badge-info-soft px-3 py-1">M — {{ localize('mission', 'បេសកម្ម') }}</span>
                <span class="badge badge-primary-soft px-3 py-1">LV — {{ localize('leave', 'ច្បាប់') }}</span>
                <span class="badge badge-secondary-soft px-3 py-1">H — {{ localize('holiday', 'ថ្ងៃបុណ្យ') }}</span>
                <span class="badge bg-light text-dark border px-3 py-1">O — {{ localize('day_off', 'ថ្ងៃឈប់') }}</span>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>{{ localize('sl', 'លរ') }}</th>
                            <th>{{ localize('employee', 'ឈ្មោះបុគ្គលិក') }}</th>
                            <th>{{ localize('date', 'ថ្ងៃ') }}</th>
                            <th>{{ localize('status', 'ស្ថានភាព') }}</th>
                            <th>{{ localize('in_time', 'ម៉ោងចូល') }}</th>
                            <th>{{ localize('out_time', 'ម៉ោងចេញ') }}</th>
                            <th>{{ localize('worked_hours', 'ម៉ោងធ្វើការ') }}</th>
                            <th>{{ localize('late_min', 'យឺត (នាទី)') }}</th>
                            <th>{{ localize('early_leave_min', 'ចេញមុន (នាទី)') }}</th>
                            <th>{{ localize('computed_at', 'គណនានៅ') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($snapshots as $snap)
                            @php
                                $statusCode = strtoupper($snap->attendance_status ?? '');
                                $statusBadge = match($statusCode) {
                                    'PRESENT'  => 'badge-success-soft',
                                    'ABSENT'   => 'badge-danger-soft',
                                    'LATE'     => 'badge-warning-soft',
                                    'MISSION'  => 'badge-info-soft',
                                    'LEAVE'    => 'badge-primary-soft',
                                    'HOLIDAY'  => 'badge-secondary-soft',
                                    'DAY_OFF'  => 'bg-light text-dark border',
                                    default    => 'badge-secondary-soft',
                                };
                                $statusLabel = match($statusCode) {
                                    'PRESENT'  => localize('present', 'P — មានវត្តមាន'),
                                    'ABSENT'   => localize('absent', 'A — អវត្តមាន'),
                                    'LATE'     => localize('late', 'L — យឺត'),
                                    'MISSION'  => localize('mission', 'M — បេសកម្ម'),
                                    'LEAVE'    => localize('leave', 'LV — ច្បាប់'),
                                    'HOLIDAY'  => localize('holiday', 'H — ថ្ងៃបុណ្យ'),
                                    'DAY_OFF'  => localize('day_off', 'O — ថ្ងៃឈប់'),
                                    default    => $snap->attendance_status ?? '-',
                                };
                                $workedHours = $snap->worked_minutes ? round($snap->worked_minutes / 60, 1) . 'h' : '-';
                            @endphp
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $snap->employee?->full_name ?? '-' }}</div>
                                    <small class="text-muted">{{ $snap->employee?->employee_id ?? '' }}</small>
                                </td>
                                <td>{{ \Carbon\Carbon::parse($snap->snapshot_date)->format('d/m/Y') }}</td>
                                <td><span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span></td>
                                <td>{{ $snap->in_time ? \Carbon\Carbon::parse($snap->in_time)->format('H:i') : '-' }}</td>
                                <td>{{ $snap->out_time ? \Carbon\Carbon::parse($snap->out_time)->format('H:i') : '-' }}</td>
                                <td>{{ $workedHours }}</td>
                                <td>
                                    @if($snap->late_minutes > 0)
                                        <span class="text-warning fw-semibold">{{ $snap->late_minutes }}</span>
                                    @else
                                        <span class="text-muted">0</span>
                                    @endif
                                </td>
                                <td>
                                    @if($snap->early_leave_minutes > 0)
                                        <span class="text-danger fw-semibold">{{ $snap->early_leave_minutes }}</span>
                                    @else
                                        <span class="text-muted">0</span>
                                    @endif
                                </td>
                                <td class="small text-muted">
                                    {{ $snap->computed_at ? \Carbon\Carbon::parse($snap->computed_at)->format('d/m H:i') : '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-4 text-muted">
                                    <i class="fa fa-inbox me-2"></i>{{ localize('no_snapshot_data', 'មិនមានទិន្នន័យ Snapshot ទេ') }}
                                    <br>
                                    <small>{{ localize('try_regenerate', 'សាកល្បងចុច "បង្កើតឡើងវិញ" ដើម្បីគណនា') }}</small>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Regenerate Modal --}}
    @can('create_attendance_snapshot')
        <div class="modal fade" id="regenerateModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('attendance-snapshots.regenerate') }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fa fa-sync me-1"></i>{{ localize('regenerate_snapshots', 'បង្កើត Snapshots ឡើងវិញ') }}
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-warning">
                                <i class="fa fa-exclamation-triangle me-1"></i>
                                {{ localize('regenerate_warning', 'ការបង្កើតឡើងវិញនឹងគណនា snapshot ថ្មីសម្រាប់រយៈពេលដែលជ្រើសរើស') }}
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">{{ localize('start_date', 'ចាប់ពីថ្ងៃ') }} <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="start_date" value="{{ $selectedDate }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">{{ localize('end_date', 'ដល់ថ្ងៃ') }} <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="end_date" value="{{ $selectedDate }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">{{ localize('employees', 'បុគ្គលិក') }}</label>
                                <select name="employee_ids[]" class="form-control select-basic-single" multiple>
                                    @foreach($employees as $emp)
                                        <option value="{{ $emp->id }}">{{ $emp->full_name }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">{{ localize('leave_blank_for_all', 'ទុកចោលប្រសិនបើចង់គណនាទាំងអស់') }}</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                {{ localize('cancel', 'ច្រាំ') }}
                            </button>
                            <button type="submit" class="btn btn-warning">
                                <i class="fa fa-sync me-1"></i>{{ localize('regenerate', 'បង្កើតឡើងវិញ') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endcan
@endsection

@push('js')
    <script src="{{ module_asset('HumanResource/js/hrcommon.js') }}"></script>
@endpush
