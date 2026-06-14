@extends('login.app')
@section('title', 'កំណត់ពាក្យសម្ងាត់ឡើងវិញ')

@push('css')
    <style>
        .auth-shell {
            min-height: 100vh;
            background-image:
                linear-gradient(135deg, rgba(10, 37, 64, 0.72), rgba(23, 92, 70, 0.55)),
                var(--auth-bg-image);
            background-size: cover;
            background-position: center;
            padding: 32px 16px;
        }

        .auth-card {
            width: min(1080px, 100%);
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 28px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.94);
            box-shadow: 0 32px 80px rgba(8, 26, 46, 0.22);
            backdrop-filter: blur(10px);
        }

        .auth-hero {
            background:
                radial-gradient(circle at top, rgba(255, 255, 255, 0.16), transparent 56%),
                linear-gradient(160deg, #0f3556 0%, #176b52 100%);
            color: #fff;
            padding: 40px 34px;
            height: 100%;
        }

        .auth-hero__badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.12);
            font-size: 0.92rem;
        }

        .auth-hero__title {
            font-size: clamp(1.8rem, 3vw, 2.5rem);
            line-height: 1.2;
            margin: 18px 0 12px;
            font-weight: 700;
        }

        .auth-hero__text {
            color: rgba(255, 255, 255, 0.84);
            font-size: 1rem;
            max-width: 34rem;
        }

        .auth-steps {
            margin: 26px 0 0;
            padding: 0;
            list-style: none;
        }

        .auth-steps li {
            display: flex;
            gap: 14px;
            align-items: flex-start;
            padding: 14px 0;
            border-top: 1px solid rgba(255, 255, 255, 0.14);
        }

        .auth-steps li:first-child {
            border-top: 0;
        }

        .auth-step__num {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.18);
            font-weight: 700;
            flex: 0 0 34px;
        }

        .auth-form-pane {
            padding: 40px 34px;
        }

        .auth-form-pane__logo {
            max-width: min(180px, 58vw);
            max-height: 88px;
            object-fit: contain;
        }

        .auth-form-pane__subtitle {
            color: #5b6b7b;
            margin-bottom: 24px;
        }

        .auth-panel {
            border: 1px solid #e6edf3;
            border-radius: 22px;
            padding: 24px;
            background: #fff;
        }

        .auth-label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #16324a;
        }

        .auth-input.form-control {
            min-height: 52px;
            border-radius: 14px;
            border-color: #d8e2ea;
            padding-inline: 16px;
            box-shadow: none;
        }

        .auth-input.form-control:focus {
            border-color: #1f7a62;
            box-shadow: 0 0 0 0.2rem rgba(31, 122, 98, 0.12);
        }

        .auth-btn {
            min-height: 52px;
            border-radius: 14px;
            font-weight: 600;
        }

        .auth-divider {
            display: flex;
            align-items: center;
            gap: 14px;
            color: #6e7c89;
            font-size: 0.92rem;
            margin: 22px 0;
        }

        .auth-divider::before,
        .auth-divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background: #e2e8ee;
        }

        .auth-footnote,
        .auth-alt-note {
            color: #607080;
            font-size: 0.94rem;
        }

        .auth-link {
            color: #0f5d4a;
            text-decoration: none;
            font-weight: 600;
        }

        .auth-link:hover {
            color: #0c493a;
        }

        @media (max-width: 991.98px) {
            .auth-hero,
            .auth-form-pane {
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
        <div class="auth-card">
            <div class="row g-0">
                <div class="col-lg-5">
                    <div class="auth-hero">
                        <div class="auth-hero__badge">
                            <i class="fas fa-shield-alt"></i>
                            <span>កំណត់ពាក្យសម្ងាត់ឡើងវិញ</span>
                        </div>
                        <h1 class="auth-hero__title">
                            ស្ដារការចូលប្រើគណនីរបស់អ្នកដោយសុវត្ថិភាព
                        </h1>
                        <p class="auth-hero__text">
                            សូមបញ្ចូលអ៊ីមែលរបស់អ្នក ហើយប្រព័ន្ធនឹងផ្ញើតំណភ្ជាប់សុវត្ថិភាពសម្រាប់កំណត់ពាក្យសម្ងាត់ឡើងវិញ។
                        </p>

                        <ul class="auth-steps">
                            <li>
                                <span class="auth-step__num">1</span>
                                <div>
                                    <strong>អាសយដ្ឋានអ៊ីមែល</strong>
                                    <div class="small text-white-50">
                                        សូមប្រើអ៊ីមែលដែលបានភ្ជាប់ជាមួយគណនីរបស់អ្នក។
                                    </div>
                                </div>
                            </li>
                            <li>
                                <span class="auth-step__num">2</span>
                                <div>
                                    <strong>ផ្ញើតំណភ្ជាប់កំណត់ពាក្យសម្ងាត់ឡើងវិញ</strong>
                                    <div class="small text-white-50">
                                        សូមពិនិត្យប្រអប់សារអ៊ីមែលរបស់អ្នក ហើយបើកតំណភ្ជាប់សុវត្ថិភាពដែលយើងបានផ្ញើ។
                                    </div>
                                </div>
                            </li>
                            <li>
                                <span class="auth-step__num">3</span>
                                <div>
                                    <strong>ពាក្យសម្ងាត់ថ្មី</strong>
                                    <div class="small text-white-50">
                                        បង្កើតពាក្យសម្ងាត់ថ្មី ហើយចូលប្រើម្ដងទៀត។
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="auth-form-pane h-100 d-flex flex-column justify-content-center">
                        <div class="text-center text-lg-start mb-4">
                            <img src="{{ $appLogo }}" alt="{{ $appTitle }} logo" class="auth-form-pane__logo mb-3">
                            <h3 class="fw-bold mb-2">កំណត់ពាក្យសម្ងាត់ឡើងវិញ</h3>
                            <p class="auth-form-pane__subtitle mb-0">
                                សូមបញ្ចូលអ៊ីមែលរបស់អ្នក ហើយប្រព័ន្ធនឹងផ្ញើតំណភ្ជាប់សុវត្ថិភាពសម្រាប់កំណត់ពាក្យសម្ងាត់ឡើងវិញ។
                            </p>
                        </div>

                        <div class="auth-panel">
                            @if (session('status'))
                                <div class="alert alert-success rounded-4" role="alert">
                                    {{ session('status') }}
                                </div>
                            @endif

                            <form method="POST" action="{{ route('password.email') }}" class="register-form">
                                @csrf

                                <div class="mb-3">
                                    <label for="email" class="auth-label">អាសយដ្ឋានអ៊ីមែល</label>
                                    <input type="email" name="email"
                                        class="form-control auth-input @error('email') is-invalid @enderror"
                                        id="email" placeholder="អាសយដ្ឋានអ៊ីមែល"
                                        value="{{ old('email') }}" required autocomplete="email" autofocus>
                                    @error('email')
                                        <span class="invalid-feedback d-block" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>

                                <button type="submit" class="btn btn-success auth-btn w-100">
                                    ផ្ញើតំណភ្ជាប់កំណត់ពាក្យសម្ងាត់ឡើងវិញ
                                </button>
                            </form>

                            <div class="auth-divider">ឬ</div>

                            <form method="POST" action="{{ route('password.telegram') }}" class="register-form">
                                @csrf
                                <div class="mb-3">
                                    <label for="login" class="auth-label">អ៊ីមែល ឬឈ្មោះអ្នកប្រើ</label>
                                    <input type="text" name="login"
                                        class="form-control auth-input @error('login') is-invalid @enderror"
                                        id="login"
                                        placeholder="អ៊ីមែល ឬឈ្មោះអ្នកប្រើ"
                                        value="{{ old('login') }}">
                                    @error('login')
                                        <span class="invalid-feedback d-block" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>

                                <button type="submit" class="btn btn-outline-success auth-btn w-100">
                                    ផ្ញើតំណកំណត់ពាក្យសម្ងាត់តាម Telegram
                                </button>
                                <p class="auth-alt-note text-center mt-3 mb-0">
                                    មុខងារនេះដំណើរការបាន លុះត្រាតែគណនីរបស់អ្នកបានភ្ជាប់ជាមួយ Telegram រួចជាមុន។
                                </p>
                            </form>
                        </div>

                        <p class="auth-footnote text-center text-lg-start mt-4 mb-0">
                            ចងចាំពាក្យសម្ងាត់របស់អ្នកបានហើយ?
                            <a class="auth-link" href="{{ route('login') }}">ចូលប្រើ</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
