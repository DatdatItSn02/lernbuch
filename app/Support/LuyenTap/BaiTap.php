<?php

namespace App\Support\LuyenTap;

use App\Models\User;
use App\Support\Hoc\LapLich;
use App\Support\NoiDung;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use stdClass;

/**
 * Bài tập theo Lernbuch (exercises.js, main.py: /ex/sets, /ex/session, /ex/result): bộ bài theo cấp, Ôn tập, Số nhiều;
 * phiên làm bài (theo bộ, Luyện nhanh, Ôn câu sai); ghi kết quả (máy chủ chấm lại, đếm đúng liên tiếp, cộng xu).
 */
final class BaiTap
{
    /** Ngôn ngữ giải thích của bài tập; G1 chỉ có tiếng Việt. */
    public const BASE_LANG = 'vi';

    /** Xu cho mỗi câu đúng (Lernbuch EX_COINS): câu bài tập 5, câu chia động từ 10. */
    public const XU_CAU = 5;

    public const XU_DONG_TU = 10;

    /** Loại lượt làm bài (exercise_runs.mode): theo bộ, Luyện nhanh, Số nhiều, Ôn câu sai, Chia động từ. */
    public const LOAI = ['set', 'quick', 'plural', 'wrong_review', 'conjugate'];

    /** Chấm ngay từng câu, hoặc làm cả phiếu rồi chấm. */
    public const CACH_CHAM = ['instant', 'sheet'];

    public const N_TOI_THIEU = 3;

    public const N_TOI_DA = 60;

    /** Một lần gửi kết quả có tối đa chừng này câu. */
    public const CAU_TOI_DA = 100;

    /** Trung bình mỗi câu nhanh hơn chừng này (mili giây) thì vẫn ghi kết quả nhưng không cộng xu. */
    public const MS_MOI_CAU = 1000;

    /** Đúng liên tiếp chừng này lần thì câu tính là đã thuộc (Lernbuch: streak ≥ 2). */
    public const DA_THUOC = 2;

    /** Bộ Ôn tập (tài liệu "Chương ngữ pháp") có slug bắt đầu bằng chữ này: để riêng, không trộn vào Luyện nhanh. */
    public const TIEN_TO_ON_TAP = 'dx-';

    /** Giờ trên máy được chạy nhanh hơn giờ máy chủ tối đa chừng này; quá thì lấy giờ máy chủ. */
    private const LECH_GIO_MS = 60_000;

    /** Bộ bài đã xuất bản của khoá học, giải thích bằng tiếng Việt. */
    public static function cacBo(): Builder
    {
        return self::locBo(DB::table('exercise_sets'));
    }

    /** Câu đã xuất bản thuộc bộ đã xuất bản (đã nối bảng exercise_sets). */
    public static function cacCau(): Builder
    {
        return self::locBo(DB::table('exercises')->join('exercise_sets', 'exercise_sets.id', '=', 'exercises.set_id'))
            ->where('exercises.status', 'published')->whereNull('exercises.deleted_at');
    }

    private static function locBo(Builder $q): Builder
    {
        return $q->where('exercise_sets.lang', NoiDung::LANG)->where('exercise_sets.base_lang', self::BASE_LANG)
            ->where('exercise_sets.status', 'published')->whereNull('exercise_sets.deleted_at');
    }

    /**
     * Các bộ bài kèm tiến độ của người học (số câu, đã thuộc, đang sai), bài ngữ pháp và chương mà bộ gắn vào.
     *
     * @return array{sets: list<array<string, mixed>>, lessons: list<array<string, mixed>>, chapters: list<array<string, mixed>>}
     */
    public static function danhSach(User $user): array
    {
        $bo = self::cacBo()->orderBy('sort')->orderBy('id')
            ->get(['id', 'slug', 'title', 'section', 'level', 'grammar_lesson_id', 'description', 'group_name']);
        $cot = 'exercises.set_id, COUNT(*) AS n, SUM(CASE WHEN s.correct_streak >= ? THEN 1 ELSE 0 END) AS done, '
            .'SUM(CASE WHEN s.exercise_id IS NOT NULL AND s.correct_streak = 0 THEN 1 ELSE 0 END) AS weak';
        $dem = self::cacCau()
            ->leftJoin('user_exercise_stats as s', fn (JoinClause $j) => $j->on('s.exercise_id', '=', 'exercises.id')->where('s.user_id', '=', $user->id))
            ->groupBy('exercises.set_id')
            ->selectRaw($cot, [self::DA_THUOC])
            ->get()->keyBy('set_id');

        $baiIds = $bo->pluck('grammar_lesson_id')->filter()->unique()->values()->all();
        $bai = NguPhap::cacBai()->whereIn('id', $baiIds)->orderBy('sort')->orderBy('id')->get(['id', 'slug', 'title', 'level', 'chapter_id'])->keyBy('id');
        $chuong = NguPhap::cacChuong($bai->pluck('chapter_id')->filter()->unique()->values()->all());
        $maChuong = array_column($chuong, 'code', 'id');

        $sets = [];

        foreach ($bo as $b) {
            $d = $dem[$b->id] ?? null;

            if ($d === null) {
                continue;
            }

            $baiCua = $b->grammar_lesson_id !== null ? ($bai[$b->grammar_lesson_id] ?? null) : null;
            $sets[] = [
                'slug' => (string) $b->slug,
                'title' => (string) $b->title,
                'section' => (string) $b->section,
                'level' => NoiDung::CAP[(int) $b->level] ?? null,
                'lesson' => $baiCua !== null ? (string) $baiCua->slug : null,
                'group' => $b->group_name !== null ? (string) $b->group_name : null,
                'note' => $b->description !== null ? (string) $b->description : null,
                'count' => (int) $d->n,
                'done' => (int) $d->done,
                'weak' => (int) $d->weak,
            ];
        }

        $lessons = [];

        foreach ($bai as $x) {
            $lessons[] = [
                'slug' => (string) $x->slug,
                'title' => (string) $x->title,
                'level' => NoiDung::CAP[(int) $x->level] ?? null,
                'chapter' => $x->chapter_id !== null ? ($maChuong[(int) $x->chapter_id] ?? null) : null,
            ];
        }

        return [
            'sets' => $sets,
            'lessons' => $lessons,
            'chapters' => array_map(fn (array $c) => array_diff_key($c, ['id' => 0]), $chuong),
        ];
    }

    /**
     * Câu cho một phiên làm bài (Lernbuch /ex/session). Có bộ và theo thứ tự thì lấy đúng thứ tự trong bộ;
     * còn lại ưu tiên câu đang sai, rồi câu chưa làm, câu mới đúng một lần, câu đã thuộc; cùng nhóm thì ngẫu nhiên.
     * Không chọn bộ (Luyện nhanh) thì không lấy bộ Ôn tập; Ôn câu sai lấy câu đã làm mà chưa thuộc ở mọi bộ.
     *
     * @param  list<string>  $boSlug
     * @param  list<int>  $cap  cấp của bộ; bộ không có cấp (Số nhiều, Ôn tập từ vựng) hợp mọi cấp
     * @return list<array<string, mixed>>
     */
    public static function phien(User $user, array $boSlug, array $cap, bool $chiSai, int $n, bool $theoThuTu): array
    {
        $n = max(self::N_TOI_THIEU, min(self::N_TOI_DA, $n));
        $q = self::cacCau()
            ->leftJoin('user_exercise_stats as s', fn (JoinClause $j) => $j->on('s.exercise_id', '=', 'exercises.id')->where('s.user_id', '=', $user->id));

        if ($boSlug !== []) {
            $q->whereIn('exercise_sets.slug', $boSlug);
        }

        if ($cap !== []) {
            $q->where(fn (Builder $w) => $w->whereIn('exercise_sets.level', $cap)->orWhereNull('exercise_sets.level'));
        }

        if ($chiSai) {
            $q->whereNotNull('s.exercise_id')->where('s.correct_streak', '<', self::DA_THUOC);
        } elseif ($boSlug === []) {
            $q->where('exercise_sets.slug', 'not like', self::TIEN_TO_ON_TAP.'%');
        }

        $theoBo = $theoThuTu && $boSlug !== [];

        if ($theoBo) {
            $q->orderBy('exercise_sets.sort')->orderBy('exercise_sets.id')->orderBy('exercises.sort')->orderBy('exercises.id');
        } else {
            $q->orderByRaw('CASE WHEN s.exercise_id IS NULL THEN 1 WHEN s.correct_streak = 0 THEN 0 WHEN s.correct_streak = 1 THEN 2 ELSE 3 END')
                ->inRandomOrder();
        }

        $dong = $q->limit($n)->get([
            'exercises.id', 'exercises.kind', 'exercises.prompt', 'exercises.answers', 'exercises.choices', 'exercises.hint',
            'exercises.explanation', 'exercises.extra', 'exercise_sets.slug as set_slug', 'exercise_sets.title as set_title',
            'exercise_sets.group_name as set_group', 'exercise_sets.description as set_note',
        ]);

        $ds = [];

        foreach ($dong as $e) {
            $cau = self::cauCham($e);
            $extra = self::extra($e);
            $ds[] = [
                'id' => (int) $e->id,
                'kind' => $cau['kind'],
                'prompt' => $cau['prompt'],
                'answers' => $cau['answers'],
                'options' => $cau['options'] !== null && ! ($extra['fixed'] ?? false) ? self::tron($cau['options']) : $cau['options'],
                'hint' => $e->hint !== null ? (string) $e->hint : null,
                'explanation' => $e->explanation !== null && $e->explanation !== '' ? (string) $e->explanation : null,
                'label' => is_string($extra['label'] ?? null) ? $extra['label'] : null,
                'instr' => is_string($extra['instr'] ?? null) ? $extra['instr'] : null,
                'case_sensitive' => $cau['case_sensitive'],
                'bank' => self::dsChu($extra['bank'] ?? null),
                'tokens' => self::dsChu($extra['tokens'] ?? null),
                'lead' => is_string($extra['lead'] ?? null) && $extra['lead'] !== '' ? $extra['lead'] : null,
                'frag' => $cau['frag'],
                'set' => [
                    'slug' => (string) $e->set_slug,
                    'title' => (string) $e->set_title,
                    'group' => $e->set_group !== null ? (string) $e->set_group : null,
                    'note' => $e->set_note !== null ? (string) $e->set_note : null,
                ],
            ];
        }

        return $theoBo ? $ds : self::tron($ds);
    }

    /**
     * Câu ở dạng để chấm: đáp án, lựa chọn (trắc nghiệm, đủ cả đáp án đúng), phân biệt hoa thường, phần đã sửa (câu sửa lỗi).
     * Như Lernbuch: đáp án lưu khác lựa chọn chỉ ở hoa thường hay dấu câu thì dùng đúng chữ của lựa chọn.
     *
     * @return array{kind: string, prompt: string, answers: list<string>, options: list<string>|null, case_sensitive: bool, frag: list<string>}
     */
    public static function cauCham(stdClass $e): array
    {
        $dapAn = self::dsChu(json_decode((string) $e->answers, true)) ?? [];
        $luaChon = self::dsChu($e->choices !== null ? json_decode((string) $e->choices, true) : null);
        $extra = self::extra($e);

        if ($luaChon !== null) {
            $nk = fn (string $t) => (string) preg_replace('/[^\p{L}\p{N}_]+/u', '', mb_strtolower($t));

            if ($dapAn !== []) {
                foreach ($luaChon as $o) {
                    if ($nk($o) === $nk($dapAn[0])) {
                        if ($o !== $dapAn[0]) {
                            $dapAn[0] = $o;
                        }

                        break;
                    }
                }

                if (! in_array($dapAn[0], $luaChon, true)) {
                    $luaChon[] = $dapAn[0];
                }
            }

            $luaChon = array_values(array_unique($luaChon));
        }

        return [
            'kind' => (string) $e->kind,
            'prompt' => (string) $e->prompt,
            'answers' => $dapAn,
            'options' => $luaChon,
            'case_sensitive' => (bool) ($extra['case_sensitive'] ?? false),
            'frag' => self::dsChu($extra['frag'] ?? null) ?? [],
        ];
    }

    /**
     * Ghi một lượt làm bài. Máy chủ chấm lại từng câu theo chữ người học đã trả lời (cùng quy tắc với app), mỗi câu tính
     * lần trả lời đầu tiên. Gửi lại cùng mã (uid) thì không ghi lần hai, xu không cộng lần hai.
     *
     * @param  array{uid: string, mode: string, grading: string, set: string|null, started_at_ms: int, finished_at_ms: int, items: list<array{id?: int, sig?: string, answer: string}>}  $vao
     * @return array<string, mixed>
     */
    public static function ghiKetQua(User $user, array $vao): array
    {
        return DB::transaction(function () use ($user, $vao) {
            $now = now();
            $giay = $now->getTimestamp();

            // Khoá theo người như lượt chấm thẻ: mỗi lúc một lần ghi của người này
            DB::table('user_stats')->insertOrIgnore(['user_id' => $user->id, 'updated_at' => $giay]);
            $tong = DB::table('user_stats')->where('user_id', $user->id)->lockForUpdate()->first();

            $cu = DB::table('exercise_runs')->where('user_id', $user->id)->where('client_uid', $vao['uid'])->first();

            if ($cu !== null) {
                return [
                    'duplicate' => true,
                    'coins' => (int) $cu->coins,
                    'correct' => (int) $cu->correct,
                    'total' => (int) $cu->total,
                    'too_fast' => $cu->no_coin_reason !== null,
                    'items' => [],
                    'today' => self::homNay($user),
                ];
            }

            $dongTu = $vao['mode'] === 'conjugate';
            $cham = $dongTu ? self::chamDongTu($vao['items']) : self::chamCau($vao['items']);

            if ($cham === []) {
                throw ValidationException::withMessages(['items' => __('api.khong_co_cau')]);
            }

            $dung = count(array_filter($cham, fn (array $c) => $c['score'] === ChamBaiTap::DUNG));
            $tongCau = count($cham);
            $nowMs = (int) $now->getTimestampMs();
            $xongMs = $vao['finished_at_ms'] > $nowMs + self::LECH_GIO_MS ? $nowMs : $vao['finished_at_ms'];
            $batDauMs = min($vao['started_at_ms'], $xongMs);
            $quaNhanh = $xongMs - $batDauMs < $tongCau * self::MS_MOI_CAU;
            $xu = $quaNhanh ? 0 : $dung * ($dongTu ? self::XU_DONG_TU : self::XU_CAU);
            $xong = intdiv($xongMs, 1000);

            $boId = $vao['set'] !== null ? self::cacBo()->where('slug', $vao['set'])->value('id') : null;
            $runId = (int) DB::table('exercise_runs')->insertGetId([
                'user_id' => $user->id,
                'client_uid' => $vao['uid'],
                'set_id' => $boId,
                'mode' => $vao['mode'],
                'grading' => $vao['grading'],
                'total' => $tongCau,
                'correct' => $dung,
                'coins' => $xu,
                'no_coin_reason' => $quaNhanh ? 'too_fast' : null,
                'started_at' => intdiv($batDauMs, 1000),
                'finished_at' => $xong,
            ]);

            if (! $dongTu) {
                self::ghiThongKeCau($user->id, $cham, $xong);
            }

            if ($xu > 0) {
                $soDu = (int) $tong->coins_balance + $xu;
                DB::table('user_stats')->where('user_id', $user->id)->update([
                    'coins_total' => (int) $tong->coins_total + $xu,
                    'coins_balance' => $soDu,
                    'updated_at' => $giay,
                ]);
                DB::table('coin_transactions')->insert([
                    'user_id' => $user->id,
                    'amount' => $xu,
                    'reason' => 'exercise',
                    'ref_type' => 'exercise_run',
                    'ref_id' => $runId,
                    'balance_after' => $soDu,
                    'created_at' => $giay,
                ]);
            }

            self::ghiNgay($user, $xong, $tongCau, $xu);

            return [
                'duplicate' => false,
                'coins' => $xu,
                'correct' => $dung,
                'total' => $tongCau,
                'too_fast' => $quaNhanh,
                'items' => array_map(fn (array $c) => array_diff_key($c, ['answer' => 0]), $cham),
                'today' => self::homNay($user),
            ];
        }, 3);
    }

    /**
     * Chấm lại câu bài tập; câu không còn (đã gỡ) thì bỏ, một câu gửi hai lần thì lấy lần đầu.
     *
     * @param  list<array{id?: int, sig?: string, answer: string}>  $items
     * @return list<array{id: int, score: int, answer: string}>
     */
    private static function chamCau(array $items): array
    {
        $ids = array_values(array_unique(array_map(fn (array $i) => (int) ($i['id'] ?? 0), $items)));
        $cau = self::cacCau()->whereIn('exercises.id', $ids)
            ->get(['exercises.id', 'exercises.kind', 'exercises.prompt', 'exercises.answers', 'exercises.choices', 'exercises.extra'])
            ->keyBy('id');
        $ra = [];

        foreach ($items as $i) {
            $id = (int) ($i['id'] ?? 0);

            if (isset($ra[$id]) || ! isset($cau[$id])) {
                continue;
            }

            $ra[$id] = ['id' => $id, 'score' => ChamBaiTap::cham(self::cauCham($cau[$id]), $i['answer']), 'answer' => $i['answer']];
        }

        return array_values($ra);
    }

    /**
     * Chấm lại câu chia động từ theo mã câu (v:động từ:dạng); động từ không còn thì bỏ.
     *
     * @param  list<array{id?: int, sig?: string, answer: string}>  $items
     * @return list<array{sig: string, score: int, answer: string}>
     */
    private static function chamDongTu(array $items): array
    {
        $ra = [];

        foreach ($items as $i) {
            $sig = (string) ($i['sig'] ?? '');

            if ($sig === '' || isset($ra[$sig])) {
                continue;
            }

            $dapAn = DongTu::dapAnLuyen($sig);

            if ($dapAn === null) {
                continue;
            }

            $cau = ['kind' => 'fill', 'prompt' => '', 'answers' => $dapAn, 'options' => null, 'case_sensitive' => false, 'frag' => []];
            $ra[$sig] = ['sig' => $sig, 'score' => ChamBaiTap::cham($cau, $i['answer']), 'answer' => $i['answer']];
        }

        return array_values($ra);
    }

    /**
     * Đúng/sai từng câu: đếm đúng, sai, số lần đúng liên tiếp (sai thì về 0). Kết quả tới trễ (làm trước lần đã ghi)
     * chỉ cộng vào số đếm, không đổi chuỗi đúng và lần trả lời cuối.
     *
     * @param  list<array{id: int, score: int, answer: string}>  $cham
     */
    private static function ghiThongKeCau(int $userId, array $cham, int $luc): void
    {
        $cu = DB::table('user_exercise_stats')->where('user_id', $userId)->whereIn('exercise_id', array_column($cham, 'id'))->get()->keyBy('exercise_id');
        $hang = [];

        foreach ($cham as $c) {
            $p = $cu[$c['id']] ?? null;
            $dung = $c['score'] === ChamBaiTap::DUNG;
            $truoc = $p !== null
                ? ['correct_count' => (int) $p->correct_count, 'wrong_count' => (int) $p->wrong_count, 'correct_streak' => (int) $p->correct_streak,
                    'last_correct' => $p->last_correct, 'last_answer' => $p->last_answer, 'last_at' => $p->last_at !== null ? (int) $p->last_at : null]
                : ['correct_count' => 0, 'wrong_count' => 0, 'correct_streak' => 0, 'last_correct' => null, 'last_answer' => null, 'last_at' => null];
            $moiHon = $truoc['last_at'] === null || $luc >= $truoc['last_at'];
            $hang[] = [
                'user_id' => $userId,
                'exercise_id' => $c['id'],
                'correct_count' => min(65535, $truoc['correct_count'] + ($dung ? 1 : 0)),
                'wrong_count' => min(65535, $truoc['wrong_count'] + ($dung ? 0 : 1)),
                'correct_streak' => $moiHon ? ($dung ? min(255, $truoc['correct_streak'] + 1) : 0) : $truoc['correct_streak'],
                'last_correct' => $moiHon ? ($dung ? 1 : 0) : $truoc['last_correct'],
                'last_answer' => $moiHon ? mb_substr($c['answer'], 0, 500) : $truoc['last_answer'],
                'last_at' => $moiHon ? $luc : $truoc['last_at'],
            ];
        }

        DB::table('user_exercise_stats')->upsert(
            $hang,
            ['user_id', 'exercise_id'],
            ['correct_count', 'wrong_count', 'correct_streak', 'last_correct', 'last_answer', 'last_at'],
        );
    }

    /** Số liệu ngày: số câu bài tập đã làm và xu (bài tập không tính vào mục tiêu ngày và chuỗi ngày, như Lernbuch). */
    private static function ghiNgay(User $user, int $luc, int $soCau, int $xu): void
    {
        $ngay = LapLich::dauNgay($luc, $user->timezone);
        $co = DB::table('user_daily_stats')->where('user_id', $user->id)->where('day_start', $ngay)->first();

        if ($co === null) {
            DB::table('user_daily_stats')->insert(['user_id' => $user->id, 'day_start' => $ngay, 'exercises' => min(65535, $soCau), 'coins' => $xu]);

            return;
        }

        DB::table('user_daily_stats')->where('user_id', $user->id)->where('day_start', $ngay)->update([
            'exercises' => min(65535, (int) $co->exercises + $soCau),
            'coins' => (int) $co->coins + $xu,
        ]);
    }

    /** @return array{coins: int, coins_today: int, exercises_today: int} */
    private static function homNay(User $user): array
    {
        $ngay = DB::table('user_daily_stats')->where('user_id', $user->id)->where('day_start', LapLich::dauNgay(now()->getTimestamp(), $user->timezone))->first();

        return [
            'coins' => (int) DB::table('user_stats')->where('user_id', $user->id)->value('coins_balance'),
            'coins_today' => $ngay !== null ? (int) $ngay->coins : 0,
            'exercises_today' => $ngay !== null ? (int) $ngay->exercises : 0,
        ];
    }

    /** @return array<string, mixed> */
    private static function extra(stdClass $e): array
    {
        $x = $e->extra !== null ? json_decode((string) $e->extra, true) : null;

        return is_array($x) ? $x : [];
    }

    /** @return list<string>|null danh sách chữ (bỏ giá trị không phải chữ); không phải danh sách thì null */
    private static function dsChu(mixed $v): ?array
    {
        if (! is_array($v)) {
            return null;
        }

        return array_values(array_map('strval', array_filter($v, fn ($x) => is_string($x) || is_int($x) || is_float($x))));
    }

    /**
     * @template T
     *
     * @param  list<T>  $ds
     * @return list<T>
     */
    private static function tron(array $ds): array
    {
        shuffle($ds);

        return $ds;
    }
}
