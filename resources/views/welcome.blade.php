<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lernbuch: học tiếng Đức cho người Việt</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#21763c">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-white text-slate-900 antialiased">
    <main class="mx-auto max-w-2xl px-6 py-16">
        <h1 class="text-4xl font-bold">Lernbuch</h1>
        <p class="mt-4 text-lg">Học tiếng Đức cho người Việt, từ A1 đến B2. Trang đang được xây dựng.</p>
        <p class="mt-6" lang="de">Ich möchte zwei Brötchen. Die Straße ist schön. Äpfel, Übung, Größe.</p>
        <p class="mt-2">Chữ có dấu: ắ ằ ẳ ẵ ặ · ế ề ể ễ ệ · ố ồ ổ ỗ ộ · ư ơ đ · Ä Ö Ü ẞ</p>
        <p class="mt-8"><a class="font-semibold underline" href="{{ route('app') }}">Mở app học</a></p>
    </main>
    <footer class="mx-auto max-w-2xl px-6 pb-8 text-sm text-slate-600">Bản {{ config('app.release') }}</footer>
</body>
</html>
