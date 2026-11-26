<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /** Số lần gọi API mỗi phút cho một người (chưa đăng nhập thì tính theo IP). App gửi lượt chấm theo lô nên dư nhiều. */
    public const API_MOI_PHUT = 120;

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Mật khẩu người học: ít nhất 8 ký tự, có cả chữ và số. Nhân viên tạo bằng app:create-admin cần ít nhất 12.
        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(self::API_MOI_PHUT)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
    }
}
