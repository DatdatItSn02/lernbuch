<?php

namespace App\Support\Ai;

use App\Models\User;
use Laravel\Pennant\Feature;

/** Danh sách thử AI (Pennant, tính năng ai-thu-nghiem theo từng tài khoản). Quản trị bật tắt ở trang xem người học. */
final class DanhSachThu
{
    public const TEN = 'ai-thu-nghiem';

    public static function co(User $user): bool
    {
        return Feature::for($user)->active(self::TEN);
    }

    public static function dat(User $user, bool $co): void
    {
        if ($co) {
            Feature::for($user)->activate(self::TEN);
        } else {
            Feature::for($user)->deactivate(self::TEN);
        }
    }
}
