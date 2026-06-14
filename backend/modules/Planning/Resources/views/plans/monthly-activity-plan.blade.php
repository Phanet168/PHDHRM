@extends('planning::layouts.app')

@php
    $pageTitle = 'ជំហានទី៤: ផែនការប្រចាំខែ';

    $groupedActivities = $activityItems
        ->groupBy(fn ($item) => optional(optional($item->indicators->first())->indicator)->id ?: 'no-indicator')
        ->map(function ($items) {
            $first = $items->first();
            $indicator = optional($first->indicators->first())->indicator;

            return [
                'indicator' => $indicator,
                'activities' => $items->values(),
            ];
        })
        ->values();

    $quarterMonthMap = [
        1 => [1, 2, 3],
        2 => [4, 5, 6],
        3 => [7, 8, 9],
        4 => [10, 11, 12],
    ];

    $monthLabels = [
        1 => 'មករា',
        2 => 'កុម្ភៈ',
        3 => 'មីនា',
        4 => 'មេសា',
        5 => 'ឧសភា',
        6 => 'មិថុនា',
        7 => 'កក្កដា',
        8 => 'សីហា',
        9 => 'កញ្ញា',
        10 => 'តុលា',
        11 => 'វិច្ឆិកា',
        12 => 'ធ្នូ',
    ];
@endphp

@section('planning-content')
    <style>
        .monthly-plan-wrap {
            border: 1px solid #d7e3db;
            border-radius: 1rem;
            background: #fff;
            overflow: hidden;
        }

        .monthly-plan-scroll {
            overflow-x: auto;
            overflow-y: auto;
            max-height: calc(100vh - 260px);
        }

        .monthly-plan-table {
            width: max-content;
            min-width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .monthly-plan-table th,
        .monthly-plan-table td {
            border-right: 1px solid #e1e9e4;
            border-bottom: 1px solid #e1e9e4;
            background: #fff;
            vertical-align: middle;
        }

        .monthly-plan-table thead th {
            position: sticky;
            top: 0;
            z-index: 5;
            background: #f6faf7;
            color: #294737;
            font-weight: 700;
            text-align: center;
            white-space: nowrap;
            padding: 0.65rem 0.45rem;
        }

        .monthly-plan-table .sticky-col {
            position: sticky;
            left: 0;
            z-index: 4;
            background: #fff;
        }

        .monthly-plan-table thead .sticky-col {
            z-index: 6;
            background: #f6faf7;
        }

        .monthly-activity-col { width: 340px; }
        .monthly-note-col { width: 220px; }

        .monthly-plan-table .month-col {
            width: 72px;
            min-width: 72px;
        }

        .indicator-row td {
            background: #eef6f0;
            color: #173b2d;
            font-weight: 700;
            padding: 0.85rem 1rem;
        }

        .indicator-title {
            font-weight: 700;
        }

        .indicator-meta {
            font-size: 0.82rem;
            color: #5e7467;
            margin-top: 0.3rem;
        }

        .monthly-plan-table .meta-cell {
            padding: 0.75rem 0.85rem;
        }

        .monthly-plan-title {
            font-weight: 700;
            color: #173b2d;
        }

        .monthly-plan-sub {
            font-size: 0.82rem;
            color: #687b70;
        }

        @media (max-width: 1599.98px) {
            .monthly-plan-table .sticky-col-2 { left: 300px; }

            .monthly-activity-col { width: 300px; }
            .monthly-note-col { width: 200px; }

            .monthly-plan-table .month-col {
                width: 64px;
                min-width: 64px;
            }
        }

        @media (max-width: 1399.98px) {
            .monthly-plan-table .sticky-col-2 { left: 260px; }

            .monthly-activity-col { width: 260px; }
            .monthly-note-col { width: 180px; }

            .monthly-plan-table .month-col {
                width: 58px;
                min-width: 58px;
            }
        }

        @media (max-width: 1199.98px) {
            .monthly-plan-table .sticky-col,
            .monthly-plan-table .sticky-col-2 {
                position: static;
            }
        }
    </style>

    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <div class="planning-kicker">ជំហានទី៤</div>
            <h1 class="h3 mt-2 mb-1 planning-page-title">ផែនការប្រចាំខែ</h1>
            <p class="planning-meta mb-0">
                រៀបចំខែអនុវត្តសកម្មភាពក្នុងតារាងតែមួយ ដោយយកតែខែដែលស្ថិតក្នុងត្រីមាសដែលបានកំណត់នៅជំហានទី៣។
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('planning.plans.activity-plan.edit', $plan) }}" class="btn btn-light border">ត្រឡប់ទៅជំហានទី៣</a>
            <a href="{{ route('planning.plans.show', $plan) }}" class="btn btn-outline-secondary">មើលព័ត៌មានផែនការ</a>
        </div>
    </div>

    @include('planning::plans.partials.wizard-steps', ['currentStep' => 4])

    <form action="{{ route('planning.plans.monthly-activity-plan.update', $plan) }}" method="post">
        @csrf
        @method('PUT')

        @if ($groupedActivities->isEmpty())
            <div class="planning-empty">
                មិនទាន់មានសកម្មភាពពីជំហានទី២ទេ។ សូមបញ្ចប់ជំហានទី២ និងជំហានទី៣ មុនសិន។
            </div>
        @else
            <div class="monthly-plan-wrap">
                <div class="monthly-plan-scroll">
                    <table class="monthly-plan-table">
                        <thead>
                            <tr>
                                <th class="sticky-col monthly-activity-col">សកម្មភាព</th>
                                @foreach ($monthLabels as $monthLabel)
                                    <th class="month-col">{{ $monthLabel }}</th>
                                @endforeach
                                <th class="sticky-col sticky-col-2 monthly-note-col">កំណត់ចំណាំ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($groupedActivities as $groupIndex => $group)
                                @php
                                    $indicator = $group['indicator'];
                                @endphp

                                <tr class="indicator-row">
                                    <td colspan="{{ 2 + count($monthLabels) }}">
                                        <div class="indicator-title">
                                            សូចនាករ {{ $groupIndex + 1 }}:
                                            {{ $indicator?->code ? $indicator->code . ' - ' : '' }}{{ $indicator?->name ?: 'មិនមានសូចនាករ' }}
                                        </div>
                                        <div class="indicator-meta">
                                            កម្មវិធី: {{ $indicator?->activityCluster?->subProgram?->program?->name ?: '-' }} |
                                            អនុកម្មវិធី: {{ $indicator?->activityCluster?->subProgram?->name ?: '-' }} |
                                            ចង្កោមសកម្មភាព: {{ $indicator?->activityCluster?->name ?: '-' }}
                                        </div>
                                    </td>
                                </tr>

                                @foreach ($group['activities'] as $activityIndex => $activity)
                                    @php
                                        $formIndex = $groupIndex . '_' . $activityIndex;
                                        $quarterSelections = $activity->schedules->where('period_type', 'quarterly')->pluck('quarter')->filter()->unique()->sort()->values();
                                        $allowedMonths = $quarterSelections
                                            ->flatMap(fn ($quarter) => $quarterMonthMap[(int) $quarter] ?? [])
                                            ->unique()
                                            ->sort()
                                            ->values();
                                        $selectedMonths = collect(
                                            old(
                                                "activities.$formIndex.months",
                                                $activity->schedules->where('period_type', 'monthly')->pluck('month')->filter()->values()->all()
                                            )
                                        );
                                    @endphp
                                    <tr>
                                        <td class="meta-cell sticky-col">
                                            <div class="monthly-plan-title">{{ $activity->title }}</div>
                                            <div class="monthly-plan-sub">{{ $activity->description ?: '-' }}</div>
                                            <input type="hidden" name="activities[{{ $formIndex }}][item_id]" value="{{ $activity->id }}">
                                        </td>
                                        @foreach ($monthLabels as $monthNo => $monthLabel)
                                            <td class="text-center">
                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    name="activities[{{ $formIndex }}][months][]"
                                                    value="{{ $monthNo }}"
                                                    @disabled(!$allowedMonths->contains($monthNo))
                                                    @checked($selectedMonths->contains($monthNo))
                                                >
                                            </td>
                                        @endforeach
                                        <td class="meta-cell sticky-col sticky-col-2">
                                            <textarea
                                                name="activities[{{ $formIndex }}][note]"
                                                class="form-control"
                                                rows="2"
                                                placeholder="កំណត់ចំណាំបន្ថែម"
                                            >{{ old("activities.$formIndex.note", $activity->schedules->where('period_type', 'monthly')->first()?->note) }}</textarea>
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="d-flex justify-content-between align-items-center gap-2 mt-4">
            <a href="{{ route('planning.plans.activity-plan.edit', $plan) }}" class="btn btn-light border">ត្រឡប់ទៅជំហានទី៣</a>
            <button type="submit" class="btn btn-success" @disabled($groupedActivities->isEmpty())>រក្សាទុក និងបន្តទៅជំហានទី៥</button>
        </div>
    </form>
@endsection
