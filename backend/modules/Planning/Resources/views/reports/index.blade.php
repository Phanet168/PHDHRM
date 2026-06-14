@extends('planning::layouts.app')

@php
    $pageTitle = 'របាយការណ៍ផែនការ';
    $activeReport = request('report', 'annual');
    $currentUnitName = collect($orgHeaderLines ?? [])->filter()->last() ?: ($reportOrgName ?? '....................................');
    $monthLabels = [1 => 'មករា', 2 => 'កុម្ភៈ', 3 => 'មីនា', 4 => 'មេសា', 5 => 'ឧសភា', 6 => 'មិថុនា', 7 => 'កក្កដា', 8 => 'សីហា', 9 => 'កញ្ញា', 10 => 'តុលា', 11 => 'វិច្ឆិកា', 12 => 'ធ្នូ'];
    $quarterLabels = [1 => 'Q1', 2 => 'Q2', 3 => 'Q3', 4 => 'Q4'];
@endphp

@section('planning-content')
    <style>
        .report-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
        }

        .report-tab {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.7rem 1.35rem;
            border: 1px solid #cfded4;
            border-radius: 999px;
            background: #fff;
            color: #234736;
            text-decoration: none;
            font-weight: 700;
            transition: 0.18s ease;
            box-shadow: 0 6px 16px rgba(15, 48, 30, 0.05);
        }

        .report-tab:hover {
            border-color: #6fa884;
            background: #f4faf6;
            color: #173b2d;
        }

        .report-tab.active {
            border-color: #1a6e3e;
            background: #1f7a45;
            color: #fff;
            box-shadow: 0 10px 20px rgba(26, 110, 62, 0.18);
        }

        .planning-report-scroll {
            overflow: auto;
            border: 1px solid #d8e5dc;
            border-radius: 1rem;
        }

        .planning-report-table {
            margin-bottom: 0;
            min-width: 1100px;
        }

        .planning-report-table th,
        .planning-report-table td {
            vertical-align: middle;
        }

        .planning-report-table .activity-col {
            min-width: 320px;
        }

        .planning-report-table .note-col {
            min-width: 220px;
        }

        .planning-report-table .count-col {
            min-width: 90px;
            text-align: center;
        }

        .planning-report-table .budget-col {
            min-width: 120px;
            text-align: end;
        }

        .planning-report-table .funding-col {
            min-width: 220px;
        }

        .planning-report-table .responsible-col {
            min-width: 180px;
        }

        .planning-report-table .band {
            background: #edf6ef;
            color: #173b2d;
            font-weight: 700;
            text-align: center;
        }

        .planning-report-table .slot-cell {
            min-width: 52px;
            width: 52px;
            text-align: center;
            font-size: 0.83rem;
            padding: 0.45rem 0.15rem;
        }

        .daily-report-table {
            min-width: 1400px;
        }

        .daily-report-table .slot-cell {
            min-width: 36px;
            width: 36px;
            font-size: 0.78rem;
            padding: 0.35rem 0.15rem;
        }

        .daily-report-table .weekend {
            background: #fff2f0;
            color: #c62828;
        }

        .daily-report-table .inactive-day {
            background: #eef2f5;
            color: #98a2ad;
        }

        .slot-hit {
            background: #e3f3e8;
            color: #1f7a45;
            font-weight: 700;
        }

        .slot-hit::after {
            content: "√";
            display: inline-block;
            font-size: 0.95rem;
            line-height: 1;
        }

        .indicator-row td {
            background: #eef6f0;
            color: #173b2d;
            font-weight: 700;
            padding: 0.8rem 1rem;
        }

        .print-sign-table {
            display: none;
            width: 100%;
            margin-top: 2rem;
            border-collapse: collapse;
        }

        .print-sign-table td {
            border: none;
            padding: 0.25rem 0.75rem;
            text-align: center;
            vertical-align: top;
        }

        .print-sign-title {
            font-weight: 700;
        }

        @media print {
            @page {
                size: A4 landscape;
                margin: 14mm 10mm 20mm 10mm;
            }

            .btn,
            .planning-filter-bar,
            .report-tabs,
            .planning-page-title,
            .planning-meta,
            nav,
            .sidebar,
            .header,
            .topbar {
                display: none !important;
            }

            .planning-panel,
            .card,
            .planning-report-scroll,
            .table-responsive {
                border: none !important;
                box-shadow: none !important;
                overflow: visible !important;
            }

            body {
                padding-bottom: 42mm !important;
            }

            .planning-report-table {
                min-width: auto !important;
                width: 100% !important;
            }

            .planning-section-title {
                text-align: center;
                margin-bottom: 1rem;
            }

            .print-sign-table {
                display: table !important;
                break-inside: avoid;
                page-break-inside: avoid;
                position: fixed;
                left: 10mm;
                right: 10mm;
                bottom: 8mm;
                margin-top: 0 !important;
                background: #fff;
            }
        }
    </style>

    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1 planning-page-title">របាយការណ៍</h1>
            <p class="planning-meta mb-0">របាយការណ៍ផែនការសកម្មភាពប្រចាំថ្ងៃ ប្រចាំខែ ប្រចាំត្រីមាស និងប្រចាំឆ្នាំ ក្នុងទំព័រតែមួយតាមរយៈ tabs។</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('planning.reports.export', array_merge(request()->query(), ['report' => $activeReport, 'format' => 'xlsx'])) }}" class="btn btn-success">ទាញយក Excel</a>
            <a href="{{ route('planning.reports.export', array_merge(request()->query(), ['report' => $activeReport, 'format' => 'pdf'])) }}" class="btn btn-danger">ទាញយក PDF</a>
            <a href="{{ route('planning.plans.index') }}" class="btn btn-light border">បញ្ជីផែនការ</a>
        </div>
    </div>

    <form method="get" class="planning-filter-bar mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">ឆ្នាំ</label>
                <input type="number" name="year" class="form-control" value="{{ $filters['year'] ?? '' }}" placeholder="2026">
            </div>
            @if ($activeReport === 'daily')
                <div class="col-md-3">
                    <label class="form-label">ខែ</label>
                    <select name="month" class="form-select">
                        @foreach ($monthLabels as $monthNumber => $monthLabel)
                            <option value="{{ $monthNumber }}" @selected(($filters['month'] ?? '') == (string) $monthNumber)>{{ $monthLabel }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <input type="hidden" name="report" value="{{ $activeReport }}">
            <div class="col-md-{{ $activeReport === 'daily' ? '6' : '9' }}">
                <button class="btn btn-primary" type="submit">បង្ហាញរបាយការណ៍</button>
                <a href="{{ route('planning.reports.index') }}" class="btn btn-light border">កំណត់ឡើងវិញ</a>
            </div>
        </div>
    </form>

    <div class="report-tabs mb-4">
        @foreach ([
            'daily' => 'របាយការណ៍ប្រចាំថ្ងៃ',
            'monthly' => 'របាយការណ៍ប្រចាំខែ',
            'quarterly' => 'របាយការណ៍ប្រចាំត្រីមាស',
            'annual' => 'របាយការណ៍ប្រចាំឆ្នាំ',
        ] as $key => $label)
            <a href="{{ route('planning.reports.index', array_merge(request()->query(), ['report' => $key])) }}" class="report-tab {{ $activeReport === $key ? 'active' : '' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @if ($activeReport === 'daily')
        @php
            $dailyGroupedReport = $dailyActivityReport->groupBy(function ($row) {
                $label = trim(($row->indicator_code ? $row->indicator_code . ' - ' : '') . ($row->indicator_name ?? ''));
                return $label !== '' ? $label : 'មិនមានសូចនាករ';
            });
            $weekdayLabels = ['ច.', 'អ.', 'ព.', 'ព្រ.', 'សុ.', 'ស.', 'អា.'];
        @endphp
        <div class="planning-panel">
            <div class="planning-section-title">របាយការណ៍ផែនការសកម្មភាពប្រចាំថ្ងៃ ខែ{{ $dailyMonthLabel }} ឆ្នាំ {{ $dailyReportYear }}</div>
            <div class="planning-report-scroll">
                <table class="table table-bordered table-sm planning-table planning-report-table daily-report-table">
                    <thead class="table-light">
                    <tr>
                        <th rowspan="3" class="activity-col">សកម្មភាពដែលត្រូវអនុវត្ត</th>
                        <th rowspan="3" class="note-col">បរិយាយសកម្មភាព</th>
                        <th colspan="{{ $dailyTimelineDays->count() }}" class="band">ខែ{{ $dailyMonthLabel }}</th>
                    </tr>
                    <tr>
                        @foreach ($dailyTimelineDays as $day)
                            @php
                                $isActualDay = $day->is_actual_day ?? true;
                                $isWeekend = $isActualDay && in_array($day->dayOfWeekIso, [6, 7], true);
                            @endphp
                            <th class="slot-cell {{ $isWeekend ? 'weekend' : '' }} {{ !$isActualDay ? 'inactive-day' : '' }}">{{ $isActualDay ? $weekdayLabels[$day->dayOfWeekIso - 1] : '' }}</th>
                        @endforeach
                    </tr>
                    <tr>
                        @foreach ($dailyTimelineDays as $day)
                            @php
                                $isActualDay = $day->is_actual_day ?? true;
                                $isWeekend = $isActualDay && in_array($day->dayOfWeekIso, [6, 7], true);
                            @endphp
                            <th class="slot-cell {{ $isWeekend ? 'weekend' : '' }} {{ !$isActualDay ? 'inactive-day' : '' }}">{{ $day->day }}</th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($dailyGroupedReport as $indicatorLabel => $indicatorRows)
                        <tr class="indicator-row"><td colspan="{{ 2 + $dailyTimelineDays->count() }}">{{ $indicatorLabel }}</td></tr>
                        @foreach ($indicatorRows as $row)
                            <tr>
                                <td>{{ $row->activity_title }}</td>
                                <td>{{ $row->goal_text ?: '-' }}</td>
                                @foreach ($dailyTimelineDays as $day)
                                    @php
                                        $isActualDay = $day->is_actual_day ?? true;
                                        $isSelected = in_array($day->day, $row->activity_day_numbers, true);
                                        $isWeekend = $isActualDay && in_array($day->dayOfWeekIso, [6, 7], true);
                                    @endphp
                                    <td class="slot-cell {{ $isWeekend ? 'weekend' : '' }} {{ !$isActualDay ? 'inactive-day' : '' }} {{ $isSelected ? 'slot-hit' : '' }}"></td>
                                @endforeach
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="{{ 2 + $dailyTimelineDays->count() }}"><div class="planning-empty">មិនមានរបាយការណ៍ផែនការសកម្មភាពប្រចាំថ្ងៃទេ។</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @include('planning::reports.partials.print-sign')
        </div>
    @elseif ($activeReport === 'monthly')
        @php
            $monthlyGroupedReport = $monthlyActivityReport->groupBy(function ($row) {
                $label = trim(($row->indicator_code ? $row->indicator_code . ' - ' : '') . ($row->indicator_name ?? ''));
                return $label !== '' ? $label : 'មិនមានសូចនាករ';
            });
        @endphp
        <div class="planning-panel">
            <div class="planning-section-title">របាយការណ៍ផែនការសកម្មភាពប្រចាំខែ</div>
            <div class="planning-report-scroll">
                <table class="table table-bordered table-sm planning-table planning-report-table">
                    <thead class="table-light">
                    <tr>
                        <th rowspan="2" class="activity-col">សកម្មភាពដែលត្រូវអនុវត្ត</th>
                        <th rowspan="2" class="note-col">កំណត់ចំណាំ</th>
                        <th colspan="12" class="band">ខែ</th>
                    </tr>
                    <tr>
                        @foreach ($monthLabels as $monthLabel)
                            <th class="slot-cell">{{ $monthLabel }}</th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($monthlyGroupedReport as $indicatorLabel => $indicatorRows)
                        <tr class="indicator-row"><td colspan="14">{{ $indicatorLabel }}</td></tr>
                        @foreach ($indicatorRows as $row)
                            <tr>
                                <td>{{ $row->activity_title }}</td>
                                <td>{{ $row->notes ?: '-' }}</td>
                                @foreach (array_keys($monthLabels) as $monthNo)
                                    @php $isSelected = in_array($monthNo, $row->activity_month_numbers ?? [], true); @endphp
                                    <td class="slot-cell {{ $isSelected ? 'slot-hit' : '' }}"></td>
                                @endforeach
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="14"><div class="planning-empty">មិនមានរបាយការណ៍ផែនការសកម្មភាពប្រចាំខែទេ។</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @include('planning::reports.partials.print-sign')
        </div>
    @elseif ($activeReport === 'quarterly')
        @php
            $quarterlyGroupedReport = $quarterlyActivityReport->groupBy(function ($row) {
                $label = trim(($row->indicator_code ? $row->indicator_code . ' - ' : '') . ($row->indicator_name ?? ''));
                return $label !== '' ? $label : 'មិនមានសូចនាករ';
            });
        @endphp
        <div class="planning-panel">
            <div class="planning-section-title">របាយការណ៍ផែនការសកម្មភាពប្រចាំត្រីមាស</div>
            <div class="planning-report-scroll">
                <table class="table table-bordered table-sm planning-table planning-report-table">
                    <thead class="table-light">
                    <tr>
                        <th rowspan="2" class="activity-col">សកម្មភាពដែលត្រូវអនុវត្ត</th>
                        <th rowspan="2" class="note-col">លទ្ធផលរំពឹងទុក</th>
                        <th colspan="4" class="band">ត្រីមាស</th>
                        <th rowspan="2" class="budget-col">ថវិកា</th>
                        <th rowspan="2" class="funding-col">ប្រភពថវិកា</th>
                        <th rowspan="2" class="responsible-col">អ្នកទទួលខុសត្រូវ</th>
                    </tr>
                    <tr>
                        @foreach ($quarterLabels as $quarterLabel)
                            <th class="slot-cell">{{ $quarterLabel }}</th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($quarterlyGroupedReport as $indicatorLabel => $indicatorRows)
                        <tr class="indicator-row"><td colspan="9">{{ $indicatorLabel }}</td></tr>
                        @foreach ($indicatorRows as $row)
                            <tr>
                                <td>{{ $row->activity_title }}</td>
                                <td>{{ $row->expected_results ?: '-' }}</td>
                                @foreach (array_keys($quarterLabels) as $quarterNo)
                                    @php $isSelected = in_array($quarterNo, $row->activity_quarters ?? [], true); @endphp
                                    <td class="slot-cell {{ $isSelected ? 'slot-hit' : '' }}"></td>
                                @endforeach
                                <td class="budget-col">{{ number_format((float) $row->total_cost, 2) }}</td>
                                <td>{{ $row->funding_source_names ?: '-' }}</td>
                                <td>{{ $row->responsible_org_unit_name ?: '-' }}</td>
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="9"><div class="planning-empty">មិនមានរបាយការណ៍ផែនការសកម្មភាពប្រចាំត្រីមាសទេ។</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @include('planning::reports.partials.print-sign')
        </div>
    @else
        @php
            $annualGroupedReport = $annualActivityReport->groupBy(function ($row) {
                $label = trim(($row->indicator_code ? $row->indicator_code . ' - ' : '') . ($row->indicator_name ?? ''));
                return $label !== '' ? $label : 'មិនមានសូចនាករ';
            });
        @endphp
        <div class="planning-panel">
            <div class="planning-section-title">របាយការណ៍ផែនការសកម្មភាពប្រចាំឆ្នាំ</div>
            <div class="planning-report-scroll">
                <table class="table table-bordered table-sm planning-table planning-report-table">
                    <thead class="table-light">
                    <tr>
                        <th rowspan="2" class="activity-col">សកម្មភាពដែលត្រូវអនុវត្ត</th>
                        <th colspan="4" class="band">ត្រីមាស</th>
                        <th rowspan="2" class="count-col">ចំនួនខែ</th>
                        <th rowspan="2" class="budget-col">ថវិកា</th>
                        <th rowspan="2" class="funding-col">ប្រភពថវិកា</th>
                        <th rowspan="2" class="responsible-col">អ្នកទទួលខុសត្រូវ</th>
                    </tr>
                    <tr>
                        @foreach ($quarterLabels as $quarterLabel)
                            <th class="slot-cell">{{ $quarterLabel }}</th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($annualGroupedReport as $indicatorLabel => $indicatorRows)
                        <tr class="indicator-row"><td colspan="9">{{ $indicatorLabel }}</td></tr>
                        @foreach ($indicatorRows as $row)
                            <tr>
                                <td>{{ $row->activity_title }}</td>
                                @foreach (array_keys($quarterLabels) as $quarterNo)
                                    @php $isSelected = in_array($quarterNo, $row->activity_quarters ?? [], true); @endphp
                                    <td class="slot-cell {{ $isSelected ? 'slot-hit' : '' }}"></td>
                                @endforeach
                                <td class="count-col">{{ $row->months_count }}</td>
                                <td class="budget-col">{{ number_format((float) $row->total_cost, 2) }}</td>
                                <td>{{ $row->funding_source_names ?: '-' }}</td>
                                <td>{{ $row->responsible_org_unit_name ?: '-' }}</td>
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="9"><div class="planning-empty">មិនមានរបាយការណ៍ផែនការសកម្មភាពប្រចាំឆ្នាំទេ។</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @include('planning::reports.partials.print-sign')
        </div>
    @endif
@endsection
