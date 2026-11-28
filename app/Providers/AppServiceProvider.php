<?php

namespace App\Providers;

use App\Http\Middleware\KhachXemThu;
use App\Models\Role;
use App\Models\User;
use App\Support\Ai\BoNoi\BoNoiGia;
use App\Support\Ai\DanhSachThu;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Pennant\Feature;

class AppServiceProvider extends ServiceProvider
{
    /** Số lần gọi API mỗi phút mặc định cho một người (chưa đăng nhập thì tính theo IP); đổi bằng config app.api_moi_phut. */
    public const API_MOI_PHUT = 120;

    public function register(): void
    {
        // AI giả: một bản cho mỗi app (mỗi test, mỗi request), để kịch bản của test này không lọt sang test khác.
        $this->app->singleton(BoNoiGia::class);
    }

    public function boot(): void
    {
        // Mật khẩu người học: ít nhất 8 ký tự, có cả chữ và số. Nhân viên tạo bằng app:create-admin cần ít nhất 12.
        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        // Quản trị cao nhất có mọi quyền, kể cả quyền thêm sau này; vai trò khác theo bảng role_has_permissions.
        Gate::before(fn ($user) => $user instanceof User && $user->is_staff && $user->hasRole(Role::CAO_NHAT) ? true : null);

        // Danh sách thử AI: mặc định không ai trong danh sách; quản trị bật cho từng người ở trang xem người học.
        Feature::define(DanhSachThu::TEN, fn (User $user) => false);

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(max(1, (int) config('app.api_moi_phut', self::API_MOI_PHUT)))
            // Khách xem thử dùng chung một tài khoản: tính theo IP để người này không làm người kia hết lượt
            ->by(KhachXemThu::la($request->user()) ? 'khach:'.$request->ip() : ($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
