@extends('backend.layouts.app')
@section('title', localize('shift_roster', 'តារាង Roster'))
@section('content')
    @include('humanresource::attendance_header')
    @include('backend.layouts.common.message')
    <p class="text-muted">តារាងវេនរបស់អង្គភាពដែលបានជ្រើសរើស។ ចុចលើថ្ងៃដើម្បីកំណត់ ឬកែប្រែវេន។ វេនយាមមានអាទិភាពលើថ្ងៃឈប់ និងម៉ោងគោល។</p>
    <a class="btn btn-outline-primary mb-3" href="{{ route('shifts.index', ['department_id' => $selectedDepartmentId]) }}">កំណត់ម៉ោងធ្វើការ</a>

    <style>
        .roster-table-wrap { overflow-x: auto; }
        .roster-table { min-width: 1300px; }
        .roster-table th,
        .roster-table td { white-space: nowrap; vertical-align: middle; }
        .roster-table .sticky-col-num {
            position: sticky;
            left: 0;
            background: #fff;
            z-index: 3;
            min-width: 40px;
            text-align: center;
        }
        .roster-table .sticky-col {
            position: sticky;
            left: 40px;
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
                        @include('humanresource::attendance.unit-filter')
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
                                    <th class="sticky-col-num">{{ localize('no', 'ល.រ') }}</th>
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
                                @php $currentDepth = 0; $employeeSeq = 0; @endphp
                                @forelse ($displayEmployeeRows as $row)
                                    @if ($row['type'] === 'header')
                                        @php $currentDepth = $row['depth']; @endphp
                                        <tr class="table-light">
                                            <td colspan="{{ 2 + count($monthDays) }}" class="fw-semibold text-primary" style="padding-left: {{ $row['depth'] * 14 }}px">
                                                {{ $row['label'] }}
                                                <span class="text-muted fw-normal small">(សរុប {{ $row['total'] }} | ប្រុស {{ $row['male'] }} | ស្រី {{ $row['female'] }})</span>
                                            </td>
                                        </tr>
                                    @else
                                        @php $emp = $row['employee']; $employeeSeq++; @endphp
                                        <tr>
                                            <td class="sticky-col-num">{{ $employeeSeq }}</td>
                                            <td class="sticky-col" style="padding-left: {{ 8 + $currentDepth * 14 }}px">
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
                                                    @can('create_shift_roster')
                                                    <button type="button" class="btn btn-sm btn-link roster-edit" aria-label="កែប្រែវេន {{ $emp->full_name }} ថ្ងៃ {{ $day }}"
                                                        data-employee="{{ $emp->id }}" data-date="{{ $dateObj->toDateString() }}" data-shift="{{ $cell?->shift_id }}" data-off="{{ (int) ($cell?->is_day_off ?? false) }}" data-holiday="{{ (int) ($cell?->is_holiday ?? false) }}" data-note="{{ $cell?->note }}">កំណត់</button><br>
                                                    @endcan
                                                    @if ($cell)
                                                        @if ($cell->is_holiday)
                                                            <span class="roster-badge roster-holiday" title="{{ localize('holiday', 'ថ្ងៃបុណ្យ') }}">H</span>
                                                        @elseif ($cell->is_day_off)
                                                            <span class="roster-badge roster-off" title="{{ localize('day_off', 'ថ្ងៃឈប់សម្រាក') }}">OFF</span>
                                                        @elseif ($cell->shift)
                                                            <span class="roster-badge roster-shift"
                                                                title="{{ $cell->shift->name }} ({{ $cell->shift->start_time }} - {{ $cell->shift->end_time }})">
                                                                {{ $cell->shift->code ?: $cell->shift->name }}
                                                            </span>
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    @else
                                                        <span class="text-muted" title="ប្រើម៉ោងគោលរបស់អង្គភាព បើបានកំណត់">—</span>
                                                    @endif
                                                    @if($cell)
                                                        @can('create_shift_roster')
                                                            <form method="POST" action="{{ route('shift-rosters.destroy', $cell->id) }}" onsubmit="return confirm('លុបវេនថ្ងៃនេះ?')">@csrf @method('DELETE')<button class="btn btn-sm text-danger" aria-label="លុបវេន">×</button></form>
                                                        @endcan
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endif
                                @empty
                                    <tr>
                                        <td colspan="{{ 2 + count($monthDays) }}" class="text-center text-muted py-4">
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
                        @include('backend.layouts.common.validation')

                        <ul class="nav nav-pills nav-fill mb-3 roster-assign-tabs">
                            <li class="nav-item">
                                <button type="button" class="nav-link active" data-bs-toggle="pill" data-bs-target="#assign-individual">{{ localize('individual', 'ម្នាក់ៗ') }}</button>
                            </li>
                            <li class="nav-item">
                                <button type="button" class="nav-link" data-bs-toggle="pill" data-bs-target="#assign-team">{{ localize('by_team', 'តាមក្រុម') }}</button>
                            </li>
                        </ul>

                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="assign-individual">
                                <form id="roster-form" action="{{ route('shift-rosters.store') }}" method="POST">
                                    <input type="hidden" name="department_id" value="{{ $selectedDepartmentId }}">
                                    @csrf

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
                                        <input type="date" name="roster_date" class="form-control" value="{{ old('roster_date', sprintf('%04d-%02d-01', $selectedYear, $selectedMonth)) }}" required>
                                        <label class="form-label mt-2">ដល់ថ្ងៃ (ជាជម្រើស អតិបរមា ៣១ ថ្ងៃ)</label><input type="date" name="end_date" class="form-control" value="{{ old('end_date') }}">
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
                                        <label class="form-check-label" for="is_day_off">{{ localize('day_off', 'ថ្ងៃឈប់សម្រាក') }}</label>
                                    </div>

                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="is_holiday" name="is_holiday" value="1">
                                        <label class="form-check-label" for="is_holiday">{{ localize('holiday', 'ថ្ងៃបុណ្យ') }}</label>
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

                            <div class="tab-pane fade" id="assign-team">
                                <form action="{{ route('shift-rosters.store-team') }}" method="POST">
                                    @csrf

                                    <div class="mb-2">
                                        <label class="form-label">{{ localize('shift_team', 'ក្រុមវេន') }} <span class="text-danger">*</span></label>
                                        <select name="shift_team_id" class="form-select select-basic-single" required @disabled($teams->isEmpty())>
                                            <option value="">{{ localize('select', 'ជ្រើសរើស') }}</option>
                                            @foreach ($teams as $team)
                                                <option value="{{ $team->id }}">{{ $team->name }} ({{ $team->activeEmployees->count() }} នាក់)</option>
                                            @endforeach
                                        </select>
                                        @if ($teams->isEmpty())
                                            <small class="text-muted d-block mt-1">
                                                អង្គភាពនេះមិនទាន់មានក្រុមវេនទេ។
                                                <a href="{{ route('shift-teams.index', ['department_id' => $selectedDepartmentId]) }}">បង្កើតក្រុមវេន</a>
                                            </small>
                                        @endif
                                    </div>

                                    <div class="mb-2">
                                        <label class="form-label">{{ localize('date', 'កាលបរិច្ឆេទ') }} <span class="text-danger">*</span></label>
                                        <input type="date" name="roster_date" class="form-control" value="{{ sprintf('%04d-%02d-01', $selectedYear, $selectedMonth) }}" required>
                                        <label class="form-label mt-2">ដល់ថ្ងៃ (ជាជម្រើស អតិបរមា ៣១ ថ្ងៃ)</label><input type="date" name="end_date" class="form-control">
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
                                        <input class="form-check-input" type="checkbox" id="team_is_day_off" name="is_day_off" value="1">
                                        <label class="form-check-label" for="team_is_day_off">{{ localize('day_off', 'ថ្ងៃឈប់សម្រាក') }}</label>
                                    </div>

                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="team_is_holiday" name="is_holiday" value="1">
                                        <label class="form-check-label" for="team_is_holiday">{{ localize('holiday', 'ថ្ងៃបុណ្យ') }}</label>
                                    </div>

                                    <div class="mb-2">
                                        <label class="form-label">{{ localize('note', 'ចំណាំ') }}</label>
                                        <textarea name="note" class="form-control" rows="2"></textarea>
                                    </div>

                                    <button type="submit" class="btn btn-primary w-100 ams-btn-primary" @disabled($teams->isEmpty())>
                                        <i class="fa fa-users me-1"></i>{{ localize('assign_whole_team', 'កំណត់ឲ្យក្រុមទាំងមូល') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3 ams-card att-card">
                    <div class="card-body">
                        <button type="button" class="btn btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#generate-roster-modal">
                            <i class="fa fa-magic me-1"></i>{{ localize('auto_generate_roster', 'បង្កើត Roster ស្វ័យប្រវត្តិ') }}
                        </button>
                        <p class="small text-muted mt-2 mb-0">
                            ចាត់តាំងបុគ្គលិកចូលវេនដោយស្វ័យប្រវត្តិ តាមវេនយាម (is_duty) ដែលបានកំណត់សម្រាប់អង្គភាពនេះ
                            — អ្នកដែលមិនទាន់ចូលវេនយូរបំផុតត្រូវបានផ្តល់អាទិភាពជាមុន។
                        </p>
                    </div>
                </div>
            @endcan
        </div>
    </div>

    @can('create_shift_roster')
        <div class="modal fade" id="generate-roster-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h6 class="modal-title">{{ localize('auto_generate_roster', 'បង្កើត Roster ស្វ័យប្រវត្តិ') }}</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ localize('close', 'បិទ') }}"></button>
                    </div>
                    <div class="modal-body">
                        <form id="generate-roster-form" class="row g-2">
                            <input type="hidden" name="department_id" value="{{ $selectedDepartmentId }}">
                            <div class="col-md-6">
                                <label class="form-label">{{ localize('duty_shift', 'វេនយាម') }} <span class="text-danger">*</span></label>
                                <select name="shift_id" class="form-select" required>
                                    <option value="">{{ localize('select', 'ជ្រើសរើស') }}</option>
                                    @foreach ($shifts->where('is_duty', true) as $s)
                                        <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->code }})</option>
                                    @endforeach
                                </select>
                                @if ($shifts->where('is_duty', true)->isEmpty())
                                    <small class="text-muted d-block mt-1">
                                        អង្គភាពនេះមិនទាន់មានវេនយាមទេ។
                                        <a href="{{ route('shifts.index', ['department_id' => $selectedDepartmentId]) }}">កំណត់វេនយាម</a>
                                    </small>
                                @endif
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ localize('shift_team', 'ក្រុមវេន') }}</label>
                                <select name="shift_team_id" class="form-select">
                                    <option value="">{{ localize('all_eligible_employees', 'គ្រប់បុគ្គលិកសមស្របក្នុងអង្គភាព') }}</option>
                                    @foreach ($teams as $team)
                                        <option value="{{ $team->id }}">{{ $team->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ localize('from_date', 'ពីថ្ងៃទី') }} <span class="text-danger">*</span></label>
                                <input type="date" name="start_date" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ localize('to_date', 'ដល់ថ្ងៃទី (អតិបរមា ៦០ ថ្ងៃ)') }} <span class="text-danger">*</span></label>
                                <input type="date" name="end_date" class="form-control" required>
                            </div>
                            <div class="col-12">
                                <button type="button" id="generate-preview-btn" class="btn btn-outline-primary">
                                    <i class="fa fa-eye me-1"></i>{{ localize('preview', 'មើលមុន') }}
                                </button>
                            </div>
                        </form>

                        <div id="generate-preview-wrap" class="mt-3" style="display:none;">
                            <div class="table-responsive" style="max-height:320px; overflow-y:auto;">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>{{ localize('date', 'កាលបរិច្ឆេទ') }}</th>
                                            <th>{{ localize('employee', 'បុគ្គលិក') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="generate-preview-body"></tbody>
                                </table>
                            </div>
                        </div>
                        <div id="generate-error" class="text-danger small mt-2" style="display:none;"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ localize('close', 'បិទ') }}</button>
                        <button type="button" id="generate-commit-btn" class="btn btn-primary" disabled>
                            <i class="fa fa-save me-1"></i>{{ localize('confirm_and_save', 'បញ្ជាក់ និងរក្សាទុក') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endcan
@endsection

@push('js')
<script>
document.querySelectorAll('.roster-edit').forEach(button => button.addEventListener('click', () => {
    const form = document.getElementById('roster-form');
    if (!form) return;
    ['employee_id', 'shift_id'].forEach((field, index) => {
        form.elements[field].value = index === 0 ? button.dataset.employee : button.dataset.shift;
        if (window.jQuery) window.jQuery(form.elements[field]).trigger('change');
    });
    form.elements.roster_date.value = button.dataset.date;
    form.elements.end_date.value = '';
    form.elements.is_day_off.checked = button.dataset.off === '1';
    form.elements.is_holiday.checked = button.dataset.holiday === '1';
    form.elements.note.value = button.dataset.note;
    form.scrollIntoView({behavior: 'smooth', block: 'center'});
}));

(function () {
    const genForm = document.getElementById('generate-roster-form');
    const previewBtn = document.getElementById('generate-preview-btn');
    const commitBtn = document.getElementById('generate-commit-btn');
    const previewWrap = document.getElementById('generate-preview-wrap');
    const previewBody = document.getElementById('generate-preview-body');
    const errorBox = document.getElementById('generate-error');
    if (!genForm || !previewBtn || !commitBtn) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    function showError(message) {
        errorBox.textContent = message;
        errorBox.style.display = '';
    }

    function callGenerateEndpoint(url) {
        return fetch(url, {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json'},
            body: new FormData(genForm),
        }).then(async (res) => {
            const json = await res.json().catch(() => ({}));
            if (!res.ok) {
                const firstError = json.errors ? Object.values(json.errors)[0]?.[0] : null;
                throw new Error(firstError || json.message || 'មានបញ្ហា');
            }
            return json;
        });
    }

    previewBtn.addEventListener('click', () => {
        errorBox.style.display = 'none';
        commitBtn.disabled = true;
        previewBtn.disabled = true;
        callGenerateEndpoint(@json(route('shift-rosters.generate-preview'), JSON_UNESCAPED_SLASHES))
            .then((json) => {
                const rows = json.data || [];
                previewBody.innerHTML = '';
                rows.forEach((row) => {
                    const tr = document.createElement('tr');
                    const dateCell = document.createElement('td');
                    dateCell.textContent = row.date;
                    const nameCell = document.createElement('td');
                    if (row.employee_id) {
                        nameCell.textContent = row.employee_name;
                    } else {
                        nameCell.className = 'text-muted';
                        nameCell.textContent = row.reason || 'គ្មានបុគ្គលិកសមស្រប';
                    }
                    tr.append(dateCell, nameCell);
                    previewBody.appendChild(tr);
                });
                previewWrap.style.display = '';
                commitBtn.disabled = ! rows.some((row) => row.employee_id);
            })
            .catch((err) => showError(err.message))
            .finally(() => { previewBtn.disabled = false; });
    });

    commitBtn.addEventListener('click', () => {
        commitBtn.disabled = true;
        errorBox.style.display = 'none';
        callGenerateEndpoint(@json(route('shift-rosters.generate-commit'), JSON_UNESCAPED_SLASHES))
            .then(() => { window.location.reload(); })
            .catch((err) => { showError(err.message); commitBtn.disabled = false; });
    });
})();
</script>
    <script src="{{ module_asset('HumanResource/js/hrcommon.js') }}"></script>
@endpush
