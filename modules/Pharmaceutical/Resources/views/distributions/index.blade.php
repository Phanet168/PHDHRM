@extends('backend.layouts.app')

@section('title', localize('distributions', 'ការចែកចាយ'))

@section('content')
    <div class="body-content">
        @include('pharmaceutical::_nav')

        <div class="card pharm-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">{{ localize('distribution_list', 'បញ្ជីការចែកចាយ') }}</h6>
                <div class="d-flex gap-2 align-items-center">
                    <a href="{{ route('pharmaceutical.help', ['article' => 'distributions']) }}" class="btn btn-sm btn-outline-info">
                        <i class="fa fa-life-ring me-1"></i>{{ localize('help', 'ជំនួយ') }}
                    </a>
                    @if($canDistribute ?? false)
                    <div class="dropdown">
                        <button class="btn btn-sm btn-success dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fa fa-plus me-1"></i>{{ localize('new_distribution', 'បង្កើតការចែកចាយ') }}
                        </button>
                        <ul class="dropdown-menu">
                            @if(($level ?? '') === 'phd' || (auth()->user() && (int) auth()->user()->user_type_id === 1))
                            <li><a class="dropdown-item" href="{{ route('pharmaceutical.distributions.create', ['type' => 'phd_to_hospital']) }}">{{ localize('phd_to_hospital', 'មន្ទីរសុខាភិបាលខេត្ត → មន្ទីរពេទ្យ') }}</a></li>
                            <li><a class="dropdown-item" href="{{ route('pharmaceutical.distributions.create', ['type' => 'phd_to_od']) }}">{{ localize('phd_to_od', 'មន្ទីរសុខាភិបាលខេត្ត → ស្រុកប្រតិបត្តិ') }}</a></li>
                            @endif
                            @if(($level ?? '') === 'od' || (auth()->user() && (int) auth()->user()->user_type_id === 1))
                            <li><a class="dropdown-item" href="{{ route('pharmaceutical.distributions.create', ['type' => 'od_to_hc']) }}">{{ localize('od_to_hc', 'ស្រុកប្រតិបត្តិ → មណ្ឌលសុខភាព') }}</a></li>
                            @endif
                        </ul>
                    </div>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-2 mb-3">
                    <div class="col-md-3">
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="{{ localize('search', 'ស្វែងរក') }}..." value="{{ $search }}">
                    </div>
                    <div class="col-md-3">
                        <select name="type" class="form-select form-select-sm">
                            <option value="">-- {{ localize('all_types', 'គ្រប់ប្រភេទ') }} --</option>
                            @foreach(\Modules\Pharmaceutical\Entities\PharmDistribution::typeLabels() as $key => $label)
                                <option value="{{ $key }}" @selected($key === $type)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">-- {{ localize('all_statuses', 'គ្រប់ស្ថានភាព') }} --</option>
                            @foreach(\Modules\Pharmaceutical\Entities\PharmDistribution::statusLabels() as $key => $label)
                                <option value="{{ $key }}" @selected($key === $status)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-sm btn-primary w-100">{{ localize('filter', 'ស្វែងរក') }}</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ localize('sl', 'SL') }}</th>
                                <th>{{ localize('reference', 'លេខយោង') }}</th>
                                <th>{{ localize('type', 'ប្រភេទ') }}</th>
                                <th>{{ localize('from', 'ពី') }}</th>
                                <th>{{ localize('to', 'ទៅ') }}</th>
                                <th>{{ localize('date', 'កាលបរិច្ឆេទ') }}</th>
                                <th>{{ localize('status', 'ស្ថានភាព') }}</th>
                                <th>{{ localize('action', 'សកម្មភាព') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($distributions as $dist)
                                <tr>
                                    <td>{{ $distributions->firstItem() + $loop->index }}</td>
                                    <td>{{ $dist->reference_no ?: '-' }}</td>
                                    <td>{{ $dist->type_label }}</td>
                                    <td>{{ $dist->fromDepartment?->department_name ?: '-' }}</td>
                                    <td>{{ $dist->toDepartment?->department_name ?: '-' }}</td>
                                    <td>{{ optional($dist->distribution_date)->format('d/m/Y') ?: '-' }}</td>
                                    <td><span class="badge bg-secondary">{{ $dist->status_label }}</span></td>
                                    <td>
                                        <a href="{{ route('pharmaceutical.distributions.show', $dist->id) }}" class="btn btn-sm btn-outline-primary"><i class="fa fa-eye"></i></a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center text-muted">{{ localize('no_data', 'មិនមានទិន្នន័យ') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $distributions->links() }}</div>
            </div>
        </div>
    </div>
@endsection
