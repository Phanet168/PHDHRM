@extends('backend.layouts.app')
@section('title', localize('shift_roster', 'តារាង Roster'))
@section('content')
    @include('humanresource::attendance_header')

    <style>
        .roster-table-wrap { overflow-x: auto; }
        .roster-table { min-width: 1300px; }
        .roster-table th,
        .roster-table td { white-space: nowrap; vertical-align: middle; }
        .roster-table .sticky-col {
            position: sticky;
            left: 0;
            background: #fff;
            z-index: 3;
            min-width: 220px;
            box-shadow: 2px 0 0 rgba(0, 0, 0, 0.04);
        }
        .roster-badge { font-size: 11px; padding: 4px 8px; border-radius: 999px; display: inline-block; }
        .roster-shift { background: #e3f2fd; color: #0d47a1; }
        .roster-off { background: #fff3cd; color: #7a5b00; }
        .roster-holiday { background: #f3e5f5; color: #5b2d82; }
        .weekend-col { background: #fafafa; }
    </style>

    <div class="row g-3 ams-page">
        <div class="col-lg-9">
            <div class="card mb-3 ams-card att-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semi-bold ams-title">
                        <i class="fa fa-calendar-alt text-primary me-1"></i>
                        {{ localize('shift_roster_calendar', 'តារាង Roster ប្រចាំខែ') }}
                    </h6>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-2 mb-3 ams-filter-row">
                        <div class="col-md-2">
                            <label class="form-label">{{ localize('year', 'ឆ្នាំ') }}</label>
                            <select name="year" class="form-select">
                                @for ($y = now()->year - 2; $y <= now()->year + 1; $y++)
                                    <option value="{{ $y }}" @selected($selectedYear == $y)>{{ $y }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">{{ localize('month', 'ខែ') }}</label>
                            <select name="month" class="form-select">
                                @for ($m = 1; $m <= 12; $m++)
                                    <option value="{{ $m }}" @selected($selectedMonth == $m)>
                                        {{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}
                                    </option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">{{ localize('employee', 'បុគ្គលិក') }}</label>
                            <select name="employee_id" class="form-select select-basic-single">
                                <option value="">{{ localize('all', 'ទាំងអស់') }}</option>
                                @foreach ($employees as $emp)
                                    <option value="{{ $emp->id }}" @selected((string) $selectedEmployeeId === (string) $emp->id)>
                                        {{ $emp->full_name }} ({{ $emp->employee_id }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end gap-2">
                            <button class="btn btn-primary w-100 ams-btn-primary" type="submit">
                                <i class="fa fa-filter me-1"></i>{{ localize('filter', 'ស្វែងរក') }}
                            </button>
                        </div>
                    </form>

                    <div class="roster-table-wrap ams-table">
                        <table class="table table-bordered roster-table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="sticky-col">{{ localize('employee', 'បុគ្គលិក') }}</th>
                                    @foreach ($monthDays as $day)
                                        @php
                                            $dateObj = \Carbon\Carbon::create($selectedYear, $selectedMonth, $day);
                                            $isWeekend = $dateObj->isWeekend();
                                        @endphp
                                        <th class="text-center {{ $isWeekend ? 'weekend-col' : '' }}">{{ $day }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($displayEmployees as $emp)
                                    <tr>
                                        <td class="sticky-col">
                                            <div class="fw-semibold">{{ $emp->full_name }}</div>
                                            <small class="text-muted">{{ $emp->employee_id }}</small>
                                        </td>
                                        @foreach ($monthDays as $day)
                                            @php
                                                $cell = $rosterMap[$emp->id][$day] ?? null;
                                                $dateObj = \Carbon\Carbon::create($selectedYear, $selectedMonth, $day);
                                                $isWeekend = $dateObj->isWeekend();
                                            @endphp
                                            <td class="text-center {{ $isWeekend ? 'weekend-col' : '' }}">
                                                @if ($cell)
                                                    @if ($cell->is_holiday)
                                                        <span class="roster-badge roster-holiday" title="{{ localize('holiday', 'ថ្ងៃឈប់សម្រាក') }}">H</span>
                                                    @elseif ($cell->is_day_off)
                                                        <span class="roster-badge roster-off" title="{{ localize('day_off', 'ថ្ងៃឈប់') }}">OFF</span>
                                                    @elseif ($cell->shift)
                                                        <span class="roster-badge roster-shift"
                                                            title="{{ $cell->shift->name }} ({{ $cell->shift->start_time }} - {{ $cell->shift->end_time }})">
                                                            {{ $cell->shift->code ?: $cell->shift->name }}
                                                        </span>
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ 1 + count($monthDays) }}" class="text-center text-muted py-4">
                                            {{ localize('no_data', 'មិនមានទិន្នន័យ') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3">
            @can('create_shift_roster')
                <div class="card mb-3 border-primary ams-card att-card">
                    <div class="card-header bg-primary-soft">
                        <h6 class="mb-0 text-primary fw-semi-bold ams-title">
                            <i class="fa fa-plus-circle me-1"></i>{{ localize('set_roster', 'កំណត់ Roster') }}
                        </h6>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('shift-rosters.store') }}" method="POST">
                            @csrf
                            @include('backend.layouts.common.validation')

                            <div class="mb-2">
                                <label class="form-label">{{ localize('employee', 'បុគ្គលិក') }} <span class="text-danger">*</span></label>
                                <select name="employee_id" class="form-select select-basic-single" required>
                                    <option value="">{{ localize('select', 'ជ្រើសរើស') }}</option>
                                    @foreach ($employees as $emp)
                                        <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->employee_id }})</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-2">
                                <label class="form-label">{{ localize('date', 'កាលបរិច្ឆេទ') }} <span class="text-danger">*</span></label>
                                <input type="date" name="roster_date" class="form-control" required>
                            </div>

                            <div class="mb-2">
                                <label class="form-label">{{ localize('shift', 'Shift') }}</label>
                                <select name="shift_id" class="form-select select-basic-single">
                                    <option value="">{{ localize('none', 'មិនកំណត់') }}</option>
                                    @foreach ($shifts as $s)
                                        <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->code }})</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="is_day_off" name="is_day_off" value="1">
                                <label class="form-check-label" for="is_day_off">{{ localize('day_off', 'ថ្ងៃឈប់') }}</label>
                            </div>

                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="is_holiday" name="is_holiday" value="1">
                                <label class="form-check-label" for="is_holiday">{{ localize('holiday', 'ថ្ងៃឈប់សម្រាក') }}</label>
                            </div>

                            <div class="mb-2">
                                <label class="form-label">{{ localize('note', 'ចំណាំ') }}</label>
                                <textarea name="note" class="form-control" rows="2"></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 ams-btn-primary">
                                <i class="fa fa-save me-1"></i>{{ localize('save', 'រក្សាទុក') }}
                            </button>
                        </form>
                    </div>
                </div>
            @endcan
        </div>
    </div>
@endsection

@push('js')
    <script src="{{ module_asset('HumanResource/js/hrcommon.js') }}"></script>
@endpush
