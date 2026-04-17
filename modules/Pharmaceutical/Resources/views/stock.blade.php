@extends('backend.layouts.app')

@section('title', localize('facility_stock', 'Facility stock'))

@section('content')
    <div class="body-content">
        @include('pharmaceutical::_nav')

        <div class="card pharm-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">{{ localize('facility_stock', 'Facility stock') }}</h6>
                <a href="{{ route('pharmaceutical.help', ['article' => 'stock']) }}" class="btn btn-sm btn-outline-info">
                    <i class="fa fa-life-ring me-1"></i>{{ localize('help', 'Help') }}
                </a>
            </div>
            <div class="card-body">
                {{-- Toggle view --}}
                <div class="mb-3 d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ request()->fullUrlWithQuery(['summary' => 0]) }}"
                           class="btn btn-sm {{ !$summary ? 'btn-primary' : 'btn-outline-secondary' }}">
                            <i class="fa fa-list me-1"></i>{{ localize('by_batch', 'តាម Batch') }}
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['summary' => 1]) }}"
                           class="btn btn-sm {{ $summary ? 'btn-success' : 'btn-outline-secondary' }}">
                            <i class="fa fa-layer-group me-1"></i>{{ localize('summary_by_medicine', 'សរុបតាមឱសថ') }}
                        </a>
                    </div>
                    <a href="{{ route('pharmaceutical.stock.print', request()->query()) }}"
                       target="_blank"
                       class="btn btn-sm btn-outline-success">
                        <i class="fa fa-print me-1"></i>{{ localize('print_stock_report', 'បោះពុម្ភរបាយការណ៍ស្តុក') }}
                    </a>
                </div>

                <form method="GET" class="row g-2 mb-3">
                    <input type="hidden" name="summary" value="{{ $summary ? 1 : 0 }}">
                    <div class="col-md-4">
                        <select name="department_id" class="form-select form-select-sm">
                            <option value="">-- {{ localize('all_facilities', 'All facilities') }} --</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}" @selected($dept->id == $departmentId)>{{ $dept->department_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="{{ localize('search_medicine', 'Search medicine') }}..." value="{{ $search }}">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-sm btn-primary w-100">{{ localize('filter', 'Filter') }}</button>
                    </div>
                </form>

                <div class="table-responsive">
                    @if($summary)
                    {{-- ── Summary view: aggregated per (facility + medicine) ── --}}
                    <table class="table table-bordered table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:35px">{{ localize('sl', 'SL') }}</th>
                                <th>{{ localize('facility', 'Facility') }}</th>
                                <th>{{ localize('medicine', 'Medicine') }}</th>
                                <th>{{ localize('category', 'Category') }}</th>
                                <th>{{ localize('unit', 'Unit') }}</th>
                                <th>{{ localize('nearest_expiry', 'ផុតកំណត់ជិត') }}</th>
                                <th class="text-end">{{ localize('total_quantity', 'ចំនួនសរុប') }}</th>
                                <th class="text-center">{{ localize('status', 'Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($stocks as $s)
                                @php
                                    $qty          = (float) $s->total_quantity;
                                    $nearExpiry   = $s->nearest_expiry ? \Carbon\Carbon::parse($s->nearest_expiry) : null;
                                    $isExpiring   = $nearExpiry && $nearExpiry->lte(now()->addMonths(3));
                                    $isLow        = $qty <= 10;
                                @endphp
                                <tr class="{{ $isExpiring ? 'table-danger' : ($isLow ? 'table-warning' : '') }}">
                                    <td>{{ $stocks->firstItem() + $loop->index }}</td>
                                    <td>{{ $s->department_name ?: '-' }}</td>
                                    <td><strong>{{ $s->medicine_code }}</strong> – {{ $s->medicine_name }}</td>
                                    <td>{{ $s->category_name ?: '-' }}</td>
                                    <td>{{ $s->medicine_unit ?: '-' }}</td>
                                    <td class="{{ $isExpiring ? 'text-danger fw-bold' : '' }}">
                                        {{ $nearExpiry ? $nearExpiry->format('d/m/Y') : '-' }}
                                    </td>
                                    <td class="text-end fw-bold {{ $isLow ? 'text-warning' : 'text-success' }}">
                                        {{ number_format($qty, 2) }}
                                    </td>
                                    <td class="text-center">
                                        @if($isExpiring)
                                            <span class="badge bg-danger">{{ localize('expiring', 'ជិតផុត') }}</span>
                                        @elseif($isLow)
                                            <span class="badge bg-warning text-dark">{{ localize('low_stock', 'ស្តុកទាប') }}</span>
                                        @else
                                            <span class="badge bg-success">{{ localize('normal', 'ធម្មតា') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center text-muted">{{ localize('no_data', 'No data') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    @else
                    {{-- ── Batch view (original) ── --}}
                    <table class="table table-bordered table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ localize('sl', 'SL') }}</th>
                                <th>{{ localize('facility', 'Facility') }}</th>
                                <th>{{ localize('medicine', 'Medicine') }}</th>
                                <th>{{ localize('category', 'Category') }}</th>
                                <th>{{ localize('batch_no', 'Batch') }}</th>
                                <th>{{ localize('expiry_date', 'Expiry') }}</th>
                                <th class="text-end">{{ localize('quantity', 'Quantity') }}</th>
                                <th class="text-end">{{ localize('unit_price', 'Unit price') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($stocks as $s)
                                @php
                                    $isExpiring = $s->expiry_date && $s->expiry_date->lte(now()->addMonths(3));
                                    $isLow = (float) $s->quantity <= 10;
                                @endphp
                                <tr class="{{ $isExpiring ? 'table-danger' : ($isLow ? 'table-warning' : '') }}">
                                    <td>{{ $stocks->firstItem() + $loop->index }}</td>
                                    <td>{{ $s->department?->department_name ?: '-' }}</td>
                                    <td>{{ $s->medicine?->name ?: '-' }} ({{ $s->medicine?->code }})</td>
                                    <td>{{ $s->medicine?->category?->name ?: '-' }}</td>
                                    <td>{{ $s->batch_no ?: '-' }}</td>
                                    <td>{{ optional($s->expiry_date)->format('d/m/Y') ?: '-' }}</td>
                                    <td class="text-end {{ $isLow ? 'text-warning fw-bold' : '' }}">{{ number_format((float) $s->quantity, 2) }}</td>
                                    <td class="text-end">{{ number_format((float) $s->unit_price, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center text-muted">{{ localize('no_data', 'No data') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    @endif
                </div>
                <div class="mt-3">{{ $stocks->links() }}</div>
            </div>
        </div>
    </div>
@endsection
