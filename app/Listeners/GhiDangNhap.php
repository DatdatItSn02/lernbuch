<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\OtherDeviceLogout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Ghi lịch sử đăng nhập vào login_events (giữ 90 ngày): đăng nhập, đăng xuất, sai, bị chặn tạm, đặt lại mật khẩu, đăng xuất các máy khác. */
class GhiDangNhap
{
    public function handleLogin(Login $event): void
    {
        $this->ghi('login', $event->user);
        DB::table('users')->where('id', $event->user->getAuthIdentifier())->update(['last_login_at' => now()->getTimestamp()]);
    }

    public function handleLogout(Logout $event): void
    {
        $this->ghi('logout', $event->user);
    }

    public function handleFailed(Failed $event): void
    {
        $email = $event->credentials['email'] ?? null;
        $this->ghi('failed', $event->user, is_string($email) ? $email : null);
    }

    public function handleLockout(Lockout $event): void
    {
        $email = $event->request->input('email');
        $this->ghi('locked', null, is_string($email) ? $email : null);
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        $this->ghi('password_reset', $event->user);
    }

    public function handleOtherDeviceLogout(OtherDeviceLogout $event): void
    {
        $this->ghi('logout_others', $event->user);
    }

    private function ghi(string $event, ?Authenticatable $user, ?string $email = null): void
    {
        $ip = request()->ip();

        DB::table('login_events')->insert([
            'user_id' => $user?->getAuthIdentifier(),
            'email' => $user === null && $email !== null ? Str::limit(Str::lower(trim($email)), 191, '') : null,
            'event' => $event,
            'ip' => $ip !== null ? (@inet_pton($ip) ?: null) : null,
            'user_agent' => Str::limit((string) request()->userAgent(), 255, ''),
            'created_at' => now()->getTimestamp(),
        ]);
    }
}
