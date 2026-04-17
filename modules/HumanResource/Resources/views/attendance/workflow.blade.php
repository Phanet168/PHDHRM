@extends('backend.layouts.app')
@section('title', localize('attendance_workflow', 'Attendance Workflow'))

@section('content')
    @include('humanresource::attendance_header')

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
                <div class="card-header fw-semibold">{{ localize('device_approval_flow', 'លំហូរអនុម័តទូរសព្ទ') }}</div>
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
                    <a href="{{ route('mobile-devices.index') }}" class="btn btn-outline-primary">
                        {{ localize('open_device_management', 'បើកការគ្រប់គ្រងទូរសព្ទ') }}
                    </a>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header fw-semibold">{{ localize('qr_workflow', 'QR Workflow') }}</div>
                <div class="card-body">
                    <p class="mb-2 text-muted">{{ localize('qr_units_available', 'ចំនួនអង្គភាពអាចបង្កើត QR') }}: <strong>{{ $workflow['qr_units'] }}</strong></p>
                    <a href="{{ route('attendances.qrCreate') }}" class="btn btn-outline-success me-2">{{ localize('generate_qr', 'បង្កើត QR') }}</a>
                    <a href="{{ route('attendances.exceptions', ['date' => $today]) }}" class="btn btn-outline-danger">{{ localize('view_exceptions', 'មើលករណីខុស') }}</a>
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