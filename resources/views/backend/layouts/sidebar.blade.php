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
        <nav class="sidebar-nav">
            <ul class="metismenu">
                @can('read_dashboard')
                    <li class="{{ request()->is('dashboard') ? 'mm-active' : '' }}">
                        <a href="{{ route('home') }}">
                            <i class="fa fa-home"></i>
                            <span>{{ localize('dashboard') }}</span>
                        </a>
                    </li>
                @endcan

                @can('read_attendance')
                    <li class="{{ request()->routeIs('attendances.*') || request()->routeIs('mobile-devices.*') ? 'mm-active' : '' }}">
                        <a class="has-arrow material-ripple" href="#">
                            <i class="fa fa-user"></i>
                            <span> {{ localize('attendance') }}</span>
                        </a>
                        <ul class="nav-second-level {{ request()->routeIs('attendances.*') || request()->routeIs('mobile-devices.*') ? 'mm-show' : '' }}">
                            @can('read_attendance')
                                <li class="{{ request()->routeIs('attendances.workflow') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('attendances.workflow') }}">{{ localize('attendance_workflow', 'Workflow វត្តមាន') }}</a>
                                </li>
                                @can('create_attendance')
                                    <li class="{{ request()->routeIs('attendances.create') ? 'mm-active' : '' }}">
                                        <a class="dropdown-item"
                                            href="{{ route('attendances.create') }}">{{ localize('attendance_form') }}</a>
                                    </li>
                                @endcan
                                @can('create_monthly_attendance')
                                    <li class="{{ request()->routeIs('attendances.monthlyCreate') ? 'mm-active' : '' }}">
                                        <a class="dropdown-item"
                                            href="{{ route('attendances.monthlyCreate') }}">{{ localize('monthly_attendance') }}</a>
                                    </li>
                                @endcan
                                @can('read_missing_attendance')
                                    <li class="{{ request()->routeIs('attendances.missingAttendance') ? 'mm-active' : '' }}">
                                        <a class="dropdown-item"
                                            href="{{ route('attendances.missingAttendance') }}">{{ localize('missing_attendance') }}</a>
                                    </li>
                                @endcan
                                <li class="{{ request()->routeIs('mobile-devices.*') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('mobile-devices.index') }}">{{ localize('mobile_device_management', 'គ្រប់គ្រងទូរសព្ទមន្រ្តី') }}</a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcan

                @can('read_award')
                    <li class="{{ request()->routeIs('award.*') ? 'mm-active' : '' }}">
                        <a class="has-arrow material-ripple" href="#">
                            <i class="fa fa-trophy"></i>
                            <span> {{ localize('award') }}</span>
                        </a>
                        <ul class="nav-second-level {{ request()->routeIs('award.*') ? 'mm-show' : '' }}">
                            <li class="{{ request()->routeIs('award.*') ? 'mm-active' : '' }}">
                                <a class="dropdown-item"
                                    href="{{ route('award.index') }}">{{ localize('award_list') }}</a>
                            </li>
                        </ul>
                    </li>
                @endcan

                @if (auth()->user()->can('read_department') || auth()->user()->can('read_positions') || auth()->user()->can('read_setup_rules'))
                    @php
                        $masterDataHrActive = request()->routeIs('departments.*')
                            || request()->routeIs('professional-skills.*')
                            || request()->routeIs('positions.*')
                            || request()->routeIs('pay-levels.*')
                            || request()->routeIs('salary-scales.*')
                            || request()->routeIs('org-unit-type-positions.*')
                            || request()->routeIs('user-org-roles.*')
                            || request()->routeIs('workflow-policies.*')
                            || request()->routeIs('org-role-module-permissions.*')
                            || request()->routeIs('system-roles.*');
                    @endphp
                    <li class="{{ $masterDataHrActive ? 'mm-active' : '' }}">
                        <a class="has-arrow material-ripple" href="#">
                            <i class="fa fa-sitemap"></i>
                            <span>{{ localize('master_data_hr', 'ទិន្នន័យមូលដ្ឋាន HR') }}</span>
                        </a>
                        <ul class="nav-second-level {{ $masterDataHrActive ? 'mm-show' : '' }}">
                            @can('read_department')
                                <li class="{{ request()->routeIs('departments.*') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item" href="{{ route('departments.index') }}">
                                        {{ localize('org_unit_management', 'គ្រប់គ្រងអង្គភាព') }}
                                    </a>
                                </li>
                                @php
                                    $orgStructureActive = request()->routeIs('org-unit-type-positions.*')
                                        || request()->routeIs('user-org-roles.*')
                                        || request()->routeIs('workflow-policies.*')
                                        || request()->routeIs('org-role-module-permissions.*')
                                        || request()->routeIs('system-roles.*');
                                @endphp
                                <li class="{{ $orgStructureActive ? 'mm-active' : '' }}">
                                    <a class="dropdown-item" href="{{ route('org-unit-type-positions.index') }}">
                                        {{ localize('org_structure_management', 'Org Structure Management') }}
                                    </a>
                                </li>
                            @endcan
                            @can('read_setup_rules')
                                <li class="{{ request()->routeIs('professional-skills.*') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item" href="{{ route('professional-skills.index') }}">
                                        {{ localize('professional_skill_management', 'គ្រប់គ្រងជំនាញ') }}
                                    </a>
                                </li>
                            @endcan
                            @can('read_positions')
                                <li class="{{ request()->routeIs('positions.*') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item" href="{{ route('positions.index') }}">
                                        {{ localize('positions', 'តួនាទី / មុខតំណែង') }}
                                    </a>
                                </li>
                            @endcan
                            @can('read_setup_rules')
                                <li class="{{ request()->routeIs('pay-levels.*') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item" href="{{ route('pay-levels.index') }}">
                                        {{ localize('pay_level_management', 'គ្រប់គ្រងកាំប្រាក់') }}
                                    </a>
                                </li>
                                <li class="{{ request()->routeIs('salary-scales.*') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item" href="{{ route('salary-scales.index') }}">
                                        {{ localize('salary_scale_management', 'កំណត់សន្ទស្សន៍ប្រាក់បៀវត្ស') }}
                                    </a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endif

                @can('read_employee')
                    <li class="{{ request()->routeIs('employees*') || request()->routeIs('employee-pay-promotions.*') || request()->routeIs('employee-workplace-transfers.*') || request()->routeIs('employee-retirements.*') ? 'mm-active' : '' }}">
                        <a class="has-arrow material-ripple" href="#">
                            <i class="fa fa-users"></i>
                            <span> {{ localize('employee') }}</span>
                        </a>
                        <ul class="nav-second-level {{ request()->routeIs('employees*') || request()->routeIs('employee-pay-promotions.*') || request()->routeIs('employee-workplace-transfers.*') || request()->routeIs('employee-retirements.*') ? 'mm-show' : '' }}">
                            @can('read_employee')
                                <li class="{{ request()->routeIs('employees.*') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('employees.index') }}">{{ localize('employee') }}</a>
                                </li>
                            @endcan
                            @can('update_employee')
                                <li class="{{ request()->routeIs('employee-pay-promotions.*') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item" href="{{ route('employee-pay-promotions.index') }}">
                                        {{ localize('grade_and_rank_management', 'គ្រប់គ្រងថ្នាក់ និងឋានន្តរស័ក្តិ') }}
                                    </a>
                                </li>
                                <li class="{{ request()->routeIs('employee-workplace-transfers.*') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item" href="{{ route('employee-workplace-transfers.index') }}">
                                        {{ localize('manage_workplace_transfers') }}
                                    </a>
                                </li>
                                <li class="{{ request()->routeIs('employee-retirements.*') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item" href="{{ route('employee-retirements.index') }}">
                                        {{ localize('retirement_management') }}
                                    </a>
                                </li>
                            @endcan
                            @can('read_employee_performance')
                                <li class="{{ request()->routeIs('employee-performances.*') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('employee-performances.index') }}">{{ localize('employee_performance ') }}</a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcan

                @can('read_leave')
                    <li class="{{ request()->routeIs('leave.*') ? 'mm-active' : '' }}">
                        <a class="has-arrow material-ripple" href="#">
                            <i class="fa fa-plane"></i>
                            <span> {{ localize('leave') }}</span>
                        </a>
                        <ul class="nav-second-level {{ request()->routeIs('leave.*') ? 'mm-show' : '' }}">
                            @can('read_leave')
                                <li class="{{ request()->routeIs('leave.weekleave') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('leave.weekleave') }}">{{ localize('weekly_holiday') }}</a>
                                </li>
                                <li class="{{ request()->routeIs('holiday.index') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item" href="{{ route('holiday.index') }}">{{ localize('holiday') }}</a>
                                </li>
                            @endcan
                            @can('read_leave_application')
                                <li class="{{ request()->routeIs('leave.index') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('leave.index') }}">{{ localize('leave_application  ') }}</a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcan

                @can('read_loan')
                    <li class="{{ request()->is('loan*') ? 'mm-active' : '' }}">
                        <a class="has-arrow material-ripple" href="#">
                            <i class="fa fa-credit-card"></i>
                            <span> {{ localize('loan') }}</span>
                        </a>
                        <ul class="nav-second-level {{ request()->is('hr.loans.*') ? 'mm-show' : '' }}">
                            <li class="{{ request()->routeIs('hr.loans.*') ? 'mm-active' : '' }}">
                                @if (auth()->user()->hasPermissionTo('read_loan'))
                                    <a class="dropdown-item"
                                        href="{{ route('hr.loans.index') }}">{{ localize('loan_list') }}</a>
                                @elseif(auth()->user()->hasPermissionTo('read_loan_disburse_report'))
                                    <a class="dropdown-item"
                                        href="{{ route('hr.loans.report') }}">{{ localize('loan_report') }}</a>
                                @endif
                            </li>
                        </ul>
                    </li>
                @endcan

                @can('read_notice')
                    <li class="{{ request()->routeIs('notice.*') ? 'mm-active' : '' }}">
                        <a class="has-arrow material-ripple" href="#">
                            <i class="fa fa-bell"></i>
                            <span> {{ localize('notice_board') }}</span>
                        </a>
                        <ul class="nav-second-level {{ request()->routeIs('notice.*') ? 'mm-show' : '' }}">
                            @can('read_notice')
                                <li class="{{ request()->routeIs('notice.*') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item" href="{{ route('notice.index') }}">{{ localize('notice') }}</a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcan

                @canany(['read_correspondence_management'])
                    <li class="{{ request()->routeIs('correspondence.*') ? 'mm-active' : '' }}">
                        <a class="has-arrow material-ripple" href="#">
                            <i class="fa fa-envelope"></i>
                            <span>{{ localize('correspondence_management', 'ការគ្រប់គ្រងលិខិតរដ្ឋបាល') }}</span>
                        </a>
                        <ul class="nav-second-level {{ request()->routeIs('correspondence.*') ? 'mm-show' : '' }}">
                            <li class="{{ request()->routeIs('correspondence.index') ? 'mm-active' : '' }}">
                                <a class="dropdown-item"
                                    href="{{ route('correspondence.index') }}">{{ localize('dashboard', 'ផ្ទាំងគ្រប់គ្រង') }}</a>
                            </li>
                            <li class="{{ request()->routeIs('correspondence.incoming') ? 'mm-active' : '' }}">
                                <a class="dropdown-item"
                                    href="{{ route('correspondence.incoming') }}">{{ localize('incoming_letters', 'លិខិតចូល') }}</a>
                            </li>
                            <li class="{{ request()->routeIs('correspondence.outgoing') ? 'mm-active' : '' }}">
                                <a class="dropdown-item"
                                    href="{{ route('correspondence.outgoing') }}">{{ localize('outgoing_letters', 'លិខិតចេញ') }}</a>
                            </li>
                        </ul>
                    </li>
                @endcanany

                @canany(['read_pharmaceutical_management', 'read_pharm_medicines', 'read_pharm_distributions', 'read_pharm_stock', 'read_pharm_dispensings', 'read_pharm_reports', 'read_pharm_users'])
                    <li class="{{ request()->routeIs('pharmaceutical.*') ? 'mm-active' : '' }}">
                        <a class="has-arrow material-ripple" href="#">
                            <i class="fa fa-pills"></i>
                            <span>{{ localize('pharmaceutical_management', 'ការគ្រប់គ្រងឱសថ') }}</span>
                        </a>
                        <ul class="nav-second-level {{ request()->routeIs('pharmaceutical.*') ? 'mm-show' : '' }}">
                            <li class="{{ request()->routeIs('pharmaceutical.index') ? 'mm-active' : '' }}">
                                <a class="dropdown-item"
                                    href="{{ route('pharmaceutical.index') }}">{{ localize('dashboard', 'ផ្ទាំងគ្រប់គ្រង') }}</a>
                            </li>
                            @canany(['read_pharmaceutical_management', 'read_pharm_medicines'])
                            <li class="{{ request()->routeIs('pharmaceutical.medicines.*') ? 'mm-active' : '' }}">
                                <a class="dropdown-item"
                                    href="{{ route('pharmaceutical.medicines.index') }}">{{ localize('medicines', 'មុខឱសថ') }}</a>
                            </li>
                            @endcanany
                            @canany(['read_pharmaceutical_management', 'read_pharm_distributions'])
                            <li class="{{ request()->routeIs('pharmaceutical.distributions.*') ? 'mm-active' : '' }}">
                                <a class="dropdown-item"
                                    href="{{ route('pharmaceutical.distributions.index') }}">{{ localize('distributions', 'ការចែកចាយ') }}</a>
                            </li>
                            @endcanany
                            @canany(['read_pharmaceutical_management', 'read_pharm_stock'])
                            <li class="{{ request()->routeIs('pharmaceutical.stock') ? 'mm-active' : '' }}">
                                <a class="dropdown-item"
                                    href="{{ route('pharmaceutical.stock') }}">{{ localize('stock', 'សន្និធិ') }}</a>
                            </li>
                            @endcanany
                            @canany(['read_pharmaceutical_management', 'read_pharm_dispensings'])
                            <li class="{{ request()->routeIs('pharmaceutical.dispensings.*') ? 'mm-active' : '' }}">
                                <a class="dropdown-item"
                                    href="{{ route('pharmaceutical.dispensings.index') }}">{{ localize('dispensing', 'ការផ្តល់ឱសថ') }}</a>
                            </li>
                            @endcanany
                            @canany(['read_pharmaceutical_management', 'read_pharm_reports'])
                            <li class="{{ request()->routeIs('pharmaceutical.reports.*') ? 'mm-active' : '' }}">
                                <a class="dropdown-item"
                                    href="{{ route('pharmaceutical.reports.index') }}">{{ localize('reports', 'របាយការណ៍') }}</a>
                            </li>
                            @endcanany
                            @canany(['read_pharmaceutical_management', 'read_pharm_users'])
                            @if(auth()->user() && (int) auth()->user()->user_type_id === 1)
                            <li class="{{ request()->routeIs('pharmaceutical.users.*') ? 'mm-active' : '' }}">
                                <a class="dropdown-item"
                                    href="{{ route('pharmaceutical.users.index') }}">{{ localize('user_management', 'គ្រប់គ្រងអ្នកប្រើ') }}</a>
                            </li>
                            @endif
                            @endcanany
                        </ul>
                    </li>
                @endcanany

                @can('read_payroll')
                    <li class="{{ request()->is('payroll/*') ? 'mm-active' : '' }}">
                        <a class="has-arrow material-ripple" href="#">
                            <i class="fa fa-credit-card"></i>
                            <span> {{ localize('payroll') }}</span>
                        </a>
                        <ul class="nav-second-level {{ request()->is('payroll/*') ? 'mm-show' : '' }}">
                            @can('read_salary_advance')
                                <li class="{{ request()->is('payroll/salary-advance') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('salary-advance.index') }}">{{ localize('salary_advance') }}</a>
                                </li>
                            @endcan
                            @can('read_salary_generate')
                                <li
                                    class="{{ request()->routeIs('salary.generate-form') || request()->routeIs('salary.approval-form') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('salary.generate-form') }}">{{ localize('salary_generate') }}</a>
                                </li>
                            @endcan
                            @can('read_manage_employee_salary')
                                <li
                                    class="{{ request()->routeIs('employee.salary') || request()->routeIs('employee.payslip') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('employee.salary') }}">{{ localize('manage_employee_salary') }}</a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcan

                @can('read_procurement')
                    <li class="{{ request()->routeIs('units.*') ? 'mm-active' : '' }}">
                        <a class="has-arrow material-ripple" href="#">
                            <i class="fa fa-industry"></i>
                            <span> {{ localize('procurement') }}</span>
                        </a>
                        <ul class="nav-second-level {{ request()->routeIs('procurement_request.*') ? 'mm-show' : '' }}">
                            @can('read_request')
                                <li
                                    class="{{ request()->routeIs('procurement_request.index') || request()->routeIs('procurement_request.create') || request()->routeIs('procurement_request.edit') || request()->routeIs('procurement_request.show') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('procurement_request.index') }}">{{ localize('request') }}</a>
                                </li>
                            @endcan
                            @can('read_quotation')
                                <li
                                    class="{{ request()->routeIs('quotation.index') || request()->routeIs('quotation.create') || request()->routeIs('quotation.edit') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('quotation.index') }}">{{ localize('quotation') }}</a>
                                </li>
                            @endcan
                            @can('read_bid_analysis')
                                <li
                                    class="{{ request()->routeIs('bid.index') || request()->routeIs('bid.create') || request()->routeIs('bid.edit') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('bid.index') }}">{{ localize('bid_analysis') }}</a>
                                </li>
                            @endcan
                            @can('read_purchase_order')
                                <li
                                    class="{{ request()->routeIs('purchase.index') || request()->routeIs('purchase.create') || request()->routeIs('purchase.edit') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('purchase.index') }}">{{ localize('purchase_order') }}</a>
                                </li>
                            @endcan
                            @can('read_goods_received')
                                <li
                                    class="{{ request()->routeIs('goods.index') || request()->routeIs('goods.create') || request()->routeIs('goods.show') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('goods.index') }}">{{ localize('goods_received') }}</a>
                                </li>
                            @endcan
                            @can('read_vendors')
                                <li class="{{ request()->routeIs('vendor.index') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('vendor.index') }}">{{ localize('vendors') }}</a>
                                </li>
                            @endcan
                            @can('read_committees')
                                <li class="{{ request()->routeIs('committee.index') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('committee.index') }}">{{ localize('committees') }}</a>
                                </li>
                            @endcan
                            @can('read_units')
                                <li class="{{ request()->routeIs('units.index') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item" href="{{ route('units.index') }}">{{ localize('units') }}</a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcan

                @can('read_project_management')
                    <li class="{{ request()->routeIs('project*') ? 'mm-active' : '' }}">
                        <a class="has-arrow material-ripple" href="#">
                            <i class="fa fa-tasks"></i>
                            <span> {{ localize('project_management') }}</span>
                        </a>
                        <ul class="nav-second-level {{ request()->is('project*') ? 'mm-show' : '' }}">
                            @can('read_clients')
                                <li class="{{ request()->is('project/clients') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('project.index') }}">{{ localize('clients') }}</a>
                                </li>
                            @endcan
                            @can('read_projects')
                                <li class="{{ request()->is('project/projects') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('project.project-lists') }}">{{ localize('projects') }}</a>
                                </li>
                            @endcan
                            @can('read_task')
                                <li class="{{ request()->is('project/manage_tasks') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('project.manage-tasks') }}">{{ localize('manage_tasks') }}</a>
                                </li>
                            @endcan
                            @can('read_project_reports')
                                <li class="{{ request()->is('project/reports/*') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('project.reports') }}">{{ localize('reports') }}</a>
                                </li>
                            @endcan
                            @can('read_team_member')
                                <li class="{{ request()->is('project/team_member_search') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('project.team-member-search') }}">{{ localize('team_members') }}</a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcan

                @can('read_recruitment')
                    <li
                        class="{{ request()->routeIs('recruitment.*') || request()->routeIs('shortlist.*') || request()->routeIs('interview.*') ? 'mm-active' : '' }}">
                        <a class="has-arrow material-ripple" href="#">
                            <i class="fa fa-newspaper"></i>
                            <span> {{ localize('recruitment') }}</span>
                        </a>
                        <ul class="nav-second-level {{ request()->is('recruitment*') ? 'mm-show' : '' }}">
                            @can('read_candidate_list')
                                <li
                                    class="{{ request()->routeIs('candidate.index') || request()->routeIs('candidate.create') || request()->routeIs('candidate.edit') || request()->routeIs('candidate.show') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('candidate.index') }}">{{ localize('candidate_list') }}</a>
                                </li>
                            @endcan
                            @can('read_candidate_shortlist')
                                <li class="{{ request()->routeIs('shortlist.index') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('shortlist.index') }}">{{ localize('candidate_shortlist') }}</a>
                                </li>
                            @endcan
                            @can('read_interview')
                                <li class="{{ request()->routeIs('interview.index') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('interview.index') }}">{{ localize('interview') }}</a>
                                </li>
                            @endcan
                            @can('read_candidate_selection')
                                <li class="{{ request()->routeIs('selection.index') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('selection.index') }}">{{ localize('candidate_selection') }}</a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcan

                @can('read_reports')
                    <li class="{{ request()->is('reports*') ? 'mm-active' : '' }}">
                        <a class="has-arrow material-ripple" href="#">
                            <i class="fa fa-industry"></i>
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
                                        request()->routeIs('reports.staff-attendance')
                                            ? 'mm-active'
                                            : '' }}">
                                        <a class="dropdown-item"
                                            href="{{ route('reports.daily-present') }}">{{ localize('attendance_report') }}</a>
                                    </li>
                                @endcan
                                @can('read_leave_report')
                                    <li class="{{ request()->routeIs('reports.leave') ? 'mm-active' : '' }}">
                                        <a class="dropdown-item"
                                            href="{{ route('reports.leave') }}">{{ localize('leave_report') }}</a>
                                    </li>
                                @endcan
                                @can('read_employee_report')
                                    <li class="{{ request()->routeIs('reports.employee') ? 'mm-active' : '' }}">
                                        <a class="dropdown-item"
                                            href="{{ route('reports.employee') }}">{{ localize('employee_reports') }}</a>
                                    </li>
                                    <li class="{{ request()->routeIs('reports.employee-report-templates.*') ? 'mm-active' : '' }}">
                                        <a class="dropdown-item"
                                            href="{{ route('reports.employee-report-templates.index') }}">{{ localize('employee_report_management', 'Employee report management') }}</a>
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
                                            href="{{ route('reports.npf3-soc-sec-tax-report') }}">{{ localize('payroll') }}</a>
                                    </li>
                                @endcan
                                @can('read_adhoc_report')
                                    <li class="{{ request()->routeIs('reports.adhoc-advance') ? 'mm-active' : '' }}">
                                        <a class="dropdown-item"
                                            href="{{ route('reports.adhoc-advance') }}">{{ localize('adhoc_report') }}</a>
                                    </li>
                                @endcan
                            @endcan
                        </ul>
                    </li>
                @endcan

                @can('read_reward_points')
                    <li class="{{ request()->is('reward*') ? 'mm-active' : '' }}">
                        <a class="has-arrow material-ripple" href="#">
                            <i class="fa fa-star"></i>
                            <span> {{ localize('reward_points') }}</span>
                        </a>
                        <ul class="nav-second-level {{ request()->is('reward*') ? 'mm-show' : '' }}">
                            @can('read_point_settings')
                                <li class="{{ request()->is('reward/point-settings') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('reward.index') }}">{{ localize('point_settings') }}</a>
                                </li>
                            @endcan
                            @can('read_point_categories')
                                <li class="{{ request()->is('reward/point-categories') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('reward.point-categories') }}">{{ localize('point_categories') }}</a>
                                </li>
                            @endcan
                            @can('read_management_points')
                                <li class="{{ request()->is('reward/management-points') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('reward.management-points') }}">{{ localize('management_points') }}</a>
                                </li>
                            @endcan
                            @can('read_collaborative_points')
                                <li class="{{ request()->is('reward/collaborative-points') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('reward.collaborative-points') }}">{{ localize('collaborative_points') }}</a>
                                </li>
                            @endcan
                            @can('read_attendance_points')
                                <li class="{{ request()->is('reward/attendance-points') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('reward.attendance-points') }}">{{ localize('attendance_points') }}</a>
                                </li>
                            @endcan
                            @can('read_employee_points')
                                <li class="{{ request()->is('reward/employee-points') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item"
                                        href="{{ route('reward.employee-points') }}">{{ localize('employee_points') }}</a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcan

                @can('read_setting')
                    <li
                        class="{{ request()->is('setting*') || request()->is('role*') || request()->is('applications*') || request()->is('currencies*') || request()->is('mails*') || request()->is('sms*') || request()->is('password*') || request()->is('user*') || request()->is('localize*') || request()->is('database-backup-reset*') ? 'mm-active' : '' }}">
                        @can('read_application')
                            <a href="{{ route('applications.application') }}">
                                <i class="fa fa-gear"></i>
                                <span>{{ localize('settings') }}</span>
                            </a>
                        @endcan
                    </li>
                @endcan
                @can('read_messages')
                    <li class="{{ request()->is('message*') ? 'mm-active' : '' }}">
                        <a class="has-arrow material-ripple" href="#">
                            <i class="fa fa-message"></i>
                            <span> {{ localize('message') }}</span>
                        </a>
                        <ul class="nav-second-level {{ request()->is('message*') ? 'mm-show' : '' }}">
                            @can('create_messages')
                                <li class="{{ request()->is('message/new') ? 'mm-active' : '' }}">
                                    <a class="dropdown-item" href="{{ route('message.index') }}">{{ localize('new') }}</a>
                                </li>
                            @endcan
                            <li class="{{ request()->is('message/inbox') ? 'mm-active' : '' }}">
                                <a class="dropdown-item"
                                    href="{{ route('message.inbox') }}">{{ localize('inbox') }}</a>
                            </li>
                            <li class="{{ request()->is('message/sent') ? 'mm-active' : '' }}">
                                <a class="dropdown-item" href="{{ route('message.sent') }}">{{ localize('sent') }}</a>
                            </li>
                        </ul>
                    </li>
                @endcan
            </ul>
        </nav>
    </div>
    <!-- sidebar-body -->
</nav>
