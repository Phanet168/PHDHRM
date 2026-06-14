@extends('planning::layouts.app')

@php
    $pageTitle = 'ផ្ទាំងគ្រប់គ្រងផែនការ';
@endphp

@section('planning-content')
    <div class="planning-hero mb-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
            <div>
                <span class="planning-kicker">មជ្ឈមណ្ឌលគ្រប់គ្រងផែនការ</span>
                <h1 class="h3 mt-3 mb-2 planning-page-title">ផ្ទាំងគ្រប់គ្រងផែនការ</h1>
                <p class="planning-meta mb-0">តាមដានផែនការតាមអង្គភាព ស្ថានភាពត្រួតពិនិត្យ និងទិន្នន័យបូកសរុបថវិកាតាមរចនាសម្ព័ន្ធអង្គភាព។</p>
            </div>
            <div class="d-flex gap-2 align-items-start">
                <a href="{{ route('planning.plans.create') }}" class="btn btn-success">បន្ថែមផែនការ</a>
                <a href="{{ route('planning.consolidation.index') }}" class="btn btn-outline-secondary">បើកទំព័របូកសរុប</a>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="planning-stat">
                <div class="planning-stat-label">ចំនួនផែនការសរុប</div>
                <p class="planning-stat-value">{{ number_format($summary['total_plans']) }}</p>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="planning-stat">
                <div class="planning-stat-label">ថវិកាសរុប</div>
                <p class="planning-stat-value">${{ number_format($summary['total_budget'], 2) }}</p>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="planning-stat">
                <div class="planning-stat-label">កំពុងរង់ចាំពិនិត្យ</div>
                <p class="planning-stat-value">{{ number_format($summary['pending_reviews']) }}</p>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="planning-stat">
                <div class="planning-stat-label">ផែនការដែលអនុម័ត</div>
                <p class="planning-stat-value">{{ number_format($summary['approved_plans']) }}</p>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-7">
            <div class="planning-panel">
                <div class="planning-section-title">ផែនការតាមប្រភេទ</div>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm planning-table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ប្រភេទផែនការ</th>
                                <th class="text-end">ចំនួន</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($summary['type_counts'] as $planType => $count)
                                <tr>
                                    <td>{{ str($planType)->replace('_', ' ')->title() }}</td>
                                    <td class="text-end fw-semibold">{{ number_format($count) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2">
                                        <div class="planning-empty">មិនទាន់មានផែនការនៅឡើយ។</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="planning-panel">
                <div class="planning-section-title">ថវិកាសរុបតាមអង្គភាព</div>
                <div class="vstack gap-3">
                    @forelse ($summary['totals_by_unit'] as $row)
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-semibold">{{ $row->name }}</span>
                                <span class="planning-meta">${{ number_format($row->total_cost, 2) }}</span>
                            </div>
                            <div class="progress" style="height: 9px;">
                                <div
                                    class="progress-bar bg-success"
                                    role="progressbar"
                                    style="width: {{ $summary['total_budget'] > 0 ? min(100, round(($row->total_cost / $summary['total_budget']) * 100, 2)) : 0 }}%;"
                                ></div>
                            </div>
                        </div>
                    @empty
                        <div class="planning-empty">ទិន្នន័យបូកសរុបថវិកានឹងបង្ហាញបន្ទាប់ពីមានការរក្សាទុកផែនការ។</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
