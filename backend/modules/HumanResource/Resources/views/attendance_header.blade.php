<div class="attendance-ui mb-3">
    <div class="card att-card fixed-tab col-12 col-md-12">
        <div class="card-body py-2 px-2 d-flex align-items-center justify-content-between gap-2">
            <ul class="nav att-tabs flex-wrap flex-grow-1">
                {{-- ១. ទិដ្ឋភាពទូទៅ --}}
                @can('read_attendance')
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('attendances.workflow') ? 'active' : '' }}"
                            href="{{ route('attendances.workflow') }}">
                            <i class="fa fa-tachometer-alt me-1"></i>{{ localize('attendance_overview', 'ទិដ្ឋភាពទូទៅ') }}
                        </a>
                    </li>
                @endcan

                {{-- ២. កត់ត្រាវត្តមាន (ការចូល-ចេញ / ប្រចាំខែ / QR) --}}
                @canany(['read_attendance', 'read_monthly_attendance'])
                    @php
                        $recordingActive = request()->routeIs('attendances.create')
                            || request()->routeIs('attendances.index')
                            || request()->routeIs('attendances.edit')
                            || request()->routeIs('attendances.monthlyCreate')
                            || request()->routeIs('attendances.qrCreate')
                            || request()->routeIs('attendances.qrGenerate');
                    @endphp
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle {{ $recordingActive ? 'active' : '' }}" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-fingerprint me-1"></i>{{ localize('attendance_recording', 'កត់ត្រាវត្តមាន') }}
                        </a>
                        <ul class="dropdown-menu">
                            @can('read_attendance')
                                <li>
                                    <a class="dropdown-item {{ request()->routeIs('attendances.create') || request()->routeIs('attendances.index') || request()->routeIs('attendances.edit') ? 'active' : '' }}"
                                        href="{{ route('attendances.create') }}">
                                        <i class="fa fa-fingerprint me-1"></i>{{ localize('attendance_record', 'ការចូល-ចេញ') }}
                                    </a>
                                </li>
                            @endcan
                            @can('read_monthly_attendance')
                                <li>
                                    <a class="dropdown-item {{ request()->routeIs('attendances.monthlyCreate') ? 'active' : '' }}"
                                        href="{{ route('attendances.monthlyCreate') }}">
                                        <i class="fa fa-calendar-alt me-1"></i>{{ localize('monthly_attendance', 'ប្រចាំខែ') }}
                                    </a>
                                </li>
                            @endcan
                            @can('read_attendance')
                                <li>
                                    <a class="dropdown-item {{ request()->routeIs('attendances.qrCreate') || request()->routeIs('attendances.qrGenerate') ? 'active' : '' }}"
                                        href="{{ route('attendances.qrCreate') }}">
                                        <i class="fa fa-qrcode me-1"></i>{{ localize('qr_attendance', 'QR Attendance') }}
                                    </a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcanany

                {{-- ៣. វេន និងកាលវិភាគ (ម៉ោងធ្វើការ / តារាងវេនយាម / ក្រុមវេន) --}}
                @canany(['read_shift', 'read_shift_roster'])
                    @php
                        $shiftActive = request()->routeIs('shifts.*') || request()->routeIs('shift-rosters.*') || request()->routeIs('shift-teams.*');
                    @endphp
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle {{ $shiftActive ? 'active' : '' }}" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-clock me-1"></i>{{ localize('shift_and_schedule', 'វេន និងកាលវិភាគ') }}
                        </a>
                        <ul class="dropdown-menu">
                            @can('read_shift')
                                <li>
                                    <a class="dropdown-item {{ request()->routeIs('shifts.*') ? 'active' : '' }}" href="{{ route('shifts.index') }}">
                                        <i class="fa fa-clock me-1"></i>{{ 'ម៉ោងធ្វើការ' }}
                                    </a>
                                </li>
                            @endcan
                            @can('read_shift_roster')
                                <li>
                                    <a class="dropdown-item {{ request()->routeIs('shift-rosters.*') ? 'active' : '' }}" href="{{ route('shift-rosters.index', ['department_id' => request('department_id')]) }}">
                                        <i class="fa fa-calendar-alt me-1"></i>តារាងវេនយាម
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item {{ request()->routeIs('shift-teams.*') ? 'active' : '' }}" href="{{ route('shift-teams.index', ['department_id' => request('department_id')]) }}">
                                        <i class="fa fa-users me-1"></i>ក្រុមវេន
                                    </a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endcanany

                {{-- ៤. បេសកម្ម --}}
                @can('read_mission')
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('missions.*') ? 'active' : '' }}"
                            href="{{ route('missions.index') }}">
                            <i class="fa fa-map-marker-alt me-1"></i>{{ localize('missions', 'បេសកម្ម') }}
                        </a>
                    </li>
                @endcan

                {{-- ៥. ករណីពុំប្រក្រតី (Exceptions + Missing + Adjustments) --}}
                @can('read_attendance')
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('attendances.exceptions') || request()->routeIs('attendances.missingAttendance') || request()->routeIs('attendance-adjustments.*') ? 'active' : '' }}"
                            href="{{ route('attendances.exceptions') }}">
                            <i class="fa fa-exclamation-triangle me-1"></i>{{ localize('attendance_exceptions_menu', 'ករណីពុំប្រក្រតី') }}
                        </a>
                    </li>
                @endcan

                {{-- ៦. ទិន្នន័យប្រចាំថ្ងៃ --}}
                @can('read_attendance_snapshot')
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('attendance-snapshots.*') ? 'active' : '' }}"
                            href="{{ route('attendance-snapshots.daily') }}">
                            <i class="fa fa-table me-1"></i>{{ localize('daily_snapshot', 'ទិន្នន័យប្រចាំថ្ងៃ') }}
                        </a>
                    </li>
                @endcan
            </ul>

            {{-- Help icon (far right, not a tab) --}}
            @can('read_attendance')
                <a href="{{ route('attendances.help') }}" class="btn btn-sm btn-outline-success ms-2" title="{{ localize('help', 'ជំនួយ') }}" style="white-space: nowrap;">
                    <i class="fa fa-question-circle"></i> {{ localize('help', 'ជំនួយ') }}
                </a>
            @endcan
        </div>
    </div>
</div>
