<?php

namespace App\Support\Ai;

/**
 * Luật cho chữ người học gõ để hỏi AI. Khoảng trắng hai đầu đã bị bỏ (TrimStrings), ô toàn khoảng trắng thành rỗng;
 * độ dài đếm bằng ký tự sau khi bỏ khoảng trắng.
 */
final class ChuNhap
{
    /** @return list<mixed> */
    public static function luat(int $toiDa): array
    {
        return ['required', 'string', 'max:'.$toiDa, new CoChu];
    }

    public static function sach(string $chu): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", $chu));
    }
}
