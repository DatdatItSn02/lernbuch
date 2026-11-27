<?php

namespace App\Support\Hoc;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Lặp lại ngắt quãng 6 mức, theo luật của Lernbuch (srs.py). Bản TypeScript (resources/js/app/lib/srs.ts)
 * tính y hệt để hiện hạn trên nút chấm; tests/chung/lap-lich.json là bảng ca thử chung của hai bên.
 *
 * Mức: 0 Mới, 1 Đang học, 2 Quen, 3 Khá, 4 Thuộc, 5 Thành thạo. Chấm: 0 Quên, 1 Khó, 2 Tốt, 3 Dễ.
 * - Quên: mức 1 trở xuống về 1, cao hơn lùi 2 mức (thấp nhất 1); ôn lại sau 10 phút; tính một lần quên nếu thẻ đã học.
 * - Khó: giữ mức, khoảng ôn còn một nửa, làm tròn xuống, ít nhất 1 ngày.
 * - Tốt lên 1 mức, Dễ lên 2 mức (cao nhất 5). Thẻ mới tính như mức 1.
 * - Hạn ôn là đầu ngày (00:00 theo múi giờ người học) của lúc chấm + số ngày + 12 giờ, như Lernbuch: cộng 12 giờ để
 *   đổi giờ mùa hè không làm lệch ngày, nên chấm từ trưa trở đi thì hạn rơi vào ngày sau đó.
 */
final class LapLich
{
    public const MUC_TOI_DA = 5;

    public const QUEN = 0;

    public const KHO = 1;

    public const TOT = 2;

    public const DE = 3;

    /** Số ngày tới hạn ôn theo mức mới. */
    public const NGAY_THEO_MUC = [1 => 1, 2 => 3, 3 => 7, 4 => 14, 5 => 30];

    public const HOC_LAI_GIAY = 600;

    public static function mucMoi(int $muc, int $cham): int
    {
        self::kiem($muc, $cham);
        $goc = max($muc, 1);

        return match ($cham) {
            self::QUEN => $muc <= 1 ? 1 : max(1, $muc - 2),
            self::KHO => $goc,
            self::TOT => min(self::MUC_TOI_DA, $goc + 1),
            default => min(self::MUC_TOI_DA, $goc + 2),
        };
    }

    /** Số ngày ghi trên nút chấm; Quên không tính theo ngày (ôn lại sau 10 phút) nên trả null. */
    public static function soNgay(int $muc, int $cham): ?int
    {
        if ($cham === self::QUEN) {
            self::kiem($muc, $cham);

            return null;
        }

        $ngay = self::NGAY_THEO_MUC[self::mucMoi($muc, $cham)];

        return $cham === self::KHO ? max(1, intdiv($ngay, 2)) : $ngay;
    }

    /**
     * Chấm một thẻ lúc $luc (Unix, giây) với người học ở múi giờ $muiGio.
     *
     * @return array{muc: int, han: int, quen: bool} mức mới, hạn ôn (Unix), có tính là một lần quên không
     */
    public static function cham(int $muc, int $cham, int $luc, string $muiGio): array
    {
        $moi = self::mucMoi($muc, $cham);
        $ngay = self::soNgay($muc, $cham);

        if ($ngay === null) {
            return ['muc' => $moi, 'han' => $luc + self::HOC_LAI_GIAY, 'quen' => $muc >= 1];
        }

        return ['muc' => $moi, 'han' => self::dauNgay($luc + $ngay * 86400 + 43200, $muiGio), 'quen' => false];
    }

    /** 00:00 của ngày chứa $luc theo múi giờ (ngày không có 00:00 vì đổi giờ thì lấy lúc đầu tiên của ngày). */
    public static function dauNgay(int $luc, string $muiGio): int
    {
        return (new DateTimeImmutable('@'.$luc))->setTimezone(new DateTimeZone($muiGio))->setTime(0, 0)->getTimestamp();
    }

    /** Đầu ngày hôm sau (ngày có 23 hay 25 giờ vẫn đúng). */
    public static function dauNgayMai(int $luc, string $muiGio): int
    {
        return self::dauNgay(self::dauNgay($luc, $muiGio) + 36 * 3600, $muiGio);
    }

    /** Đầu ngày hôm trước của một đầu ngày. */
    public static function dauNgayTruoc(int $dauNgay, string $muiGio): int
    {
        return self::dauNgay($dauNgay - 12 * 3600, $muiGio);
    }

    private static function kiem(int $muc, int $cham): void
    {
        if ($muc < 0 || $muc > self::MUC_TOI_DA) {
            throw new InvalidArgumentException("Mức không hợp lệ: {$muc}");
        }

        if ($cham < self::QUEN || $cham > self::DE) {
            throw new InvalidArgumentException("Cách chấm không hợp lệ: {$cham}");
        }
    }
}
