<div class="leave-ui mb-3">
    <div class="card att-card fixed-tab col-12 col-md-12">
        <div class="card-body py-2 px-2">
            <ul class="nav leave-tabs flex-wrap">
            @can('read_weekly_holiday')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('leave.weekleave') || request()->routeIs('leave.weekleave.edit') ? 'active' : '' }}"
                        href="{{ route('leave.weekleave') }}"><i class="fa fa-calendar-week me-1"></i>{{ localize('weekly_holiday') }}</a>
                </li>
            @endcan
            @can('read_holiday')
                <li class="nav-item">
                    <a class="nav-link mt-0 {{ request()->routeIs('holiday.index') ? 'active' : '' }}"
                        href="{{ route('holiday.index') }}"><i class="fa fa-umbrella-beach me-1"></i>{{ localize('holiday') }}</a>
                </li>
            @endcan
            @can('read_leave_type')
                <li class="nav-item">
                    <a class="nav-link mt-0 {{ request()->routeIs('leave.leaveTypeindex') ? 'active' : '' }}"
                        href="{{ route('leave.leaveTypeindex') }}"><i class="fa fa-tags me-1"></i>{{ localize('leave_type') }}</a>
                </li>
            @endcan
            <!-- @can('read_leave_type')
                <li class="nav-item">
                    <a class="nav-link mt-0 {{ request()->routeIs('leave.leaveGenerate') || request()->routeIs('leave.generateLeaveDetail') ? 'active' : '' }}"
                        href="{{ route('leave.leaveGenerate') }}">{{ localize('leave_generate') }}</a>
                </li>
            @endcan -->
            @can('read_leave_approval')
                <li class="nav-item">
                    <a class="nav-link mt-0 {{ request()->routeIs('leave.approval') ? 'active' : '' }}"
                        href="{{ route('leave.approval') }}"><i class="fa fa-check-circle me-1"></i>{{ localize('leave_approval') }}</a>
                </li>
            @endcan
            @can('read_leave_application')
                <li class="nav-item">
                    <a class="nav-link mt-0 {{ request()->routeIs('leave.index') ? 'active' : '' }}"
                        href="{{ route('leave.index') }}"><i class="fa fa-file-alt me-1"></i>{{ localize('leave_application') }}</a>
                </li>
            @endcan
            </ul>
        </div>
    </div>
</div>
