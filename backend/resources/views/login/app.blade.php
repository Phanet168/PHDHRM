<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <!-- Required meta tags -->
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Sales ERP Software">
    <meta name="author" content="Bdtask">
    @php
        $appSetting = app_setting();
        $isKhmerUi = app()->getLocale() === 'km' || (string) ($appSetting->lang?->value ?? '') === 'km';
    @endphp
    <title>@yield('title')</title>

    <!-- App favicon -->
    <link rel="shortcut icon" class="favicon_show" href="{{ $appSetting->favicon }}">

    @if ($isKhmerUi)
        <style>
            :root {
                --khmer-font-family: "Khmer OS Battambang", "Khmer OS Siemreap", "Khmer OS", "Leelawadee UI", sans-serif;
            }

            body,
            .panel,
            .form-control,
            .btn,
            .card,
            .login-form-w,
            .register-form {
                font-family: var(--khmer-font-family) !important;
                letter-spacing: 0;
                line-height: 1.45;
            }
        </style>
    @endif

    @stack('css')
    @include('login.assets.css')
</head>

<body class="bg-white">

    @yield('content')

    @include('login.assets.js')
    @stack('js')
</body>

</html>
