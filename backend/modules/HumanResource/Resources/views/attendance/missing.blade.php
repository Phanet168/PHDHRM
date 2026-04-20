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
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group row">
                            <label for="date" class="col-md-4 col-form-label">{{ localize('date', 'ថ្ងៃ') }}
                                <span class="text-danger">*</span>
                            </label>
                            <div class="col-md-6">
                                <input type="date" name="date" id="date" class="form-control datepicker"
                                    placeholder="{{ localize('select_date') }}" value="{{ $date }}"
                                    autocomplete="off">
                            </div>
                            <div class="col-md-2 text-center">
                                <button type="submit" class="btn btn-success"
                                    autocomplete="off">{{ localize('search', 'ស្វែងរក') }}</button>
                            </div>
                        </div>
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
                                <th>{{ localize('employee_id', 'លេខបុគ្គលិក') }}</th>
                                <th>{{ localize('name', 'ឈ្មោះ') }}</th>
                                <th>{{ localize('designation', 'តួនាទី') }}</th>
                                <th>{{ localize('in_time', 'ម៉ោងចូល') }}</th>
                                <th>{{ localize('out_time', 'ម៉ោងចេញ') }}</th>
                                <th>{{ localize('date', 'ថ្ងៃ') }}</th>
                                <th>{{ localize('status', 'ស្ថានភាព') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($missingAttendance as $key => $value)
                                <tr>
                                    <td><input type="checkbox" name="employee_id[]" value="{{ $value->id }}"
                                            class="checkSingle"></td>
                                    <td>{{ $value->employee_id }}</td>
                                    <td>{{ $value->full_name }}</td>
                                    <td>{{ $value->position->position_name }}</td>
                                    <td><input type="time" class="form-control in_time" name="in_time[]" /></td>
                                    <td><input type="time" class="form-control out_time" name="out_time[]" /></td>
                                    <td>{{ $date }}</td>
                                    <td><span class="badge badge-danger-soft">{{ localize('absent', 'អវត្តមាន') }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="m-2">
                                <td colspan="8" class="text-end">
                                    <button class="btn btn-success" id="submit"><i class="fa fa-save me-1"></i>{{ localize('submit', 'រក្សាទុក') }}</button>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
@push('js')
    <script src="{{ module_asset('HumanResource/js/missing-attendance.js') }}"></script>
@endpush
