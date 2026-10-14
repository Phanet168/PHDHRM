@extends('login.app')
@section('title', 'ចូលប្រើ')

@push('css')
    <style>
        .auth-shell {
            min-height: 100vh;
            background-image:
                linear-gradient(140deg, rgba(9, 34, 59, 0.82), rgba(23, 92, 70, 0.62)),
                var(--auth-bg-image);
            background-size: cover;
            background-position: center;
            padding: 32px 16px;
        }

        .login-card {
            width: min(420px, 100%);
            border-radius: 20px;
            background: #fff;
            box-shadow: 0 24px 60px rgba(7, 24, 42, 0.28);
            padding: 40px 32px 32px;
        }

        .login-card__logo {
            max-width: 160px;
            max-height: 84px;
            object-fit: contain;
        }

        .login-card__title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #173450;
            line-height: 1.5;
            margin: 16px 0 4px;
        }

        .login-card__subtitle {
            color: #6b7c8a;
            font-size: 0.95rem;
        }

        .login-label {
            font-weight: 600;
            font-size: 0.92rem;
            color: #173450;
            margin-bottom: 6px;
        }

        .login-input.form-control {
            min-height: 48px;
            border-radius: 12px;
            border-color: #d8e2ea;
            padding-inline: 14px;
            box-shadow: none;
        }

        .login-input.form-control:focus {
            border-color: #1f7a62;
            box-shadow: 0 0 0 0.2rem rgba(31, 122, 98, 0.12);
        }

        .login-password-wrap {
            position: relative;
        }

        .login-password-wrap .login-input.form-control {
            padding-right: 44px;
            background-image: none;
        }

        .login-toggle {
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-50%);
            border: 0;
            background: transparent;
            color: #617282;
            padding: 4px;
        }

        .login-btn {
            min-height: 48px;
            border-radius: 12px;
            font-weight: 600;
        }

        .login-link {
            color: #0f5d4a;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.92rem;
        }

        .login-link:hover {
            color: #0b4b3a;
        }

        @media (max-width: 575.98px) {
            .login-card {
                padding: 32px 22px 24px;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $appSetting = app_setting();
        $appTitle = trim((string) ($appSetting->title ?? config('app.name', 'HRM')));
        $appLogo = trim((string) ($appSetting->logo ?? asset('assets/HRM2.png')));
        $appLoginImage = trim((string) ($appSetting->login_image ?? asset('assets/HRM2.png')));
        $loginSeed = old('login');

        if (!$loginSeed && old('official_id_10')) {
            $loginSeed = old('official_id_10');
        }
    @endphp

    <div class="auth-shell d-flex align-items-center justify-content-center"
        style="--auth-bg-image: url('{{ $appLoginImage }}');">
        <div class="login-card">
            <div class="text-center mb-4">
                <img src="{{ $appLogo }}" class="login-card__logo" alt="{{ $appTitle }} logo">
                <h1 class="login-card__title">{{ $appTitle }}</h1>
                <p class="login-card__subtitle mb-0">សូមចូលប្រើគណនីរបស់អ្នក ដើម្បីបន្ត</p>
            </div>

            @if (session('warning'))
                <div class="alert alert-warning text-start mb-3" role="alert">
                    {{ session('warning') }}
                </div>
            @endif

            <form class="register-form text-start" action="{{ route('login') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label for="login" class="login-label">គណនី</label>
                    <input type="text" name="login" value="{{ $loginSeed }}"
                        class="form-control login-input @error('login') is-invalid @enderror" id="login"
                        placeholder="លេខកូដ ១០ ខ្ទង់ អ៊ីមែល ឬឈ្មោះអ្នកប្រើ" autofocus />
                    @error('login')
                        <span class="text-danger text-start d-block mt-2" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>

                <div class="mb-2">
                    <label for="pass" class="login-label">ពាក្យសម្ងាត់</label>
                    <div class="login-password-wrap">
                        <input type="password" name="password"
                            class="form-control login-input @error('password') is-invalid @enderror" id="pass"
                            placeholder="បញ្ចូលពាក្យសម្ងាត់" />
                        <button type="button" class="login-toggle" data-toggle-password="#pass" aria-label="បង្ហាញ ឬលាក់ពាក្យសម្ងាត់">
                            <i class="far fa-eye"></i>
                        </button>
                    </div>
                    @error('password')
                        <span class="text-danger text-start d-block mt-2" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>

                @if (Route::has('password.request'))
                    <div class="text-end mb-4">
                        <a href="{{ route('password.request') }}" class="login-link">ភ្លេចពាក្យសម្ងាត់?</a>
                    </div>
                @endif

                <button type="submit" class="btn btn-success login-btn w-100">
                    ចូលប្រើ
                </button>
            </form>
        </div>
    </div>
@endsection

@push('js')
    <script>
        (function() {
            "use strict";

            document.querySelectorAll('[data-toggle-password]').forEach(function(button) {
                button.addEventListener('click', function() {
                    var selector = button.getAttribute('data-toggle-password');
                    var input = selector ? document.querySelector(selector) : null;
                    var icon = button.querySelector('i');

                    if (!input) {
                        return;
                    }

                    var show = input.type === 'password';
                    input.type = show ? 'text' : 'password';

                    if (icon) {
                        icon.classList.toggle('fa-eye', !show);
                        icon.classList.toggle('fa-eye-slash', show);
                    }
                });
            });
        })();
    </script>
@endpush
