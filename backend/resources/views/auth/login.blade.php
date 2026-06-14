@extends('login.app')
@section('title', 'ចូលប្រើ')

@push('css')
    <style>
        .auth-shell {
            min-height: 100vh;
            background-image:
                linear-gradient(140deg, rgba(9, 34, 59, 0.76), rgba(23, 92, 70, 0.48)),
                var(--auth-bg-image);
            background-size: cover;
            background-position: center;
            padding: 32px 16px;
        }

        .login-card {
            width: min(1040px, 100%);
            border-radius: 28px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.18);
            box-shadow: 0 34px 90px rgba(7, 24, 42, 0.24);
            backdrop-filter: blur(10px);
        }

        .login-hero {
            background:
                radial-gradient(circle at top right, rgba(255, 255, 255, 0.15), transparent 52%),
                linear-gradient(160deg, #0f3556 0%, #176b52 100%);
            color: #fff;
            padding: 42px 34px;
            height: 100%;
        }

        .login-hero__badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.12);
            font-size: 0.92rem;
        }

        .login-hero__title {
            font-size: clamp(1.9rem, 3vw, 2.5rem);
            line-height: 1.18;
            font-weight: 700;
            margin: 18px 0 12px;
        }

        .login-hero__text {
            color: rgba(255, 255, 255, 0.84);
            max-width: 34rem;
        }

        .login-highlights {
            margin: 28px 0 0;
            padding: 0;
            list-style: none;
        }

        .login-highlights li {
            display: flex;
            gap: 14px;
            align-items: flex-start;
            padding: 14px 0;
            border-top: 1px solid rgba(255, 255, 255, 0.14);
        }

        .login-highlights li:first-child {
            border-top: 0;
        }

        .login-highlight__icon {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.16);
            flex: 0 0 38px;
        }

        .login-form-pane {
            padding: 40px 34px;
        }

        .login-form-pane__logo {
            max-width: min(180px, 58vw);
            max-height: 90px;
            object-fit: contain;
        }

        .login-form-pane__subtitle {
            color: #5a6c7b;
        }

        .login-panel {
            border: 1px solid #e5edf3;
            border-radius: 22px;
            padding: 24px;
            background: #fff;
        }

        .login-label {
            font-weight: 600;
            color: #173450;
            margin-bottom: 8px;
        }

        .login-input.form-control {
            min-height: 52px;
            border-radius: 14px;
            border-color: #d8e2ea;
            padding-inline: 16px;
            box-shadow: none;
        }

        .login-input.form-control:focus {
            border-color: #1f7a62;
            box-shadow: 0 0 0 0.2rem rgba(31, 122, 98, 0.12);
        }

        .login-password-wrap {
            position: relative;
        }

        .login-toggle {
            position: absolute;
            top: 50%;
            right: 14px;
            transform: translateY(-50%);
            border: 0;
            background: transparent;
            color: #617282;
            padding: 4px;
        }

        .login-btn {
            min-height: 52px;
            border-radius: 14px;
            font-weight: 600;
        }

        .login-links {
            color: #607080;
            font-size: 0.95rem;
        }

        .login-link {
            color: #0f5d4a;
            text-decoration: none;
            font-weight: 600;
        }

        .login-link:hover {
            color: #0b4b3a;
        }

        @media (max-width: 991.98px) {
            .login-hero,
            .login-form-pane {
                padding: 28px 22px;
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
            <div class="row g-0">
                <div class="col-lg-5">
                    <div class="login-hero">
                        <div class="login-hero__badge">
                            <i class="fas fa-user-shield"></i>
                            <span>ចូលប្រើ</span>
                        </div>
                        <h1 class="login-hero__title">{{ $appTitle }}</h1>
                        <p class="login-hero__text">
                            អ្នកអាចចូលប្រើដោយប្រើលេខកូដ ១០ ខ្ទង់ អ៊ីមែល ឬឈ្មោះអ្នកប្រើ។
                        </p>

                        <ul class="login-highlights">
                            <li>
                                <span class="login-highlight__icon"><i class="fas fa-id-card"></i></span>
                                <div>
                                    <strong>លេខកូដ ១០ ខ្ទង់ អ៊ីមែល ឬឈ្មោះអ្នកប្រើ</strong>
                                    <div class="small text-white-50">
                                        អ្នកអាចប្រើព័ត៌មានគណនីណាមួយដែលងាយស្រួលសម្រាប់អ្នកបំផុត។
                                    </div>
                                </div>
                            </li>
                            <li>
                                <span class="login-highlight__icon"><i class="fas fa-lock"></i></span>
                                <div>
                                    <strong>ពាក្យសម្ងាត់</strong>
                                    <div class="small text-white-50">
                                        ការចូលប្រើរបស់អ្នកត្រូវបានការពារដោយពាក្យសម្ងាត់គណនី។
                                    </div>
                                </div>
                            </li>
                            <li>
                                <span class="login-highlight__icon"><i class="fas fa-key"></i></span>
                                <div>
                                    <strong>ភ្លេចពាក្យសម្ងាត់?</strong>
                                    <div class="small text-white-50">
                                        អ្នកអាចស្នើសុំតំណភ្ជាប់កំណត់ពាក្យសម្ងាត់ឡើងវិញបានគ្រប់ពេល បើមិនអាចចូលគណនីបាន។
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="login-form-pane h-100 d-flex flex-column justify-content-center">
                        <div class="text-center text-lg-start mb-4">
                            <img src="{{ $appLogo }}" class="login-form-pane__logo mb-3" alt="{{ $appTitle }} logo">
                            <h3 class="fw-bold mb-2">ចូលប្រើគណនី</h3>
                            <p class="login-form-pane__subtitle mb-0">
                                សូមបញ្ចូលព័ត៌មានគណនីរបស់អ្នក ដើម្បីបន្ត។
                            </p>
                        </div>

                        <div class="login-panel">
                            @if (session('warning'))
                                <div class="alert alert-warning text-start mb-3" role="alert">
                                    {{ session('warning') }}
                                </div>
                            @endif

                            <form class="register-form text-start" action="{{ route('login') }}" method="POST">
                                @csrf
                                <div class="mb-3">
                                    <label for="login" class="login-label">
                                        លេខកូដ ១០ ខ្ទង់ អ៊ីមែល ឬឈ្មោះអ្នកប្រើ
                                    </label>
                                    <input type="text" name="login" value="{{ $loginSeed }}"
                                        class="form-control login-input @error('login') is-invalid @enderror" id="login"
                                        placeholder="សូមបញ្ចូលលេខកូដ ១០ ខ្ទង់ អ៊ីមែល ឬឈ្មោះអ្នកប្រើ" />
                                    @error('login')
                                        <span class="text-danger text-start d-block mt-2" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="pass" class="login-label">ពាក្យសម្ងាត់</label>
                                    <div class="login-password-wrap">
                                        <input type="password" name="password"
                                            class="form-control login-input @error('password') is-invalid @enderror" id="pass"
                                            placeholder="សូមបញ្ចូលពាក្យសម្ងាត់" />
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

                                <div class="d-flex justify-content-between align-items-center mb-3 login-links">
                                    <span>ការចូលប្រើមានសុវត្ថិភាព</span>
                                    @if (Route::has('password.request'))
                                        <a href="{{ route('password.request') }}"
                                            class="login-link">ភ្លេចពាក្យសម្ងាត់?</a>
                                    @endif
                                </div>

                                <button type="submit" class="btn btn-success login-btn w-100">
                                    ចូលប្រើ
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
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
