@extends('backend.layouts.app')
@section('title', localize('attendance_workflow', 'Attendance Workflow'))

@section('content')
    @include('humanresource::attendance_header')

    {{-- Auto-refresh indicator --}}
    <div class="d-flex align-items-center justify-content-end mb-2 gap-2">
        <span class="text-muted small" id="last-refresh-label">
            {{ localize('last_refreshed', 'Last refreshed') }}: <span id="last-refresh-time">{{ now()->format('H:i:s') }}</span>
        </span>
        <span class="badge bg-success text-white small" id="auto-refresh-badge">
            <span class="spinner-grow spinner-grow-sm me-1" role="status" aria-hidden="true"></span>
            {{ localize('auto_refresh', 'Auto-refresh ON') }}
        </span>
        <button class="btn btn-sm btn-outline-secondary" id="manual-refresh-btn" title="{{ localize('refresh_now', 'Refresh Now') }}">
            <i class="fas fa-sync-alt"></i>
        </button>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">{{ localize('employees_in_scope', 'បុគ្គលិកក្នុងសិទ្ធិ') }}</div>
                    <div class="fs-2 fw-bold">{{ $workflow['employees_in_scope'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">{{ localize('today_attendance', 'វត្តមានថ្ងៃនេះ') }}</div>
                    <div class="fs-2 fw-bold text-success">{{ $workflow['today_attendance'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">{{ localize('today_missing', 'អវត្តមានថ្ងៃនេះ') }}</div>
                    <div class="fs-2 fw-bold text-warning">{{ $workflow['missing_today'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">{{ localize('today_exceptions', 'ករណីខុសថ្ងៃនេះ') }}</div>
                    <div class="fs-2 fw-bold text-danger">{{ $workflow['today_exceptions'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header fw-semibold">{{ localize('device_approval_flow', 'Device Approval Flow') }}</div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-4 text-center">
                            <div class="small text-muted">Pending</div>
                            <div class="fs-4 fw-bold text-warning">{{ $workflow['device_pending'] }}</div>
                        </div>
                        <div class="col-4 text-center">
                            <div class="small text-muted">Active</div>
                            <div class="fs-4 fw-bold text-success">{{ $workflow['device_active'] }}</div>
                        </div>
                        <div class="col-4 text-center">
                            <div class="small text-muted">Blocked</div>
                            <div class="fs-4 fw-bold text-danger">{{ $workflow['device_blocked'] }}</div>
                        </div>
                    </div>
                    <a href="{{ route('role.user.list') }}" class="btn btn-outline-primary">
                        {{ localize('open_device_management', 'Open Device Management') }}
                    </a>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header fw-semibold">{{ localize('device_connectivity', 'Device Connectivity') }}</div>
                <div class="card-body">
                    <div class="row g-3 mb-2">
                        <div class="col-6 text-center">
                            <div class="small text-muted">Online</div>
                            <div class="fs-4 fw-bold text-success">{{ $workflow['device_online'] }}</div>
                        </div>
                        <div class="col-6 text-center">
                            <div class="small text-muted">Offline</div>
                            <div class="fs-4 fw-bold text-secondary">{{ $workflow['device_offline'] }}</div>
                        </div>
                    </div>
                    <p class="text-muted small mb-0">
                        {{ localize('device_online_hint', 'Online means active device login within the last') }}
                        <strong>{{ $workflow['device_online_window_minutes'] }}</strong>
                        {{ localize('minutes', 'minutes') }}.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                    <span>{{ localize('recent_device_activity', 'Recent Device Activity') }}</span>
                    <span class="badge bg-light text-dark">{{ $workflow['device_recent_activity']->count() }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ localize('employee', 'Employee') }}</th>
                                <th>{{ localize('device', 'Device') }}</th>
                                <th>{{ localize('last_login', 'Last Login') }}</th>
                                <th class="text-center">{{ localize('state', 'State') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($workflow['device_recent_activity'] as $device)
                                @php
                                    $status = (string) $device->status;
                                    $stateLabel = ucfirst($status);
                                    $stateClass = 'bg-secondary';

                                    if ($status === 'active' && $device->is_online) {
                                        $stateLabel = localize('online', 'Online');
                                        $stateClass = 'bg-success';
                                    } elseif ($status === 'active') {
                                        $stateLabel = localize('offline', 'Offline');
                                        $stateClass = 'bg-warning text-dark';
                                    } elseif ($status === 'pending') {
                                        $stateLabel = localize('pending', 'Pending');
                                        $stateClass = 'bg-warning text-dark';
                                    } elseif ($status === 'blocked') {
                                        $stateLabel = localize('blocked', 'Blocked');
                                        $stateClass = 'bg-danger';
                                    }
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ optional($device->user)->full_name ?? optional($device->user)->email ?? '-' }}</div>
                                        <div class="text-muted small">{{ optional(optional($device->user)->employee)->employee_id ?? optional($device->user)->email ?? '-' }}</div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold small">{{ $device->device_name ?: localize('unnamed_device', 'Unnamed device') }}</div>
                                        <code class="small text-muted">{{ \Illuminate\Support\Str::limit($device->device_id, 30) }}</code>
                                        @if($device->platform)
                                            <div><span class="badge bg-info text-dark mt-1">{{ strtoupper($device->platform) }}</span></div>
                                        @endif
                                    </td>
                                    <td>
                                        @if($device->last_login_at)
                                            <div>{{ $device->last_login_at->format('Y-m-d H:i') }}</div>
                                            <small class="text-muted">{{ $device->last_login_at->diffForHumans() }}</small>
                                        @else
                                            <span class="text-muted">{{ localize('no_login_yet', 'No login yet') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <span class="badge {{ $stateClass }}">{{ $stateLabel }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        {{ localize('no_device_activity', 'No device activity found yet.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header fw-semibold">{{ localize('qr_workflow', 'QR Workflow') }}</div>
                <div class="card-body">
                    <p class="mb-2 text-muted">{{ localize('qr_units_available', 'Available units for QR') }}: <strong>{{ $workflow['qr_units'] }}</strong></p>
                    <a href="{{ route('attendances.qrCreate') }}" class="btn btn-outline-success me-2">{{ localize('generate_qr', 'Generate QR') }}</a>
                    <a href="{{ route('attendances.exceptions', ['date' => $today]) }}" class="btn btn-outline-danger mt-2 mt-md-0">{{ localize('view_exceptions', 'View Exceptions') }}</a>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header fw-semibold">{{ localize('attendance_workflow_steps', 'លំដាប់លំហូរការងារ') }}</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="border rounded p-3 h-100">
                        <div class="fw-semibold mb-2">1. {{ localize('device_request', 'ស្នើសុំឧបករណ៍') }}</div>
                        <div class="text-muted small">{{ localize('device_request_desc', 'អ្នកប្រើ login លើកដំបូង ហើយឧបករណ៍ចូល pending') }}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-3 h-100">
                        <div class="fw-semibold mb-2">2. {{ localize('manager_approval', 'អនុម័ត') }}</div>
                        <div class="text-muted small">{{ localize('manager_approval_desc', 'អ្នកគ្រប់គ្រង approve/block/reject ឧបករណ៍') }}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-3 h-100">
                        <div class="fw-semibold mb-2">3. {{ localize('attendance_capture', 'កត់វត្តមាន') }}</div>
                        <div class="text-muted small">{{ localize('attendance_capture_desc', 'Manual, Monthly, Missing ឬ QR តាមសិទ្ធិអង្គភាព') }}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-3 h-100">
                        <div class="fw-semibold mb-2">4. {{ localize('exception_review', 'ពិនិត្យករណីខុស') }}</div>
                        <div class="text-muted small">{{ localize('exception_review_desc', 'ត្រួតពិនិត្យ missing attendance និង unpaired punches') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js')
<script>
    // Auto-refresh the workflow page every 30 seconds to show live device online status
    const REFRESH_INTERVAL_MS = 30000;
    let countdown = REFRESH_INTERVAL_MS / 1000;

    setInterval(function () {
        countdown--;
        if (countdown <= 0) {
            window.location.reload();
        }
    }, 1000);

    document.getElementById('manual-refresh-btn').addEventListener('click', function () {
        window.location.reload();
    });
</script>
@endpush
