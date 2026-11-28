<?php

namespace App\Support\Ai;

use App\Http\Middleware\KhachXemThu;
use App\Models\User;
use App\Support\PhatAm\ChamPhatAm;
use Carbon\CarbonImmutable;

/**
 * Chặng 1 và 2: AI có mở cho người này không (công tắc, danh sách thử), đủ tuổi chưa, đã đồng ý chưa.
 * Cũng cho khối "ai" trong GET /api/v1/toi để app biết hiện gì.
 */
final class TrangThaiAi
{
    /** @param array{enabled: bool, audience: string} $cd */
    public static function moChoNguoi(User $user, array $cd): bool
    {
        // Tài khoản khách xem thử dùng chung: không bao giờ dùng AI (không ai đồng ý thay được cho người khác)
        return $cd['enabled'] && ! KhachXemThu::la($user) && ($cd['audience'] === 'adults' || DanhSachThu::co($user));
    }

    /**
     * Ngày sinh + N năm <= hôm nay, cả hai là ngày lịch theo múi giờ của người học. Sinh 29/2 thì đủ tuổi vào 1/3.
     */
    public static function duTuoi(User $user, int $tuoi): bool
    {
        $ngaySinh = $user->getAttribute('birth_date');

        if (! is_int($ngaySinh)) {
            return false;
        }

        $sinh = CarbonImmutable::createFromTimestamp($ngaySinh, 'UTC');
        $homNay = CarbonImmutable::now($user->timezone)->format('Y-m-d');

        return $sinh->addYears($tuoi)->format('Y-m-d') <= $homNay;
    }

    /** Chặng 1 và 2: không qua thì ném TuChoiAi. @param array{enabled: bool, audience: string, min_age: int} $cd */
    public static function kiem(User $user, array $cd): void
    {
        if (! $cd['enabled']) {
            throw new TuChoiAi('ai_tam_tat', 503);
        }

        if (! self::moChoNguoi($user, $cd)) {
            throw new TuChoiAi('ai_chua_mo', 403);
        }

        if ($user->getAttribute('birth_date') === null) {
            throw new TuChoiAi('ai_can_ngay_sinh', 403);
        }

        if (! self::duTuoi($user, CaiDatAi::tuoiToiThieu($cd))) {
            throw new TuChoiAi('ai_chua_du_tuoi', 403);
        }

        if (! DongYAi::dangDongY($user)) {
            throw new TuChoiAi('ai_can_dong_y', 403);
        }
    }

    /** @return array<string, mixed> khối "ai" của GET /toi */
    public static function choToi(User $user): array
    {
        $cd = CaiDatAi::tatCa();

        if (! self::moChoNguoi($user, $cd)) {
            return ['state' => 'hidden'];
        }

        $tuoi = CaiDatAi::tuoiToiThieu($cd);

        if ($user->getAttribute('birth_date') === null) {
            return ['state' => 'need_birth_date', 'min_age' => $tuoi];
        }

        if (! self::duTuoi($user, $tuoi)) {
            return ['state' => 'underage', 'min_age' => $tuoi];
        }

        if (! DongYAi::dangDongY($user)) {
            return ['state' => 'need_consent', 'min_age' => $tuoi, 'consent_version' => DongYAi::PHIEN_BAN];
        }

        $tt = HanMucAi::trangThai($user, $cd);

        return [
            'state' => 'ready', 'consent_version' => DongYAi::PHIEN_BAN, 'limit' => $tt['limit'], 'used' => $tt['used'], 'reset_at' => $tt['reset_at'],
            'pron' => ChamPhatAm::choToi($user),
        ];
    }
}
