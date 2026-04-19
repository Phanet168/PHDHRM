@extends('backend.layouts.app')
@section('title', localize('attendance_workflow', 'Attendance Workflow'))

@push('css')
<style>
    .workflow-shell {
        --wf-surface: #ffffff;
        --wf-bg-soft: #f4f7fb;
        --wf-line: #e4e9f2;
        --wf-text-muted: #5f6b7a;
        --wf-navy: #0f2742;
        --wf-cyan: #1fa2a6;
        --wf-green: #1f9d55;
        --wf-orange: #e38b2c;
        --wf-red: #dc3f45;
    }

    .workflow-hero {
        background: linear-gradient(125deg, #0f2742 0%, #1a3c66 45%, #1fa2a6 100%);
        border-radius: 14px;
        color: #fff;
        padding: 1rem 1.2rem;
        box-shadow: 0 10px 24px rgba(8, 25, 43, 0.2);
    }

    .workflow-hero-subtitle {
        color: rgba(255, 255, 255, 0.83);
        font-size: 0.92rem;
    }

    .workflow-stat-card {
        background: var(--wf-surface);
        border: 1px solid var(--wf-line);
        border-radius: 12px;
        box-shadow: 0 6px 18px rgba(17, 40, 66, 0.06);
    }

    .workflow-stat-card .icon-pill {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.95rem;
    }

    .icon-pill.navy {
        background: rgba(15, 39, 66, 0.12);
        color: var(--wf-navy);
    }

    .icon-pill.green {
        background: rgba(31, 157, 85, 0.14);
        color: var(--wf-green);
    }

    .icon-pill.orange {
        background: rgba(227, 139, 44, 0.16);
        color: var(--wf-orange);
    }

    .icon-pill.red {
        background: rgba(220, 63, 69, 0.14);
        color: var(--wf-red);
    }

    .workflow-pane {
        border: 1px solid var(--wf-line);
        border-radius: 12px;
        box-shadow: 0 6px 18px rgba(17, 40, 66, 0.05);
        overflow: hidden;
    }

    .workflow-pane .card-header {
        border-bottom: 1px solid var(--wf-line);
        background: var(--wf-bg-soft);
    }

    .workflow-kpi {
        background: #f8fafc;
        border: 1px solid var(--wf-line);
        border-radius: 10px;
        padding: 0.65rem;
    }

    .workflow-table thead th {
        border-bottom-width: 1px;
        color: #435163;
        font-weight: 600;
        letter-spacing: 0.01em;
    }

    .workflow-table tbody tr td {
        border-color: #edf1f7;
    }

    .workflow-table code {
        background: #f1f5fb;
        border-radius: 6px;
        padding: 0.15rem 0.35rem;
        display: inline-block;
    }

    .workflow-step {
        border: 1px solid var(--wf-line);
        border-radius: 12px;
        padding: 0.95rem;
        height: 100%;
        background: #fff;
        position: relative;
    }

    .workflow-step .step-index {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        background: #eaf1fb;
        color: #23436d;
        margin-bottom: 0.6rem;
    }
</style>
@endpush

@section('content')
    @include('humanresource::attendance_header')

    <div class="workflow-shell">
        <div class="workflow-hero mb-3 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <h5 class="mb-1 fw-semibold">{{ localize('attendance_workflow', 'Attendance Workflow') }}</h5>
                <div class="workflow-hero-subtitle">
                    {{ localize('workflow_overview_hint', 'Overview of attendance activity, device status, and exception handling') }}
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-light text-dark">
                    {{ localize('last_refreshed', 'Last refreshed') }}: {{ now()->format('H:i:s') }}
                </span>
                <a href="{{ request()->fullUrl() }}" class="btn btn-sm btn-light">
                    <i class="fas fa-sync-alt me-1"></i>{{ localize('refresh_now', 'Refresh') }}
                </a>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="workflow-stat-card card h-100">
                <div class="card-body">
                    <span class="icon-pill navy mb-2"><i class="fas fa-users"></i></span>
                    <div class="text-muted small">{{ localize('employees_in_scope', 'បុគ្គលិកក្នុងសិទ្ធិ') }}</div>
                    <div class="fs-2 fw-bold">{{ $workflow['employees_in_scope'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="workflow-stat-card card h-100">
                <div class="card-body">
                    <span class="icon-pill green mb-2"><i class="fas fa-check-circle"></i></span>
                    <div class="text-muted small">{{ localize('today_attendance', 'វត្តមានថ្ងៃនេះ') }}</div>
                    <div class="fs-2 fw-bold text-success">{{ $workflow['today_attendance'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="workflow-stat-card card h-100">
                <div class="card-body">
                    <span class="icon-pill orange mb-2"><i class="fas fa-user-clock"></i></span>
                    <div class="text-muted small">{{ localize('today_missing', 'អវត្តមានថ្ងៃនេះ') }}</div>
                    <div class="fs-2 fw-bold text-warning">{{ $workflow['missing_today'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="workflow-stat-card card h-100">
                <div class="card-body">
                    <span class="icon-pill red mb-2"><i class="fas fa-exclamation-triangle"></i></span>
                    <div class="text-muted small">{{ localize('today_exceptions', 'ករណីខុសថ្ងៃនេះ') }}</div>
                    <div class="fs-2 fw-bold text-danger">{{ $workflow['today_exceptions'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="workflow-pane card h-100">
                <div class="card-header fw-semibold">{{ localize('device_approval_flow', 'Device Approval Flow') }}</div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-4 text-center">
                            <div class="workflow-kpi">
                            <div class="small text-muted">Pending</div>
                            <div class="fs-4 fw-bold text-warning">{{ $workflow['device_pending'] }}</div>
                            </div>
                        </div>
                        <div class="col-4 text-center">
                            <div class="workflow-kpi">
                            <div class="small text-muted">Active</div>
                            <div class="fs-4 fw-bold text-success">{{ $workflow['device_active'] }}</div>
                            </div>
                        </div>
                        <div class="col-4 text-center">
                            <div class="workflow-kpi">
                            <div class="small text-muted">Blocked</div>
                            <div class="fs-4 fw-bold text-danger">{{ $workflow['device_blocked'] }}</div>
                            </div>
                        </div>
                    </div>
                    <a href="{{ route('role.user.list') }}" class="btn btn-outline-primary">
                        {{ localize('open_device_management', 'Open Device Management') }}
                    </a>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="workflow-pane card h-100">
                <div class="card-header fw-semibold">{{ localize('device_connectivity', 'Device Connectivity') }}</div>
                <div class="card-body">
                    <div class="row g-3 mb-2">
                        <div class="col-6 text-center">
                            <div class="workflow-kpi">
                            <div class="small text-muted">Online</div>
                            <div class="fs-4 fw-bold text-success">{{ $workflow['device_online'] }}</div>
                            </div>
                        </div>
                        <div class="col-6 text-center">
                            <div class="workflow-kpi">
                            <div class="small text-muted">Offline</div>
                            <div class="fs-4 fw-bold text-secondary">{{ $workflow['device_offline'] }}</div>
                            </div>
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
            <div class="workflow-pane card h-100">
                <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                    <span>{{ localize('recent_device_activity', 'Recent Device Activity') }}</span>
                    <span class="badge bg-light text-dark">{{ $workflow['device_recent_activity']->count() }}</span>
                </div>
                <div class="table-responsive">
                    <table class="workflow-table table table-sm align-middle mb-0">
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
            <div class="workflow-pane card h-100">
                <div class="card-header fw-semibold">{{ localize('qr_workflow', 'QR Workflow') }}</div>
                <div class="card-body">
                    <p class="mb-2 text-muted">{{ localize('qr_units_available', 'Available units for QR') }}: <strong>{{ $workflow['qr_units'] }}</strong></p>
                    <a href="{{ route('attendances.qrCreate') }}" class="btn btn-outline-success me-2">{{ localize('generate_qr', 'Generate QR') }}</a>
                    <a href="{{ route('attendances.exceptions', ['date' => $today]) }}" class="btn btn-outline-danger mt-2 mt-md-0">{{ localize('view_exceptions', 'View Exceptions') }}</a>
                </div>
            </div>
        </div>
    </div>

    <div class="workflow-pane card mb-2">
        <div class="card-header fw-semibold">{{ localize('attendance_workflow_steps', 'លំដាប់លំហូរការងារ') }}</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="workflow-step">
                        <div class="step-index">1</div>
                        <div class="fw-semibold mb-2">{{ localize('device_request', 'ស្នើសុំឧបករណ៍') }}</div>
                        <div class="text-muted small">{{ localize('device_request_desc', 'អ្នកប្រើ login លើកដំបូង ហើយឧបករណ៍ចូល pending') }}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="workflow-step">
                        <div class="step-index">2</div>
                        <div class="fw-semibold mb-2">{{ localize('manager_approval', 'អនុម័ត') }}</div>
                        <div class="text-muted small">{{ localize('manager_approval_desc', 'អ្នកគ្រប់គ្រង approve/block/reject ឧបករណ៍') }}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="workflow-step">
                        <div class="step-index">3</div>
                        <div class="fw-semibold mb-2">{{ localize('attendance_capture', 'កត់វត្តមាន') }}</div>
                        <div class="text-muted small">{{ localize('attendance_capture_desc', 'Manual, Monthly, Missing ឬ QR តាមសិទ្ធិអង្គភាព') }}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="workflow-step">
                        <div class="step-index">4</div>
                        <div class="fw-semibold mb-2">{{ localize('exception_review', 'ពិនិត្យករណីខុស') }}</div>
                        <div class="text-muted small">{{ localize('exception_review_desc', 'ត្រួតពិនិត្យ missing attendance និង unpaired punches') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
