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
@endsection

@push('js')
    <script src="{{ module_asset('HumanResource/js/hrcommon.js') }}"></script>
@endpush
