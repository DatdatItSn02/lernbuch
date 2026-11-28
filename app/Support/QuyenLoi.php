<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Quyền lợi dạng số theo gói (bảng plan_entitlements), vd ai_daily, pron_daily. Gói của một người là các gói đang chạy
 * (subscriptions trialing hoặc active, đã bắt đầu, chưa hết hạn); không có thì là gói free. Nhiều gói thì lấy số lớn nhất.
 * Ô plans.open_access bật thì lấy số lớn hơn giữa gói của mình và gói ở ô plans.open_access_plan. -1 là không giới hạn.
 */
final class QuyenLoi
{
    public const KHONG_GIOI_HAN = -1;

    public static function so(User $user, string $ma): int
    {
        $bay = now()->getTimestamp();
        $goi = DB::table('subscriptions')->where('user_id', $user->id)->whereIn('status', ['trialing', 'active'])
            ->where('starts_at', '<=', $bay)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $bay))
            ->pluck('plan_id')->map(fn ($v) => (int) $v)->all();

        if ($goi === []) {
            $free = DB::table('plans')->where('code', 'free')->value('id');
            $goi = $free !== null ? [(int) $free] : [];
        }

        if (self::caiDat('plans.open_access') === true) {
            $maGoi = self::caiDat('plans.open_access_plan');
            $moTam = is_string($maGoi) ? DB::table('plans')->where('code', $maGoi)->value('id') : null;

            if ($moTam !== null) {
                $goi[] = (int) $moTam;
            }
        }

        $ds = [];

        foreach (DB::table('plan_entitlements')->whereIn('plan_id', $goi)->where('entitlement_code', $ma)->pluck('value') as $v) {
            $so = json_decode((string) $v, true);

            if (is_int($so)) {
                $ds[] = $so;
            }
        }

        if (in_array(self::KHONG_GIOI_HAN, $ds, true)) {
            return self::KHONG_GIOI_HAN;
        }

        return $ds === [] ? 0 : max(0, ...$ds);
    }

    private static function caiDat(string $ten): mixed
    {
        return json_decode((string) DB::table('settings')->where('name', $ten)->value('value'), true);
    }
}
