@php
    $canSeeMissionModule = auth()->user()?->can('read_mission') || auth()->user()?->can('create_mission')
        || auth()->user()?->can('approve_mission') || auth()->user()?->can('manage_mission_order');
@endphp
@if($canSeeMissionModule)
<div class="attendance-ui mb-3">
    <div class="card att-card fixed-tab col-12 col-md-12">
        <div class="card-body py-2 px-2 d-flex align-items-center justify-content-between gap-2">
            <ul class="nav att-tabs flex-wrap flex-grow-1">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('missions.dashboard') ? 'active' : '' }}" href="{{ route('missions.dashboard') }}">
                        <i class="fa fa-tachometer-alt me-1"></i>Dashboard
                    </a>
                </li>
                @can('create_mission')
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('missions.request.create') ? 'active' : '' }}" href="{{ route('missions.request.create') }}">
                            <i class="fa fa-file-signature me-1"></i>ស្នើសុំបេសកកម្ម
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('missions.queue') && request()->route('key') === 'mine' ? 'active' : '' }}" href="{{ route('missions.queue', 'mine') }}">
                            <i class="fa fa-user me-1"></i>សំណើរបស់ខ្ញុំ
                        </a>
                    </li>
                @endcan
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('missions.queue') && request()->route('key') === 'office-head' ? 'active' : '' }}" href="{{ route('missions.queue', 'office-head') }}">
                        <i class="fa fa-inbox me-1"></i>រង់ចាំប្រធានការិយាល័យ
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('missions.queue') && request()->route('key') === 'director' ? 'active' : '' }}" href="{{ route('missions.queue', 'director') }}">
                        <i class="fa fa-inbox me-1"></i>រង់ចាំប្រធានមន្ទីរ
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('missions.queue') && request()->route('key') === 'approved' ? 'active' : '' }}" href="{{ route('missions.queue', 'approved') }}">
                        <i class="fa fa-check-circle me-1"></i>សំណើដែលបានអនុម័ត
                    </a>
                </li>
                @can('manage_mission_order')
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('missions.direct.create') ? 'active' : '' }}" href="{{ route('missions.direct.create') }}">
                            <i class="fa fa-bolt me-1"></i>បង្កើតដោយផ្ទាល់
                        </a>
                    </li>
                @endcan
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('missions.queue') && request()->route('key') === 'on-mission' ? 'active' : '' }}" href="{{ route('missions.queue', 'on-mission') }}">
                        <i class="fa fa-route me-1"></i>កំពុងបេសកកម្ម
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('missions.queue') && request()->route('key') === 'pending-report' ? 'active' : '' }}" href="{{ route('missions.queue', 'pending-report') }}">
                        <i class="fa fa-file-alt me-1"></i>រង់ចាំរបាយការណ៍
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('missions.queue') && request()->route('key') === 'history' ? 'active' : '' }}" href="{{ route('missions.queue', 'history') }}">
                        <i class="fa fa-history me-1"></i>ប្រវត្តិបេសកកម្ម
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('missions.index') ? 'active' : '' }}" href="{{ route('missions.index') }}">
                        <i class="fa fa-list me-1"></i>បញ្ជីទាំងអស់
                    </a>
                </li>
            </ul>
        </div>
    </div>
</div>
@endif
