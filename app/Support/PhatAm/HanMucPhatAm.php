<?php

namespace App\Support\PhatAm;

use App\Models\User;
use App\Support\Ai\CaiDatAi;
use App\Support\Hoc\LapLich;
use App\Support\QuyenLoi;
use Illuminate\Support\Facades\DB;

/**
 * Hạn mức chấm phát âm: lượt mỗi ngày của một người (ngày theo múi giờ người đó, chỉ đếm lượt Azure chấm được) và số giây
 * đã gửi Azure trong tháng (tháng theo UTC, như cách Azure tính gói miễn phí).
 */
final class HanMucPhatAm
{
    /** Giây âm thanh đã gửi Azure từ đầu tháng (UTC); dòng không gửi đi (im lặng, chặn trước) có audio_ms rỗng. */
    public static function giayThangNay(): int
    {
        $dauThang = now('UTC')->startOfMonth()->getTimestamp();
        $ms = (int) DB::table('pronunciation_attempts')->where('provider', 'azure')->where('created_at', '>=', $dauThang)->sum('audio_ms');

        return (int) ceil($ms / 1000);
    }

    /** Hạn mức theo gói; giai đoạn thử (AI chỉ cho danh sách thử) thì lấy số nhỏ hơn giữa gói và ô hạn mức giai đoạn thử. */
    public static function hanMuc(User $user): int
    {
        $so = QuyenLoi::so($user, 'pron_daily');

        if (CaiDatAi::tatCa()['audience'] === 'testers') {
            $thu = CaiDatPhatAm::tatCa()['trial_daily_cap'];
            $so = $so === QuyenLoi::KHONG_GIOI_HAN ? $thu : min($so, $thu);
        }

        return $so;
    }

    /** @return array{limit: int, used: int, reset_at: int} */
    public static function trangThai(User $user): array
    {
        $bay = now()->getTimestamp();

        return [
            'limit' => self::hanMuc($user),
            'used' => DB::table('pronunciation_attempts')->where('user_id', $user->id)->where('provider', 'azure')->where('status', 'ok')
                ->where('created_at', '>=', LapLich::dauNgay($bay, $user->timezone))->count(),
            'reset_at' => LapLich::dauNgayMai($bay, $user->timezone),
        ];
    }

    /** @param array{limit: int, used: int} $tt */
    public static function conLuot(array $tt): bool
    {
        return $tt['limit'] === QuyenLoi::KHONG_GIOI_HAN || $tt['used'] < $tt['limit'];
    }

    /**
     * @param  array{limit: int, used: int, reset_at: int}  $tt
     * @return array{limit: int, used: int, remaining: int, reset_at: int}
     */
    public static function meta(array $tt): array
    {
        return [
            'limit' => $tt['limit'],
            'used' => $tt['used'],
            'remaining' => $tt['limit'] === QuyenLoi::KHONG_GIOI_HAN ? -1 : max(0, $tt['limit'] - $tt['used']),
            'reset_at' => $tt['reset_at'],
        ];
    }
}
