<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\LoiApi;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tài khoản bị khoá (users.status khác active) đang còn phiên thì đăng xuất ngay: API trả 403 tai_khoan_bi_khoa
 * (app đưa về trang đăng nhập), trang web về /dang-nhap kèm lời báo bị khoá.
 */
class ConHoatDong
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->status === 'active') {
            return $next($request);
        }

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            // Trang đăng nhập (app chuyển tới sau lỗi API, hoặc trang web chuyển thẳng) hiện lời báo bị khoá ở ô email
            $request->session()->flash('errors', (new ViewErrorBag)->put('default', new MessageBag(['email' => [__('auth.bi_khoa')]])));
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            return LoiApi::json('tai_khoan_bi_khoa', 403);
        }

        return redirect('/dang-nhap');
    }
}
