<div class="row dashboard_heading mb-3">
    <div class="col-12">
        <div class="card fixed-tab">
            <ul class="nav nav-tabs">
                @can('read_department')
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('departments.*') ? 'active' : '' }}"
                            href="{{ route('departments.index') }}">
                            {{ localize('org_unit_management', 'គ្រប់គ្រងអង្គភាព') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('org-unit-types.*') ? 'active' : '' }}"
                            href="{{ route('org-unit-types.index') }}">
                            {{ localize('org_unit_type', 'ប្រភេទអង្គភាព') }}
                        </a>
                    </li>
                @endcan

                @can('read_setup_rules')
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('professional-skills.*') ? 'active' : '' }}"
                            href="{{ route('professional-skills.index') }}">
                            {{ localize('professional_skill_management', 'គ្រប់គ្រងជំនាញវិជ្ជាជីវៈ') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('employee-statuses.*') ? 'active' : '' }}"
                            href="{{ route('employee-statuses.index') }}">
                            {{ localize('employee_statuses', 'ស្ថានភាពបុគ្គលិក') }}
                        </a>
                    </li>
                @endcan

                @can('read_positions')
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('positions.*') ? 'active' : '' }}"
                            href="{{ route('positions.index') }}">
                            {{ localize('positions', 'មុខតំណែង') }}
                        </a>
                    </li>
                @endcan

                @can('read_setup_rules')
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('pay-levels.*') ? 'active' : '' }}"
                            href="{{ route('pay-levels.index') }}">
                            {{ localize('pay_level_management', 'គ្រប់គ្រងកម្រិតប្រាក់ខែ') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('salary-scales.*') ? 'active' : '' }}"
                            href="{{ route('salary-scales.index') }}">
                            {{ localize('salary_scale_management', 'គ្រប់គ្រងតារាងបៀវត្ស') }}
                        </a>
                    </li>
                @endcan
            </ul>
        </div>
    </div>
</div>
