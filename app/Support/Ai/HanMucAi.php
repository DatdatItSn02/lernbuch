<?php

namespace App\Support\Ai;

use App\Models\User;
use App\Support\Hoc\LapLich;
use App\Support\QuyenLoi;
use Illuminate\Support\Facades\DB;

/**
 * Chặng 3: lượt AI mỗi ngày của một người, ngày tính theo múi giờ của người đó. Chỉ lượt gọi tới nhà cung cấp mà thành công
 * mới bị trừ (status ok, không lấy từ cache); lượt lỗi, lượt sai khuôn, lượt bị chặn không bị trừ. Chấm phát âm đếm riêng.
 */
final class HanMucAi
{
    /**
     * @param  array{audience: string, trial_daily_cap: int}  $cd
     * @return array{limit: int, used: int, reset_at: int}
     */
    public static function trangThai(User $user, array $cd): array
    {
        $bay = now()->getTimestamp();

        return [
            'limit' => self::hanMuc($user, $cd),
            'used' => DB::table('ai_requests')->where('user_id', $user->id)->where('created_at', '>=', LapLich::dauNgay($bay, $user->timezone))
                ->where('status', 'ok')->where('cache_hit', 0)->where('kind', '<>', 'pron')->count(),
            'reset_at' => LapLich::dauNgayMai($bay, $user->timezone),
        ];
    }

    /** Hạn mức theo gói; giai đoạn thử (chỉ danh sách thử) thì lấy số nhỏ hơn giữa gói và ô Hạn mức giai đoạn thử. -1 là không giới hạn. @param array{audience: string, trial_daily_cap: int} $cd */
    public static function hanMuc(User $user, array $cd): int
    {
        $so = QuyenLoi::so($user, 'ai_daily');

        if ($cd['audience'] === 'testers') {
            $so = $so === QuyenLoi::KHONG_GIOI_HAN ? $cd['trial_daily_cap'] : min($so, $cd['trial_daily_cap']);
        }

        return $so;
    }

    /** @param array{limit: int, used: int} $tt */
    public static function conLuot(array $tt): bool
    {
        return $tt['limit'] === QuyenLoi::KHONG_GIOI_HAN || $tt['used'] < $tt['limit'];
    }

    /**
     * Phần "meta" trả về cùng câu trả lời.
     *
     * @param  array{limit: int, used: int, reset_at: int}  $tt
     * @return array{cached: bool, limit: int, used: int, remaining: int, reset_at: int}
     */
    public static function meta(array $tt, bool $tuCache): array
    {
        return [
            'cached' => $tuCache,
            'limit' => $tt['limit'],
            'used' => $tt['used'],
            'remaining' => $tt['limit'] === QuyenLoi::KHONG_GIOI_HAN ? -1 : max(0, $tt['limit'] - $tt['used']),
            'reset_at' => $tt['reset_at'],
        ];
    }
}
