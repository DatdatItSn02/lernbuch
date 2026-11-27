<?php

namespace App\Support\Hoc;

use App\Models\User;
use App\Models\UserSetting;
use App\Support\KhoaHoc;
use App\Support\NoiDung;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Phiên học như Lernbuch: thẻ đến hạn hôm nay (quá hạn lâu nhất ra trước) cộng số từ mới đã chọn, xen kẽ ôn và mới.
 * Từ mới lấy theo thứ tự chủ đề: hết từ mới của chủ đề đang học mới sang chủ đề kế (cùng loại thẻ), không trộn chủ đề.
 */
final class PhienHoc
{
    /** Số thẻ đến hạn tối đa mỗi phiên (Lernbuch: 50); còn nữa thì "Ôn tiếp" mở phiên mới. */
    public const ON_TOI_DA = 50;

    /** "Tất cả từ mới" vẫn chặn ở số này trong một phiên. */
    public const MOI_TOI_DA = 200;

    /** Loại thẻ bắt đầu khi người học chưa có chủ đề đang học. */
    public const BO_DAU = 'vocab';

    /** Chế độ chỉ học thẻ có câu ví dụ chính (Đặt câu: sắp xếp lại các chữ của câu). */
    public const CHE_DO_CAN_VI_DU = ['sentence'];

    /** Chế độ cần đáp án nhiễu: chọn đáp án đúng, ghép cặp, hỗn hợp (có lượt chọn đáp án). */
    public const CHE_DO_CO_NHIEU = ['quiz', 'match', 'mixed'];

    /** Số đáp án nhiễu mỗi thẻ (Quiz cần 3; Ghép cặp lấy thêm cặp nhiễu khi một vòng có ít thẻ). */
    public const SO_NHIEU = 6;

    /** Thẻ người học được xem, kèm tiến độ của họ (p.*; thẻ chưa học thì p.card_id là NULL). */
    public static function theVaTienDo(User $user): Builder
    {
        return NoiDung::cacThe($user)->leftJoin('card_progress as p', function (JoinClause $j) use ($user): void {
            $j->on('p.card_id', '=', 'cards.id')->where('p.user_id', '=', $user->id);
        });
    }

    /** Thẻ đến hạn trước $truocLuc (thường là đầu ngày mai): thẻ đã học có hạn từ hôm nay trở về trước, kể cả thẻ vừa Quên. */
    public static function denHan(User $user, int $truocLuc): Builder
    {
        return NoiDung::cacThe($user)->join('card_progress as p', function (JoinClause $j) use ($user): void {
            $j->on('p.card_id', '=', 'cards.id')->where('p.user_id', '=', $user->id);
        })->where('p.box', '>', 0)->where('p.due_at', '<', $truocLuc);
    }

    /**
     * Lập một phiên và ghi vào study_sessions. $chuDe: học riêng chủ đề này (ôn và mới đều trong chủ đề);
     * null: ôn mọi chủ đề, từ mới theo chủ đề đang học. $chiOn: không lấy từ mới.
     * Đặt câu chỉ lấy thẻ có câu ví dụ và không đổi chủ đề đang học (để Flashcard vẫn học đủ chủ đề, kể cả thẻ không có câu).
     *
     * @return array<string, mixed>
     */
    public static function tao(User $user, ?stdClass $chuDe, bool $chiOn, string $cheDo): array
    {
        $now = now()->getTimestamp();
        $on = self::layOn($user, $chuDe, $now, $cheDo);
        [$moi, $chuDeMoi] = $chiOn ? [[], null] : self::layTuMoi($user, $chuDe, ! in_array($cheDo, self::CHE_DO_CAN_VI_DU, true), $cheDo);
        $khoa = KhoaHoc::hienTai($user);

        $id = (int) DB::table('study_sessions')->insertGetId([
            'user_id' => $user->id,
            'course_id' => $khoa?->id,
            'topic_id' => $chuDe?->id,
            'mode' => $cheDo,
            'planned_new' => count($moi),
            'planned_review' => count($on),
            'client' => 'web',
            'started_at' => $now,
        ]);

        return [
            'id' => $id,
            'mode' => $cheDo,
            'started_at' => $now,
            'topic' => $chuDe !== null ? NoiDung::tenGon($chuDe) : null,
            'new_topic' => $chuDeMoi !== null ? NoiDung::tenGon($chuDeMoi) : null,
            'counts' => ['review' => count($on), 'new' => count($moi)],
            'cards' => self::theChoPhien($user, self::xenKe($on, $moi), $cheDo),
        ];
    }

    /**
     * Phiên kế tiếp trong cùng phạm vi sẽ có bao nhiêu thẻ (để bảng tổng kết hiện "Học tiếp"); không ghi gì.
     *
     * @return array{review: int, new: int}
     */
    public static function conLai(User $user, ?stdClass $chuDe, bool $chiOn, string $cheDo = 'flashcard'): array
    {
        $hetHom = LapLich::dauNgayMai(now()->getTimestamp(), $user->timezone);
        $on = self::locCheDo(self::denHan($user, $hetHom), $cheDo)->when($chuDe, fn (Builder $q, stdClass $t) => $q->where('cards.topic_id', $t->id))->count();

        return ['review' => min($on, self::ON_TOI_DA), 'new' => $chiOn ? 0 : count(self::layTuMoi($user, $chuDe, false, $cheDo)[0])];
    }

    /** Lọc thẻ theo chế độ: Đặt câu chỉ lấy thẻ có câu ví dụ chính. */
    private static function locCheDo(Builder $q, string $cheDo): Builder
    {
        if (! in_array($cheDo, self::CHE_DO_CAN_VI_DU, true)) {
            return $q;
        }

        return $q->whereExists(fn (Builder $c) => $c->selectRaw('1')->from('card_examples as ex')->whereColumn('ex.card_id', 'cards.id')->where('ex.sort', 0));
    }

    /**
     * Từ mới của phiên tới, theo chủ đề đang học; không ghi gì (Hôm nay hiện số này).
     *
     * @return array{0: list<int>, 1: stdClass|null} id thẻ, chủ đề có từ mới cuối cùng
     */
    public static function xemTuMoi(User $user): array
    {
        return self::layTuMoi($user, null, false);
    }

    /**
     * Tiến độ của người học trong một chủ đề: đến hạn hôm nay, chưa học, số thẻ theo 6 mức.
     *
     * @return array{due: int, new: int, levels: list<int>}
     */
    public static function tienDoChuDe(User $user, stdClass $t): array
    {
        $hetHom = LapLich::dauNgayMai(now()->getTimestamp(), $user->timezone);
        $muc = self::demTheoMuc(self::theVaTienDo($user)->where('cards.topic_id', $t->id));

        return ['due' => self::denHan($user, $hetHom)->where('cards.topic_id', $t->id)->count(), 'new' => $muc[0], 'levels' => $muc];
    }

    /** @return list<int> số thẻ theo mức 0–5 (0 = chưa học) */
    public static function demTheoMuc(Builder $theVaTienDo): array
    {
        $dem = $theVaTienDo->selectRaw('COALESCE(p.box, 0) AS muc, COUNT(*) AS n')->groupByRaw('COALESCE(p.box, 0)')->pluck('n', 'muc');

        return array_map(fn (int $m) => (int) ($dem[$m] ?? 0), range(0, LapLich::MUC_TOI_DA));
    }

    /**
     * Xen từ mới vào giữa thẻ ôn như Lernbuch: cứ k thẻ ôn thì một từ mới (k = số ôn / số mới), còn dư để cuối.
     *
     * @param  list<int>  $on
     * @param  list<int>  $moi
     * @return list<int>
     */
    public static function xenKe(array $on, array $moi): array
    {
        if ($on === [] || $moi === []) {
            return [...$on, ...$moi];
        }

        $k = max(1, intdiv(count($on), count($moi)));
        $ra = [];
        $j = 0;

        foreach ($on as $i => $id) {
            $ra[] = $id;

            if ($j < count($moi) && ($i + 1) % $k === 0) {
                $ra[] = $moi[$j++];
            }
        }

        return [...$ra, ...array_slice($moi, $j)];
    }

    /** Chủ đề nhỏ đang học (học từ mới): đã chọn trước đó, chưa có (hoặc đã ẩn) thì chủ đề đầu tiên của Từ vựng. */
    public static function chuDeDangHoc(User $user): ?stdClass
    {
        $id = self::idChuDeDaLuu($user);
        $t = $id !== null ? NoiDung::cacChuDe()->where('id', $id)->where('depth', 3)->first() : null;

        return $t ?? (self::thuTuChuDe(self::BO_DAU)[0] ?? null);
    }

    private static function idChuDeDaLuu(User $user): ?int
    {
        $khoa = KhoaHoc::hienTai($user);
        $id = $khoa !== null ? DB::table('user_courses')->where('user_id', $user->id)->where('course_id', $khoa->id)->value('current_topic_id') : null;

        return $id !== null ? (int) $id : null;
    }

    /**
     * Chủ đề nhỏ của một loại thẻ theo thứ tự hiện trên trang Chủ đề (nhóm › chủ đề vừa › chủ đề nhỏ).
     *
     * @return list<stdClass>
     */
    public static function thuTuChuDe(string $bo): array
    {
        $hien = function (Builder $q, string $bang): void {
            $q->where("{$bang}.lang", NoiDung::LANG)->where("{$bang}.status", 'published')->whereNull("{$bang}.deleted_at");
        };

        /** @var list<stdClass> $ds */
        $ds = DB::table('topics as t')->join('topics as v', 'v.id', '=', 't.parent_id')->join('topics as n', 'n.id', '=', 'v.parent_id')
            ->where('t.depth', 3)->where('t.collection', $bo)
            ->where(fn (Builder $q) => $hien($q, 't'))->where(fn (Builder $q) => $hien($q, 'v'))->where(fn (Builder $q) => $hien($q, 'n'))
            ->orderBy('n.sort')->orderBy('n.id')->orderBy('v.sort')->orderBy('v.id')->orderBy('t.sort')->orderBy('t.id')
            ->get(['t.id', 't.slug', 't.name_vi', 't.name_en', 't.collection'])->all();

        return $ds;
    }

    /** @return list<int> */
    private static function layOn(User $user, ?stdClass $chuDe, int $now, string $cheDo): array
    {
        return self::locCheDo(self::denHan($user, LapLich::dauNgayMai($now, $user->timezone)), $cheDo)
            ->when($chuDe, fn (Builder $q, stdClass $t) => $q->where('cards.topic_id', $t->id))
            ->orderBy('p.due_at')->orderBy('cards.id')->limit(self::ON_TOI_DA)->pluck('cards.id')->map(fn ($v) => (int) $v)->all();
    }

    /**
     * Từ mới cho một phiên. Có $chuDe: chỉ trong chủ đề đó. Không có: từ chủ đề đang học, hết thì sang chủ đề kế
     * (quay vòng) cho đủ số; chọn "tất cả" thì lấy hết từ mới của một chủ đề rồi dừng. $ghi: nhớ chủ đề đang học mới.
     *
     * @return array{0: list<int>, 1: stdClass|null}
     */
    private static function layTuMoi(User $user, ?stdClass $chuDe, bool $ghi, string $cheDo = 'flashcard'): array
    {
        $muon = UserSetting::cua($user)['new_per_session'];
        $tatCa = $muon === 0;
        $n = $tatCa ? self::MOI_TOI_DA : $muon;

        if ($chuDe !== null) {
            $ids = self::moiCua($user, $chuDe, $n, $cheDo);

            if ($ids !== [] && $ghi && (int) $chuDe->id !== self::idChuDeDaLuu($user)) {
                self::datChuDeDangHoc($user, $chuDe);
            }

            return [$ids, $ids !== [] ? $chuDe : null];
        }

        $dang = self::chuDeDangHoc($user);

        if ($dang === null) {
            return [[], null];
        }

        $ids = self::moiCua($user, $dang, $n, $cheDo);
        $cuoi = $ids !== [] ? $dang : null;

        if (count($ids) < $n && ! ($tatCa && $ids !== [])) {
            // Chủ đề đang học hết từ mới: đi tiếp theo thứ tự (quay vòng), chỉ qua các chủ đề còn từ mới
            $conMoi = self::locCheDo(self::chuaHoc($user), $cheDo)->where('cards.collection', (string) $dang->collection)->whereNotNull('cards.topic_id')
                ->distinct()->pluck('cards.topic_id')->map(fn ($v) => (int) $v)->flip()->all();
            $ds = self::thuTuChuDe((string) $dang->collection);
            $viTri = (int) array_search((int) $dang->id, array_map(fn (stdClass $t) => (int) $t->id, $ds), true);

            for ($i = 1; $i < count($ds) && count($ids) < $n; $i++) {
                $t = $ds[($viTri + $i) % count($ds)];

                if (! isset($conMoi[(int) $t->id])) {
                    continue;
                }

                $lay = self::moiCua($user, $t, $n - count($ids), $cheDo);
                $ids = [...$ids, ...$lay];
                $cuoi = $lay !== [] ? $t : $cuoi;

                if ($tatCa && $lay !== []) {
                    break;
                }
            }
        }

        if ($ghi && $cuoi !== null && (int) $cuoi->id !== self::idChuDeDaLuu($user)) {
            self::datChuDeDangHoc($user, $cuoi);
        }

        return [$ids, $cuoi];
    }

    /** Thẻ người học được xem mà chưa học lần nào. */
    public static function chuaHoc(User $user): Builder
    {
        return self::theVaTienDo($user)->whereNull('p.card_id');
    }

    /** @return list<int> từ chưa học của một chủ đề, theo thứ tự trong chủ đề */
    private static function moiCua(User $user, stdClass $t, int $n, string $cheDo): array
    {
        if ($n <= 0) {
            return [];
        }

        return NoiDung::xepThe(self::locCheDo(self::chuaHoc($user), $cheDo)->where('cards.topic_id', $t->id))->limit($n)->pluck('cards.id')->map(fn ($v) => (int) $v)->all();
    }

    private static function datChuDeDangHoc(User $user, stdClass $t): void
    {
        $khoa = KhoaHoc::hienTai($user);

        if ($khoa === null) {
            return;
        }

        $now = now()->getTimestamp();
        $da = DB::table('user_courses')->where('user_id', $user->id)->where('course_id', $khoa->id);

        if ($da->exists()) {
            $da->update(['current_topic_id' => $t->id, 'last_studied_at' => $now]);

            return;
        }

        DB::table('user_courses')->insert(['user_id' => $user->id, 'course_id' => $khoa->id, 'current_topic_id' => $t->id, 'enrolled_at' => $now, 'last_studied_at' => $now]);
    }

    /**
     * Thẻ của phiên theo đúng thứ tự: tóm tắt, câu ví dụ chính, chủ đề, mức hiện tại; chế độ cần chọn đáp án thì kèm đáp án nhiễu.
     *
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
     */
    private static function theChoPhien(User $user, array $ids, string $cheDo): array
    {
        if ($ids === []) {
            return [];
        }

        $dong = NoiDung::cacThe($user)->whereIn('cards.id', $ids)->get([...NoiDung::cotTomTat(), 'cards.topic_id'])->keyBy('id');
        $nhieu = in_array($cheDo, self::CHE_DO_CO_NHIEU, true) ? self::dapAnNhieu($user, $dong->values()->all()) : null;
        $tomTat = collect(NoiDung::tomTat($dong->values(), $user))->keyBy('id');
        $viDu = DB::table('card_examples')->whereIn('card_id', $ids)->where('sort', 0)->get(['card_id', 'sentence', 'translations'])->keyBy('card_id');
        $tienDo = DB::table('card_progress')->where('user_id', $user->id)->whereIn('card_id', $ids)->get(['card_id', 'box', 'due_at'])->keyBy('card_id');
        $chuDe = NoiDung::cacChuDe()->whereIn('id', $dong->pluck('topic_id')->filter()->unique()->values()->all())->get()->keyBy('id');
        $ra = [];

        foreach ($ids as $id) {
            if (! isset($tomTat[$id])) {
                continue;
            }

            $v = $viDu[$id] ?? null;
            $t = $chuDe[$dong[$id]->topic_id] ?? null;
            $p = $tienDo[$id] ?? null;
            $ra[] = [
                ...$tomTat[$id],
                'example' => $v !== null ? ['sentence' => (string) $v->sentence, 'translations' => (object) (json_decode((string) $v->translations, true) ?: [])] : null,
                'topic' => $t !== null ? NoiDung::tenGon($t) : null,
                'box' => $p !== null ? (int) $p->box : 0,
                'due_at' => $p !== null && $p->due_at !== null ? (int) $p->due_at : null,
                ...($nhieu !== null ? ['options' => $nhieu[$id] ?? []] : []),
            ];
        }

        return $ra;
    }

    /**
     * Đáp án nhiễu như Lernbuch: thẻ khác cùng loại thẻ và cùng loại từ, ưu tiên cùng chủ đề; thiếu (dưới 4) thì thêm
     * thẻ bất kỳ cùng loại thẻ. Bỏ thẻ trùng mặt trước hay trùng nghĩa tiếng Việt với thẻ đang hỏi, để không có hai đáp án đúng.
     * Lấy một lần cho cả phiên: thẻ của các chủ đề trong phiên, chỉ khi thiếu mới lấy thêm thẻ ngẫu nhiên.
     *
     * @param  list<stdClass>  $the  thẻ của phiên (cột tóm tắt và topic_id)
     * @return array<int, list<array<string, mixed>>> id thẻ → đáp án nhiễu
     */
    private static function dapAnNhieu(User $user, array $the): array
    {
        $cot = ['cards.id', 'cards.collection', 'cards.topic_id', 'cards.front', 'cards.article', 'cards.gender', 'cards.pos'];
        $chuDe = array_values(array_unique(array_filter(array_map(fn (stdClass $t) => $t->topic_id !== null ? (int) $t->topic_id : null, $the))));
        $kho = NoiDung::cacThe($user)->whereIn('cards.topic_id', $chuDe)->get($cot)->keyBy('id')->all();
        $thieu = function (stdClass $t) use ($kho): bool {
            $cungLoaiTu = array_filter($kho, fn (stdClass $x) => $x->collection === $t->collection && $x->pos === $t->pos && (int) $x->id !== (int) $t->id);

            return count($cungLoaiTu) < self::SO_NHIEU;
        };
        $boThieu = array_values(array_unique(array_map(fn (stdClass $t) => (string) $t->collection, array_filter($the, $thieu))));

        if ($boThieu !== []) {
            // Chủ đề nhỏ ít thẻ: thêm thẻ ngẫu nhiên cùng loại thẻ (một lần cho cả phiên)
            foreach (NoiDung::cacThe($user)->whereIn('cards.collection', $boThieu)->inRandomOrder()->limit(self::SO_NHIEU * 10)->get($cot) as $x) {
                $kho[(int) $x->id] ??= $x;
            }
        }

        $nghia = NoiDung::nghia(array_map('intval', array_keys($kho + array_column($the, null, 'id'))));
        $ra = [];

        foreach ($the as $t) {
            $id = (int) $t->id;
            $hopLe = fn (stdClass $x) => (int) $x->id !== $id && $x->collection === $t->collection
                && mb_strtolower((string) $x->front) !== mb_strtolower((string) $t->front)
                && ($nghia[(int) $x->id]['vi'] ?? null) !== ($nghia[$id]['vi'] ?? '');
            $cungLoaiTu = array_filter($kho, fn (stdClass $x) => $hopLe($x) && $x->pos === $t->pos);
            $cungChuDe = array_filter($cungLoaiTu, fn (stdClass $x) => (int) $x->topic_id === (int) $t->topic_id);
            $chon = [...self::tron($cungChuDe), ...self::tron(array_diff_key($cungLoaiTu, $cungChuDe))];

            if (count($chon) < 4) {
                $chon = [...$chon, ...self::tron(array_diff_key(array_filter($kho, $hopLe), $cungLoaiTu))];
            }

            $ra[$id] = array_map(fn (stdClass $x) => ['id' => (int) $x->id, ...NoiDung::matTruoc($x), 'meanings' => (object) ($nghia[(int) $x->id] ?? [])],
                array_slice($chon, 0, self::SO_NHIEU));
        }

        return $ra;
    }

    /**
     * @param  array<int, stdClass>  $ds
     * @return list<stdClass>
     */
    private static function tron(array $ds): array
    {
        $ds = array_values($ds);
        shuffle($ds);

        return $ds;
    }
}
