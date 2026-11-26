<?php

namespace App\Http\Middleware;

use App\Support\LoiApi;
use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** API chỉ cho tài khoản đã xác minh email; chưa thì trả 403 với mã chua_xac_minh để app dẫn tới trang gửi lại email. */
class DaXacMinhEmail
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            return LoiApi::json('chua_xac_minh', 403);
        }

        return $next($request);
    }
}
