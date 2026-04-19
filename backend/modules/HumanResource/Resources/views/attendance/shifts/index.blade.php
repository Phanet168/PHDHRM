@extends('backend.layouts.app')
@section('title', localize('shift_management', 'គ្រប់គ្រង Shift'))
@section('content')
    @include('humanresource::attendance_header')

    <div class="row g-3">
        {{-- Left: Shift List --}}
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="fs-17 fw-semi-bold mb-0">
                        <i class="fa fa-clock text-primary me-1"></i>
                        {{ localize('shifts', 'បញ្ជី Shift') }}
                    </h6>
                    <span class="badge badge-primary-soft">{{ $shifts->total() }}</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ localize('sl', 'លរ') }}</th>
                                    <th>{{ localize('shift_code', 'កូដ') }}</th>
                                    <th>{{ localize('shift_name', 'ឈ្មោះ Shift') }}</th>
                                    <th>{{ localize('start_time', 'ម៉ោងចូល') }}</th>
                                    <th>{{ localize('end_time', 'ម៉ោងចេញ') }}</th>
                                    <th>{{ localize('grace_late', 'ត្រួស (យឺត)') }}</th>
                                    <th>{{ localize('grace_early_leave', 'ត្រួស (ចេញ)') }}</th>
                                    <th>{{ localize('cross_day', 'ឆ្លងថ្ងៃ') }}</th>
                                    <th>{{ localize('status', 'ស្ថានភាព') }}</th>
                                    <th>{{ localize('action', 'សកម្មភាព') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($shifts as $shift)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td><code>{{ $shift->code ?? '-' }}</code></td>
                                        <td class="fw-semibold">{{ $shift->name }}</td>
                                        <td>{{ $shift->start_time }}</td>
                                        <td>{{ $shift->end_time }}</td>
                                        <td>{{ $shift->grace_late_minutes }}{{ localize('min', 'ន') }}</td>
                                        <td>{{ $shift->grace_early_leave_minutes }}{{ localize('min', 'ន') }}</td>
                                        <td class="text-center">
                                            @if($shift->is_cross_day)
                                                <span class="badge badge-info-soft">{{ localize('yes', 'បាទ') }}</span>
                                            @else
                                                <span class="badge badge-secondary-soft">{{ localize('no', 'ទេ') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($shift->is_active)
                                                <span class="badge badge-success-soft">{{ localize('active', 'សកម្ម') }}</span>
                                            @else
                                                <span class="badge badge-danger-soft">{{ localize('inactive', 'អសកម្ម') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @can('create_shift')
                                                <form action="{{ route('shifts.destroy', $shift->id) }}" method="POST"
                                                    onsubmit="return confirm('{{ localize('confirm_delete', 'តើអ្នកប្រាកដចង់លុបទេ?') }}')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger btn-sm">
                                                        <i class="fa fa-trash"></i>
                                                    </button>
                                                </form>
                                            @endcan
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-center py-4 text-muted">
                                            <i class="fa fa-clock me-2"></i>{{ localize('no_shifts', 'មិនទាន់មាន Shift ទេ') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($shifts->hasPages())
                        <div class="p-3">{{ $shifts->links() }}</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Right: Create Shift Form --}}
        @can('create_shift')
            <div class="col-lg-4">
                <div class="card mb-4 border-primary">
                    <div class="card-header bg-primary-soft">
                        <h6 class="fs-15 fw-semi-bold mb-0 text-primary">
                            <i class="fa fa-plus-circle me-1"></i>{{ localize('create_shift', 'បង្កើត Shift ថ្មី') }}
                        </h6>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('shifts.store') }}" method="POST">
                            @csrf

                            @include('backend.layouts.common.validation')

                            <div class="mb-3">
                                <label class="form-label fw-semibold">{{ localize('shift_name', 'ឈ្មោះ Shift') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" value="{{ old('name') }}"
                                    placeholder="{{ localize('eg_morning_shift', 'ឧ: ប្រែវ') }}" required>
                                @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">{{ localize('shift_code', 'កូដ Shift') }}</label>
                                <input type="text" class="form-control" name="code" value="{{ old('code') }}"
                                    placeholder="{{ localize('eg_M', 'ឧ: M') }}" maxlength="30">
                                @error('code')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label fw-semibold">{{ localize('start_time', 'ម៉ោងចូល') }} <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" name="start_time" value="{{ old('start_time') }}" required>
                                    @error('start_time')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-semibold">{{ localize('end_time', 'ម៉ោងចេញ') }} <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" name="end_time" value="{{ old('end_time') }}" required>
                                    @error('end_time')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label fw-semibold">{{ localize('grace_late_min', 'ត្រួស យឺត (ន)') }}</label>
                                    <input type="number" class="form-control" name="grace_late_minutes"
                                        value="{{ old('grace_late_minutes', 0) }}" min="0" max="720">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-semibold">{{ localize('grace_early_min', 'ត្រួស ចេញ (ន)') }}</label>
                                    <input type="number" class="form-control" name="grace_early_leave_minutes"
                                        value="{{ old('grace_early_leave_minutes', 0) }}" min="0" max="720">
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_cross_day" value="1"
                                        id="is_cross_day" @checked(old('is_cross_day'))>
                                    <label class="form-check-label" for="is_cross_day">
                                        {{ localize('shift_cross_day', 'Shift ឆ្លងថ្ងៃ (overnight)') }}
                                    </label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1"
                                        id="is_active" @checked(old('is_active', true))>
                                    <label class="form-check-label" for="is_active">
                                        {{ localize('shift_active', 'Shift សកម្ម') }}
                                    </label>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fa fa-save me-1"></i>{{ localize('save', 'រក្សាទុក') }}
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
@endpush
