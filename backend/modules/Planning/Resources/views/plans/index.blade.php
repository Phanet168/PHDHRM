@extends('planning::layouts.app')

@php
    $pageTitle = 'បញ្ជីផែនការសម្រេចបាន';
@endphp

@section('planning-content')
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1 planning-page-title">បញ្ជីផែនការសម្រេចបាន</h1>
            <p class="planning-meta mb-0">មើល និងគ្រប់គ្រងផែនការសម្រេចបានតាមអង្គភាព និងឆ្នាំ។</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('planning.reports.index') }}" class="btn btn-outline-secondary">របាយការណ៍</a>
            <a href="{{ route('planning.plans.create') }}" class="btn btn-success">បង្កើតផែនការ</a>
        </div>
    </div>

    <form method="get" class="planning-filter-bar mb-4">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">ឆ្នាំ</label>
                <input type="number" name="year" value="{{ $filters['year'] ?? '' }}" class="form-control" placeholder="2026">
            </div>
            <div class="col-md-4">
                <label class="form-label">ស្ថានភាព</label>
                <select name="status" class="form-select">
                    <option value="">គ្រប់ស្ថានភាព</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label">អង្គភាព</label>
                <select name="org_unit_id" class="form-select">
                    <option value="">គ្រប់អង្គភាព</option>
                    @foreach ($orgUnits as $orgUnit)
                        <option value="{{ $orgUnit->id }}" @selected((string) ($filters['org_unit_id'] ?? '') === (string) $orgUnit->id)>{{ $orgUnit->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-primary" type="submit">ស្វែងរក</button>
            <a href="{{ route('planning.plans.index') }}" class="btn btn-light border">កំណត់ឡើងវិញ</a>
        </div>
    </form>

    <div class="card pharm-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">បញ្ជីផែនការសម្រេចបាន</h6>
            <span class="planning-meta">សរុប {{ $plans->total() }} ផែនការ</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-sm planning-table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ផែនការ</th>
                            <th>អង្គភាព</th>
                            <th>ឆ្នាំ</th>
                            <th>ស្ថានភាព</th>
                            <th class="text-end">ចំនួនជួរ</th>
                            <th class="text-end">សកម្មភាព</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($plans as $plan)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $plan->title }}</div>
                                    <div class="planning-meta">{{ $plan->reference_no ?: 'មិនមានលេខយោង' }}</div>
                                </td>
                                <td>{{ $plan->orgUnit?->name }}</td>
                                <td>{{ $plan->year }}</td>
                                <td>
                                    <span class="planning-badge badge-info">
                                        {{ str($plan->workflow_status)->replace('_', ' ')->title() }}
                                    </span>
                                </td>
                                <td class="text-end">{{ number_format($plan->items_count) }}</td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <a href="{{ route('planning.plans.show', $plan) }}" class="btn btn-sm btn-outline-secondary">មើល</a>
                                        @can('update', $plan)
                                            <a href="{{ route('planning.plans.edit', $plan) }}" class="btn btn-sm btn-outline-primary">កែប្រែ</a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="planning-empty">មិនមានផែនការសម្រេចបានត្រូវនឹងលក្ខខណ្ឌស្វែងរកទេ។</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                {{ $plans->links() }}
            </div>
        </div>
    </div>
@endsection
