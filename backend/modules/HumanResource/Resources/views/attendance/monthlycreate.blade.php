@extends('backend.layouts.app')
@section('title', localize('monthly_attendance', 'ការចូល-ចេញប្រចាំខែ'))

@push('css')
<style>
    .monthly-grid-table th,
    .monthly-grid-table td { font-size: 0.78rem; padding: 0.3rem 0.35rem; white-space: nowrap; }
    .monthly-grid-table th.day-col { min-width: 30px; width: 30px; text-align: center; }
    .monthly-grid-table td.day-cell { text-align: center; cursor: default; }
    .status-P  { background: #d1fae5; color: #065f46; font-weight: 600; border-radius: 3px; padding: 1px 4px; }
    .status-A  { background: #fee2e2; color: #991b1b; font-weight: 600; border-radius: 3px; padding: 1px 4px; }
    .status-L  { background: #fef3c7; color: #92400e; font-weight: 600; border-radius: 3px; padding: 1px 4px; }
    .status-M  { background: #dbeafe; color: #1e40af; font-weight: 600; border-radius: 3px; padding: 1px 4px; }
    .status-LV { background: #ede9fe; color: #4c1d95; font-weight: 600; border-radius: 3px; padding: 1px 4px; }
    .status-H  { background: #f3f4f6; color: #374151; font-weight: 600; border-radius: 3px; padding: 1px 4px; }
    .status-O  { background: #f9fafb; color: #6b7280; border-radius: 3px; padding: 1px 4px; }
    .status-NA { color: #d1d5db; font-size: 0.7rem; }
    .summary-col { background: #f8fafc; font-weight: 600; min-width: 42px; }
    .sticky-col { position: sticky; left: 0; background: #fff; z-index: 2; box-shadow: 2px 0 4px rgba(0,0,0,0.05); }
    .sticky-col-2 { position: sticky; left: 120px; background: #fff; z-index: 2; }
    .weekend-col { background: #f9fafb; }
</style>
@endpush

@section('content')
    @include('humanresource::attendance_header')

    <div class="card mb-3 fixed-tab-body">
        @include('backend.layouts.common.validation')
        @include('backend.layouts.common.message')

        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="fs-17 fw-semi-bold mb-0">
                <i class="fa fa-calendar-alt text-primary me-1"></i>
                {{ localize('monthly_attendance', 'ការចូល-ចេញប្រចាំខែ') }}
                — {{ \Carbon\Carbon::create($selectedYear, $selectedMonth)->locale('km')->translatedFormat('F Y') }}
            </h6>
            <div class="d-flex gap-2">
                @can('create_monthly_attendance')
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#bulkImportModal">
                        <i class="fa fa-file-excel me-1"></i>{{ localize('import_excel', 'នាំចូល Excel') }}
                    </button>
                    <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#manualEntryModal">
                        <i class="fa fa-plus-circle me-1"></i>{{ localize('manual_entry', 'បញ្ចូលដោយដៃ') }}
                    </button>
                @endcan
                @can('create_attendance_snapshot')
                    <a href="{{ route('attendance-snapshots.daily', ['date' => \Carbon\Carbon::create($selectedYear, $selectedMonth, 1)->toDateString()]) }}"
                        class="btn btn-sm btn-outline-secondary">
                        <i class="fa fa-table me-1"></i>{{ localize('view_snapshots', 'មើល Snapshots') }}
                    </a>
                @endcan
            </div>
        </div>

        <div class="card-body">
            {{-- Filter --}}
            <form action="{{ route('attendances.monthlyCreate') }}" method="GET" class="mb-3">
                <div class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label fw-semibold small">{{ localize('year', 'ឆ្នាំ') }}</label>
                        <select name="year" class="form-control">
                            @for($y = now()->year; $y >= 2020; $y--)
                                <option value="{{ $y }}" @selected($y === $selectedYear)>{{ $y }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold small">{{ localize('month', 'ខែ') }}</label>
                        <select name="month" class="form-control">
                            @php
                                $khMonths = ['មករា','កុម្ភៈ','មីនា','មេសា','ឧសភា','មិថុនា','កក្កដា','សីហា','កញ្ញា','តុលា','វិច្ឆិកា','ធ្នូ'];
                            @endphp
                            @foreach($khMonths as $mi => $mName)
                                <option value="{{ $mi + 1 }}" @selected(($mi + 1) === $selectedMonth)>{{ $mName }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">{{ localize('employee', 'បុគ្គលិក') }}</label>
                        <select name="employee_id" class="form-control select-basic-single">
                            <option value="">{{ localize('all_employees', 'បុគ្គលិកទាំងអស់') }}</option>
                            @foreach($employees as $emp)
                                <option value="{{ $emp->id }}" @selected($selectedEmployeeId == $emp->id)>{{ $emp->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-success flex-grow-1">
                            <i class="fa fa-search me-1"></i>{{ localize('search', 'ស្វែងរក') }}
                        </button>
                        <a href="{{ route('attendances.monthlyCreate') }}" class="btn btn-outline-secondary">
                            <i class="fa fa-redo"></i>
                        </a>
                    </div>
                </div>
            </form>

            {{-- Legend --}}
            <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
                <small class="text-muted fw-semibold me-1">{{ localize('legend', 'ពណ៌') }}:</small>
                <span class="status-P">P</span><small class="me-2">{{ localize('present', 'វត្តមាន') }}</small>
                <span class="status-A">A</span><small class="me-2">{{ localize('absent', 'អវត្តមាន') }}</small>
                <span class="status-L">L</span><small class="me-2">{{ localize('late', 'យឺត') }}</small>
                <span class="status-M">M</span><small class="me-2">{{ localize('mission', 'បេសកម្ម') }}</small>
                <span class="status-LV">LV</span><small class="me-2">{{ localize('leave', 'ច្បាប់') }}</small>
                <span class="status-H">H</span><small class="me-2">{{ localize('holiday', 'ថ្ងៃបុណ្យ') }}</small>
                <span class="status-O">O</span><small class="me-2">{{ localize('day_off', 'ថ្ងៃឈប់') }}</small>
            </div>

            {{-- Monthly Grid --}}
            <div class="table-responsive" style="overflow-x: auto;">
                <table class="table table-bordered monthly-grid-table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="sticky-col" style="min-width:120px;">{{ localize('employee', 'ឈ្មោះ') }}</th>
                            <th class="sticky-col-2" style="min-width:80px;">{{ localize('dept', 'អង្គភាព') }}</th>
                            @foreach($monthDays as $day)
                                @php
                                    $dayOfWeek = \Carbon\Carbon::create($selectedYear, $selectedMonth, $day)->dayOfWeek;
                                    $isWeekend = $dayOfWeek === 0 || $dayOfWeek === 6;
                                @endphp
                                <th class="day-col {{ $isWeekend ? 'weekend-col' : '' }}">
                                    {{ $day }}<br>
                                    <small style="color:#9ca3af">{{ \Carbon\Carbon::create($selectedYear, $selectedMonth, $day)->format('D')[0] }}</small>
                                </th>
                            @endforeach
                            <th class="summary-col text-center">P</th>
                            <th class="summary-col text-center">A</th>
                            <th class="summary-col text-center">L</th>
                            <th class="summary-col text-center">M</th>
                            <th class="summary-col text-center" title="{{ localize('leave', 'ច្បាប់') }}">LV</th>
                            <th class="summary-col text-center" title="{{ localize('worked_hours', 'ម៉ោង') }}">h</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($displayEmployees as $emp)
                            @php
                                $empSnapshots = $snapshotMap->get($emp->id, collect());
                                $countP = $countA = $countL = $countM = $countLV = 0;
                                $totalMinutes = 0;
                            @endphp
                            <tr>
                                <td class="sticky-col fw-semibold" style="min-width:120px;">
                                    {{ $emp->full_name }}<br>
                                    <small class="text-muted">{{ $emp->employee_id }}</small>
                                </td>
                                <td class="sticky-col-2 small text-muted" style="min-width:80px;">
                                    {{ $emp->sub_department?->department_name ?? $emp->department?->department_name ?? '-' }}
                                </td>
                                @foreach($monthDays as $day)
                                    @php
                                        $snap = $empSnapshots->get($day);
                                        $dayOfWeek = \Carbon\Carbon::create($selectedYear, $selectedMonth, $day)->dayOfWeek;
                                        $isWeekend = $dayOfWeek === 0 || $dayOfWeek === 6;
                                        $statusCode = strtoupper($snap?->attendance_status ?? '');
                                        if (!$snap) {
                                            $cellClass = 'status-NA'; $cellLabel = '·';
                                        } else {
                                            [$cellClass, $cellLabel] = match($statusCode) {
                                                'PRESENT'  => ['status-P', 'P'],
                                                'ABSENT'   => ['status-A', 'A'],
                                                'LATE'     => ['status-L', 'L'],
                                                'MISSION'  => ['status-M', 'M'],
                                                'LEAVE'    => ['status-LV', 'LV'],
                                                'HOLIDAY'  => ['status-H', 'H'],
                                                'DAY_OFF'  => ['status-O', 'O'],
                                                default    => ['status-NA', '?'],
                                            };
                                        }
                                        if ($statusCode === 'PRESENT') $countP++;
                                        elseif ($statusCode === 'ABSENT') $countA++;
                                        elseif ($statusCode === 'LATE') { $countP++; $countL++; }
                                        elseif ($statusCode === 'MISSION') $countM++;
                                        elseif ($statusCode === 'LEAVE') $countLV++;
                                        $totalMinutes += (int) ($snap?->worked_minutes ?? 0);
                                    @endphp
                                    <td class="day-cell {{ $isWeekend ? 'weekend-col' : '' }}">
                                        @if($snap)
                                            <span class="{{ $cellClass }}"
                                                title="{{ $snap->in_time ? \Carbon\Carbon::parse($snap->in_time)->format('H:i') : '' }} — {{ $snap->out_time ? \Carbon\Carbon::parse($snap->out_time)->format('H:i') : '' }}">
                                                {{ $cellLabel }}
                                            </span>
                                        @else
                                            <span class="status-NA">·</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="summary-col text-center text-success">{{ $countP ?: '—' }}</td>
                                <td class="summary-col text-center text-danger">{{ $countA ?: '—' }}</td>
                                <td class="summary-col text-center text-warning">{{ $countL ?: '—' }}</td>
                                <td class="summary-col text-center text-info">{{ $countM ?: '—' }}</td>
                                <td class="summary-col text-center text-primary">{{ $countLV ?: '—' }}</td>
                                <td class="summary-col text-center text-muted">
                                    {{ $totalMinutes > 0 ? round($totalMinutes / 60, 1) : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 2 + $daysInMonth + 6 }}" class="text-center py-4 text-muted">
                                    <i class="fa fa-inbox me-2"></i>{{ localize('no_data_found', 'មិនមានទិន្នន័យទេ') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <p class="text-muted small mt-2">
                <i class="fa fa-info-circle me-1"></i>
                {{ localize('monthly_grid_note', 'ទិន្នន័យបង្ហាញចេញពី Attendance Snapshots — ប្រើ "Snapshots" ខាងក្រោមដើម្បីបង្កើតឡើងវិញ') }}
            </p>
        </div>
    </div>

    {{-- Manual Entry Modal (kept for bulk insert of same-time attendance) --}}
    @can('create_monthly_attendance')
        <div class="modal fade" id="manualEntryModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form id="attendance" action="{{ route('attendances.monthlyStore') }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fa fa-plus-circle me-1"></i>{{ localize('manual_entry', 'បញ្ចូលការចូល-ចេញដោយដៃ') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">{{ localize('employee', 'បុគ្គលិក') }} <span class="text-danger">*</span></label>
                                <select name="employee_id" class="form-control select-basic-single" required>
                                    <option value="">{{ localize('select_one', 'ជ្រើសរើស') }}</option>
                                    @foreach($employee as $e)
                                        <option value="{{ $e->id }}">{{ $e->full_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">{{ localize('year', 'ឆ្នាំ') }} <span class="text-danger">*</span></label>
                                    <select name="year" class="form-control" required>
                                        @for($y = now()->year; $y >= 2020; $y--)
                                            <option value="{{ $y }}" @selected($y === $selectedYear)>{{ $y }}</option>
                                        @endfor
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">{{ localize('month', 'ខែ') }} <span class="text-danger">*</span></label>
                                    <select name="month" class="form-control" required>
                                        @foreach($khMonths ?? ['មករា','កុម្ភៈ','មីនា','មេសា','ឧសភា','មិថុនា','កក្កដា','សីហា','កញ្ញា','តុលា','វិច្ឆិកា','ធ្នូ'] as $mi => $mName)
                                            <option value="{{ $mi + 1 }}" @selected(($mi + 1) === $selectedMonth)>{{ $mName }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="row g-2 mt-1">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">{{ localize('time_in', 'ម៉ោងចូល') }} <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" name="in_time" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">{{ localize('time_out', 'ម៉ោងចេញ') }} <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" name="out_time" required>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ localize('cancel', 'ច្រាំ') }}</button>
                            <button type="submit" class="btn btn-success"><i class="fa fa-save me-1"></i>{{ localize('submit', 'រក្សាទុក') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Bulk Import Modal --}}
        @include('humanresource::attendance.monthly-attendance-bulk-import')
    @endcan
@endsection

@push('js')
    <script src="{{ module_asset('HumanResource/js/hrcommon.js') }}"></script>
@endpush
