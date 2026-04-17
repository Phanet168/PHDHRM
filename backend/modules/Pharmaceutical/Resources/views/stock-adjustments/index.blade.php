@extends('backend.layouts.app')

@section('title', localize('stock_adjustments', 'Stock Adjustments'))

@section('content')
    <div class="body-content">
        @include('pharmaceutical::_nav')

        <div class="card pharm-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">{{ localize('stock_adjustments', 'Stock Adjustments') }}</h6>
                <div class="d-flex gap-2">
                    <a href="{{ route('pharmaceutical.help', ['article' => 'adjustments']) }}" class="btn btn-sm btn-outline-info">
                        <i class="fa fa-life-ring me-1"></i>{{ localize('help', 'Help') }}
                    </a>
                    <a href="{{ route('pharmaceutical.stock-adjustments.create') }}" class="btn btn-sm btn-success">
                        <i class="fa fa-plus me-1"></i>{{ localize('new_adjustment', 'New adjustment') }}
                    </a>
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
                                <option value="{{ $dept->id }}" @selected($dept->id === $deptFilter)>{{ $dept->department_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="type" class="form-select form-select-sm">
                            <option value="">-- {{ localize('all_types', 'All types') }} --</option>
                            @foreach(\Modules\Pharmaceutical\Entities\PharmStockAdjustment::typeLabels() as $key => $label)
                                <option value="{{ $key }}" @selected($key === $typeFilter)>{{ $label }}</option>
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
                                <th>{{ localize('medicine', 'Medicine') }}</th>
                                <th>{{ localize('type', 'Type') }}</th>
                                <th>{{ localize('quantity', 'Qty') }}</th>
                                <th>{{ localize('batch_no', 'Batch') }}</th>
                                <th>{{ localize('date', 'Date') }}</th>
                                <th>{{ localize('reason', 'Reason') }}</th>
                                <th>{{ localize('by', 'By') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($adjustments as $adj)
                                @php
                                    $typeBadge = match($adj->adjustment_type) {
                                        'damaged' => 'bg-danger',
                                        'expired' => 'bg-warning text-dark',
                                        'loss' => 'bg-dark',
                                        'correction' => 'bg-info',
                                        default => 'bg-secondary',
                                    };
                                @endphp
                                <tr>
                                    <td>{{ $adjustments->firstItem() + $loop->index }}</td>
                                    <td><small>{{ $adj->reference_no ?: '-' }}</small></td>
                                    <td>{{ $adj->department?->department_name ?: '-' }}</td>
                                    <td>
                                        {{ $adj->medicine?->name }}
                                        @if($adj->medicine?->name_kh)
                                            <br><small class="text-muted">{{ $adj->medicine->name_kh }}</small>
                                        @endif
                                    </td>
                                    <td><span class="badge {{ $typeBadge }}">{{ $adj->type_label }}</span></td>
                                    <td class="text-end">{{ number_format($adj->quantity, 2) }}</td>
                                    <td><small>{{ $adj->batch_no ?: '-' }}</small></td>
                                    <td>{{ $adj->adjustment_date?->format('d/m/Y') }}</td>
                                    <td><small>{{ \Illuminate\Support\Str::limit($adj->reason, 40) }}</small></td>
                                    <td><small>{{ $adj->adjuster?->name ?: '-' }}</small></td>
                                </tr>
                            @empty
                                <tr><td colspan="10" class="text-center text-muted">{{ localize('no_data', 'No data') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $adjustments->links() }}</div>
            </div>
        </div>
    </div>
@endsection
