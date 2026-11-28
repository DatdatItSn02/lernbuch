<?php

namespace App\Support\Ai;

use App\Models\User;
use App\Support\KhoaHoc;
use App\Support\NoiDung;

/** Trình độ ghi vào prompt ({level}): trình độ người học tự chọn; chưa chọn thì cấp của nội dung (thẻ, bài); không có thì A1. */
final class TrinhDoAi
{
    public static function cua(User $user, ?int $capMacDinh = null): string
    {
        $tuChon = KhoaHoc::choApi($user, KhoaHoc::hienTai($user))['level'] ?? null;
        $cap = is_int($tuChon) ? $tuChon : $capMacDinh;

        return NoiDung::CAP[$cap ?? 1] ?? 'A1';
    }
}
