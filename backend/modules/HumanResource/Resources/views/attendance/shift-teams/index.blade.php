@extends('backend.layouts.app')
@section('title', 'ក្រុមវេន')
@section('content')
@include('humanresource::attendance_header')
<div class="card att-card mb-3">
    <div class="card-body">
        <h5>ក្រុមវេន (Duty Teams)</h5>
        <p class="text-muted">បង្កើតក្រុមមន្ត្រីអចិន្ត្រៃយ៍ដើម្បីរៀបចំតារាងវេនយាមសម្រាប់សមាជិកទាំងអស់ក្នុងម្ដងតែមួយ ជំនួសការជ្រើសរើសម្នាក់ៗ។</p>
        <form method="GET" class="row align-items-end">
            @include('humanresource::attendance.unit-filter')
            <div class="col-md-8 mb-2 d-flex flex-wrap gap-2">
                @can('read_shift')<a class="btn btn-outline-primary" href="{{ route('shifts.index', ['department_id' => $selectedDepartmentId]) }}">ម៉ោងធ្វើការ</a>@endcan
                @can('read_shift_roster')<a class="btn btn-outline-secondary" href="{{ route('shift-rosters.index', ['department_id' => $selectedDepartmentId]) }}">តារាងវេនយាម</a>@endcan
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
                <thead class="table-light"><tr><th>ក្រុមវេន</th><th>សមាជិកសកម្ម</th><th>ស្ថានភាព</th><th>សកម្មភាព</th></tr></thead>
                <tbody>
                @forelse($teams as $team)
                    <tr>
                        <td><strong>{{ $team->name }}</strong> <small>{{ $team->code }}</small></td>
                        <td>
                            @forelse($team->activeEmployees as $member)
                                <span class="badge bg-light text-dark border">{{ $member->full_name }}</span>
                            @empty
                                <span class="text-muted small">មិនទាន់មានសមាជិក</span>
                            @endforelse
                        </td>
                        <td>{{ $team->is_active ? 'កំពុងប្រើ' : 'ផ្អាក' }}</td>
                        <td>@can('create_shift_roster')
                            <button type="button" class="btn btn-sm btn-outline-primary mb-1" data-bs-toggle="modal" data-bs-target="#members-modal-{{ $team->id }}">សមាជិក</button>
                            <form method="POST" action="{{ route('shift-teams.destroy', $team->id) }}" class="d-inline" onsubmit="return confirm('តើអ្នកចង់លុបក្រុមវេននេះ?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger mb-1">លុប</button></form>
                        @endcan</td>
                    </tr>

                    @can('create_shift_roster')
                    <div class="modal fade" id="members-modal-{{ $team->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST" action="{{ route('shift-teams.members', $team->id) }}">
                                    @csrf @method('PUT')
                                    <div class="modal-header"><h6 class="modal-title">សមាជិកក្រុម៖ {{ $team->name }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                    <div class="modal-body" style="max-height:60vh;overflow-y:auto;">
                                        @php($activeIds = $team->activeEmployees->pluck('id')->all())
                                        @forelse($employees as $employee)
                                            <div class="form-check mb-1">
                                                <input class="form-check-input" type="checkbox" name="employee_ids[]" value="{{ $employee->id }}" id="member-{{ $team->id }}-{{ $employee->id }}" @checked(in_array($employee->id, $activeIds))>
                                                <label class="form-check-label" for="member-{{ $team->id }}-{{ $employee->id }}">{{ $employee->full_name }}</label>
                                            </div>
                                        @empty
                                            <p class="text-muted small">មិនមានបុគ្គលិកក្នុងអង្គភាពនេះទេ។</p>
                                        @endforelse
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">បិទ</button>
                                        <button class="btn btn-primary">រក្សាទុកសមាជិក</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    @endcan
                @empty
                    <tr><td colspan="4" class="text-center text-muted p-4">អង្គភាពនេះមិនទាន់មានក្រុមវេនទេ។</td></tr>
                @endforelse
                </tbody>
            </table>
        </div><div class="p-3">{{ $teams->links() }}</div></div>
    </div>
    @can('create_shift_roster')
    <div class="col-xl-4"><div class="card att-card"><div class="card-body">
        <h6>បង្កើតក្រុមវេនថ្មី</h6>
        <form method="POST" action="{{ route('shift-teams.store') }}">
            @csrf
            <label class="form-label">អង្គភាព</label>
            <select name="department_id" class="form-select mb-3" required>
                @foreach($departments as $department)<option value="{{ $department->id }}" @selected(old('department_id', $selectedDepartmentId) == $department->id)>{{ $department->department_name }}</option>@endforeach
            </select>
            <label class="form-label">ឈ្មោះក្រុម</label><input class="form-control mb-3" name="name" value="{{ old('name') }}" required maxlength="255" placeholder="ឧ. ក្រុម A">
            <label class="form-label">កូដក្រុម</label><input class="form-control mb-3" name="code" value="{{ old('code') }}" maxlength="30">
            <input type="hidden" name="is_active" value="1">
            <button class="btn btn-primary w-100">រក្សាទុក</button>
        </form>
        <p class="small text-muted mt-3">បន្ទាប់ពីបង្កើតរួច ចុច "សមាជិក" នៅជួរក្រុមវេនដើម្បីជ្រើសរើសបុគ្គលិក។</p>
    </div></div></div>
    @endcan
</div>
@endsection
