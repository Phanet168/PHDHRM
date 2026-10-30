@extends('backend.layouts.app')

@section('title', trim($__env->yieldContent('rpt_title', 'របាយការណ៍សកម្មភាពសុខាភិបាល')) . ' | PHD Report')

@php
    $rptOpenIssues = \Modules\Report\Entities\ReportAction::open()->count();
    $rptNav = [
        ['route' => 'report.health.dashboard', 'match' => 'report.health.dashboard', 'icon' => 'grid', 'label' => 'ផ្ទាំងគ្រប់គ្រង', 'can' => ['read_health_activity_report', 'read_health_report_settings']],
        ['route' => 'report.health.entry', 'match' => 'report.health.entry*', 'icon' => 'report', 'label' => 'បញ្ចូលទិន្នន័យ', 'can' => ['read_health_activity_report', 'read_health_report_settings']],
        ['route' => 'report.health.import', 'match' => 'report.health.import*', 'icon' => 'arrow', 'label' => 'Import Excel', 'can' => ['create_health_activity_report', 'update_health_report_settings']],
        ['route' => 'report.health.his', 'match' => 'report.health.his*', 'icon' => 'building', 'label' => 'ទិន្នន័យ HIS', 'can' => ['create_health_activity_report', 'update_health_report_settings']],
                ['route' => 'report.health.hr', 'match' => 'report.health.hr*', 'icon' => 'users', 'label' => 'ទិន្នន័យ HRM', 'can' => ['create_health_activity_report', 'update_health_report_settings']],
        ['route' => 'report.health.analysis', 'match' => 'report.health.analysis*', 'icon' => 'grid', 'label' => 'វិភាគទិន្នន័យ', 'can' => ['read_health_activity_report', 'read_health_report_settings']],
        ['route' => 'report.health.issues', 'match' => 'report.health.issues*', 'icon' => 'alert', 'label' => 'បញ្ហាប្រឈម', 'badge' => $rptOpenIssues, 'can' => ['read_health_activity_report', 'read_health_report_settings']],
        ['route' => 'report.health.structure', 'match' => 'report.health.structure*', 'icon' => 'building', 'label' => 'ផ្នែក និងជំពូក', 'can' => ['read_health_report_settings']],
        ['route' => 'report.health.reports', 'match' => 'report.health.reports*', 'icon' => 'report', 'label' => 'របាយការណ៍', 'can' => ['read_health_activity_report', 'read_health_report_settings']],
        ['route' => 'report.health.users', 'match' => 'report.health.users*', 'icon' => 'users', 'label' => 'អ្នកប្រើប្រាស់', 'can' => ['read_health_report_settings']],
        ['route' => 'report.health.settings', 'match' => 'report.health.settings*', 'icon' => 'settings', 'label' => 'ការកំណត់', 'can' => ['read_health_report_settings']],
    ];
@endphp

@push('css')
    <style>
        .phd-rpt {
            --rpt-e50: #ecfdf5; --rpt-e100: #d1fae5; --rpt-e400: #34d399; --rpt-e500: #10b981; --rpt-e600: #059669; --rpt-e700: #047857; --rpt-e900: #064e3b;
            --rpt-s950: #020617; --rpt-s900: #0f172a; --rpt-s700: #334155; --rpt-s600: #475569; --rpt-s500: #64748b; --rpt-s400: #94a3b8; --rpt-s200: #e2e8f0; --rpt-s100: #f1f5f9; --rpt-s50: #f8fafc;
            --rpt-a50: #fffbeb; --rpt-a100: #fef3c7; --rpt-a200: #fde68a; --rpt-a500: #f59e0b; --rpt-a600: #d97706; --rpt-a700: #b45309; --rpt-a800: #92400e; --rpt-a900: #78350f;
            --rpt-r50: #fff1f2; --rpt-r500: #f43f5e; --rpt-r700: #be123c;
            --rpt-b50: #eff6ff; --rpt-b100: #dbeafe; --rpt-b500: #3b82f6; --rpt-b700: #1d4ed8;
            --rpt-v100: #ede9fe; --rpt-v700: #6d28d9;
            color: var(--rpt-s950);
            font-size: 14px;
        }
        .phd-rpt .rpt-icon { flex-shrink: 0; }

        /* Module navigation — the Figma sidebar, laid out as a bar above the content */
        .rpt-nav { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; background: var(--rpt-s950); border-radius: 16px; padding: 10px; margin-bottom: 28px; }
        .rpt-brand { display: flex; align-items: center; gap: 12px; padding: 0 14px 0 6px; margin-right: 6px; border-right: 1px solid rgba(255,255,255,.1); white-space: nowrap; }
        .rpt-brand-mark { display: grid; place-items: center; width: 40px; height: 40px; border-radius: 14px; background: var(--rpt-e500); color: #fff; font-weight: 700; font-size: 17px; box-shadow: 0 10px 15px -3px rgba(16,185,129,.2); }
        .rpt-brand-name { color: #fff; font-weight: 600; letter-spacing: .03em; line-height: 1.2; }
        .rpt-brand-sub { color: var(--rpt-s400); font-size: 11px; }
        .rpt-nav a { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 12px; color: var(--rpt-s400); text-decoration: none; white-space: nowrap; transition: background .15s, color .15s; }
        .rpt-nav a:hover { background: rgba(255,255,255,.05); color: #fff; }
        .rpt-nav a.active { background: var(--rpt-e600); color: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.1); }
        .rpt-nav-badge { display: grid; place-items: center; min-width: 22px; height: 22px; padding: 0 6px; border-radius: 999px; background: var(--rpt-r500); color: #fff; font-size: 11px; }

        /* Page title */
        .rpt-title { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; gap: 16px; margin-bottom: 28px; }
        .rpt-eyebrow { color: var(--rpt-e600); font-weight: 500; margin-bottom: 8px; }
        .rpt-title h1 { font-size: 28px; font-weight: 700; letter-spacing: -.02em; margin: 0; color: var(--rpt-s950); }
        .rpt-title p { color: var(--rpt-s500); margin: 8px 0 0; }
        .rpt-actions { display: flex; flex-wrap: wrap; gap: 8px; }

        /* Cards, toolbars, controls */
        .rpt-card { background: #fff; border: 1px solid var(--rpt-s200); border-radius: 16px; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
        .rpt-card-pad { padding: 24px; }
        .rpt-card h2, .rpt-card h3 { font-size: 16px; font-weight: 600; margin: 0; color: var(--rpt-s950); }
        .rpt-sub { color: var(--rpt-s500); font-size: 12px; margin-top: 4px; }
        .rpt-toolbar { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; padding: 20px; border-bottom: 1px solid var(--rpt-s100); }
        .rpt-search { position: relative; flex: 1; min-width: 240px; }
        .rpt-search .rpt-icon { position: absolute; left: 12px; top: 10px; color: var(--rpt-s400); }
        .rpt-search input { padding-left: 44px !important; }
        .rpt-input, .rpt-select { border: 1px solid var(--rpt-s200); border-radius: 12px; background: #fff; padding: 9px 12px; font-size: 14px; color: var(--rpt-s700); outline: none; width: 100%; transition: border-color .15s, box-shadow .15s; }
        .rpt-select { width: auto; min-width: 140px; }
        .rpt-input:focus, .rpt-select:focus { border-color: var(--rpt-e500); box-shadow: 0 0 0 4px rgba(16,185,129,.1); }
        .rpt-input:disabled { background: var(--rpt-s50); }
        textarea.rpt-input { min-height: 84px; }
        .rpt-label { display: block; font-size: 12px; font-weight: 500; color: var(--rpt-s600); margin-bottom: 8px; }
        .rpt-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; border-radius: 12px; padding: 10px 16px; font-size: 14px; border: 1px solid transparent; cursor: pointer; text-decoration: none; transition: background .15s, color .15s; white-space: nowrap; }
        .rpt-btn:disabled { opacity: .55; cursor: not-allowed; }
        .rpt-btn-primary { background: var(--rpt-e600); color: #fff; font-weight: 600; }
        .rpt-btn-primary:hover { background: var(--rpt-e700); color: #fff; }
        .rpt-btn-dark { background: var(--rpt-s900); color: #fff; font-weight: 600; }
        .rpt-btn-dark:hover { background: var(--rpt-s700); color: #fff; }
        .rpt-btn-outline { background: #fff; border-color: var(--rpt-s200); color: var(--rpt-s700); }
        .rpt-btn-outline:hover { background: var(--rpt-s100); color: var(--rpt-s900); }
        .rpt-btn-sm { padding: 6px 12px; font-size: 12px; border-radius: 10px; }
        .rpt-link { background: none; border: 0; padding: 0; color: var(--rpt-e700); font-size: 12px; font-weight: 500; cursor: pointer; text-decoration: none; }
        .rpt-link:hover { color: var(--rpt-e900); text-decoration: underline; }
        .rpt-link-danger { color: var(--rpt-r700); }

        /* Segmented control */
        .rpt-seg { display: inline-flex; background: var(--rpt-s100); border-radius: 12px; padding: 4px; }
        .rpt-seg a, .rpt-seg button { border: 0; background: none; border-radius: 9px; padding: 6px 12px; font-size: 12px; font-weight: 500; color: var(--rpt-s500); text-decoration: none; cursor: pointer; }
        .rpt-seg .active { background: var(--rpt-e600); color: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.1); }

        /* Tables */
        .rpt-table-wrap { overflow-x: auto; }
        .rpt-table { width: 100%; min-width: 880px; border-collapse: collapse; text-align: left; }
        .rpt-table thead th { background: var(--rpt-s50); color: var(--rpt-s500); font-size: 12px; font-weight: 500; padding: 12px 20px; white-space: nowrap; position: sticky; top: 0; z-index: 1; }
        .rpt-table td { padding: 14px 20px; border-top: 1px solid var(--rpt-s100); vertical-align: middle; }
        .rpt-table tbody tr:hover > td { background: var(--rpt-s50); }
        .rpt-table .rpt-id { color: var(--rpt-s400); white-space: nowrap; }
        .rpt-table .rpt-name { font-weight: 500; }
        .rpt-group-dept > td { background: var(--rpt-e50) !important; color: var(--rpt-e900); font-weight: 600; }
        .rpt-group-unit > td { background: #fbfdfc !important; color: var(--rpt-s700); font-weight: 600; padding-left: 32px; }
        .rpt-cell-input { width: 116px; border: 1px solid var(--rpt-s200); border-radius: 8px; padding: 7px 12px; outline: none; }
        .rpt-cell-input:focus { border-color: var(--rpt-e500); }
        .rpt-cell-input.dirty { border-color: var(--rpt-a500); background: var(--rpt-a50); }
        .rpt-statbar { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; background: var(--rpt-s50); padding: 12px 20px; font-size: 12px; }
        .rpt-statbar b { color: var(--rpt-s900); }
        .rpt-foot { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; border-top: 1px solid var(--rpt-s100); padding: 16px; font-size: 12px; color: var(--rpt-s500); }

        /* Badges & progress */
        .rpt-badge { display: inline-block; border-radius: 999px; padding: 4px 10px; font-size: 12px; white-space: nowrap; }
        .rpt-badge-ok { background: var(--rpt-e50); color: var(--rpt-e700); }
        .rpt-badge-warn { background: var(--rpt-a50); color: var(--rpt-a700); }
        .rpt-badge-muted { background: var(--rpt-s100); color: var(--rpt-s600); }
        .rpt-badge-info { background: var(--rpt-b50); color: var(--rpt-b700); }
        .rpt-badge-danger { background: var(--rpt-r50); color: var(--rpt-r700); }
        .rpt-progress { display: flex; align-items: center; gap: 8px; }
        .rpt-progress-track { height: 6px; width: 80px; border-radius: 999px; background: var(--rpt-s100); overflow: hidden; }
        .rpt-progress-bar { height: 100%; border-radius: 999px; background: var(--rpt-e500); }
        .rpt-progress-bar.below { background: var(--rpt-a500); }

        /* Notices */
        .rpt-alert { border-radius: 16px; padding: 14px 18px; margin-bottom: 20px; font-size: 13px; border: 1px solid; }
        .rpt-alert-ok { background: var(--rpt-e50); border-color: var(--rpt-e100); color: var(--rpt-e900); }
        .rpt-alert-err { background: var(--rpt-r50); border-color: #fecdd3; color: var(--rpt-r700); }
        .rpt-note { border-radius: 16px; border: 1px solid var(--rpt-a200); background: var(--rpt-a50); padding: 20px; display: flex; gap: 12px; }
        .rpt-note h3 { font-size: 14px; font-weight: 600; color: var(--rpt-a900); margin: 0; }
        .rpt-note p { font-size: 12px; line-height: 20px; color: var(--rpt-a800); margin: 4px 0 0; }
        .rpt-empty { text-align: center; color: var(--rpt-s500); padding: 40px 20px; }

        /* Save dock */
        .rpt-dock { position: fixed; right: 24px; bottom: 24px; z-index: 1050; display: none; align-items: center; gap: 12px; background: #fff; border: 1px solid var(--rpt-s200); border-radius: 16px; padding: 10px 12px 10px 16px; box-shadow: 0 20px 40px rgba(15,23,42,.18); }
        .rpt-dock.show { display: flex; }

        /* Modal (Bootstrap modal, restyled) */
        .phd-rpt-modal .modal-content { border: 0; border-radius: 16px; }
        .phd-rpt-modal .modal-header { border-bottom: 1px solid #f1f5f9; }
        .phd-rpt-modal .modal-footer { border-top: 1px solid #f1f5f9; }

        /* Collapsible group rows */
        .rpt-toggle { cursor: pointer; user-select: none; }
        .rpt-toggle > td::before { content: '▾'; display: inline-block; width: 16px; color: var(--rpt-s400); transition: transform .15s; }
        .rpt-toggle.collapsed > td::before { transform: rotate(-90deg); }
        .rpt-folded { display: none; }

        @media (max-width: 767px) {
            .rpt-title h1 { font-size: 22px; }
            .rpt-card-pad { padding: 18px; }
            .rpt-brand-sub { display: none; }
            .rpt-nav { padding: 8px; gap: 4px; }
            .rpt-nav a { padding: 8px 10px; font-size: 13px; }
            .rpt-select, .rpt-search { width: 100%; min-width: 0; }

            /* Tables become stacked cards: each cell shows its column name from data-label */
            .rpt-cards { min-width: 0 !important; }
            .rpt-cards thead { display: none; }
            .rpt-cards, .rpt-cards tbody, .rpt-cards tr, .rpt-cards td { display: block; width: 100% !important; }
            .rpt-cards tr.rpt-row, .rpt-cards tbody tr:not([class*="rpt-group"]) { border-top: 1px solid var(--rpt-s200); padding: 10px 0; }
            .rpt-cards td { border: 0 !important; padding: 4px 16px !important; }
            .rpt-cards td[data-label] { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
            .rpt-cards td[data-label]::before { content: attr(data-label); color: var(--rpt-s500); font-size: 12px; flex-shrink: 0; }
            .rpt-cards td.rpt-id { color: var(--rpt-s400); font-size: 12px; padding-bottom: 0 !important; }
            .rpt-cards .rpt-cell-input { width: 140px; }
            .rpt-cards tr[hidden], .rpt-cards tr.rpt-folded { display: none; }
            .rpt-dock { left: 12px; right: 12px; bottom: 12px; justify-content: space-between; }
        }
        @media print { .rpt-nav, .rpt-dock { display: none !important; } }
    </style>
    @stack('rpt_css')
@endpush

@section('content')
    <div class="body-content phd-rpt">
        <nav class="rpt-nav" aria-label="PHD Report">
            <div class="rpt-brand">
                <span class="rpt-brand-mark">P</span>
                <span><span class="rpt-brand-name d-block">PHD REPORT</span><span class="rpt-brand-sub">ប្រព័ន្ធរបាយការណ៍សុខាភិបាល</span></span>
            </div>
            @foreach ($rptNav as $item)
                @canany($item['can'])
                    <a href="{{ route($item['route']) }}" class="{{ request()->routeIs($item['match']) ? 'active' : '' }}">
                        @include('report::health._icon', ['name' => $item['icon']])
                        <span>{{ $item['label'] }}</span>
                        @if (!empty($item['badge']))
                            <span class="rpt-nav-badge">{{ \Modules\Report\Services\PeriodCode::kh($item['badge']) }}</span>
                        @endif
                    </a>
                @endcanany
            @endforeach
        </nav>

        @if (session('success'))
            <div class="rpt-alert rpt-alert-ok">{{ session('success') }}</div>
        @endif
        @if (session('import_errors'))
            <div class="rpt-alert rpt-alert-err">@foreach (session('import_errors') as $e)<div>{{ $e }}</div>@endforeach</div>
        @endif
        @if ($errors->any())
            <div class="rpt-alert rpt-alert-err">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
        @endif

        @yield('rpt')
    </div>
@endsection

@push('js')
    <script>
        window.RPT = {
            csrf: @json(csrf_token()),
            kh: (v) => String(v).replace(/[0-9]/g, (d) => '០១២៣៤៥៦៧៨៩'[d]),
            num: (v) => {
                const s = String(v ?? '').replace(/[០-៩]/g, (d) => '០១២៣៤៥៦៧៨៩'.indexOf(d)).replace(/,/g, '').trim();
                return s === '' || isNaN(Number(s)) ? null : Number(s);
            },
            fmt: (v) => v === null || v === undefined || v === '' ? '—' : window.RPT.kh(Number(v).toLocaleString('en-US', { maximumFractionDigits: 2 })),
            async send(url, method, body) {
                const res = await fetch(url, {
                    method,
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': window.RPT.csrf },
                    body: body ? JSON.stringify(body) : undefined,
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    const msg = data.message || (data.errors ? Object.values(data.errors).flat().join('\n') : 'HTTP ' + res.status);
                    throw new Error(msg);
                }
                return data;
            },
        };
    </script>
    @stack('rpt_js')
@endpush
