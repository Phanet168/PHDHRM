@extends('backend.layouts.app')
@section('title', localize('attendance_adjustments', 'ការកែប្រែការចូល-ចេញ'))
@section('content')
    @include('humanresource::attendance_header')

    {{-- Sub-tab bar: Exceptions / Missing / Adjustments --}}
    <div class="card fixed-tab att-card mb-2">
        <ul class="nav nav-pills px-3 py-2 gap-1">
            @can('read_attendance')
                <li class="nav-item">
                    <a class="nav-link py-1 px-3" href="{{ route('attendances.exceptions') }}">
                        <i class="fa fa-exclamation-circle me-1"></i>{{ localize('attendance_exceptions', 'ករណីពុំប្រក្រតី') }}
                    </a>
                </li>
            @endcan
            @can('read_missing_attendance')
                <li class="nav-item">
                    <a class="nav-link py-1 px-3" href="{{ route('attendances.missingAttendance') }}">
                        <i class="fa fa-user-times me-1"></i>{{ localize('missing_attendance', 'បុគ្គលិករំលងការចូល-ចេញ') }}
                    </a>
                </li>
            @endcan
            <li class="nav-item">
                <a class="nav-link active py-1 px-3" href="{{ route('attendance-adjustments.index') }}">
                    <i class="fa fa-edit me-1"></i>{{ localize('attendance_adjustments', 'ការកែប្រែ') }}
                </a>
            </li>
        </ul>
    </div>

    <div class="row g-3 ams-page">
        {{-- Left: List --}}
        <div class="col-lg-8">
            <div class="card mb-4 ams-card att-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="fs-17 fw-semi-bold mb-0 ams-title">
                        <i class="fa fa-edit text-primary me-1"></i>
                        {{ localize('attendance_adjustment_list', 'បញ្ជីការស្នើសុំកែប្រែ') }}
                    </h6>
                    <span class="badge badge-primary-soft fs-13">
                        {{ localize('total', 'សរុប') }}: {{ $adjustments->total() ?? 0 }}
                    </span>
                </div>
                <div class="card-body">
                    {{-- Filter --}}
                    <form action="{{ route('attendance-adjustments.index') }}" method="GET" class="mb-3">
                        <div class="row g-2 align-items-end ams-filter-row">
                            <div class="col-md-3">
                                <label class="form-label fw-semibold small">{{ localize('employee', 'បុគ្គលិក') }}</label>
                                <select name="employee_id" class="form-control select-basic-single">
                                    <option value="">{{ localize('all', 'ទាំងអស់') }}</option>
                                    @foreach($employees ?? [] as $emp)
                                        <option value="{{ $emp->id }}" @selected(request('employee_id') == $emp->id)>
                                            {{ $emp->full_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold small">{{ localize('date_from', 'ចាប់ពីថ្ងៃ') }}</label>
                                <input type="date" class="form-control" name="date_from" value="{{ request('date_from') }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold small">{{ localize('date_to', 'ដល់ថ្ងៃ') }}</label>
                                <input type="date" class="form-control" name="date_to" value="{{ request('date_to') }}">
                            </div>
                            <div class="col-md-3 d-flex gap-2">
                                <button type="submit" class="btn btn-success flex-grow-1 ams-btn-primary">
                                    <i class="fa fa-search me-1"></i>{{ localize('search', 'ស្វែងរក') }}
                                </button>
                                <a href="{{ route('attendance-adjustments.index') }}" class="btn btn-outline-secondary ams-btn-secondary">
                                    <i class="fa fa-redo"></i>
                                </a>
                            </div>
                        </div>
                    </form>

                    <div class="table-responsive ams-table">
                        <table class="table table-bordered table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ localize('sl', 'លរ') }}</th>
                                    <th>{{ localize('employee', 'បុគ្គលិក') }}</th>
                                    <th>{{ localize('date', 'ថ្ងៃ') }}</th>
                                    <th>{{ localize('new_time', 'ម៉ោងថ្មី') }}</th>
                                    <th>{{ localize('punch_type', 'ប្រភេទ') }}</th>
                                    <th>{{ localize('reason', 'មូលហេតុ') }}</th>
                                    <th>{{ localize('requested_by', 'ស្នើដោយ') }}</th>
                                    <th>{{ localize('action', 'សកម្មភាព') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($adjustments ?? [] as $adj)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>
                                            <div class="fw-semibold">{{ $adj->employee?->full_name ?? '-' }}</div>
                                            <small class="text-muted">{{ $adj->employee?->employee_id ?? '' }}</small>
                                        </td>
                                        <td>
                                            {{ $adj->attendance?->attendance_date
                                                ? \Carbon\Carbon::parse($adj->attendance->attendance_date)->format('d/m/Y')
                                                : '-' }}
                                        </td>
                                        <td>{{ $adj->new_time ? \Carbon\Carbon::parse($adj->new_time)->format('H:i') : '-' }}</td>
                                        <td>
                                            @if($adj->new_machine_state === 'in')
                                                <span class="badge badge-success-soft">{{ localize('check_in', 'ចូល') }}</span>
                                            @elseif($adj->new_machine_state === 'out')
                                                <span class="badge badge-danger-soft">{{ localize('check_out', 'ចេញ') }}</span>
                                            @else
                                                <span class="badge badge-secondary-soft">-</span>
                                            @endif
                                        </td>
                                        <td class="text-muted small">{{ Str::limit($adj->reason, 60) }}</td>
                                        <td class="small">{{ $adj->createdBy?->name ?? '-' }}</td>
                                        <td>
                                            @can('create_attendance_adjustment')
                                                <button type="button" class="btn btn-danger btn-sm btn-delete-adj"
                                                    data-id="{{ $adj->id }}"
                                                    title="{{ localize('delete', 'លុប') }}">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                            @endcan
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">
                                            <i class="fa fa-inbox me-2"></i>{{ localize('no_data_found', 'មិនមានទិន្នន័យទេ') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if(isset($adjustments) && $adjustments->hasPages())
                        <div class="mt-3">{{ $adjustments->appends(request()->query())->links() }}</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Right: Create Form --}}
        @can('create_attendance_adjustment')
            <div class="col-lg-4">
                <div class="card mb-4 border-primary ams-card att-card">
                    <div class="card-header bg-primary-soft">
                        <h6 class="fs-15 fw-semi-bold mb-0 text-primary ams-title">
                            <i class="fa fa-plus-circle me-1"></i>{{ localize('create_adjustment', 'ចុះបញ្ជីការកែប្រែថ្មី') }}
                        </h6>
                    </div>
                    <div class="card-body">
                        <form id="adjustmentForm" action="{{ route('attendance-adjustments.store') }}" method="POST">
                            @csrf

                            <div class="mb-3">
                                <label class="form-label fw-semibold">{{ localize('employee', 'បុគ្គលិក') }} <span class="text-danger">*</span></label>
                                <select name="employee_id" id="adj_employee_id" class="form-control select-basic-single" required>
                                    <option value="">{{ localize('select_one', 'ជ្រើសរើស') }}</option>
                                    @foreach($employees ?? [] as $emp)
                                        <option value="{{ $emp->id }}"
                                            @selected(old('employee_id', request('employee_id')) == $emp->id)>
                                            {{ $emp->full_name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('employee_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">{{ localize('date', 'ថ្ងៃ') }}</label>
                                <input type="date" class="form-control" name="adj_date" id="adj_date"
                                    value="{{ old('adj_date', request('date', date('Y-m-d'))) }}">
                                <small class="text-muted">{{ localize('select_date_to_load_records', 'ជ្រើសរើសថ្ងៃដើម្បីផ្ទុក records') }}</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">{{ localize('attendance_record_ref', 'Record ដែលត្រូវកែ') }}</label>
                                <select name="attendance_id" id="adj_attendance_id" class="form-control">
                                    <option value="">{{ localize('select_employee_first', 'ជ្រើសរើសបុគ្គលិកមុន') }}</option>
                                </select>
                                @error('attendance_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">{{ localize('new_time', 'ម៉ោងថ្មី') }}</label>
                                <input type="time" class="form-control" name="new_time" value="{{ old('new_time') }}">
                                @error('new_time')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">{{ localize('punch_type', 'ប្រភេទ Punch') }}</label>
                                <select name="new_machine_state" class="form-control">
                                    <option value="">{{ localize('no_change', 'មិនផ្លាស់ប្ដូរ') }}</option>
                                    <option value="in" @selected(old('new_machine_state') === 'in')>{{ localize('check_in', 'ចូល (IN)') }}</option>
                                    <option value="out" @selected(old('new_machine_state') === 'out')>{{ localize('check_out', 'ចេញ (OUT)') }}</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">{{ localize('reason', 'មូលហេតុ') }} <span class="text-danger">*</span></label>
                                <textarea name="reason" class="form-control" rows="3" required
                                    placeholder="{{ localize('enter_reason', 'សរសេរមូលហេតុ...') }}">{{ old('reason') }}</textarea>
                                @error('reason')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>

                            <button type="submit" class="btn btn-primary w-100 ams-btn-primary">
                                <i class="fa fa-save me-1"></i>{{ localize('submit', 'រក្សាទុក') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @endcan
    </div>
@endsection

@push('js')
    <script src="{{ module_asset('HumanResource/js/hrcommon.js') }}"></script>
    <script>
        // Load attendance records for selected employee + date
        const adjEmployeeSelect = document.getElementById('adj_employee_id');
        const adjDateInput = document.getElementById('adj_date');
        const adjAttendanceSelect = document.getElementById('adj_attendance_id');

        function loadAttendanceRecords() {
            const empId = adjEmployeeSelect?.value;
            const date = adjDateInput?.value;
            if (!empId || !date) return;

            fetch(`/api/v1/attendance-adjustments?employee_id=${empId}&date=${date}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
            .then(res => res.json())
            .then(data => {
                adjAttendanceSelect.innerHTML = '<option value="">{{ localize('select_record', 'ជ្រើសរើស Record') }}</option>';
                const records = data.data ?? data.records ?? [];
                if (records.length === 0) {
                    adjAttendanceSelect.innerHTML += '<option disabled>{{ localize('no_records_on_date', 'គ្មាន Record នៅថ្ងៃនោះ') }}</option>';
                }
                records.forEach(r => {
                    const time = r.punch_time ?? r.time ?? '';
                    const state = r.machine_state ?? '';
                    adjAttendanceSelect.innerHTML += `<option value="${r.id}">[${state.toUpperCase()}] ${time}</option>`;
                });
            })
            .catch(() => {
                adjAttendanceSelect.innerHTML = '<option value="">{{ localize('select_one', 'ជ្រើសរើស') }}</option>';
            });
        }

        adjEmployeeSelect?.addEventListener('change', loadAttendanceRecords);
        adjDateInput?.addEventListener('change', loadAttendanceRecords);

        // Pre-fill if URL params passed (from Exceptions "កែ" button)
        @if(request('mode') === 'create' && request('employee_id'))
            document.addEventListener('DOMContentLoaded', () => {
                if (adjEmployeeSelect) {
                    adjEmployeeSelect.value = '{{ request('employee_id') }}';
                    // Trigger Select2 if used
                    if (typeof $ !== 'undefined') $(adjEmployeeSelect).trigger('change.select2');
                    loadAttendanceRecords();
                }
            });
        @endif
    </script>
@endpush
