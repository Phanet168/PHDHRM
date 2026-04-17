@extends('backend.layouts.app')

@section('title', localize('reports', 'Reports'))

@section('content')
    <div class="body-content">
        @include('pharmaceutical::_nav')

        <div class="card pharm-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">{{ localize('pharm_report_list', 'Pharmaceutical reports') }}</h6>
                <div class="d-flex gap-2">
                    <a href="{{ route('pharmaceutical.help', ['article' => 'reports']) }}" class="btn btn-sm btn-outline-info">
                        <i class="fa fa-life-ring me-1"></i>{{ localize('help', 'Help') }}
                    </a>
                    @if(!($canReview ?? false))
                    <a href="{{ route('pharmaceutical.reports.create') }}" class="btn btn-sm btn-success">
                        <i class="fa fa-plus me-1"></i>{{ localize('new_report', 'New report') }}
                    </a>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-2 mb-3">
                    <div class="col-md-3">
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="{{ localize('search', 'Search') }}..." value="{{ $search }}">
                    </div>
                    <div class="col-md-2">
                        <select name="dept" class="form-select form-select-sm">
                            <option value="">-- {{ localize('all_facilities', 'All facilities') }} --</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}" @selected($dept->id === ($deptFilter ?? 0))>{{ $dept->department_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="type" class="form-select form-select-sm">
                            <option value="">-- {{ localize('all_types', 'All types') }} --</option>
                            @foreach(\Modules\Pharmaceutical\Entities\PharmReport::typeLabels() as $key => $label)
                                <option value="{{ $key }}" @selected($key === $type)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">-- {{ localize('all_statuses', 'All statuses') }} --</option>
                            @foreach(\Modules\Pharmaceutical\Entities\PharmReport::statusLabels() as $key => $label)
                                <option value="{{ $key }}" @selected($key === $status)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-sm btn-primary w-100">{{ localize('filter', 'Filter') }}</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ localize('sl', 'SL') }}</th>
                                <th>{{ localize('reference', 'Reference') }}</th>
                                <th>{{ localize('facility', 'Facility') }}</th>
                                <th>{{ localize('report_to', 'Report to') }}</th>
                                <th>{{ localize('type', 'Type') }}</th>
                                <th>{{ localize('period', 'Period') }}</th>
                                <th>{{ localize('status', 'Status') }}</th>
                                <th>{{ localize('action', 'Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reports as $rpt)
                                <tr>
                                    <td>{{ $reports->firstItem() + $loop->index }}</td>
                                    <td>{{ $rpt->reference_no ?: '-' }}</td>
                                    <td>{{ $rpt->department?->department_name ?: '-' }}</td>
                                    <td>{{ $rpt->parentDepartment?->department_name ?: '-' }}</td>
                                    <td>{{ $rpt->type_label }}</td>
                                    <td>{{ $rpt->period_label ?: (optional($rpt->period_start)->format('d/m/Y') . ' - ' . optional($rpt->period_end)->format('d/m/Y')) }}</td>
                                    <td>{!! $rpt->status_badge !!}</td>
                                    <td>
                                        <a href="{{ route('pharmaceutical.reports.show', $rpt->id) }}" class="btn btn-sm btn-outline-primary"><i class="fa fa-eye"></i></a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center text-muted">{{ localize('no_data', 'No data') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $reports->links() }}</div>
            </div>
        </div>
    </div>
@endsection
