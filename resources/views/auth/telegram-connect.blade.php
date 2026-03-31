@extends('login.app')
@section('title', localize('telegram_connect', 'Connect Telegram'))

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
                        <h3 class="fs-24 fw-bold mb-1">{{ localize('telegram_connect_title', 'Connect Telegram for OTP') }}</h3>
                        <p class="fw--semi-bold text-center fs-14 mb-0">
                            {{ localize('telegram_connect_subtitle', 'For secure login, link your Telegram account once.') }}
                        </p>
                    </div>

                    @if (session('warning'))
                        <div class="alert alert-warning text-start py-2">{{ session('warning') }}</div>
                    @endif

                    @if ($errors->has('telegram'))
                        <div class="alert alert-danger text-start py-2">{{ $errors->first('telegram') }}</div>
                    @endif

                    @if (!$isConfigured)
                        <div class="alert alert-danger text-start">
                            {{ localize('telegram_not_configured', 'Telegram bot is not configured. Please contact administrator.') }}
                        </div>
                    @endif

                    @if (!empty($user?->telegram_chat_id))
                        <div class="alert alert-success text-start py-2">
                            {{ localize('telegram_already_connected', 'Telegram is already connected for this account.') }}
                        </div>
                        <a href="{{ route('security.otp.show') }}" class="btn btn-success py-2 w-100 mb-2">
                            {{ localize('continue_to_otp', 'Continue to OTP') }}
                        </a>
                    @else
                        <ol class="text-start small ps-3 mb-3">
                            <li>{{ localize('telegram_step_open_bot', 'Open Telegram bot link below.') }}</li>
                            <li>{{ localize('telegram_step_press_start', 'Press Start in the bot chat.') }}</li>
                            <li>{{ localize('telegram_step_sync', 'Come back and click Sync Telegram.') }}</li>
                        </ol>

                        @if (!empty($telegramLink))
                            <a href="{{ $telegramLink }}" target="_blank" class="btn btn-primary py-2 w-100 mb-2">
                                {{ localize('open_telegram_bot', 'Open Telegram Bot') }}
                            </a>
                        @endif

                        <form method="POST" action="{{ route('security.telegram.connect.sync') }}" class="mb-2">
                            @csrf
                            <button type="submit" class="btn btn-success py-2 w-100" @if (!$isConfigured) disabled @endif>
                                {{ localize('sync_telegram', 'Sync Telegram') }}
                            </button>
                        </form>

                        <form method="POST" action="{{ route('security.telegram.connect.regenerate') }}" class="mb-2">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary py-2 w-100" @if (!$isConfigured) disabled @endif>
                                {{ localize('regenerate_link', 'Regenerate Link') }}
                            </button>
                        </form>
                    @endif

                    @if (!empty($botUsername))
                        <div class="text-muted small text-start mb-2">
                            {{ localize('telegram_bot_username', 'Bot') }}: <strong>{{ '@' . ltrim($botUsername, '@') }}</strong>
                        </div>
                    @endif

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

