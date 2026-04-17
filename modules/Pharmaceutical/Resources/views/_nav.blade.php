@once
    @push('css')
        <style>
            :root {
                --pharm-primary: #1a6e3e;
                --pharm-primary-dark: #145530;
                --pharm-accent: #2980b9;
                --pharm-soft: #edf7f0;
                --pharm-shadow: 0 10px 24px rgba(15, 48, 30, 0.08);
            }

            .pharmaceutical-ui .pharm-tabs .nav-link {
                border: 0;
                border-radius: 999px;
                padding: 6px 16px;
                margin-right: 6px;
                background: rgba(255, 255, 255, 0.8);
                color: #0f3a2f;
                font-weight: 600;
                transition: all 0.2s ease;
            }

            .pharmaceutical-ui .pharm-tabs .nav-link.active {
                background: var(--pharm-primary);
                color: #fff;
                box-shadow: 0 8px 18px rgba(26, 110, 62, 0.25);
            }

            .pharm-card {
                border: 0;
                box-shadow: var(--pharm-shadow);
            }

            .pharm-card .card-header {
                background: #f4faf6;
                border-bottom: 1px solid rgba(26, 110, 62, 0.12);
            }
        </style>
    @endpush
@endonce

<div class="pharmaceutical-ui mb-3">
    <div class="card pharm-card">
        <div class="card-body py-2">
            <ul class="nav pharm-tabs flex-wrap">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('pharmaceutical.index') ? 'active' : '' }}"
                       href="{{ route('pharmaceutical.index') }}">
                        <i class="fa fa-tachometer-alt me-1"></i>{{ localize('dashboard', 'Dashboard') }}
                    </a>
                </li>
                @canany(['read_pharmaceutical_management', 'read_pharm_medicines'])
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('pharmaceutical.medicines.*') ? 'active' : '' }}"
                       href="{{ route('pharmaceutical.medicines.index') }}">
                        <i class="fa fa-pills me-1"></i>{{ localize('medicines', 'Medicines') }}
                    </a>
                </li>
                @endcanany
                @canany(['read_pharmaceutical_management', 'read_pharm_medicines'])
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('pharmaceutical.categories.*') ? 'active' : '' }}"
                       href="{{ route('pharmaceutical.categories.index') }}">
                        <i class="fa fa-tags me-1"></i>{{ localize('categories', 'Categories') }}
                    </a>
                </li>
                @endcanany
                @canany(['read_pharmaceutical_management', 'read_pharm_distributions'])
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('pharmaceutical.distributions.*') ? 'active' : '' }}"
                       href="{{ route('pharmaceutical.distributions.index') }}">
                        <i class="fa fa-truck me-1"></i>{{ localize('distributions', 'Distributions') }}
                    </a>
                </li>
                @endcanany
                @canany(['read_pharmaceutical_management', 'read_pharm_stock'])
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('pharmaceutical.stock') ? 'active' : '' }}"
                       href="{{ route('pharmaceutical.stock') }}">
                        <i class="fa fa-boxes me-1"></i>{{ localize('stock', 'Stock') }}
                    </a>
                </li>
                @endcanany
                @canany(['read_pharmaceutical_management', 'read_pharm_stock'])
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('pharmaceutical.stock-adjustments.*') ? 'active' : '' }}"
                       href="{{ route('pharmaceutical.stock-adjustments.index') }}">
                        <i class="fa fa-exchange-alt me-1"></i>{{ localize('adjustments', 'Adjustments') }}
                    </a>
                </li>
                @endcanany
                @canany(['read_pharmaceutical_management', 'read_pharm_dispensings'])
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('pharmaceutical.dispensings.*') ? 'active' : '' }}"
                       href="{{ route('pharmaceutical.dispensings.index') }}">
                        <i class="fa fa-hand-holding-medical me-1"></i>{{ localize('dispensing', 'Dispensing') }}
                    </a>
                </li>
                @endcanany
                @canany(['read_pharmaceutical_management', 'read_pharm_reports'])
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('pharmaceutical.reports.*') ? 'active' : '' }}"
                       href="{{ route('pharmaceutical.reports.index') }}">
                        <i class="fa fa-chart-bar me-1"></i>{{ localize('reports', 'Reports') }}
                    </a>
                </li>
                @endcanany
                @canany(['read_pharmaceutical_management', 'read_pharm_reports'])
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('pharmaceutical.summary-reports.usage*') ? 'active' : '' }}"
                       href="{{ route('pharmaceutical.summary-reports.usage') }}">
                        <i class="fa fa-prescription-bottle-alt me-1"></i>{{ localize('usage_report', 'ប្រើប្រាស់ឱសថ') }}
                    </a>
                </li>
                @endcanany
                @canany(['read_pharmaceutical_management', 'read_pharm_reports'])
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('pharmaceutical.summary-reports.stock-summary*') ? 'active' : '' }}"
                       href="{{ route('pharmaceutical.summary-reports.stock-summary') }}">
                        <i class="fa fa-warehouse me-1"></i>{{ localize('stock_summary', 'ស្តុកដើម-ចុងគ្រា') }}
                    </a>
                </li>
                @endcanany
                @canany(['read_pharmaceutical_management', 'read_pharm_medicines', 'read_pharm_stock', 'read_pharm_reports', 'read_pharm_distributions', 'read_pharm_dispensings', 'read_pharm_users'])
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('pharmaceutical.help') ? 'active' : '' }}"
                       href="{{ route('pharmaceutical.help') }}">
                        <i class="fa fa-life-ring me-1"></i>{{ localize('help_center', 'Help Center') }}
                    </a>
                </li>
                @endcanany
                @canany(['read_pharmaceutical_management', 'read_pharm_users'])
                @if(auth()->user() && (int) auth()->user()->user_type_id === 1)
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('pharmaceutical.users.*') ? 'active' : '' }}"
                       href="{{ route('pharmaceutical.users.index') }}">
                        <i class="fa fa-users-cog me-1"></i>{{ localize('users', 'Users') }}
                    </a>
                </li>
                @endif
                @endcanany
            </ul>
        </div>
    </div>
</div>
