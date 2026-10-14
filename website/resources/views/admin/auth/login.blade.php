<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if (config('site.favicon_url'))
        <link rel="icon" href="{{ config('site.favicon_url') }}">
    @endif
    <title>ចូលប្រព័ន្ធគ្រប់គ្រង · {{ config('site.name_km') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-surface text-ink antialiased min-h-screen flex items-center justify-center px-6">
    <div class="w-full max-w-[420px]">
        <div class="flex flex-col items-center gap-2 mb-8">
            <x-organization-logo style="--site-logo-size: 72px" />
            <p class="font-bold text-brand-navy text-[19px] text-center">{{ config('site.name_km') }}</p>
            <p class="text-muted text-[13px]">ប្រព័ន្ធគ្រប់គ្រងគេហទំព័រ</p>
        </div>

        <div class="bg-white border border-line rounded-[12px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-8">
            <h1 class="font-bold text-brand-navy text-[20px] mb-6">ចូលប្រព័ន្ធ</h1>

            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 text-brand-red text-[14px] rounded-[8px] px-4 py-3 mb-4">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login.attempt') }}" class="flex flex-col gap-4">
                @csrf
                <div>
                    <label class="block text-ink text-[14px] font-bold mb-1">អ៊ីមែល</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus
                           class="bg-white border border-line rounded-[8px] h-[46px] px-[14px] text-[14px] text-ink w-full">
                </div>
                <div>
                    <label class="block text-ink text-[14px] font-bold mb-1">ពាក្យសម្ងាត់</label>
                    <input type="password" name="password" required
                           class="bg-white border border-line rounded-[8px] h-[46px] px-[14px] text-[14px] text-ink w-full">
                </div>
                <label class="flex items-center gap-2 text-[13px] text-muted">
                    <input type="checkbox" name="remember" class="rounded border-line">
                    ចងចាំការចូលប្រព័ន្ធ
                </label>
                <button type="submit" class="bg-brand-blue text-white font-bold text-[15px] rounded-[8px] h-[46px] w-full">
                    ចូលប្រព័ន្ធ
                </button>
            </form>
        </div>

        <p class="text-center text-muted text-[12px] mt-6">
            <a href="{{ route('home') }}" class="hover:text-brand-blue">← ត្រឡប់ទៅគេហទំព័រ</a>
        </p>
    </div>
</body>
</html>
