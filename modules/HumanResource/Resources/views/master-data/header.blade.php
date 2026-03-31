<div class="row dashboard_heading mb-3">
    <div class="card fixed-tab col-12 col-md-12">
        <ul class="nav nav-tabs">
            @can('read_department')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('departments.*') ? 'active' : '' }}"
                        href="{{ route('departments.index') }}">
                        {{ localize('org_unit_management', 'Org Unit Management') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('org-unit-types.*') ? 'active' : '' }}"
                        href="{{ route('org-unit-types.index') }}">
                        {{ localize('org_unit_type', 'Org Unit Type') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('org-unit-type-positions.*') ? 'active' : '' }}"
                        href="{{ route('org-unit-type-positions.index') }}">
                        {{ localize('org_position_matrix', 'Org Position Matrix') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('user-org-roles.*') ? 'active' : '' }}"
                        href="{{ route('user-org-roles.index') }}">
                        {{ localize('org_role_management', 'គ្រប់គ្រងតួនាទីតាមអង្គភាព') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('workflow-policies.*') ? 'active' : '' }}"
                        href="{{ route('workflow-policies.index') }}">
                        {{ localize('workflow_policy_matrix', 'Workflow Policy Matrix') }}
                    </a>
                </li>
            @endcan

            @can('read_setup_rules')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('professional-skills.*') ? 'active' : '' }}"
                        href="{{ route('professional-skills.index') }}">
                        {{ localize('professional_skill_management', 'Professional Skill Management') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('employee-statuses.*') ? 'active' : '' }}"
                        href="{{ route('employee-statuses.index') }}">
                        {{ localize('employee_statuses', 'Employee Statuses') }}
                    </a>
                </li>
            @endcan

            @can('read_positions')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('positions.*') ? 'active' : '' }}"
                        href="{{ route('positions.index') }}">
                        {{ localize('positions', 'Positions') }}
                    </a>
                </li>
            @endcan

            @can('read_setup_rules')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('pay-levels.*') ? 'active' : '' }}"
                        href="{{ route('pay-levels.index') }}">
                        {{ localize('pay_level_management', 'Pay Level Management') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('salary-scales.*') ? 'active' : '' }}"
                        href="{{ route('salary-scales.index') }}">
                        {{ localize('salary_scale_management', 'Salary Scale Management') }}
                    </a>
                </li>
            @endcan
        </ul>
    </div>
</div>
