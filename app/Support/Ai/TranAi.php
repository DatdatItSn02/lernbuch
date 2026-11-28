<?php

namespace App\Support\Ai;

use App\Support\Hoc\LapLich;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Chặng 5: trần của cả hệ thống, kiểm trước khi gọi nhà cung cấp. Lượt mỗi phút, lượt mỗi ngày và chi phí quy đổi mỗi ngày
 * tính trên các lượt đã tới nhà cung cấp (không kể cache, không kể chấm phát âm). Ngày tính từ nửa đêm giờ Thái Bình Dương,
 * cùng lúc Google đổi hạn mức ngày. Số lượt gọi cùng lúc giữ bằng các khoá ai:cho:1 … ai:cho:N.
 */
final class TranAi
{
    public const MUI_GIO_NGAY = 'America/Los_Angeles';

    public const GIU_KHOA_GIAY = 40;

    /** Có chạm trần nào không (lượt mỗi phút, mỗi ngày, chi phí). @param array{rpm_cap: int, rpd_cap: int, daily_spend_cap_usd: int|float} $cd */
    public static function chamTran(array $cd): bool
    {
        $bay = now()->getTimestamp();
        $toiNhaCungCap = fn () => DB::table('ai_requests')->where('cache_hit', 0)->whereIn('status', ['ok', 'error'])->where('kind', '<>', 'pron');

        if ($toiNhaCungCap()->where('created_at', '>=', $bay - 60)->count() >= $cd['rpm_cap']) {
            return true;
        }

        $dauNgay = LapLich::dauNgay($bay, self::MUI_GIO_NGAY);

        if ($toiNhaCungCap()->where('created_at', '>=', $dauNgay)->count() >= $cd['rpd_cap']) {
            return true;
        }

        return (int) $toiNhaCungCap()->where('created_at', '>=', $dauNgay)->sum('cost_micro_usd') >= $cd['daily_spend_cap_usd'] * 1000000;
    }

    /** Giữ một chỗ gọi; hết chỗ thì null. Người gọi phải nhả (release) trong finally. @param array{max_concurrent: int} $cd */
    public static function giuCho(array $cd): ?Lock
    {
        for ($i = 1; $i <= $cd['max_concurrent']; $i++) {
            $khoa = Cache::lock('ai:cho:'.$i, self::GIU_KHOA_GIAY);

            if ($khoa->get()) {
                return $khoa;
            }
        }

        return null;
    }
}
