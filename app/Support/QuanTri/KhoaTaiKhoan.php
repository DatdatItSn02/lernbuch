<?php

namespace App\Support\QuanTri;

use App\Models\User;
use App\Support\NhatKy;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Khoá và mở khoá tài khoản người học từ trang quản trị. Khoá thì đăng xuất ngay mọi máy (xoá phiên trong bảng sessions
 * và token), đăng nhập lại bị từ chối, API trả 403 tai_khoan_bi_khoa. Mỗi lần khoá, mở ghi nhật ký kèm lý do.
 * Tài khoản nhân viên khoá ở trang Nhân viên.
 */
final class KhoaTaiKhoan
{
    public const DANG_HOC = 'active';

    public const BI_KHOA = 'suspended';

    /** Lý do khoá dài nhất (ghi vào nhật ký). */
    public const LY_DO_TOI_DA = 255;

    public static function khoa(User $user, string $lyDo = ''): void
    {
        self::kiem($user);

        if ($user->status === self::BI_KHOA) {
            return;
        }

        $truoc = $user->status;
        // Không qua GhiNhatKy (update): ghi một dòng riêng kèm lý do thay cho dòng user.update
        $user->forceFill(['status' => self::BI_KHOA, 'updated_by' => NhatKy::nguoiLam()?->id]);
        $user->saveQuietly();
        DB::table('sessions')->where('user_id', $user->id)->delete();
        DB::table('personal_access_tokens')->where('tokenable_type', $user->getMorphClass())->where('tokenable_id', $user->id)->delete();

        NhatKy::ghi('user.suspend', $user, ['status' => $truoc], array_filter([
            'status' => self::BI_KHOA,
            'reason' => mb_substr(trim($lyDo), 0, self::LY_DO_TOI_DA),
        ], fn (string $v) => $v !== ''));
    }

    public static function moKhoa(User $user): void
    {
        self::kiem($user);

        if ($user->status !== self::BI_KHOA) {
            return;
        }

        $user->forceFill(['status' => self::DANG_HOC, 'updated_by' => NhatKy::nguoiLam()?->id]);
        $user->saveQuietly();
        NhatKy::ghi('user.unsuspend', $user, ['status' => self::BI_KHOA], ['status' => self::DANG_HOC]);
    }

    private static function kiem(User $user): void
    {
        if ($user->is_staff) {
            throw new InvalidArgumentException('Tài khoản nhân viên khoá ở trang Nhân viên.');
        }
    }
}
