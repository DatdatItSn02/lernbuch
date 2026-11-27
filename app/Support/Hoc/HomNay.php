<?php

namespace App\Support\Hoc;

use App\Models\User;
use App\Models\UserSetting;
use App\Support\NoiDung;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

/** Số liệu của trang Hôm nay, theo ngày của người học (múi giờ trong hồ sơ). */
final class HomNay
{
    public const NGAY_QUA = 10;

    public const NGAY_TOI = 3;

    /** Số chủ đề nhỏ hiện ở mục "Đến hạn theo chủ đề". */
    public const CHU_DE_DEN_HAN = 8;

    /** @return array<string, mixed> */
    public static function cua(User $user): array
    {
        $tz = $user->timezone;
        $now = now()->getTimestamp();
        $homNay = LapLich::dauNgay($now, $tz);
        $mai = LapLich::dauNgayMai($now, $tz);
        $caiDat = UserSetting::cua($user);

        $denHanMuc = PhienHoc::denHan($user, $mai)->selectRaw('p.box AS muc, COUNT(*) AS n')->groupBy('p.box')->pluck('n', 'muc');
        $denHanTheoMuc = array_map(fn (int $m) => (int) ($denHanMuc[$m] ?? 0), range(0, LapLich::MUC_TOI_DA));
        [$moi, $chuDeMoi] = PhienHoc::xemTuMoi($user);
        $dangHoc = $chuDeMoi ?? PhienHoc::chuDeDangHoc($user);

        $tong = DB::table('user_stats')->where('user_id', $user->id)->first();
        $homQua = LapLich::dauNgayTruoc($homNay, $tz);
        $chuoi = $tong !== null && $tong->streak_last_day !== null && (int) $tong->streak_last_day >= $homQua ? (int) $tong->streak_current : 0;
        $thongKeHomNay = DB::table('user_daily_stats')->where('user_id', $user->id)->where('day_start', $homNay)->first();
        $daOn = $thongKeHomNay !== null ? (int) $thongKeHomNay->reviews + (int) $thongKeHomNay->new_cards : 0;

        return [
            'date' => self::ngay($homNay, $tz),
            'timezone' => $tz,
            'due' => array_sum($denHanTheoMuc),
            'due_by_level' => $denHanTheoMuc,
            'levels' => PhienHoc::demTheoMuc(PhienHoc::theVaTienDo($user)),
            'new_next' => count($moi),
            // Từ chưa học còn lại của loại thẻ đang học (phiên lấy tiếp sang chủ đề kế): app tính lại số từ mới khi đổi số mỗi lượt
            'new_available' => $dangHoc !== null ? PhienHoc::chuaHoc($user)->where('cards.collection', (string) $dangHoc->collection)->whereNotNull('cards.topic_id')->count() : 0,
            'new_topic' => $dangHoc !== null ? [
                ...NoiDung::tenGon($dangHoc),
                'new' => PhienHoc::tienDoChuDe($user, $dangHoc)['new'],
            ] : null,
            'reviewed_today' => $daOn,
            'goal' => $caiDat['daily_goal'],
            'coins' => $tong !== null ? (int) $tong->coins_balance : 0,
            'coins_today' => $thongKeHomNay !== null ? (int) $thongKeHomNay->coins : 0,
            'streak' => $chuoi,
            'streak_longest' => $tong !== null ? (int) $tong->streak_longest : 0,
            'days' => self::muoiBonNgay($user, $homNay, $daOn),
            'due_topics' => self::denHanTheoChuDe($user, $mai),
        ];
    }

    /**
     * 10 ngày qua (số lượt chấm), hôm nay, 3 ngày tới (số thẻ sẽ đến hạn).
     *
     * @return list<array{date: string, count: int, kind: string}>
     */
    private static function muoiBonNgay(User $user, int $homNay, int $daOn): array
    {
        $tz = $user->timezone;
        $qua = [];
        $d = $homNay;

        for ($i = 0; $i < self::NGAY_QUA; $i++) {
            $d = LapLich::dauNgayTruoc($d, $tz);
            array_unshift($qua, $d);
        }

        $dem = DB::table('user_daily_stats')->where('user_id', $user->id)->whereBetween('day_start', [$qua[0], $homNay - 1])
            ->selectRaw('day_start, reviews + new_cards AS n')->pluck('n', 'day_start');
        $ra = array_map(fn (int $d) => ['date' => self::ngay($d, $tz), 'count' => (int) ($dem[$d] ?? 0), 'kind' => 'past'], $qua);
        $ra[] = ['date' => self::ngay($homNay, $tz), 'count' => $daOn, 'kind' => 'today'];

        $tu = LapLich::dauNgayMai($homNay, $tz);

        for ($i = 0; $i < self::NGAY_TOI; $i++) {
            $den = LapLich::dauNgayMai($tu, $tz);
            $ra[] = [
                'date' => self::ngay($tu, $tz),
                'count' => PhienHoc::denHan($user, $den)->where('p.due_at', '>=', $tu)->count(),
                'kind' => 'future',
            ];
            $tu = $den;
        }

        return $ra;
    }

    /** @return list<array<string, mixed>> chủ đề nhỏ có nhiều thẻ đến hạn nhất, kèm số thẻ theo mức */
    private static function denHanTheoChuDe(User $user, int $mai): array
    {
        $dem = PhienHoc::denHan($user, $mai)->whereNotNull('cards.topic_id')->selectRaw('cards.topic_id AS id, COUNT(*) AS n')
            ->groupBy('cards.topic_id')->orderByDesc('n')->orderBy('cards.topic_id')->limit(self::CHU_DE_DEN_HAN)->pluck('n', 'id');

        if ($dem->isEmpty()) {
            return [];
        }

        $ids = $dem->keys()->map(fn ($v) => (int) $v)->all();
        $chuDe = NoiDung::cacChuDe()->whereIn('id', $ids)->get()->keyBy('id');
        $muc = PhienHoc::theVaTienDo($user)->whereIn('cards.topic_id', $ids)
            ->selectRaw('cards.topic_id AS id, COALESCE(p.box, 0) AS muc, COUNT(*) AS n')->groupBy('cards.topic_id')->groupByRaw('COALESCE(p.box, 0)')->get();
        $theoMuc = [];

        foreach ($muc as $m) {
            $theoMuc[(int) $m->id][(int) $m->muc] = (int) $m->n;
        }

        $ra = [];

        foreach ($ids as $id) {
            if (! isset($chuDe[$id])) {
                continue;
            }

            $levels = array_map(fn (int $m) => $theoMuc[$id][$m] ?? 0, range(0, LapLich::MUC_TOI_DA));
            $ra[] = [...NoiDung::tenGon($chuDe[$id]), 'collection' => (string) $chuDe[$id]->collection, 'due' => (int) $dem[$id], 'new' => $levels[0], 'levels' => $levels];
        }

        return $ra;
    }

    private static function ngay(int $luc, string $tz): string
    {
        return (new DateTimeImmutable('@'.$luc))->setTimezone(new DateTimeZone($tz))->format('Y-m-d');
    }
}
