<?php

namespace App\Support\PhatAm;

/**
 * Bản dự phòng của chấm phát âm: so chữ trình duyệt nghe được với câu mẫu. Không có điểm số, chỉ biết từ nào nghe ra.
 * Hai bên đổi chữ thường, bỏ dấu câu, gộp khoảng trắng, ß thành ss (giữ ä, ö, ü).
 */
final class SoChu
{
    /** @return list<array{word: string, heard: bool}> */
    public static function so(string $cauMau, string $ngheDuoc): array
    {
        $nghe = self::cacTu($ngheDuoc);
        $vt = 0;
        $ra = [];

        foreach (preg_split('/\s+/u', trim($cauMau), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $goc) {
            $sach = self::cacTu($goc);

            if ($sach === []) {
                continue;
            }

            $thay = false;

            // Một chữ của câu mẫu có thể tách thành nhiều từ sau khi bỏ dấu (vd "z.B."): coi là nghe ra khi thấy từ đầu tiên
            for ($i = $vt, $n = count($nghe); $i < $n; $i++) {
                if ($nghe[$i] === $sach[0]) {
                    $thay = true;
                    $vt = $i + 1;
                    break;
                }
            }

            $ra[] = ['word' => trim($goc, " \t.,!?;:\"'()«»„“”"), 'heard' => $thay];
        }

        return $ra;
    }

    public static function rong(string $ngheDuoc): bool
    {
        return self::cacTu($ngheDuoc) === [];
    }

    /** @return list<string> */
    private static function cacTu(string $chu): array
    {
        $chu = str_replace(['ß', 'ẞ'], 'ss', mb_strtolower($chu));
        $chu = (string) preg_replace('/[^\p{L}\p{M}\p{N}]+/u', ' ', $chu);

        return preg_split('/\s+/u', trim($chu), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
