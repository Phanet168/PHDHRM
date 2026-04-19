@extends('backend.layouts.app')
@section('title', localize('qr_attendance', 'QR Attendance'))

@section('content')
    @include('humanresource::attendance_header')

    <div class="card mb-4 fixed-tab-body">
        @include('backend.layouts.common.validation')
        @include('backend.layouts.common.message')

        <div class="card-header">
            <h6 class="fs-17 fw-semi-bold mb-0">{{ localize('qr_attendance', 'QR Attendance') }}</h6>
        </div>

        <div class="card-body">
            <form action="{{ route('attendances.qrGenerate') }}" method="POST" class="row g-3 align-items-end">
                @csrf
                <div class="col-md-7">
                    <label for="workplace_id" class="form-label fw-semibold">
                        {{ localize('workplace', 'អង្គភាព') }} <span class="text-danger">*</span>
                    </label>
                    <select name="workplace_id" id="workplace_id" class="select-basic-single" required>
                        <option value="">{{ localize('select_one', 'ជ្រើសរើស') }}</option>
                        @foreach ($orgUnitOptions as $option)
                            @php
                                $optionId = data_get($option, 'id');
                                $optionText = data_get($option, 'path') ?? data_get($option, 'label') ?? data_get($option, 'name') ?? ('#' . $optionId);
                            @endphp
                            <option value="{{ $optionId }}" @selected(old('workplace_id', $selectedWorkplaceId ?? null) == $optionId)>
                                {{ $optionText }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="expires_minutes" class="form-label fw-semibold">
                        {{ localize('expires_in_minutes', 'មានសុពលភាព (នាទី)') }}
                    </label>
                    <input type="number" min="1" max="{{ $maxExpiry }}" class="form-control" id="expires_minutes"
                        name="expires_minutes" value="{{ old('expires_minutes', $selectedExpiryMinutes ?? $defaultExpiry) }}">
                </div>

                <div class="col-md-2 text-md-end">
                    <button type="submit" class="btn btn-success w-100">
                        {{ localize('generate_qr', 'បង្កើត QR') }}
                    </button>
                </div>
            </form>

            @if ($generated)
                <hr class="my-4">
                <div class="row g-4">
                    <div class="col-lg-4 text-center">
                        <img src="{{ $generated['qr_image_url'] }}" alt="Attendance QR"
                            class="img-fluid border rounded p-2 bg-white" style="max-width: 320px;">
                        <p class="text-muted mt-2 small">
                            <i class="fa fa-clock me-1"></i>{{ localize('valid_until', 'មានសុពលភាពដល់') }}:
                            <strong>{{ $generated['expires_at']->format('H:i:s d/m/Y') }}</strong>
                        </p>
                    </div>

                    <div class="col-lg-8">
                        <div class="alert alert-info mb-3">
                            <div><strong>{{ localize('workplace', 'អង្គភាព') }}:</strong> {{ $generated['workplace_name'] ?: '-' }}</div>
                            <div><strong>{{ localize('valid_until', 'មានសុពលភាពដល់') }}:</strong>
                                {{ $generated['expires_at']->format('Y-m-d H:i:s') }}</div>
                        </div>

                        <label class="form-label fw-semibold">{{ localize('qr_payload', 'QR Payload') }}</label>
                        <textarea class="form-control mb-3" rows="3" readonly>{{ $generated['payload_json'] }}</textarea>

                        <label class="form-label fw-semibold">{{ localize('qr_token', 'QR Token') }}</label>
                        <textarea class="form-control" rows="3" readonly>{{ $generated['token'] }}</textarea>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- QR Scan Log Section --}}
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="fs-17 fw-semi-bold mb-0">
                <i class="fa fa-list-alt me-1"></i>{{ localize('qr_scan_log', 'កំណត់ហេតុការស្កេន QR') }}
            </h6>
            <div class="d-flex gap-2 align-items-end">
                <form id="scanLogFilterForm" action="{{ route('attendances.qrCreate') }}" method="GET" class="d-flex gap-2 align-items-end mb-0">
                    @if($selectedWorkplaceId)
                        <input type="hidden" name="workplace_id" value="{{ $selectedWorkplaceId }}">
                    @endif
                    <div>
                        <label class="form-label fw-semibold small mb-1">{{ localize('date', 'ថ្ងៃ') }}</label>
                        <input type="date" class="form-control form-control-sm" name="log_date"
                            value="{{ request('log_date', date('Y-m-d')) }}">
                    </div>
                    <button type="submit" class="btn btn-sm btn-outline-primary mt-auto">
                        <i class="fa fa-search me-1"></i>{{ localize('search', 'ស្វែងរក') }}
                    </button>
                </form>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0" id="scanLogTable">
                    <thead class="table-light">
                        <tr>
                            <th>{{ localize('sl', 'លរ') }}</th>
                            <th>{{ localize('employee', 'ឈ្មោះបុគ្គលិក') }}</th>
                            <th>{{ localize('workplace', 'អង្គភាព') }}</th>
                            <th>{{ localize('scan_time', 'ម៉ោងស្កេន') }}</th>
                            <th>{{ localize('punch_type', 'ប្រភេទ') }}</th>
                            <th>{{ localize('distance_m', 'ចំងាយ (m)') }}</th>
                            <th>{{ localize('status', 'ស្ថានភាព') }}</th>
                            <th>{{ localize('reject_reason', 'មូលហេតុ') }}</th>
                        </tr>
                    </thead>
                    <tbody id="scanLogBody">
                        @forelse($scanLogs ?? [] as $i => $log)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td class="fw-semibold">{{ $log->employee?->full_name ?? '-' }}</td>
                                <td>{{ $log->workplace?->department_name ?? '-' }}</td>
                                <td>{{ \Carbon\Carbon::parse($log->created_at)->format('H:i:s') }}</td>
                                <td>
                                    @if(($log->machine_state ?? '') === 'in')
                                        <span class="badge badge-success-soft"><i class="fa fa-sign-in-alt me-1"></i>{{ localize('check_in', 'ចូល') }}</span>
                                    @elseif(($log->machine_state ?? '') === 'out')
                                        <span class="badge badge-danger-soft"><i class="fa fa-sign-out-alt me-1"></i>{{ localize('check_out', 'ចេញ') }}</span>
                                    @else
                                        <span class="badge badge-secondary-soft">{{ $log->machine_state ?? '-' }}</span>
                                    @endif
                                </td>
                                <td>{{ $log->range_meters !== null ? number_format($log->range_meters, 0) . 'm' : '-' }}</td>
                                <td>
                                    @if(($log->status ?? '') === 'accepted')
                                        <span class="badge badge-success-soft"><i class="fa fa-check me-1"></i>{{ localize('accepted', 'បានទទួល') }}</span>
                                    @else
                                        <span class="badge badge-danger-soft"><i class="fa fa-times me-1"></i>{{ localize('rejected', 'បដិសេធ') }}</span>
                                    @endif
                                </td>
                                <td class="text-muted small">{{ $log->reject_reason ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="fa fa-qrcode me-2"></i>{{ localize('no_scan_logs', 'មិនមានកំណត់ហេតុការស្កេនទេ') }}
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
