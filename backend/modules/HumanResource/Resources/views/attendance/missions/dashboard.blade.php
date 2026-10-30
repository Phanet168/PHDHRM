@extends('backend.layouts.app')
@section('title', 'ផ្ទាំងគ្រប់គ្រងបេសកកម្ម')
@push('css')
<style>
    .mission-dashboard { --md-ink:#1e3250; --md-muted:#64748b; --md-border:#e4eaf2; color:var(--md-ink); background:#f3f6fb; padding:22px; border-radius:20px; }
    .mission-dashboard * { box-sizing:border-box; }
    .mission-dashboard .md-panel { background:#fff; border:1px solid var(--md-border); border-radius:16px; box-shadow:0 4px 18px #243b5a08; padding:22px; height:100%; }
    .mission-dashboard .md-muted { color:var(--md-muted); }
    .mission-dashboard .md-hero { position:relative; overflow:hidden; background:linear-gradient(115deg,#17365d 0%,#2b4f86 55%,#55479a 100%); border-radius:18px; padding:28px; color:#fff; display:flex; justify-content:space-between; align-items:center; gap:20px; margin-bottom:18px; }
    .mission-dashboard .md-hero::before, .mission-dashboard .md-hero::after { content:''; position:absolute; border-radius:50%; background:#ffffff10; pointer-events:none; }
    .mission-dashboard .md-hero::before { width:260px; height:260px; right:-60px; top:-120px; }
    .mission-dashboard .md-hero::after { width:150px; height:150px; right:220px; bottom:-90px; }
    .mission-dashboard .md-hero > * { position:relative; z-index:1; }
    .mission-dashboard .md-eyebrow { color:#c6dbf3; font-size:.8rem; margin-bottom:8px; }
    .mission-dashboard h1 { color:inherit; font-size:1.45rem; font-weight:700; line-height:1.8; margin:0 0 6px; }
    .mission-dashboard .md-hero-sub { font-size:.84rem; color:#d6e5f6; line-height:1.9; }
    .mission-dashboard .md-today { display:inline-flex; align-items:center; gap:8px; margin-top:12px; background:#ffffff1a; border:1px solid #ffffff30; border-radius:999px; padding:6px 14px; font-size:.82rem; color:#fff; text-decoration:none; }
    .mission-dashboard .md-today strong { font-size:1rem; }
    .mission-dashboard .md-hero-actions { display:flex; flex-direction:column; align-items:flex-end; gap:10px; flex-shrink:0; }
    .mission-dashboard .md-hero-buttons { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:8px; }
    .mission-dashboard .md-hero-buttons .btn { color:#fff; border:1px solid #ffffff45; border-radius:10px; padding:9px 14px; background:#ffffff12; font-size:.84rem; }
    .mission-dashboard .md-hero-buttons .btn:hover { background:#ffffff2a; }
    .mission-dashboard .md-hero-buttons .btn.md-solid { background:#fff; color:#1f4479; border-color:#fff; font-weight:700; }
    .mission-dashboard .md-hero-buttons .btn.md-solid:hover { background:#eef4ff; }
    .mission-dashboard .md-year { display:flex; align-items:center; gap:8px; font-size:.8rem; color:#d6e5f6; }
    .mission-dashboard .md-year select { min-height:38px; border-radius:10px; border:1px solid #ffffff45; background-color:#ffffff14; color:#fff; font-size:.85rem; padding:4px 34px 4px 12px; }
    .mission-dashboard .md-year select option { color:#1e3250; }
    .mission-dashboard .md-section-title { font-size:1rem; font-weight:700; color:var(--md-ink); margin:0; line-height:1.8; }
    .mission-dashboard .md-section-sub { color:var(--md-muted); font-size:.78rem; line-height:1.8; margin:2px 0 0; }
    .mission-dashboard .md-tag { font-size:.72rem; color:#436184; background:#edf3fa; border:1px solid #e2eaf5; border-radius:7px; padding:5px 9px; white-space:nowrap; }
    .mission-dashboard .md-tag.is-danger { color:#b42318; background:#fdecec; border-color:#f8d4d4; }

    /* Workflow pipeline */
    .mission-dashboard .md-pipeline { display:grid; grid-template-columns:repeat(auto-fit,minmax(165px,1fr)); gap:12px; margin-top:16px; }
    .mission-dashboard .md-stage { position:relative; display:block; padding:16px; border-radius:14px; border:1px solid var(--md-border); background:#fbfcfe; color:var(--md-ink); text-decoration:none; transition:transform .15s, box-shadow .15s, border-color .15s; }
    .mission-dashboard .md-stage:hover { transform:translateY(-2px); box-shadow:0 10px 22px #1e325014; border-color:var(--md-color); color:var(--md-ink); }
    .mission-dashboard .md-stage.is-busy { background:linear-gradient(140deg,#fff 30%,var(--md-tint)); border-color:color-mix(in srgb,var(--md-color) 35%,#fff); }
    .mission-dashboard .md-stage-head { display:flex; align-items:center; justify-content:space-between; gap:8px; }
    .mission-dashboard .md-step { font-size:.7rem; font-weight:700; color:var(--md-muted); background:#eef2f7; border-radius:999px; padding:2px 9px; }
    .mission-dashboard .md-icon { width:40px; height:40px; border-radius:12px; background:var(--md-tint); color:var(--md-color); display:grid; place-items:center; flex-shrink:0; font-size:1rem; }
    .mission-dashboard .md-stage-count { font-size:2rem; font-weight:800; line-height:1.3; margin:10px 0 2px; color:var(--md-color); font-variant-numeric:tabular-nums; }
    .mission-dashboard .md-stage:not(.is-busy) .md-stage-count { color:#a3b1c2; }
    .mission-dashboard .md-stage-label { font-size:.86rem; font-weight:700; line-height:1.7; }
    .mission-dashboard .md-stage-hint { font-size:.74rem; color:var(--md-muted); line-height:1.7; }
    @media (min-width:1400px) {
        .mission-dashboard .md-pipeline { grid-template-columns:repeat(var(--md-stages),minmax(0,1fr)); gap:22px; }
        .mission-dashboard .md-stage:not(:last-child)::after { content:'›'; position:absolute; right:-16px; top:50%; transform:translateY(-55%); color:#aab8ca; font-size:1.7rem; line-height:1; }
    }

    /* Year figures */
    .mission-dashboard .md-stat { padding:20px; border:1px solid var(--md-edge); border-radius:15px; background:linear-gradient(120deg,#fff,var(--md-tint)); height:100%; position:relative; overflow:hidden; }
    .mission-dashboard .md-stat::before { content:''; position:absolute; inset:0 auto 0 0; width:4px; background:var(--md-color); }
    .mission-dashboard .md-stat-label { font-size:.85rem; font-weight:600; line-height:1.8; }
    .mission-dashboard .md-number { font-size:2.1rem; font-weight:800; line-height:1.4; font-variant-numeric:tabular-nums; color:var(--md-color); margin:6px 0 2px; }
    .mission-dashboard .md-number small { font-size:.8rem; font-weight:500; }
    .mission-dashboard .md-stat-hint { color:var(--md-muted); font-size:.74rem; line-height:1.8; }
    .mission-dashboard .md-meter { height:7px; border-radius:6px; background:#ffffffb0; overflow:hidden; margin-top:8px; }
    .mission-dashboard .md-meter span { display:block; height:100%; border-radius:6px; background:var(--md-color); }

    /* Monthly columns */
    .mission-dashboard .md-columns { display:grid; grid-template-columns:repeat(12,minmax(0,1fr)); gap:8px; height:230px; margin-top:18px; padding-top:6px; background:repeating-linear-gradient(to top,transparent 0,transparent calc(25% - 1px),#edf1f6 calc(25% - 1px),#edf1f6 25%); background-size:100% calc(100% - 28px); background-repeat:no-repeat; }
    .mission-dashboard .md-col { display:flex; flex-direction:column; min-width:0; }
    .mission-dashboard .md-col-track { flex:1; display:flex; flex-direction:column; justify-content:flex-end; align-items:center; }
    .mission-dashboard .md-col-value { font-size:.75rem; font-weight:700; color:#2f5e9e; margin-bottom:4px; font-variant-numeric:tabular-nums; }
    .mission-dashboard .md-col-bar { width:70%; max-width:36px; border-radius:8px 8px 3px 3px; background:linear-gradient(180deg,#7aa7ee,#3f74cf); transform-origin:bottom; animation:md-grow .7s cubic-bezier(.2,.8,.2,1) both; }
    .mission-dashboard .md-col.is-now .md-col-bar { background:linear-gradient(180deg,#9d8cf0,#5b47c4); }
    .mission-dashboard .md-col.is-now .md-col-value { color:#5b47c4; }
    .mission-dashboard .md-col-label { height:28px; display:flex; align-items:center; justify-content:center; font-size:.7rem; color:var(--md-muted); white-space:nowrap; overflow:hidden; }
    .mission-dashboard .md-col.is-now .md-col-label { color:#5b47c4; font-weight:700; }
    .mission-dashboard .md-col-label .md-short { display:none; }
    @keyframes md-grow { from { transform:scaleY(0); } to { transform:scaleY(1); } }

    /* Donut */
    .mission-dashboard .md-donut-layout { display:flex; align-items:center; gap:22px; padding-top:16px; }
    .mission-dashboard .md-donut { width:170px; flex:0 0 170px; aspect-ratio:1; position:relative; }
    .mission-dashboard .md-donut svg { width:100%; height:100%; display:block; }
    .mission-dashboard .md-donut-center { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; }
    .mission-dashboard .md-donut-center strong { font-size:1.9rem; font-weight:800; line-height:1.2; }
    .mission-dashboard .md-legend { flex:1; min-width:0; }
    .mission-dashboard .md-legend-item { display:flex; align-items:center; gap:8px; padding:7px 0; border-bottom:1px solid #edf1f6; font-size:.8rem; }
    .mission-dashboard .md-legend-item:last-child { border-bottom:0; }
    .mission-dashboard .md-legend-item span:nth-child(2) { flex:1; min-width:0; color:var(--md-muted); line-height:1.6; }
    .mission-dashboard .md-legend-item strong { font-variant-numeric:tabular-nums; }
    .mission-dashboard .md-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }

    /* Mission lists */
    .mission-dashboard .md-list { margin-top:12px; }
    .mission-dashboard .md-item { display:flex; gap:14px; align-items:flex-start; padding:14px; margin:0 -14px; border-radius:12px; color:var(--md-ink); text-decoration:none; transition:background-color .15s; }
    .mission-dashboard .md-item + .md-item { border-top:1px solid #edf1f6; }
    .mission-dashboard .md-item:hover { background:#f5f8fd; color:var(--md-ink); }
    .mission-dashboard .md-item-body { flex:1; min-width:0; }
    .mission-dashboard .md-item-title { font-weight:700; font-size:.9rem; line-height:1.7; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
    .mission-dashboard .md-meta { display:flex; flex-wrap:wrap; gap:4px 14px; font-size:.76rem; color:var(--md-muted); line-height:1.8; }
    .mission-dashboard .md-meta i { width:14px; text-align:center; margin-right:3px; opacity:.8; }
    .mission-dashboard .md-pill { font-size:.72rem; font-weight:700; border-radius:999px; padding:3px 10px; white-space:nowrap; background:var(--md-tint,#eef4ff); color:var(--md-color,#2f5e9e); }
    .mission-dashboard .md-people { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
    .mission-dashboard .md-person { display:inline-flex; align-items:center; gap:6px; background:#f2f5fa; border-radius:999px; padding:2px 10px 2px 2px; font-size:.74rem; }
    .mission-dashboard .md-avatar { width:24px; height:24px; border-radius:50%; display:grid; place-items:center; color:#fff; font-size:.72rem; font-weight:700; background:var(--md-avatar,#5b7fbf); }
    .mission-dashboard .md-date { width:54px; flex-shrink:0; text-align:center; border-radius:12px; background:#eef4ff; color:#2f5e9e; padding:6px 0; line-height:1.3; }
    .mission-dashboard .md-date strong { display:block; font-size:1.3rem; font-weight:800; }
    .mission-dashboard .md-date span { font-size:.68rem; }
    .mission-dashboard .md-status-icon { width:38px; height:38px; border-radius:11px; display:grid; place-items:center; flex-shrink:0; background:var(--md-tint); color:var(--md-color); }
    .mission-dashboard .md-empty { padding:30px 10px; color:var(--md-muted); text-align:center; font-size:.85rem; line-height:1.9; }
    .mission-dashboard .md-empty i { display:block; font-size:1.8rem; margin-bottom:10px; color:#c3cedb; }
    .mission-dashboard .md-panel-foot { border-top:1px solid #edf1f6; margin-top:10px; padding-top:12px; font-size:.78rem; }
    .mission-dashboard .md-panel-foot a { text-decoration:none; font-weight:600; }

    @media (max-width:991px) {
        .mission-dashboard .md-hero { flex-direction:column; align-items:flex-start; }
        .mission-dashboard .md-hero-actions { align-items:flex-start; }
        .mission-dashboard .md-hero-buttons { justify-content:flex-start; }
    }
    @media (max-width:767px) {
        .mission-dashboard { padding:12px; border-radius:12px; }
        .mission-dashboard .md-hero { padding:20px; }
        .mission-dashboard h1 { font-size:1.15rem; }
        .mission-dashboard .md-panel { padding:16px; }
        .mission-dashboard .md-stat { padding:16px; }
        .mission-dashboard .md-number { font-size:1.7rem; }
        .mission-dashboard .md-columns { gap:3px; height:190px; }
        .mission-dashboard .md-col-label .md-long { display:none; }
        .mission-dashboard .md-col-label .md-short { display:inline; }
        .mission-dashboard .md-donut-layout { flex-direction:column; align-items:stretch; }
        .mission-dashboard .md-donut { margin:0 auto; }
        .mission-dashboard .md-item { margin:0 -8px; padding:12px 8px; }
    }
    @media (max-width:575px) {
        .mission-dashboard .md-pipeline { grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
        .mission-dashboard .md-stage { padding:12px; }
        .mission-dashboard .md-stage-count { font-size:1.6rem; margin-top:6px; }
        .mission-dashboard .md-stage-label { font-size:.8rem; }
        .mission-dashboard .md-icon { width:32px; height:32px; border-radius:10px; font-size:.85rem; }
        .mission-dashboard .md-stat { padding:14px; }
        .mission-dashboard .md-stat-label { font-size:.78rem; }
        .mission-dashboard .md-number { font-size:1.5rem; }
    }
    @media (prefers-reduced-motion:reduce) {
        .mission-dashboard .md-col-bar { animation:none; }
        .mission-dashboard .md-stage { transition:none; }
    }
</style>
@endpush
@section('content')
    @include('humanresource::attendance.missions.nav')
@php
    $monthsKm = [1 => 'មករា', 'កុម្ភៈ', 'មីនា', 'មេសា', 'ឧសភា', 'មិថុនា', 'កក្កដា', 'សីហា', 'កញ្ញា', 'តុលា', 'វិច្ឆិកា', 'ធ្នូ'];
    $stages = [
        ['office-head', 'រង់ចាំប្រធានការិយាល័យ', 'សំណើថ្មី ត្រូវឯកភាព', 'inbox', '#b7791f', '#fff4e0'],
        ['director', 'រង់ចាំប្រធានមន្ទីរ', 'ត្រូវពិនិត្យ និងអនុម័ត', 'user-check', '#c2410c', '#ffede3'],
        ['approved', 'រៀបចំលិខិតបញ្ជា', 'អនុម័តរួច រង់ចាំចេញលិខិត', 'file-signature', '#2563eb', '#e8f0ff'],
        ['on-mission', 'កំពុងបេសកកម្ម', 'បានចេញលិខិត ឬកំពុងចុះ', 'route', '#7c3aed', '#f1eaff'],
        ['pending-report', 'រង់ចាំរបាយការណ៍', 'មន្ត្រីមិនទាន់ដាក់របាយការណ៍', 'file-alt', '#0e7490', '#e2f5f9'],
        ['report-review', 'ពិនិត្យ និងបិទបញ្ចប់', 'របាយការណ៍បានដាក់ ត្រូវបិទ', 'clipboard-check', '#15803d', '#e5f7ec'],
    ];
    $stages = array_values(array_filter($stages, fn ($stage) => array_key_exists($stage[0], $counts)));
    $waiting = collect($stages)->sum(fn ($stage) => $counts[$stage[0]]);
    $officersToday = $onMissionToday->flatMap->assignments->pluck('employee_id')->unique()->count();
    $typeColors = ['#3b82f6', '#10b981', '#8b5cf6', '#f59e0b', '#ec4899', '#0ea5e9'];
    $avatarColors = ['#5b7fbf', '#2f9e8a', '#8b6bd1', '#d08a2e', '#d0567a', '#3f97c9'];
    $monthMax = max(1, max($monthly));
    $completedPercent = $stats['ended'] > 0 ? round(100 * $stats['completed'] / $stats['ended']) : null;
    $name = fn ($employee) => $employee ? trim($employee->last_name.' '.$employee->first_name) : 'មន្ត្រី';
    $initial = fn ($employee) => $employee ? mb_substr(trim($employee->first_name) ?: trim($employee->last_name), 0, 1) : '?';
    $dateRange = fn ($m) => $m->start_date->isSameDay($m->end_date)
        ? $m->start_date->format('d/m/Y')
        : $m->start_date->format('d/m').' – '.$m->end_date->format('d/m/Y');
@endphp
<div class="mission-dashboard">
    @include('backend.layouts.common.validation')
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <header class="md-hero">
        <div>
            <div class="md-eyebrow"><i class="fa fa-briefcase me-2" aria-hidden="true"></i>បេសកកម្ម / ទិដ្ឋភាពទូទៅ</div>
            <h1>ផ្ទាំងគ្រប់គ្រងបេសកកម្ម</h1>
            <div class="md-hero-sub"><i class="far fa-calendar-alt me-2" aria-hidden="true"></i>{{ now()->format('d/m/Y') }}<span class="mx-2">·</span>ការងាររង់ចាំចាត់ការ {{ $waiting }} · ទិន្នន័យឆ្នាំ {{ $year }}</div>
            <span class="md-today"><i class="fa fa-walking" aria-hidden="true"></i>ថ្ងៃនេះ មន្ត្រី <strong>{{ $officersToday }}</strong> នាក់ កំពុងបំពេញបេសកកម្ម</span>
            @isset($counts['mine'])
                <a class="md-today ms-1" href="{{ route('missions.queue', 'mine') }}"><i class="fa fa-user" aria-hidden="true"></i>សំណើរបស់ខ្ញុំ <strong>{{ $counts['mine'] }}</strong></a>
            @endisset
        </div>
        <div class="md-hero-actions">
            <div class="md-hero-buttons">
                @if($canCreateRequest)<a href="{{ route('missions.request.create') }}" class="btn md-solid"><i class="fa fa-file-signature me-1" aria-hidden="true"></i>ស្នើសុំបេសកកម្ម</a>@endif
                @if($canCreateDirect)<a href="{{ route('missions.direct.create') }}" class="btn"><i class="fa fa-bolt me-1" aria-hidden="true"></i>បង្កើតដោយផ្ទាល់</a>@endif
                @if($canManage)<a href="{{ route('missions.index') }}" class="btn"><i class="fa fa-list me-1" aria-hidden="true"></i>បញ្ជីទាំងអស់</a>@endif
            </div>
            <form method="GET" action="{{ route('missions.dashboard') }}" class="md-year">
                <label for="md-year-select" class="mb-0">ឆ្នាំ</label>
                <select id="md-year-select" name="year" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($years as $option)<option value="{{ $option }}" @selected($option === $year)>{{ $option }}</option>@endforeach
                </select>
                <noscript><button class="btn btn-sm btn-light" type="submit">បង្ហាញ</button></noscript>
            </form>
        </div>
    </header>

    @if($stages)
        <section class="md-panel mb-3" aria-labelledby="md-pipeline-title">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h2 id="md-pipeline-title" class="md-section-title">ដំណើរការសំណើ និងការងារត្រូវចាត់ការ</h2>
                    <p class="md-section-sub">ចុចលើដំណាក់កាលនីមួយៗ ដើម្បីមើលបញ្ជី និងចាត់ការ</p>
                </div>
                <span class="md-tag">សរុប {{ $waiting }}</span>
            </div>
            <div class="md-pipeline" style="--md-stages:{{ count($stages) }}">
                @foreach($stages as $index => [$key, $label, $hint, $icon, $color, $tint])
                    <a class="md-stage {{ $counts[$key] > 0 ? 'is-busy' : '' }}" href="{{ route('missions.queue', $key) }}" style="--md-color:{{ $color }};--md-tint:{{ $tint }}">
                        <div class="md-stage-head"><span class="md-icon"><i class="fa fa-{{ $icon }}" aria-hidden="true"></i></span><span class="md-step">ជំហាន {{ $index + 1 }}</span></div>
                        <div class="md-stage-count">{{ $counts[$key] }}</div>
                        <div class="md-stage-label">{{ $label }}</div>
                        <div class="md-stage-hint">{{ $hint }}</div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <div class="row g-3 mb-3">
        @foreach([
            ['បេសកកម្មឆ្នាំ '.$year, $stats['missions'], 'ដង', 'បេសកកម្មដែលបានអនុម័ត', '#306db2', '#edf4ff', '#d8e6f8', 'briefcase'],
            ['មន្ត្រីបានចុះបេសកកម្ម', $stats['officers'], 'នាក់', 'រាប់មន្ត្រីម្នាក់តែម្តង', '#18846f', '#eaf8f2', '#d3ece3', 'users'],
            ['ថ្ងៃបេសកកម្មសរុប', $stats['person_days'], 'ថ្ងៃ', 'ចំនួនថ្ងៃ × មន្ត្រីម្នាក់ៗ', '#7952b2', '#f4eefe', '#e5d9f4', 'calendar-day'],
        ] as [$title, $value, $unit, $hint, $color, $tint, $edge, $icon])
            <div class="col-xl-3 col-6">
                <article class="md-stat" style="--md-color:{{ $color }};--md-tint:{{ $tint }};--md-edge:{{ $edge }}">
                    <div class="d-flex justify-content-between align-items-center gap-2"><span class="md-stat-label">{{ $title }}</span><span class="md-icon"><i class="fa fa-{{ $icon }}" aria-hidden="true"></i></span></div>
                    <div class="md-number">{{ number_format($value) }} <small>{{ $unit }}</small></div>
                    <div class="md-stat-hint">{{ $hint }}</div>
                </article>
            </div>
        @endforeach
        <div class="col-xl-3 col-6">
            <article class="md-stat" style="--md-color:#a66b14;--md-tint:#fff6e5;--md-edge:#f2e1bd">
                <div class="d-flex justify-content-between align-items-center gap-2"><span class="md-stat-label">បានបិទបញ្ចប់</span><span class="md-icon"><i class="fa fa-flag-checkered" aria-hidden="true"></i></span></div>
                <div class="md-number">{{ $completedPercent === null ? '—' : $completedPercent.'%' }}</div>
                <div class="md-stat-hint">{{ $stats['completed'] }} / {{ $stats['ended'] }} បេសកកម្មដែលផុតកាលបរិច្ឆេទ</div>
                <div class="md-meter" role="progressbar" aria-label="បានបិទបញ្ចប់" aria-valuenow="{{ $stats['completed'] }}" aria-valuemin="0" aria-valuemax="{{ $stats['ended'] }}"><span style="width:{{ $completedPercent ?? 0 }}%"></span></div>
            </article>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-8">
            <section class="md-panel" aria-labelledby="md-monthly-title">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div><h2 id="md-monthly-title" class="md-section-title">បេសកកម្មតាមខែ</h2><p class="md-section-sub">ចំនួនបេសកកម្មតាមខែចាប់ផ្តើម ឆ្នាំ {{ $year }}</p></div>
                    <span class="md-tag">សរុប {{ $stats['missions'] }}</span>
                </div>
                <div class="md-columns" role="img" aria-label="ចំនួនបេសកកម្មតាមខែ ឆ្នាំ {{ $year }}">
                    @foreach($monthly as $month => $count)
                        <div class="md-col {{ $year === now()->year && $month === now()->month ? 'is-now' : '' }}" title="{{ $monthsKm[$month] }}: {{ $count }}">
                            <div class="md-col-track">
                                @if($count > 0)
                                    <span class="md-col-value">{{ $count }}</span>
                                    <span class="md-col-bar" style="height:{{ round(100 * $count / $monthMax * 0.88, 2) }}%;animation-delay:{{ $month * 35 }}ms"></span>
                                @endif
                            </div>
                            <div class="md-col-label"><span class="md-long">{{ $monthsKm[$month] }}</span><span class="md-short">{{ $month }}</span></div>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>
        <div class="col-xl-4">
            <section class="md-panel" aria-labelledby="md-type-title">
                <h2 id="md-type-title" class="md-section-title">តាមប្រភេទបេសកកម្ម</h2>
                <p class="md-section-sub">ឆ្នាំ {{ $year }}</p>
                @if($stats['missions'] > 0)
                    <div class="md-donut-layout">
                        <div class="md-donut">
                            <svg viewBox="0 0 200 200" role="img" aria-labelledby="md-type-desc">
                                <title id="md-type-desc">បេសកកម្ម {{ $stats['missions'] }} តាមប្រភេទ</title>
                                <circle cx="100" cy="100" r="78" fill="none" stroke="#edf1f7" stroke-width="22"/>
                                @php($offset = 0)
                                @foreach($byType as $label => $count)
                                    @php($share = 100 * $count / $stats['missions'])
                                    <circle cx="100" cy="100" r="78" fill="none" stroke="{{ $typeColors[$loop->index % count($typeColors)] }}" stroke-width="22" pathLength="100"
                                        stroke-dasharray="{{ round($share, 3) }} 100" stroke-dashoffset="{{ round(-$offset, 3) }}" transform="rotate(-90 100 100)"/>
                                    @php($offset += $share)
                                @endforeach
                            </svg>
                            <div class="md-donut-center" aria-hidden="true"><strong>{{ $stats['missions'] }}</strong><span class="md-section-sub">បេសកកម្ម</span></div>
                        </div>
                        <div class="md-legend">
                            @foreach($byType as $label => $count)
                                <div class="md-legend-item"><span class="md-dot" style="background:{{ $typeColors[$loop->index % count($typeColors)] }}"></span><span>{{ $label }}</span><strong>{{ $count }}</strong></div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="md-empty"><i class="far fa-chart-bar" aria-hidden="true"></i>មិនមានបេសកកម្មក្នុងឆ្នាំ {{ $year }}</div>
                @endif
            </section>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-7">
            <section class="md-panel" aria-labelledby="md-today-title">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div><h2 id="md-today-title" class="md-section-title">កំពុងបំពេញបេសកកម្មថ្ងៃនេះ</h2><p class="md-section-sub">បេសកកម្មដែលកាលបរិច្ឆេទគ្របដណ្តប់ថ្ងៃនេះ</p></div>
                    <span class="md-tag">{{ $officersToday }} នាក់ · {{ $onMissionToday->count() }} បេសកកម្ម</span>
                </div>
                <div class="md-list">
                    @forelse($onMissionToday as $mission)
                        @php($team = $mission->assignments)
                        <a class="md-item" href="{{ route('missions.show', $mission->id) }}">
                            <span class="md-status-icon" style="--md-color:#7c3aed;--md-tint:#f1eaff"><i class="fa fa-route" aria-hidden="true"></i></span>
                            <div class="md-item-body">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <span class="md-item-title">{{ $mission->title }}</span>
                                    <span class="md-pill" style="--md-color:#6d28d9;--md-tint:#f1eaff">{{ $mission->end_date->isSameDay(now()) ? 'ត្រឡប់ថ្ងៃនេះ' : 'ដល់ '.$mission->end_date->format('d/m') }}</span>
                                </div>
                                <div class="md-meta"><span><i class="fa fa-map-marker-alt" aria-hidden="true"></i>{{ $mission->destination ?: '—' }}</span><span><i class="far fa-calendar" aria-hidden="true"></i>{{ $dateRange($mission) }}</span></div>
                                @if($team->isNotEmpty())
                                    <div class="md-people">
                                        @foreach($team->take(4) as $member)
                                            <span class="md-person"><span class="md-avatar" style="--md-avatar:{{ $avatarColors[($member->employee_id ?? 0) % count($avatarColors)] }}">{{ $initial($member->employee) }}</span>{{ $name($member->employee) }}</span>
                                        @endforeach
                                        @if($team->count() > 4)<span class="md-person ps-2">+{{ $team->count() - 4 }} នាក់ទៀត</span>@endif
                                    </div>
                                @endif
                            </div>
                        </a>
                    @empty
                        <div class="md-empty"><i class="fa fa-building" aria-hidden="true"></i>ថ្ងៃនេះ គ្មានមន្ត្រីចេញបេសកកម្មទេ។</div>
                    @endforelse
                </div>
            </section>
        </div>
        <div class="col-xl-5">
            <section class="md-panel" aria-labelledby="md-upcoming-title">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div><h2 id="md-upcoming-title" class="md-section-title">បេសកកម្មខាងមុខ</h2><p class="md-section-sub">ចាប់ផ្តើមក្នុងរយៈ ១៤ ថ្ងៃខាងមុខ</p></div>
                    <span class="md-tag">{{ $upcoming->count() }}</span>
                </div>
                <div class="md-list">
                    @forelse($upcoming as $mission)
                        @php($daysAway = (int) now()->startOfDay()->diffInDays($mission->start_date))
                        <a class="md-item" href="{{ route('missions.show', $mission->id) }}">
                            <span class="md-date"><strong>{{ $mission->start_date->format('d') }}</strong><span>{{ $monthsKm[$mission->start_date->month] }}</span></span>
                            <div class="md-item-body">
                                <span class="md-item-title">{{ $mission->title }}</span>
                                <div class="md-meta"><span><i class="fa fa-map-marker-alt" aria-hidden="true"></i>{{ $mission->destination ?: '—' }}</span><span><i class="fa fa-users" aria-hidden="true"></i>{{ $mission->assignments->count() }} នាក់</span></div>
                                <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
                                    <span class="md-pill">{{ $daysAway === 1 ? 'ថ្ងៃស្អែក' : 'ក្នុង '.$daysAway.' ថ្ងៃ' }}</span>
                                    @include('humanresource::attendance.missions.status')
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="md-empty"><i class="far fa-calendar-check" aria-hidden="true"></i>មិនមានបេសកកម្មគ្រោងក្នុង ១៤ ថ្ងៃខាងមុខ។</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>

    <div class="row g-3">
        @if($canManage)
            <div class="col-xl-5">
                <section class="md-panel" aria-labelledby="md-overdue-title">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div><h2 id="md-overdue-title" class="md-section-title">របាយការណ៍ហួសកំណត់</h2><p class="md-section-sub">បេសកកម្មបានបញ្ចប់ តែមិនទាន់មានរបាយការណ៍</p></div>
                        <span class="md-tag {{ $overdueCount > 0 ? 'is-danger' : '' }}">{{ $overdueCount }}</span>
                    </div>
                    <div class="md-list">
                        @forelse($overdueList as $mission)
                            <a class="md-item" href="{{ route('missions.show', $mission->id) }}">
                                <span class="md-status-icon" style="--md-color:#b42318;--md-tint:#fdecec"><i class="fa fa-hourglass-end" aria-hidden="true"></i></span>
                                <div class="md-item-body">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <span class="md-item-title">{{ $mission->title }}</span>
                                        <span class="md-pill" style="--md-color:#b42318;--md-tint:#fdecec">ហួស {{ (int) $mission->end_date->diffInDays(now()->startOfDay()) }} ថ្ងៃ</span>
                                    </div>
                                    <div class="md-meta"><span><i class="far fa-calendar" aria-hidden="true"></i>បញ្ចប់ {{ $mission->end_date->format('d/m/Y') }}</span><span><i class="fa fa-users" aria-hidden="true"></i>{{ $mission->assignments_count }} នាក់</span></div>
                                </div>
                            </a>
                        @empty
                            <div class="md-empty"><i class="far fa-check-circle" aria-hidden="true"></i>គ្រប់បេសកកម្មដែលបានបញ្ចប់ មានរបាយការណ៍រួចហើយ។</div>
                        @endforelse
                    </div>
                    @if($overdueCount > $overdueList->count())
                        <div class="md-panel-foot md-muted">បង្ហាញ {{ $overdueList->count() }} ថ្មីបំផុត ក្នុងចំណោម {{ $overdueCount }}</div>
                    @endif
                </section>
            </div>
        @endif
        <div class="{{ $canManage ? 'col-xl-7' : 'col-12' }}">
            <section class="md-panel" aria-labelledby="md-recent-title">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div><h2 id="md-recent-title" class="md-section-title">សកម្មភាពថ្មីៗ</h2><p class="md-section-sub">បេសកកម្ម និងសំណើចុងក្រោយ</p></div>
                </div>
                <div class="md-list">
                    @forelse($recent as $mission)
                        <a class="md-item" href="{{ route('missions.show', $mission->id) }}">
                            <span class="md-status-icon" style="--md-color:#306db2;--md-tint:#edf4ff"><i class="fa fa-briefcase" aria-hidden="true"></i></span>
                            <div class="md-item-body">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <span class="md-item-title">{{ $mission->title }}</span>
                                    <span class="flex-shrink-0">@include('humanresource::attendance.missions.status')</span>
                                </div>
                                <div class="md-meta">
                                    <span><i class="fa fa-map-marker-alt" aria-hidden="true"></i>{{ $mission->destination ?: '—' }}</span>
                                    <span><i class="far fa-calendar" aria-hidden="true"></i>{{ $dateRange($mission) }}</span>
                                    <span><i class="fa fa-users" aria-hidden="true"></i>{{ $mission->assignments_count }} នាក់</span>
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="md-empty"><i class="far fa-folder-open" aria-hidden="true"></i>មិនទាន់មានបេសកកម្មទេ។</div>
                    @endforelse
                </div>
                @if($canManage && $recent->isNotEmpty())
                    <div class="md-panel-foot"><a href="{{ route('missions.index') }}">មើលបញ្ជីទាំងអស់ <i class="fa fa-angle-right ms-1" aria-hidden="true"></i></a></div>
                @endif
            </section>
        </div>
    </div>
</div>
@endsection
