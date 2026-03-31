@extends('login.app')
@section('title', localize('phone_security_setup', 'Security Setup'))

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
                        <h3 class="fs-24 fw-bold mb-1">{{ localize('phone_required_for_login', 'Phone number is required') }}</h3>
                        <p class="fw--semi-bold text-center fs-14 mb-0">
                            {{ localize('phone_required_for_login_subtitle', 'For account security, please add your mobile number before continuing.') }}
                        </p>
                    </div>

                    @if (session('warning'))
                        <div class="alert alert-warning text-start py-2">{{ session('warning') }}</div>
                    @endif

                    <form class="register-form text-start" action="{{ route('security.phone.required.update') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label for="contact_no" class="form-label fw-semi-bold">
                                {{ localize('contact_no', 'Mobile') }}
                            </label>
                            <input type="text" name="contact_no" value="{{ old('contact_no', optional($user)->contact_no) }}"
                                class="form-control @error('contact_no') is-invalid @enderror" id="contact_no"
                                placeholder="{{ localize('enter_mobile_number', 'Enter mobile number') }}" />
                            @error('contact_no')
                                <span class="text-danger text-start d-block mt-1" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-success py-2 w-100 mb-2">
                            {{ localize('save_and_continue', 'Save & Continue') }}
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


