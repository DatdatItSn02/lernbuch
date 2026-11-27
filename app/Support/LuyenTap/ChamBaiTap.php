<?php

namespace App\Support\LuyenTap;

use Normalizer;

/**
 * Chấm câu bài tập như Lernbuch (exercises.js: gradeFill, gradeOrder): 2 đúng, 1 đúng chữ nhưng sai hoa thường
 * (câu có phân biệt hoa thường), 0 sai; chỉ 2 tính là đúng. App chấm ngay trên máy theo cùng quy tắc, máy chủ chấm lại
 * khi nhận kết quả. Hai bên cùng chạy bảng ca thử tests/chung/cham-bai-tap.json.
 */
final class ChamBaiTap
{
    public const DUNG = 2;

    public const DUNG_CHU = 1;

    public const SAI = 0;

    /** Khoảng trắng như \s của JavaScript, để máy chủ và app tách chữ giống hệt nhau. */
    private const KHOANG_TRANG = '[\t\n\x{0B}\f\r \x{A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}]';

    /** Chữ để so: viết thường, ä ö ü ß thành ae oe ue ss, bỏ dấu, dấu câu thành khoảng trắng, gộp khoảng trắng. */
    public static function fold(string $s): string
    {
        $s = mb_strtolower(self::nfc($s));
        $s = strtr($s, ['ß' => 'ss', 'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue']);
        $s = class_exists(Normalizer::class) ? (string) Normalizer::normalize($s, Normalizer::FORM_D) : $s;
        $s = (string) preg_replace('/[\x{0300}-\x{036f}]/u', '', $s);
        $s = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s);

        return trim($s, ' ');
    }

    /** Bỏ khoảng trắng thừa và dấu câu cuối (. ! ? , ; :), giữ hoa thường. */
    public static function strip(string $s): string
    {
        $s = trim((string) preg_replace('/'.self::KHOANG_TRANG.'+/u', ' ', self::nfc($s)), ' ');

        return (string) preg_replace('/[.!?,;:]+$/u', '', $s);
    }

    /**
     * @param  array{kind: string, prompt: string, answers: list<string>, options: list<string>|null, case_sensitive: bool, frag: list<string>}  $cau
     * @return 0|1|2
     */
    public static function cham(array $cau, string $traLoi): int
    {
        if (trim((string) preg_replace('/'.self::KHOANG_TRANG.'+/u', ' ', $traLoi), ' ') === '') {
            return self::SAI;
        }

        if ($cau['kind'] === 'mc' && count($cau['options'] ?? []) > 1) {
            return in_array($traLoi, $cau['answers'], true) ? self::DUNG : self::SAI;
        }

        if ($cau['kind'] === 'order') {
            $f = self::fold($traLoi);

            return $f !== '' && self::coFold($cau['answers'], $f) ? self::DUNG : self::SAI;
        }

        return self::dien($cau, $traLoi);
    }

    /**
     * Câu điền (và sửa lỗi): khớp đúng chữ (bỏ dấu câu cuối) thì 2; khớp khi bỏ dấu, hoa thường thì 2, câu phân biệt hoa thường
     * thì 1. Sửa lỗi: để nguyên câu sai thì 0; viết đúng cả câu, hoặc câu có chứa đúng phần đã sửa, thì 2.
     *
     * @param  array{kind: string, prompt: string, answers: list<string>, options: list<string>|null, case_sensitive: bool, frag: list<string>}  $cau
     * @return 0|1|2
     */
    private static function dien(array $cau, string $traLoi): int
    {
        $v = self::strip($traLoi);

        if ($cau['kind'] === 'fix') {
            $fv = self::fold($v);

            if ($fv === '' || $fv === self::fold($cau['prompt'])) {
                return self::SAI;
            }

            if (self::coFold($cau['answers'], $fv)) {
                return self::DUNG;
            }

            foreach ($cau['frag'] as $f) {
                if (str_contains(' '.$fv.' ', ' '.self::fold($f).' ')) {
                    return self::DUNG;
                }
            }

            return self::SAI;
        }

        foreach ($cau['answers'] as $a) {
            if (self::strip($a) === $v) {
                return self::DUNG;
            }
        }

        $fv = self::fold($v);

        if ($fv !== '' && self::coFold($cau['answers'], $fv)) {
            return $cau['case_sensitive'] ? self::DUNG_CHU : self::DUNG;
        }

        return self::SAI;
    }

    /** @param  list<string>  $dapAn */
    private static function coFold(array $dapAn, string $fv): bool
    {
        foreach ($dapAn as $a) {
            if (self::fold($a) === $fv) {
                return true;
            }
        }

        return false;
    }

    private static function nfc(string $s): string
    {
        return class_exists(Normalizer::class) ? (string) Normalizer::normalize($s, Normalizer::FORM_C) : $s;
    }
}
