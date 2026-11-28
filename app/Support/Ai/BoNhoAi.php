<?php

namespace App\Support\Ai;

use Illuminate\Support\Facades\DB;
use Normalizer;

/**
 * Chặng 4: cache dùng chung cho mọi người (bảng ai_cache). Khoá gồm loại, phiên bản prompt, model và tham số đã chuẩn hoá
 * (NFC, gộp khoảng trắng), giữ nguyên hoa thường vì tiếng Đức phân biệt Essen với essen. Chỉ lưu loại không chứa chữ riêng
 * của người học. Trúng cache thì không gọi nhà cung cấp và không trừ lượt. Mục hết hạn sau 90 ngày; bài học AI không hết hạn.
 */
final class BoNhoAi
{
    public const DUOC_LUU = ['lookup', 'word', 'doc', 'lesson', 'answer', 'sentence'];

    public const HAN_NGAY = 90;

    public static function duocLuu(string $loai): bool
    {
        return in_array($loai, self::DUOC_LUU, true);
    }

    /** @param array<string, string> $thamSo */
    public static function khoa(string $loai, int $phienBan, string $model, array $thamSo): string
    {
        $gon = [];

        foreach ($thamSo as $ten => $giaTri) {
            $gon[(string) $ten] = self::chuan((string) $giaTri);
        }

        ksort($gon);

        return hash('sha256', $loai.'|'.$phienBan.'|'.$model.'|'.json_encode($gon, JSON_UNESCAPED_UNICODE));
    }

    public static function chuan(string $chu): string
    {
        $nfc = class_exists(Normalizer::class) ? Normalizer::normalize($chu, Normalizer::FORM_C) : $chu;

        return trim((string) preg_replace('/\s+/u', ' ', is_string($nfc) ? $nfc : $chu));
    }

    /** @return array<string, mixed>|null kết quả đã lưu và còn hạn; trúng thì tăng số lần dùng */
    public static function lay(string $khoa): ?array
    {
        $bay = now()->getTimestamp();
        $dong = DB::table('ai_cache')->where('hash', $khoa)->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $bay))->first();

        if ($dong === null) {
            return null;
        }

        $kq = json_decode((string) $dong->response, true);

        if (! is_array($kq)) {
            return null;
        }

        DB::table('ai_cache')->where('hash', $khoa)->update(['hits' => DB::raw('hits + 1'), 'last_hit_at' => $bay]);

        return $kq;
    }

    /** Đọc một mục theo khoá, không tính là một lần dùng, kể cả khi đã hết hạn (bài học AI đã lưu). @return array<string, mixed>|null */
    public static function theoHash(string $khoa): ?array
    {
        $kq = json_decode((string) DB::table('ai_cache')->where('hash', $khoa)->value('response'), true);

        return is_array($kq) ? $kq : null;
    }

    /** Lưu (ghi đè mục cũ, kể cả mục đã hết hạn). @param array<string, mixed> $kq */
    public static function luu(string $khoa, string $loai, array $kq): void
    {
        $bay = now()->getTimestamp();

        DB::table('ai_cache')->upsert([[
            'hash' => $khoa,
            'kind' => $loai,
            'response' => json_encode($kq, JSON_UNESCAPED_UNICODE),
            'hits' => 0,
            'created_at' => $bay,
            'last_hit_at' => null,
            'expires_at' => $loai === 'lesson' ? null : $bay + self::HAN_NGAY * 86400,
        ]], ['hash'], ['kind', 'response', 'hits', 'created_at', 'last_hit_at', 'expires_at']);
    }
}
