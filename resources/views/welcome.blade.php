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
<body class="min-h-screen bg-paper text-ink antialiased">
    <main class="mx-auto max-w-2xl px-6 pt-16 pb-12">
        <h1 class="font-display text-display font-bold">Lernbuch</h1>
        <p class="mt-4 text-title font-semibold">Học tiếng Đức cho người Việt, từ A1 đến B2. Trang đang được xây dựng.</p>
        <p class="mt-6 text-ink-2" lang="de">Ich möchte zwei Brötchen. Die Straße ist schön. Äpfel, Übung, Größe.</p>
        <p class="mt-2 text-ink-2">Chữ có dấu: ắ ằ ẳ ẵ ặ · ế ề ể ễ ệ · ố ồ ổ ỗ ộ · ư ơ đ · Ä Ö Ü ẞ</p>
        <p class="mt-10">
            <a class="inline-flex min-h-11 items-center rounded-md bg-leaf px-5 text-label font-semibold text-on-leaf transition-colors duration-200 hover:bg-leaf-strong" href="{{ route('app') }}">Mở app học</a>
        </p>
    </main>
    <footer class="mx-auto max-w-2xl px-6 pb-8 text-label text-ink-2">Bản {{ config('app.release') }}</footer>
</body>
</html>
