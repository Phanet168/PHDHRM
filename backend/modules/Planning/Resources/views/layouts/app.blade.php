@extends('backend.layouts.app')

@php
    $pageTitle = $pageTitle ?? 'ការគ្រប់គ្រងផែនការ';
@endphp

@section('title', $pageTitle)

@push('css')
    <style>
        .planning-shell {
            --planning-primary: #1a6e3e;
            --planning-primary-dark: #145530;
            --planning-border: #d8e5dc;
            --planning-muted: #5f6f68;
            --planning-bg: #f6faf7;
            --planning-soft: #edf7f0;
            --planning-danger-soft: #fde7e9;
            --planning-card-shadow: 0 10px 24px rgba(15, 48, 30, 0.08);
            padding: 20px;
            background: var(--planning-bg);
            min-height: calc(100vh - 120px);
        }

        .planning-nav .nav-link {
            border: 0;
            border-radius: 999px;
            padding: 6px 16px;
            margin-right: 6px;
            background: rgba(255, 255, 255, 0.85);
            color: #0f3a2f;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .planning-nav .nav-link.active,
        .planning-nav .nav-link:hover {
            background: var(--planning-primary);
            color: #fff;
            box-shadow: 0 8px 18px rgba(26, 110, 62, 0.2);
        }

        .pharm-card,
        .planning-panel,
        .planning-hero,
        .planning-stat,
        .planning-form-section,
        .planning-filter-card,
        .planning-filter-bar {
            border: 0;
            border-radius: 0.75rem;
            background: #fff;
            box-shadow: var(--planning-card-shadow);
        }

        .pharm-card .card-header {
            background: #f4faf6;
            border-bottom: 1px solid rgba(26, 110, 62, 0.12);
        }

        .planning-hero,
        .planning-form-section,
        .planning-stat,
        .planning-filter-bar {
            padding: 1.25rem;
        }

        .planning-page-title,
        .planning-section-title {
            color: #173b2d;
            font-weight: 700;
        }

        .planning-section-title {
            font-size: 1rem;
            margin-bottom: 1rem;
        }

        .planning-meta,
        .planning-stat-label {
            color: var(--planning-muted);
        }

        .planning-meta {
            font-size: 0.92rem;
        }

        .planning-stat-label {
            font-size: 0.9rem;
            margin-bottom: 0.4rem;
        }

        .planning-stat-value {
            color: #173b2d;
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0;
        }

        .planning-kicker,
        .planning-badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            font-weight: 700;
        }

        .planning-kicker {
            padding: 0.35rem 0.8rem;
            background: var(--planning-soft);
            color: var(--planning-primary-dark);
            font-size: 0.8rem;
        }

        .planning-badge {
            padding: 0.35rem 0.75rem;
            font-size: 0.78rem;
        }

        .planning-badge.badge-approved { background: #dcfce7; color: #166534; }
        .planning-badge.badge-rejected { background: var(--planning-danger-soft); color: #b42318; }
        .planning-badge.badge-info { background: #e8f3fb; color: #1e6091; }

        .planning-table thead th {
            white-space: nowrap;
            background: #f8fbf9;
            color: #35584a;
            font-size: 0.82rem;
            font-weight: 700;
        }

        .planning-empty {
            border: 1px dashed var(--planning-border);
            border-radius: 0.75rem;
            padding: 28px;
            color: var(--planning-muted);
            text-align: center;
            background: #fcfefd;
        }

        @media (max-width: 767.98px) {
            .planning-shell {
                padding: 16px;
            }
        }
    </style>
@endpush

@section('content')
    <div class="planning-shell">
        <div class="card pharm-card mb-4">
            <div class="card-body py-3">
                <ul class="nav planning-nav flex-wrap">
                    <li class="nav-item">
                        <a href="{{ route('planning.dashboard') }}" class="nav-link {{ request()->routeIs('planning.dashboard*') ? 'active' : '' }}">ផ្ទាំងគ្រប់គ្រង</a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('planning.plans.index') }}" class="nav-link {{ request()->routeIs('planning.plans.*') ? 'active' : '' }}">ផែនការ</a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('planning.consolidation.index') }}" class="nav-link {{ request()->routeIs('planning.consolidation.*') ? 'active' : '' }}">បូកសរុប</a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('planning.reports.index') }}" class="nav-link {{ request()->routeIs('planning.reports.*') ? 'active' : '' }}">របាយការណ៍</a>
                    </li>
                    @if (auth()->user()?->can('planning.manage_master_data'))
                        <li class="nav-item">
                            <a href="{{ route('planning.master-data.index', 'org_units') }}" class="nav-link {{ request()->routeIs('planning.master-data.*') ? 'active' : '' }}">ទិន្នន័យគោល</a>
                        </li>
                    @endif
                </ul>
                @if (auth()->user()?->can('planning.manage_master_data'))
                    <hr class="my-3">
                    <div class="d-flex flex-wrap gap-2">
                        @foreach ([
                            'org_units' => 'អង្គភាព',
                            'funding_sources' => 'ប្រភពថវិកា',
                            'chapters' => 'ជំពូក',
                            'accounts' => 'គណនេយ្យ',
                            'sub_accounts' => 'អនុគណនេយ្យ',
                            'programs' => 'កម្មវិធី',
                            'sub_programs' => 'អនុកម្មវិធី',
                            'activity_clusters' => 'ចង្កោមសកម្មភាព',
                            'indicators' => 'សូចនាករ',
                        ] as $resourceKey => $resourceLabel)
                            <a href="{{ route('planning.master-data.index', $resourceKey) }}" class="btn btn-sm {{ request()->route('resource') === $resourceKey ? 'btn-success' : 'btn-outline-secondary' }}">{{ $resourceLabel }}</a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
        @yield('planning-content')
    </div>
@endsection

@push('js')
    @stack('planning-scripts')
@endpush
