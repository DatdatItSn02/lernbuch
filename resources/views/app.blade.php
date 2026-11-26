<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lernbuch</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#2d2364">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <script>
        // Áp giao diện và cỡ chữ đã lưu trước khi vẽ, để người dùng giao diện tối không thấy nền sáng chớp lên
        try {
            var s = JSON.parse(localStorage.getItem('lernbuch.caidat.v1') || '{}'), h = document.documentElement;
            if (s.theme === 'light' || s.theme === 'dark') h.dataset.theme = s.theme;
            if (s.fontSize === 'sm' || s.fontSize === 'md' || s.fontSize === 'lg') h.dataset.fontSize = s.fontSize;
        } catch (e) {}
    </script>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app/main.tsx'])
</head>
<body class="min-h-screen bg-paper text-ink antialiased">
    <div id="app"></div>
    <noscript>Cần bật JavaScript để dùng app học.</noscript>
</body>
</html>
