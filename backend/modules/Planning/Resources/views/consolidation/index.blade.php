@extends('planning::layouts.app')

@php
    $pageTitle = 'បូកសរុបផែនការ';
@endphp

@section('planning-content')
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1 planning-page-title">ទំព័របូកសរុបផែនការ</h1>
            <p class="planning-meta mb-0">ពិនិត្យផែនការតាមអង្គភាព ប្រៀបធៀបទិន្នន័យសរុប និងអនុវត្តការបូកសរុបតាមសិទ្ធិដែលបានអនុញ្ញាត។</p>
        </div>
        <a href="{{ route('planning.dashboard') }}" class="btn btn-light border">ត្រឡប់ទៅផ្ទាំងគ្រប់គ្រង</a>
    </div>

    <div class="row g-3 mb-4">
        @foreach ($statusRollups as $status => $count)
            <div class="col-md-6 col-xl-3">
                <div class="planning-stat">
                    <div class="planning-stat-label">{{ str($status)->replace('_', ' ')->title() }}</div>
                    <p class="planning-stat-value">{{ number_format($count) }}</p>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-xl-5">
            <div class="planning-panel">
                <div class="planning-section-title">សរុបតាមអង្គភាព និងប្រភេទផែនការ</div>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm planning-table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>អង្គភាព</th>
                                <th>ប្រភេទផែនការ</th>
                                <th class="text-end">ចំនួនផែនការ</th>
                                <th class="text-end">ថវិកា</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rollups as $rollup)
                                <tr>
                                    <td>{{ $rollup->org_unit_name }}</td>
                                    <td>{{ str($rollup->plan_type)->replace('_', ' ')->title() }}</td>
                                    <td class="text-end">{{ number_format($rollup->plans_count) }}</td>
                                    <td class="text-end">${{ number_format($rollup->total_cost, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4"><div class="planning-empty">មិនទាន់មានទិន្នន័យបូកសរុបនៅឡើយ។</div></td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="planning-panel">
                <div class="planning-section-title">ផែនការដែលអាចមើលឃើញ</div>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm planning-table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ផែនការ</th>
                                <th>អង្គភាព</th>
                                <th>ស្ថានភាព</th>
                                <th class="text-end">ថវិកា</th>
                                <th class="text-end">សកម្មភាព</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($plans as $plan)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $plan->title }}</div>
                                        <div class="planning-meta">{{ str($plan->plan_type)->replace('_', ' ')->title() }} • {{ $plan->year }}</div>
                                    </td>
                                    <td>{{ $plan->orgUnit?->name }}</td>
                                    <td><span class="planning-badge badge-{{ $plan->workflow_status }}">{{ str($plan->workflow_status)->replace('_', ' ')->title() }}</span></td>
                                    <td class="text-end fw-semibold">${{ number_format($plan->total_estimated_cost, 2) }}</td>
                                    <td class="text-end">
                                        <div class="btn-group">
                                            <a href="{{ route('planning.plans.show', $plan) }}" class="btn btn-sm btn-outline-secondary">បើកមើល</a>
                                            @if ($canConsolidate)
                                                <form action="{{ route('planning.plans.consolidate', $plan) }}" method="post">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-dark">បូកសរុប</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5"><div class="planning-empty">មិនមានផែនការសម្រាប់បូកសរុបទេ។</div></td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-3">
                    {{ $plans->links() }}
                </div>
            </div>
        </div>
    </div>

    <div class="planning-panel mt-4">
        <div class="planning-section-title">ប្រវត្តិបូកសរុបថ្មីៗ</div>
        <div class="table-responsive">
            <table class="table table-bordered table-sm planning-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ផែនការមេ</th>
                        <th>ផែនការរង</th>
                        <th class="text-end">ថវិកាដែលបានបូក</th>
                        <th>កាលបរិច្ឆេទបូកសរុប</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($consolidatedChildren as $rollup)
                        <tr>
                            <td>{{ $rollup->parentPlan?->title ?: '-' }}</td>
                            <td>{{ $rollup->childPlan?->orgUnit?->name ?: '-' }}</td>
                            <td class="text-end">${{ number_format($rollup->rolled_cost, 2) }}</td>
                            <td>{{ optional($rollup->rolled_at)->format('d M Y H:i') ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4"><div class="planning-empty">មិនទាន់មានប្រវត្តិបូកសរុបទេ។</div></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
