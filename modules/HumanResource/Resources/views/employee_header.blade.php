<div class="row  dashboard_heading mb-3">
    <div class="card fixed-tab col-12 col-md-12">
        <ul class="nav nav-tabs">
            @can('read_employee')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('employees.index') || request()->routeIs('employees.edit') || request()->routeIs('employees.create') || request()->routeIs('employees.show') || request()->routeIs('employees.career_management.edit') ? 'active' : '' }}"
                        href="{{ route('employees.index') }}">{{ localize('employee') }}</a>
                </li>
            @endcan

            @can('update_employee')
                <li class="nav-item">
                    <a class="nav-link mt-0 {{ request()->routeIs('employee-position-promotions.*') ? 'active' : '' }}"
                        href="{{ route('employee-position-promotions.index') }}">{{ localize('manage_position_promotions') }}</a>
                </li>
            @endcan

            @can('update_employee')
                <li class="nav-item">
                    <a class="nav-link mt-0 {{ request()->routeIs('employee-pay-promotions.*') ? 'active' : '' }}"
                        href="{{ route('employee-pay-promotions.index') }}">{{ app()->getLocale() === 'en' ? 'Grade and rank management' : 'គ្រប់គ្រងថ្នាក់ និងឋានន្តរស័ក្តិ' }}</a>
                </li>
            @endcan

            @can('update_employee')
                <li class="nav-item">
                    <a class="nav-link mt-0 {{ request()->routeIs('employee-workplace-transfers.*') ? 'active' : '' }}"
                        href="{{ route('employee-workplace-transfers.index') }}">{{ localize('manage_workplace_transfers') }}</a>
                </li>
            @endcan

            @can('update_employee')
                <li class="nav-item">
                    <a class="nav-link mt-0 {{ request()->routeIs('employee-retirements.*') ? 'active' : '' }}"
                        href="{{ route('employee-retirements.index') }}">{{ localize('retirement_management') }}</a>
                </li>
            @endcan

            @can('read_inactive_employees_list')
                <li class="nav-item">
                    <a class="nav-link mt-0 {{ request()->routeIs('employees.inactive_list') ? 'active' : '' }}"
                        href="{{ route('employees.inactive_list') }}">{{ localize('inactive_employees_list') }}</a>
                </li>
            @endcan
        </ul>
    </div>
</div>
