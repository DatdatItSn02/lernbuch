<?php

namespace App\Support\Hoc;

use Illuminate\Support\Facades\DB;

/**
 * Cấu hình của phần học: chế độ học, xu mỗi thẻ, ngưỡng chấm tối thiểu (sửa trong trang quản trị, lưu ở bảng settings).
 */
final class CaiDatHoc
{
    /** 7 chế độ học như Lernbuch: Flashcard, Quiz, Listening, Typing, Ghép cặp, Hỗn hợp, Đặt câu. */
    public const CHE_DO = ['flashcard', 'quiz', 'listening', 'typing', 'match', 'mixed', 'sentence'];

    /** Chế độ của lượt chấm thẻ: 7 chế độ học và Điền câu (bài tập làm từ câu có chỗ trống của thẻ, như Lernbuch). */
    public const CHE_DO_CHAM = [...self::CHE_DO, 'cloze'];

    /** Xu cho mỗi thẻ chấm Tốt hoặc Dễ, theo chế độ (Lernbuch: MODE_COINS). Khó được nửa (làm tròn xuống), Quên không có. */
    public const XU = ['flashcard' => 5, 'quiz' => 10, 'listening' => 15, 'typing' => 10, 'match' => 10, 'mixed' => 20, 'sentence' => 20, 'cloze' => 10];

    /**
     * Chấm nhanh hơn ngưỡng này (mili giây, tính từ lượt chấm trước hoặc từ lúc thẻ hiện) thì vẫn ghi tiến độ nhưng không cộng xu.
     * Giá trị mặc định khi quản trị chưa sửa.
     */
    public const NGUONG_MAC_DINH = ['flashcard' => 700, 'quiz' => 700, 'listening' => 1000, 'typing' => 1000, 'match' => 400, 'mixed' => 700, 'sentence' => 1500, 'cloze' => 1000];

    public const NGUONG_TOI_DA = 10000;

    /** Tên dòng trong bảng settings. */
    public const TEN_NGUONG = 'hoc.nguong_cham_ms';

    /** Lý do không cộng xu ghi vào review_logs.no_coin_reason. */
    public const CHAM_QUA_NHANH = 'too_fast';

    /** @return array<string, int> ngưỡng (ms) theo chế độ: giá trị đã lưu, thiếu hoặc lạ thì lấy mặc định */
    public static function nguong(): array
    {
        $daLuu = json_decode((string) DB::table('settings')->where('name', self::TEN_NGUONG)->value('value'), true);
        $ra = self::NGUONG_MAC_DINH;

        foreach (is_array($daLuu) ? $daLuu : [] as $cheDo => $ms) {
            if (isset($ra[$cheDo]) && is_int($ms) && $ms >= 0 && $ms <= self::NGUONG_TOI_DA) {
                $ra[$cheDo] = $ms;
            }
        }

        return $ra;
    }

    /** @param  array<string, int>  $nguong */
    public static function luuNguong(array $nguong, ?int $nguoiSua): void
    {
        $gon = self::nguong();

        foreach ($nguong as $cheDo => $ms) {
            if (isset($gon[$cheDo])) {
                $gon[$cheDo] = max(0, min(self::NGUONG_TOI_DA, $ms));
            }
        }

        DB::table('settings')->upsert(
            [['name' => self::TEN_NGUONG, 'value' => json_encode($gon), 'is_secret' => 0, 'updated_at' => now()->getTimestamp(), 'updated_by' => $nguoiSua]],
            ['name'],
            ['value', 'updated_at', 'updated_by'],
        );
    }

    /** Xu của một lượt chấm (0 Quên, 1 Khó, 2 Tốt, 3 Dễ) ở một chế độ. */
    public static function xu(string $cheDo, int $cham): int
    {
        $moiThe = self::XU[$cheDo] ?? 0;

        return match ($cham) {
            LapLich::KHO => intdiv($moiThe, 2),
            LapLich::TOT, LapLich::DE => $moiThe,
            default => 0,
        };
    }
}
