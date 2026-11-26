<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Tài khoản người học thử cho test trình duyệt (Playwright) và máy dev: đã xác minh email.
 * Chạy: php artisan db:seed --class=NguoiHocThuSeeder. Không tạo gì trên máy chủ thật (APP_ENV=production).
 */
class NguoiHocThuSeeder extends Seeder
{
    public const EMAIL = 'nguoi-hoc@lernbuch.test';

    public const MAT_KHAU = 'thu-lernbuch-2026';

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('Bỏ qua: không tạo tài khoản thử trên máy chủ thật.');

            return;
        }

        $user = User::query()->firstOrNew(['email' => self::EMAIL]);
        $user->forceFill([
            'name' => 'Người học thử',
            'password' => self::MAT_KHAU,
            'email_verified_at' => now(),
        ])->save();
    }
}
