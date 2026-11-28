<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Support\Ai\CaiDatAi;
use App\Support\Ai\DanhSachThu;
use App\Support\Ai\DongYAi;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

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

    /**
     * 1–3: phiên Flashcard (tests/e2e/hoc.spec.ts); 4–10: mỗi chế độ học một tài khoản (tests/e2e/che-do.spec.ts);
     * 11: học khi mất mạng (tests/e2e/pwa.spec.ts); 12, 13: AI (tests/e2e/ai.spec.ts); 14, 15: Hỏi AI (tests/e2e/hoi-ai.spec.ts).
     */
    public const SO_TAI_KHOAN_HOC = 15;

    /** Tài khoản học nằm trong danh sách thử AI; mỗi lần chạy seeder thì xoá ngày sinh, đồng ý và nhật ký AI của chúng. */
    public const TAI_KHOAN_AI = [12, 13];

    /** Tài khoản dùng được AI ngay (người lớn, đã đồng ý); mỗi lần chạy seeder thì xoá dữ liệu AI của chúng. */
    public const TAI_KHOAN_AI_SAN = [14, 15];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('Bỏ qua: không tạo tài khoản thử trên máy chủ thật.');

            return;
        }

        $this->tao(self::EMAIL, 'Người học thử');

        foreach (self::CO_MAN_HINH as $co) {
            for ($i = 1; $i <= self::SO_TAI_KHOAN_HOC; $i++) {
                $user = $this->tao(self::emailHoc($co, $i), "Học thử {$co} {$i}");

                if (in_array($i, self::TAI_KHOAN_AI, true)) {
                    $this->datLaiAi($user);
                }

                if (in_array($i, self::TAI_KHOAN_AI_SAN, true)) {
                    $this->datLaiAi($user);
                    $user->forceFill(['birth_date' => gmmktime(0, 0, 0, 1, 1, 1990)])->saveQuietly();
                    DongYAi::ghi($user, true);
                    $this->goiPremium($user);
                }
            }
        }

        // AI bật cho danh sách thử (AI giả trên máy dev và CI, AI_DRIVER=fake)
        // Trần hệ thống nới rộng: test trình duyệt chạy song song nhiều lượt hỏi AI giả
        CaiDatAi::luu(['enabled' => true, 'audience' => 'testers', 'rpm_cap' => 1000, 'rpd_cap' => 100000], null);

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

    /** Về lại lúc chưa khai ngày sinh, chưa đồng ý, chưa hỏi AI lần nào; vẫn trong danh sách thử. */
    private function datLaiAi(User $user): void
    {
        $user->forceFill(['birth_date' => null])->saveQuietly();
        DB::table('consents')->where('user_id', $user->id)->delete();
        DB::table('ai_requests')->where('user_id', $user->id)->delete();
        $cuoc = DB::table('ai_conversations')->where('user_id', $user->id)->pluck('id');
        DB::table('ai_messages')->whereIn('conversation_id', $cuoc)->delete();
        DB::table('ai_conversations')->where('user_id', $user->id)->delete();
        DB::table('user_ai_lessons')->where('user_id', $user->id)->delete();
        DB::table('writing_submissions')->where('user_id', $user->id)->delete();
        DanhSachThu::dat($user, true);
    }

    /** Gói Premium cấp tay (50 lượt AI mỗi ngày, gói free chỉ 3): test trình duyệt hỏi AI nhiều lượt. */
    private function goiPremium(User $user): void
    {
        $goi = DB::table('plans')->where('code', 'premium_12m')->value('id');

        if ($goi === null) {
            return;
        }

        DB::table('subscriptions')->updateOrInsert(
            ['user_id' => $user->id, 'source' => 'admin', 'note' => 'tài khoản thử AI'],
            ['plan_id' => (int) $goi, 'status' => 'active', 'starts_at' => now()->getTimestamp() - 60, 'ends_at' => null, 'created_at' => now()->getTimestamp()],
        );
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
