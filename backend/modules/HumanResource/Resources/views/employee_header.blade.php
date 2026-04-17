@once
    @push('css')
        <style>
            :root {
                --employee-primary: #1a6e3e;
                --employee-primary-dark: #145530;
                --employee-soft: #edf7f0;
                --employee-shadow: 0 10px 24px rgba(15, 48, 30, 0.08);
            }

            .employee-ui .employee-tabs .nav-link {
                border: 0;
                border-radius: 999px;
                padding: 6px 16px;
                margin-right: 6px;
                background: rgba(255, 255, 255, 0.8);
                color: #0f3a2f;
                font-weight: 600;
                transition: all 0.2s ease;
            }

            .employee-ui .employee-tabs .nav-link.active {
                background: var(--employee-primary);
                color: #fff;
                box-shadow: 0 8px 18px rgba(26, 110, 62, 0.25);
            }

            .employee-ui .employee-header-card {
                border: 0;
                box-shadow: var(--employee-shadow);
            }

            .employee-ui .employee-header-wrap {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }

            .employee-ui .employee-tabs {
                flex: 1 1 auto;
                min-width: 0;
            }

            .employee-ui .employee-help-shortcut {
                border-radius: 999px;
                font-weight: 600;
                white-space: nowrap;
            }

            .employee-ui .employee-help-shortcut.active {
                background: var(--employee-primary);
                border-color: var(--employee-primary);
                color: #fff;
                box-shadow: 0 8px 18px rgba(26, 110, 62, 0.2);
            }

            @media (max-width: 991.98px) {
                .employee-ui .employee-header-wrap {
                    align-items: stretch;
                }

                .employee-ui .employee-help-shortcut {
                    width: 100%;
                    text-align: center;
                }
            }
        </style>
    @endpush
@endonce

<div class="employee-ui row dashboard_heading mb-3">
    <div class="col-12">
        <div class="card employee-header-card fixed-tab">
            <div class="card-body py-2">
                <div class="employee-header-wrap">
                    <ul class="nav employee-tabs flex-wrap align-items-center">
                        @can('read_employee')
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('employees.index') || request()->routeIs('employees.edit') || request()->routeIs('employees.create') || request()->routeIs('employees.show') || request()->routeIs('employees.career_management.edit') ? 'active' : '' }}"
                                    href="{{ route('employees.index') }}">
                                    <i class="fa fa-users me-1"></i>{{ localize('employee') }}
                                </a>
                            </li>
                        @endcan

                        @can('update_employee')
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('employee-position-promotions.*') ? 'active' : '' }}"
                                    href="{{ route('employee-position-promotions.index') }}">
                                    <i class="fa fa-user-tie me-1"></i>{{ localize('manage_position_promotions') }}
                                </a>
                            </li>
                        @endcan

                        @can('update_employee')
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('employee-pay-promotions.*') && !request()->routeIs('employee-pay-promotions.help') ? 'active' : '' }}"
                                    href="{{ route('employee-pay-promotions.index') }}">
                                    <i class="fa fa-layer-group me-1"></i>{{ app()->getLocale() === 'en' ? 'Grade and rank management' : 'គ្រប់គ្រងថ្នាក់ និងឋានន្តរស័ក្តិ' }}
                                </a>
                            </li>
                        @endcan

                        @can('update_employee')
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('employee-workplace-transfers.*') ? 'active' : '' }}"
                                    href="{{ route('employee-workplace-transfers.index') }}">
                                    <i class="fa fa-random me-1"></i>{{ localize('manage_workplace_transfers') }}
                                </a>
                            </li>
                        @endcan

                        @can('update_employee')
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('employee-retirements.*') ? 'active' : '' }}"
                                    href="{{ route('employee-retirements.index') }}">
                                    <i class="fa fa-user-clock me-1"></i>{{ localize('retirement_management') }}
                                </a>
                            </li>
                        @endcan

                        @can('read_inactive_employees_list')
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('employees.inactive_list') ? 'active' : '' }}"
                                    href="{{ route('employees.inactive_list') }}">
                                    <i class="fa fa-user-slash me-1"></i>{{ localize('inactive_employees_list') }}
                                </a>
                            </li>
                        @endcan
                    </ul>

                    @can('read_employee')
                        <a class="btn btn-outline-success employee-help-shortcut {{ request()->routeIs('employees.help') || request()->routeIs('employee-pay-promotions.help') ? 'active' : '' }}"
                            href="{{ route('employees.help') }}">
                            <i class="fa fa-life-ring me-1"></i>{{ app()->getLocale() === 'en' ? 'Help' : 'ជំនួយ' }}
                        </a>
                    @endcan
                </div>
            </div>
        </div>
    </div>
</div>
