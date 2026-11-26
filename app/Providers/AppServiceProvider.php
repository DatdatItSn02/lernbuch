<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Mật khẩu người học: ít nhất 8 ký tự, có cả chữ và số. Nhân viên tạo bằng app:create-admin cần ít nhất 12.
        Password::defaults(fn () => Password::min(8)->letters()->numbers());
    }
}
