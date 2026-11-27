<?php

namespace App\Providers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /** Số lần gọi API mỗi phút mặc định cho một người (chưa đăng nhập thì tính theo IP); đổi bằng config app.api_moi_phut. */
    public const API_MOI_PHUT = 120;

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Mật khẩu người học: ít nhất 8 ký tự, có cả chữ và số. Nhân viên tạo bằng app:create-admin cần ít nhất 12.
        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        // Quản trị cao nhất có mọi quyền, kể cả quyền thêm sau này; vai trò khác theo bảng role_has_permissions.
        Gate::before(fn ($user) => $user instanceof User && $user->is_staff && $user->hasRole(Role::CAO_NHAT) ? true : null);

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(max(1, (int) config('app.api_moi_phut', self::API_MOI_PHUT)))
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
    }
}
