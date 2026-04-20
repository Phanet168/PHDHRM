@extends('backend.layouts.app')
@section('title', localize('qr_attendance', 'QR Attendance'))

@push('css')
<style>
    /* ── Print layout: show only the poster card ── */
    @media print {
        body > *            { visibility: hidden !important; }
        #qrPrintPoster,
        #qrPrintPoster *    { visibility: visible !important; }
        #qrPrintPoster {
            position: fixed !important;
            inset: 0;
            display: flex !important;
            align-items: center;
            justify-content: center;
            background: #fff;
        }
    }
    @media screen {
        #qrPrintPoster { display: none; }
    }

    /* Geofence status chips */
    .geo-chip { display: inline-flex; align-items: center; gap: 5px;
                padding: 3px 10px; border-radius: 20px; font-size: .8rem; font-weight: 600; }
    .geo-ok   { background: #e6f4ea; color: #1b6b34; }
    .geo-warn { background: #fff3cd; color: #85600a; }
    .geo-miss { background: #f8d7da; color: #842029; }

    /* Punch-type badges on history table */
    .punch-in  { background: #e6f4ea; color: #1b6b34; }
    .punch-out { background: #fdecea; color: #b71c1c; }
</style>
@endpush

@section('content')
    @include('humanresource::attendance_header')

    {{-- ══════════════════════════════════════════
         PRINT POSTER  (hidden on screen)
    ══════════════════════════════════════════ --}}
    @if ($generated)
    <div id="qrPrintPoster">
        <div style="text-align:center; font-family: 'Khmer OS', Arial, sans-serif;
                    padding: 40px 48px; border: 5px solid #1a7c5e;
                    border-radius: 14px; max-width: 520px; width: 100%;">

            <div style="font-size: .9rem; font-weight: 700; color: #1a7c5e;
                        letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 6px;">
                {{ localize('workplace_attendance_qr', 'ការស្កេនវត្តមាន') }}
            </div>

            <div style="font-size: 1.6rem; font-weight: 900; color: #14211d; margin-bottom: 24px;">
                {{ $generated['workplace_name'] ?: '-' }}
            </div>

            <img src="{{ $generated['qr_image_url_print'] }}" alt="QR"
                 style="width:380px; height:380px; border:6px solid #d0ede3;
                        border-radius:10px; padding:6px; background:#fff;">

            <div style="margin-top:18px; font-size:1rem; color:#444; line-height:1.7;">
                {{ localize('qr_print_instruction', 'ស្កេន QR នេះតាម Mobile App ដើម្បីកត់ត្រាវត្តមានការងារ') }}
            </div>

            <div style="margin-top:8px; font-size:.72rem; color:#aaa;">
                {{ localize('generated_on', 'បង្កើតថ្ងៃ') }}: {{ $generated['generated_at']->format('d/m/Y H:i') }}
            </div>
        </div>
    </div>
    @endif

    {{-- ══════════════════════════════════════════
         MAIN MANAGEMENT CARD
    ══════════════════════════════════════════ --}}
    <div class="card mb-4 fixed-tab-body att-card">
        @include('backend.layouts.common.validation')
        @include('backend.layouts.common.message')

        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="fs-17 fw-semi-bold mb-0">
                <i class="fa fa-qrcode me-1"></i>{{ localize('workplace_qr_manager', 'Workplace QR — បោះពុម្ពបិទការិយាល័យ') }}
            </h6>
            @if ($generated)
                <button onclick="window.print()" class="btn btn-sm btn-primary no-print">
                    <i class="fa fa-print me-1"></i>{{ localize('print_qr', 'បោះពុម្ព QR') }}
                </button>
            @endif
        </div>

        <div class="card-body">

            {{-- ── Step 1: Select Workplace & Generate ── --}}
            <form action="{{ route('attendances.qrGenerate') }}" method="POST" class="row g-3 align-items-end">
                @csrf
                <div class="col-md-9">
                    <label for="workplace_id" class="form-label fw-semibold">
                        {{ localize('select_workplace', 'ជ្រើសរើសអង្គភាព (Workplace)') }}
                        <span class="text-danger">*</span>
                    </label>
                    <select name="workplace_id" id="workplace_id" class="select-basic-single" required>
                        <option value="">{{ localize('select_one', 'ជ្រើសរើស…') }}</option>
                        @foreach ($orgUnitOptions as $option)
                            @php
                                $optId   = data_get($option, 'id');
                                $optText = data_get($option, 'path') ?? data_get($option, 'label') ?? data_get($option, 'name') ?? ('#' . $optId);
                            @endphp
                            <option value="{{ $optId }}"
                                @selected(old('workplace_id', $selectedWorkplaceId ?? null) == $optId)>
                                {{ $optText }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text text-muted">
                        <i class="fa fa-info-circle me-1"></i>
                        {{ localize('qr_static_note', 'QR នេះជា QR ថេរ (permanent) — បោះពុម្ពបិទនៅចំហៀងទ្វារ ឬ នៅកន្លែងធ្វើការ') }}
                    </div>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-success w-100">
                        <i class="fa fa-qrcode me-1"></i>{{ localize('generate_qr', 'បង្កើត QR') }}
                    </button>
                </div>
            </form>

            {{-- ── Geofence Rule Panel (shown after workplace chosen) ── --}}
            @if ($selectedWorkplace ?? null)
                @php
                    $geoLat    = $selectedWorkplace->geofence_latitude  ?? null;
                    $geoLng    = $selectedWorkplace->geofence_longitude  ?? null;
                    $geoRadius = (int) ($selectedWorkplace->geofence_radius_meters ?? 500);
                    $hasGeo    = $geoLat !== null && $geoLng !== null && (float)$geoLat != 0.0;
                @endphp
                <hr class="my-4">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <h6 class="fw-semibold mb-0">
                        <i class="fa fa-map-marker-alt text-danger me-1"></i>
                        {{ localize('geofence_rule', 'Geofence Rule') }}
                        <span class="text-muted fw-normal small ms-1">— {{ $selectedWorkplace->department_name }}</span>
                    </h6>
                    @if ($hasGeo)
                        <span class="geo-chip geo-ok">
                            <i class="fa fa-check-circle"></i> {{ localize('geofence_configured', 'Geofence configured') }}
                        </span>
                    @else
                        <span class="geo-chip geo-miss">
                            <i class="fa fa-exclamation-circle"></i> {{ localize('geofence_not_set', 'មិនទាន់កំណត់') }}
                        </span>
                    @endif
                </div>

                @if ($hasGeo)
                    <div class="row g-3 mb-2">
                        <div class="col-sm-4">
                            <div class="border rounded p-3 bg-light text-center">
                                <div class="small text-muted mb-1">{{ localize('geofence_latitude', 'Latitude') }}</div>
                                <div class="fw-bold font-monospace">{{ number_format((float)$geoLat, 7) }}</div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="border rounded p-3 bg-light text-center">
                                <div class="small text-muted mb-1">{{ localize('geofence_longitude', 'Longitude') }}</div>
                                <div class="fw-bold font-monospace">{{ number_format((float)$geoLng, 7) }}</div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="border rounded p-3 bg-light text-center">
                                <div class="small text-muted mb-1">{{ localize('acceptable_range', 'Radius (m)') }}</div>
                                <div class="fw-bold">{{ number_format($geoRadius, 0) }} m</div>
                            </div>
                        </div>
                    </div>
                    <p class="small text-muted mb-0">
                        <i class="fa fa-info-circle me-1"></i>
                        {{ localize('geofence_help', 'បុគ្គលិកត្រូវស្ថិតក្នុងចម្ងាយ') }} <strong>{{ number_format($geoRadius, 0) }}m</strong>
                        {{ localize('geofence_help2', 'ពីអង្គភាព ដើម្បីស្កេនវត្តមានបានជោគជ័យ') }}
                    </p>
                @else
                    <div class="alert alert-warning mb-0">
                        <i class="fa fa-exclamation-triangle me-2"></i>
                        {{ localize('geofence_not_configured_warning', 'Geofence coordinates are not configured for this workplace. Employees may be rejected when scanning.') }}
                        <a href="{{ route('departments.index') }}" class="alert-link ms-2">
                            <i class="fa fa-external-link-alt me-1"></i>{{ localize('configure_now', 'Configure Now →') }}
                        </a>
                    </div>
                @endif
            @endif

            {{-- ── Generated QR Result ── --}}
            @if ($generated)
                <hr class="my-4">
                <div class="row g-4 align-items-start">

                    {{-- QR image + action buttons --}}
                    <div class="col-lg-4 text-center">
                        <div class="border rounded p-3 d-inline-block bg-white shadow-sm">
                            <img src="{{ $generated['qr_image_url'] }}" alt="Attendance QR"
                                 class="img-fluid" style="max-width: 280px;">
                        </div>
                        <div class="mt-3 d-flex justify-content-center flex-wrap gap-2">
                            <button onclick="window.print()" class="btn btn-primary">
                                <i class="fa fa-print me-1"></i>{{ localize('print_qr', 'បោះពុម្ព QR') }}
                            </button>
                            <a href="{{ $generated['qr_image_url_print'] }}" target="_blank"
                               class="btn btn-outline-secondary">
                                <i class="fa fa-download me-1"></i>{{ localize('download_image', 'ទាញយករូបភាព') }}
                            </a>
                        </div>
                    </div>

                    {{-- Info + print instructions --}}
                    <div class="col-lg-8">
                        <div class="alert alert-success mb-3">
                            <div class="fw-semibold mb-2">
                                <i class="fa fa-check-circle me-1"></i>{{ localize('qr_ready_to_print', 'QR រួចរាល់សម្រាប់បោះពុម្ព') }}
                            </div>
                            <div>
                                <strong>{{ localize('workplace', 'អង្គភាព') }}:</strong>
                                {{ $generated['workplace_name'] ?: '-' }}
                            </div>
                            <div>
                                <strong>{{ localize('generated_on', 'បង្កើតថ្ងៃ') }}:</strong>
                                {{ $generated['generated_at']->format('d/m/Y H:i:s') }}
                            </div>
                            <div class="mt-2 small text-success-emphasis">
                                <i class="fa fa-infinity me-1"></i>
                                {{ localize('qr_permanent_desc', 'QR នេះជា QR ថេរ (permanent) — មិនមានកាល​បរិច្ឆេទផុតកំណត់ — ត្រូវបោះពុម្ព​បិទ​នៅ​កន្លែង​ធ្វើការ') }}
                            </div>
                        </div>

                        <h6 class="fw-semibold mb-2">
                            <i class="fa fa-list-ol me-1 text-primary"></i>{{ localize('print_instructions', 'របៀបប្រើប្រាស់') }}
                        </h6>
                        <ol class="small text-muted ps-3 mb-0" style="line-height: 1.9;">
                            <li>{{ localize('print_step_1', 'ចុច "បោះពុម្ព QR" ឬ "ទាញយករូបភាព"') }}</li>
                            <li>{{ localize('print_step_2', 'ជ្រើស Paper size: A4 ហើយ Print') }}</li>
                            <li>{{ localize('print_step_3', 'បិទ QR នៅជិតទ្វារចូល ឬ ក្នុងការិយាល័យ') }}</li>
                            <li>{{ localize('print_step_4', 'បុគ្គលិកបើក Mobile App → QR Attendance → ស្កេន') }}</li>
                            <li>{{ localize('print_step_5', 'App ត្រួតពិនិត្យ GPS ហើយកត់ត្រាវត្តមានដោយស្វ័យប្រវត្តិ') }}</li>
                        </ol>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- ══════════════════════════════════════════
         SCAN HISTORY TABLE
    ══════════════════════════════════════════ --}}
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h6 class="fs-17 fw-semi-bold mb-0">
                <i class="fa fa-history me-1"></i>{{ localize('qr_scan_log', 'ប្រវត្តិការស្កេន QR') }}
                @if ($selectedWorkplaceId)
                    <span class="text-muted fw-normal small ms-1">— {{ $selectedWorkplace?->department_name }}</span>
                @endif
            </h6>
            <form action="{{ route('attendances.qrCreate') }}" method="GET"
                  class="d-flex gap-2 align-items-end mb-0 flex-wrap">
                <div>
                    <label class="form-label fw-semibold small mb-1">{{ localize('workplace', 'អង្គភាព') }}</label>
                    <select name="workplace_id" class="form-select form-select-sm" style="min-width:180px;">
                        <option value="">{{ localize('all', 'ទាំងអស់') }}</option>
                        @foreach ($orgUnitOptions as $option)
                            @php
                                $optId   = data_get($option, 'id');
                                $optText = data_get($option, 'label') ?? data_get($option, 'name') ?? ('#' . $optId);
                            @endphp
                            <option value="{{ $optId }}" @selected(($selectedWorkplaceId ?? null) == $optId)>
                                {{ $optText }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label fw-semibold small mb-1">{{ localize('date', 'ថ្ងៃ') }}</label>
                    <input type="date" class="form-control form-control-sm" name="log_date"
                           value="{{ $logDate ?? date('Y-m-d') }}">
                </div>
                <button type="submit" class="btn btn-sm btn-outline-primary mt-auto">
                    <i class="fa fa-search me-1"></i>{{ localize('filter', 'ត្រង') }}
                </button>
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:44px">#</th>
                            <th>{{ localize('employee', 'ឈ្មោះបុគ្គលិក') }}</th>
                            <th>{{ localize('workplace', 'អង្គភាព') }}</th>
                            <th>{{ localize('scan_time', 'ម៉ោងស្កេន') }}</th>
                            <th>{{ localize('punch_type', 'ប្រភេទ') }}</th>
                            <th>{{ localize('distance_m', 'ចំងាយ') }}</th>
                            <th>{{ localize('allowed_range', 'ត្រូវ') }}</th>
                            <th>{{ localize('status', 'ស្ថានភាព') }}</th>
                            <th>{{ localize('error_detail', 'លម្អិត') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($scanLogs ?? [] as $i => $log)
                            @php
                                $dist    = $log->range_meters;
                                $allowed = $log->acceptable_range_meters;
                                $isOver  = $dist !== null && $allowed !== null && $dist > $allowed;
                                $state   = $log->machine_state ?? '';
                                $status  = $log->status ?? '';
                            @endphp
                            <tr>
                                <td class="text-muted small">{{ $i + 1 }}</td>

                                <td class="fw-semibold">
                                    {{ $log->employee?->full_name ?? '-' }}
                                    @if ($log->employee?->employee_id ?? null)
                                        <div class="small text-muted">{{ $log->employee->employee_id }}</div>
                                    @endif
                                </td>

                                <td class="small">{{ $log->workplace?->department_name ?? '-' }}</td>

                                <td class="small">
                                    {{ \Carbon\Carbon::parse($log->created_at)->format('H:i:s') }}
                                </td>

                                <td>
                                    @if ($state === 'in')
                                        <span class="badge punch-in">
                                            <i class="fa fa-sign-in-alt me-1"></i>{{ localize('check_in', 'ចូល') }}
                                        </span>
                                    @elseif ($state === 'out')
                                        <span class="badge punch-out">
                                            <i class="fa fa-sign-out-alt me-1"></i>{{ localize('check_out', 'ចេញ') }}
                                        </span>
                                    @else
                                        <span class="badge badge-secondary-soft">{{ $state ?: '-' }}</span>
                                    @endif
                                </td>

                                <td class="{{ $isOver ? 'text-danger fw-semibold' : '' }} small">
                                    {{ $dist !== null ? number_format($dist, 0) . ' m' : '-' }}
                                    @if ($isOver)
                                        <i class="fa fa-exclamation-triangle ms-1" title="{{ localize('out_of_range', 'ហួសចម្ងាយ') }}"></i>
                                    @endif
                                </td>

                                <td class="small text-muted">
                                    {{ $allowed !== null ? number_format($allowed, 0) . ' m' : '-' }}
                                </td>

                                <td>
                                    @if ($status === 'accepted')
                                        <span class="badge badge-success-soft">
                                            <i class="fa fa-check me-1"></i>{{ localize('accepted', 'បានទទួល') }}
                                        </span>
                                    @elseif (in_array($status, ['error', 'rejected']))
                                        <span class="badge badge-danger-soft">
                                            <i class="fa fa-times me-1"></i>{{ localize('rejected', 'បដិសេធ') }}
                                        </span>
                                    @elseif ($status === 'client_error')
                                        <span class="badge badge-warning-soft">
                                            <i class="fa fa-mobile-alt me-1"></i>{{ localize('client_error', 'App Error') }}
                                        </span>
                                    @else
                                        <span class="badge badge-secondary-soft">{{ $status ?: '-' }}</span>
                                    @endif
                                </td>

                                <td class="text-muted small" style="max-width:200px; white-space:normal;">
                                    @if ($log->error_code)
                                        <code class="small">{{ $log->error_code }}</code><br>
                                    @endif
                                    {{ $log->message ?? '' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    @if (!$selectedWorkplaceId)
                                        <i class="fa fa-arrow-up me-1"></i>
                                        {{ localize('select_workplace_to_view_logs', 'ជ្រើស Workplace ដើម្បីមើលប្រវត្តិ') }}
                                    @else
                                        <i class="fa fa-qrcode me-2"></i>
                                        {{ localize('no_scan_logs', 'មិនមានការស្កេននៅ​ថ្ងៃ​នេះ') }}
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

@endsection

@push('js')
    <script src="{{ module_asset('HumanResource/js/hrcommon.js') }}"></script>
@endpush
