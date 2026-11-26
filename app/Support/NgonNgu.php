<?php

namespace App\Support;

/** Chọn ngôn ngữ giao diện: chỉ dùng ngôn ngữ đã có bản dịch (config app.ngon_ngu_giao_dien), còn lại về tiếng Việt. */
final class NgonNgu
{
    /** @return list<string> */
    public static function giaoDien(): array
    {
        /** @var list<string> $ds */
        $ds = config('app.ngon_ngu_giao_dien', ['vi']);

        return $ds;
    }

    public static function chon(?string $ma): string
    {
        return $ma !== null && in_array($ma, self::giaoDien(), true) ? $ma : (string) config('app.locale', 'vi');
    }
}
