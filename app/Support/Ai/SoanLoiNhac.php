<?php

namespace App\Support\Ai;

/**
 * Ghép prompt gửi AI từ prompt hệ thống và mẫu câu của một loại. Chữ người học nằm trong thẻ <input> của mẫu;
 * mọi thẻ <input> hay </input> giả trong tham số bị vô hiệu (đổi "<" thành "‹"), ký tự điều khiển bị bỏ.
 * Thay {ten} bằng strtr với đúng các tham số của loại: ngoặc nhọn của khuôn JSON trong prompt hệ thống còn nguyên,
 * và chữ người học có "{meaning}" cũng không bị thay lần nữa.
 */
final class SoanLoiNhac
{
    /** Tham số mỗi loại dùng trong mẫu câu (user_template). {level} của prompt hệ thống luôn có. */
    public const THAM_SO = [
        'translate' => ['target', 'text'],
        'lookup' => ['text'],
        'lesson' => ['level', 'topic', 'words'],
        'chat' => ['scenario', 'history', 'text'],
        'grammar' => ['text'],
        'sentence' => ['word', 'meaning', 'text'],
        'answer' => ['expected', 'prompt', 'text'],
        'word' => ['word', 'meaning'],
        'doc' => ['text'],
        'ask' => ['text'],
    ];

    /** Tham số có chữ do người học gõ (hoặc tin cũ của họ): mẫu câu phải đặt chúng giữa đúng một cặp thẻ <input>. */
    public const NGUOI_GO = ['text', 'history', 'topic', 'words'];

    /** Bỏ ký tự điều khiển (giữ tab, xuống dòng), rồi vô hiệu mọi thẻ input, kể cả viết hoa, có dấu cách hay xuống dòng. */
    public static function lamSach(string $chu): string
    {
        $chu = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $chu) ?? '';

        return preg_replace('/<(?=\s*\/?\s*input\b)/iu', '‹', $chu) ?? '';
    }

    /**
     * @param  array<string, string>  $thamSo
     * @return array{0: string, 1: string} [prompt hệ thống, lời nhắc người dùng]
     */
    public static function soan(string $loai, string $heThong, string $mau, array $thamSo): array
    {
        $thay = [];

        foreach (self::THAM_SO[$loai] ?? [] as $ten) {
            $thay['{'.$ten.'}'] = self::lamSach((string) ($thamSo[$ten] ?? ''));
        }

        return [
            strtr($heThong, ['{level}' => self::lamSach((string) ($thamSo['level'] ?? 'A1'))]),
            strtr($mau, $thay),
        ];
    }
}
