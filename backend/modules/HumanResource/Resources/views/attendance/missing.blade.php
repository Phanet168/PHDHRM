@extends('backend.layouts.app')
@section('title', localize('missing_attendance', 'បុគ្គលិករំលងការចូល-ចេញ'))
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
            <li class="nav-item">
                <a class="nav-link active py-1 px-3" href="{{ route('attendances.missingAttendance') }}">
                    <i class="fa fa-user-times me-1"></i>{{ localize('missing_attendance', 'បុគ្គលិករំលងការចូល-ចេញ') }}
                </a>
            </li>
            @can('read_attendance_adjustment')
                <li class="nav-item">
                    <a class="nav-link py-1 px-3" href="{{ route('attendance-adjustments.index') }}">
                        <i class="fa fa-edit me-1"></i>{{ localize('attendance_adjustments', 'ការកែប្រែ') }}
                    </a>
                </li>
            @endcan
        </ul>
    </div>

    <div class="card mb-4 fixed-tab-body att-card">
        @include('backend.layouts.common.validation')
        @include('backend.layouts.common.message')
        <input type="hidden" id="missingAttnStore" value="{{ route('attendances.missingAttendance.store') }}">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="fs-17 fw-semi-bold mb-0">
                        <i class="fa fa-user-times text-warning me-1"></i>
                        {{ localize('missing_attendance', 'បុគ្គលិករំលងការចូល-ចេញ') }}
                    </h6>
                </div>
                <div class="text-end">
                    <div class="actions">
                        <a href="{{ route('attendances.create') }}" class="btn btn-success btn-sm">
                            <i class="fa fa-plus-circle"></i>&nbsp;{{ localize('attendance_record', 'បញ្ចូលការចូល-ចេញ') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body">
            <form action="{{ route('attendances.missingAttendance') }}" method="GET">
                <div class="row align-items-end">
                    @include('humanresource::attendance.unit-filter')
                    <div class="col-md-4">
                        <label for="date" class="form-label">{{ localize('date', 'ថ្ងៃ') }}
                            <span class="text-danger">*</span>
                        </label>
                        <input type="date" name="date" id="date" class="form-control datepicker"
                            placeholder="{{ localize('select_date') }}" value="{{ $date }}"
                            autocomplete="off">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-success w-100"
                            autocomplete="off">{{ localize('search', 'ស្វែងរក') }}</button>
                    </div>
                </div>
            </form>
            <br>
            @if ($missingAttendance->count() > 0)
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>{{ localize('all', 'ទាំងអស់') }} <input type="checkbox" id="checkAll"></th>
                                <th>{{ localize('no', 'ល.រ') }}</th>
                                <th>{{ localize('employee_id', 'លេខបុគ្គលិក') }}</th>
                                <th>{{ localize('name', 'ឈ្មោះ') }}</th>
                                <th>{{ localize('designation', 'តួនាទី') }}</th>
                                <th style="min-width:220px;">{{ localize('type', 'ប្រភេទ') }}</th>
                                <th style="min-width:280px;">{{ localize('session_times', 'ម៉ោងកត់ត្រា (ព្រឹក/រសៀល/យប់)') }}</th>
                                <th>{{ localize('date', 'ថ្ងៃ') }}</th>
                                <th>{{ localize('status', 'ស្ថានភាព') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $currentDepth = 0; $employeeSeq = 0; @endphp
                            @foreach ($employeeRows as $row)
                                @if ($row['type'] === 'header')
                                    @php $currentDepth = $row['depth']; @endphp
                                    <tr class="table-light">
                                        <td colspan="9" class="fw-semibold text-primary" style="padding-left: {{ $row['depth'] * 14 }}px">
                                            {{ $row['label'] }}
                                            <span class="text-muted fw-normal small">(សរុប {{ $row['total'] }} | ប្រុស {{ $row['male'] }} | ស្រី {{ $row['female'] }})</span>
                                        </td>
                                    </tr>
                                @else
                                    @php
                                        $employeeSeq++;
                                        $value = $row['employee'];
                                        $unitId = $value->sub_department_id ?: $value->department_id;
                                        $rowShifts = $shiftsByDepartment->get($unitId, collect());
                                        $hasDutyShift = $rowShifts->contains('is_duty', true);
                                    @endphp
                                    <tr>
                                        <td><input type="checkbox" name="employee_id[]" value="{{ $value->id }}"
                                                class="checkSingle"></td>
                                        <td>{{ $employeeSeq }}</td>
                                        <td>{{ $value->employee_id }}</td>
                                        <td style="padding-left: {{ 8 + $currentDepth * 14 }}px">{{ $value->full_name }}</td>
                                        <td>{{ $value->position->position_name ?? '-' }}</td>
                                        <td>
                                            <select class="form-select form-select-sm row-type select-basic-single" name="type[]" style="width:100%;">
                                                <option value="in_out">{{ localize('clock_in_out', 'ចូល-ចេញ') }}</option>
                                                <option value="day_off">{{ localize('day_off', 'ថ្ងៃឈប់សម្រាក') }}</option>
                                                <option value="holiday">{{ localize('holiday', 'ថ្ងៃបុណ្យ') }}</option>
                                                @if ($rowShifts->isNotEmpty())
                                                    <option value="shift">{{ localize('assign_shift', 'ថ្ងៃចុះវេនយាម') }}</option>
                                                @endif
                                            </select>
                                            <div class="shift-select-wrap mt-1" style="display:none;">
                                                <select class="form-select form-select-sm shift-select select-basic-single" name="shift_id[]" style="width:100%;">
                                                    <option value="">{{ localize('select', 'ជ្រើសរើស') }}</option>
                                                    @foreach ($rowShifts as $s)
                                                        <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->code }})</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </td>
                                        <td class="session-times">
                                            <div class="d-flex align-items-center gap-1 mb-1">
                                                <span class="small text-muted" style="width:40px;">{{ localize('morning', 'ព្រឹក') }}</span>
                                                <input type="time" class="form-control form-control-sm session-in" data-session="morning" name="morning_in[]" />
                                                <input type="time" class="form-control form-control-sm session-out" data-session="morning" name="morning_out[]" />
                                            </div>
                                            <div class="d-flex align-items-center gap-1 mb-1">
                                                <span class="small text-muted" style="width:40px;">{{ localize('afternoon', 'រសៀល') }}</span>
                                                <input type="time" class="form-control form-control-sm session-in" data-session="afternoon" name="afternoon_in[]" />
                                                <input type="time" class="form-control form-control-sm session-out" data-session="afternoon" name="afternoon_out[]" />
                                            </div>
                                            @if ($hasDutyShift)
                                                <div class="d-flex align-items-center gap-1">
                                                    <span class="small text-muted" style="width:40px;">{{ localize('night', 'យប់') }}</span>
                                                    <input type="time" class="form-control form-control-sm session-in" data-session="night" name="night_in[]" />
                                                    <input type="time" class="form-control form-control-sm session-out" data-session="night" name="night_out[]" />
                                                </div>
                                            @endif
                                        </td>
                                        <td>{{ $date }}</td>
                                        <td><span class="badge badge-danger-soft">{{ localize('absent', 'អវត្តមាន') }}</span></td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="m-2">
                                <td colspan="9" class="text-end">
                                    <button class="btn btn-success" id="submit"><i class="fa fa-save me-1"></i>{{ localize('submit', 'រក្សាទុក') }}</button>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                    <p class="text-muted small">
                        <i class="fa fa-info-circle me-1"></i>
                        {{ localize('missing_attendance_leave_mission_note', 'ចំពោះការឈប់សម្រាកឬបេសកម្ម សូមកត់ត្រាតាមរយៈម៉ឺនុយ ច្បាប់ ឬ បេសកម្ម ជំនួសទំព័រនេះ។') }}
                    </p>
                </div>
            @endif
        </div>
    </div>
@endsection
@push('js')
    <script src="{{ module_asset('HumanResource/js/missing-attendance.js') }}"></script>
@endpush
