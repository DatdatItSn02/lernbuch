<?php

namespace App\Support\Ai\BoNoi;

use App\Support\Ai\CaiDatAi;

/**
 * Chọn bộ nối: biến AI_DRIVER (config ai.bo_noi) thắng ô Nhà cung cấp trong trang Cài đặt AI. Ngoài production, bộ nối thật
 * mà thiếu khoá thì dùng AI giả (máy dev chạy được không cần khoá). Ở production thiếu khoá là lỗi cấu hình, không bao giờ giả.
 */
final class ChonBoNoi
{
    public static function ten(): string
    {
        $ten = (string) config('ai.bo_noi');

        if ($ten === '') {
            $ten = CaiDatAi::tatCa()['provider'];
        }

        if ($ten !== 'fake' && ! app()->isProduction() && self::khoa($ten) === '') {
            return 'fake';
        }

        return $ten;
    }

    public static function boNoi(): BoNoiAi
    {
        return match (self::ten()) {
            'fake' => app(BoNoiGia::class),
            'gemini' => new BoNoiGemini(self::khoa('gemini'), (string) config('ai.gemini.goc')),
            'openai_compatible' => new BoNoiOpenAi(self::khoa('openai_compatible'), (string) config('ai.openai.goc')),
            default => throw new LoiBoNoi(LoiBoNoi::CAU_HINH, 'Bộ nối AI lạ'),
        };
    }

    /** Khoá của bộ nối thật ('' nếu chưa có); chỉ dùng trên máy chủ, không bao giờ trả ra trình duyệt. */
    public static function khoa(string $ten): string
    {
        return match ($ten) {
            'gemini' => (string) config('ai.gemini.khoa'),
            'openai_compatible' => (string) config('ai.openai.khoa'),
            default => '',
        };
    }
}
