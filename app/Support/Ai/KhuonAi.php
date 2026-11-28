<?php

namespace App\Support\Ai;

/**
 * Khuôn kết quả AI theo loại. Kết quả phải là object JSON đúng khuôn: thiếu khoá, sai kiểu, chuỗi quá dài, chuỗi bắt buộc
 * mà rỗng thì hỏng (không hiện, không lưu cache, không trừ lượt). Danh sách dài quá thì cắt bớt, khoá lạ thì bỏ.
 * Chuỗi giữ nguyên, kể cả khi có thẻ HTML: app luôn hiện kết quả như chữ, không bao giờ chạy như mã.
 *
 * Trường: ['chu', tối đa ký tự, bắt buộc có chữ] hoặc ['ds', tối đa phần tử, khuôn phần tử];
 * khuôn phần tử là một trường như trên hoặc một object (tên trường → trường).
 */
final class KhuonAi
{
    public const KHUON = [
        'word' => [
            'meaning' => ['chu', 600, true],
            'usage' => ['chu', 800, false],
            'examples' => ['ds', 5, ['de' => ['chu', 200, true], 'vi' => ['chu', 300, true]]],
            'common_mistakes' => ['ds', 5, ['chu', 300, true]],
        ],
    ];

    /** @return array<string, mixed>|null kết quả đã làm sạch, hoặc null nếu hỏng hay loại chưa có khuôn */
    public static function doc(string $loai, string $chu): ?array
    {
        $hinh = self::KHUON[$loai] ?? null;

        if ($hinh === null) {
            return null;
        }

        $chu = trim($chu);

        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/si', $chu, $m) === 1) {
            $chu = $m[1];
        }

        $giaTri = json_decode($chu, true);
        $kq = self::docVat($hinh, $giaTri);

        return is_array($kq) ? $kq : null;
    }

    /** @return array<string, mixed>|null JSON Schema để nhà cung cấp trả đúng khuôn (không có giới hạn độ dài: Google có thể từ chối khuôn phức tạp) */
    public static function jsonSchema(string $loai): ?array
    {
        return isset(self::KHUON[$loai]) ? self::schemaVat(self::KHUON[$loai]) : null;
    }

    /**
     * @param  array<string, mixed>  $hinh
     * @return array<string, mixed>|null
     */
    private static function docVat(array $hinh, mixed $giaTri): ?array
    {
        if (! is_array($giaTri) || ($giaTri !== [] && array_is_list($giaTri))) {
            return null;
        }

        $ra = [];

        foreach ($hinh as $ten => $truong) {
            if (! array_key_exists($ten, $giaTri)) {
                return null;
            }

            /** @var array<int|string, mixed> $truong */
            $v = self::docPhan($truong, $giaTri[$ten]);

            if ($v === null) {
                return null;
            }

            $ra[$ten] = $v;
        }

        return $ra;
    }

    /** @param array<int|string, mixed> $khuon một trường (list) hoặc một object (tên trường → trường) */
    private static function docPhan(array $khuon, mixed $giaTri): mixed
    {
        if (! array_is_list($khuon)) {
            /** @var array<string, mixed> $khuon */
            return self::docVat($khuon, $giaTri);
        }

        [$kieu, $toiDa, $them] = $khuon;

        if ($kieu === 'chu') {
            if (! is_string($giaTri)) {
                return null;
            }

            $giaTri = trim($giaTri);

            return mb_strlen($giaTri) > $toiDa || ($them === true && $giaTri === '') ? null : $giaTri;
        }

        if (! is_array($giaTri) || ! array_is_list($giaTri)) {
            return null;
        }

        $ra = [];

        foreach (array_slice($giaTri, 0, (int) $toiDa) as $phan) {
            /** @var array<int|string, mixed> $them */
            $v = self::docPhan($them, $phan);

            if ($v === null) {
                return null;
            }

            $ra[] = $v;
        }

        return $ra;
    }

    /**
     * @param  array<string, mixed>  $hinh
     * @return array<string, mixed>
     */
    private static function schemaVat(array $hinh): array
    {
        $thuocTinh = [];

        foreach ($hinh as $ten => $truong) {
            /** @var array<int|string, mixed> $truong */
            $thuocTinh[$ten] = self::schemaPhan($truong);
        }

        return ['type' => 'object', 'properties' => $thuocTinh, 'required' => array_keys($hinh)];
    }

    /**
     * @param  array<int|string, mixed>  $khuon
     * @return array<string, mixed>
     */
    private static function schemaPhan(array $khuon): array
    {
        if (! array_is_list($khuon)) {
            /** @var array<string, mixed> $khuon */
            return self::schemaVat($khuon);
        }

        if ($khuon[0] === 'chu') {
            return ['type' => 'string'];
        }

        /** @var array<int|string, mixed> $phanTu */
        $phanTu = $khuon[2];

        return ['type' => 'array', 'items' => self::schemaPhan($phanTu)];
    }
}
