<!-- Sidebar  -->
<nav class="sidebar sidebar-bunker">
    <div class="sidebar-header">
        <a href="{{ route('home') }}" class="sidebar-brand w-100">
            <img class="sidebar-logo sidebar_brand_icon w-100"
                src="{{ app_setting()->sidebar_logo ?? asset('assets/HRM2.png') }}" alt="{{ localize('logo') }}">
            <img class="collapsed-logo" src="{{ app_setting()->sidebar_collapsed_logo ?? asset('assets/mini-logo.png') }}"
                alt="{{ localize('logo') }}">
        </a>
    </div>
    <!--/.sidebar header-->
    <div class="sidebar-body">
        <div class="search sidebar-form">
            <div class="search__inner sidebar-search">
                <input id="search" type="text" class="form-control search__text" placeholder="Menu Search..."
                    autocomplete="off">
                {{-- <i class="typcn typcn-zoom-outline search__helper" data-sa-action="search-close"></i> --}}
            </div>
        </div>
        <nav class="sidebar-nav sidebar-menu-icons">
            <ul class="metismenu">
                @can('read_dashboard')
                    <li class="{{ request()->is('dashboard') ? 'mm-active' : '' }}">
                        <a href="{{ route('home') }}">
                            <i class="fas fa-home sidebar-menu-icon" aria-hidden="true"></i>
                            <span>{{ localize('dashboard') }}</span>
                        </a>
                    </li>
                @endcan

                @can('read_mission')
                    <li class="{{ request()->routeIs('missions.*') ? 'mm-active' : '' }}">
                        <a href="{{ route('missions.index') }}" class="material-ripple">
                            <i class="fas fa-briefcase sidebar-menu-icon" aria-hidden="true"></i><span>គ្រប់គ្រងបេសកកម្ម</span>
                        </a>
                    </li>
                @endcan
                @php
                    $canUseAttendanceModule = auth()->user()?->can('read_attendance')
                        || app(\Modules\HumanResource\Support\OrgRolePermissionService::class)->canUserPerform(
                            auth()->user(),
                            'attendance',
                            'create_adjustment',
                            null,
                            []
                        )
                        || app(\Modules\HumanResource\Support\OrgRolePermissionService::class)->canUserPerform(
                            auth()->user(),
                            'attendance',
                            'manage_exceptions',
                            null,
                            []
                        );
                @endphp
                @if ($canUseAttendanceModule)
                    <li class="{{ request()->routeIs('attendances.*') ? 'mm-active' : '' }}">
                        <a class="has-arrow material-ripple" href="#">
                            <i class="fas fa-calendar-check sidebar-menu-icon" aria-hidden="true"></i>
                            <span> {{ localize('attendance') }}</span>
                        </a>
                        <ul class="nav-second-level {{ request()->routeIs('attendances.*') ? 'mm-show' : '' }}">
                            @if ($canUseAttendanceModule)
                                <li class="{{ request()->routeIs('attendances.workflow') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('attendances.workflow') }}"><i class="fas fa-project-diagram sidebar-menu-icon" aria-hidden="true"></i> {{ localize('attendance_workflow', 'Workflow ÃƒÂ¡Ã…Â¾Ã…â€œÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¾Ã‹Å“ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“') }}</a>
                                </li>
                                @can('create_attendance')
                                    <li class="{{ request()->routeIs('attendances.create') ? 'mm-active' : '' }}">
                                        <a class="dropdown-item"
                                            href="{{ route('attendances.create') }}"><i class="fas fa-user-check sidebar-menu-icon" aria-hidden="true"></i> {{ localize('attendance_form') }}</a>
                                    </li>
                                @endcan
                                @can('create_monthly_attendance')
                                    <li class="{{ request()->routeIs('attendances.monthlyCreate') ? 'mm-active' : '' }}">
                                        <a class="dropdown-item"
                                            href="{{ route('attendances.monthlyCreate') }}"><i class="fas fa-calendar-alt sidebar-menu-icon" aria-hidden="true"></i> {{ localize('monthly_attendance') }}</a>
                                    </li>
                                @endcan
                                @can('read_missing_attendance')
                                    <li class="{{ request()->routeIs('attendances.missingAttendance') ? 'mm-active' : '' }}">
                                        <a class="dropdown-item"
                                            href="{{ route('attendances.missingAttendance') }}"><i class="fas fa-user-times sidebar-menu-icon" aria-hidden="true"></i> {{ localize('missing_attendance') }}</a>
                                    </li>
                                @endcan
                            @endif
                        </ul>
                    </li>
                @endif

                @can('read_award')
                    <li class="{{ request()->routeIs('award.*') ? 'mm-active' : '' }}">
                        <a class="has-arrow material-ripple" href="#">
                            <i class="fas fa-trophy sidebar-menu-icon" aria-hidden="true"></i>
                            <span> {{ localize('award') }}</span>
                        </a>
                        <ul class="nav-second-level {{ request()->routeIs('award.*') ? 'mm-show' : '' }}">
                            <li class="{{ request()->routeIs('award.*') ? 'mm-active' : '' }}">
                                <a class="dropdown-item"
                                    href="{{ route('award.index') }}"><i class="fas fa-award sidebar-menu-icon" aria-hidden="true"></i> {{ localize('award_list') }}</a>
                            </li>
                        </ul>
                    </li>
                @endcan

                @if (auth()->user()->admin())
                    @php
                        $showAdvancedGovernance = (bool) config('hr_governance.ui.show_advanced_central_governance', false);
                        $masterDataHrActive = request()->routeIs('departments.*')
                            || request()->routeIs('professional-skills.*')
                            || request()->routeIs('positions.*')
                            || request()->routeIs('pay-levels.*')
                            || request()->routeIs('salary-scales.*')
                            || request()->routeIs('org-unit-type-positions.*');
                        if ($showAdvancedGovernance) {
                            $masterDataHrActive = $masterDataHrActive
                                || request()->routeIs('user-org-roles.*')
                                || request()->routeIs('workflow-policies.*')
                                || request()->routeIs('org-role-module-permissions.*')
                                || request()->routeIs('system-roles.*');
                        }
                    @endphp
                    <li class="{{ $masterDataHrActive ? 'mm-active' : '' }}">
                        <a class="has-arrow material-ripple" href="#">
                            <i class="fas fa-sitemap sidebar-menu-icon" aria-hidden="true"></i>
                            <span>{{ localize('master_data_hr', 'Master Data HR') }}</span>
                        </a>
                        <ul class="nav-second-level {{ $masterDataHrActive ? 'mm-show' : '' }}">
                            @can('read_department')
                                <li class="{{ request()->routeIs('departments.*') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item" href="{{ route('departments.index') }}"><i class="fas fa-building sidebar-menu-icon" aria-hidden="true"></i>
                                        {{ localize('org_unit_management', 'Org Unit Management') }}
                                    </a>
                                </li>
                                @php
                                    $orgStructureActive = request()->routeIs('org-unit-type-positions.*');
                                    if ($showAdvancedGovernance) {
                                        $orgStructureActive = $orgStructureActive
                                            || request()->routeIs('user-org-roles.*')
                                            || request()->routeIs('workflow-policies.*')
                                            || request()->routeIs('org-role-module-permissions.*')
                                            || request()->routeIs('system-roles.*');
                                    }
                                @endphp
                                <li class="{{ $orgStructureActive ? 'mm-active' : '' }}">
                                    <a class="dropdown-item" href="{{ route('org-unit-type-positions.index') }}"><i class="fas fa-sitemap sidebar-menu-icon" aria-hidden="true"></i>
                                        {{ localize('org_structure_management', 'Org Structure Management') }}
                                    </a>
                                </li>
                            @endcan
                            @can('read_setup_rules')
                                <li class="{{ request()->routeIs('professional-skills.*') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item" href="{{ route('professional-skills.index') }}"><i class="fas fa-graduation-cap sidebar-menu-icon" aria-hidden="true"></i>
                                        {{ localize('professional_skill_management', 'Professional Skill Management') }}
                                    </a>
                                </li>
                            @endcan
                            @can('read_positions')
                                <li class="{{ request()->routeIs('positions.*') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item" href="{{ route('positions.index') }}"><i class="fas fa-id-badge sidebar-menu-icon" aria-hidden="true"></i>
                                        {{ localize('positions', 'Positions') }}
                                    </a>
                                </li>
                            @endcan
                            @can('read_setup_rules')
                                <li class="{{ request()->routeIs('pay-levels.*') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item" href="{{ route('pay-levels.index') }}"><i class="fas fa-layer-group sidebar-menu-icon" aria-hidden="true"></i>
                                        {{ localize('pay_level_management', 'Pay Level Management') }}
                                    </a>
                                </li>
                                <li class="{{ request()->routeIs('salary-scales.*') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item" href="{{ route('salary-scales.index') }}"><i class="fas fa-money-check-alt sidebar-menu-icon" aria-hidden="true"></i>
                                        {{ localize('salary_scale_management', 'Salary Scale Management') }}
                                    </a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endif
                @can('read_employee')
                    <li class="{{ request()->routeIs('employees*') || request()->routeIs('employee-pay-promotions.*') || request()->routeIs('employee-workplace-transfers.*') || request()->routeIs('employee-retirements.*') ? 'mm-active' : '' }}">
                        <a class="has-arrow material-ripple" href="#">
                            <i class="fas fa-users sidebar-menu-icon" aria-hidden="true"></i>
                            <span> {{ localize('employee') }}</span>
                        </a>
                        <ul class="nav-second-level {{ request()->routeIs('employees*') || request()->routeIs('employee-pay-promotions.*') || request()->routeIs('employee-workplace-transfers.*') || request()->routeIs('employee-retirements.*') ? 'mm-show' : '' }}">
                            @can('read_employee')
                                <li class="{{ request()->routeIs('employees.*') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('employees.index') }}"><i class="fas fa-address-book sidebar-menu-icon" aria-hidden="true"></i> {{ localize('employee') }}</a>
                                </li>
                            @endcan
                            @can('update_employee')
                                <li class="{{ request()->routeIs('employee-pay-promotions.*') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item" href="{{ route('employee-pay-promotions.index') }}"><i class="fas fa-level-up-alt sidebar-menu-icon" aria-hidden="true"></i>
                                        {{ localize('grade_and_rank_management', 'ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¡ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¸Ã¢â‚¬Â¹ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¡ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¾ÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â¹ ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã‚Â·ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¾ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¹ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¸Ã‚ÂÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¾Ã‚Â·') }}
                                    </a>
                                </li>
                                <li class="{{ request()->routeIs('employee-workplace-transfers.*') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item" href="{{ route('employee-workplace-transfers.index') }}"><i class="fas fa-exchange-alt sidebar-menu-icon" aria-hidden="true"></i>
                                        {{ localize('manage_workplace_transfers') }}
                                    </a>
                                </li>
                                <li class="{{ request()->routeIs('employee-retirements.*') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item" href="{{ route('employee-retirements.index') }}"><i class="fas fa-user-clock sidebar-menu-icon" aria-hidden="true"></i>
                                        {{ localize('retirement_management') }}
                                    </a>
                                </li>
                            @endcan
                            @can('read_employee_performance')
                                <li class="{{ request()->routeIs('employee-performances.*') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('employee-performances.index') }}"><i class="fas fa-chart-line sidebar-menu-icon" aria-hidden="true"></i> {{ localize('employee_performance ') }}</a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcan

                @can('planning.view')
                    <li class="{{ request()->routeIs('planning.*') ? 'mm-active' : '' }}">
                        <a class="has-arrow material-ripple" href="#">
                            <i class="fas fa-project-diagram sidebar-menu-icon" aria-hidden="true"></i>
                            <span>{{ localize('planning_management', 'Planning') }}</span>
                        </a>
                        <ul class="nav-second-level {{ request()->routeIs('planning.*') ? 'mm-show' : '' }}">
                            <li class="{{ request()->routeIs('planning.dashboard*') ? 'mm-active' : '' }}">
                                <a class="dropdown-item" href="{{ route('planning.dashboard') }}"><i class="fas fa-tachometer-alt sidebar-menu-icon" aria-hidden="true"></i>
                                    {{ localize('planning_dashboard', 'Dashboard') }}
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('planning.plans.*') ? 'mm-active' : '' }}">
                                <a class="dropdown-item" href="{{ route('planning.plans.index') }}"><i class="fas fa-clipboard-list sidebar-menu-icon" aria-hidden="true"></i>
                                    {{ localize('planning_plan_list', 'Plan List') }}
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('planning.consolidation.*') ? 'mm-active' : '' }}">
                                <a class="dropdown-item" href="{{ route('planning.consolidation.index') }}"><i class="fas fa-object-group sidebar-menu-icon" aria-hidden="true"></i>
                                    {{ localize('planning_consolidation', 'Consolidation') }}
                                </a>
                            </li>
                            <li class="{{ request()->routeIs('planning.reports.*') ? 'mm-active' : '' }}">
                                <a class="dropdown-item" href="{{ route('planning.reports.index') }}"><i class="fas fa-chart-pie sidebar-menu-icon" aria-hidden="true"></i>
                                    {{ localize('planning_reports', 'Reports') }}
                                </a>
                            </li>
                        </ul>
                    </li>
                @endcan

                @can('read_leave')
                    <li class="{{ request()->routeIs('leave.*') ? 'mm-active' : '' }}">
                        <a class="has-arrow material-ripple" href="#">
                            <i class="fas fa-plane sidebar-menu-icon" aria-hidden="true"></i>
                            <span> {{ localize('leave') }}</span>
                        </a>
                        <ul class="nav-second-level {{ request()->routeIs('leave.*') ? 'mm-show' : '' }}">
                            @can('read_leave')
                                <li class="{{ request()->routeIs('leave.weekleave') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('leave.weekleave') }}"><i class="fas fa-calendar-week sidebar-menu-icon" aria-hidden="true"></i> {{ localize('weekly_holiday') }}</a>
                                </li>
                                <li class="{{ request()->routeIs('holiday.index') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item" href="{{ route('holiday.index') }}"><i class="fas fa-calendar-day sidebar-menu-icon" aria-hidden="true"></i> {{ localize('holiday') }}</a>
                                </li>
                            @endcan
                            @can('read_leave_application')
                                <li class="{{ request()->routeIs('leave.index') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('leave.index') }}"><i class="fas fa-calendar-check sidebar-menu-icon" aria-hidden="true"></i> {{ localize('leave_application  ') }}</a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcan

                @can('read_loan')
                    <li class="{{ request()->is('loan*') ? 'mm-active' : '' }}">
                        <a class="has-arrow material-ripple" href="#">
                            <i class="fas fa-credit-card sidebar-menu-icon" aria-hidden="true"></i>
                            <span> {{ localize('loan') }}</span>
                        </a>
                        <ul class="nav-second-level {{ request()->is('hr.loans.*') ? 'mm-show' : '' }}">
                            <li class="{{ request()->routeIs('hr.loans.*') ? 'mm-active' : '' }}">
                                @if (auth()->user()->hasPermissionTo('read_loan'))
                                    <a class="dropdown-item"
                                        href="{{ route('hr.loans.index') }}"><i class="fas fa-hand-holding-usd sidebar-menu-icon" aria-hidden="true"></i> {{ localize('loan_list') }}</a>
                                @elseif(auth()->user()->hasPermissionTo('read_loan_disburse_report'))
                                    <a class="dropdown-item"
                                        href="{{ route('hr.loans.report') }}"><i class="fas fa-file-invoice-dollar sidebar-menu-icon" aria-hidden="true"></i> {{ localize('loan_report') }}</a>
                                @endif
                            </li>
                        </ul>
                    </li>
                @endcan

                @can('read_notice')
                    <li class="{{ request()->routeIs('notice.*') ? 'mm-active' : '' }}">
                        <a class="has-arrow material-ripple" href="#">
                            <i class="fas fa-bell sidebar-menu-icon" aria-hidden="true"></i>
                            <span> {{ localize('notice_board') }}</span>
                        </a>
                        <ul class="nav-second-level {{ request()->routeIs('notice.*') ? 'mm-show' : '' }}">
                            @can('read_notice')
                                <li class="{{ request()->routeIs('notice.*') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item" href="{{ route('notice.index') }}"><i class="fas fa-bullhorn sidebar-menu-icon" aria-hidden="true"></i> {{ localize('notice') }}</a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcan

                @php
                    $canUseCorrespondenceModule = auth()->user()?->can('read_correspondence_management')
                        || app(\Modules\HumanResource\Support\OrgRolePermissionService::class)->canUserPerform(
                            auth()->user(),
                            'correspondence',
                            'print',
                            null,
                            []
                        )
                        || app(\Modules\HumanResource\Support\OrgRolePermissionService::class)->canUserPerform(
                            auth()->user(),
                            'correspondence',
                            'create_incoming',
                            null,
                            []
                        )
                        || app(\Modules\HumanResource\Support\OrgRolePermissionService::class)->canUserPerform(
                            auth()->user(),
                            'correspondence',
                            'create_outgoing',
                            null,
                            []
                        );
                @endphp
                @if ($canUseCorrespondenceModule)
                    <li class="{{ request()->routeIs('correspondence.*') ? 'mm-active' : '' }}">
                        <a class="has-arrow material-ripple" href="#">
                            <i class="fas fa-envelope sidebar-menu-icon" aria-hidden="true"></i>
                            <span>{{ localize('correspondence_management', 'ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¡ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¸Ã¢â‚¬Â¹ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¡ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¾ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¾Ã‚Â·ÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¾Ã‚Â·ÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã…Â ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¹ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã¢â‚¬Âº') }}</span>
                        </a>
                        <ul class="nav-second-level {{ request()->routeIs('correspondence.*') ? 'mm-show' : '' }}">
                            <li class="{{ request()->routeIs('correspondence.index') ? 'mm-active' : '' }}">
                                <a class="dropdown-item"
                                    href="{{ route('correspondence.index') }}"><i class="fas fa-folder-open sidebar-menu-icon" aria-hidden="true"></i> {{ localize('dashboard', 'ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¢ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬ËœÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¾ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¡ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¸Ã¢â‚¬Â¹ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¡ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¾') }}</a>
                            </li>
                            <li class="{{ request()->routeIs('correspondence.incoming') ? 'mm-active' : '' }}">
                                <a class="dropdown-item"
                                    href="{{ route('correspondence.incoming') }}"><i class="fas fa-inbox sidebar-menu-icon" aria-hidden="true"></i> {{ localize('incoming_letters', 'ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¾Ã‚Â·ÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¾Ã‚Â·ÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¦ÃƒÂ¡Ã…Â¾Ã‚Â¼ÃƒÂ¡Ã…Â¾Ã¢â‚¬Âº') }}</a>
                            </li>
                            <li class="{{ request()->routeIs('correspondence.outgoing') ? 'mm-active' : '' }}">
                                <a class="dropdown-item"
                                    href="{{ route('correspondence.outgoing') }}"><i class="fas fa-paper-plane sidebar-menu-icon" aria-hidden="true"></i> {{ localize('outgoing_letters', 'ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¾Ã‚Â·ÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¾Ã‚Â·ÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¦ÃƒÂ¡Ã…Â¸Ã‚ÂÃƒÂ¡Ã…Â¾Ã¢â‚¬Â°') }}</a>
                            </li>
                        </ul>
                    </li>
                @endif

                @canany(['read_pharmaceutical_management', 'read_pharm_medicines', 'read_pharm_distributions', 'read_pharm_stock', 'read_pharm_dispensings', 'read_pharm_reports', 'read_pharm_users'])
                    <li class="{{ request()->routeIs('pharmaceutical.*') ? 'mm-active' : '' }}">
                        <a class="has-arrow material-ripple" href="#">
                            <i class="fas fa-pills sidebar-menu-icon" aria-hidden="true"></i>
                            <span>{{ localize('pharmaceutical_management', 'ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¡ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¸Ã¢â‚¬Â¹ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¡ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¾ÃƒÂ¡Ã…Â¾Ã‚Â±ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¾Ã‚Â') }}</span>
                        </a>
                        <ul class="nav-second-level {{ request()->routeIs('pharmaceutical.*') ? 'mm-show' : '' }}">
                            <li class="{{ request()->routeIs('pharmaceutical.index') ? 'mm-active' : '' }}">
                                <a class="dropdown-item"
                                    href="{{ route('pharmaceutical.index') }}"><i class="fas fa-clinic-medical sidebar-menu-icon" aria-hidden="true"></i> {{ localize('dashboard', 'ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¢ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬ËœÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¾ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¡ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¸Ã¢â‚¬Â¹ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¡ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¾') }}</a>
                            </li>
                            @canany(['read_pharmaceutical_management', 'read_pharm_medicines'])
                            <li class="{{ request()->routeIs('pharmaceutical.medicines.*') ? 'mm-active' : '' }}">
                                <a class="dropdown-item"
                                    href="{{ route('pharmaceutical.medicines.index') }}"><i class="fas fa-capsules sidebar-menu-icon" aria-hidden="true"></i> {{ localize('medicines', 'ÃƒÂ¡Ã…Â¾Ã‹Å“ÃƒÂ¡Ã…Â¾Ã‚Â»ÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¾Ã‚Â±ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¾Ã‚Â') }}</a>
                            </li>
                            @endcanany
                            @canany(['read_pharmaceutical_management', 'read_pharm_distributions'])
                            <li class="{{ request()->routeIs('pharmaceutical.distributions.*') ? 'mm-active' : '' }}">
                                <a class="dropdown-item"
                                    href="{{ route('pharmaceutical.distributions.index') }}"><i class="fas fa-truck sidebar-menu-icon" aria-hidden="true"></i> {{ localize('distributions', 'ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¦ÃƒÂ¡Ã…Â¸Ã¢â‚¬Å¡ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¦ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã¢â€žÂ¢') }}</a>
                            </li>
                            @endcanany
                            @canany(['read_pharmaceutical_management', 'read_pharm_stock'])
                            <li class="{{ request()->routeIs('pharmaceutical.stock') ? 'mm-active' : '' }}">
                                <a class="dropdown-item"
                                    href="{{ route('pharmaceutical.stock') }}"><i class="fas fa-boxes sidebar-menu-icon" aria-hidden="true"></i> {{ localize('stock', 'ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã‚Â·ÃƒÂ¡Ã…Â¾Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã‚Â·') }}</a>
                            </li>
                            @endcanany
                            @canany(['read_pharmaceutical_management', 'read_pharm_dispensings'])
                            <li class="{{ request()->routeIs('pharmaceutical.dispensings.*') ? 'mm-active' : '' }}">
                                <a class="dropdown-item"
                                    href="{{ route('pharmaceutical.dispensings.index') }}"><i class="fas fa-prescription-bottle-alt sidebar-menu-icon" aria-hidden="true"></i> {{ localize('dispensing', 'ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¢ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¸Ã¢â‚¬Â¹ÃƒÂ¡Ã…Â¾Ã‚Â±ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¾Ã‚Â') }}</a>
                            </li>
                            @endcanany
                            @canany(['read_pharmaceutical_management', 'read_pharm_reports'])
                            <li class="{{ request()->routeIs('pharmaceutical.reports.*') ? 'mm-active' : '' }}">
                                <a class="dropdown-item"
                                    href="{{ route('pharmaceutical.reports.index') }}"><i class="fas fa-chart-bar sidebar-menu-icon" aria-hidden="true"></i> {{ localize('reports', 'ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã¢â€žÂ¢ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã…Â½ÃƒÂ¡Ã…Â¸Ã‚Â') }}</a>
                            </li>
                            @endcanany
                            @canany(['read_pharmaceutical_management', 'read_pharm_users'])
                            @if(auth()->user() && (int) auth()->user()->user_type_id === 1)
                            <li class="{{ request()->routeIs('pharmaceutical.users.*') ? 'mm-active' : '' }}">
                                <a class="dropdown-item"
                                    href="{{ route('pharmaceutical.users.index') }}"><i class="fas fa-user-cog sidebar-menu-icon" aria-hidden="true"></i> {{ localize('user_management', 'ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¡ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¸Ã¢â‚¬Â¹ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¡ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¾ÃƒÂ¡Ã…Â¾Ã‚Â¢ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã‚Â¾') }}</a>
                            </li>
                            @endif
                            @endcanany
                        </ul>
                    </li>
                @endcanany

                @can('read_payroll')
                    <li class="{{ request()->is('payroll/*') ? 'mm-active' : '' }}">
                        <a class="has-arrow material-ripple" href="#">
                            <i class="fas fa-credit-card sidebar-menu-icon" aria-hidden="true"></i>
                            <span> {{ localize('payroll') }}</span>
                        </a>
                        <ul class="nav-second-level {{ request()->is('payroll/*') ? 'mm-show' : '' }}">
                            @can('read_salary_advance')
                                <li class="{{ request()->is('payroll/salary-advance') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('salary-advance.index') }}"><i class="fas fa-hand-holding-usd sidebar-menu-icon" aria-hidden="true"></i> {{ localize('salary_advance') }}</a>
                                </li>
                            @endcan
                            @can('read_salary_generate')
                                <li
                                    class="{{ request()->routeIs('salary.generate-form') || request()->routeIs('salary.approval-form') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('salary.generate-form') }}"><i class="fas fa-file-invoice-dollar sidebar-menu-icon" aria-hidden="true"></i> {{ localize('salary_generate') }}</a>
                                </li>
                            @endcan
                            @can('read_manage_employee_salary')
                                <li
                                    class="{{ request()->routeIs('employee.salary') || request()->routeIs('employee.payslip') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('employee.salary') }}"><i class="fas fa-wallet sidebar-menu-icon" aria-hidden="true"></i> {{ localize('manage_employee_salary') }}</a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcan

                @can('read_procurement')
                    <li class="{{ request()->routeIs('units.*') ? 'mm-active' : '' }}">
                        <a class="has-arrow material-ripple" href="#">
                            <i class="fas fa-shopping-basket sidebar-menu-icon" aria-hidden="true"></i>
                            <span> {{ localize('procurement') }}</span>
                        </a>
                        <ul class="nav-second-level {{ request()->routeIs('procurement_request.*') ? 'mm-show' : '' }}">
                            @can('read_request')
                                <li
                                    class="{{ request()->routeIs('procurement_request.index') || request()->routeIs('procurement_request.create') || request()->routeIs('procurement_request.edit') || request()->routeIs('procurement_request.show') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('procurement_request.index') }}"><i class="fas fa-file-alt sidebar-menu-icon" aria-hidden="true"></i> {{ localize('request') }}</a>
                                </li>
                            @endcan
                            @can('read_quotation')
                                <li
                                    class="{{ request()->routeIs('quotation.index') || request()->routeIs('quotation.create') || request()->routeIs('quotation.edit') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('quotation.index') }}"><i class="fas fa-file-invoice sidebar-menu-icon" aria-hidden="true"></i> {{ localize('quotation') }}</a>
                                </li>
                            @endcan
                            @can('read_bid_analysis')
                                <li
                                    class="{{ request()->routeIs('bid.index') || request()->routeIs('bid.create') || request()->routeIs('bid.edit') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('bid.index') }}"><i class="fas fa-gavel sidebar-menu-icon" aria-hidden="true"></i> {{ localize('bid_analysis') }}</a>
                                </li>
                            @endcan
                            @can('read_purchase_order')
                                <li
                                    class="{{ request()->routeIs('purchase.index') || request()->routeIs('purchase.create') || request()->routeIs('purchase.edit') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('purchase.index') }}"><i class="fas fa-shopping-cart sidebar-menu-icon" aria-hidden="true"></i> {{ localize('purchase_order') }}</a>
                                </li>
                            @endcan
                            @can('read_goods_received')
                                <li
                                    class="{{ request()->routeIs('goods.index') || request()->routeIs('goods.create') || request()->routeIs('goods.show') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('goods.index') }}"><i class="fas fa-box-open sidebar-menu-icon" aria-hidden="true"></i> {{ localize('goods_received') }}</a>
                                </li>
                            @endcan
                            @can('read_vendors')
                                <li class="{{ request()->routeIs('vendor.index') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('vendor.index') }}"><i class="fas fa-store sidebar-menu-icon" aria-hidden="true"></i> {{ localize('vendors') }}</a>
                                </li>
                            @endcan
                            @can('read_committees')
                                <li class="{{ request()->routeIs('committee.index') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('committee.index') }}"><i class="fas fa-users-cog sidebar-menu-icon" aria-hidden="true"></i> {{ localize('committees') }}</a>
                                </li>
                            @endcan
                            @can('read_units')
                                <li class="{{ request()->routeIs('units.index') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item" href="{{ route('units.index') }}"><i class="fas fa-ruler-combined sidebar-menu-icon" aria-hidden="true"></i> {{ localize('units') }}</a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcan

                @can('read_project_management')
                    <li class="{{ request()->routeIs('project*') ? 'mm-active' : '' }}">
                        <a class="has-arrow material-ripple" href="#">
                            <i class="fas fa-tasks sidebar-menu-icon" aria-hidden="true"></i>
                            <span> {{ localize('project_management') }}</span>
                        </a>
                        <ul class="nav-second-level {{ request()->is('project*') ? 'mm-show' : '' }}">
                            @can('read_clients')
                                <li class="{{ request()->is('project/clients') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('project.index') }}"><i class="fas fa-plus-circle sidebar-menu-icon" aria-hidden="true"></i> {{ localize('clients') }}</a>
                                </li>
                            @endcan
                            @can('read_projects')
                                <li class="{{ request()->is('project/projects') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('project.project-lists') }}"><i class="fas fa-folder-open sidebar-menu-icon" aria-hidden="true"></i> {{ localize('projects') }}</a>
                                </li>
                            @endcan
                            @can('read_task')
                                <li class="{{ request()->is('project/manage_tasks') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('project.manage-tasks') }}"><i class="fas fa-tasks sidebar-menu-icon" aria-hidden="true"></i> {{ localize('manage_tasks') }}</a>
                                </li>
                            @endcan
                            @can('read_project_reports')
                                <li class="{{ request()->is('project/reports/*') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('project.reports') }}"><i class="fas fa-chart-line sidebar-menu-icon" aria-hidden="true"></i> {{ localize('reports') }}</a>
                                </li>
                            @endcan
                            @can('read_team_member')
                                <li class="{{ request()->is('project/team_member_search') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('project.team-member-search') }}"><i class="fas fa-users sidebar-menu-icon" aria-hidden="true"></i> {{ localize('team_members') }}</a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcan

                @can('read_recruitment')
                    <li
                        class="{{ request()->routeIs('recruitment.*') || request()->routeIs('shortlist.*') || request()->routeIs('interview.*') ? 'mm-active' : '' }}">
                        <a class="has-arrow material-ripple" href="#">
                            <i class="fas fa-newspaper sidebar-menu-icon" aria-hidden="true"></i>
                            <span> {{ localize('recruitment') }}</span>
                        </a>
                        <ul class="nav-second-level {{ request()->is('recruitment*') ? 'mm-show' : '' }}">
                            @can('read_candidate_list')
                                <li
                                    class="{{ request()->routeIs('candidate.index') || request()->routeIs('candidate.create') || request()->routeIs('candidate.edit') || request()->routeIs('candidate.show') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('candidate.index') }}"><i class="fas fa-user-tie sidebar-menu-icon" aria-hidden="true"></i> {{ localize('candidate_list') }}</a>
                                </li>
                            @endcan
                            @can('read_candidate_shortlist')
                                <li class="{{ request()->routeIs('shortlist.index') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('shortlist.index') }}"><i class="fas fa-list-alt sidebar-menu-icon" aria-hidden="true"></i> {{ localize('candidate_shortlist') }}</a>
                                </li>
                            @endcan
                            @can('read_interview')
                                <li class="{{ request()->routeIs('interview.index') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('interview.index') }}"><i class="fas fa-comments sidebar-menu-icon" aria-hidden="true"></i> {{ localize('interview') }}</a>
                                </li>
                            @endcan
                            @can('read_candidate_selection')
                                <li class="{{ request()->routeIs('selection.index') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('selection.index') }}"><i class="fas fa-user-check sidebar-menu-icon" aria-hidden="true"></i> {{ localize('candidate_selection') }}</a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcan

                @can('read_reports')
                    <li class="{{ request()->is('reports*') ? 'mm-active' : '' }}">
                        <a class="has-arrow material-ripple" href="#">
                            <i class="fas fa-chart-bar sidebar-menu-icon" aria-hidden="true"></i>
                            <span> {{ localize('reports') }}</span>
                        </a>
                        <ul class="nav-second-level {{ request()->routeIs('reports.*') ? 'mm-show' : '' }}">
                            @can('read_reports')
                                @can('read_attendance_report')
                                    <li
                                        class="{{ request()->routeIs('reports.daily-present') ||
                                        request()->routeIs('reports.lateness-closing-attendance') ||
                                        request()->routeIs('reports.attendance-log') ||
                                        request()->routeIs('reports.attendance-log-details') ||
                                        request()->routeIs('reports.monthly') ||
                                        request()->routeIs('reports.staff-attendance') ||
                                        request()->routeIs('reports.attendance-summery') ||
                                        request()->routeIs('reports.attendance-weekly') ||
                                        request()->routeIs('reports.attendance-quarterly') ||
                                        request()->routeIs('reports.attendance-semester') ||
                                        request()->routeIs('reports.attendance-yearly')
                                            ? 'mm-active'
                                            : '' }}">
                                        <a class="dropdown-item"
                                            href="{{ route('reports.daily-present') }}"><i class="fas fa-calendar-check sidebar-menu-icon" aria-hidden="true"></i> {{ localize('attendance_report') }}</a>
                                    </li>
                                @endcan
                                @can('read_leave_report')
                                    <li class="{{ request()->routeIs('reports.leave') ? 'mm-active' : '' }}">
                                        <a class="dropdown-item"
                                            href="{{ route('reports.leave') }}"><i class="fas fa-calendar-minus sidebar-menu-icon" aria-hidden="true"></i> {{ localize('leave_report') }}</a>
                                    </li>
                                @endcan
                                @can('read_employee_report')
                                    <li class="{{ request()->routeIs('reports.employee') ? 'mm-active' : '' }}">
                                        <a class="dropdown-item"
                                            href="{{ route('reports.employee') }}"><i class="fas fa-id-card sidebar-menu-icon" aria-hidden="true"></i> {{ localize('employee_reports') }}</a>
                                    </li>
                                    <li class="{{ request()->routeIs('reports.employee-report-templates.*') ? 'mm-active' : '' }}">
                                        <a class="dropdown-item"
                                            href="{{ route('reports.employee-report-templates.index') }}"><i class="fas fa-file-alt sidebar-menu-icon" aria-hidden="true"></i> {{ localize('employee_report_management', 'Employee report management') }}</a>
                                    </li>
                                @endcan
                                @can('read_payroll_report')
                                    <li
                                        class="{{ request()->routeIs('reports.npf3-soc-sec-tax-report') ||
                                        request()->routeIs('reports.iicf3-contribution') ||
                                        request()->routeIs('reports.social-security-npf-icf') ||
                                        request()->routeIs('reports.gra-ret-5-report') ||
                                        request()->routeIs('reports.sate-income-tax') ||
                                        request()->routeIs('reports.salary-confirmation-form')
                                            ? 'mm-active'
                                            : '' }}">
                                        <a class="dropdown-item"
                                            href="{{ route('reports.npf3-soc-sec-tax-report') }}"><i class="fas fa-file-invoice-dollar sidebar-menu-icon" aria-hidden="true"></i> {{ localize('payroll') }}</a>
                                    </li>
                                @endcan
                                @can('read_adhoc_report')
                                    <li class="{{ request()->routeIs('reports.adhoc-advance') ? 'mm-active' : '' }}">
                                        <a class="dropdown-item"
                                            href="{{ route('reports.adhoc-advance') }}"><i class="fas fa-money-bill-wave sidebar-menu-icon" aria-hidden="true"></i> {{ localize('adhoc_report') }}</a>
                                    </li>
                                @endcan
                            @endcan
                        </ul>
                    </li>
                @endcan

                @can('read_reward_points')
                    <li class="{{ request()->is('reward*') ? 'mm-active' : '' }}">
                        <a class="has-arrow material-ripple" href="#">
                            <i class="fas fa-star sidebar-menu-icon" aria-hidden="true"></i>
                            <span> {{ localize('reward_points') }}</span>
                        </a>
                        <ul class="nav-second-level {{ request()->is('reward*') ? 'mm-show' : '' }}">
                            @can('read_point_settings')
                                <li class="{{ request()->is('reward/point-settings') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('reward.index') }}"><i class="fas fa-star sidebar-menu-icon" aria-hidden="true"></i> {{ localize('point_settings') }}</a>
                                </li>
                            @endcan
                            @can('read_point_categories')
                                <li class="{{ request()->is('reward/point-categories') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('reward.point-categories') }}"><i class="fas fa-tags sidebar-menu-icon" aria-hidden="true"></i> {{ localize('point_categories') }}</a>
                                </li>
                            @endcan
                            @can('read_management_points')
                                <li class="{{ request()->is('reward/management-points') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('reward.management-points') }}"><i class="fas fa-tasks sidebar-menu-icon" aria-hidden="true"></i> {{ localize('management_points') }}</a>
                                </li>
                            @endcan
                            @can('read_collaborative_points')
                                <li class="{{ request()->is('reward/collaborative-points') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('reward.collaborative-points') }}"><i class="fas fa-hands-helping sidebar-menu-icon" aria-hidden="true"></i> {{ localize('collaborative_points') }}</a>
                                </li>
                            @endcan
                            @can('read_attendance_points')
                                <li class="{{ request()->is('reward/attendance-points') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('reward.attendance-points') }}"><i class="fas fa-user-clock sidebar-menu-icon" aria-hidden="true"></i> {{ localize('attendance_points') }}</a>
                                </li>
                            @endcan
                            @can('read_employee_points')
                                <li class="{{ request()->is('reward/employee-points') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('reward.employee-points') }}"><i class="fas fa-medal sidebar-menu-icon" aria-hidden="true"></i> {{ localize('employee_points') }}</a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcan

                @canany(['read_role_list', 'read_user_list'])
                    <li class="{{ request()->is('access-control*') ? 'mm-active' : '' }}">
                        <a href="{{ route('access-control.index') }}">
                            <i class="fas fa-shield-alt sidebar-menu-icon" aria-hidden="true"></i>
                            <span>មជ្ឈមណ្ឌលគ្រប់គ្រងសិទ្ធិ</span>
                        </a>
                    </li>
                @endcanany
                @if(Auth::user()->admin())
                    <li
                        class="{{ request()->is('setting*') || request()->is('role*') || request()->is('applications*') || request()->is('currencies*') || request()->is('mails*') || request()->is('sms*') || request()->is('password*') || request()->is('user*') || request()->is('localize*') || request()->is('database-backup-reset*') ? 'mm-active' : '' }}">
                        @can('read_application')
                            <a href="{{ route('applications.application') }}">
                                <i class="fas fa-cog sidebar-menu-icon" aria-hidden="true"></i>
                                <span>{{ localize('settings') }}</span>
                            </a>
                        @endcan
                    </li>
                @endif
                @can('read_messages')
                    <li class="{{ request()->is('message*') ? 'mm-active' : '' }}">
                        <a class="has-arrow material-ripple" href="#">
                            <i class="fas fa-comments sidebar-menu-icon" aria-hidden="true"></i>
                            <span> {{ localize('message') }}</span>
                        </a>
                        <ul class="nav-second-level {{ request()->is('message*') ? 'mm-show' : '' }}">
                            @can('create_messages')
                                <li class="{{ request()->is('message/new') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item" href="{{ route('message.index') }}"><i class="fas fa-edit sidebar-menu-icon" aria-hidden="true"></i> {{ localize('new') }}</a>
                                </li>
                            @endcan
                            <li class="{{ request()->is('message/inbox') ? 'mm-active' : '' }}">
                                <a class="dropdown-item"
                                    href="{{ route('message.inbox') }}"><i class="fas fa-inbox sidebar-menu-icon" aria-hidden="true"></i> {{ localize('inbox') }}</a>
                            </li>
                            <li class="{{ request()->is('message/sent') ? 'mm-active' : '' }}">
                                <a class="dropdown-item" href="{{ route('message.sent') }}"><i class="fas fa-paper-plane sidebar-menu-icon" aria-hidden="true"></i> {{ localize('sent') }}</a>
                            </li>
                        </ul>
                    </li>
                @endcan
            </ul>
        </nav>
    </div>
    <!-- sidebar-body -->
</nav>
