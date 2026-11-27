<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Normalizer;

/**
 * Audio đọc từ tiếng Đức tạo sẵn bằng Piper (workflow audio trên GitHub Actions, công cụ tools/audio).
 * File nằm ở thư mục audio dùng chung (trên alwaysdata: ~/lernbuch/shared/audio, nối vào public/audio khi deploy),
 * tên theo hash sha1(giọng|tốc độ|chữ) như cột media_assets.hash; bảng media_assets ghi file nào đã có.
 * Thẻ chưa có file thì app đọc bằng giọng của trình duyệt.
 */
final class AmThanh
{
    public const LOAI = 'audio';

    /** Nghe chậm phát ở tốc độ 0,65 trong trình duyệt, không tạo file riêng. */
    public const TOC_DO = 'normal';

    /** Tên ổ trong media_assets: thư mục audio dùng chung của máy chủ. */
    public const O_CHUNG = 'shared';

    public const MAU_GIONG = '/^[a-z]{2}_[A-Z]{2}-[a-z0-9_]+-(x_low|low|medium|high)$/';

    /** Giọng đang dùng cho audio của từ (đổi bằng AUDIO_GIONG sau khi đã tạo audio bằng giọng mới). */
    public static function giong(): string
    {
        return (string) config('app.audio.giong');
    }

    /** Chữ đem đi đọc cho một thẻ: mặt trước bỏ phần trong ngoặc ("die Eltern (Pl.)"), "der/die" đọc thành "der, die". */
    public static function chuCanDoc(string $front): string
    {
        $chu = (string) preg_replace('/\([^)]*\)/u', ' ', $front);
        $chu = (string) preg_replace('/\s*\/\s*/u', ', ', $chu);

        return self::chuanHoa(trim($chu, " \t\n\r\0\x0B,;"));
    }

    /** Chuẩn hoá như tools/audio/giong.py (chuan_hoa): Unicode NFC, gộp khoảng trắng. */
    public static function chuanHoa(string $chu): string
    {
        $nfc = class_exists(Normalizer::class) ? Normalizer::normalize($chu, Normalizer::FORM_C) : $chu;

        return trim((string) preg_replace('/\s+/u', ' ', $nfc === false ? $chu : $nfc));
    }

    public static function hash(string $giong, string $chu): string
    {
        return sha1($giong.'|'.self::TOC_DO.'|'.self::chuanHoa($chu));
    }

    public static function duongDan(string $giong, string $hash): string
    {
        return $giong.'/'.substr($hash, 0, 2).'/'.$hash.'.mp3';
    }

    public static function url(string $duongDan): string
    {
        return rtrim((string) config('app.audio.url'), '/').'/'.$duongDan;
    }

    /**
     * Địa chỉ audio của nhiều thẻ một lần (một truy vấn): id thẻ → URL, null là chưa có file.
     *
     * @param  array<int, string>  $matTruoc  id thẻ → mặt trước
     * @return array<int, string|null>
     */
    public static function diaChi(array $matTruoc): array
    {
        $giong = self::giong();
        $hashCua = array_map(fn (string $f) => self::hash($giong, self::chuCanDoc($f)), $matTruoc);
        $co = $hashCua === [] ? collect() : DB::table('media_assets')->where('kind', self::LOAI)->whereIn('hash', array_values(array_unique($hashCua)))
            ->pluck('path', 'hash');

        return array_map(fn (string $h) => isset($co[$h]) ? self::url((string) $co[$h]) : null, $hashCua);
    }
}
