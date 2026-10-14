@props(['title' => null])
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if (config('site.favicon_url'))
        <link rel="icon" href="{{ config('site.favicon_url') }}">
    @endif
    <title>{{ $title ? $title . ' · ប្រព័ន្ធគ្រប់គ្រង' : 'ប្រព័ន្ធគ្រប់គ្រង' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-surface text-ink antialiased">
    <div class="flex min-h-screen">
        <x-admin.sidebar />

        <div class="flex-1 flex flex-col min-w-0">
            <x-admin.topbar :title="$title" />

            <main class="flex-1 p-6 md:p-8">
                @if (session('status'))
                    <div class="bg-brand-green-tint text-brand-green text-[14px] font-bold rounded-[8px] px-4 py-3 mb-5">
                        {{ session('status') }}
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
