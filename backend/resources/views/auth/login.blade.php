@extends('login.app')
@section('title', localize('login'))
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
                        <div class="login-brand mb-3">
                            <img src="{{ $appSetting->logo }}" class="login-brand__logo"
                                alt="{{ $appSetting->title }} logo">
                        </div>
                        <h3 class="fs-24 fw-bold mb-1">{{ $appSetting->title }} {{ localize('login') }}</h3>
                        <p class="fw--semi-bold text-center fs-14 mb-0">{{ localize('welcome_back') }}, {{ $appSetting->title }}</p>
                    </div>


                    <form class="register-form text-start" action="{{ route('login') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label for="login" class="form-label fw-semi-bold">{{ localize('email_or_username') }}</label>
                            <input type="text" name="login" value="{{ old('login') }}"
                                class="form-control @error('login') is-invalid @enderror" id="login"
                                placeholder="{{ localize('enter_email_or_username') }}" />
                            <input type="hidden" name="email" id="email_fallback" value="{{ old('login') }}">

                            @error('login')
                                <span class="text-danger text-start" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="pass" class="form-label fw-semi-bold">{{ localize('password') }}</label>
                            <input type="password" name="password"
                                class="form-control @error('password') is-invalid @enderror" id="pass"
                                placeholder="{{ localize('enter_your_password') }}" />
                            @error('password')
                                <span class="text-danger text-start" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="form-check mb-3 text-end">
                            @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}"
                                    class="text-black fw-medium">{{ localize('forgot_password') }}</a>
                            @endif
                        </div>
                        <button type="submit" class="btn btn-success py-2 w-100">{{ localize('sign_in') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('js')
    <script>
        (function() {
            "use strict";

            const loginInput = document.getElementById('login');
            const emailFallback = document.getElementById('email_fallback');
            const form = document.querySelector('form.register-form');

            const syncFallback = function() {
                if (emailFallback && loginInput) {
                    emailFallback.value = loginInput.value || '';
                }
            };

            if (loginInput) {
                loginInput.addEventListener('input', syncFallback);
                syncFallback();
            }

            if (form) {
                form.addEventListener('submit', syncFallback);
            }
        })();
    </script>
@endpush
