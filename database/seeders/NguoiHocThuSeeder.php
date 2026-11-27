<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Tài khoản thử cho test trình duyệt (Playwright) và máy dev, đã xác minh email: người học, và một nhân viên quản trị.
 * Chạy: php artisan db:seed --class=NguoiHocThuSeeder (sau db:seed để có vai trò). Không tạo gì trên máy chủ thật (APP_ENV=production).
 */
class NguoiHocThuSeeder extends Seeder
{
    public const EMAIL = 'nguoi-hoc@lernbuch.test';

    public const MAT_KHAU = 'thu-lernbuch-2026';

    /** Nhân viên thử (Quản trị cao nhất), chỉ có trên máy dev và CI. */
    public const EMAIL_QUAN_TRI = 'quan-tri-thu@lernbuch.test';

    /** Tài khoản riêng cho test trình duyệt có chấm thẻ: mỗi cỡ màn hình, mỗi test một tài khoản để tiến độ không lẫn nhau. */
    public const CO_MAN_HINH = ['may-tinh', 'dien-thoai'];

    /** 1–3: phiên Flashcard (tests/e2e/hoc.spec.ts); 4–10: mỗi chế độ học một tài khoản (tests/e2e/che-do.spec.ts); 11: học khi mất mạng (tests/e2e/pwa.spec.ts). */
    public const SO_TAI_KHOAN_HOC = 11;

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('Bỏ qua: không tạo tài khoản thử trên máy chủ thật.');

            return;
        }

        $this->tao(self::EMAIL, 'Người học thử');

        foreach (self::CO_MAN_HINH as $co) {
            for ($i = 1; $i <= self::SO_TAI_KHOAN_HOC; $i++) {
                $this->tao(self::emailHoc($co, $i), "Học thử {$co} {$i}");
            }
        }

        // Nhân viên thử cho test trình duyệt trang quản trị (tests/e2e/quan-tri.spec.ts); vai trò có sau khi chạy seed.sql
        $quanTri = $this->tao(self::EMAIL_QUAN_TRI, 'Quản trị thử');
        $quanTri->forceFill(['is_staff' => true])->save();

        if (Role::query()->where('name', 'super_admin')->where('guard_name', 'web')->exists()) {
            $quanTri->syncRoles(['super_admin']);
        }
    }

    /** Email tài khoản học thử, vd hoc-may-tinh-1@lernbuch.test (tests/e2e/phien.ts ghép cùng cách). */
    public static function emailHoc(string $coManHinh, int $so): string
    {
        return "hoc-{$coManHinh}-{$so}@lernbuch.test";
    }

    private function tao(string $email, string $ten): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $ten,
            'password' => self::MAT_KHAU,
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }
}
