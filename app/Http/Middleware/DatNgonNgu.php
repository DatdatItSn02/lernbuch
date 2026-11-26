<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\NgonNgu;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/** Đặt ngôn ngữ của trang, email và thông báo lỗi theo ngôn ngữ giao diện của người đang đăng nhập (users.ui_locale). */
class DatNgonNgu
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        App::setLocale(NgonNgu::chon($user instanceof User ? $user->ui_locale : null));

        return $next($request);
    }
}
