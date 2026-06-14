@extends('planning::layouts.app')

@php
    $pageTitle = 'ព័ត៌មានលម្អិតផែនការសម្រេចបាន';
@endphp

@section('planning-content')
    <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 mb-4">
        <div>
            <div class="planning-kicker">ផែនការសម្រេចបាន</div>
            <h1 class="h3 mt-3 mb-1 planning-page-title">{{ $plan->title }}</h1>
            <p class="planning-meta mb-0">
                {{ $plan->orgUnit?->name }} | ឆ្នាំ {{ $plan->year }} | {{ $plan->reference_no ?: 'មិនមានលេខយោង' }}
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <span class="planning-badge badge-info">{{ str($plan->workflow_status)->replace('_', ' ')->title() }}</span>
            <a href="{{ route('planning.plans.export', $plan) }}" class="btn btn-outline-secondary">ទាញយក CSV</a>
            @can('update', $plan)
                <a href="{{ route('planning.plans.micro-plan.edit', $plan) }}" class="btn btn-success">ជំហានទី២ Micro Plan</a>
                <a href="{{ route('planning.plans.edit', $plan) }}" class="btn btn-primary">កែប្រែផែនការ</a>
            @endcan
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="planning-stat">
                <div class="planning-stat-label">ចំនួនជួរសូចនាករ</div>
                <p class="planning-stat-value">{{ number_format($plan->items->where('item_type', 'indicator_result')->count()) }}</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="planning-stat">
                <div class="planning-stat-label">កាលបរិច្ឆេទបង្កើត</div>
                <p class="planning-stat-value fs-5">{{ optional($plan->created_at)->format('d/m/Y H:i') ?: '-' }}</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="planning-stat">
                <div class="planning-stat-label">ថ្ងៃដាក់ស្នើ</div>
                <p class="planning-stat-value fs-5">{{ optional($plan->submitted_at)->format('d/m/Y') ?: 'មិនទាន់ដាក់ស្នើ' }}</p>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="planning-form-section mb-4">
                <div class="planning-section-title">ព័ត៌មានទូទៅ</div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="planning-meta mb-1">អង្គភាព</div>
                        <div>{{ $plan->orgUnit?->name ?: '-' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="planning-meta mb-1">ឆ្នាំ</div>
                        <div>{{ $plan->year }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="planning-meta mb-1">លេខយោង</div>
                        <div>{{ $plan->reference_no ?: '-' }}</div>
                    </div>
                    <div class="col-12">
                        <div class="planning-meta mb-1">គោលបំណង</div>
                        <div>{{ $plan->objective ?: '-' }}</div>
                    </div>
                </div>
            </div>

            <div class="planning-form-section">
                <div class="planning-section-title">តារាងផែនការសម្រេចបាន</div>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle planning-table mb-0">
                        <thead>
                            <tr>
                                <th>កាលបរិច្ឆេទ</th>
                                <th>សូចនាករ</th>
                                <th>អ្នកទទួលខុសត្រូវ</th>
                                <th class="text-end">សម្រេចបានឆ្នាំចាស់</th>
                                <th class="text-end">គោលដៅ</th>
                                <th class="text-end">សម្រេចបានបច្ចុប្បន្ន</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($plan->items->where('item_type', 'indicator_result') as $item)
                                @php
                                    $indicatorRow = $item->indicators->first();
                                @endphp
                                <tr>
                                    <td>{{ optional($item->created_at)->format('d/m/Y H:i') ?: '-' }}</td>
                                    <td>
                                        <div>{{ $indicatorRow?->indicator?->name ?: $item->indicator_text ?: '-' }}</div>
                                        <div class="planning-meta">{{ $item->title ?: '-' }}</div>
                                    </td>
                                    <td>{{ $item->responsibleOrgUnit?->name ?: '-' }}</td>
                                    <td class="text-end">{{ $indicatorRow?->baseline_value !== null ? number_format($indicatorRow->baseline_value, 2) : '-' }}</td>
                                    <td class="text-end">{{ $indicatorRow?->target_value !== null ? number_format($indicatorRow->target_value, 2) : '-' }}</td>
                                    <td class="text-end">{{ $indicatorRow?->achieved_value !== null ? number_format($indicatorRow->achieved_value, 2) : '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6"><div class="planning-empty">មិនមានទិន្នន័យផែនការសម្រេចបានទេ។</div></td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="planning-form-section mb-4">
                <div class="planning-section-title">Workflow</div>
                <div class="d-grid gap-2">
                    @can('submit', $plan)
                        <form action="{{ route('planning.plans.submit', $plan) }}" method="post">
                            @csrf
                            <button type="submit" class="btn btn-success w-100">ដាក់ស្នើសម្រាប់ពិនិត្យ</button>
                        </form>
                    @endcan
                    @can('review', $plan)
                        <form action="{{ route('planning.plans.review', $plan) }}" method="post">
                            @csrf
                            <textarea name="comment" class="form-control mb-2" rows="2" placeholder="កំណត់ចំណាំរបស់អ្នកពិនិត្យ"></textarea>
                            <button type="submit" class="btn btn-outline-primary w-100">រក្សាទុកការពិនិត្យ</button>
                        </form>
                    @endcan
                    @can('approve', $plan)
                        <form action="{{ route('planning.plans.approve', $plan) }}" method="post">
                            @csrf
                            <textarea name="comment" class="form-control mb-2" rows="2" placeholder="កំណត់ចំណាំពេលអនុម័ត"></textarea>
                            <button type="submit" class="btn btn-primary w-100">អនុម័តផែនការ</button>
                        </form>
                    @endcan
                    @can('reject', $plan)
                        <form action="{{ route('planning.plans.reject', $plan) }}" method="post">
                            @csrf
                            <textarea name="comment" class="form-control mb-2" rows="3" placeholder="មូលហេតុនៃការបដិសេធ" required></textarea>
                            <button type="submit" class="btn btn-outline-danger w-100">បដិសេធផែនការ</button>
                        </form>
                    @endcan
                </div>
            </div>

            <div class="planning-form-section">
                <div class="planning-section-title">មតិយោបល់</div>
                @can('comment', $plan)
                    <form action="{{ route('planning.plans.comments.store', $plan) }}" method="post" class="mb-3">
                        @csrf
                        <textarea name="comment" class="form-control mb-2" rows="3" placeholder="បញ្ចូលមតិយោបល់"></textarea>
                        <button type="submit" class="btn btn-outline-secondary">បង្ហោះមតិយោបល់</button>
                    </form>
                @endcan
                <div class="vstack gap-3">
                    @forelse ($plan->comments as $comment)
                        <div class="border rounded-4 p-3">
                            <div class="d-flex justify-content-between gap-2">
                                <div class="fw-semibold">{{ $comment->user?->full_name ?: 'អ្នកប្រើប្រាស់' }}</div>
                                <div class="planning-meta">{{ $comment->created_at?->format('d/m/Y H:i') }}</div>
                            </div>
                            <div class="planning-meta mb-2">{{ $comment->comment_type }} | {{ $comment->orgUnit?->name ?: 'មិនមានអង្គភាព' }}</div>
                            <div>{{ $comment->comment }}</div>
                        </div>
                    @empty
                        <div class="planning-empty py-3">មិនទាន់មានមតិយោបល់ទេ។</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
