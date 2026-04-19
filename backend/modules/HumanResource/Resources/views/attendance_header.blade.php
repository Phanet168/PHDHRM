<div class="row  dashboard_heading mb-3">
    <div class="card fixed-tab col-12 col-md-12">
        <ul class="nav nav-tabs">
            @can('read_attendance')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('attendances.workflow') ? 'active' : '' }}"
                        href="{{ route('attendances.workflow') }}">{{ localize('attendance_workflow', 'Workflow') }}</a>
                </li>
            @endcan
            @can('read_attendance')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('attendances.create') ? 'active' : '' }}"
                        href="{{ route('attendances.create') }}">{{ localize('attendance_form') }}</a>
                </li>
            @endcan
            @can('read_monthly_attendance')
                <li class="nav-item">
                    <a class="nav-link mt-0 {{ request()->routeIs('attendances.monthlyCreate') ? 'active' : '' }}"
                        href="{{ route('attendances.monthlyCreate') }}">{{ localize('monthly_attendance') }}</a>
                </li>
            @endcan
            @can('read_missing_attendance')
                <li class="nav-item">
                    <a class="nav-link mt-0 {{ request()->routeIs('attendances.missingAttendance') ? 'active' : '' }}"
                        href="{{ route('attendances.missingAttendance') }}">{{ localize('missing_attendance') }}</a>
                </li>
            @endcan
            @can('read_attendance')
                <li class="nav-item">
                    <a class="nav-link mt-0 {{ request()->routeIs('attendances.exceptions') ? 'active' : '' }}"
                        href="{{ route('attendances.exceptions') }}">{{ localize('attendance_exceptions', 'Attendance exceptions') }}</a>
                </li>
            @endcan
            @can('read_attendance')
                <li class="nav-item">
                    <a class="nav-link mt-0 {{ request()->routeIs('attendances.qrCreate') || request()->routeIs('attendances.qrGenerate') ? 'active' : '' }}"
                        href="{{ route('attendances.qrCreate') }}">{{ localize('qr_attendance', 'QR attendance') }}</a>
                </li>
            @endcan
            @can('read_attendance')
                <li class="nav-item">
                    <a class="nav-link mt-0 {{ request()->routeIs('attendances.help') ? 'active' : '' }}"
                        href="{{ route('attendances.help') }}">{{ localize('help', 'Help') }}</a>
                </li>
            @endcan
        </ul>
    </div>
</div>
