<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('tieu-de') · Lernbuch</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#2d2364">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-paper text-ink antialiased">
    <header class="bg-box text-on-box">
        <div class="mx-auto flex min-h-14 max-w-xl items-center px-6">
            <a href="{{ route('home') }}" class="text-title font-extrabold tracking-tight focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-on-box">Lernbuch</a>
        </div>
    </header>
    <main class="relative mx-auto max-w-xl py-10 pr-6 pl-14 sm:py-14">
        <span aria-hidden="true" class="absolute inset-y-0 left-8 w-[1.5px] bg-pen opacity-45"></span>
        <h1 class="text-headline font-bold">@yield('tieu-de')</h1>
        @if (session('status') && session('status') !== 'verification-link-sent')
            <p role="status" class="mt-4 rounded-md border border-line bg-tim-tint px-4 py-3 text-body">{{ session('status') }}</p>
        @endif
        @yield('noi-dung')
    </main>
</body>
</html>
