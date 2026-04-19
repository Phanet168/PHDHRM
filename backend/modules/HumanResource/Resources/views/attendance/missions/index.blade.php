@extends('backend.layouts.app')
@section('title', localize('mission_management', 'គ្រប់គ្រងបេសកម្ម'))
@section('content')
    @include('humanresource::attendance_header')

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semi-bold">
                        <i class="fa fa-briefcase text-primary me-1"></i>
                        {{ localize('mission_list', 'បញ្ជីបេសកម្ម') }}
                    </h6>
                    @can('create_mission')
                        <a href="{{ route('missions.index', ['mode' => 'create']) }}" class="btn btn-primary btn-sm">
                            <i class="fa fa-plus me-1"></i>{{ localize('new_mission', 'បង្កើតថ្មី') }}
                        </a>
                    @endcan
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ localize('sl', 'លរ') }}</th>
                                    <th>{{ localize('title', 'ចំណងជើង') }}</th>
                                    <th>{{ localize('destination', 'គោលដៅ') }}</th>
                                    <th>{{ localize('period', 'កាលបរិច្ឆេទ') }}</th>
                                    <th>{{ localize('employees', 'បុគ្គលិក') }}</th>
                                    <th>{{ localize('status', 'ស្ថានភាព') }}</th>
                                    <th>{{ localize('action', 'សកម្មភាព') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($missions as $mission)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td class="fw-semibold">{{ $mission->title }}</td>
                                        <td>{{ $mission->destination }}</td>
                                        <td>
                                            {{ optional($mission->start_date)->format('Y-m-d') }}<br>
                                            <small class="text-muted">{{ optional($mission->end_date)->format('Y-m-d') }}</small>
                                        </td>
                                        <td><span class="badge badge-info-soft">{{ $mission->assignments_count }}</span></td>
                                        <td>
                                            @php
                                                $statusMap = [
                                                    'draft' => 'secondary',
                                                    'pending' => 'warning',
                                                    'approved' => 'success',
                                                    'rejected' => 'danger',
                                                    'cancelled' => 'dark',
                                                ];
                                                $statusClass = $statusMap[$mission->status] ?? 'secondary';
                                            @endphp
                                            <span class="badge badge-{{ $statusClass }}-soft">{{ strtoupper($mission->status) }}</span>
                                        </td>
                                        <td>
                                            <a class="btn btn-info btn-sm" href="{{ route('missions.show', $mission->id) }}" title="{{ localize('view', 'មើល') }}">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                            @can('create_mission')
                                                <form action="{{ route('missions.destroy', $mission->id) }}" method="POST" class="d-inline"
                                                    onsubmit="return confirm('{{ localize('confirm_delete', 'តើអ្នកប្រាកដចង់លុបទេ?') }}')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger btn-sm" title="{{ localize('delete', 'លុប') }}">
                                                        <i class="fa fa-trash"></i>
                                                    </button>
                                                </form>
                                            @endcan
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">
                                            {{ localize('no_missions', 'មិនទាន់មានបេសកម្មទេ') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($missions->hasPages())
                        <div class="p-3">{{ $missions->links() }}</div>
                    @endif
                </div>
            </div>
        </div>

        @can('create_mission')
            @if(request('mode') === 'create')
                <div class="col-lg-4">
                    <div class="card border-primary mb-3">
                        <div class="card-header bg-primary-soft">
                            <h6 class="mb-0 text-primary fw-semi-bold">
                                <i class="fa fa-plus-circle me-1"></i>{{ localize('create_mission', 'បង្កើតបេសកម្ម') }}
                            </h6>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('missions.store') }}" method="POST">
                                @csrf
                                @include('backend.layouts.common.validation')

                                <div class="mb-2">
                                    <label class="form-label">{{ localize('title', 'ចំណងជើង') }} <span class="text-danger">*</span></label>
                                    <input type="text" name="title" class="form-control" required>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label">{{ localize('destination', 'គោលដៅ') }} <span class="text-danger">*</span></label>
                                    <input type="text" name="destination" class="form-control" required>
                                </div>

                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label class="form-label">{{ localize('start_date', 'ថ្ងៃចាប់ផ្តើម') }} <span class="text-danger">*</span></label>
                                        <input type="date" name="start_date" class="form-control" required>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">{{ localize('end_date', 'ថ្ងៃបញ្ចប់') }} <span class="text-danger">*</span></label>
                                        <input type="date" name="end_date" class="form-control" required>
                                    </div>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label">{{ localize('status', 'ស្ថានភាព') }}</label>
                                    <select name="status" class="form-select">
                                        <option value="draft">Draft</option>
                                        <option value="pending" selected>Pending</option>
                                        <option value="approved">Approved</option>
                                        <option value="rejected">Rejected</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label">{{ localize('employees', 'បុគ្គលិក') }}</label>
                                    <select name="employee_ids[]" class="form-select select-basic-multiple" multiple>
                                        @foreach($employees as $emp)
                                            <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->employee_id }})</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">{{ localize('purpose', 'គោលបំណង') }}</label>
                                    <textarea name="purpose" class="form-control" rows="3"></textarea>
                                </div>

                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fa fa-save me-1"></i>{{ localize('save', 'រក្សាទុក') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        @endcan
    </div>
@endsection

@push('js')
    <script src="{{ module_asset('HumanResource/js/hrcommon.js') }}"></script>
@endpush
