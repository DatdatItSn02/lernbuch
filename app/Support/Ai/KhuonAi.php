<?php

namespace App\Support\Ai;

/**
 * Khuôn kết quả AI theo loại. Kết quả phải là object JSON đúng khuôn: thiếu khoá, sai kiểu, chuỗi quá dài, chuỗi bắt buộc
 * mà rỗng thì hỏng (không hiện, không lưu cache, không trừ lượt). Danh sách dài quá thì cắt bớt, khoá lạ thì bỏ.
 * Chuỗi giữ nguyên, kể cả khi có thẻ HTML: app luôn hiện kết quả như chữ, không bao giờ chạy như mã.
 *
 * Trường: ['chu', tối đa ký tự, bắt buộc có chữ]; ['ds', tối đa phần tử, khuôn phần tử, ít nhất] (ít nhất mặc định 0);
 * ['logic'] (chỉ true, false); ['so', nhỏ nhất, lớn nhất] (số nguyên); ['chon', [giá trị…]] (chuỗi trong danh sách).
 * Khuôn phần tử là một trường như trên hoặc một object (tên trường → trường).
 */
final class KhuonAi
{
    private const VD = ['de' => ['chu', 200, true], 'vi' => ['chu', 300, true]];

    private const LOI = ['wrong' => ['chu', 200, true], 'right' => ['chu', 200, true], 'why' => ['chu', 400, false]];

    public const KHUON = [
        'word' => [
            'meaning' => ['chu', 600, true],
            'usage' => ['chu', 800, false],
            'examples' => ['ds', 5, self::VD],
            'common_mistakes' => ['ds', 5, ['chu', 300, true]],
        ],
        'translate' => [
            'translation' => ['chu', 1200, true],
            'literal' => ['chu', 1200, false],
            'words' => ['ds', 30, ['de' => ['chu', 80, true], 'vi' => ['chu', 200, true], 'note' => ['chu', 300, false]]],
            'grammar' => ['chu', 1000, false],
            'natural' => ['chu', 1200, false],
        ],
        'lookup' => [
            'lemma' => ['chu', 80, true],
            'pos' => ['chu', 40, false],
            'gender' => ['chon', ['m', 'f', 'n', '']],
            'plural' => ['chu', 80, false],
            'forms' => ['ds', 8, ['label' => ['chu', 60, true], 'form' => ['chu', 120, true]]],
            'meanings_vi' => ['ds', 6, ['chu', 200, true], 1],
            'examples' => ['ds', 4, self::VD],
            'collocations' => ['ds', 6, ['chu', 120, true]],
            'level' => ['chu', 4, false],
        ],
        'lesson' => [
            'title' => ['chu', 160, true],
            'level' => ['chu', 4, false],
            'text' => ['ds', 12, ['de' => ['chu', 300, true], 'vi' => ['chu', 400, true]], 1],
            'new_words' => ['ds', 12, ['de' => ['chu', 80, true], 'vi' => ['chu', 200, true]]],
            'questions' => ['ds', 5, ['q' => ['chu', 300, true], 'choices' => ['ds', 4, ['chu', 200, true], 2], 'answer' => ['so', 0, 3]]],
            'exercise' => ['ds', 6, ['de' => ['chu', 300, true], 'answer' => ['chu', 120, true]]],
        ],
        'chat' => [
            'reply_de' => ['chu', 600, true],
            'reply_vi' => ['chu', 800, false],
            'corrections' => ['ds', 5, self::LOI],
            'next_hint' => ['chu', 300, false],
        ],
        'grammar' => [
            'corrected' => ['chu', 1600, true],
            'errors' => ['ds', 15, self::LOI],
            'natural' => ['chu', 1600, false],
            'level' => ['chu', 4, false],
        ],
        'sentence' => [
            'verdict' => ['chon', ['dung', 'gan_dung', 'sai']],
            'word_ok' => ['logic'],
            'corrected' => ['chu', 400, false],
            'errors' => ['ds', 8, self::LOI],
            'explain' => ['chu', 800, false],
            'use' => ['chu', 400, false],
            'tip' => ['chu', 400, false],
        ],
        'answer' => [
            'ok' => ['logic'],
            'why' => ['chu', 600, true],
            'better' => ['chu', 300, false],
        ],
        'doc' => [
            'summary' => ['chu', 800, true],
            'points' => ['ds', 6, ['chu', 400, true]],
            'examples' => ['ds', 5, self::VD],
        ],
        'ask' => [
            'answer' => ['chu', 1500, true],
            'examples' => ['ds', 5, self::VD],
        ],
    ];

    /** Loại không gửi khuôn cho nhà cung cấp (Google từ chối khuôn đó, xem ai-thu); khuôn vẫn được kiểm ở máy chủ. */
    public const KHONG_GUI_KHUON = [];

    /** @return list<string> */
    public static function khongGuiKhuon(): array
    {
        return self::KHONG_GUI_KHUON;
    }

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

        if (! is_array($kq) || ! self::hopLeThem($loai, $kq)) {
            return null;
        }

        return $kq;
    }

    /** @return array<string, mixed>|null JSON Schema để nhà cung cấp trả đúng khuôn (không có giới hạn độ dài: Google có thể từ chối khuôn phức tạp) */
    public static function jsonSchema(string $loai): ?array
    {
        if (! isset(self::KHUON[$loai]) || in_array($loai, self::khongGuiKhuon(), true)) {
            return null;
        }

        return self::schemaVat(self::KHUON[$loai]);
    }

    /** Luật ngoài khuôn: bài học AI có câu hỏi mà đáp án trỏ ra ngoài các lựa chọn thì hỏng. @param array<string, mixed> $kq */
    private static function hopLeThem(string $loai, array $kq): bool
    {
        if ($loai !== 'lesson') {
            return true;
        }

        /** @var list<array{choices: list<string>, answer: int}> $cauHoi */
        $cauHoi = $kq['questions'];

        foreach ($cauHoi as $c) {
            if ($c['answer'] >= count($c['choices'])) {
                return false;
            }
        }

        return true;
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

    /** @param array<int|string, mixed> $khuon một trường (list) hoặc một object (tên trường → trường); null là hỏng */
    private static function docPhan(array $khuon, mixed $giaTri): mixed
    {
        if (! array_is_list($khuon)) {
            /** @var array<string, mixed> $khuon */
            return self::docVat($khuon, $giaTri);
        }

        switch ($khuon[0]) {
            case 'chu':
                if (! is_string($giaTri)) {
                    return null;
                }

                $giaTri = trim($giaTri);

                return mb_strlen($giaTri) > (int) $khuon[1] || ($khuon[2] === true && $giaTri === '') ? null : $giaTri;

            case 'logic':
                return is_bool($giaTri) ? $giaTri : null;

            case 'so':
                return is_int($giaTri) && $giaTri >= (int) $khuon[1] && $giaTri <= (int) $khuon[2] ? $giaTri : null;

            case 'chon':
                /** @var list<string> $duoc */
                $duoc = $khuon[1];

                return is_string($giaTri) && in_array(trim($giaTri), $duoc, true) ? trim($giaTri) : null;
        }

        if (! is_array($giaTri) || ! array_is_list($giaTri) || count($giaTri) < (int) ($khuon[3] ?? 0)) {
            return null;
        }

        $ra = [];

        foreach (array_slice($giaTri, 0, (int) $khuon[1]) as $phan) {
            /** @var array<int|string, mixed> $phanTu */
            $phanTu = $khuon[2];
            $v = self::docPhan($phanTu, $phan);

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

        return match ($khuon[0]) {
            'chu' => ['type' => 'string'],
            'logic' => ['type' => 'boolean'],
            'so' => ['type' => 'integer'],
            // Danh sách có chuỗi rỗng (vd giống của từ không phải danh từ): không gửi enum, máy chủ vẫn kiểm
            'chon' => in_array('', (array) $khuon[1], true) ? ['type' => 'string'] : ['type' => 'string', 'enum' => array_values((array) $khuon[1])],
            default => ['type' => 'array', 'items' => self::schemaPhan((array) $khuon[2])],
        };
    }
}
