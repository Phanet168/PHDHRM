@extends('login.app')
@section('title', 'កំណត់ពាក្យសម្ងាត់ឡើងវិញ')

@push('css')
    <style>
        .auth-shell {
            min-height: 100vh;
            background-image:
                linear-gradient(140deg, rgba(13, 45, 78, 0.74), rgba(15, 93, 74, 0.52)),
                var(--auth-bg-image);
            background-size: cover;
            background-position: center;
            padding: 32px 16px;
        }

        .reset-card {
            width: min(980px, 100%);
            border-radius: 28px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.16);
            box-shadow: 0 30px 80px rgba(9, 27, 45, 0.24);
            backdrop-filter: blur(10px);
        }

        .reset-hero {
            background:
                radial-gradient(circle at top right, rgba(255, 255, 255, 0.14), transparent 54%),
                linear-gradient(165deg, #123b61 0%, #16614d 100%);
            color: #fff;
            padding: 42px 34px;
            height: 100%;
        }

        .reset-hero__chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.12);
        }

        .reset-hero__title {
            font-size: clamp(1.8rem, 3vw, 2.35rem);
            font-weight: 700;
            line-height: 1.2;
            margin: 18px 0 12px;
        }

        .reset-hero__text {
            color: rgba(255, 255, 255, 0.84);
            max-width: 32rem;
        }

        .reset-note-box {
            margin-top: 28px;
            padding: 18px 18px 16px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.12);
        }

        .reset-note-box ul {
            margin: 12px 0 0;
            padding-left: 18px;
        }

        .reset-form-pane {
            padding: 40px 34px;
        }

        .reset-form-pane__logo {
            max-width: min(180px, 58vw);
            max-height: 88px;
            object-fit: contain;
        }

        .reset-panel {
            border: 1px solid #e5edf3;
            border-radius: 22px;
            padding: 24px;
            background: #fff;
        }

        .reset-label {
            font-weight: 600;
            color: #173450;
            margin-bottom: 8px;
        }

        .reset-input.form-control {
            min-height: 52px;
            border-radius: 14px;
            border-color: #d8e2ea;
            box-shadow: none;
            padding-inline: 16px;
        }

        .reset-input.form-control:focus {
            border-color: #1f7a62;
            box-shadow: 0 0 0 0.2rem rgba(31, 122, 98, 0.12);
        }

        .reset-password-wrap {
            position: relative;
        }

        .reset-toggle {
            position: absolute;
            top: 50%;
            right: 14px;
            transform: translateY(-50%);
            border: 0;
            background: transparent;
            color: #5f7080;
            padding: 4px;
        }

        .reset-btn {
            min-height: 52px;
            border-radius: 14px;
            font-weight: 600;
        }

        .reset-footnote {
            color: #607080;
            font-size: 0.95rem;
        }

        .reset-link {
            color: #0f5d4a;
            text-decoration: none;
            font-weight: 600;
        }

        .reset-link:hover {
            color: #0b4c3b;
        }

        @media (max-width: 991.98px) {
            .reset-hero,
            .reset-form-pane {
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
    @endphp

    <div class="auth-shell d-flex align-items-center justify-content-center"
        style="--auth-bg-image: url('{{ $appLoginImage }}');">
        <div class="reset-card">
            <div class="row g-0">
                <div class="col-lg-5">
                    <div class="reset-hero">
                        <div class="reset-hero__chip">
                            <i class="fas fa-key"></i>
                            <span>ពាក្យសម្ងាត់ថ្មី</span>
                        </div>
                        <h1 class="reset-hero__title">
                            កំណត់ពាក្យសម្ងាត់របស់អ្នកឡើងវិញ
                        </h1>
                        <p class="reset-hero__text">
                            សូមជ្រើសរើសពាក្យសម្ងាត់ថ្មីដែលរឹងមាំ ដើម្បីការពារគណនីរបស់អ្នក មុនចូលប្រើម្ដងទៀត។
                        </p>

                        <div class="reset-note-box">
                            <strong>គន្លឹះសុវត្ថិភាពពាក្យសម្ងាត់</strong>
                            <ul class="mb-0 text-white-50">
                                <li>សូមប្រើយ៉ាងតិច ៨ តួអក្សរ។</li>
                                <li>បើអាច សូមលាយអក្សរធំ អក្សរតូច លេខ ឬសញ្ញាពិសេស។</li>
                                <li>សូមជៀសវាងការប្រើពាក្យសម្ងាត់ចាស់មកវិញ។</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="reset-form-pane h-100 d-flex flex-column justify-content-center">
                        <div class="text-center text-lg-start mb-4">
                            <img src="{{ $appLogo }}" alt="{{ $appTitle }} logo" class="reset-form-pane__logo mb-3">
                            <h3 class="fw-bold mb-2">កំណត់ពាក្យសម្ងាត់ឡើងវិញ</h3>
                            <p class="text-muted mb-0">
                                សូមជ្រើសរើសពាក្យសម្ងាត់ថ្មីដែលរឹងមាំ ដើម្បីការពារគណនីរបស់អ្នក មុនចូលប្រើម្ដងទៀត។
                            </p>
                        </div>

                        <div class="reset-panel">
                            <form method="POST" action="{{ route('password.update') }}">
                                @csrf
                                <input type="hidden" name="token" value="{{ $token }}">

                                <div class="mb-3">
                                    <label for="email" class="reset-label">អាសយដ្ឋានអ៊ីមែល</label>
                                    <input id="email" type="email"
                                        class="form-control reset-input @error('email') is-invalid @enderror"
                                        name="email" value="{{ $email ?? old('email') }}"
                                        placeholder="អាសយដ្ឋានអ៊ីមែល"
                                        required autocomplete="email" autofocus>
                                    @error('email')
                                        <span class="invalid-feedback d-block" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="password" class="reset-label">ពាក្យសម្ងាត់ថ្មី</label>
                                    <div class="reset-password-wrap">
                                        <input id="password" type="password"
                                            class="form-control reset-input @error('password') is-invalid @enderror"
                                            name="password" required
                                            placeholder="ពាក្យសម្ងាត់ថ្មី"
                                            autocomplete="new-password">
                                        <button type="button" class="reset-toggle" data-toggle-password="#password" aria-label="បង្ហាញ ឬលាក់ពាក្យសម្ងាត់">
                                            <i class="far fa-eye"></i>
                                        </button>
                                    </div>
                                    @error('password')
                                        <span class="invalid-feedback d-block" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="password-confirm" class="reset-label">បញ្ជាក់ពាក្យសម្ងាត់</label>
                                    <div class="reset-password-wrap">
                                        <input id="password-confirm" type="password" class="form-control reset-input"
                                            name="password_confirmation" required
                                            placeholder="បញ្ជាក់ពាក្យសម្ងាត់"
                                            autocomplete="new-password">
                                        <button type="button" class="reset-toggle" data-toggle-password="#password-confirm" aria-label="បង្ហាញ ឬលាក់ការបញ្ជាក់ពាក្យសម្ងាត់">
                                            <i class="far fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-success reset-btn w-100">
                                    កំណត់ពាក្យសម្ងាត់ឡើងវិញ
                                </button>
                            </form>
                        </div>

                        <p class="reset-footnote text-center text-lg-start mt-4 mb-0">
                            <a href="{{ route('login') }}" class="reset-link">ត្រឡប់ទៅទំព័រចូលប្រើ</a>
                        </p>
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
