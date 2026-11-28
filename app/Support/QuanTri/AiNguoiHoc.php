<?php

namespace App\Support\QuanTri;

use App\Models\User;
use App\Support\Ai\DanhSachThu;
use App\Support\NhatKy;
use DateTimeImmutable;
use DateTimeZone;

/** Việc AI của nhân viên trên một người học: cho vào hay bỏ khỏi danh sách thử AI, sửa ngày sinh. Mỗi việc ghi nhật ký. */
final class AiNguoiHoc
{
    public static function datThu(User $user, bool $co): void
    {
        $truoc = DanhSachThu::co($user);

        if ($truoc === $co) {
            return;
        }

        DanhSachThu::dat($user, $co);
        NhatKy::ghi('user.ai_thu', $user, ['ai_thu' => $truoc], ['ai_thu' => $co]);
    }

    /** Ngày sinh dạng Y-m-d (đã kiểm ở form), lưu 00:00 UTC như app học. Nhật ký ghi ngày cũ, ngày mới và lý do. */
    public static function suaNgaySinh(User $user, string $ngay, string $lyDo): void
    {
        $moi = (new DateTimeImmutable($ngay.' 00:00:00', new DateTimeZone('UTC')))->getTimestamp();
        $truoc = $user->getAttribute('birth_date');

        if ($truoc === $moi) {
            return;
        }

        // Không qua GhiNhatKy (update): ghi một dòng riêng kèm lý do thay cho dòng user.update
        $user->forceFill(['birth_date' => $moi, 'updated_by' => NhatKy::nguoiLam()?->id]);
        $user->saveQuietly();
        NhatKy::ghi('user.ngay_sinh', $user, ['birth_date' => self::ngay($truoc)], array_filter(['birth_date' => $ngay, 'ly_do' => trim($lyDo)]));
    }

    public static function ngay(mixed $ngaySinh): ?string
    {
        return is_int($ngaySinh) ? gmdate('Y-m-d', $ngaySinh) : null;
    }
}
