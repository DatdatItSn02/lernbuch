<?php

namespace App\Support\PhatAm;

use App\Support\Ai\TuChoiAi;

/**
 * File ghi âm app gửi lên (base64 trong JSON, để PHP không ghi file tạm ra đĩa): phải là WAV PCM 16 bit, 16 kHz, một kênh,
 * đúng định dạng Azure nhận. Đọc từng khối của file (không giả định khối data nằm ở byte 44). Không bao giờ lưu file này.
 */
final class Wav
{
    public const TAN_SO = 16000;

    /** Byte mỗi giây: 16.000 mẫu × 2 byte. */
    public const BYTE_MOI_GIAY = 32000;

    /** Dưới mức này coi là im lặng (biên độ lớn nhất trên thang 32.767). */
    public const NGUONG_IM_LANG = 400;

    public const MS_TOI_THIEU = 300;

    private function __construct(
        public readonly string $file,
        public readonly int $bytes,
        public readonly int $ms,
        public readonly bool $imLang,
    ) {}

    public static function doDaiToiDa(): int
    {
        return intdiv((CaiDatPhatAm::GIAY_TOI_DA * self::BYTE_MOI_GIAY + 1000) * 4, 3) + 4;
    }

    /** Ném TuChoiAi (422) khi file quá lớn, sai định dạng hoặc dài quá $giayToiDa. */
    public static function doc(string $base64, int $giayToiDa): self
    {
        if (strlen($base64) > self::doDaiToiDa()) {
            throw new TuChoiAi('phat_am_qua_lon', 422);
        }

        $file = base64_decode($base64, true);

        if ($file === false || strlen($file) < 12 || substr($file, 0, 4) !== 'RIFF' || substr($file, 8, 4) !== 'WAVE') {
            throw new TuChoiAi('phat_am_sai_dinh_dang', 422);
        }

        $fmt = null;
        $data = null;
        $vt = 12;
        $dai = strlen($file);

        while ($vt + 8 <= $dai) {
            $ten = substr($file, $vt, 4);
            $co = unpack('V', substr($file, $vt + 4, 4));
            $kichCo = is_array($co) ? (int) $co[1] : 0;
            $batDau = $vt + 8;

            if ($ten === 'fmt ' && $fmt === null) {
                $fmt = substr($file, $batDau, min($kichCo, max(0, $dai - $batDau)));
            } elseif ($ten === 'data' && $data === null) {
                $data = substr($file, $batDau, min($kichCo, max(0, $dai - $batDau)));
            }

            // Khối có kích cỡ lẻ được đệm thêm một byte
            $vt = $batDau + $kichCo + ($kichCo % 2);
        }

        if ($fmt === null || strlen($fmt) < 16 || $data === null || $data === '') {
            throw new TuChoiAi('phat_am_sai_dinh_dang', 422);
        }

        $f = unpack('vma/vkenh/Vtanso/Vbyte/vkhoi/vbit', $fmt);

        if (! is_array($f) || $f['ma'] !== 1 || $f['kenh'] !== 1 || $f['tanso'] !== self::TAN_SO || $f['bit'] !== 16) {
            throw new TuChoiAi('phat_am_sai_dinh_dang', 422);
        }

        $ms = intdiv(strlen($data) * 1000, self::BYTE_MOI_GIAY);

        if ($ms > ($giayToiDa * 1000) + 500) {
            throw new TuChoiAi('phat_am_qua_dai', 422);
        }

        return new self($file, strlen($file), $ms, $ms < self::MS_TOI_THIEU || self::bienDoLonNhat($data) < self::NGUONG_IM_LANG);
    }

    private static function bienDoLonNhat(string $data): int
    {
        $mau = unpack('v*', strlen($data) % 2 === 0 ? $data : substr($data, 0, -1));
        $lon = 0;

        foreach (is_array($mau) ? $mau : [] as $u) {
            $s = $u >= 32768 ? $u - 65536 : $u;
            $lon = max($lon, abs($s));

            if ($lon >= self::NGUONG_IM_LANG) {
                break;
            }
        }

        return $lon;
    }
}
