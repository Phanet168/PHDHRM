@extends('planning::layouts.app')

@php
    use Carbon\Carbon;

    $pageTitle = 'ជំហានទី៥: ផែនការប្រចាំថ្ងៃ';

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

    $timelineMonths = $activityItems
        ->flatMap(fn ($item) => $item->schedules->where('period_type', 'monthly')->pluck('month'))
        ->filter()
        ->map(fn ($month) => (int) $month)
        ->unique()
        ->sort()
        ->values();

    $timelineDays = $timelineMonths->flatMap(function ($month) use ($plan) {
        $monthStart = Carbon::createFromDate($plan->year, $month, 1);

        return collect(range(1, $monthStart->daysInMonth))->map(
            fn ($day) => $monthStart->copy()->day($day)
        );
    })->values();

    $totalActivities = $activityItems->count();
    $totalDailyDates = $activityItems->sum(fn ($item) => $item->schedules->where('period_type', 'daily')->count());
@endphp

@section('planning-content')
    <style>
        .project-plan-wrap {
            border: 1px solid #d7e3db;
            border-radius: 1rem;
            background: #fff;
            overflow: hidden;
        }

        .project-plan-scroll {
            overflow-x: auto;
            overflow-y: auto;
            max-height: calc(100vh - 260px);
        }

        .project-plan-table {
            width: max-content;
            min-width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .project-plan-table th,
        .project-plan-table td {
            border-right: 1px solid #e1e9e4;
            border-bottom: 1px solid #e1e9e4;
            vertical-align: middle;
            background: #fff;
        }

        .project-plan-table thead th {
            position: sticky;
            top: 0;
            z-index: 5;
            background: #f6faf7;
            color: #294737;
            font-weight: 700;
            text-align: center;
            white-space: nowrap;
        }

        .project-plan-table thead th.month-head {
            background: #edf6f0;
            font-size: 0.85rem;
            padding: 0.55rem 0.35rem;
        }

        .project-plan-table thead th.day-head {
            font-size: 0.72rem;
            padding: 0.35rem 0.2rem;
            min-width: 34px;
        }

        .project-plan-table .sticky-col {
            position: sticky;
            left: 0;
            z-index: 4;
            background: #fff;
        }

        .project-plan-table thead .sticky-col {
            z-index: 6;
            background: #f6faf7;
        }

        .project-plan-table .sticky-col-2 { left: 360px; }

        .project-plan-table thead th.weekday-head {
            font-size: 0.68rem;
            padding: 0.28rem 0.15rem;
            min-width: 34px;
            background: #fbfdfc;
            color: #6a7c72;
        }

        .project-plan-table thead th.day-head.weekend {
            background: #fdeaea;
            color: #b42318;
        }

        .project-plan-table thead th.weekday-head.weekend {
            background: #fff1f1;
            color: #b42318;
        }

        .project-plan-task { width: 360px; }
        .project-plan-desc { width: 260px; }

        .project-plan-title {
            font-weight: 700;
            color: #173b2d;
            line-height: 1.35;
        }

        .project-plan-sub {
            font-size: 0.82rem;
            color: #6a7c72;
        }

        .indicator-row td {
            background: #eef6f0;
            font-weight: 700;
            color: #173b2d;
            padding: 0.8rem 1rem;
        }

        .indicator-row .indicator-sticky {
            position: sticky;
            left: 0;
            z-index: 4;
            background: #eef6f0;
        }

        .indicator-toggle {
            border: 0;
            background: transparent;
            color: inherit;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.65rem;
            width: 100%;
            text-align: left;
        }

        .indicator-toggle-mark {
            width: 28px;
            height: 28px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #dceee2;
            color: #145530;
            flex: 0 0 auto;
        }

        .activity-row td.meta-cell {
            padding: 0.7rem 0.85rem;
        }

        .project-plan-table .meta-input {
            min-width: 100%;
            font-size: 0.85rem;
        }

        .project-plan-table .meta-textarea {
            min-width: 100%;
            min-height: 74px;
            resize: vertical;
            font-size: 0.85rem;
        }

        .plan-day-btn {
            width: 34px;
            height: 40px;
            border: 0;
            background: #fff;
            color: transparent;
            display: block;
            cursor: pointer;
            transition: background 0.15s ease;
        }

        .plan-day-btn:hover {
            background: #eef7f1;
        }

        .plan-day-btn.weekend {
            background: #fff5f5;
        }

        .plan-day-btn.weekend:hover {
            background: #fdeaea;
        }

        .plan-day-btn.is-active {
            background: linear-gradient(135deg, #2ea76a, #1f7a45);
        }

        .is-hidden {
            display: none;
        }

        @media (max-width: 1599.98px) {
            .project-plan-table .sticky-col-2 { left: 300px; }

            .project-plan-task { width: 300px; }
            .project-plan-desc { width: 220px; }

            .project-plan-title {
                font-size: 0.9rem;
            }

            .project-plan-sub,
            .project-plan-table .meta-textarea,
            .project-plan-table .meta-input {
                font-size: 0.8rem;
            }

            .project-plan-table thead th.day-head,
            .project-plan-table thead th.weekday-head {
                min-width: 30px;
                font-size: 0.66rem;
            }

            .plan-day-btn {
                width: 30px;
                height: 34px;
            }
        }

        @media (max-width: 1399.98px) {
            .project-plan-table .sticky-col-2 { left: 260px; }

            .project-plan-task { width: 260px; }
            .project-plan-desc { width: 190px; }

            .project-plan-table thead th.day-head,
            .project-plan-table thead th.weekday-head {
                min-width: 28px;
                font-size: 0.62rem;
                padding-left: 0.1rem;
                padding-right: 0.1rem;
            }

            .plan-day-btn {
                width: 28px;
                height: 32px;
            }
        }

        @media (max-width: 1199.98px) {
            .project-plan-table .sticky-col,
            .project-plan-table .sticky-col-2,
            .indicator-row .indicator-sticky {
                position: static;
            }
        }

        @media (max-width: 991.98px) {
            .project-plan-scroll {
                max-height: calc(100vh - 220px);
            }
        }
    </style>

    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <div class="planning-kicker">ជំហានទី៥</div>
            <h1 class="h3 mt-2 mb-1 planning-page-title">ផែនការប្រចាំថ្ងៃ</h1>
            <p class="planning-meta mb-0">តារាងតែមួយ ដោយដាក់សូចនាករខាងលើ និងសកម្មភាពការងារនៅខាងក្រោម។ ចុចលើសូចនាករ ដើម្បីបើកមើលសកម្មភាពរបស់វា។</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('planning.plans.monthly-activity-plan.edit', $plan) }}" class="btn btn-light border">ត្រឡប់ទៅជំហានទី៤</a>
            <a href="{{ route('planning.plans.show', $plan) }}" class="btn btn-outline-secondary">មើលព័ត៌មានផែនការ</a>
        </div>
    </div>

    @include('planning::plans.partials.wizard-steps', ['currentStep' => 5])

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="planning-stat h-100">
                <div class="planning-stat-label">សូចនាករសរុប</div>
                <div class="planning-stat-value">{{ $groupedActivities->count() }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="planning-stat h-100">
                <div class="planning-stat-label">សកម្មភាពសរុប</div>
                <div class="planning-stat-value">{{ $totalActivities }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="planning-stat h-100">
                <div class="planning-stat-label">ថ្ងៃដែលបានជ្រើសរួច</div>
                <div class="planning-stat-value">{{ $totalDailyDates }}</div>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <div class="fw-semibold mb-2">មិនអាចរក្សាទុកផែនការប្រចាំថ្ងៃបានទេ។</div>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('planning.plans.daily-activity-plan.update', $plan) }}" method="post">
        @csrf
        @method('PUT')

        @if ($timelineMonths->isEmpty())
            <div class="planning-empty">មិនទាន់មានខែដែលបានកំណត់ពីជំហានទី៤ទេ។</div>
        @else
            <div class="project-plan-wrap">
                <div class="project-plan-scroll">
                    <table class="project-plan-table">
                        <thead>
                            <tr>
                                <th rowspan="3" class="sticky-col project-plan-task">សកម្មភាពដែលត្រូវអនុវត្ត</th>
                                <th rowspan="3" class="sticky-col sticky-col-2 project-plan-desc">បរិយាយសកម្មភាព</th>
                                @foreach ($timelineMonths as $monthNo)
                                    @php
                                        $daysInMonth = Carbon::createFromDate($plan->year, $monthNo, 1)->daysInMonth;
                                    @endphp
                                    <th colspan="{{ $daysInMonth }}" class="month-head">{{ $monthLabels[$monthNo] }}</th>
                                @endforeach
                            </tr>
                            <tr>
                                @foreach ($timelineDays as $day)
                                    <th class="weekday-head {{ $day->isWeekend() ? 'weekend' : '' }}">
                                        @switch($day->dayOfWeekIso)
                                            @case(1) ច. @break
                                            @case(2) អ. @break
                                            @case(3) ព. @break
                                            @case(4) ព្រ. @break
                                            @case(5) សុ. @break
                                            @case(6) ស. @break
                                            @case(7) អា. @break
                                        @endswitch
                                    </th>
                                @endforeach
                            </tr>
                            <tr>
                                @foreach ($timelineDays as $day)
                                    <th class="day-head {{ $day->isWeekend() ? 'weekend' : '' }}">{{ $day->day }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($groupedActivities as $groupIndex => $group)
                                @php
                                    $indicator = $group['indicator'];
                                    $groupId = 'indicator-group-' . $groupIndex;
                                @endphp

                                <tr class="indicator-row">
                                    <td colspan="2" class="indicator-sticky">
                                        <button type="button" class="indicator-toggle" data-target="{{ $groupId }}">
                                            <span class="indicator-toggle-mark">−</span>
                                            <span>
                                                សូចនាករ {{ $groupIndex + 1 }}:
                                                {{ $indicator?->code ? $indicator->code . ' - ' : '' }}{{ $indicator?->name ?: 'មិនមានសូចនាករ' }}
                                            </span>
                                        </button>
                                    </td>
                                    <td colspan="{{ $timelineDays->count() }}"></td>
                                </tr>

                                @foreach ($group['activities'] as $activityIndex => $activity)
                                    @php
                                        $formIndex = $groupIndex . '_' . $activityIndex;
                                        $selectedMonths = $activity->schedules
                                            ->where('period_type', 'monthly')
                                            ->pluck('month')
                                            ->filter()
                                            ->map(fn ($month) => (int) $month)
                                            ->unique()
                                            ->sort()
                                            ->values();
                                        $savedDates = $activity->schedules
                                            ->where('period_type', 'daily')
                                            ->pluck('start_date')
                                            ->filter()
                                            ->map(fn ($date) => optional($date)->format('Y-m-d'))
                                            ->values()
                                            ->all();
                                        $selectedDates = collect(old("activities.$formIndex.dates", $savedDates))
                                            ->filter()
                                            ->unique()
                                            ->sort()
                                            ->values();
                                        $dailyMeta = $activity->schedules->where('period_type', 'daily')->first();
                                        $goalText = old("activities.$formIndex.goal_text", $dailyMeta?->goal_text ?: '');
                                    @endphp

                                    <tr class="activity-row {{ $groupId }}">
                                        <td class="meta-cell sticky-col">
                                            <div class="project-plan-title">{{ $activity->title }}</div>
                                            <div class="project-plan-sub">{{ $activity->description ?: 'មិនមានពិពណ៌នា' }}</div>
                                            <input type="hidden" name="activities[{{ $formIndex }}][item_id]" value="{{ $activity->id }}">
                                            <div class="selected-date-inputs d-none" data-activity-index="{{ $formIndex }}">
                                                @foreach ($selectedDates as $selectedDate)
                                                    <input type="hidden" name="activities[{{ $formIndex }}][dates][]" value="{{ $selectedDate }}">
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="meta-cell sticky-col sticky-col-2">
                                            <textarea
                                                name="activities[{{ $formIndex }}][goal_text]"
                                                class="form-control meta-textarea"
                                                placeholder="បញ្ចូលបរិយាយសកម្មភាព"
                                            >{{ $goalText }}</textarea>
                                        </td>
                                        @foreach ($timelineDays as $day)
                                            @php
                                                $dateString = $day->format('Y-m-d');
                                                $allowed = $selectedMonths->contains((int) $day->month);
                                                $active = $selectedDates->contains($dateString);
                                            @endphp
                                            <td class="p-0 text-center">
                                                <button
                                                    type="button"
                                                    class="plan-day-btn {{ $day->isWeekend() ? 'weekend' : '' }} {{ $active ? 'is-active' : '' }}"
                                                    data-activity-index="{{ $formIndex }}"
                                                    data-date="{{ $dateString }}"
                                                    @disabled(!$allowed)
                                                    title="{{ $dateString }}"
                                                >
                                                    .
                                                </button>
                                            </td>
                                        @endforeach
                                    </tr>

                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="d-flex justify-content-between align-items-center gap-2 mt-4">
            <a href="{{ route('planning.plans.monthly-activity-plan.edit', $plan) }}" class="btn btn-light border">ត្រឡប់ទៅជំហានទី៤</a>
            <button type="submit" class="btn btn-success" @disabled($groupedActivities->isEmpty() || $timelineMonths->isEmpty())>បញ្ចប់ និងរក្សាទុកផែនការប្រចាំថ្ងៃ</button>
        </div>
    </form>
@endsection

@push('planning-scripts')
    <script>
        function refreshActivityDates(activityIndex) {
            const buttons = Array.from(document.querySelectorAll(`.plan-day-btn[data-activity-index="${activityIndex}"].is-active`));
            const inputsWrapper = document.querySelector(`.selected-date-inputs[data-activity-index="${activityIndex}"]`);

            const dates = buttons.map(button => button.dataset.date).sort((a, b) => a.localeCompare(b));

            if (inputsWrapper) {
                inputsWrapper.innerHTML = dates
                    .map(date => `<input type="hidden" name="activities[${activityIndex}][dates][]" value="${date}">`)
                    .join('');
            }
        }

        document.addEventListener('click', function (event) {
            const button = event.target.closest('.plan-day-btn');
            if (button && !button.disabled) {
                button.classList.toggle('is-active');
                refreshActivityDates(button.dataset.activityIndex);
                return;
            }

            const toggle = event.target.closest('.indicator-toggle');
            if (!toggle) {
                return;
            }

            const targetClass = toggle.dataset.target;
            const rows = document.querySelectorAll(`.${targetClass}`);
            const marker = toggle.querySelector('.indicator-toggle-mark');
            const shouldHide = !rows[0]?.classList.contains('is-hidden');

            rows.forEach(row => row.classList.toggle('is-hidden', shouldHide));
            if (marker) {
                marker.textContent = shouldHide ? '+' : '−';
            }
        });
    </script>
@endpush
