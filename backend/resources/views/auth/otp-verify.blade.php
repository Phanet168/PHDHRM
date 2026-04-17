@extends('login.app')
@section('title', localize('otp_verification', 'OTP Verification'))

@section('content')
    @php
        $appSetting = app_setting();
    @endphp

    <div class="d-flex align-items-center justify-content-center text-center login-bg h-100vh"
        style="background-image: url({{ $appSetting->login_image }});">
        <div class="form-wrapper position-relative m-auto">
            <div class="form-container my-4">
                <div class="panel login-form-w">
                    <div class="panel-header text-center mb-3">
                        <div class="mb-3">
                            <img src="{{ $appSetting->logo }}" class="rounded-circle" width="90" height="90" alt="">
                        </div>
                        <h3 class="fs-24 fw-bold mb-1">{{ localize('otp_verification', 'OTP Verification') }}</h3>
                        <p class="fw--semi-bold text-center fs-14 mb-0">
                            {{ localize('otp_sent_to_target', 'A verification code has been sent to your') }}
                            {{ $targetLabel ?? localize('phone', 'Phone') }}:
                            <strong>{{ $maskedTarget ?? '' }}</strong>
                        </p>
                    </div>

                    @if (!empty($debugOtp))
                        <div class="alert alert-info text-start py-2 mb-3">
                            DEV OTP: <strong>{{ $debugOtp }}</strong>
                        </div>
                    @endif

                    <form class="register-form text-start" action="{{ route('security.otp.verify') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label for="otp_code" class="form-label fw-semi-bold">
                                {{ localize('otp_code', 'OTP Code') }}
                            </label>
                            <input type="text" name="otp_code" value="{{ old('otp_code') }}"
                                class="form-control @error('otp_code') is-invalid @enderror" id="otp_code"
                                maxlength="6" autocomplete="one-time-code"
                                placeholder="{{ localize('enter_otp_code', 'Enter 6-digit OTP code') }}" />
                            @error('otp_code')
                                <span class="text-danger text-start d-block mt-1" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-success py-2 w-100 mb-2">
                            {{ localize('verify_and_continue', 'Verify & Continue') }}
                        </button>
                    </form>

                    <form method="POST" action="{{ route('security.otp.resend') }}" class="mb-2">
                        @csrf
                        <button type="submit" class="btn btn-outline-primary py-2 w-100">
                            {{ localize('resend_code', 'Resend code') }}
                        </button>
                    </form>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary py-2 w-100">
                            {{ localize('logout', 'Logout') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection


