@extends('backend.layouts.app')
@section('title', 'ម៉ោងធ្វើការតាមអង្គភាព')
@section('content')
@include('humanresource::attendance_header')
<div class="card att-card mb-3">
    <div class="card-body">
        <h5>ម៉ោងធ្វើការតាមអង្គភាព</h5>
        <p class="text-muted">១. ជ្រើសរើសអង្គភាព និងកំណត់ម៉ោងធ្វើការ។ ២. រៀបចំតារាងវេនយាមតាមមន្ត្រី។ ៣. ពិនិត្យវត្តមានប្រចាំថ្ងៃ។</p>
        <form method="GET" class="row align-items-end">
            @include('humanresource::attendance.unit-filter')
            <div class="col-md-8 mb-2 d-flex flex-wrap gap-2">
                @can('read_shift_roster')<a class="btn btn-outline-primary" href="{{ route('shift-rosters.index', ['department_id' => $selectedDepartmentId]) }}">តារាងវេនយាម</a>@endcan
                @can('read_attendance_snapshot')<a class="btn btn-outline-secondary" href="{{ route('attendance-snapshots.daily', ['department_id' => $selectedDepartmentId]) }}">វត្តមានប្រចាំថ្ងៃ</a>@endcan
            </div>
        </form>
        @include('backend.layouts.common.validation')
        @include('backend.layouts.common.message')
    </div>
</div>
<div class="row g-3">
    <div class="col-xl-8">
        <div class="card att-card"><div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>វេន / អង្គភាព</th><th>ម៉ោងធ្វើការ</th><th>អនុគ្រោះ (នាទី)</th><th>ស្ថានភាព</th><th>សកម្មភាព</th></tr></thead>
                <tbody>
                @forelse($shifts as $shift)
                    <tr>
                        <td><strong>{{ $shift->name }}</strong> <small>{{ $shift->code }}</small><br><small class="text-muted">{{ $shift->department?->department_name ?? 'មិនទាន់កំណត់អង្គភាព' }}</small>
                            <div>@if($shift->is_default)<span class="badge bg-primary">ម៉ោងគោល</span>@endif @if($shift->is_duty)<span class="badge bg-info text-dark">វេនយាម</span>@endif</div></td>
                        <td class="text-nowrap">
                            @if($shift->morning_end_time)
                                <div>ព្រឹក៖ {{ substr($shift->start_time, 0, 5) }} – {{ substr($shift->morning_end_time, 0, 5) }}</div>
                                <div>ល្ងាច៖ {{ substr($shift->afternoon_start_time, 0, 5) }} – {{ substr($shift->end_time, 0, 5) }}</div>
                            @else
                                {{ substr($shift->start_time, 0, 5) }} – {{ substr($shift->end_time, 0, 5) }}
                            @endif
                            @if($shift->is_cross_day)<small class="d-block text-primary">ចេញនៅថ្ងៃបន្ទាប់</small>@endif
                        </td>
                        <td>មកយឺត៖ {{ $shift->grace_late_minutes }}<br>ចេញមុន៖ {{ $shift->grace_early_leave_minutes }}</td>
                        <td>{{ $shift->is_active ? 'កំពុងប្រើ' : 'ផ្អាក' }}</td>
                        <td>@can('create_shift')
                            <a class="btn btn-sm btn-outline-primary mb-1" href="{{ route('shifts.index', ['department_id' => $selectedDepartmentId, 'edit' => $shift->id]) }}">កែប្រែ</a>
                            <form method="POST" action="{{ route('shifts.destroy', $shift->id) }}" onsubmit="return confirm('តើអ្នកចង់លុបវេននេះ?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">លុប</button></form>
                        @endcan</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted p-4">អង្គភាពនេះមិនទាន់មានម៉ោងធ្វើការ។ សូមបង្កើតម៉ោងគោល ឬវេនយាម។</td></tr>
                @endforelse
                </tbody>
            </table>
        </div><div class="p-3">{{ $shifts->links() }}</div></div>
    </div>
    @can('create_shift')
    <div class="col-xl-4"><div class="card att-card"><div class="card-body">
        <h6>{{ $editingShift ? 'កែប្រែវេន' : 'បង្កើតម៉ោងធ្វើការ' }}</h6>
        <form method="POST" action="{{ $editingShift ? route('shifts.update', $editingShift->id) : route('shifts.store') }}">
            @csrf @if($editingShift) @method('PUT') @endif
            <label class="form-label">អង្គភាព</label>
            <select name="department_id" class="form-select mb-3" required>
                @foreach($departments as $department)<option value="{{ $department->id }}" @selected(old('department_id', $editingShift?->department_id ?? $selectedDepartmentId) == $department->id)>{{ $department->department_name }}</option>@endforeach
            </select>
            <label class="form-label">ឈ្មោះវេន</label><input class="form-control mb-3" name="name" value="{{ old('name', $editingShift?->name) }}" required maxlength="255">
            <label class="form-label">កូដវេន</label><input class="form-control mb-3" name="code" value="{{ old('code', $editingShift?->code) }}" maxlength="30">
            <div class="row g-2 mb-3">
                @foreach(['start_time' => 'ចូលព្រឹក / ចាប់ផ្ដើមវេន', 'morning_end_time' => 'ចេញព្រឹក', 'afternoon_start_time' => 'ចូលល្ងាច', 'end_time' => 'ចេញល្ងាច / បញ្ចប់វេន'] as $field => $label)
                    <div class="col-6"><label class="form-label">{{ $label }}</label><input type="time" name="{{ $field }}" class="form-control" value="{{ old($field, $editingShift?->$field ? substr($editingShift->$field, 0, 5) : '') }}" @required(in_array($field, ['start_time', 'end_time']))></div>
                @endforeach
            </div>
            <p class="small text-muted">វេនព្រឹក/ល្ងាច៖ បំពេញម៉ោងទាំង ៤។ វេនយាមជាប់គ្នា៖ បំពេញតែម៉ោងចាប់ផ្ដើម និងបញ្ចប់។</p>
            <div class="row g-2 mb-3">
                @foreach(['grace_late_minutes' => 'អនុគ្រោះមកយឺត (នាទី)', 'grace_early_leave_minutes' => 'អនុគ្រោះចេញមុន (នាទី)'] as $field => $label)
                    <div class="col-6"><label class="form-label">{{ $label }}</label><input type="number" name="{{ $field }}" class="form-control" min="0" max="720" value="{{ old($field, $editingShift?->$field ?? 0) }}"></div>
                @endforeach
            </div>
            @foreach(['is_cross_day' => 'វេនឆ្លងថ្ងៃ', 'is_duty' => 'វេនយាម (ត្រូវរៀបចំក្នុងតារាង)', 'is_default' => 'ប្រើជាម៉ោងគោលរបស់អង្គភាព', 'is_active' => 'កំពុងប្រើប្រាស់'] as $field => $label)
                <input type="hidden" name="{{ $field }}" value="0">
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="{{ $field }}" id="{{ $field }}" value="1" @checked(old($field, $editingShift?->$field ?? ($field === 'is_active')))><label class="form-check-label" for="{{ $field }}">{{ $label }}</label></div>
            @endforeach
            <p class="small text-muted">វេនយាមមានអាទិភាពលើម៉ោងគោលនៅថ្ងៃដែលបានកំណត់ រួមទាំងថ្ងៃឈប់សម្រាក។</p>
            <button class="btn btn-primary w-100">រក្សាទុក</button>
            @if($editingShift)<a class="btn btn-link" href="{{ route('shifts.index', ['department_id' => $selectedDepartmentId]) }}">បោះបង់ការកែប្រែ</a>@endif
        </form>
    </div></div></div>
    @endcan
</div>
@endsection
