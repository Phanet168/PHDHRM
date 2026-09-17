@extends('backend.layouts.app')
@section('title', 'ផ្ទាំងគ្រប់គ្រងវត្តមានមន្ត្រី')
@push('css')
<style>
    .attendance-dashboard { --ad-ink:#20334f; --ad-muted:#64748b; --ad-border:#e4eaf2; color:var(--ad-ink); background:#f3f6fb; padding:22px; border-radius:20px; font-family:var(--khmer-font-family, inherit); }
    .attendance-dashboard * { box-sizing:border-box; }
    .attendance-dashboard .ad-panel { background:#fff; border:1px solid var(--ad-border); border-radius:16px; box-shadow:0 4px 18px #243b5a05; padding:24px; height:100%; }
    .attendance-dashboard .ad-muted { color:var(--ad-muted); }
    .attendance-dashboard .ad-hero { background:linear-gradient(115deg,#17365d,#254f7c); border-radius:16px; padding:28px; color:#fff; display:flex; justify-content:space-between; align-items:center; gap:20px; margin-bottom:18px; }
    .attendance-dashboard .ad-eyebrow { color:#c6dbf3; font-size:.8rem; margin-bottom:10px; }
    .attendance-dashboard h1 { color:inherit; font-size:1.45rem; font-weight:700; line-height:1.8; margin:0 0 8px; }
    .attendance-dashboard .ad-hero-sub { font-size:.84rem; color:#d6e5f6; line-height:1.8; }
    .attendance-dashboard .ad-hero-actions { display:flex; flex-wrap:wrap; gap:8px; flex-shrink:0; }
    .attendance-dashboard .ad-hero-actions .btn { color:#fff; border:1px solid #ffffff45; border-radius:9px; padding:10px 14px; background:#ffffff0d; }
    .attendance-dashboard .ad-hero-actions .btn:hover { background:#ffffff26; }
    .attendance-dashboard .ad-filter-panel { padding:18px 22px; margin-bottom:22px; }
    .attendance-dashboard .form-label { font-size:.8rem; font-weight:600; }
    .attendance-dashboard .form-control,.attendance-dashboard .form-select { border-radius:9px; min-height:44px; border-color:#dce4ef; font-size:.88rem; }
    .attendance-dashboard .ad-filter-panel .btn { border-radius:9px; min-height:44px; display:inline-flex; align-items:center; justify-content:center; gap:8px; }
    .attendance-dashboard .ad-stat { padding:22px; border:1px solid var(--ad-edge); border-radius:15px; background:linear-gradient(120deg,#fff,var(--ad-tint)); height:100%; position:relative; overflow:hidden; }
    .attendance-dashboard .ad-stat:before { content:''; position:absolute; inset:0 auto 0 0; width:4px; background:var(--ad-color); }
    .attendance-dashboard .ad-stat-label { font-size:.85rem; font-weight:600; line-height:1.8; }
    .attendance-dashboard .ad-number { font-size:2.25rem; font-weight:800; line-height:1.4; font-variant-numeric:tabular-nums; color:var(--ad-color); margin:8px 0; }
    .attendance-dashboard .ad-stat-hint { color:var(--ad-muted); font-size:.75rem; line-height:1.8; }
    .attendance-dashboard .ad-icon { width:42px; height:42px; border-radius:12px; background:var(--ad-tint); color:var(--ad-color); display:grid; place-items:center; flex-shrink:0; font-size:1.1rem; }
    .attendance-dashboard .ad-section-title { font-size:1rem; font-weight:700; color:var(--ad-ink); margin:0; line-height:1.8; }
    .attendance-dashboard .ad-section-sub { color:var(--ad-muted); font-size:.78rem; margin-top:5px; line-height:1.8; }
    .attendance-dashboard .ad-tag { font-size:.72rem; color:#436184; background:#edf3fa; border:1px solid #e2eaf5; border-radius:7px; padding:5px 9px; white-space:nowrap; }
    .attendance-dashboard .ad-donut-layout { display:flex; align-items:center; gap:26px; justify-content:center; padding:24px 0 8px; }
    .attendance-dashboard .ad-donut { width:205px; max-width:100%; flex:0 0 205px; aspect-ratio:1; position:relative; }
    .attendance-dashboard .ad-donut svg { width:100%; height:100%; display:block; }
    .attendance-dashboard .ad-donut-center { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; }
    .attendance-dashboard .ad-percent { color:#147d69; font-size:2rem; font-weight:800; font-variant-numeric:tabular-nums; }
    .attendance-dashboard .ad-legend { flex:1; max-width:210px; min-width:130px; }
    .attendance-dashboard .ad-legend-item { padding:12px 0; border-bottom:1px solid #edf1f6; }
    .attendance-dashboard .ad-legend-item:last-child { border-bottom:0; }
    .attendance-dashboard .ad-dot { display:inline-block; width:9px; height:9px; border-radius:50%; margin-right:7px; flex-shrink:0; }
    .attendance-dashboard .ad-legend-label { font-size:.8rem; color:var(--ad-muted); }
    .attendance-dashboard .ad-legend-value { font-size:1.3rem; font-weight:700; margin-top:3px; padding-left:16px; }
    .attendance-dashboard .ad-bars { margin-top:26px; }
    .attendance-dashboard .ad-bar-row { display:grid; grid-template-columns:112px minmax(0,1fr) 40px; gap:12px; align-items:center; margin-bottom:22px; font-size:.8rem; }
    .attendance-dashboard .ad-bar-track { height:18px; border-radius:5px; background:repeating-linear-gradient(to right,#eff3f8 0,#eff3f8 calc(25% - 1px),#dce5ef calc(25% - 1px),#dce5ef 25%); overflow:hidden; }
    .attendance-dashboard .ad-bar-fill { display:block; height:100%; border-radius:5px; background:var(--ad-color); min-width:0; }
    .attendance-dashboard .ad-bar-value { font-size:1rem; font-weight:700; text-align:right; }
    .attendance-dashboard .ad-chart-foot { font-size:.73rem; color:var(--ad-muted); line-height:1.8; border-top:1px solid #edf1f6; padding-top:12px; margin-top:12px; }
    .attendance-dashboard .ad-session { padding:18px 0; border-bottom:1px solid #edf1f6; }
    .attendance-dashboard .ad-session:last-child { border-bottom:0; padding-bottom:0; }
    .attendance-dashboard .ad-progress { height:10px; background:#edf1f7; border-radius:8px; overflow:hidden; margin:12px 0 8px; }
    .attendance-dashboard .ad-progress > span { display:block; height:100%; border-radius:8px; background:var(--ad-color,#269c87); }
    .attendance-dashboard .ad-session-meta { display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap; color:var(--ad-muted); font-size:.73rem; }
    .attendance-dashboard .ad-time { background:#f4f7fc; border:1px solid #e7edf5; border-radius:10px; display:flex; justify-content:space-between; align-items:center; gap:12px; padding:14px; margin-top:10px; font-size:.84rem; }
    .attendance-dashboard .ad-time strong { color:#244c78; font-variant-numeric:tabular-nums; }
    .attendance-dashboard .ad-action { display:flex; align-items:center; gap:12px; padding:15px 0; border-bottom:1px solid #edf1f6; color:var(--ad-ink); text-decoration:none; font-size:.84rem; }
    .attendance-dashboard .ad-action:last-child { border-bottom:0; }
    .attendance-dashboard .ad-action:hover { color:#166bba; }
    .attendance-dashboard .ad-other { display:flex; align-items:center; gap:12px; padding:16px; background:#fff; border:1px solid var(--ad-border); border-radius:12px; height:100%; }
    .attendance-dashboard .ad-other strong { font-size:1.4rem; margin-left:auto; }
    .attendance-dashboard .ad-empty { padding:28px 10px; color:var(--ad-muted); text-align:center; font-size:.85rem; line-height:1.9; }
    .attendance-dashboard .ad-footer { display:flex; align-items:flex-start; gap:8px; font-size:.75rem; color:var(--ad-muted); line-height:1.9; padding:16px 2px 0; }
    @media(min-width:1200px) and (max-width:1450px) { .attendance-dashboard .ad-donut-layout { gap:12px; } .attendance-dashboard .ad-donut { flex-basis:165px; width:165px; } }
    @media(max-width:767px) { .attendance-dashboard { padding:12px; border-radius:12px; } .attendance-dashboard .ad-hero { padding:20px; align-items:flex-start; flex-direction:column; } .attendance-dashboard h1 { font-size:1.15rem; } .attendance-dashboard .ad-panel { padding:18px; } .attendance-dashboard .ad-stat { padding:16px; } .attendance-dashboard .ad-stat-label { font-size:.77rem; } .attendance-dashboard .ad-icon { width:34px; height:34px; font-size:.9rem; } .attendance-dashboard .ad-number { font-size:1.85rem; } .attendance-dashboard .ad-donut-layout { flex-wrap:wrap; gap:12px; } .attendance-dashboard .ad-legend { max-width:none; } .attendance-dashboard .ad-bar-row { grid-template-columns:94px minmax(0,1fr) 28px; gap:8px; font-size:.73rem; } }
    @media(prefers-reduced-motion:no-preference) { .attendance-dashboard .ad-action,.attendance-dashboard .btn { transition:background-color .15s,color .15s; } }
</style>
@endpush
@section('content')
@include('humanresource::attendance_header')
@php
    $base = ['department_id' => $selectedDepartmentId, 'date' => $selectedDate];
    $sessionLabels = ['morning' => 'វេនព្រឹក', 'afternoon' => 'វេនល្ងាច', 'duty' => 'វេនយាម', 'work' => 'វេនធ្វើការ'];
    $sessionColors = ['morning' => '#3385d6', 'afternoon' => '#289b88', 'duty' => '#8660c7', 'work' => '#3385d6'];
    $expectedRecorded = min($summary['expected'], $summary['expected_recorded']);
    $remaining = max(0, $summary['expected'] - $expectedRecorded);
    $recordedPercent = $summary['expected'] > 0 ? round(100 * $expectedRecorded / $summary['expected']) : 0;
    $issues = [
        ['late', 'មកយឺត', '#e5a02e'], ['early_leave', 'ចេញមុន', '#9a72d0'],
        ['absent', 'អវត្តមាន', '#df6374'], ['incomplete', 'ខ្វះស្កេន', '#4e92d5'],
    ];
    $issueMax = max(1, $summary['late'], $summary['early_leave'], $summary['absent'], $summary['incomplete']);
@endphp
<div class="attendance-dashboard">
    @include('backend.layouts.common.validation')
    @include('backend.layouts.common.message')
    <header class="ad-hero">
        <div>
            <div class="ad-eyebrow"><i class="fa fa-chart-pie me-2" aria-hidden="true"></i>វត្តមានមន្ត្រី / ទិដ្ឋភាពទូទៅ</div>
            <h1>{{ $department?->department_name ?? 'ផ្ទាំងគ្រប់គ្រងវត្តមានមន្ត្រី' }}</h1>
            <div class="ad-hero-sub"><i class="far fa-calendar-alt me-2" aria-hidden="true"></i>{{ \Carbon\Carbon::parse($selectedDate)->format('d/m/Y') }} <span class="mx-2">·</span> សង្ខេបវត្តមាន និងវេនប្រចាំថ្ងៃ</div>
        </div>
        <div class="ad-hero-actions">
            <a class="btn btn-sm" href="{{ route('attendances.workflow', $base) }}"><i class="fa fa-sync me-1" aria-hidden="true"></i>ធ្វើបច្ចុប្បន្នភាព</a>
            @can('read_attendance')<a class="btn btn-sm" href="{{ route('attendances.settings') }}"><i class="fa fa-cog me-1" aria-hidden="true"></i>ការកំណត់</a>@endcan
        </div>
    </header>
    <form method="GET" action="{{ route('attendances.workflow') }}" class="ad-panel ad-filter-panel">
        <div class="row g-3 align-items-end">
            <div class="col-lg-5 col-md-6">
                <label for="dashboard-unit" class="form-label">អង្គភាព</label>
                <select id="dashboard-unit" name="department_id" class="form-select" onchange="this.form.submit()">
                    @forelse($departments as $unit)<option value="{{ $unit->id }}" @selected($selectedDepartmentId == $unit->id)>{{ $unit->department_name }}</option>
                    @empty<option value="">មិនមានអង្គភាពក្នុងសិទ្ធិគ្រប់គ្រង</option>@endforelse
                </select>
            </div>
            <div class="col-lg-3 col-md-6"><label for="dashboard-date" class="form-label">កាលបរិច្ឆេទ</label><input id="dashboard-date" name="date" type="date" class="form-control" value="{{ $selectedDate }}" max="{{ now()->toDateString() }}" required></div>
            <div class="col-lg-4 d-flex gap-2"><button class="btn btn-primary flex-grow-1" type="submit"><i class="fa fa-filter" aria-hidden="true"></i>បង្ហាញវត្តមាន</button><a class="btn btn-light" href="{{ route('attendances.workflow', ['department_id' => $selectedDepartmentId]) }}">ថ្ងៃនេះ</a></div>
        </div>
    </form>
    <div class="row g-3 mb-4">
        @foreach([
            ['មន្ត្រីក្នុងអង្គភាព', $summary['total'], 'មានកាលវិភាគធ្វើការ '.$summary['expected'].' នាក់', '#306db2', '#edf4ff', '#d8e6f8', 'users'],
            ['បានកត់ត្រាវត្តមាន', $summary['recorded'], 'រង់ចាំចូល '.$summary['waiting'].' នាក់', '#18846f', '#eaf8f2', '#d3ece3', 'user-check'],
            ['មន្ត្រីមានវេនយាម', $summary['duty'], 'តាមតារាងវេនប្រចាំថ្ងៃ', '#7952b2', '#f4eefe', '#e5d9f4', 'moon'],
            ['ករណីត្រូវពិនិត្យ', $summary['attention'], 'មកយឺត ចេញមុន ខ្វះស្កេន ឬអវត្តមាន', '#a66b14', '#fff6e5', '#f2e1bd', 'exclamation-circle']
        ] as [$title, $count, $hint, $color, $tint, $edge, $icon])
            <div class="col-xl-3 col-6"><article class="ad-stat" style="--ad-color:{{ $color }};--ad-tint:{{ $tint }};--ad-edge:{{ $edge }}"><div class="d-flex justify-content-between align-items-center gap-2"><span class="ad-stat-label">{{ $title }}</span><span class="ad-icon"><i class="fa fa-{{ $icon }}" aria-hidden="true"></i></span></div><div class="ad-number">{{ $count }} <span style="font-size:.8rem;font-weight:500">នាក់</span></div><div class="ad-stat-hint">{{ $hint }}</div></article></div>
        @endforeach
    </div>
    <div class="row g-3 mb-3">
        <div class="col-xl-5">
            <section class="ad-panel" aria-labelledby="attendance-ratio-title">
                <div class="d-flex justify-content-between align-items-center gap-2"><h2 id="attendance-ratio-title" class="ad-section-title">សមាមាត្រកត់ត្រាវត្តមាន</h2><span class="ad-tag">ប្រចាំថ្ងៃ</span></div>
                <p class="ad-section-sub mb-0">មន្ត្រីដែលមានកាលវិភាគធ្វើការ {{ $summary['expected'] }} នាក់</p>
                <div class="ad-donut-layout">
                    <div class="ad-donut">
                        <svg viewBox="0 0 200 200" role="img" aria-labelledby="attendance-ratio-description">
                            <title id="attendance-ratio-description">បានកត់ត្រា {{ $expectedRecorded }} នាក់ ក្នុងចំណោម {{ $summary['expected'] }} នាក់</title>
                            <circle cx="100" cy="100" r="82" fill="none" stroke="#edf1f7" stroke-width="20"/>
                            @if($expectedRecorded > 0)<circle cx="100" cy="100" r="82" fill="none" stroke="#27a48b" stroke-width="20" pathLength="100" stroke-dasharray="{{ $recordedPercent }} 100" transform="rotate(-90 100 100)"/>@endif
                        </svg>
                        <div class="ad-donut-center" aria-hidden="true"><span class="ad-percent">{{ $summary['expected'] ? $recordedPercent.'%' : '—' }}</span><span class="ad-section-sub">បានកត់ត្រា</span></div>
                    </div>
                    <div class="ad-legend">
                        <div class="ad-legend-item"><div class="ad-legend-label"><span class="ad-dot" style="background:#27a48b"></span>បានកត់ត្រា</div><div class="ad-legend-value">{{ $expectedRecorded }} <small class="ad-muted" style="font-size:.75rem;font-weight:400">នាក់</small></div></div>
                        <div class="ad-legend-item"><div class="ad-legend-label"><span class="ad-dot" style="background:#bdcbdc"></span>មិនទាន់កត់ត្រា</div><div class="ad-legend-value">{{ $remaining }} <small class="ad-muted" style="font-size:.75rem;font-weight:400">នាក់</small></div></div>
                    </div>
                </div>
                <div class="ad-chart-foot">{{ $summary['expected'] ? 'គិតតែមន្ត្រីដែលត្រូវធ្វើការនៅថ្ងៃដែលបានជ្រើស។ បានកត់ត្រា មានន័យថាមានស្កេនយ៉ាងតិចមួយដង។' : 'មិនមានកាលវិភាគធ្វើការសម្រាប់ថ្ងៃដែលបានជ្រើស។' }}</div>
            </section>
        </div>
        <div class="col-xl-7">
            <section class="ad-panel" aria-labelledby="attendance-status-title">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2"><h2 id="attendance-status-title" class="ad-section-title">សង្ខេបស្ថានភាពវត្តមាន</h2><span class="ad-tag">ត្រូវពិនិត្យ {{ $summary['attention'] }} នាក់</span></div>
                <div class="ad-section-sub">ចំនួនមន្ត្រីតាមប្រភេទករណី</div>
                <div class="ad-bars">
                    @foreach($issues as [$key, $label, $color])
                        <div class="ad-bar-row"><span>{{ $label }}</span><div class="ad-bar-track" role="img" aria-label="{{ $label }} {{ $summary[$key] }} នាក់"><span class="ad-bar-fill" style="--ad-color:{{ $color }};width:{{ round(100 * $summary[$key] / $issueMax, 2) }}%"></span></div><strong class="ad-bar-value">{{ $summary[$key] }}</strong></div>
                    @endforeach
                </div>
                <div class="ad-chart-foot">មន្ត្រីម្នាក់អាចមានច្រើនករណី។ កាត «ករណីត្រូវពិនិត្យ» រាប់មន្ត្រីម្នាក់តែម្តង។</div>
            </section>
        </div>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-xl-5">
            <section class="ad-panel" aria-labelledby="session-title">
                <h2 id="session-title" class="ad-section-title">ការចូល–ចេញតាមវេន</h2>
                <div class="ad-section-sub">សមាមាត្រកត់ត្រាចូល និងចេញបានគ្រប់</div>
                @forelse(collect($sessionSummary)->filter(fn ($session) => $session['expected'] > 0) as $name => $session)
                    @php($completion = round(100 * $session['complete'] / $session['expected']))
                    <div class="ad-session" style="--ad-color:{{ $sessionColors[$name] }}">
                        <div class="d-flex align-items-center justify-content-between gap-2"><span class="small fw-semibold">{{ $sessionLabels[$name] }}</span><strong style="color:var(--ad-color)">{{ $completion }}%</strong></div>
                        <div class="ad-progress" role="progressbar" aria-label="{{ $sessionLabels[$name] }} កត់ត្រាចូលចេញគ្រប់" aria-valuenow="{{ $session['complete'] }}" aria-valuemin="0" aria-valuemax="{{ $session['expected'] }}"><span style="width:{{ $completion }}%"></span></div>
                        <div class="ad-session-meta"><span>គ្រប់ {{ $session['complete'] }} / {{ $session['expected'] }} នាក់</span><span>មកយឺត {{ $session['late'] }} នាក់</span></div>
                    </div>
                @empty<div class="ad-empty"><i class="far fa-calendar fa-2x d-block mb-3" aria-hidden="true"></i>មិនមានវេនធ្វើការនៅថ្ងៃនេះ។</div>@endforelse
            </section>
        </div>
        <div class="col-xl-4 col-md-7">
            <section class="ad-panel" aria-labelledby="unit-hours-title">
                <h2 id="unit-hours-title" class="ad-section-title">ម៉ោងគោលរបស់អង្គភាព</h2>
                @if($defaultShift)
                    <div class="ad-section-sub mb-3">{{ $defaultShift->name }}</div>
                    @if($defaultShift->morning_end_time)
                        <div class="ad-time"><span><i class="far fa-sun me-2" aria-hidden="true"></i>ព្រឹក</span><strong dir="ltr">{{ substr($defaultShift->start_time, 0, 5) }} – {{ substr($defaultShift->morning_end_time, 0, 5) }}</strong></div>
                        <div class="ad-time"><span><i class="far fa-clock me-2" aria-hidden="true"></i>ល្ងាច</span><strong dir="ltr">{{ substr($defaultShift->afternoon_start_time, 0, 5) }} – {{ substr($defaultShift->end_time, 0, 5) }}</strong></div>
                    @else<div class="ad-time"><span>ម៉ោងធ្វើការ</span><strong dir="ltr">{{ substr($defaultShift->start_time, 0, 5) }} – {{ substr($defaultShift->end_time, 0, 5) }}</strong></div>@endif
                    <div class="ad-section-sub mt-3">អនុគ្រោះមកយឺត {{ $defaultShift->grace_late_minutes }} នាទី</div>
                @else<div class="ad-empty">អង្គភាពនេះមិនទាន់កំណត់ម៉ោងគោល។</div>@endif
                @can('read_shift')<a class="btn btn-sm btn-outline-primary mt-3 w-100" href="{{ route('shifts.index', ['department_id' => $selectedDepartmentId]) }}">កំណត់ម៉ោងធ្វើការ</a>@endcan
            </section>
        </div>
        <div class="col-xl-3 col-md-5">
            <section class="ad-panel" aria-labelledby="quick-actions-title">
                <h2 id="quick-actions-title" class="ad-section-title mb-2">សកម្មភាពរហ័ស</h2>
                @can('create_attendance')<a class="ad-action" href="{{ route('attendances.create', ['department_id' => $selectedDepartmentId]) }}"><i class="fa fa-fingerprint text-primary" aria-hidden="true"></i><span>កត់ត្រាវត្តមាន</span><i class="fa fa-angle-right ms-auto" aria-hidden="true"></i></a>@endcan
                @can('read_shift_roster')<a class="ad-action" href="{{ route('shift-rosters.index', ['department_id' => $selectedDepartmentId, 'year' => \Carbon\Carbon::parse($selectedDate)->year, 'month' => \Carbon\Carbon::parse($selectedDate)->month]) }}"><i class="far fa-calendar-alt" style="color:#7952b2" aria-hidden="true"></i><span>តារាងវេនយាម</span><i class="fa fa-angle-right ms-auto" aria-hidden="true"></i></a>@endcan
                @can('read_monthly_attendance')<a class="ad-action" href="{{ route('attendances.monthlyCreate', ['department_id' => $selectedDepartmentId, 'year' => \Carbon\Carbon::parse($selectedDate)->year, 'month' => \Carbon\Carbon::parse($selectedDate)->month]) }}"><i class="fa fa-calendar-check" style="color:#18846f" aria-hidden="true"></i><span>វត្តមានប្រចាំខែ</span><i class="fa fa-angle-right ms-auto" aria-hidden="true"></i></a>@endcan
            </section>
        </div>
    </div>
    <div class="row g-3">
        @foreach([
            ['leave', 'សុំច្បាប់', 'calendar-minus', '#306db2', '#edf4ff'],
            ['mission', 'បេសកកម្ម', 'map-marker-alt', '#7952b2', '#f4eefe'],
            ['off', 'ថ្ងៃសម្រាក / ថ្ងៃបុណ្យ', 'coffee', '#18846f', '#eaf8f2'],
            ['unscheduled', 'មិនទាន់កំណត់ម៉ោង', 'clock', '#a66b14', '#fff6e5']
        ] as [$key, $label, $icon, $color, $tint])
            <div class="col-xl-3 col-sm-6"><div class="ad-other"><span class="ad-icon" style="--ad-color:{{ $color }};--ad-tint:{{ $tint }}"><i class="fa fa-{{ $icon }}" aria-hidden="true"></i></span><span class="small">{{ $label }}</span><strong>{{ $summary[$key] }}</strong></div></div>
        @endforeach
    </div>
    <footer class="ad-footer"><i class="fa fa-info-circle mt-1" aria-hidden="true"></i><span>ម៉ោងធ្វើការកំពុងបន្ត មិនត្រូវបានចាត់ទុកជាអវត្តមានចុងក្រោយទេ។ ទិន្នន័យគិតត្រឹម {{ now()->format('H:i') }}។</span></footer>
</div>
@endsection
