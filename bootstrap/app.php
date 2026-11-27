<?php

use App\Http\Middleware\ConHoatDong;
use App\Http\Middleware\DatNgonNgu;
use App\Http\Middleware\DaXacMinhEmail;
use App\Support\LoiApi;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // App học React cùng tên miền gọi /api bằng cookie phiên (Sanctum); sau này app di động dùng token.
        $middleware->statefulApi();
        // Giới hạn "api" đặt trong AppServiceProvider.
        $middleware->throttleApi();
        // Đổi mật khẩu hoặc "đăng xuất các máy khác" thì phiên trên máy khác hết hiệu lực, kể cả máy đã chọn "Ghi nhớ".
        $middleware->authenticateSessions();
        $middleware->web(append: [DatNgonNgu::class]);
        $middleware->alias(['da-xac-minh' => DaXacMinhEmail::class, 'ngon-ngu' => DatNgonNgu::class, 'con-hoat-dong' => ConHoatDong::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(fn (Throwable $e, Request $request) => LoiApi::tuNgoaiLe($e, $request));
    })->create();
