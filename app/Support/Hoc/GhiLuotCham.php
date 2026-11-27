<?php

namespace App\Support\Hoc;

use App\Models\User;
use App\Models\UserSetting;
use App\Support\NoiDung;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Ghi một lô lượt chấm của một người, trong một transaction (mục tự kiểm 4, 6, 7, 8):
 * - Mỗi lượt có mã chống trùng do máy tạo (ULID): gửi lại lô cũ thì lượt đã nhận bỏ qua, xu không cộng lần hai.
 * - Lượt xếp theo giờ chấm trên máy. Lượt tới trễ (máy khác, hàng chờ khi mất mạng) có giờ chấm không sau lượt cuối
 *   đã ghi của thẻ thì tính lại tiến độ thẻ đó từ đầu theo giờ chấm, nên lô nào tới trước kết quả vẫn như nhau.
 * - Chấm nhanh hơn ngưỡng của chế độ (so với lượt trước, hoặc thời gian thẻ hiện trên màn hình) thì vẫn ghi tiến độ
 *   nhưng không cộng xu; lý do ghi ở review_logs.no_coin_reason.
 * - Cộng xu (sổ xu ghi theo phiên), thống kê ngày, chuỗi ngày, số liệu tổng.
 * - Thẻ không xem được (đã gỡ, thẻ riêng của người khác) thì bỏ lượt đó; phiên của người khác thì báo 404.
 */
final class GhiLuotCham
{
    /** Giờ chấm trên máy được chạy nhanh hơn giờ máy chủ tối đa chừng này; quá thì coi là chấm lúc máy chủ nhận. */
    public const LECH_GIO_MS = 60_000;

    /** Một lượt tính tối đa chừng này mili giây vào thời gian học trong ngày. */
    public const THOI_GIAN_TOI_DA_MS = 60_000;

    /**
     * @param  list<array{uid: string, card_id: int, session_id: int|null, mode: string, rating: int, reviewed_at_ms: int, duration_ms: int|null}>  $luot
     * @return array<string, mixed>
     */
    public static function ghi(User $user, array $luot): array
    {
        // Phiên ghi trong lượt phải là của chính người này: kiểm trước khi ghi gì
        $phienIds = array_values(array_unique(array_filter(array_column($luot, 'session_id'), fn ($v) => $v !== null)));
        $phien = DB::table('study_sessions')->where('user_id', $user->id)->whereIn('id', $phienIds)->get()->keyBy('id');

        if ($phien->count() !== count($phienIds)) {
            abort(404);
        }

        return DB::transaction(fn () => self::trongGiaoDich($user, $luot), 3);
    }

    /**
     * @param  list<array{uid: string, card_id: int, session_id: int|null, mode: string, rating: int, reviewed_at_ms: int, duration_ms: int|null}>  $luot
     * @return array<string, mixed>
     */
    private static function trongGiaoDich(User $user, array $luot): array
    {
        $tz = $user->timezone;
        $now = now();
        $giay = $now->getTimestamp();
        $nowMs = (int) $now->getTimestampMs();

        // Khoá theo người: mỗi lúc chỉ một lô của người này được xử lý, lô thứ hai (máy khác) chờ lô đầu xong
        DB::table('user_stats')->insertOrIgnore(['user_id' => $user->id, 'updated_at' => $giay]);
        $tong = DB::table('user_stats')->where('user_id', $user->id)->lockForUpdate()->first();

        // 1. Bỏ lượt đã nhận (gửi trùng), kể cả trùng ngay trong lô
        $daNhan = DB::table('review_logs')->where('user_id', $user->id)->whereIn('client_uid', array_column($luot, 'uid'))->pluck('client_uid')->flip()->all();
        $trung = [];
        $moi = [];

        foreach ($luot as $l) {
            if (isset($daNhan[$l['uid']]) || isset($moi[$l['uid']])) {
                $trung[] = $l['uid'];
            } else {
                $moi[$l['uid']] = $l;
            }
        }

        // 2. Bỏ lượt của thẻ không xem được
        $xemDuoc = NoiDung::cacThe($user)->whereIn('cards.id', array_values(array_unique(array_column($moi, 'card_id'))))->pluck('cards.id')
            ->map(fn ($v) => (int) $v)->flip()->all();
        $bo = [];

        foreach ($moi as $uid => $l) {
            if (! isset($xemDuoc[$l['card_id']])) {
                $bo[] = $uid;
                unset($moi[$uid]);
            }
        }

        if ($moi === []) {
            return self::ketQua($user, 0, $trung, $bo, 0, 0, []);
        }

        // 3. Giờ chấm: không trước lúc mở phiên, không quá xa giờ máy chủ; xếp theo giờ chấm, cùng giờ thì theo mã
        $batDau = DB::table('study_sessions')->where('user_id', $user->id)->whereIn('id', array_values(array_unique(array_filter(array_column($moi, 'session_id')))))
            ->pluck('started_at', 'id')->map(fn ($v) => (int) $v)->all();
        $ds = [];

        foreach ($moi as $l) {
            $ms = $l['reviewed_at_ms'] > $nowMs + self::LECH_GIO_MS ? $nowMs : $l['reviewed_at_ms'];

            if ($l['session_id'] !== null && $ms < $batDau[$l['session_id']] * 1000) {
                $ms = $batDau[$l['session_id']] * 1000;
            }

            $ds[] = [...$l, 'ms' => $ms, 'giay' => intdiv($ms, 1000)];
        }

        usort($ds, fn (array $a, array $b) => [$a['ms'], $a['uid']] <=> [$b['ms'], $b['uid']]);

        // 4. Chấm quá nhanh (cách lượt trước trong lô, hoặc lượt cuối đã ghi nếu lượt này sau nó; hay thẻ hiện quá ngắn): không cộng xu
        $nguong = CaiDatHoc::nguong();
        $cuoiDaGhi = DB::table('review_logs')->where('user_id', $user->id)->max('reviewed_at');
        $quaNhanh = 0;

        foreach ($ds as $k => $l) {
            $muc = $nguong[$l['mode']];
            $truocMs = $k > 0 ? $ds[$k - 1]['ms'] : ($cuoiDaGhi !== null ? (int) $cuoiDaGhi * 1000 : null);
            $nhanh = ($l['duration_ms'] !== null && $l['duration_ms'] < $muc) || ($truocMs !== null && $l['ms'] >= $truocMs && $l['ms'] - $truocMs < $muc);
            $ds[$k]['xu'] = $nhanh ? 0 : CaiDatHoc::xu($l['mode'], $l['rating']);
            $ds[$k]['ly_do'] = $nhanh ? CaiDatHoc::CHAM_QUA_NHANH : null;
            $quaNhanh += $nhanh ? 1 : 0;
        }

        // 5. Tiến độ từng thẻ: lượt mới hơn lượt cuối đã ghi thì tính tiếp; lượt tới trễ thì tính lại cả thẻ sau khi ghi nhật ký
        $theoThe = [];

        foreach ($ds as $i => $l) {
            $theoThe[$l['card_id']][] = $i;
        }

        $cu = DB::table('card_progress')->where('user_id', $user->id)->whereIn('card_id', array_keys($theoThe))->get()->keyBy('card_id');
        $tienDo = [];
        $tinhLai = [];

        foreach ($theoThe as $theId => $cacLuot) {
            $p = $cu[$theId] ?? null;

            if ($p !== null && $ds[$cacLuot[0]]['giay'] <= (int) $p->last_seen_at) {
                $tinhLai[] = $theId;

                continue;
            }

            $tt = $p !== null ? self::tuDong($p) : self::trong();

            foreach ($cacLuot as $i) {
                $ds[$i]['truoc'] = $tt['box'];
                $ds[$i]['moi'] = $tt['reps'] === 0;
                $tt = self::chamMot($tt, $ds[$i], $tz);
                $ds[$i]['sau'] = $tt['box'];
            }

            $tienDo[$theId] = $tt;
        }

        $nhatKy = [];

        foreach ($ds as $i => $l) {
            $nhatKy[] = [
                'user_id' => $user->id,
                'card_id' => $l['card_id'],
                'session_id' => $l['session_id'],
                'client_uid' => $l['uid'],
                'mode' => $l['mode'],
                'rating' => $l['rating'],
                'box_before' => $l['truoc'] ?? 0,
                'box_after' => $l['sau'] ?? 0,
                'coins' => $l['xu'],
                'no_coin_reason' => $l['ly_do'],
                'duration_ms' => $l['duration_ms'],
                'reviewed_at' => $l['giay'],
            ];
        }

        foreach (array_chunk($nhatKy, 500) as $lo) {
            DB::table('review_logs')->insert($lo);
        }

        foreach ($tinhLai as $theId) {
            $tienDo[$theId] = self::tinhLaiThe($user->id, $theId, $tz, $ds, $theoThe[$theId]);
        }

        // 6. Ghi tiến độ, cập nhật phiên, thống kê ngày, số liệu tổng, sổ xu
        DB::table('card_progress')->upsert(
            array_map(fn (int $theId) => ['user_id' => $user->id, 'card_id' => $theId, ...$tienDo[$theId]], array_keys($tienDo)),
            ['user_id', 'card_id'],
            ['box', 'due_at', 'reps', 'lapses', 'last_rating', 'first_seen_at', 'last_seen_at'],
        );

        self::capNhatPhien($user->id, $ds);
        $caiDat = UserSetting::cua($user);
        $ngay = self::capNhatNgay($user->id, $ds, $tz, $caiDat['daily_goal']);

        $theMoi = count(array_filter(array_keys($tienDo), fn (int $theId) => ! isset($cu[$theId])));
        $thanhThao = 0;

        foreach ($tienDo as $theId => $tt) {
            $truoc = isset($cu[$theId]) ? (int) $cu[$theId]->box : 0;
            $thanhThao += ($tt['box'] === LapLich::MUC_TOI_DA ? 1 : 0) - ($truoc === LapLich::MUC_TOI_DA ? 1 : 0);
        }

        $xu = array_sum(array_column($ds, 'xu'));
        [$chuoi, $daiNhat, $ngayCuoi] = self::chuoi($user->id, $tong, $ngay, $tz);
        $soDu = (int) $tong->coins_balance + $xu;

        DB::table('user_stats')->where('user_id', $user->id)->update([
            'coins_total' => (int) $tong->coins_total + $xu,
            'coins_balance' => $soDu,
            'streak_current' => $chuoi,
            'streak_longest' => $daiNhat,
            'streak_last_day' => $ngayCuoi,
            'cards_seen' => (int) $tong->cards_seen + $theMoi,
            'cards_mastered' => max(0, (int) $tong->cards_mastered + $thanhThao),
            'reviews_total' => (int) $tong->reviews_total + count($ds),
            'updated_at' => $giay,
        ]);

        self::ghiSoXu($user->id, $ds, (int) $tong->coins_balance, $giay);

        return self::ketQua($user, count($ds), $trung, $bo, $xu, $quaNhanh, $tienDo);
    }

    /** @return array{box: int, due_at: int|null, reps: int, lapses: int, last_rating: int|null, first_seen_at: int|null, last_seen_at: int|null} */
    private static function trong(): array
    {
        return ['box' => 0, 'due_at' => null, 'reps' => 0, 'lapses' => 0, 'last_rating' => null, 'first_seen_at' => null, 'last_seen_at' => null];
    }

    /** @return array{box: int, due_at: int|null, reps: int, lapses: int, last_rating: int|null, first_seen_at: int|null, last_seen_at: int|null} */
    private static function tuDong(stdClass $p): array
    {
        return [
            'box' => (int) $p->box,
            'due_at' => $p->due_at !== null ? (int) $p->due_at : null,
            'reps' => (int) $p->reps,
            'lapses' => (int) $p->lapses,
            'last_rating' => $p->last_rating !== null ? (int) $p->last_rating : null,
            'first_seen_at' => $p->first_seen_at !== null ? (int) $p->first_seen_at : null,
            'last_seen_at' => $p->last_seen_at !== null ? (int) $p->last_seen_at : null,
        ];
    }

    /**
     * @param  array{box: int, due_at: int|null, reps: int, lapses: int, last_rating: int|null, first_seen_at: int|null, last_seen_at: int|null}  $tt
     * @param  array{rating: int, giay: int}  $l
     * @return array{box: int, due_at: int|null, reps: int, lapses: int, last_rating: int|null, first_seen_at: int|null, last_seen_at: int|null}
     */
    private static function chamMot(array $tt, array $l, string $tz): array
    {
        $kq = LapLich::cham($tt['box'], $l['rating'], $l['giay'], $tz);

        return [
            'box' => $kq['muc'],
            'due_at' => $kq['han'],
            'reps' => min(65535, $tt['reps'] + 1),
            'lapses' => min(65535, $tt['lapses'] + ($kq['quen'] ? 1 : 0)),
            'last_rating' => $l['rating'],
            'first_seen_at' => $tt['first_seen_at'] ?? $l['giay'],
            'last_seen_at' => $l['giay'],
        ];
    }

    /**
     * Tính lại tiến độ một thẻ từ mọi lượt đã ghi, theo giờ chấm (cùng giây thì theo mã lượt); sửa luôn mức trước/sau trong nhật ký.
     *
     * @param  list<array<string, mixed>>  $ds
     * @param  list<int>  $cacLuot
     * @return array{box: int, due_at: int|null, reps: int, lapses: int, last_rating: int|null, first_seen_at: int|null, last_seen_at: int|null}
     */
    private static function tinhLaiThe(int $userId, int $theId, string $tz, array &$ds, array $cacLuot): array
    {
        $tt = self::trong();
        $cuaLo = [];

        foreach ($cacLuot as $i) {
            $cuaLo[$ds[$i]['uid']] = $i;
        }

        $nhatKy = DB::table('review_logs')->where('user_id', $userId)->where('card_id', $theId)
            ->orderBy('reviewed_at')->orderBy('client_uid')->orderBy('id')->get(['id', 'reviewed_at', 'client_uid', 'rating', 'box_before', 'box_after']);

        foreach ($nhatKy as $n) {
            $truoc = $tt['box'];
            $tt = self::chamMot($tt, ['rating' => (int) $n->rating, 'giay' => (int) $n->reviewed_at], $tz);

            if ((int) $n->box_before !== $truoc || (int) $n->box_after !== $tt['box']) {
                DB::table('review_logs')->where('id', $n->id)->where('reviewed_at', $n->reviewed_at)->update(['box_before' => $truoc, 'box_after' => $tt['box']]);
            }

            if ($n->client_uid !== null && isset($cuaLo[$n->client_uid])) {
                $i = $cuaLo[$n->client_uid];
                $ds[$i]['truoc'] = $truoc;
                $ds[$i]['sau'] = $tt['box'];
                $ds[$i]['moi'] = $tt['reps'] === 1;
            }
        }

        return $tt;
    }

    /** @param  list<array<string, mixed>>  $ds */
    private static function capNhatPhien(int $userId, array $ds): void
    {
        $theoPhien = [];

        foreach ($ds as $l) {
            if ($l['session_id'] !== null) {
                $theoPhien[$l['session_id']][] = $l;
            }
        }

        foreach ($theoPhien as $id => $cacLuot) {
            $p = DB::table('study_sessions')->where('id', $id)->where('user_id', $userId)->first();

            if ($p === null) {
                continue;
            }

            $dem = json_decode((string) $p->ratings, true);
            $dem = is_array($dem) ? $dem : [];

            foreach ($cacLuot as $l) {
                $dem[(string) $l['rating']] = (int) ($dem[(string) $l['rating']] ?? 0) + 1;
            }

            ksort($dem);
            $moi = count(array_filter($cacLuot, fn (array $l) => (bool) ($l['moi'] ?? false)));
            $lenMuc = count(array_filter($cacLuot, fn (array $l) => ($l['sau'] ?? 0) > ($l['truoc'] ?? 0)));

            DB::table('study_sessions')->where('id', $id)->update([
                'done_new' => min(65535, (int) $p->done_new + $moi),
                'done_review' => min(65535, (int) $p->done_review + count($cacLuot) - $moi),
                'leveled_up' => min(65535, (int) $p->leveled_up + $lenMuc),
                'ratings' => json_encode($dem),
                'coins' => (int) $p->coins + array_sum(array_column($cacLuot, 'xu')),
                'ended_at' => max((int) $p->ended_at, max(array_column($cacLuot, 'giay'))),
            ]);
        }
    }

    /**
     * Thống kê từng ngày (theo múi giờ người học) có lượt trong lô.
     *
     * @param  list<array<string, mixed>>  $ds
     * @return list<int> các ngày có lượt chấm
     */
    private static function capNhatNgay(int $userId, array $ds, string $tz, int $mucTieu): array
    {
        $theoNgay = [];

        foreach ($ds as $l) {
            $d = LapLich::dauNgay($l['giay'], $tz);
            $theoNgay[$d] ??= ['reviews' => 0, 'new_cards' => 0, 'correct' => 0, 'coins' => 0, 'study_seconds' => 0];
            $theoNgay[$d][($l['moi'] ?? false) ? 'new_cards' : 'reviews']++;
            $theoNgay[$d]['correct'] += $l['rating'] >= LapLich::KHO ? 1 : 0;
            $theoNgay[$d]['coins'] += $l['xu'];
            $theoNgay[$d]['study_seconds'] += min(self::THOI_GIAN_TOI_DA_MS, (int) ($l['duration_ms'] ?? 0));
        }

        foreach ($theoNgay as $d => $x) {
            $co = DB::table('user_daily_stats')->where('user_id', $userId)->where('day_start', $d)->first();
            $giay = intdiv($x['study_seconds'], 1000);

            if ($co === null) {
                DB::table('user_daily_stats')->insert([
                    'user_id' => $userId,
                    'day_start' => $d,
                    'reviews' => min(65535, $x['reviews']),
                    'new_cards' => min(65535, $x['new_cards']),
                    'correct' => min(65535, $x['correct']),
                    'coins' => $x['coins'],
                    'study_seconds' => $giay,
                    'goal_met' => $x['reviews'] + $x['new_cards'] >= $mucTieu ? 1 : 0,
                ]);

                continue;
            }

            $on = min(65535, (int) $co->reviews + $x['reviews']);
            $moi = min(65535, (int) $co->new_cards + $x['new_cards']);
            DB::table('user_daily_stats')->where('user_id', $userId)->where('day_start', $d)->update([
                'reviews' => $on,
                'new_cards' => $moi,
                'correct' => min(65535, (int) $co->correct + $x['correct']),
                'coins' => (int) $co->coins + $x['coins'],
                'study_seconds' => (int) $co->study_seconds + $giay,
                'goal_met' => (int) $co->goal_met === 1 || $on + $moi >= $mucTieu ? 1 : 0,
            ]);
        }

        return array_keys($theoNgay);
    }

    /**
     * Chuỗi ngày học liên tiếp sau lô này. Lô chỉ có ngày từ ngày học cuối trở đi thì tính tiếp; có ngày cũ hơn
     * (lượt tới trễ) thì đếm lại từ ngày học gần nhất lùi về.
     *
     * @param  list<int>  $ngay
     * @return array{0: int, 1: int, 2: int|null} chuỗi hiện tại, dài nhất, ngày học cuối
     */
    private static function chuoi(int $userId, stdClass $tong, array $ngay, string $tz): array
    {
        sort($ngay);
        $cuoi = $tong->streak_last_day !== null ? (int) $tong->streak_last_day : null;
        $dai = (int) $tong->streak_current;

        if ($cuoi === null || $ngay[0] >= $cuoi) {
            foreach ($ngay as $d) {
                if ($cuoi === null || ($d !== $cuoi && $d !== LapLich::dauNgayMai($cuoi, $tz))) {
                    $dai = 1;
                } elseif ($d !== $cuoi) {
                    $dai++;
                }

                $cuoi = $d;
            }
        } else {
            $cuoi = max($cuoi, $ngay[count($ngay) - 1]);
            $dai = self::demChuoi($userId, $cuoi, $tz);
        }

        return [$dai, max((int) $tong->streak_longest, $dai), $cuoi];
    }

    private static function demChuoi(int $userId, int $tu, string $tz): int
    {
        $dem = 0;
        $mong = $tu;

        do {
            $ds = DB::table('user_daily_stats')->where('user_id', $userId)->where('day_start', '<=', $mong)->whereRaw('reviews + new_cards > 0')
                ->orderByDesc('day_start')->limit(400)->pluck('day_start');

            foreach ($ds as $d) {
                if ((int) $d !== $mong) {
                    return $dem;
                }

                $dem++;
                $mong = LapLich::dauNgayTruoc($mong, $tz);
            }
        } while ($ds->count() === 400);

        return $dem;
    }

    /**
     * Sổ xu: một dòng mỗi phiên có xu trong lô (lượt không gắn phiên gom một dòng).
     *
     * @param  list<array<string, mixed>>  $ds
     */
    private static function ghiSoXu(int $userId, array $ds, int $soDu, int $luc): void
    {
        $theoPhien = [];

        foreach ($ds as $l) {
            $k = $l['session_id'] ?? 0;
            $theoPhien[$k] = ($theoPhien[$k] ?? 0) + $l['xu'];
        }

        foreach ($theoPhien as $phien => $xu) {
            if ($xu <= 0) {
                continue;
            }

            $soDu += $xu;
            DB::table('coin_transactions')->insert([
                'user_id' => $userId,
                'amount' => $xu,
                'reason' => 'study',
                'ref_type' => $phien !== 0 ? 'study_session' : null,
                'ref_id' => $phien !== 0 ? $phien : null,
                'balance_after' => $soDu,
                'created_at' => $luc,
            ]);
        }
    }

    /**
     * @param  list<string>  $trung
     * @param  list<string>  $bo
     * @param  array<int, array{box: int, due_at: int|null}>  $tienDo
     * @return array<string, mixed>
     */
    private static function ketQua(User $user, int $nhan, array $trung, array $bo, int $xu, int $quaNhanh, array $tienDo): array
    {
        $tz = $user->timezone;
        $now = now()->getTimestamp();
        $homNay = LapLich::dauNgay($now, $tz);
        $tong = DB::table('user_stats')->where('user_id', $user->id)->first();
        $ngay = DB::table('user_daily_stats')->where('user_id', $user->id)->where('day_start', $homNay)->first();
        $homQua = LapLich::dauNgayTruoc($homNay, $tz);

        return [
            'received' => $nhan,
            'duplicates' => count($trung),
            'skipped' => $bo,
            'coins' => $xu,
            'too_fast' => $quaNhanh,
            'cards' => array_map(fn (int $id) => ['id' => $id, 'box' => $tienDo[$id]['box'], 'due_at' => $tienDo[$id]['due_at']], array_keys($tienDo)),
            'today' => [
                'reviewed' => $ngay !== null ? (int) $ngay->reviews + (int) $ngay->new_cards : 0,
                'goal' => UserSetting::cua($user)['daily_goal'],
                'coins' => $tong !== null ? (int) $tong->coins_balance : 0,
                'streak' => $tong !== null && $tong->streak_last_day !== null && (int) $tong->streak_last_day >= $homQua ? (int) $tong->streak_current : 0,
            ],
        ];
    }
}
