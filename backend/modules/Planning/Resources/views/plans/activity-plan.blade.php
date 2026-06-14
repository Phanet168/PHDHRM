@extends('planning::layouts.app')

@php
    $pageTitle = 'ជំហានទី៣: ផែនការប្រចាំត្រីមាស';

    $groupedActivities = $activityItems
        ->groupBy(fn ($item) => optional(optional($item->indicators->first())->indicator)->id ?: 'no-indicator')
        ->map(function ($items) {
            $first = $items->first();
            $indicator = optional($first->indicators->first())->indicator;

            return [
                'indicator' => $indicator,
                'activities' => $items,
            ];
        })
        ->values();
@endphp

@section('planning-content')
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <div class="planning-kicker">ជំហានទី៣</div>
            <h1 class="h3 mt-2 mb-1 planning-page-title">ផែនការប្រចាំត្រីមាស</h1>
            <p class="planning-meta mb-0">
                កំណត់ថាសកម្មភាពដែលបានបង្កើតនៅជំហានទី២ ត្រូវអនុវត្តនៅត្រីមាសណាខ្លះ ដើម្បីរៀបជាផែនការសកម្មភាពប្រចាំឆ្នាំ។
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('planning.plans.micro-plan.edit', $plan) }}" class="btn btn-light border">ត្រឡប់ទៅជំហានទី២</a>
            <a href="{{ route('planning.plans.show', $plan) }}" class="btn btn-outline-secondary">មើលព័ត៌មានផែនការ</a>
        </div>
    </div>

    @include('planning::plans.partials.wizard-steps', ['currentStep' => 3])

    <form action="{{ route('planning.plans.activity-plan.update', $plan) }}" method="post">
        @csrf
        @method('PUT')

        @foreach ($groupedActivities as $groupIndex => $group)
            @php
                $indicator = $group['indicator'];
            @endphp
            <div class="planning-form-section mb-4">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
                    <div>
                        <div class="planning-section-title mb-1">
                            សូចនាករ {{ $groupIndex + 1 }}:
                            {{ $indicator?->code ? $indicator->code . ' - ' : '' }}{{ $indicator?->name ?: 'មិនមានសូចនាករ' }}
                        </div>
                        <div class="planning-meta">
                            កម្មវិធី: {{ $indicator?->activityCluster?->subProgram?->program?->name ?: '-' }} |
                            អនុកម្មវិធី: {{ $indicator?->activityCluster?->subProgram?->name ?: '-' }} |
                            ចង្កោមសកម្មភាព: {{ $indicator?->activityCluster?->name ?: '-' }}
                        </div>
                    </div>
                    <div class="planning-badge badge-info">
                        គោលដៅ: {{ optional($group['activities']->first()->indicators->first())->target_value ?? '-' }}
                        {{ optional($group['activities']->first()->indicators->first())->value_text ?? $group['activities']->first()->target_unit ?? '' }}
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle planning-table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px;">ល.រ</th>
                                <th style="min-width: 340px;">សកម្មភាព</th>
                                <th style="min-width: 180px;">អង្គភាពអនុវត្ត</th>
                                <th class="text-center" style="width: 70px;">Q1</th>
                                <th class="text-center" style="width: 70px;">Q2</th>
                                <th class="text-center" style="width: 70px;">Q3</th>
                                <th class="text-center" style="width: 70px;">Q4</th>
                                <th style="min-width: 180px;">លទ្ធផលរំពឹងទុក</th>
                                <th style="min-width: 200px;">កំណត់ចំណាំ</th>
                                <th class="text-end" style="width: 160px;">ថវិកា</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($group['activities'] as $activityIndex => $activity)
                                @php
                                    $formIndex = $groupIndex . '_' . $activityIndex;
                                    $selectedQuarters = collect(old("activities.$formIndex.quarters", $activity->schedules->pluck('quarter')->filter()->values()->all()));
                                @endphp
                                <tr>
                                    <td class="text-center fw-semibold">{{ $activityIndex + 1 }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $activity->title }}</div>
                                        <div class="planning-meta small">{{ $activity->description ?: '-' }}</div>
                                        <input type="hidden" name="activities[{{ $formIndex }}][item_id]" value="{{ $activity->id }}">
                                    </td>
                                    <td>{{ $activity->responsibleOrgUnit?->name ?: '-' }}</td>
                                    @foreach ([1, 2, 3, 4] as $quarter)
                                        <td class="text-center">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                name="activities[{{ $formIndex }}][quarters][]"
                                                value="{{ $quarter }}"
                                                @checked($selectedQuarters->contains($quarter))
                                            >
                                        </td>
                                    @endforeach
                                    <td>
                                        <input
                                            type="text"
                                            name="activities[{{ $formIndex }}][period_label]"
                                            class="form-control"
                                            value="{{ old("activities.$formIndex.period_label", $activity->schedules->first()?->period_label) }}"
                                            placeholder="ឧ. សម្រេចបានការចុះត្រួតពិនិត្យ ៤ ដង"
                                        >
                                    </td>
                                    <td>
                                        <textarea
                                            name="activities[{{ $formIndex }}][note]"
                                            class="form-control"
                                            rows="2"
                                            placeholder="កំណត់ចំណាំបន្ថែម"
                                        >{{ old("activities.$formIndex.note", $activity->schedules->first()?->note) }}</textarea>
                                    </td>
                                    <td class="text-end fw-semibold">{{ number_format((float) $activity->total_cost, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach

        @if ($groupedActivities->isEmpty())
            <div class="planning-empty">
                មិនទាន់មានសកម្មភាពពីជំហានទី២ ទេ។ សូមត្រឡប់ទៅជំហានទី២ មុនសិន។
            </div>
        @endif

        <div class="d-flex justify-content-between align-items-center gap-2 mt-4">
            <a href="{{ route('planning.plans.micro-plan.edit', $plan) }}" class="btn btn-light border">ត្រឡប់ទៅជំហានទី២</a>
            <button type="submit" class="btn btn-success" @disabled($groupedActivities->isEmpty())>រក្សាទុក និងបន្តទៅជំហានទី៤</button>
        </div>
    </form>
@endsection
